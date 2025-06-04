<?php
declare(strict_types=1);

namespace Performance\Review\Phar\Analyzer;

use Performance\Review\Phar\AnalyzerInterface;
use Performance\Review\Phar\Issue;
use Performance\Review\Phar\IssueInterface;
use Performance\Review\Phar\Util\MagentoHelper;

class FrontendAnalyzer implements AnalyzerInterface
{
    public function analyze(string $magentoRoot): array
    {
        $issues = [];
        
        try {
            $env = MagentoHelper::getEnvConfig($magentoRoot);
            
            // Check deployment configuration file
            $deployConfigFile = $magentoRoot . '/app/etc/config.php';
            $deployConfig = [];
            if (file_exists($deployConfigFile)) {
                $deployConfig = include $deployConfigFile;
            }
            
            // Read core_config_data from database if possible
            $configValues = $this->getConfigFromDatabase($env);
            
            // Check JS bundling
            if (!$this->getConfigValue($configValues, 'dev/js/enable_js_bundling', '0')) {
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_MEDIUM,
                    'Enable JavaScript bundling',
                    "JS bundling reduces the number of HTTP requests.\n" .
                    "Current: Disabled\nRecommended: Enable for production"
                );
            }
            
            // Check JS minification
            if (!$this->getConfigValue($configValues, 'dev/js/minify_files', '0')) {
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_MEDIUM,
                    'Enable JavaScript minification',
                    "Minifying JS files reduces file size.\n" .
                    "Current: Disabled\nRecommended: Enable for production"
                );
            }
            
            // Check CSS minification
            if (!$this->getConfigValue($configValues, 'dev/css/minify_files', '0')) {
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_MEDIUM,
                    'Enable CSS minification',
                    "Minifying CSS files reduces file size.\n" .
                    "Current: Disabled\nRecommended: Enable for production"
                );
            }
            
            // Check CSS merging
            if (!$this->getConfigValue($configValues, 'dev/css/merge_css_files', '0')) {
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_LOW,
                    'Consider CSS merging',
                    "Merging CSS files can reduce HTTP requests.\n" .
                    "Current: Disabled\nNote: Test thoroughly as it may cause issues"
                );
            }
            
            // Check static content signing
            if (!$this->getConfigValue($configValues, 'dev/static/sign', '0')) {
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_LOW,
                    'Enable static content signing',
                    "Signing helps with cache busting.\n" .
                    "Current: Disabled\nRecommended: Enable for better cache control"
                );
            }
            
            // Check for Varnish configuration
            $httpCacheHosts = MagentoHelper::getConfigValue($env, 'http_cache_hosts');
            if (empty($httpCacheHosts)) {
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_HIGH,
                    'Configure Varnish for full page caching',
                    "Varnish significantly improves frontend performance.\n" .
                    "Current: Not configured\nRecommended: Set up Varnish"
                );
            }
            
            // Check critical CSS
            if (!$this->getConfigValue($configValues, 'dev/css/use_css_critical_path', '0')) {
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_LOW,
                    'Consider critical CSS',
                    "Critical CSS can improve perceived performance.\n" .
                    "Current: Not configured\nConsider for above-the-fold optimization"
                );
            }
            
            // Check pub/static directory
            $staticDir = $magentoRoot . '/pub/static';
            if (is_dir($staticDir)) {
                $deployedVersions = $this->countDeployedVersions($staticDir);
                if ($deployedVersions > 5) {
                    $issues[] = new Issue(
                        IssueInterface::PRIORITY_LOW,
                        'Clean old static content versions',
                        "Found $deployedVersions deployed versions in pub/static.\n" .
                        "Old versions consume disk space.\n" .
                        "Run: php bin/magento setup:static-content:deploy --clear-static-content"
                    );
                }
            }
            
            // Check for WebP image support hint
            $issues[] = new Issue(
                IssueInterface::PRIORITY_LOW,
                'Consider WebP image format',
                "WebP images are smaller than JPEG/PNG.\n" .
                "Consider using WebP with fallbacks for better performance"
            );
            
        } catch (\Exception $e) {
            $issues[] = new Issue(
                IssueInterface::PRIORITY_HIGH,
                'Frontend analysis failed',
                "Error: " . $e->getMessage()
            );
        }
        
        return $issues;
    }
    
    private function getConfigFromDatabase(array $env): array
    {
        try {
            $pdo = MagentoHelper::getDatabaseConnection($env);
            if (!$pdo) {
                return [];
            }
            
            $stmt = $pdo->query("SELECT path, value FROM core_config_data WHERE scope = 'default'");
            $config = [];
            
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $config[$row['path']] = $row['value'];
            }
            
            return $config;
        } catch (\Exception $e) {
            return [];
        }
    }
    
    private function getConfigValue(array $config, string $path, string $default): string
    {
        return $config[$path] ?? $default;
    }
    
    private function countDeployedVersions(string $staticDir): int
    {
        $count = 0;
        $versionDirs = glob($staticDir . '/version*', GLOB_ONLYDIR);
        
        return count($versionDirs);
    }
}