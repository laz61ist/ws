<?php

declare(strict_types=1);

use WABridge\Auth\AuthSession;
use WABridge\Support\App;
use WABridge\Support\Csrf;
use WABridge\Support\Env;
use WABridge\Web\AuthController;
use WABridge\Web\HistoryController;

require dirname(__DIR__) . '/src/autoload.php';

Env::load(dirname(__DIR__) . '/.env');

if (!AuthSession::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$userId = AuthSession::userId();
$groupId = AuthSession::groupId();

$deleteError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_account') {
    if (!Csrf::check(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null)) {
        $deleteError = 'Oturum doğrulaması başarısız (CSRF). Sayfayı yenileyip tekrar deneyin.';
    } else {
        (new AuthController(App::magicLinkService(), App::database()))->deleteAccount((int) $userId);
        header('Location: login.php');
        exit;
    }
}

$history = (new HistoryController(App::digestRepository()))->listForGroup((int) $groupId);
$csrf = Csrf::token();

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WABridge — Geçmiş</title>
    <style>
        :root { color-scheme: light dark; }
        body { font-family: system-ui, sans-serif; max-width: 720px; margin: 0 auto; padding: 1.5rem; line-height: 1.5; }
        .card { border: 1px solid #8883; border-radius: 12px; padding: 1rem 1.25rem; margin: 1rem 0; }
        .error { border-color: #d33; background: #d3300f; color: #fff; }
        .muted { color: #8889; font-size: .85rem; }
        .badge { display: inline-block; background: #8882; border-radius: 999px; padding: .1rem .6rem; font-size: .8rem; }
        nav a { margin-right: 1rem; }
        button.danger { background: #d33; color: #fff; border: 0; border-radius: 8px; padding: .5rem 1rem; cursor: pointer; }
    </style>
</head>
<body>
    <nav><a href="index.php">← Yeni digest</a></nav>
    <h1>Geçmiş digest'lerim</h1>

    <?php if ($deleteError !== null): ?>
        <div class="card error"><?= e($deleteError) ?></div>
    <?php endif; ?>

    <?php if ($history === []): ?>
        <p class="muted">Henüz hiç digest oluşturmadın.</p>
    <?php else: ?>
        <?php foreach ($history as $item): ?>
            <div class="card">
                <p><span class="badge">Hafta: <?= e($item['weekLabel']) ?></span>
                   <span class="muted"><?= e($item['createdAt']) ?></span></p>
                <p class="muted">
                    <?= count($item['digest']['takvim']) ?> takvim ·
                    <?= count($item['digest']['senden_aksiyon']) ?> aksiyon ·
                    <?= count($item['digest']['para_talepleri']) ?> para talebi ·
                    <?= (int) $item['digest']['elenen_gurultu_sayisi'] ?> gürültü elendi
                </p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <details class="card">
        <summary>Hesabımı ve tüm digest'lerimi sil</summary>
        <p class="muted">Bu işlem geri alınamaz — hesabın, gruplarına ait tüm digest kayıtların
        kalıcı olarak silinir (KVKK unutulma hakkı).</p>
        <form method="post" onsubmit="return confirm('Emin misin? Bu işlem geri alınamaz.');">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="delete_account">
            <button type="submit" class="danger">Hesabımı sil</button>
        </form>
    </details>
</body>
</html>
