<?php
declare(strict_types=1);

namespace Performance\Review\Phar\Analyzer;

use Performance\Review\Phar\AnalyzerInterface;
use Performance\Review\Phar\Issue;
use Performance\Review\Phar\IssueInterface;
use Performance\Review\Phar\Util\MagentoHelper;
use Performance\Review\Phar\Util\ByteConverter;

class MysqlAnalyzer implements AnalyzerInterface
{
    public function analyze(string $magentoRoot): array
    {
        $issues = [];
        
        try {
            $env = MagentoHelper::getEnvConfig($magentoRoot);
            $pdo = MagentoHelper::getDatabaseConnection($env);
            
            if (!$pdo) {
                return $issues;
            }
            
            // Get MySQL version
            $stmt = $pdo->query("SELECT VERSION() as version");
            $version = $stmt->fetch(\PDO::FETCH_ASSOC)['version'];
            
            // Check MySQL/MariaDB version
            if (stripos($version, 'mariadb') !== false) {
                if (version_compare($version, '10.4', '<')) {
                    $issues[] = new Issue(
                        IssueInterface::PRIORITY_HIGH,
                        'MariaDB version too old',
                        "Current: $version\nRecommended: MariaDB 10.4 or higher for Magento 2.4.8"
                    );
                }
            } else {
                if (version_compare($version, '8.0', '<')) {
                    $issues[] = new Issue(
                        IssueInterface::PRIORITY_HIGH,
                        'MySQL version too old',
                        "Current: $version\nRecommended: MySQL 8.0 or higher for Magento 2.4.8"
                    );
                }
            }
            
            // Check key MySQL variables
            $variables = $this->getMysqlVariables($pdo);
            
            // InnoDB buffer pool size
            if (isset($variables['innodb_buffer_pool_size'])) {
                $bufferPoolSize = (int) $variables['innodb_buffer_pool_size'];
                $totalRam = $this->getSystemMemory();
                $recommendedSize = (int) ($totalRam * 0.7);
                
                if ($bufferPoolSize < $recommendedSize * 0.5) {
                    $issues[] = new Issue(
                        IssueInterface::PRIORITY_HIGH,
                        'InnoDB buffer pool size too small',
                        sprintf(
                            "Current: %s\nRecommended: %s (70%% of RAM)",
                            ByteConverter::formatBytes($bufferPoolSize),
                            ByteConverter::formatBytes($recommendedSize)
                        )
                    );
                }
            }
            
            // Max connections
            if (isset($variables['max_connections'])) {
                $maxConnections = (int) $variables['max_connections'];
                if ($maxConnections < 500) {
                    $issues[] = new Issue(
                        IssueInterface::PRIORITY_MEDIUM,
                        'Increase max_connections',
                        "Current: $maxConnections\nRecommended: 1000 or higher"
                    );
                }
            }
            
            // Thread cache size
            if (isset($variables['thread_cache_size'])) {
                $threadCacheSize = (int) $variables['thread_cache_size'];
                if ($threadCacheSize < 50) {
                    $issues[] = new Issue(
                        IssueInterface::PRIORITY_LOW,
                        'Increase thread_cache_size',
                        "Current: $threadCacheSize\nRecommended: 100"
                    );
                }
            }
            
            // Table open cache
            if (isset($variables['table_open_cache'])) {
                $tableOpenCache = (int) $variables['table_open_cache'];
                if ($tableOpenCache < 4000) {
                    $issues[] = new Issue(
                        IssueInterface::PRIORITY_MEDIUM,
                        'Increase table_open_cache',
                        "Current: $tableOpenCache\nRecommended: 8000"
                    );
                }
            }
            
            // Query cache (should be disabled in MySQL 8.0+)
            if (isset($variables['query_cache_type']) && $variables['query_cache_type'] !== 'OFF') {
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_MEDIUM,
                    'Disable query cache',
                    "Query cache is deprecated and can reduce performance.\n" .
                    "Current: Enabled\nRecommended: Disabled"
                );
            }
            
            // InnoDB log file size
            if (isset($variables['innodb_log_file_size'])) {
                $logFileSize = (int) $variables['innodb_log_file_size'];
                $recommendedSize = 512 * 1024 * 1024; // 512MB
                
                if ($logFileSize < $recommendedSize) {
                    $issues[] = new Issue(
                        IssueInterface::PRIORITY_MEDIUM,
                        'Increase InnoDB log file size',
                        sprintf(
                            "Current: %s\nRecommended: %s",
                            ByteConverter::formatBytes($logFileSize),
                            ByteConverter::formatBytes($recommendedSize)
                        )
                    );
                }
            }
            
            // Check all tables are InnoDB
            $dbName = MagentoHelper::getConfigValue($env, 'db/connection/default/dbname');
            $stmt = $pdo->query("
                SELECT COUNT(*) as count 
                FROM information_schema.TABLES 
                WHERE table_schema = '$dbName' 
                    AND engine != 'InnoDB'
            ");
            $nonInnodbCount = (int) $stmt->fetch(\PDO::FETCH_ASSOC)['count'];
            
            if ($nonInnodbCount > 0) {
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_HIGH,
                    'Non-InnoDB tables found',
                    "$nonInnodbCount tables are not using InnoDB engine.\n" .
                    "All tables should use InnoDB for optimal performance and consistency."
                );
            }
            
        } catch (\Exception $e) {
            $issues[] = new Issue(
                IssueInterface::PRIORITY_HIGH,
                'MySQL analysis failed',
                "Error: " . $e->getMessage()
            );
        }
        
        return $issues;
    }
    
    private function getMysqlVariables(\PDO $pdo): array
    {
        $variables = [];
        $stmt = $pdo->query("SHOW VARIABLES");
        
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $variables[$row['Variable_name']] = $row['Value'];
        }
        
        return $variables;
    }
    
    private function getSystemMemory(): int
    {
        // Try to get system memory (Linux/Mac)
        if (PHP_OS_FAMILY === 'Linux') {
            $meminfo = file_get_contents('/proc/meminfo');
            if (preg_match('/MemTotal:\s+(\d+)\s+kB/', $meminfo, $matches)) {
                return (int) $matches[1] * 1024;
            }
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            exec('sysctl -n hw.memsize', $output);
            if (!empty($output[0])) {
                return (int) $output[0];
            }
        }
        
        // Default to 8GB if we can't determine
        return 8 * 1024 * 1024 * 1024;
    }
}