<?php

namespace App\Support;

use InvalidArgumentException;

class Money
{
    public static function normalize(string $value): string
    {
        $value = str_replace(',', '.', trim($value));

        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            throw new InvalidArgumentException('Valor monetario invalido.');
        }

        [$whole, $decimal] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($whole, '0') ?: '0';

        return $whole.'.'.str_pad($decimal, 2, '0');
    }

    public static function cents(string $value): int
    {
        $normalized = self::normalize($value);
        [$whole, $decimal] = explode('.', $normalized);

        return ((int) $whole * 100) + (int) $decimal;
    }

    public static function fromCents(int $cents): string
    {
        $whole = intdiv($cents, 100);
        $decimal = $cents % 100;

        return $whole.'.'.str_pad((string) $decimal, 2, '0', STR_PAD_LEFT);
    }
}
