<?php
declare(strict_types=1);

namespace Performance\Review\Phar\Util;

class ByteConverter
{
    public static function convertToBytes(string $value): int
    {
        $value = strtolower(trim($value));
        $unit = preg_replace('/[^a-z]/', '', $value);
        $size = (int) preg_replace('/[^0-9]/', '', $value);

        $units = [
            'b' => 0,
            'k' => 1,
            'kb' => 1,
            'm' => 2,
            'mb' => 2,
            'g' => 3,
            'gb' => 3,
            't' => 4,
            'tb' => 4,
        ];

        $exponent = $units[$unit] ?? 0;
        return $size * pow(1024, $exponent);
    }

    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}