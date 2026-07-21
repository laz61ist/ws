<?php

declare(strict_types=1);

/**
 * Sıfır-bağımlılık PSR-4 autoloader. `composer install` gerektirmez;
 * `composer test` (php tests/run.php) offline koşar.
 */
spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'WABridge\\Tests\\' => __DIR__ . '/',
        'WABridge\\' => dirname(__DIR__) . '/src/',
    ];
    foreach ($prefixes as $prefix => $base) {
        if (str_starts_with($class, $prefix)) {
            $rel = substr($class, strlen($prefix));
            $file = $base . str_replace('\\', '/', $rel) . '.php';
            if (is_file($file)) {
                require $file;
                return;
            }
        }
    }
});
