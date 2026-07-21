<?php

declare(strict_types=1);

namespace WABridge\Tests;

/**
 * Minimal test temel sınıfı — harici framework yok. Assertion başarısızlığında
 * AssertionFailed fırlatır; run.php yakalar ve raporlar.
 */
abstract class TestCase
{
    private int $assertions = 0;

    public function assertionCount(): int
    {
        return $this->assertions;
    }

    protected function assertTrue(bool $cond, string $msg = ''): void
    {
        $this->assertions++;
        if (!$cond) {
            throw new AssertionFailed($msg !== '' ? $msg : 'assertTrue başarısız');
        }
    }

    protected function assertFalse(bool $cond, string $msg = ''): void
    {
        $this->assertTrue(!$cond, $msg !== '' ? $msg : 'assertFalse başarısız');
    }

    protected function assertSame(mixed $expected, mixed $actual, string $msg = ''): void
    {
        $this->assertions++;
        if ($expected !== $actual) {
            throw new AssertionFailed(sprintf(
                "%s\n  beklenen: %s\n  gerçek:   %s",
                $msg !== '' ? $msg : 'assertSame başarısız',
                self::dump($expected),
                self::dump($actual),
            ));
        }
    }

    protected function assertCount(int $expected, array $actual, string $msg = ''): void
    {
        $this->assertSame($expected, count($actual), $msg !== '' ? $msg : 'assertCount başarısız');
    }

    protected function assertStringContains(string $needle, string $haystack, string $msg = ''): void
    {
        $this->assertions++;
        if (!str_contains($haystack, $needle)) {
            throw new AssertionFailed(sprintf(
                "%s\n  aranan: %s\n  içinde: %s",
                $msg !== '' ? $msg : 'assertStringContains başarısız',
                $needle,
                $haystack,
            ));
        }
    }

    private static function dump(mixed $v): string
    {
        if (is_string($v)) {
            return "'" . $v . "'";
        }
        return var_export($v, true);
    }
}
