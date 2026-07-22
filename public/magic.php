<?php

declare(strict_types=1);

use WABridge\Auth\AuthSession;
use WABridge\Support\App;
use WABridge\Support\Env;
use WABridge\Web\AuthController;

require dirname(__DIR__) . '/src/autoload.php';

Env::load(dirname(__DIR__) . '/.env');

$token = is_string($_GET['token'] ?? null) ? $_GET['token'] : '';
$controller = new AuthController(App::magicLinkService(), App::database());
$result = $controller->consumeToken($token);

if ($result['ok']) {
    header('Location: index.php');
    exit;
}

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
        body { font-family: system-ui, sans-serif; max-width: 480px; margin: 3rem auto; padding: 1.5rem; }
        .card { border: 1px solid #d33; background: #d3300f; color: #fff; border-radius: 12px; padding: 1.25rem; }
        a { color: inherit; }
    </style>
</head>
<body>
    <div class="card"><?= e((string) $result['error']) ?></div>
    <p><a href="login.php">← Giriş sayfasına dön</a></p>
</body>
</html>
