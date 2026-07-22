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

    private function maskPii(string $body): string
    {
        $masked = $body;

        // IBAN (TR + 24 hane, boşluklu/boşluksuz) — telefon/TCKN'den ÖNCE
        // maskelenmeli (yoksa içindeki rakam dizileri diğer pattern'lere de takılabilir).
        $masked = (string) preg_replace('/\bTR\d{2}(?:[ ]?\d{4}){5}[ ]?\d{2}\b/u', self::PLACEHOLDER_IBAN, $masked);

        // E-posta.
        $masked = (string) preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/u', self::PLACEHOLDER_EMAIL, $masked);

        // TR cep telefonu: +90/0 önekli veya önek olmadan 5xx xxx xx xx
        // (boşluk/nokta/tire ayraçlı varyantlar dahil).
        $masked = (string) preg_replace(
            '/\b(?:\+90[ ]?|0)?5\d{2}[ .\-]?\d{3}[ .\-]?\d{2}[ .\-]?\d{2}\b/u',
            self::PLACEHOLDER_PHONE,
            $masked,
        );

        // TCKN: bağımsız 11 haneli sayı (yukarıdaki maskelemelerden sonra
        // kalan tek bariz 11-hane deseni budur).
        $masked = (string) preg_replace('/\b\d{11}\b/u', self::PLACEHOLDER_TCKN, $masked);

        return $masked;
    }
}
