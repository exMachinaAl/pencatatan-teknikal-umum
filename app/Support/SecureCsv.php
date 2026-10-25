<?php

namespace App\Support;

final class SecureCsv
{
    public static function value(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[\s\x00-\x1F]*[=+@\-]/u', $value) ? "'".$value : $value;
    }

    public static function writeRow($stream, array $row): void
    {
        fputcsv($stream, array_map(self::value(...), $row), ',', '"', '');
    }
}
