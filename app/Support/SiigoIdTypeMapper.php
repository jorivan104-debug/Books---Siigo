<?php

namespace App\Support;

class SiigoIdTypeMapper
{
    public static function fromZoho(?string $zohoValue): string
    {
        $map = (array) config('siigo.id_type_map', []);
        $default = (string) config('siigo.id_type_default', '13');

        if ($zohoValue === null || trim($zohoValue) === '') {
            return $default;
        }

        $normalized = self::normalize($zohoValue);

        if (array_key_exists($normalized, $map)) {
            return (string) $map[$normalized];
        }

        if (preg_match('/^\d+$/', $normalized) === 1) {
            return $normalized;
        }

        return $default;
    }

    private static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $replacements = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
            'ñ' => 'n',
        ];

        return strtr($value, $replacements);
    }
}
