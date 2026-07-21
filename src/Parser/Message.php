<?php

declare(strict_types=1);

namespace WABridge\Parser;

/**
 * Parser'ın ürettiği kanonik mesaj kaydı. Tüm export format varyantları
 * (Android, iOS, farklı tarih/saat düzenleri) bu tek şekle indirgenir.
 *
 * KVKK notu: bu nesneler yalnızca bellekte, işlem süresince yaşar.
 * Kalıcı depoya yazılmaz.
 */
final readonly class Message
{
    public function __construct(
        public \DateTimeImmutable $at,
        public string $sender,
        public string $body,
        public int $lineNo,
    ) {
    }

    /** ISO-8601 hafta anahtarı, ör. "2026-W04". Digest haftalandırması için. */
    public function isoWeekKey(): string
    {
        return $this->at->format('o-\WW');
    }
}
