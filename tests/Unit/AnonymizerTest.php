<?php

declare(strict_types=1);

namespace WABridge\Tests\Unit;

use WABridge\Anonymize\Anonymizer;
use WABridge\Parser\ChatParser;
use WABridge\Tests\TestCase;

/**
 * Anonymizer testleri KURGULANMIŞ, sahte PII pattern'leri (gerçek TCKN/IBAN/
 * telefon DEĞİL, sadece format-doğru test verisi) kullanır. Amaç: raw_exports/
 * gerçek veri gelince scripts/anonymize.php'nin PII sızdırmadığını,
 * takma-ad eşlemesinin tutarlı olduğunu ve mesaj metninin korunduğunu
 * kanıtlamak.
 */
final class AnonymizerTest extends TestCase
{
    private const FIXTURE = <<<'TXT'
    19.01.2026, 08:32 - Ayşe Kaya: Günaydın arkadaşlar, telefonum 0532 123 45 67
    19.01.2026, 08:40 - Fatma Yıldız: Bana da 05321234567 üzerinden ulaşabilirsiniz
    19.01.2026, 09:10 - Zeynep Öğretmen: 22 Ocak Perşembe kıyafet serbest, IBAN'ım TR330006100519786457841326 aidat için
    19.01.2026, 09:12 - Ayşe Kaya: TCKN'imi paylaşayım 12345678901, e-postam ayse.kaya@ornek.com
    19.01.2026, 10:00 - Ali Demir: Merhaba, ben yeni katıldım
    TXT;

    public function testSenderNamesReplacedWithConsistentPseudonyms(): void
    {
        $result = (new Anonymizer())->anonymize(self::FIXTURE);
        $output = $result['output'];

        foreach (['Ayşe Kaya', 'Fatma Yıldız', 'Zeynep Öğretmen', 'Ali Demir'] as $realName) {
            $this->assertFalse(str_contains($output, $realName), "Gerçek isim '{$realName}' çıktıda görünmemeli");
        }

        // Ayşe Kaya iki mesaj gönderdi -> her ikisinde de AYNI takma ad olmalı.
        $this->assertSame(2, substr_count($output, 'Veli1:'), 'Aynı gönderen aynı takma adı almalı (tutarlılık)');
        $this->assertStringContains('Öğretmen:', $output, "Öğretmen içeren isim 'Öğretmen' takma adı almalı");
    }

    public function testPhoneNumbersMasked(): void
    {
        $result = (new Anonymizer())->anonymize(self::FIXTURE);
        $this->assertFalse(str_contains($result['output'], '0532 123 45 67'));
        $this->assertFalse(str_contains($result['output'], '05321234567'));
        $this->assertTrue(substr_count($result['output'], '[TELEFON]') === 2, 'İki telefon numarası da maskelenmeli');
    }

    public function testEmailMasked(): void
    {
        $result = (new Anonymizer())->anonymize(self::FIXTURE);
        $this->assertFalse(str_contains($result['output'], 'ayse.kaya@ornek.com'));
        $this->assertStringContains('[EPOSTA]', $result['output']);
    }

    public function testIbanMasked(): void
    {
        $result = (new Anonymizer())->anonymize(self::FIXTURE);
        $this->assertFalse(str_contains($result['output'], 'TR330006100519786457841326'));
        $this->assertStringContains('[IBAN]', $result['output']);
    }

    public function testTcknMasked(): void
    {
        $result = (new Anonymizer())->anonymize(self::FIXTURE);
        $this->assertFalse(str_contains($result['output'], '12345678901'));
        $this->assertStringContains('[TCKN]', $result['output']);
    }

    public function testNonPiiMessageTextPreserved(): void
    {
        $result = (new Anonymizer())->anonymize(self::FIXTURE);
        $output = $result['output'];
        // PII olmayan gerçek içerik (sınıflama testinde işe yarayacak sinyal) korunmalı.
        $this->assertStringContains('22 Ocak Perşembe kıyafet serbest', $output);
        $this->assertStringContains('aidat için', $output);
        $this->assertStringContains('ben yeni katıldım', $output);
    }

    public function testOutputIsReparseableByChatParser(): void
    {
        $result = (new Anonymizer())->anonymize(self::FIXTURE);
        $reparsed = (new ChatParser())->parse($result['output']);
        $this->assertCount(5, $reparsed, 'Anonimleştirilmiş çıktı ChatParser ile hatasız yeniden ayrıştırılmalı');
        $this->assertSame('Veli1', $reparsed[0]->sender);
        $this->assertSame('Öğretmen', $reparsed[2]->sender);
    }

    public function testSenderAndMessageCountsReported(): void
    {
        $result = (new Anonymizer())->anonymize(self::FIXTURE);
        $this->assertSame(4, $result['senderCount'], '4 benzersiz gönderen olmalı (Ayşe, Fatma, Zeynep Öğretmen, Ali)');
        $this->assertSame(5, $result['messageCount']);
    }

    public function testEmptyInputProducesEmptyOutput(): void
    {
        $result = (new Anonymizer())->anonymize('');
        $this->assertSame('', $result['output']);
        $this->assertSame(0, $result['messageCount']);
    }

    public function testTwoTeachersGetDistinctPseudonyms(): void
    {
        $fixture = "19.01.2026, 08:00 - Ayşe Öğretmen: merhaba\n19.01.2026, 08:01 - Zeynep Öğretmen: merhaba";
        $result = (new Anonymizer())->anonymize($fixture);
        $this->assertStringContains('Öğretmen:', $result['output']);
        $this->assertStringContains('Öğretmen2:', $result['output']);
    }
}
