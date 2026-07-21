<?php

declare(strict_types=1);

/**
 * Deterministik ~500 mesajlık SENTETİK export üretir. Seed sabit (mt_srand),
 * bu yüzden her çalıştırmada birebir aynı dosya çıkar.
 *
 * Gürültü havuzu bilinçli olarak lojistiksizdir (tarih/saat/tutar/etkinlik
 * kelimesi içermez), böylece 5 gömülü lojistik dışındaki her mesaj gürültü
 * olarak elenir. Bu "500 mesajdan 5 gerçek maddeyi çıkardı mı?" kriterini test eder.
 *
 * Çalıştır: php tests/tools/make_large_fixture.php
 */

mt_srand(42);

$senders = [
    'Ayşe K.', 'Mehmet T.', 'Fatma Y.', 'Ali D.', 'Selin A.',
    'Hakan B.', 'Emine G.', 'Murat S.', 'Deniz Y.', 'Zeynep Öğretmen',
];

// Hepsi kesinlikle lojistiksiz (sinyal tetiklemez).
$noise = [
    'Günaydın arkadaşlar 🌞', 'İyi akşamlar herkese', 'Teşekkürler hocam 🙏',
    'Aynen katılıyorum', 'Çok haklısınız', 'Rica ederim', 'Kolay gelsin',
    'Eyvallah kanka', 'Sağ olun', 'Çocuklar çok mutlu 🙂', 'Hava çok güzel',
    'Biraz yorgunum ya 😅', 'Kim izledi dün akşamki diziyi 📺', 'Hasta oldum galiba 🤒',
    'Ellerinize sağlık', 'Herkese selam 👋', 'Süper haber', 'Bence de öyle',
    'Peki tamam', 'Çok teşekkür ederim',
];

// index => lojistik mesaj gövdesi (500 gürültünün içine gömülür).
$logistics = [
    90 => 'Sayın veliler, 4 Şubat Çarşamba saat 14:00 veli toplantısı yapılacaktır.',
    180 => '6 Şubat Cuma kermes düzenlenecek, saat 10:00 bahçede.',
    270 => 'Yarına beslenme formunu doldurup getirin lütfen.',
    360 => 'Sınıf kasası için 50₺ aidat topluyoruz, 8 Şubat\'a kadar.',
    440 => 'Kimlik fotokopisini Cuma\'ya kadar gönderin.',
];

$lines = [];
// Sistem satırı (":" yok -> parser atlar).
$lines[] = '02.02.2026, 07:59 - Mesajlar ve aramalar uçtan uca şifrelidir. Kimse okuyamaz veya dinleyemez.';

for ($i = 0; $i < 500; $i++) {
    $day = mt_rand(2, 8);           // hepsi 2026-02 haftası (ISO W06)
    $h = mt_rand(7, 22);
    $m = mt_rand(0, 59);
    $header = sprintf('%02d.02.2026, %02d:%02d', $day, $h, $m);
    $sender = $senders[mt_rand(0, count($senders) - 1)];

    if (isset($logistics[$i])) {
        $body = $logistics[$i];
        $sender = 'Zeynep Öğretmen';
    } else {
        $body = $noise[mt_rand(0, count($noise) - 1)];
    }

    $lines[] = $header . ' - ' . $sender . ': ' . $body;
}

$out = dirname(__DIR__) . '/fixtures/synthetic_large_500.txt';
file_put_contents($out, implode("\n", $lines) . "\n");

echo count($lines) . " satır yazıldı -> " . $out . "\n";
echo "(1 sistem satırı + 500 mesaj; 5 gömülü lojistik, 495 gürültü)\n";
