<?php
/**
 * Copyright © Performance, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Performance\Review\Util;

/**
 * Utility class for byte conversions
 *
 * @since 1.0.0
 */
class ByteConverter
{
    /**
     * Convert size string (e.g., "2M", "1G") to bytes
     *
     * @param string $value
     * @return int
     */
    public function convertToBytes(string $value): int
    {
        $value = trim($value);
        
        // If it's already numeric, return as is
        if (is_numeric($value)) {
            return (int)$value;
        }
        
        // Extract the numeric part and the unit
        $last = strtoupper(substr($value, -1));
        $numeric = (int)$value;

        switch ($last) {
            case 'T':
                $numeric *= 1024;
                // no break
            case 'G':
                $numeric *= 1024;
                // no break
            case 'M':
                $numeric *= 1024;
                // no break
            case 'K':
                $numeric *= 1024;
        }

        return $numeric;
    }

    /**
     * Format bytes to human readable format
     *
     * @param int $bytes
     * @param int $precision
     * @return string
     */
    public function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Convert bytes to megabytes
     *
     * @param int $bytes
     * @return float
     */
    public function bytesToMegabytes(int $bytes): float
    {
        return $bytes / 1024 / 1024;
    }

    /**
     * Convert bytes to gigabytes
     *
     * @param int $bytes
     * @return float
     */
    public function bytesToGigabytes(int $bytes): float
    {
        return $bytes / 1024 / 1024 / 1024;
    }
}