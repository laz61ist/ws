<?php

declare(strict_types=1);

use WABridge\Auth\AuthSession;
use WABridge\Support\App;
use WABridge\Support\Csrf;
use WABridge\Support\Env;
use WABridge\Web\AuthController;

require dirname(__DIR__) . '/src/autoload.php';

Env::load(dirname(__DIR__) . '/.env');

if (AuthSession::isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$sent = false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller = new AuthController(App::magicLinkService(), App::database());
    $result = $controller->requestLink($_POST);
    $sent = $result['ok'];
    $error = $result['error'];
}

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
    <title>WABridge — Giriş</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
               max-width: 480px; margin: 3rem auto; padding: 1.5rem; line-height: 1.5; }
        .card { border: 1px solid #8883; border-radius: 12px; padding: 1.25rem; }
        .error { border-color: #d33; background: #d3300f; color: #fff; }
        input[type=email] { width: 100%; padding: .6rem; font-size: 1rem; margin: .5rem 0 1rem; }
        button { background: #128c7e; color: #fff; border: 0; border-radius: 8px;
                 padding: .7rem 1.2rem; font-size: 1rem; cursor: pointer; width: 100%; }
        button:hover { background: #0e6f63; }
        .muted { color: #8889; font-size: .85rem; }
    </style>
</head>
<body>
    <h1>WABridge</h1>

    <?php if ($error !== null): ?>
        <div class="card error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($sent): ?>
        <div class="card">
            <p>E-postanı kontrol et — sana bir giriş bağlantısı gönderdik (20 dakika geçerli).</p>
        </div>
    <?php else: ?>
        <form class="card" method="post">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <label for="email">E-posta</label>
            <input type="email" id="email" name="email" required autofocus placeholder="veli@ornek.com">
            <button type="submit">Bağlantı gönder</button>
        </form>
    <?php endif; ?>

    <p class="muted">Parola yok — e-postana gönderdiğimiz bağlantıyla giriş yaparsın.</p>
</body>
</html>
