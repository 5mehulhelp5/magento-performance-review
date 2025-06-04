<?php
/**
 * Copyright © Performance, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Performance\Review\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Performance\Review\Api\Data\IssueInterface;
use Performance\Review\Model\IssueFactory;
use Psr\Log\LoggerInterface;

/**
 * Database performance analyzer
 *
 * @since 1.0.0
 */
class DatabaseAnalyzer
{
    /**
     * Size thresholds in GB
     */
    private const DATABASE_SIZE_WARNING_THRESHOLD = 20;
    private const DATABASE_SIZE_CRITICAL_THRESHOLD = 50;
    private const TABLE_SIZE_THRESHOLD_BYTES = 1073741824; // 1GB
    
    /**
     * Product count thresholds
     */
    private const PRODUCT_COUNT_WARNING = 100000;
    private const PRODUCT_COUNT_CRITICAL = 500000;
    
    /**
     * Category count threshold
     */
    private const CATEGORY_COUNT_WARNING = 10000;
    
    /**
     * Log table row thresholds
     */
    private const LOG_TABLE_ROW_THRESHOLD = 1000000;
    
    /**
     * URL rewrite thresholds
     */
    private const URL_REWRITE_WARNING = 500000;
    private const URL_REWRITE_CRITICAL = 1000000;

    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    /**
     * @var ProductCollectionFactory
     */
    private ProductCollectionFactory $productCollectionFactory;

    /**
     * @var CategoryCollectionFactory
     */
    private CategoryCollectionFactory $categoryCollectionFactory;

    /**
     * @var AdapterInterface
     */
    private AdapterInterface $connection;

    /**
     * @var IssueFactory
     */
    private IssueFactory $issueFactory;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * Constructor
     *
     * @param ResourceConnection $resourceConnection
     * @param ProductCollectionFactory $productCollectionFactory
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param IssueFactory $issueFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        ProductCollectionFactory $productCollectionFactory,
        CategoryCollectionFactory $categoryCollectionFactory,
        IssueFactory $issueFactory,
        LoggerInterface $logger
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->connection = $resourceConnection->getConnection();
        $this->issueFactory = $issueFactory;
        $this->logger = $logger;
    }

    /**
     * Analyze database for performance issues
     *
     * @return IssueInterface[]
     * @throws LocalizedException
     */
    public function analyzeDatabase(): array
    {
        $issues = [];

        try {
            // Check database size
            $dbSizeIssues = $this->checkDatabaseSize();
            $issues = array_merge($issues, $dbSizeIssues);

            // Check table sizes
            $tableSizeIssues = $this->checkTableSizes();
            $issues = array_merge($issues, $tableSizeIssues);

            // Check product/category counts
            $catalogIssues = $this->checkCatalogSize();
            $issues = array_merge($issues, $catalogIssues);

            // Check flat tables
            $flatTableIssues = $this->checkFlatTables();
            $issues = array_merge($issues, $flatTableIssues);

            // Check log tables
            $logTableIssues = $this->checkLogTables();
            $issues = array_merge($issues, $logTableIssues);

            // Check URL rewrites
            $urlRewriteIssues = $this->checkUrlRewrites();
            $issues = array_merge($issues, $urlRewriteIssues);
        } catch (\Exception $e) {
            $this->logger->error('Database analysis failed: ' . $e->getMessage());
            throw new LocalizedException(__('Failed to analyze database: %1', $e->getMessage()));
        }

        return $issues;
    }

    /**
     * Check database size
     *
     * @return IssueInterface[]
     */
    private function checkDatabaseSize(): array
    {
        $issues = [];
        
        try {
            $dbName = $this->connection->fetchOne("SELECT DATABASE()");
            $query = "SELECT 
                SUM(data_length + index_length) / 1024 / 1024 / 1024 AS size_gb
                FROM information_schema.tables 
                WHERE table_schema = :dbname";
            
            $sizeGb = (float) $this->connection->fetchOne($query, ['dbname' => $dbName]);
            
            if ($sizeGb > self::DATABASE_SIZE_CRITICAL_THRESHOLD) {
                $issues[] = $this->issueFactory->create([
                    'priority' => IssueInterface::PRIORITY_HIGH,
                    'category' => 'Database',
                    'issue' => 'Database size is very large',
                    'details' => sprintf(
                        'Database size is %.2f GB. Large databases can impact backup/restore times and overall performance.',
                        $sizeGb
                    ),
                    'current_value' => sprintf('%.2f GB', $sizeGb),
                    'recommended_value' => 'Regular cleanup and archiving of old data'
                ]);
            } elseif ($sizeGb > self::DATABASE_SIZE_WARNING_THRESHOLD) {
                $issues[] = $this->issueFactory->create([
                    'priority' => IssueInterface::PRIORITY_MEDIUM,
                    'category' => 'Database',
                    'issue' => 'Database size is growing large',
                    'details' => sprintf(
                        'Database size is %.2f GB. Consider implementing data archiving strategies.',
                        $sizeGb
                    ),
                    'current_value' => sprintf('%.2f GB', $sizeGb),
                    'recommended_value' => 'Monitor growth and plan for archiving'
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->warning('Failed to check database size: ' . $e->getMessage());
        }

        return $issues;
    }

    /**
     * Check table sizes
     *
     * @return IssueInterface[]
     */
    private function checkTableSizes(): array
    {
        $issues = [];
        $largeTables = [];
        
        try {
            $dbName = $this->connection->fetchOne("SELECT DATABASE()");
            $query = "SELECT 
                table_name,
                ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
                FROM information_schema.tables
                WHERE table_schema = :dbname
                AND (data_length + index_length) > :threshold
                ORDER BY (data_length + index_length) DESC
                LIMIT 10";
            
            $tables = $this->connection->fetchAll($query, [
                'dbname' => $dbName,
                'threshold' => self::TABLE_SIZE_THRESHOLD_BYTES
            ]);
            
            foreach ($tables as $table) {
                $largeTables[] = sprintf('%s (%.2f GB)', $table['table_name'], $table['size_mb'] / 1024);
            }
            
            if (!empty($largeTables)) {
                $issues[] = $this->issueFactory->create([
                    'priority' => IssueInterface::PRIORITY_HIGH,
                    'category' => 'Database',
                    'issue' => 'Large database tables detected',
                    'details' => 'The following tables are larger than 1GB: ' . implode(', ', array_slice($largeTables, 0, 5)),
                    'current_value' => count($largeTables) . ' tables over 1GB',
                    'recommended_value' => 'Review and clean up large tables, especially logs and temporary data'
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->warning('Failed to check table sizes: ' . $e->getMessage());
        }

        return $issues;
    }

    /**
     * Check catalog size
     *
     * @return IssueInterface[]
     */
    private function checkCatalogSize(): array
    {
        $issues = [];
        
        try {
            // Check product count
            $productCount = $this->productCollectionFactory->create()->getSize();
            
            if ($productCount > self::PRODUCT_COUNT_CRITICAL) {
                $issues[] = $this->issueFactory->create([
                    'priority' => IssueInterface::PRIORITY_HIGH,
                    'category' => 'Database',
                    'issue' => 'Very large product catalog',
                    'details' => sprintf(
                        'You have %s products. This can significantly impact indexing and search performance.',
                        number_format($productCount)
                    ),
                    'current_value' => number_format($productCount) . ' products',
                    'recommended_value' => 'Use Elasticsearch, optimize indexers, consider catalog segmentation'
                ]);
            } elseif ($productCount > self::PRODUCT_COUNT_WARNING) {
                $issues[] = $this->issueFactory->create([
                    'priority' => IssueInterface::PRIORITY_MEDIUM,
                    'category' => 'Database',
                    'issue' => 'Large product catalog',
                    'details' => sprintf(
                        'You have %s products. Ensure proper indexing and search configuration.',
                        number_format($productCount)
                    ),
                    'current_value' => number_format($productCount) . ' products',
                    'recommended_value' => 'Monitor indexing performance, use partial indexing'
                ]);
            }

            // Check category count
            $categoryCount = $this->categoryCollectionFactory->create()->getSize();
            
            if ($categoryCount > self::CATEGORY_COUNT_WARNING) {
                $issues[] = $this->issueFactory->create([
                    'priority' => IssueInterface::PRIORITY_MEDIUM,
                    'category' => 'Database',
                    'issue' => 'Large number of categories',
                    'details' => sprintf(
                        'You have %s categories. This can impact category tree rendering and navigation.',
                        number_format($categoryCount)
                    ),
                    'current_value' => number_format($categoryCount) . ' categories',
                    'recommended_value' => 'Review category structure, implement caching strategies'
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->warning('Failed to check catalog size: ' . $e->getMessage());
        }

        return $issues;
    }

    /**
     * Check flat tables configuration
     *
     * @return IssueInterface[]
     */
    private function checkFlatTables(): array
    {
        $issues = [];
        
        try {
            // Check if flat tables are enabled
            $flatProductEnabled = $this->connection->fetchOne(
                "SELECT value FROM " . $this->connection->getTableName('core_config_data') . 
                " WHERE path = :path",
                ['path' => 'catalog/frontend/flat_catalog_product']
            );
            
            $flatCategoryEnabled = $this->connection->fetchOne(
                "SELECT value FROM " . $this->connection->getTableName('core_config_data') . 
                " WHERE path = :path",
                ['path' => 'catalog/frontend/flat_catalog_category']
            );
            
            // For large catalogs, flat tables might not be optimal
            $productCount = $this->productCollectionFactory->create()->getSize();
            
            if ($productCount > 50000 && $flatProductEnabled == '1') {
                $issues[] = $this->issueFactory->create([
                    'priority' => IssueInterface::PRIORITY_MEDIUM,
                    'category' => 'Database',
                    'issue' => 'Flat catalog enabled for large catalog',
                    'details' => 'Flat catalog tables can become very large and slow with many products. Consider disabling for better performance.',
                    'current_value' => 'Flat catalog enabled',
                    'recommended_value' => 'Disable flat catalog for large catalogs'
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->warning('Failed to check flat tables: ' . $e->getMessage());
        }

        return $issues;
    }

    /**
     * Check log tables
     *
     * @return IssueInterface[]
     */
    private function checkLogTables(): array
    {
        $issues = [];
        $logTables = [
            'report_event' => 'Customer behavior tracking',
            'report_viewed_product_index' => 'Product view tracking',
            'customer_log' => 'Customer activity log',
            'customer_visitor' => 'Visitor tracking',
            'report_compared_product_index' => 'Product comparison tracking'
        ];
        
        try {
            // Build a single query to get counts for all tables
            $unions = [];
            foreach ($logTables as $table => $description) {
                $tableName = $this->connection->getTableName($table);
                if ($this->connection->isTableExists($tableName)) {
                    $select = $this->connection->select()
                        ->from($tableName, [
                            'table_name' => new \Zend_Db_Expr($this->connection->quote($table)),
                            'row_count' => new \Zend_Db_Expr('COUNT(*)')
                        ]);
                    $unions[] = $select;
                }
            }
            
            if (!empty($unions)) {
                // Execute single query with UNION
                $unionSelect = $this->connection->select();
                $unionSelect->union($unions, \Zend_Db_Select::SQL_UNION_ALL);
                
                $results = $this->connection->fetchAll($unionSelect);
                
                // Process results
                foreach ($results as $result) {
                    $table = $result['table_name'];
                    $count = (int) $result['row_count'];
                    
                    if ($count > self::LOG_TABLE_ROW_THRESHOLD) {
                        $description = $logTables[$table] ?? 'Log table';
                        $issues[] = $this->issueFactory->create([
                            'priority' => IssueInterface::PRIORITY_HIGH,
                            'category' => 'Database',
                            'issue' => sprintf('Large log table: %s', $table),
                            'details' => sprintf(
                                '%s table has %s rows. Large log tables impact performance.',
                                $description,
                                number_format($count)
                            ),
                            'current_value' => number_format($count) . ' rows',
                            'recommended_value' => 'Configure log cleaning in Admin > System > Configuration > Advanced > System'
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->warning('Failed to check log tables: ' . $e->getMessage());
        }

        return $issues;
    }

    /**
     * Check URL rewrites
     *
     * @return IssueInterface[]
     */
    private function checkUrlRewrites(): array
    {
        $issues = [];
        
        try {
            $tableName = $this->connection->getTableName('url_rewrite');
            // Use select builder to avoid SQL injection
            $select = $this->connection->select()
                ->from($tableName, ['count' => new \Zend_Db_Expr('COUNT(*)')]);
            $count = $this->connection->fetchOne($select);
            
            if ($count > self::URL_REWRITE_CRITICAL) {
                $issues[] = $this->issueFactory->create([
                    'priority' => IssueInterface::PRIORITY_HIGH,
                    'category' => 'Database',
                    'issue' => 'Excessive URL rewrites',
                    'details' => sprintf(
                        'URL rewrite table has %s rows. This significantly impacts routing performance.',
                        number_format((int) $count)
                    ),
                    'current_value' => number_format((int) $count) . ' URL rewrites',
                    'recommended_value' => 'Clean up duplicate rewrites, disable automatic generation if not needed'
                ]);
            } elseif ($count > self::URL_REWRITE_WARNING) {
                $issues[] = $this->issueFactory->create([
                    'priority' => IssueInterface::PRIORITY_MEDIUM,
                    'category' => 'Database',
                    'issue' => 'Large number of URL rewrites',
                    'details' => sprintf(
                        'URL rewrite table has %s rows. Monitor performance impact.',
                        number_format((int) $count)
                    ),
                    'current_value' => number_format((int) $count) . ' URL rewrites',
                    'recommended_value' => 'Regularly clean up old URL rewrites'
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->warning('Failed to check URL rewrites: ' . $e->getMessage());
        }

        return $issues;
    }
}