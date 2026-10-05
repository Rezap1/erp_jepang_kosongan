<?php

namespace App\Support;

final class AlumniPathway
{
    public const TG = 'TG';

    public const MAGANG = 'MAGANG';

    public const ENGINEER = 'ENGINEER';

    /** @return list<string> */
    public static function values(): array
    {
        return [self::TG, self::MAGANG, self::ENGINEER];
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::TG => 'Tokutei Ginou (TG)',
            self::MAGANG => 'Magang',
            self::ENGINEER => 'Engineer',
        ];
    }

    public static function normalize(mixed $value): ?string
    {
        $value = strtoupper(trim((string) $value));

        return in_array($value, self::values(), true) ? $value : null;
    }

    public static function label(mixed $value): string
    {
        return self::options()[self::normalize($value) ?? ''] ?? 'Belum diketahui';
    }
}
