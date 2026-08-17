<?php

namespace App\Support;

class Document
{
    public static function normalize(?string $value): string
    {
        return preg_replace('/\D+/', '', $value ?? '') ?? '';
    }

    public static function isValid(string $document, string $type): bool
    {
        return match ($type) {
            'cpf' => self::isValidCpf($document),
            'cnpj' => self::isValidCnpj($document),
            default => false,
        };
    }

    public static function isValidCpf(string $document): bool
    {
        if (! preg_match('/^\d{11}$/', $document) || self::hasRepeatedDigits($document)) {
            return false;
        }

        $firstDigit = self::calculateCpfDigit(substr($document, 0, 9), 10);
        $secondDigit = self::calculateCpfDigit(substr($document, 0, 10), 11);

        return $firstDigit === (int) $document[9] && $secondDigit === (int) $document[10];
    }

    public static function isValidCnpj(string $document): bool
    {
        if (! preg_match('/^\d{14}$/', $document) || self::hasRepeatedDigits($document)) {
            return false;
        }

        $firstDigit = self::calculateWeightedDigit(substr($document, 0, 12), [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
        $secondDigit = self::calculateWeightedDigit(substr($document, 0, 13), [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);

        return $firstDigit === (int) $document[12] && $secondDigit === (int) $document[13];
    }

    private static function hasRepeatedDigits(string $document): bool
    {
        return count(array_unique(str_split($document))) === 1;
    }

    private static function calculateCpfDigit(string $base, int $initialWeight): int
    {
        $sum = 0;

        foreach (str_split($base) as $index => $digit) {
            $sum += (int) $digit * ($initialWeight - $index);
        }

        $result = ($sum * 10) % 11;

        return $result === 10 ? 0 : $result;
    }

    /**
     * @param  array<int, int>  $weights
     */
    private static function calculateWeightedDigit(string $base, array $weights): int
    {
        $sum = 0;

        foreach (str_split($base) as $index => $digit) {
            $sum += (int) $digit * $weights[$index];
        }

        $remainder = $sum % 11;

        return $remainder < 2 ? 0 : 11 - $remainder;
    }
}
