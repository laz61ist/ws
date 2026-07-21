<?php

declare(strict_types=1);

namespace WABridge\Classify;

use WABridge\Parser\Message;
use WABridge\Support\TurkishText;

/**
 * Deterministik, API'siz sınıflandırıcı. Testler bunu koşar (tekrarlanabilir).
 *
 * Naif keyword eşleme DEĞİL: bileşik sinyaller kullanır —
 *   - Para: tutar token (₺/TL/lira) + (kolektif toplama dili | deadline)
 *   - Etkinlik: gelecek tarih/gün ipucu + (saat | etkinlik adı)
 *   - Görev: imperative fiil + nesne (form/imza/belge/ücret...)
 *   - Oylama: oy/anket/+1/evet-hayır çağrısı
 *   - Gürültü: hiçbiri (selam, teşekkür, dedikodu, medya placeholder)
 *
 * Göreli tarih ("yarın", "Cuma", "haftaya") mesajın kendi zamanına göre çözülür.
 *
 * SINIR: kolektif para-talebi vs kişisel-ödeme-aksiyonu gibi ince ayrımları
 * heuristik %100 yakalayamaz; o kenar durumlar production'da LlmClassifier'a
 * (üst model) aittir. Bu sınıf sinyal/gürültü ayrımını ve yaygın lojistiği
 * güvenilir yakalamak için vardır.
 */
final class HeuristicClassifier implements ClassifierInterface
{
    private const TR_MONTHS = [
        'ocak' => 1, 'şubat' => 2, 'mart' => 3, 'nisan' => 4, 'mayıs' => 5, 'haziran' => 6,
        'temmuz' => 7, 'ağustos' => 8, 'eylül' => 9, 'ekim' => 10, 'kasım' => 11, 'aralık' => 12,
    ];

    private const TR_WEEKDAYS = [
        'pazartesi' => 1, 'salı' => 2, 'çarşamba' => 3, 'perşembe' => 4,
        'cuma' => 5, 'cumartesi' => 6, 'pazar' => 7,
    ];

    private const EVENT_TERMS = [
        'veli toplantısı', 'toplantı', 'gezi', 'tören', 'kermes', 'gösteri', 'sunum',
        'kutlama', 'piknik', 'seminer', 'bilgilendirme', 'kıyafet serbest', 'kıyafet günü',
        'servis', 'karne', 'sınav', 'etkinlik', 'ziyaret', 'gösteri',
    ];

    private const IMPERATIVES = [
        'getirin', 'getiriniz', 'getir', 'imzalayın', 'imzalayınız', 'imzala',
        'doldurun', 'doldurunuz', 'doldur', 'gönderin', 'gönderiniz', 'gönder',
        'hazırlayın', 'hazırlayınız', 'hazırla', 'yollayın', 'yolla', 'yatırın', 'yatır',
        'teslim edin', 'ekleyin', 'işaretleyin', 'unutmayın',
    ];

    private const TASK_OBJECTS = [
        'form', 'imza', 'belge', 'evrak', 'ödev', 'malzeme', 'kıyafet', 'kimlik',
        'fotoğraf', 'foto', 'izin', 'dilekçe', 'karne', 'poşet', 'kitap', 'defter',
    ];

    private const COLLECTIVE_MONEY = [
        'topluyoruz', 'toplanıyor', 'topluyoruz', 'hediye', 'aidat', 'kasa', 'katkı',
        'bağış', 'iban', 'hesap', 'ortak', 'para topl', 'aramızda',
    ];

    public function classify(Message $message): Classification
    {
        $body = $message->body;
        $low = TurkishText::lower($body);

        if ($this->isNoisePlaceholder($low)) {
            return Classification::noise();
        }

        $amount = $this->extractAmount($body, $low);
        $hasImperative = $this->hasImperative($low);
        $hasTaskObject = $this->containsAny($low, self::TASK_OBJECTS);

        // 1) Para: tutar + (kolektif dil | deadline). Kişisel imperative varsa göreve düşer.
        if ($amount !== null) {
            $collective = $this->containsAny($low, self::COLLECTIVE_MONEY);
            $deadline = $this->resolveDate($low, $message->at);

            if ($collective && !$hasImperative) {
                return new Classification(Label::Para, [
                    'ne' => $this->moneyTopic($low),
                    'tutar' => $amount,
                    'son' => $deadline?->format('Y-m-d'),
                ]);
            }
            // Kişisel ödeme/aksiyon (getir/yatır) → senden_aksiyon.
            if ($hasImperative || $deadline !== null) {
                return new Classification(Label::Gorev, [
                    'aksiyon' => $this->summary($body),
                ]);
            }
            // Tutar var ama bağlam zayıf → yine para talebi say (sinyal kaybetme).
            return new Classification(Label::Para, [
                'ne' => $this->moneyTopic($low),
                'tutar' => $amount,
                'son' => $deadline?->format('Y-m-d'),
            ]);
        }

        // 2) Görev: imperative (emir kipi) + nesne. Kişisel aksiyon güçlü
        //    sinyaldir; etkinlik kontrolünden ÖNCE gelir ki "imza formu getir,
        //    veli toplantısı için" mesajı yanlışlıkla etkinliğe düşmesin.
        if ($hasImperative && $hasTaskObject) {
            return new Classification(Label::Gorev, [
                'aksiyon' => $this->summary($body),
            ]);
        }

        // 3) Etkinlik: gelecek tarih/gün ipucu + (saat | etkinlik adı).
        $eventDate = $this->resolveDate($low, $message->at);
        $time = $this->extractTime($low);
        $eventTerm = $this->firstMatch($low, self::EVENT_TERMS);
        if (($eventDate !== null || $this->hasDateCue($low)) && ($time !== null || $eventTerm !== null)) {
            return new Classification(Label::Etkinlik, [
                'tarih' => $eventDate?->format('Y-m-d'),
                'saat' => $time,
                'ne' => $eventTerm ?? $this->summary($body),
                'cocuk' => null,
            ]);
        }

        // 4) Oylama.
        if ($this->isVote($low)) {
            return new Classification(Label::Oylama, [
                'aksiyon' => $this->summary($body),
            ]);
        }

        // 5) Gürültü (default).
        return Classification::noise();
    }

    private function isNoisePlaceholder(string $low): bool
    {
        $placeholders = ['<medya dahil edilmedi>', '<media omitted>', 'bu mesaj silindi', 'this message was deleted'];
        foreach ($placeholders as $p) {
            if (str_contains($low, $p)) {
                return true;
            }
        }
        // Sadece emoji/çok kısa tepki.
        $stripped = trim(preg_replace('/[\p{P}\p{S}\p{Z}\s]+/u', '', $low) ?? '');
        return $stripped === '';
    }

    /** Tutar token'ı: "150₺", "150 TL", "100 lira" → normalize "150₺". */
    private function extractAmount(string $body, string $low): ?string
    {
        if (preg_match('/(\d[\d.]*)\s*(₺|tl\b|lira\b)/u', $low, $m) === 1) {
            $num = str_replace('.', '', $m[1]);
            return $num . '₺';
        }
        return null;
    }

    private function moneyTopic(string $low): string
    {
        foreach (['öğretmen hediye', 'hediye', 'aidat', 'gezi', 'kermes', 'kutlama', 'sınıf'] as $t) {
            if (str_contains($low, $t)) {
                return $t === 'öğretmen hediye' ? 'öğretmen hediyesi' : $t;
            }
        }
        return $this->summary($low);
    }

    private function extractTime(string $low): ?string
    {
        if (preg_match('/\b(\d{1,2})[:.](\d{2})\b/u', $low, $m) === 1) {
            $h = (int) $m[1];
            $min = (int) $m[2];
            if ($h <= 23 && $min <= 59) {
                return sprintf('%02d:%02d', $h, $min);
            }
        }
        if (preg_match('/\bsaat\s*(\d{1,2})\b/u', $low, $m) === 1) {
            $h = (int) $m[1];
            if ($h <= 23) {
                return sprintf('%02d:00', $h);
            }
        }
        return null;
    }

    /** Metinde herhangi bir tarih/gün ipucu var mı (saat şart değil)? */
    private function hasDateCue(string $low): bool
    {
        if (preg_match('/\b(bugün|yarın|haftaya|gelecek hafta|önümüzdeki)\b/u', $low) === 1) {
            return true;
        }
        foreach (array_keys(self::TR_WEEKDAYS) as $d) {
            if (str_contains($low, $d)) {
                return true;
            }
        }
        foreach (array_keys(self::TR_MONTHS) as $mo) {
            if (str_contains($low, $mo)) {
                return true;
            }
        }
        return preg_match('/\b\d{1,2}[.\/]\d{1,2}\b/u', $low) === 1;
    }

    /**
     * Metindeki tarih ifadesini mesajın zamanına göre çözer.
     * Öncelik: mutlak (DD Ay / DD.AA) > gün ismi > göreli (yarın/haftaya).
     */
    private function resolveDate(string $low, \DateTimeImmutable $ref): ?\DateTimeImmutable
    {
        // "22 Ocak"
        if (preg_match('/\b(\d{1,2})\s+(' . implode('|', array_keys(self::TR_MONTHS)) . ')\b/u', $low, $m) === 1) {
            $day = (int) $m[1];
            $month = self::TR_MONTHS[$m[2]];
            return $this->makeDate((int) $ref->format('Y'), $month, $day);
        }
        // "22.01" / "22/01"
        if (preg_match('/\b(\d{1,2})[.\/](\d{1,2})(?:[.\/](\d{2,4}))?\b/u', $low, $m) === 1) {
            $day = (int) $m[1];
            $month = (int) $m[2];
            $year = isset($m[3]) && $m[3] !== '' ? (int) $m[3] : (int) $ref->format('Y');
            if ($year < 100) {
                $year += 2000;
            }
            if ($month >= 1 && $month <= 12 && $day >= 1 && $day <= 31) {
                return $this->makeDate($year, $month, $day);
            }
        }
        // "24'üne kadar", "24'ine kadar" → bu ayın günü (geçmişse sonraki ay).
        if (preg_match('/\b(\d{1,2})[\'’]?(?:ü|u|i|ı)ne\s+kadar\b/u', $low, $m) === 1) {
            $day = (int) $m[1];
            $y = (int) $ref->format('Y');
            $mo = (int) $ref->format('n');
            $cand = $this->makeDate($y, $mo, $day);
            if ($cand !== null && $cand < $ref->setTime(0, 0)) {
                $cand = $cand->modify('+1 month');
            }
            return $cand;
        }
        // Gün ismi (Cuma / Cuma'ya kadar / Cuma günü).
        foreach (self::TR_WEEKDAYS as $name => $dow) {
            if (str_contains($low, $name)) {
                return $this->nextWeekday($ref, $dow);
            }
        }
        // Göreli.
        if (str_contains($low, 'yarın')) {
            return $ref->modify('+1 day');
        }
        if (str_contains($low, 'bugün')) {
            return $ref;
        }
        if (str_contains($low, 'haftaya') || str_contains($low, 'gelecek hafta')) {
            return $ref->modify('+7 day');
        }
        return null;
    }

    private function nextWeekday(\DateTimeImmutable $ref, int $targetDow): \DateTimeImmutable
    {
        $refDow = (int) $ref->format('N');
        $delta = ($targetDow - $refDow + 7) % 7;
        return $ref->modify('+' . $delta . ' day');
    }

    private function makeDate(int $y, int $m, int $d): ?\DateTimeImmutable
    {
        if (!checkdate($m, $d, $y)) {
            return null;
        }
        $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', sprintf('%04d-%02d-%02d', $y, $m, $d));
        return $dt === false ? null : $dt;
    }

    private function isVote(string $low): bool
    {
        if ($this->containsAny($low, ['oylama', 'anket', 'oy verin', 'oy verelim', 'kim katıl', 'katılan +', 'evet/hayır', 'evet-hayır'])) {
            return true;
        }
        return preg_match('/(^|\s)\+1(\s|$)/u', $low) === 1;
    }

    /**
     * Emir kipi var mı — kelime sınırıyla eşler ki "getir" (emir) ile
     * "getirebilir/getiriyorum" (haber kipi) karışmasın.
     */
    private function hasImperative(string $low): bool
    {
        $alt = implode('|', self::IMPERATIVES);
        return preg_match('/\b(' . $alt . ')\b/u', $low) === 1;
    }

    /** @param list<string> $needles */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $n) {
            if (str_contains($haystack, $n)) {
                return true;
            }
        }
        return false;
    }

    /** @param list<string> $needles */
    private function firstMatch(string $haystack, array $needles): ?string
    {
        foreach ($needles as $n) {
            if (str_contains($haystack, $n)) {
                return $n;
            }
        }
        return null;
    }

    /** Mesajı kısa, tek satır aksiyon özetine indirger. */
    private function summary(string $body): string
    {
        $s = preg_replace('/\s+/u', ' ', $body) ?? $body;
        // Baştaki selamlama/hitap temizliği.
        $s = preg_replace('/^(sayın\s+veliler|değerli\s+veliler|arkadaşlar|merhaba|selam|sn\.?\s*veliler|lütfen)[,\s]*/iu', '', trim($s)) ?? $s;
        // Emojileri kırp.
        $s = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}]/u', '', $s) ?? $s;
        $s = trim($s);
        if (mb_strlen($s) > 100) {
            $s = mb_substr($s, 0, 97) . '...';
        }
        return $s;
    }
}
