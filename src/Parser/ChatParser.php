<?php

declare(strict_types=1);

namespace WABridge\Parser;

/**
 * Deterministik WhatsApp export .txt ayrıştırıcı — SADECE regex/script.
 * LLM YOK (CLAUDE.md kesin kuralı: LLM'e .txt ayrıştırtmak = para + halüsinasyon).
 *
 * Desteklenen satır varyantları:
 *   - Android TR:  "12.01.2026, 09:15 - Ayşe Yılmaz: mesaj"
 *   - iOS köşeli:  "[12.01.2026, 09:15:03] Ayşe Yılmaz: mesaj"
 *   - 12 saat:     "12/01/2026, 9:15 PM - Ali: mesaj"
 *   - Tarih ayracı . / - ; 2 veya 4 haneli yıl ; saniyeli/saniyesiz saat
 *   - iOS'un araya soktuğu dar/kırılmaz boşluklar (U+202F, U+00A0) ve
 *     yön işaretleri (U+200E/U+200F) normalize edilir.
 *   - Çok satırlı mesaj: tarih başlığı olmayan satır önceki mesaja eklenir.
 *   - Sistem satırları (şifreleme bildirimi, gruba katılma/ayrılma, numara
 *     değişikliği vb. — ":" içermez) atlanır.
 */
final class ChatParser
{
    /**
     * Köşeli parantezli (iOS) başlık.
     * Gruplar: 1=gün 2=ay 3=yıl 4=saat 5=dakika 6=saniye? 7=AM/PM? 8=kalan
     */
    private const RE_BRACKET =
        '/^\[\s*(\d{1,2})[.\/\-](\d{1,2})[.\/\-](\d{2,4})[,]?\s+(\d{1,2}):(\d{2})(?::(\d{2}))?\s*([APap][Mm]|ÖÖ|ÖS|öö|ös)?\s*\]\s*(.*)$/u';

    /**
     * Tireli (Android) başlık.
     * Gruplar: 1=gün 2=ay 3=yıl 4=saat 5=dakika 6=saniye? 7=AM/PM? 8=kalan
     */
    private const RE_DASH =
        '/^(\d{1,2})[.\/\-](\d{1,2})[.\/\-](\d{2,4})[,]?\s+(\d{1,2}):(\d{2})(?::(\d{2}))?\s*([APap][Mm]|ÖÖ|ÖS|öö|ös)?\s+-\s+(.*)$/u';

    /**
     * @return list<Message>
     */
    public function parse(string $content): array
    {
        $content = $this->normalize($content);
        $lines = explode("\n", $content);

        /** @var list<Message> $messages */
        $messages = [];
        /** @var array{at:\DateTimeImmutable,sender:string,body:string,lineNo:int}|null $current */
        $current = null;

        foreach ($lines as $idx => $rawLine) {
            $lineNo = $idx + 1;
            $header = $this->matchHeader($rawLine);

            if ($header === null) {
                // Başlık yok: önceki mesajın devamı (çok satırlı) ya da başıboş satır.
                if ($current !== null && $rawLine !== '') {
                    $current['body'] .= "\n" . $rawLine;
                }
                continue;
            }

            // Yeni başlık geldi: önceki mesajı kapat.
            if ($current !== null) {
                $messages[] = $this->finalize($current);
                $current = null;
            }

            [$at, $rest] = $header;

            // Gönderen ayracı: ilk ":". Yoksa sistem mesajı → atla.
            $colon = mb_strpos($rest, ':');
            if ($colon === false) {
                // Sistem satırı (şifreleme bildirimi, katılma/ayrılma vb.). Atla.
                continue;
            }

            $sender = trim(mb_substr($rest, 0, $colon));
            $body = ltrim(mb_substr($rest, $colon + 1));

            if ($sender === '') {
                continue;
            }

            $current = ['at' => $at, 'sender' => $sender, 'body' => $body, 'lineNo' => $lineNo];
        }

        if ($current !== null) {
            $messages[] = $this->finalize($current);
        }

        return $messages;
    }

    /**
     * @param array{at:\DateTimeImmutable,sender:string,body:string,lineNo:int} $c
     */
    private function finalize(array $c): Message
    {
        return new Message($c['at'], $c['sender'], rtrim($c['body']), $c['lineNo']);
    }

    /**
     * Satır başlığını dener; başarılıysa [DateTimeImmutable, kalanMetin] döner.
     *
     * @return array{0:\DateTimeImmutable,1:string}|null
     */
    private function matchHeader(string $line): ?array
    {
        foreach ([self::RE_BRACKET, self::RE_DASH] as $re) {
            if (preg_match($re, $line, $m) === 1) {
                $at = $this->buildDate(
                    (int) $m[1],
                    (int) $m[2],
                    (int) $m[3],
                    (int) $m[4],
                    (int) $m[5],
                    $m[6] !== '' ? (int) $m[6] : 0,
                    $m[7] ?? '',
                );
                if ($at === null) {
                    return null;
                }
                return [$at, $m[8]];
            }
        }
        return null;
    }

    /**
     * Gün/ay belirsizliğini çözer (TR gün-önce varsayılan; ilk bileşen >12 ise
     * kesin gün, ikinci >12 ise ay-önce/US), 12→24 saat dönüşümü yapar.
     */
    private function buildDate(
        int $a,
        int $b,
        int $year,
        int $hour,
        int $minute,
        int $second,
        string $meridiem,
    ): ?\DateTimeImmutable {
        if ($year < 100) {
            $year += 2000;
        }

        // Gün-önce (TR) vs ay-önce (US) ayrımı.
        if ($a > 12 && $b <= 12) {
            $day = $a;
            $month = $b;
        } elseif ($b > 12 && $a <= 12) {
            $day = $b;
            $month = $a;
        } else {
            // Belirsiz: TR varsayılanı gün-önce.
            $day = $a;
            $month = $b;
        }

        if ($meridiem !== '') {
            $mer = mb_strtoupper($meridiem);
            $isPm = ($mer === 'PM' || $mer === 'ÖS');
            if ($isPm && $hour < 12) {
                $hour += 12;
            }
            if (!$isPm && $hour === 12) {
                $hour = 0;
            }
        }

        if ($month < 1 || $month > 12 || $day < 1 || $day > 31
            || $hour > 23 || $minute > 59 || $second > 59) {
            return null;
        }

        $dt = \DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second),
        );

        return $dt === false ? null : $dt;
    }

    /**
     * iOS'un araya soktuğu dar/kırılmaz boşlukları normal boşluğa çevirir,
     * yön işaretleri ve BOM'u siler, satır sonlarını birleştirir.
     */
    private function normalize(string $content): string
    {
        // BOM.
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }
        // Yön işaretleri / BOM ortada (U+200E, U+200F, U+FEFF).
        $content = (string) preg_replace('/[\x{200E}\x{200F}\x{FEFF}]/u', '', $content);
        // Kırılmaz / dar boşluklar → normal boşluk (U+00A0, U+202F, U+2007, U+2060).
        $content = (string) preg_replace('/[\x{00A0}\x{202F}\x{2007}\x{2060}]/u', ' ', $content);
        // Satır sonları.
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        return $content;
    }
}
