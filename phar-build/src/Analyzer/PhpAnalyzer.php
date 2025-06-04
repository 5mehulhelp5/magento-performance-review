<?php
declare(strict_types=1);

namespace Performance\Review\Phar\Analyzer;

use Performance\Review\Phar\AnalyzerInterface;
use Performance\Review\Phar\Issue;
use Performance\Review\Phar\IssueInterface;
use Performance\Review\Phar\Util\ByteConverter;

class PhpAnalyzer implements AnalyzerInterface
{
    public function analyze(string $magentoRoot): array
    {
        $issues = [];
        
        // Check PHP version
        $phpVersion = PHP_VERSION;
        $versionParts = explode('.', $phpVersion);
        $majorMinor = $versionParts[0] . '.' . $versionParts[1];
        
        if (!in_array($majorMinor, ['8.1', '8.2', '8.3'])) {
            $issues[] = new Issue(
                IssueInterface::PRIORITY_HIGH,
                'PHP version not optimal for Magento 2.4.8',
                "Magento 2.4.8 requires PHP 8.1, 8.2, or 8.3.\n" .
                "Current: $phpVersion\nRecommended: PHP 8.2 or 8.3"
            );
        }
        
        // Check memory limit
        $memoryLimit = ini_get('memory_limit');
        $memoryBytes = ByteConverter::convertToBytes($memoryLimit);
        $recommendedBytes = ByteConverter::convertToBytes('2G');
        
        if ($memoryBytes < $recommendedBytes) {
            $issues[] = new Issue(
                IssueInterface::PRIORITY_HIGH,
                'Increase PHP memory limit',
                "PHP memory limit is too low for optimal Magento performance.\n" .
                "Current: $memoryLimit\nRecommended: 2G or higher"
            );
        }
        
        // Check max execution time
        $maxExecutionTime = (int) ini_get('max_execution_time');
        if ($maxExecutionTime > 0 && $maxExecutionTime < 18000) {
            $issues[] = new Issue(
                IssueInterface::PRIORITY_MEDIUM,
                'Increase max_execution_time',
                "Max execution time may be too low for long-running operations.\n" .
                "Current: {$maxExecutionTime}s\nRecommended: 18000s (5 hours) or 0 (unlimited)"
            );
        }
        
        // Check OPcache
        if (!extension_loaded('Zend OPcache') || !ini_get('opcache.enable')) {
            $issues[] = new Issue(
                IssueInterface::PRIORITY_HIGH,
                'Enable OPcache',
                "OPcache is critical for PHP performance.\n" .
                "Current: Disabled or not installed\nRecommended: Enable OPcache"
            );
        } else {
            // Check OPcache settings
            $opcacheMemory = (int) ini_get('opcache.memory_consumption');
            if ($opcacheMemory < 512) {
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_MEDIUM,
                    'Increase OPcache memory',
                    "OPcache memory consumption is too low.\n" .
                    "Current: {$opcacheMemory}MB\nRecommended: 512MB or higher"
                );
            }
            
            $maxFiles = (int) ini_get('opcache.max_accelerated_files');
            if ($maxFiles < 130000) {
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_MEDIUM,
                    'Increase OPcache max_accelerated_files',
                    "Magento has many PHP files that need to be cached.\n" .
                    "Current: $maxFiles\nRecommended: 130000"
                );
            }
        }
        
        // Check realpath cache
        $realpathCacheSize = ini_get('realpath_cache_size');
        $realpathBytes = ByteConverter::convertToBytes($realpathCacheSize);
        $recommendedRealpathBytes = ByteConverter::convertToBytes('10M');
        
        if ($realpathBytes < $recommendedRealpathBytes) {
            $issues[] = new Issue(
                IssueInterface::PRIORITY_MEDIUM,
                'Increase realpath_cache_size',
                "Realpath cache improves file system performance.\n" .
                "Current: $realpathCacheSize\nRecommended: 10M"
            );
        }
        
        // Check for problematic extensions
        if (extension_loaded('xdebug')) {
            $issues[] = new Issue(
                IssueInterface::PRIORITY_HIGH,
                'Disable Xdebug in production',
                "Xdebug significantly impacts performance and should not be enabled in production.\n" .
                "Current: Enabled\nRecommended: Disable Xdebug"
            );
        }
        
        // Check max_input_vars
        $maxInputVars = (int) ini_get('max_input_vars');
        if ($maxInputVars < 10000) {
            $issues[] = new Issue(
                IssueInterface::PRIORITY_MEDIUM,
                'Increase max_input_vars',
                "Magento admin forms may require many input variables.\n" .
                "Current: $maxInputVars\nRecommended: 10000 or higher"
            );
        }
        
        return $issues;
    }
}