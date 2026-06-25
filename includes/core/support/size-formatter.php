<?php

namespace WPOptiKit\Core\Support;

final class SizeFormatter
{
    public static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1024 * 1024) {
            return self::formatScaled($bytes / 1024, 'KB');
        }

        if ($bytes < 1024 * 1024 * 1024) {
            return self::formatScaled($bytes / (1024 * 1024), 'MB');
        }

        return self::formatScaled($bytes / (1024 * 1024 * 1024), 'GB');
    }

    private static function formatScaled(float $value, string $unit): string
    {
        $precision = $value >= 100 ? 1 : 2;
        $formatted = number_format($value, $precision, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted . ' ' . $unit;
    }
}
