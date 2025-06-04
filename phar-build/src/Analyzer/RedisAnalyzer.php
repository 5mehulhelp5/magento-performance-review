<?php
declare(strict_types=1);

namespace Performance\Review\Phar\Analyzer;

use Performance\Review\Phar\AnalyzerInterface;
use Performance\Review\Phar\Issue;
use Performance\Review\Phar\IssueInterface;
use Performance\Review\Phar\Util\MagentoHelper;
use Performance\Review\Phar\Util\ByteConverter;

class RedisAnalyzer implements AnalyzerInterface
{
    public function analyze(string $magentoRoot): array
    {
        $issues = [];
        
        try {
            $env = MagentoHelper::getEnvConfig($magentoRoot);
            
            // Check if Redis is configured
            $cacheBackend = MagentoHelper::getConfigValue($env, 'cache/frontend/default/backend');
            $sessionSave = MagentoHelper::getConfigValue($env, 'session/save');
            
            if ($cacheBackend !== 'Cm_Cache_Backend_Redis' && $sessionSave !== 'redis') {
                // Redis not configured, skip analysis
                return $issues;
            }
            
            // Check cache Redis configuration
            if ($cacheBackend === 'Cm_Cache_Backend_Redis') {
                $redisConfig = MagentoHelper::getConfigValue($env, 'cache/frontend/default/backend_options', []);
                $issues = array_merge($issues, $this->analyzeRedisConfig($redisConfig, 'Cache'));
            }
            
            // Check page cache Redis configuration
            $pageCacheBackend = MagentoHelper::getConfigValue($env, 'cache/frontend/page_cache/backend');
            if ($pageCacheBackend === 'Cm_Cache_Backend_Redis') {
                $redisConfig = MagentoHelper::getConfigValue($env, 'cache/frontend/page_cache/backend_options', []);
                $issues = array_merge($issues, $this->analyzeRedisConfig($redisConfig, 'Page Cache'));
            }
            
            // Check session Redis configuration
            if ($sessionSave === 'redis') {
                $redisConfig = MagentoHelper::getConfigValue($env, 'session/redis', []);
                $issues = array_merge($issues, $this->analyzeSessionRedisConfig($redisConfig));
            }
            
        } catch (\Exception $e) {
            $issues[] = new Issue(
                IssueInterface::PRIORITY_HIGH,
                'Redis analysis failed',
                "Error: " . $e->getMessage()
            );
        }
        
        return $issues;
    }
    
    private function analyzeRedisConfig(array $config, string $type): array
    {
        $issues = [];
        
        // Check database number
        $database = (int) ($config['database'] ?? 0);
        if ($database === 0 && $type === 'Page Cache') {
            $issues[] = new Issue(
                IssueInterface::PRIORITY_MEDIUM,
                "Use different Redis database for $type",
                "Using the same Redis database for different purposes can cause conflicts.\n" .
                "Current: database $database\nRecommended: Use separate databases (e.g., 0 for cache, 1 for page cache)"
            );
        }
        
        // Check compression
        $compressionLib = $config['compress_data'] ?? '0';
        if ($compressionLib === '0' || !$compressionLib) {
            $issues[] = new Issue(
                IssueInterface::PRIORITY_LOW,
                "Enable compression for $type Redis",
                "Compression can reduce memory usage.\n" .
                "Current: Disabled\nRecommended: Enable compression (compress_data = 1)"
            );
        }
        
        // Check compression threshold
        $compressionThreshold = (int) ($config['compress_threshold'] ?? 2048);
        if ($compressionThreshold < 2048) {
            $issues[] = new Issue(
                IssueInterface::PRIORITY_LOW,
                "Increase compression threshold for $type",
                "Small values add overhead.\n" .
                "Current: $compressionThreshold bytes\nRecommended: 2048 bytes or higher"
            );
        }
        
        return $issues;
    }
    
    private function analyzeSessionRedisConfig(array $config): array
    {
        $issues = [];
        
        // Check log level
        $logLevel = (int) ($config['log_level'] ?? 1);
        if ($logLevel > 1) {
            $issues[] = new Issue(
                IssueInterface::PRIORITY_MEDIUM,
                'Reduce Redis session log level',
                "High log levels impact performance.\n" .
                "Current: $logLevel\nRecommended: 1 or 0"
            );
        }
        
        // Check database number
        $database = (int) ($config['database'] ?? 0);
        if ($database === 0) {
            $issues[] = new Issue(
                IssueInterface::PRIORITY_MEDIUM,
                'Use dedicated Redis database for sessions',
                "Sessions should use a separate database.\n" .
                "Current: database $database\nRecommended: Use database 2 or higher for sessions"
            );
        }
        
        // Check compression
        $compressionLib = $config['compress_data'] ?? '0';
        if ($compressionLib === '0' || !$compressionLib) {
            $issues[] = new Issue(
                IssueInterface::PRIORITY_LOW,
                'Enable compression for Redis sessions',
                "Compression can reduce memory usage for sessions.\n" .
                "Current: Disabled\nRecommended: Enable compression"
            );
        }
        
        // Check disable_locking
        $disableLocking = (int) ($config['disable_locking'] ?? 0);
        if ($disableLocking === 1) {
            $issues[] = new Issue(
                IssueInterface::PRIORITY_HIGH,
                'Enable session locking',
                "Disabling locking can cause race conditions.\n" .
                "Current: Locking disabled\nRecommended: Enable locking (disable_locking = 0)"
            );
        }
        
        return $issues;
    }
}