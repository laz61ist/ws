<?php

declare(strict_types=1);

namespace WABridge\Classify;

/**
 * Bir mesajın sınıflama + yapılandırma sonucu.
 *
 * $data etikete özel yapılandırılmış alanları tutar:
 *  - Etkinlik: ['tarih' => 'YYYY-MM-DD'|null, 'saat' => 'HH:MM'|null, 'ne' => string, 'cocuk' => string|null]
 *  - Gorev:    ['aksiyon' => string]
 *  - Oylama:   ['aksiyon' => string]
 *  - Para:     ['ne' => string, 'tutar' => string, 'son' => 'YYYY-MM-DD'|null]
 *  - Gurultu:  []
 */
final readonly class Classification
{
    /**
     * @param array<string,mixed> $data
     */
    public function __construct(
        public Label $label,
        public array $data = [],
        public float $confidence = 1.0,
    ) {
    }

    public static function noise(): self
    {
        return new self(Label::Gurultu, [], 1.0);
    }
}
