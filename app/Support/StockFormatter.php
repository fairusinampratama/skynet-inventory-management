<?php

namespace App\Support;

class StockFormatter
{
    public static function format(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $number = round((float) $value, 3);

        if (abs($number) < 0.0005) {
            $number = 0.0;
        }

        $formatted = rtrim(rtrim(number_format($number, 3, '.', ''), '0'), '.');

        return $formatted === '-0' ? '0' : $formatted;
    }

    public static function signed(mixed $value): string
    {
        $formatted = self::format($value);

        return (float) $value > 0 ? "+{$formatted}" : $formatted;
    }
}
