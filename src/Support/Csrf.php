<?php

declare(strict_types=1);

namespace WABridge\Support;

/**
 * Basit oturum tabanlı CSRF koruması. Token oturumda tutulur, form'a gömülür,
 * POST'ta hash_equals ile sabit-zamanlı doğrulanır.
 */
final class Csrf
{
    public static function token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['csrf_token'];
    }

    public static function check(?string $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $stored = $_SESSION['csrf_token'] ?? '';
        return is_string($token) && $token !== '' && is_string($stored) && $stored !== ''
            && hash_equals($stored, $token);
    }
}
