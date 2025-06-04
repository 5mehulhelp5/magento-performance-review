<?php
declare(strict_types=1);

namespace Performance\Review\Phar\Analyzer;

use Performance\Review\Phar\AnalyzerInterface;
use Performance\Review\Phar\Issue;
use Performance\Review\Phar\IssueInterface;
use Performance\Review\Phar\Util\MagentoHelper;

class ApiAnalyzer implements AnalyzerInterface
{
    public function analyze(string $magentoRoot): array
    {
        $issues = [];
        
        try {
            $env = MagentoHelper::getEnvConfig($magentoRoot);
            
            // Check if API-related modules are enabled
            $modules = MagentoHelper::getActiveModules($magentoRoot);
            $webApiEnabled = in_array('Magento_Webapi', $modules);
            $graphQlEnabled = in_array('Magento_GraphQl', $modules);
            
            // Read configuration from database if possible
            $configValues = $this->getConfigFromDatabase($env);
            
            // Check OAuth settings
            if ($webApiEnabled) {
                // Check access token lifetime
                $accessTokenLifetime = (int) $this->getConfigValue($configValues, 'oauth/access_token_lifetime/admin', '4');
                if ($accessTokenLifetime > 24) {
                    $issues[] = new Issue(
                        IssueInterface::PRIORITY_MEDIUM,
                        'OAuth token lifetime too long',
                        "Long-lived tokens are a security risk.\n" .
                        "Current: {$accessTokenLifetime} hours\nRecommended: 4-8 hours"
                    );
                }
                
                // Check if rate limiting is configured
                $rateLimitingEnabled = $this->getConfigValue($configValues, 'webapi/webapisecurity/allow_insecure', '0');
                if ($rateLimitingEnabled === '1') {
                    $issues[] = new Issue(
                        IssueInterface::PRIORITY_HIGH,
                        'API security disabled',
                        "Insecure API access is allowed.\n" .
                        "This is a major security risk.\n" .
                        "Disable: webapi/webapisecurity/allow_insecure"
                    );
                }
            }
            
            // Check GraphQL specific settings
            if ($graphQlEnabled) {
                // Check query depth limit
                $queryDepth = (int) $this->getConfigValue($configValues, 'graphql/query_complexity_and_depth/max_query_depth', '20');
                if ($queryDepth > 20 || $queryDepth == 0) {
                    $issues[] = new Issue(
                        IssueInterface::PRIORITY_MEDIUM,
                        'GraphQL query depth limit too high',
                        "Deep queries can cause performance issues.\n" .
                        "Current: " . ($queryDepth == 0 ? 'Unlimited' : $queryDepth) . "\n" .
                        "Recommended: 15-20"
                    );
                }
                
                // Check query complexity
                $queryComplexity = (int) $this->getConfigValue($configValues, 'graphql/query_complexity_and_depth/max_query_complexity', '300');
                if ($queryComplexity > 1000 || $queryComplexity == 0) {
                    $issues[] = new Issue(
                        IssueInterface::PRIORITY_MEDIUM,
                        'GraphQL query complexity limit too high',
                        "Complex queries can exhaust resources.\n" .
                        "Current: " . ($queryComplexity == 0 ? 'Unlimited' : $queryComplexity) . "\n" .
                        "Recommended: 300-500"
                    );
                }
            }
            
            // Check REST API settings
            if ($webApiEnabled) {
                // Check default page size
                $defaultPageSize = (int) $this->getConfigValue($configValues, 'webapi/soap/default_page_size', '20');
                if ($defaultPageSize > 100) {
                    $issues[] = new Issue(
                        IssueInterface::PRIORITY_MEDIUM,
                        'API default page size too large',
                        "Large page sizes can cause memory issues.\n" .
                        "Current: $defaultPageSize\nRecommended: 20-50"
                    );
                }
                
                // Check max page size
                $maxPageSize = (int) $this->getConfigValue($configValues, 'webapi/soap/max_page_size', '300');
                if ($maxPageSize > 500 || $maxPageSize == 0) {
                    $issues[] = new Issue(
                        IssueInterface::PRIORITY_HIGH,
                        'API max page size too large',
                        "Very large page sizes can cause timeouts.\n" .
                        "Current: " . ($maxPageSize == 0 ? 'Unlimited' : $maxPageSize) . "\n" .
                        "Recommended: 200-300"
                    );
                }
            }
            
            // Check for Swagger/OpenAPI in production
            if (in_array('Magento_Swagger', $modules)) {
                $mode = MagentoHelper::getConfigValue($env, 'MAGE_MODE', 'default');
                if ($mode === 'production') {
                    $issues[] = new Issue(
                        IssueInterface::PRIORITY_MEDIUM,
                        'Swagger module enabled in production',
                        "Swagger UI exposes API structure.\n" .
                        "Consider disabling in production:\n" .
                        "php bin/magento module:disable Magento_Swagger"
                    );
                }
            }
            
            // General API recommendations
            if ($webApiEnabled || $graphQlEnabled) {
                // Check for API response caching
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_LOW,
                    'Consider API response caching',
                    "Implement caching for frequently accessed API endpoints.\n" .
                    "Use Varnish or CDN for GET requests"
                );
                
                // Rate limiting recommendation
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_MEDIUM,
                    'Implement API rate limiting',
                    "Rate limiting prevents API abuse.\n" .
                    "Consider implementing rate limits per IP/token"
                );
            }
            
        } catch (\Exception $e) {
            $issues[] = new Issue(
                IssueInterface::PRIORITY_HIGH,
                'API analysis failed',
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
}