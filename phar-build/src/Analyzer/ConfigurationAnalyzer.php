<?php
declare(strict_types=1);

namespace Performance\Review\Phar\Analyzer;

use Performance\Review\Phar\AnalyzerInterface;
use Performance\Review\Phar\Issue;
use Performance\Review\Phar\IssueInterface;
use Performance\Review\Phar\Util\MagentoHelper;

class ConfigurationAnalyzer implements AnalyzerInterface
{
    public function analyze(string $magentoRoot): array
    {
        $issues = [];
        
        try {
            $env = MagentoHelper::getEnvConfig($magentoRoot);
            
            // Check deployment mode
            $mode = MagentoHelper::getConfigValue($env, 'MAGE_MODE', 'default');
            if ($mode !== 'production') {
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_HIGH,
                    'Switch from ' . $mode . ' mode to production mode',
                    "Developer mode significantly impacts performance and should never be used in production.\n" .
                    "Current: $mode\nRecommended: production"
                );
            }
            
            // Check cache backend
            $cacheBackend = MagentoHelper::getConfigValue($env, 'cache/frontend/default/backend');
            if (!$cacheBackend || $cacheBackend === 'Cm_Cache_Backend_File') {
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_HIGH,
                    'Configure Redis for cache storage',
                    "Using Redis for cache provides significantly better performance.\n" .
                    "Current: File-based cache\nRecommended: Redis cache backend"
                );
            }
            
            // Check page cache backend
            $pageCacheBackend = MagentoHelper::getConfigValue($env, 'cache/frontend/page_cache/backend');
            if (!$pageCacheBackend || $pageCacheBackend === 'Cm_Cache_Backend_File') {
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_HIGH,
                    'Configure Redis for full page cache',
                    "Redis should be used for full page cache for optimal performance.\n" .
                    "Current: File-based page cache\nRecommended: Redis page cache backend"
                );
            }
            
            // Check session storage
            $sessionSave = MagentoHelper::getConfigValue($env, 'session/save');
            if ($sessionSave === 'files') {
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_MEDIUM,
                    'Use Redis for session storage',
                    "Redis session storage is more performant than file-based sessions.\n" .
                    "Current: files\nRecommended: redis"
                );
            }
            
            // Check if Varnish is configured
            $httpCacheHosts = MagentoHelper::getConfigValue($env, 'http_cache_hosts');
            if (empty($httpCacheHosts)) {
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_MEDIUM,
                    'Configure Varnish for full page caching',
                    "Varnish provides the best full page cache performance for Magento.\n" .
                    "Current: No Varnish configuration detected\nRecommended: Configure Varnish"
                );
            }
            
        } catch (\Exception $e) {
            $issues[] = new Issue(
                IssueInterface::PRIORITY_HIGH,
                'Unable to read Magento configuration',
                "Error: " . $e->getMessage()
            );
        }
        
        return $issues;
    }
}