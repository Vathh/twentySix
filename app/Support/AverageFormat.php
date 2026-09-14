<?php

namespace App\Support;

final class AverageFormat
{
    /**
     * Średnia 3-dartowa — zawsze xx.xx, nawet gdy część ułamkowa to zera (72 → 72.00).
     */
    public static function display(mixed $value, string $empty = '—'): string
    {
        if ($value === null || $value === '' || $value === '-' || $value === '–' || $value === '—') {
            return $empty;
        }
        if (! is_numeric($value)) {
            return $empty;
        }

        return number_format((float) $value, 2, '.', '');
    }
}
