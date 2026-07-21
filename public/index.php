<?php

declare(strict_types=1);

use WABridge\Support\Csrf;
use WABridge\Support\Env;
use WABridge\Web\DigestController;

require dirname(__DIR__) . '/src/autoload.php';

Env::load(dirname(__DIR__) . '/.env');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$result = ['ok' => false, 'error' => null, 'digest' => null];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = (new DigestController())->handleUpload($_POST, $_FILES);
}

$csrf = Csrf::token();

/** htmlspecialchars kısaltması. */
function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$digest = $result['digest'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WABridge — Haftalık Digest</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
               max-width: 720px; margin: 0 auto; padding: 1.5rem; line-height: 1.5; }
        h1 { font-size: 1.5rem; }
        .card { border: 1px solid #8883; border-radius: 12px; padding: 1rem 1.25rem; margin: 1rem 0; }
        .error { border-color: #d33; background: #d3300f; color: #fff; }
        .consent { font-size: .9rem; }
        label.file { display: block; margin: .75rem 0; }
        button { background: #128c7e; color: #fff; border: 0; border-radius: 8px;
                 padding: .7rem 1.2rem; font-size: 1rem; cursor: pointer; }
        button:hover { background: #0e6f63; }
        table { width: 100%; border-collapse: collapse; margin: .5rem 0; }
        th, td { text-align: left; padding: .4rem .5rem; border-bottom: 1px solid #8883; font-size: .95rem; }
        ul { margin: .3rem 0; padding-left: 1.2rem; }
        .muted { color: #8889; font-size: .85rem; }
        .badge { display: inline-block; background: #8882; border-radius: 999px; padding: .1rem .6rem; font-size: .8rem; }
        details summary { cursor: pointer; }
        pre { overflow-x: auto; background: #8881; padding: .75rem; border-radius: 8px; }
    </style>
</head>
<body>
    <h1>WABridge</h1>
    <p class="muted">WhatsApp veli grubu export .txt'sini yükle → haftalık lojistik digest'i al.
    Ham sohbet işlenir, digest üretilir ve dosya <strong>anında silinir</strong> (KVKK).</p>

    <?php if ($result['error'] !== null): ?>
        <div class="card error"><?= e($result['error']) ?></div>
    <?php endif; ?>

    <?php if ($digest === null): ?>
        <form class="card" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <label class="file">
                Export .txt dosyası:
                <input type="file" name="chat" accept=".txt,text/plain" required>
            </label>
            <label class="consent">
                <input type="checkbox" name="kvkk_consent" value="1" required>
                Sohbette 3. kişilerin verisi olabileceğini biliyorum; dosyanın işlenip
                <strong>hemen silinmesine</strong> ve kalıcı olarak saklanmamasına onay veriyorum.
            </label>
            <p><button type="submit">Digest oluştur</button></p>
        </form>
    <?php else: ?>
        <div class="card">
            <p><span class="badge">Hafta: <?= e((string) $digest['hafta']) ?></span>
               <span class="badge"><?= (int) $digest['elenen_gurultu_sayisi'] ?> gürültü elendi</span></p>

            <h2>📅 Takvim</h2>
            <?php if ($digest['takvim'] === []): ?>
                <p class="muted">Bu hafta takvim maddesi yok.</p>
            <?php else: ?>
                <table>
                    <tr><th>Tarih</th><th>Saat</th><th>Ne</th><th>Çocuk</th></tr>
                    <?php foreach ($digest['takvim'] as $t): ?>
                        <tr>
                            <td><?= e((string) ($t['tarih'] ?? '-')) ?></td>
                            <td><?= e((string) ($t['saat'] ?? '-')) ?></td>
                            <td><?= e((string) ($t['ne'] ?? '')) ?></td>
                            <td><?= e((string) ($t['cocuk'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>

            <h2>✅ Senden aksiyon</h2>
            <?php if ($digest['senden_aksiyon'] === []): ?>
                <p class="muted">Sana özel aksiyon yok.</p>
            <?php else: ?>
                <ul><?php foreach ($digest['senden_aksiyon'] as $a): ?><li><?= e((string) $a) ?></li><?php endforeach; ?></ul>
            <?php endif; ?>

            <h2>💰 Para talepleri</h2>
            <?php if ($digest['para_talepleri'] === []): ?>
                <p class="muted">Para talebi yok.</p>
            <?php else: ?>
                <table>
                    <tr><th>Ne</th><th>Tutar</th><th>Son tarih</th></tr>
                    <?php foreach ($digest['para_talepleri'] as $p): ?>
                        <tr>
                            <td><?= e((string) ($p['ne'] ?? '')) ?></td>
                            <td><?= e((string) ($p['tutar'] ?? '')) ?></td>
                            <td><?= e((string) ($p['son'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>

            <details>
                <summary class="muted">Ham JSON</summary>
                <pre><?= e(json_encode($digest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '') ?></pre>
            </details>
            <p><a href="<?= e($_SERVER['PHP_SELF']) ?>">← Yeni dosya yükle</a></p>
        </div>
    <?php endif; ?>

    <p class="muted">Yalnızca resmî WhatsApp "Sohbeti dışa aktar" .txt'si kullanılır.
    Scraping / yetkisiz API kullanılmaz.</p>
</body>
</html>
