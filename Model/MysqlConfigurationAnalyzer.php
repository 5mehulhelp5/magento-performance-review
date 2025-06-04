<?php
/**
 * Copyright © Performance, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Performance\Review\Model;

use Magento\Framework\App\ResourceConnection;
use Performance\Review\Api\MysqlConfigurationAnalyzerInterface;
use Performance\Review\Api\Data\IssueInterface;
use Performance\Review\Model\IssueFactory;
use Performance\Review\Util\ByteConverter;
use Psr\Log\LoggerInterface;

/**
 * MySQL configuration analyzer for performance review
 *
 * @since 1.0.0
 */
class MysqlConfigurationAnalyzer implements MysqlConfigurationAnalyzerInterface
{
    /**
     * Recommended MySQL/MariaDB settings for Magento 2.4.8
     */
    private const RECOMMENDED_SETTINGS = [
        // InnoDB settings
        'innodb_buffer_pool_size' => [
            'type' => 'memory_percentage',
            'recommended_percentage' => 70,
            'min_value' => 1073741824, // 1GB minimum
            'priority' => IssueInterface::PRIORITY_HIGH,
            'description' => 'Should be 70% of available RAM for dedicated MySQL servers'
        ],
        'innodb_thread_concurrency' => [
            'type' => 'exact',
            'value' => 0,
            'priority' => IssueInterface::PRIORITY_MEDIUM,
            'description' => 'Set to 0 to allow MySQL to manage thread concurrency automatically'
        ],
        'innodb_flush_log_at_trx_commit' => [
            'type' => 'exact',
            'value' => 2,
            'priority' => IssueInterface::PRIORITY_HIGH,
            'description' => 'Set to 2 for better performance (slightly less ACID compliance)'
        ],
        'innodb_log_file_size' => [
            'type' => 'min',
            'min_value' => 536870912, // 512MB
            'priority' => IssueInterface::PRIORITY_MEDIUM,
            'description' => 'Larger log files reduce checkpoint frequency'
        ],
        'innodb_buffer_pool_instances' => [
            'type' => 'min',
            'min_value' => 8,
            'priority' => IssueInterface::PRIORITY_MEDIUM,
            'description' => 'Multiple instances reduce contention'
        ],
        'innodb_io_capacity' => [
            'type' => 'min',
            'min_value' => 2000,
            'priority' => IssueInterface::PRIORITY_LOW,
            'description' => 'Higher value for SSD storage'
        ],
        'innodb_io_capacity_max' => [
            'type' => 'min',
            'min_value' => 4000,
            'priority' => IssueInterface::PRIORITY_LOW,
            'description' => 'Higher value for SSD storage'
        ],
        // General settings
        'max_connections' => [
            'type' => 'min',
            'min_value' => 1000,
            'priority' => IssueInterface::PRIORITY_HIGH,
            'description' => 'Ensure enough connections for peak traffic'
        ],
        'thread_cache_size' => [
            'type' => 'min',
            'min_value' => 100,
            'priority' => IssueInterface::PRIORITY_MEDIUM,
            'description' => 'Cache threads to reduce creation overhead'
        ],
        'table_open_cache' => [
            'type' => 'min',
            'min_value' => 8000,
            'priority' => IssueInterface::PRIORITY_MEDIUM,
            'description' => 'Cache open table handles'
        ],
    ];

    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    /**
     * @var IssueFactory
     */
    private IssueFactory $issueFactory;

    /**
     * @var ByteConverter
     */
    private ByteConverter $byteConverter;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * Constructor
     *
     * @param ResourceConnection $resourceConnection
     * @param IssueFactory $issueFactory
     * @param ByteConverter $byteConverter
     * @param LoggerInterface $logger
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        IssueFactory $issueFactory,
        ByteConverter $byteConverter,
        LoggerInterface $logger
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->issueFactory = $issueFactory;
        $this->byteConverter = $byteConverter;
        $this->logger = $logger;
    }

    /**
     * Analyze MySQL configuration for performance issues
     *
     * @return IssueInterface[]
     */
    public function analyzeMysqlConfiguration(): array
    {
        $issues = [];

        try {
            $connection = $this->resourceConnection->getConnection();

            // Check MySQL version
            $versionIssues = $this->checkMysqlVersion($connection);
            if (!empty($versionIssues)) {
                $issues = array_merge($issues, $versionIssues);
            }

            // Check MySQL variables
            $variableIssues = $this->checkMysqlVariables($connection);
            if (!empty($variableIssues)) {
                $issues = array_merge($issues, $variableIssues);
            }

            // Check for query cache (should be disabled in MySQL 8.0)
            $queryCacheIssues = $this->checkQueryCache($connection);
            if (!empty($queryCacheIssues)) {
                $issues = array_merge($issues, $queryCacheIssues);
            }

            // Check performance schema
            $perfSchemaIssues = $this->checkPerformanceSchema($connection);
            if (!empty($perfSchemaIssues)) {
                $issues = array_merge($issues, $perfSchemaIssues);
            }

            // Check storage engine
            $engineIssues = $this->checkStorageEngine($connection);
            if (!empty($engineIssues)) {
                $issues = array_merge($issues, $engineIssues);
            }
        } catch (\Exception $e) {
            $this->logger->error('MySQL configuration analysis failed: ' . $e->getMessage());
        }

        return $issues;
    }

    /**
     * Check MySQL version
     *
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     * @return IssueInterface[]
     */
    private function checkMysqlVersion($connection): array
    {
        $issues = [];

        try {
            $version = $connection->fetchOne('SELECT VERSION()');
            $isMariaDB = stripos($version, 'mariadb') !== false;
            
            if ($isMariaDB) {
                // Extract MariaDB version
                preg_match('/(\d+\.\d+\.\d+)/', $version, $matches);
                $versionNumber = $matches[1] ?? '0.0.0';
                
                if (version_compare($versionNumber, '10.4', '<')) {
                    $issues[] = $this->issueFactory->create([
                        'priority' => IssueInterface::PRIORITY_HIGH,
                        'category' => 'MySQL Config',
                        'issue' => 'Upgrade MariaDB version',
                        'details' => 'MariaDB 10.4+ recommended for Magento 2.4.8',
                        'current_value' => $version,
                        'recommended_value' => 'MariaDB 10.4 or higher'
                    ]);
                }
            } else {
                // MySQL version check
                preg_match('/(\d+\.\d+\.\d+)/', $version, $matches);
                $versionNumber = $matches[1] ?? '0.0.0';
                
                if (version_compare($versionNumber, '8.0', '<')) {
                    $issues[] = $this->issueFactory->create([
                        'priority' => IssueInterface::PRIORITY_HIGH,
                        'category' => 'MySQL Config',
                        'issue' => 'Upgrade MySQL version',
                        'details' => 'MySQL 8.0+ recommended for Magento 2.4.8',
                        'current_value' => $version,
                        'recommended_value' => 'MySQL 8.0 or higher'
                    ]);
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to check MySQL version: ' . $e->getMessage());
        }

        return $issues;
    }

    /**
     * Check MySQL variables
     *
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     * @return IssueInterface[]
     */
    private function checkMysqlVariables($connection): array
    {
        $issues = [];

        try {
            // Get all variables at once
            $variables = [];
            $result = $connection->fetchAll('SHOW VARIABLES');
            foreach ($result as $row) {
                $variables[$row['Variable_name']] = $row['Value'];
            }

            // Get total RAM for buffer pool calculation
            $totalRam = $this->getTotalSystemMemory();

            foreach (self::RECOMMENDED_SETTINGS as $variable => $config) {
                if (!isset($variables[$variable])) {
                    continue;
                }

                $currentValue = $variables[$variable];
                $failed = false;
                $recommendedValue = '';

                switch ($config['type']) {
                    case 'exact':
                        $failed = $currentValue != $config['value'];
                        $recommendedValue = (string)$config['value'];
                        break;
                    
                    case 'min':
                        $currentBytes = $this->byteConverter->convertToBytes($currentValue);
                        $failed = $currentBytes < $config['min_value'];
                        $recommendedValue = $this->byteConverter->formatBytes($config['min_value']);
                        break;
                    
                    case 'memory_percentage':
                        if ($variable === 'innodb_buffer_pool_size' && $totalRam > 0) {
                            $currentBytes = $this->byteConverter->convertToBytes($currentValue);
                            $recommendedBytes = (int)($totalRam * $config['recommended_percentage'] / 100);
                            $failed = $currentBytes < $config['min_value'] || 
                                     $currentBytes < $recommendedBytes * 0.5; // Less than 50% of recommended
                            $recommendedValue = $this->byteConverter->formatBytes($recommendedBytes);
                        }
                        break;
                }

                if ($failed) {
                    $issues[] = $this->issueFactory->create([
                        'priority' => $config['priority'],
                        'category' => 'MySQL Config',
                        'issue' => sprintf('Optimize %s', str_replace('_', ' ', $variable)),
                        'details' => $config['description'],
                        'current_value' => $currentValue,
                        'recommended_value' => $recommendedValue
                    ]);
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to check MySQL variables: ' . $e->getMessage());
        }

        return $issues;
    }

    /**
     * Check query cache settings
     *
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     * @return IssueInterface[]
     */
    private function checkQueryCache($connection): array
    {
        $issues = [];

        try {
            $result = $connection->fetchAll("SHOW VARIABLES LIKE 'query_cache%'");
            $queryCache = [];
            foreach ($result as $row) {
                $queryCache[$row['Variable_name']] = $row['Value'];
            }

            // MySQL 8.0 removed query cache
            if (isset($queryCache['query_cache_type']) && $queryCache['query_cache_type'] !== 'OFF' && $queryCache['query_cache_type'] !== '0') {
                $issues[] = $this->issueFactory->create([
                    'priority' => IssueInterface::PRIORITY_HIGH,
                    'category' => 'MySQL Config',
                    'issue' => 'Disable query cache',
                    'details' => 'Query cache is deprecated and removed in MySQL 8.0. It often causes more harm than good.',
                    'current_value' => 'Enabled',
                    'recommended_value' => 'Disabled (query_cache_type = OFF)'
                ]);
            }
        } catch (\Exception $e) {
            // Query cache variables might not exist in MySQL 8.0+
            $this->logger->info('Query cache variables not found (expected for MySQL 8.0+)');
        }

        return $issues;
    }

    /**
     * Check performance schema
     *
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     * @return IssueInterface[]
     */
    private function checkPerformanceSchema($connection): array
    {
        $issues = [];

        try {
            $perfSchema = $connection->fetchOne("SELECT @@performance_schema");
            
            if ($perfSchema == '1') {
                $issues[] = $this->issueFactory->create([
                    'priority' => IssueInterface::PRIORITY_LOW,
                    'category' => 'MySQL Config',
                    'issue' => 'Consider disabling performance_schema in production',
                    'details' => 'Performance schema adds overhead. Disable if not actively used for monitoring.',
                    'current_value' => 'Enabled',
                    'recommended_value' => 'Disabled in production'
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->info('Could not check performance_schema: ' . $e->getMessage());
        }

        return $issues;
    }

    /**
     * Check storage engine usage
     *
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     * @return IssueInterface[]
     */
    private function checkStorageEngine($connection): array
    {
        $issues = [];

        try {
            // Check if any tables are not using InnoDB
            $nonInnoDBTables = $connection->fetchOne(
                "SELECT COUNT(*) FROM information_schema.TABLES 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND ENGINE != 'InnoDB' 
                AND TABLE_TYPE = 'BASE TABLE'"
            );

            if ($nonInnoDBTables > 0) {
                $issues[] = $this->issueFactory->create([
                    'priority' => IssueInterface::PRIORITY_MEDIUM,
                    'category' => 'MySQL Config',
                    'issue' => 'Convert all tables to InnoDB',
                    'details' => sprintf(
                        'Found %d tables not using InnoDB engine. InnoDB is recommended for all Magento tables.',
                        $nonInnoDBTables
                    ),
                    'current_value' => $nonInnoDBTables . ' non-InnoDB tables',
                    'recommended_value' => 'All tables use InnoDB'
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to check storage engines: ' . $e->getMessage());
        }

        return $issues;
    }

    /**
     * Get total system memory
     *
     * @return int
     */
    private function getTotalSystemMemory(): int
    {
        try {
            if (PHP_OS_FAMILY === 'Linux') {
                $memInfo = file_get_contents('/proc/meminfo');
                preg_match('/MemTotal:\s+(\d+)\s+kB/', $memInfo, $matches);
                return isset($matches[1]) ? (int)$matches[1] * 1024 : 0;
            } elseif (PHP_OS_FAMILY === 'Darwin') {
                $output = shell_exec('sysctl -n hw.memsize');
                return (int)trim($output);
            }
        } catch (\Exception $e) {
            $this->logger->error('Could not determine system memory: ' . $e->getMessage());
        }
        
        return 0;
    }


    /**
     * @inheritdoc
     */
    public function analyze(): array
    {
        return $this->analyzeMysqlConfiguration();
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return 'MySQL Configuration Analyzer';
    }

    /**
     * @inheritdoc
     */
    public function getCategory(): string
    {
        return 'MySQL Config';
    }
}