<?php

declare(strict_types=1);

/**
 * raw_exports/*.txt (gerçek, ham export) -> tests/fixtures/real/*.txt
 * (anonimleştirilmiş) dönüştürür. Kaynak dosyalar SİLİNMEZ.
 *
 * Kullanım: php scripts/anonymize.php
 *
 * Anonimleştirme kapsamı/sınırı için src/Anonymize/Anonymizer.php docblock'una
 * bakın — mesaj METNİ İÇİNDE geçen ad/telefon dışı serbest metin PII'si
 * (ör. "Ayşe bugün gelemeyecek" gibi bir cümledeki isim) TESPİT EDİLMEZ.
 */

require dirname(__DIR__) . '/src/autoload.php';

use WABridge\Anonymize\Anonymizer;

$rawDir = dirname(__DIR__) . '/raw_exports';
$outDir = dirname(__DIR__) . '/tests/fixtures/real';

$files = glob($rawDir . '/*.txt') ?: [];

if ($files === []) {
    fwrite(STDOUT, "raw_exports/ içinde .txt bulunamadı. Önce gerçek export'ları oraya koy.\n");
    exit(0);
}

if (!is_dir($outDir) && !@mkdir($outDir, 0770, true) && !is_dir($outDir)) {
    fwrite(STDERR, "Çıktı dizini oluşturulamadı: {$outDir}\n");
    exit(1);
}

$anonymizer = new Anonymizer();

foreach ($files as $file) {
    $raw = (string) file_get_contents($file);
    $result = $anonymizer->anonymize($raw);

    $basename = basename($file);
    $outPath = $outDir . '/' . $basename;
    file_put_contents($outPath, $result['output']);

    fwrite(STDOUT, sprintf(
        "%s -> %s (%d mesaj, %d benzersiz gönderen anonimleştirildi)\n",
        $basename,
        $outPath,
        $result['messageCount'],
        $result['senderCount'],
    ));
}

fwrite(STDOUT, "\nKaynak dosyalar raw_exports/ altında değiştirilmeden duruyor.\n");
fwrite(STDOUT, "ÖNEMLİ: tests/fixtures/real/*.txt paylaşmadan/repoya eklemeden önce (zaten .gitignore'da)\n");
fwrite(STDOUT, "mesaj METNİ içinde kalmış olabilecek isim/detayları elle gözden geçir.\n");
