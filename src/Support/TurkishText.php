<?php

declare(strict_types=1);

namespace WABridge\Support;

/**
 * Türkçe-duyarlı metin yardımcıları. Generic mb_strtolower "İ"/"I" harflerini
 * yanlış çevirir; sınıflama karşılaştırmaları bu yüzden buradan geçer.
 */
final class TurkishText
{
    public static function lower(string $s): string
    {
        // Türkçe özel eşlemeler önce (aksi halde "I"->"i" olur).
        $s = str_replace(
            ['İ', 'I', 'Ş', 'Ğ', 'Ü', 'Ö', 'Ç'],
            ['i', 'ı', 'ş', 'ğ', 'ü', 'ö', 'ç'],
            $s,
        );
        return mb_strtolower($s, 'UTF-8');
    }
}
