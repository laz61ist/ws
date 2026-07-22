<?php

declare(strict_types=1);

namespace WABridge\Auth;

/**
 * Session tabanlı kullanıcı kimliği. Mevcut Support\Csrf ile aynı
 * session_start() deseni; ayrı bir framework/kütüphane eklemez.
 */
final class AuthSession
{
    public static function login(int $userId, int $groupId): void
    {
        self::ensureStarted();
        $_SESSION['user_id'] = $userId;
        $_SESSION['group_id'] = $groupId;
        session_regenerate_id(true);
    }

    public static function userId(): ?int
    {
        self::ensureStarted();
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function groupId(): ?int
    {
        self::ensureStarted();
        return isset($_SESSION['group_id']) ? (int) $_SESSION['group_id'] : null;
    }

    public static function isLoggedIn(): bool
    {
        return self::userId() !== null;
    }

    public static function logout(): void
    {
        self::ensureStarted();
        $_SESSION = [];
        session_destroy();
    }

    private static function ensureStarted(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}
