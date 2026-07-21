<?php

declare(strict_types=1);

/**
 * Sıfır-bağımlılık PSR-4 autoloader (production/web girişi için).
 * `composer install` gerektirmez.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'WABridge\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $rel = substr($class, strlen($prefix));
    $file = __DIR__ . '/' . str_replace('\\', '/', $rel) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
