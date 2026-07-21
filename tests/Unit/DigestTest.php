<?php

declare(strict_types=1);

namespace WABridge\Tests\Unit;

use WABridge\Classify\HeuristicClassifier;
use WABridge\Pipeline;
use WABridge\Tests\TestCase;

/**
 * Her fixture'ı deterministik pipeline'dan geçirip beklenen digest JSON'una
 * (tests/fixtures/expected/) karşı TAM eşleşme denetler. Bu, sinyal/gürültü
 * ayrımının fixture'lar üzerinde gerilememesini garanti eder.
 */
final class DigestTest extends TestCase
{
    /** @return array<string,mixed> */
    private function digestFor(string $name): array
    {
        $pipeline = new Pipeline(new HeuristicClassifier());
        $raw = (string) file_get_contents(dirname(__DIR__) . '/fixtures/' . $name . '.txt');
        return $pipeline->processString($raw);
    }

    /** @return array<string,mixed> */
    private function expected(string $name): array
    {
        $json = (string) file_get_contents(dirname(__DIR__) . '/fixtures/expected/' . $name . '.json');
        /** @var array<string,mixed> $data */
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        return $data;
    }

    private function assertDigestMatches(string $name): void
    {
        $actual = $this->digestFor($name);
        $expected = $this->expected($name);
        // JSON'a çevirip karşılaştır: sıra ve iç içe yapılar dahil tam eşleşme.
        $this->assertSame(
            json_encode($expected, JSON_UNESCAPED_UNICODE),
            json_encode($actual, JSON_UNESCAPED_UNICODE),
            "Digest beklenen şemadan sapıyor: {$name}",
        );
    }

    public function testAndroidDigestMatchesExpected(): void
    {
        $this->assertDigestMatches('synthetic_android_tr');
    }

    public function testIosDigestMatchesExpected(): void
    {
        $this->assertDigestMatches('synthetic_ios_tr');
    }

    public function testLargeDigestMatchesExpected(): void
    {
        $this->assertDigestMatches('synthetic_large_500');
    }

    public function testLargeFixtureExtractsFiveSignalsFromFiveHundred(): void
    {
        // "Gözle bakış": ~500 mesajdan tam 5 gerçek lojistik, gerisi gürültü.
        $d = $this->digestFor('synthetic_large_500');
        $signalCount = count($d['takvim']) + count($d['senden_aksiyon']) + count($d['para_talepleri']);
        $this->assertSame(5, $signalCount, '500 mesajdan 5 sinyal çıkmalı');
        $this->assertSame(495, $d['elenen_gurultu_sayisi']);
    }

    public function testSemanticLogisticsCaptured(): void
    {
        // Anlamsal: gerçek lojistik anahtarları çıktıda görünmeli.
        $d = $this->digestFor('synthetic_android_tr');
        $actions = implode(' | ', $d['senden_aksiyon']);
        $this->assertStringContains('imza', $actions);
        $this->assertStringContains('Gezi ücreti', $actions);
        $this->assertSame('öğretmen hediyesi', $d['para_talepleri'][0]['ne']);
    }
}
