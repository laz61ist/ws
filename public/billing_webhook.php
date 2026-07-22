<?php

declare(strict_types=1);

use WABridge\Billing\WebhookController;
use WABridge\Support\App;
use WABridge\Support\Env;

require dirname(__DIR__) . '/src/autoload.php';

Env::load(dirname(__DIR__) . '/.env');

$raw = (string) file_get_contents('php://input');
$payload = json_decode($raw, true);
$signature = $_SERVER['HTTP_X_IYZ_SIGNATURE'] ?? null;

$controller = new WebhookController(App::database());
$result = $controller->handle(is_array($payload) ? $payload : [], is_string($signature) ? $signature : null);

header('Content-Type: application/json; charset=utf-8');
http_response_code($result['ok'] ? 200 : 400);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
