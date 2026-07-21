<?php

declare(strict_types=1);

/**
 * Bağımlılıksız test koşucusu. tests/Unit/*Test.php altındaki her sınıfın
 * test* metotlarını çağırır. Başarısızlıkta çıktıyı gösterir ve non-zero
 * exit code ile döner (CI için).
 *
 * Kullanım: composer test   (veya)   php tests/run.php
 */

require __DIR__ . '/bootstrap.php';

use WABridge\Tests\AssertionFailed;
use WABridge\Tests\TestCase;

// Büyük fixture deterministik üretilir (repoya konmaz). Yoksa üret.
$largeFixture = __DIR__ . '/fixtures/synthetic_large_500.txt';
if (!is_file($largeFixture)) {
    ob_start();
    require __DIR__ . '/tools/make_large_fixture.php';
    ob_end_clean();
}

$dir = __DIR__ . '/Unit';
$files = glob($dir . '/*Test.php') ?: [];
sort($files);

$totalTests = 0;
$totalAssertions = 0;
$failures = [];

foreach ($files as $file) {
    require $file;
}

foreach (get_declared_classes() as $class) {
    if (!is_subclass_of($class, TestCase::class)) {
        continue;
    }
    $ref = new ReflectionClass($class);
    if ($ref->isAbstract()) {
        continue;
    }

    foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if (!str_starts_with($method->getName(), 'test')) {
            continue;
        }
        $totalTests++;
        /** @var TestCase $instance */
        $instance = $ref->newInstance();
        $name = $class . '::' . $method->getName();
        try {
            $instance->{$method->getName()}();
            $totalAssertions += $instance->assertionCount();
            fwrite(STDOUT, "  \033[32m✓\033[0m {$name}\n");
        } catch (AssertionFailed $e) {
            $totalAssertions += $instance->assertionCount();
            $failures[] = [$name, 'ASSERTION', $e->getMessage()];
            fwrite(STDOUT, "  \033[31m✗\033[0m {$name}\n");
        } catch (\Throwable $e) {
            $failures[] = [$name, 'HATA', $e::class . ': ' . $e->getMessage() . "\n    " . $e->getFile() . ':' . $e->getLine()];
            fwrite(STDOUT, "  \033[31m✗\033[0m {$name}  (istisna)\n");
        }
    }
}

fwrite(STDOUT, "\n");
fwrite(STDOUT, str_repeat('-', 60) . "\n");

if ($failures !== []) {
    fwrite(STDOUT, "\n\033[31mBAŞARISIZ:\033[0m\n\n");
    foreach ($failures as [$name, $kind, $detail]) {
        fwrite(STDOUT, "[{$kind}] {$name}\n{$detail}\n\n");
    }
    fwrite(STDOUT, sprintf(
        "\033[31m%d test, %d assertion, %d BAŞARISIZ\033[0m\n",
        $totalTests,
        $totalAssertions,
        count($failures),
    ));
    exit(1);
}

fwrite(STDOUT, sprintf(
    "\033[32mHEPSI GEÇTI: %d test, %d assertion\033[0m\n",
    $totalTests,
    $totalAssertions,
));
exit(0);
