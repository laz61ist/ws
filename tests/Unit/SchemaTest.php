<?php

declare(strict_types=1);

namespace WABridge\Tests\Unit;

use WABridge\Classify\HeuristicClassifier;
use WABridge\Pipeline;
use WABridge\Tests\TestCase;

/**
 * Digest çıktısı referans şemaya uymalı:
 *   { hafta:string, takvim:[{tarih,saat,ne,cocuk}], senden_aksiyon:[string],
 *     para_talepleri:[{ne,tutar,son}], elenen_gurultu_sayisi:int }
 */
final class SchemaTest extends TestCase
{
    /** @return array<string,mixed> */
    private function digest(): array
    {
        $pipeline = new Pipeline(new HeuristicClassifier());
        $raw = (string) file_get_contents(dirname(__DIR__) . '/fixtures/synthetic_android_tr.txt');
        return $pipeline->processString($raw);
    }

    public function testTopLevelKeysAndTypes(): void
    {
        $d = $this->digest();
        foreach (['hafta', 'takvim', 'senden_aksiyon', 'para_talepleri', 'elenen_gurultu_sayisi'] as $key) {
            $this->assertTrue(array_key_exists($key, $d), "Eksik alan: {$key}");
        }
        $this->assertTrue(is_string($d['hafta']), 'hafta string olmalı');
        $this->assertTrue(is_array($d['takvim']), 'takvim dizi olmalı');
        $this->assertTrue(is_array($d['senden_aksiyon']), 'senden_aksiyon dizi olmalı');
        $this->assertTrue(is_array($d['para_talepleri']), 'para_talepleri dizi olmalı');
        $this->assertTrue(is_int($d['elenen_gurultu_sayisi']), 'elenen_gurultu_sayisi tam sayı olmalı');
    }

    public function testCalendarItemShape(): void
    {
        $d = $this->digest();
        $item = $d['takvim'][0];
        foreach (['tarih', 'saat', 'ne', 'cocuk'] as $key) {
            $this->assertTrue(array_key_exists($key, $item), "takvim öğesinde eksik alan: {$key}");
        }
    }

    public function testMoneyItemShape(): void
    {
        $d = $this->digest();
        $item = $d['para_talepleri'][0];
        foreach (['ne', 'tutar', 'son'] as $key) {
            $this->assertTrue(array_key_exists($key, $item), "para öğesinde eksik alan: {$key}");
        }
    }

    public function testWeekLabelFormat(): void
    {
        $d = $this->digest();
        // "YYYY-MM-DD/DD" veya ay geçişinde "YYYY-MM-DD/MM-DD".
        $this->assertTrue(
            preg_match('/^\d{4}-\d{2}-\d{2}\/(\d{2}|\d{2}-\d{2})$/', $d['hafta']) === 1,
            'hafta etiketi formatı: ' . $d['hafta'],
        );
    }

    public function testDigestIsJsonEncodable(): void
    {
        $d = $this->digest();
        $json = json_encode($d, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $this->assertTrue(is_string($json) && $json !== '');
        $decoded = json_decode($json, true);
        $this->assertSame($d['elenen_gurultu_sayisi'], $decoded['elenen_gurultu_sayisi']);
    }
}
