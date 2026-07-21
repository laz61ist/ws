<?php

declare(strict_types=1);

namespace WABridge\Support;

/**
 * Minimal .env okuyucu. Secret'lar KODA yazılmaz; yalnızca runtime'da
 * .env dosyasından veya süreç ortamından okunur.
 */
final class Env
{
    /** @var array<string,string> */
    private static array $cache = [];

    private static bool $loaded = false;

    public static function load(string $path): void
    {
        self::$loaded = true;

        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            // Basit tırnak temizliği.
            if (strlen($value) >= 2) {
                $first = $value[0];
                if (($first === '"' || $first === "'") && $value[strlen($value) - 1] === $first) {
                    $value = substr($value, 1, -1);
                }
            }
            self::$cache[$key] = $value;
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }
        $fromEnv = getenv($key);
        if ($fromEnv !== false) {
            return $fromEnv;
        }
        return $default;
    }

    public static function isLoaded(): bool
    {
        return self::$loaded;
    }
}
