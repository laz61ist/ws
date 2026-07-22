<?php

declare(strict_types=1);

namespace WABridge\Anonymize;

use WABridge\Parser\ChatParser;
use WABridge\Parser\Message;
use WABridge\Support\TurkishText;

/**
 * Gerçek WhatsApp export'unu tests/fixtures/real/ altına yazılabilir hâle
 * getirmek için ANONİMLEŞTİRİR — deterministik, script (regex), LLM YOK
 * (CLAUDE.md kuralına uygun).
 *
 * Yapar:
 *  - Gönderen adını tutarlı bir takma ada çevirir (Veli1, Veli2, ... /
 *    "öğretmen" geçen adlar için Öğretmen, Öğretmen2, ...). Aynı gönderen
 *    dosya boyunca hep aynı takma adı alır.
 *  - Mesaj GÖVDESİ içindeki telefon/e-posta/IBAN/TCKN pattern'lerini maskeler.
 *  - Mesaj metninin GERİ KALANINI korur (sınıflamayı gerçek kaosa karşı test
 *    edebilmek için — CLAUDE.md: "gerçek grup senin sentetik fixture'ın gibi
 *    temiz değil").
 *
 * YAPMAZ (bilinçli sınır, dürüstçe belirtilir — DOĞRULANMADI/kapsam dışı):
 *  - Mesaj METNİ İÇİNDE rastgele geçen bir kişi adını (ör. "Ayşe bugün
 *    gelemeyecek") TESPİT ETMEZ. Bu, genel ad tanıma (NER) gerektirir —
 *    deterministik regex ile güvenilir çözülemez, CLAUDE.md'nin "LLM'e
 *    ayrıştırtma yasak" ilkesiyle de çelişir. tests/fixtures/real/ yine de
 *    .gitignore'dadır (savunma derinliği); bu dosyaları REPO DIŞINA
 *    paylaşmadan önce insan gözden geçirmesi önerilir.
 */
final class Anonymizer
{
    private const PLACEHOLDER_TCKN = '[TCKN]';
    private const PLACEHOLDER_IBAN = '[IBAN]';
    private const PLACEHOLDER_EMAIL = '[EPOSTA]';
    private const PLACEHOLDER_PHONE = '[TELEFON]';

    public function __construct(private readonly ChatParser $parser = new ChatParser())
    {
    }

    /**
     * @return array{output:string,senderCount:int,messageCount:int}
     */
    public function anonymize(string $rawContent): array
    {
        if (!mb_check_encoding($rawContent, 'UTF-8')) {
            // Bilinçli fail-loud: ChatParser::normalize() geçersiz UTF-8'de
            // preg_replace('/u') null döndürüp SESSİZCE tüm içeriği boşaltır.
            // Anonimleştirme sonrası ham dosya SİLİNEBİLECEĞİ için (KVKK akışı)
            // bu durumda veri geri getirilemez kaybolur — o yüzden burada
            // erken ve gürültülü şekilde durur, boş çıktı asla üretmez.
            throw new \RuntimeException(
                'Girdi geçerli UTF-8 değil — anonimleştirme güvenli şekilde yapılamaz '
                . '(sessiz veri kaybı riski). Ham dosyanın kodlamasını elle kontrol et.'
            );
        }

        $messages = $this->parser->parse($rawContent);
        $pseudonyms = $this->buildPseudonymMap($messages);

        $lines = [];
        foreach ($messages as $message) {
            $pseudonym = $pseudonyms[$message->sender];
            $maskedBody = $this->maskPii($message->body);
            $lines[] = sprintf(
                '%s - %s: %s',
                $message->at->format('d.m.Y, H:i'),
                $pseudonym,
                $maskedBody,
            );
        }

        return [
            'output' => implode("\n", $lines) . ($lines !== [] ? "\n" : ''),
            'senderCount' => count($pseudonyms),
            'messageCount' => count($messages),
        ];
    }

    /**
     * @param list<Message> $messages
     * @return array<string,string> gönderen adı -> takma ad
     */
    private function buildPseudonymMap(array $messages): array
    {
        $map = [];
        $veliCount = 0;
        $ogretmenCount = 0;

        foreach ($messages as $message) {
            if (isset($map[$message->sender])) {
                continue;
            }

            if (str_contains(TurkishText::lower($message->sender), 'öğretmen')) {
                $ogretmenCount++;
                $map[$message->sender] = $ogretmenCount === 1 ? 'Öğretmen' : 'Öğretmen' . $ogretmenCount;
            } else {
                $veliCount++;
                $map[$message->sender] = 'Veli' . $veliCount;
            }
        }

        return $map;
    }

    /**
     * Ayraç sınıfı: boşluk/nokta/tire/parantez — HERHANGİ birleşimde, sıfır
     * veya daha fazla. Parantezleri de "sadece bir başka ayraç karakteri"
     * olarak ele almak (yapısal konumunu modellemeye çalışmak yerine),
     * "(532)", "0(532)", "+90(532)" gibi TÜM parantez yerleşimi varyantlarını
     * tek bir basit kuralla kapsar (bkz. adversarial denetim bulguları).
     */
    private const SEP = '[\s.\-()]*';

    private function maskPii(string $body): string
    {
        $masked = $body;

        // IBAN (TR + 24 hane). \d{11}'den ÖNCE ve telefon'dan ÖNCE
        // maskelenmeli (yoksa içindeki rakam dizileri diğer pattern'lere
        // yanlış eşleşebilir). \b YERİNE (?<!\d)/(?!\d) kullanılıyor — \b,
        // rakam ile harf arasında (ikisi de \w) TETİKLENMEZ, bu yüzden
        // "...841326TL" gibi harfe bitişik yazımlarda \b sessizce başarısız
        // olurdu (adversarial denetimde doğrulandı).
        $masked = (string) preg_replace(
            '/(?<!\d)TR' . self::SEP . '(?:\d' . self::SEP . '){24}(?!\d)/u',
            self::PLACEHOLDER_IBAN,
            $masked,
        );

        // E-posta.
        $masked = (string) preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/u', self::PLACEHOLDER_EMAIL, $masked);

        // TR cep telefonu (5xx) ve sabit hat (il kodu 2xx/3xx/4xx) — TEK bir
        // permissif ayraç sınıfıyla (boşluk/nokta/tire/parantez, herhangi
        // sırada) +90/0 önekli/öneksiz TÜM gerçekçi yazım biçimlerini kapsar.
        // (?<!\d)/(?!\d) kullanımı "+90" bitişik yazımda \b'nin iki
        // non-word-karakter arasında (boşluk/+ gibi) tetiklenmeme sorununu
        // da çözer (adversarial denetimde doğrulanan kök neden).
        $prefix = '(?:\+?90' . self::SEP . ')?' . self::SEP . '0?' . self::SEP;
        $masked = (string) preg_replace(
            '/(?<!\d)' . $prefix . '5\d{2}' . self::SEP . '\d{3}' . self::SEP . '\d{2}' . self::SEP . '\d{2}(?!\d)/u',
            self::PLACEHOLDER_PHONE,
            $masked,
        );
        $masked = (string) preg_replace(
            '/(?<!\d)' . $prefix . '[2-4]\d{2}' . self::SEP . '\d{3}' . self::SEP . '\d{2}' . self::SEP . '\d{2}(?!\d)/u',
            self::PLACEHOLDER_PHONE,
            $masked,
        );

        // TCKN: bağımsız 11 haneli sayı, ayraçlı (tireli/noktalı/boşluklu)
        // yazımlar dahil, harfe bitişik olsa bile (yukarıdaki (?<!\d)/(?!\d)
        // gerekçesiyle aynı). Diğer maskelemelerden SONRA çalışır ki
        // IBAN/telefon içindeki rakam dizilerini yanlışlıkla yutmasın.
        $digitSep = '[\s.\-]?';
        $masked = (string) preg_replace(
            '/(?<!\d)' . str_repeat('\d' . $digitSep, 10) . '\d(?!\d)/u',
            self::PLACEHOLDER_TCKN,
            $masked,
        );

        return $masked;
    }
}
