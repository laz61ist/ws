<?php

declare(strict_types=1);

namespace WABridge\Support;

use WABridge\Auth\MagicLinkService;
use WABridge\Billing\Entitlement;
use WABridge\Digest\DigestRepository;
use WABridge\Group\GroupRepository;
use WABridge\Mail\CurlMailer;
use WABridge\Mail\LogMailer;
use WABridge\Mail\MailerInterface;
use WABridge\Storage\Database;
use WABridge\Storage\Migrator;

/**
 * Web/CLI giriş noktaları için ince kablolama katmanı — Pipeline::fromEnv()
 * ile aynı desen: .env'den okur, bileşenleri kurar. Testler bunu KULLANMAZ
 * (testler Database::inMemory() ile izole kurulum yapar).
 */
final class App
{
    private static ?Database $database = null;

    public static function database(): Database
    {
        if (self::$database !== null) {
            return self::$database;
        }

        $path = (string) Env::get('WABRIDGE_DB_PATH', 'storage/wabridge.sqlite');
        // Göreli yol proje köküne göre çözülür (public/*.php'den çağrıldığında CWD farklı olabilir).
        if (!str_starts_with($path, '/')) {
            $path = dirname(__DIR__, 2) . '/' . $path;
        }

        $db = Database::fromFile($path);
        (new Migrator($db))->migrate(dirname(__DIR__, 2) . '/storage/migrations');

        self::$database = $db;
        return $db;
    }

    public static function mailer(): MailerInterface
    {
        $driver = (string) Env::get('WABRIDGE_MAIL_DRIVER', 'log');
        if ($driver === 'curl') {
            return new CurlMailer(
                (string) Env::get('WABRIDGE_MAIL_API_KEY', ''),
                (string) Env::get('WABRIDGE_MAIL_API_URL', ''),
                (string) Env::get('WABRIDGE_MAIL_FROM', 'no-reply@wabridge.local'),
            );
        }

        $logPath = (string) Env::get('WABRIDGE_MAIL_LOG_PATH', 'storage/mail.log');
        if (!str_starts_with($logPath, '/')) {
            $logPath = dirname(__DIR__, 2) . '/' . $logPath;
        }
        return new LogMailer($logPath);
    }

    public static function groupRepository(): GroupRepository
    {
        return new GroupRepository(self::database());
    }

    public static function digestRepository(): DigestRepository
    {
        return new DigestRepository(self::database());
    }

    public static function entitlement(): Entitlement
    {
        return new Entitlement(
            self::database(),
            self::digestRepository(),
            (int) Env::get('WABRIDGE_FREE_DIGEST_LIMIT', '4'),
        );
    }

    public static function magicLinkService(): MagicLinkService
    {
        return new MagicLinkService(
            self::database(),
            self::mailer(),
            self::groupRepository(),
            (string) Env::get('WABRIDGE_APP_BASE_URL', 'http://localhost:8000'),
            (int) Env::get('WABRIDGE_MAGIC_LINK_TTL_MIN', '20'),
        );
    }
}
