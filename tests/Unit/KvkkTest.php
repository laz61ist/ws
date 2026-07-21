<?php

declare(strict_types=1);

namespace WABridge\Tests\Unit;

use WABridge\Classify\HeuristicClassifier;
use WABridge\Pipeline;
use WABridge\Tests\TestCase;

/**
 * KVKK kuralı: ham export .txt işlendikten sonra SİLİNİR ve hiçbir kalıcı
 * depoya yazılmaz. Bu davranış test edilebilir olmalı (CLAUDE.md kabul kriteri).
 */
final class KvkkTest extends TestCase
{
    private function tempCopy(): string
    {
        $src = dirname(__DIR__) . '/fixtures/synthetic_android_tr.txt';
        $tmp = tempnam(sys_get_temp_dir(), 'wabridge_raw_');
        copy($src, $tmp);
        return $tmp;
    }

    public function testRawFileDeletedAfterProcessing(): void
    {
        $path = $this->tempCopy();
        $this->assertTrue(is_file($path), 'Ön koşul: geçici ham dosya var');

        $pipeline = new Pipeline(new HeuristicClassifier());
        $digest = $pipeline->processFile($path);

        // Digest üretildi...
        $this->assertTrue(isset($digest['takvim']) && count($digest['takvim']) > 0, 'Digest üretilmeli');
        // ...ve ham dosya SİLİNDİ.
        $this->assertFalse(is_file($path), 'KVKK: ham .txt işlem sonrası silinmeli');
    }

    public function testFileDeletedEvenWhenClassifierProcesses(): void
    {
        // finally bloğu: içerik boş/bozuk olsa bile ham dosya silinir.
        $tmp = tempnam(sys_get_temp_dir(), 'wabridge_raw_');
        file_put_contents($tmp, "bozuk içerik, geçerli satır yok\n");

        $pipeline = new Pipeline(new HeuristicClassifier());
        $pipeline->processFile($tmp);

        $this->assertFalse(is_file($tmp), 'KVKK: geçersiz içerikte bile ham .txt silinmeli');
    }

    public function testDeleteAfterFalseKeepsFileForPreview(): void
    {
        // Açık şekilde deleteAfter=false verilirse (önizleme) dosya durur.
        $path = $this->tempCopy();
        $pipeline = new Pipeline(new HeuristicClassifier());
        $pipeline->processFile($path, deleteAfter: false);
        $this->assertTrue(is_file($path), 'deleteAfter=false dosyayı korumalı');
        @unlink($path);
    }
}
