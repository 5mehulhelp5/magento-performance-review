<?php

namespace Performance\Review\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;

class DatabaseAnalyzer
{
    private ResourceConnection $resourceConnection;
    private ProductCollectionFactory $productCollectionFactory;
    private CategoryCollectionFactory $categoryCollectionFactory;
    private AdapterInterface $connection;

    public function __construct(
        ResourceConnection $resourceConnection,
        ProductCollectionFactory $productCollectionFactory,
        CategoryCollectionFactory $categoryCollectionFactory
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->connection = $resourceConnection->getConnection();
    }

    public function analyzeDatabase(): array
    {
        $issues = [];

        // Check database size
        $dbSizeIssues = $this->checkDatabaseSize();
        if (!empty($dbSizeIssues)) {
            $issues = array_merge($issues, $dbSizeIssues);
        }

        // Check table sizes
        $tableSizeIssues = $this->checkTableSizes();
        if (!empty($tableSizeIssues)) {
            $issues = array_merge($issues, $tableSizeIssues);
        }

        // Check product/category counts
        $catalogIssues = $this->checkCatalogSize();
        if (!empty($catalogIssues)) {
            $issues = array_merge($issues, $catalogIssues);
        }

        // Check flat tables
        $flatTableIssues = $this->checkFlatTables();
        if (!empty($flatTableIssues)) {
            $issues = array_merge($issues, $flatTableIssues);
        }

        // Check log tables
        $logTableIssues = $this->checkLogTables();
        if (!empty($logTableIssues)) {
            $issues = array_merge($issues, $logTableIssues);
        }

        // Check URL rewrites
        $urlRewriteIssues = $this->checkUrlRewrites();
        if (!empty($urlRewriteIssues)) {
            $issues = array_merge($issues, $urlRewriteIssues);
        }

        return $issues;
    }

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
            
            if ($sizeGb > 50) {
                $issues[] = [
                    'priority' => 'High',
                    'category' => 'Database',
                    'issue' => 'Database size is very large',
                    'details' => sprintf(
                        'Database size is %.2f GB. Large databases can impact backup/restore times and overall performance.',
                        $sizeGb
                    ),
                    'current_value' => sprintf('%.2f GB', $sizeGb),
                    'recommended_value' => 'Regular cleanup and archiving of old data'
                ];
            } elseif ($sizeGb > 20) {
                $issues[] = [
                    'priority' => 'Medium',
                    'category' => 'Database',
                    'issue' => 'Database size is growing large',
                    'details' => sprintf(
                        'Database size is %.2f GB. Consider implementing data archiving strategies.',
                        $sizeGb
                    ),
                    'current_value' => sprintf('%.2f GB', $sizeGb),
                    'recommended_value' => 'Monitor growth and plan for archiving'
                ];
            }
        } catch (\Exception $e) {
            // Skip if we can't determine database size
        }

        return $issues;
    }

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
                AND (data_length + index_length) > 1073741824
                ORDER BY (data_length + index_length) DESC
                LIMIT 10";
            
            $tables = $this->connection->fetchAll($query, ['dbname' => $dbName]);
            
            foreach ($tables as $table) {
                $largeTables[] = sprintf('%s (%.2f GB)', $table['table_name'], $table['size_mb'] / 1024);
            }
            
            if (!empty($largeTables)) {
                $issues[] = [
                    'priority' => 'High',
                    'category' => 'Database',
                    'issue' => 'Large database tables detected',
                    'details' => 'The following tables are larger than 1GB: ' . implode(', ', array_slice($largeTables, 0, 5)),
                    'current_value' => count($largeTables) . ' tables over 1GB',
                    'recommended_value' => 'Review and clean up large tables, especially logs and temporary data'
                ];
            }
        } catch (\Exception $e) {
            // Skip if we can't check table sizes
        }

        return $issues;
    }

    private function checkCatalogSize(): array
    {
        $issues = [];
        
        try {
            // Check product count
            $productCount = $this->productCollectionFactory->create()->getSize();
            
            if ($productCount > 500000) {
                $issues[] = [
                    'priority' => 'High',
                    'category' => 'Database',
                    'issue' => 'Very large product catalog',
                    'details' => sprintf(
                        'You have %s products. This can significantly impact indexing and search performance.',
                        number_format($productCount)
                    ),
                    'current_value' => number_format($productCount) . ' products',
                    'recommended_value' => 'Use Elasticsearch, optimize indexers, consider catalog segmentation'
                ];
            } elseif ($productCount > 100000) {
                $issues[] = [
                    'priority' => 'Medium',
                    'category' => 'Database',
                    'issue' => 'Large product catalog',
                    'details' => sprintf(
                        'You have %s products. Ensure proper indexing and search configuration.',
                        number_format($productCount)
                    ),
                    'current_value' => number_format($productCount) . ' products',
                    'recommended_value' => 'Monitor indexing performance, use partial indexing'
                ];
            }

            // Check category count
            $categoryCount = $this->categoryCollectionFactory->create()->getSize();
            
            if ($categoryCount > 10000) {
                $issues[] = [
                    'priority' => 'Medium',
                    'category' => 'Database',
                    'issue' => 'Large number of categories',
                    'details' => sprintf(
                        'You have %s categories. This can impact category tree rendering and navigation.',
                        number_format($categoryCount)
                    ),
                    'current_value' => number_format($categoryCount) . ' categories',
                    'recommended_value' => 'Review category structure, implement caching strategies'
                ];
            }
        } catch (\Exception $e) {
            // Skip if we can't check catalog size
        }

        return $issues;
    }

    private function checkFlatTables(): array
    {
        $issues = [];
        
        try {
            // Check if flat tables are enabled
            $flatProductEnabled = $this->connection->fetchOne(
                "SELECT value FROM core_config_data WHERE path = 'catalog/frontend/flat_catalog_product'"
            );
            
            $flatCategoryEnabled = $this->connection->fetchOne(
                "SELECT value FROM core_config_data WHERE path = 'catalog/frontend/flat_catalog_category'"
            );
            
            // For large catalogs, flat tables might not be optimal
            $productCount = $this->productCollectionFactory->create()->getSize();
            
            if ($productCount > 50000 && $flatProductEnabled == '1') {
                $issues[] = [
                    'priority' => 'Medium',
                    'category' => 'Database',
                    'issue' => 'Flat catalog enabled for large catalog',
                    'details' => 'Flat catalog tables can become very large and slow with many products. Consider disabling for better performance.',
                    'current_value' => 'Flat catalog enabled',
                    'recommended_value' => 'Disable flat catalog for large catalogs'
                ];
            }
        } catch (\Exception $e) {
            // Skip if we can't check flat tables
        }

        return $issues;
    }

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
            foreach ($logTables as $table => $description) {
                $count = $this->connection->fetchOne("SELECT COUNT(*) FROM {$table}");
                
                if ($count > 1000000) {
                    $issues[] = [
                        'priority' => 'High',
                        'category' => 'Database',
                        'issue' => sprintf('Large log table: %s', $table),
                        'details' => sprintf(
                            '%s table has %s rows. Large log tables impact performance.',
                            $description,
                            number_format($count)
                        ),
                        'current_value' => number_format($count) . ' rows',
                        'recommended_value' => 'Configure log cleaning in Admin > System > Configuration > Advanced > System'
                    ];
                }
            }
        } catch (\Exception $e) {
            // Skip tables that don't exist
        }

        return $issues;
    }

    private function checkUrlRewrites(): array
    {
        $issues = [];
        
        try {
            $count = $this->connection->fetchOne("SELECT COUNT(*) FROM url_rewrite");
            
            if ($count > 1000000) {
                $issues[] = [
                    'priority' => 'High',
                    'category' => 'Database',
                    'issue' => 'Excessive URL rewrites',
                    'details' => sprintf(
                        'URL rewrite table has %s rows. This significantly impacts routing performance.',
                        number_format($count)
                    ),
                    'current_value' => number_format($count) . ' URL rewrites',
                    'recommended_value' => 'Clean up duplicate rewrites, disable automatic generation if not needed'
                ];
            } elseif ($count > 500000) {
                $issues[] = [
                    'priority' => 'Medium',
                    'category' => 'Database',
                    'issue' => 'Large number of URL rewrites',
                    'details' => sprintf(
                        'URL rewrite table has %s rows. Monitor performance impact.',
                        number_format($count)
                    ),
                    'current_value' => number_format($count) . ' URL rewrites',
                    'recommended_value' => 'Regularly clean up old URL rewrites'
                ];
            }
        } catch (\Exception $e) {
            // Skip if table doesn't exist
        }

        return $issues;
    }
}