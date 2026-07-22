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

    // --- REGRESYON TESTLERİ: bağımsız adversarial denetimde bulunan gerçek
    // sızıntı senaryoları. Her biri düzeltme ÖNCESİ gerçekten sızıyordu
    // (bkz. commit geçmişi); bir daha sessizce geri gelmesin diye burada
    // kilitlendi.

    /** @return list<string> */
    private function anonymizeLine(string $body): array
    {
        $line = "12.01.2026, 09:00 - Ali Veli: {$body}";
        $result = (new Anonymizer())->anonymize($line);
        return [$result['output']];
    }

    public function testPhonePlusNinetyAttachedNoSpaceMasked(): void
    {
        [$out] = $this->anonymizeLine('+905321234567 hemen ara');
        $this->assertFalse(str_contains($out, '905321234567'), '+90 bitişik cep numarası sızmamalı');
        $this->assertStringContains('[TELEFON]', $out);
    }

    public function testPhoneWithParenthesesMasked(): void
    {
        [$out] = $this->anonymizeLine('(532) 123 45 67 seklinde arayabilirsiniz');
        $this->assertFalse(str_contains($out, '532) 123 45 67'), 'Parantezli alan kodu sızmamalı');
        $this->assertStringContains('[TELEFON]', $out);
    }

    public function testLandlineNumberMasked(): void
    {
        [$out1] = $this->anonymizeLine('0212 345 67 89 sabit hattan arayin');
        $this->assertFalse(str_contains($out1, '0212 345 67 89'), 'Sabit hat (boşluklu) sızmamalı');
        $this->assertStringContains('[TELEFON]', $out1);

        [$out2] = $this->anonymizeLine('02123456789 sabit hat bosluksuz');
        $this->assertFalse(str_contains($out2, '02123456789'), 'Sabit hat (boşluksuz) sızmamalı');
        $this->assertStringContains('[TELEFON]', $out2);

        [$out3] = $this->anonymizeLine('(0212) 345 67 89 parantezli il kodu');
        $this->assertFalse(str_contains($out3, '0212'), 'Parantezli sabit hat sızmamalı');
        $this->assertStringContains('[TELEFON]', $out3);

        [$out4] = $this->anonymizeLine('+90 212 345 67 89 uluslararasi sabit hat');
        $this->assertFalse(str_contains($out4, '212 345 67 89'), 'Uluslararası sabit hat sızmamalı');
        $this->assertStringContains('[TELEFON]', $out4);
    }

    public function testIbanWithSpaceAfterCountryCodeMasked(): void
    {
        [$out] = $this->anonymizeLine('TR 33 0006 1005 1978 6457 8413 26 boyle de olur');
        $this->assertFalse(str_contains($out, '0006 1005 1978 6457 8413 26'), 'TR-sonrası-boşluklu IBAN sızmamalı');
        $this->assertStringContains('[IBAN]', $out);
    }

    public function testIbanNonStandardGroupingMasked(): void
    {
        [$out] = $this->anonymizeLine('garip gruplama TR33 00061005197864 57841326 farkli');
        $this->assertFalse(str_contains($out, '00061005197864'), 'Standart-dışı gruplanmış IBAN sızmamalı');
        $this->assertStringContains('[IBAN]', $out);
    }

    public function testIbanAttachedToLetterMasked(): void
    {
        [$out] = $this->anonymizeLine('TR330006100519786457841326TL seklinde yazdim');
        $this->assertFalse(str_contains($out, '841326TL'), 'Harfe bitişik IBAN sızmamalı');
        $this->assertStringContains('[IBAN]', $out);
    }

    public function testTcknAttachedToLetterMasked(): void
    {
        [$out1] = $this->anonymizeLine('kimlik no bitisik yazdim 12345678901TL boyle');
        $this->assertFalse(str_contains($out1, '12345678901'), 'Sonda harfe bitişik TCKN sızmamalı');
        $this->assertStringContains('[TCKN]', $out1);

        [$out2] = $this->anonymizeLine('onune bitisik yazi kimlikno12345678901 diye');
        $this->assertFalse(str_contains($out2, '12345678901'), 'Başta harfe bitişik TCKN sızmamalı');
        $this->assertStringContains('[TCKN]', $out2);
    }

    public function testTcknWithDashesMasked(): void
    {
        [$out] = $this->anonymizeLine('tire ile yazdim 123-456-789-01 boyle format');
        $this->assertFalse(str_contains($out, '123-456-789-01'), 'Tireli TCKN sızmamalı');
        $this->assertStringContains('[TCKN]', $out);
    }

    public function testTwelveDigitNumberNotTreatedAsTckn(): void
    {
        // Negatif kontrol: 12 haneli bir sayı TCKN (11 hane) OLMAMALI —
        // aşırı-maskeleme de yanlıştır (mesaj metnini gereksiz bozar).
        [$out] = $this->anonymizeLine('12 haneli olmamali 123456789012 boyle');
        $this->assertStringContains('123456789012', $out);
        $this->assertFalse(str_contains($out, '[TCKN]'), '12 haneli sayı TCKN olarak maskelenmemeli');
    }

    public function testInvalidUtf8ThrowsInsteadOfSilentlyEmptying(): void
    {
        // KRİTİK: adversarial denetimde bulunan sessiz veri kaybı riski.
        // Geçersiz UTF-8 baytı ChatParser::normalize()'ı SESSİZCE boş çıktı
        // üretmeye zorluyordu (ham dosya sonra silinirse veri kalıcı kaybolur).
        // Anonymizer artık bunun yerine erken ve gürültülü bir istisna fırlatır.
        $invalid = "12.01.2026, 09:00 - Ali: temiz mesaj\n"
            . "12.01.2026, 09:01 - Veli2: bozuk \xFF bayt\n"
            . "12.01.2026, 09:02 - Veli3: baska temiz mesaj";

        $threw = false;
        try {
            (new Anonymizer())->anonymize($invalid);
        } catch (\RuntimeException $e) {
            $threw = true;
        }
        $this->assertTrue($threw, 'Geçersiz UTF-8 girdisinde istisna fırlatılmalı, sessizce boş çıktı ÜRETİLMEMELİ');
    }

    public function testMultiplePhonesInSameMessageAllMasked(): void
    {
        [$out] = $this->anonymizeLine('iki numara var 0532 123 45 67 ve 0533 987 65 43 ikisi de gecerli');
        $this->assertFalse(str_contains($out, '0532 123 45 67'));
        $this->assertFalse(str_contains($out, '0533 987 65 43'));
        $this->assertSame(2, substr_count($out, '[TELEFON]'), 'Aynı mesajdaki iki telefon da ayrı ayrı maskelenmeli');
    }
}
