<?php

declare(strict_types=1);

namespace WABridge\Classify;

/**
 * CLAUDE.md'deki 5 sınıflama etiketi. Backing value ascii tutulur;
 * insan-okur Türkçe karşılık human() ile verilir.
 */
enum Label: string
{
    case Etkinlik = 'etkinlik';
    case Gorev = 'gorev';
    case Oylama = 'oylama';
    case Para = 'para';
    case Gurultu = 'gurultu';

    public function human(): string
    {
        return match ($this) {
            self::Etkinlik => 'etkinlik',
            self::Gorev => 'görev',
            self::Oylama => 'oylama',
            self::Para => 'para',
            self::Gurultu => 'gürültü',
        };
    }

    /** Digest'te raporlanan (gürültü olmayan) sinyal etiketleri. */
    public function isSignal(): bool
    {
        return $this !== self::Gurultu;
    }
}
