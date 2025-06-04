<?php
declare(strict_types=1);

namespace Performance\Review\Phar\Analyzer;

use Performance\Review\Phar\AnalyzerInterface;
use Performance\Review\Phar\Issue;
use Performance\Review\Phar\IssueInterface;
use Performance\Review\Phar\Util\MagentoHelper;
use Performance\Review\Phar\Util\ByteConverter;

class DatabaseAnalyzer implements AnalyzerInterface
{
    public function analyze(string $magentoRoot): array
    {
        $issues = [];
        
        try {
            $env = MagentoHelper::getEnvConfig($magentoRoot);
            $pdo = MagentoHelper::getDatabaseConnection($env);
            
            if (!$pdo) {
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_HIGH,
                    'Unable to connect to database',
                    'Database connection failed. Check database configuration.'
                );
                return $issues;
            }
            
            // Check database size
            $dbName = MagentoHelper::getConfigValue($env, 'db/connection/default/dbname');
            $stmt = $pdo->query("
                SELECT 
                    SUM(data_length + index_length) as size 
                FROM information_schema.TABLES 
                WHERE table_schema = '$dbName'
            ");
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $dbSize = (int) $result['size'];
            
            if ($dbSize > 50 * 1024 * 1024 * 1024) { // 50GB
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_HIGH,
                    'Database size exceeds 50GB',
                    sprintf(
                        "Very large database can impact performance and backup times.\n" .
                        "Current: %s\nConsider: Archiving old data, cleaning logs",
                        ByteConverter::formatBytes($dbSize)
                    )
                );
            } elseif ($dbSize > 20 * 1024 * 1024 * 1024) { // 20GB
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_MEDIUM,
                    'Database size exceeds 20GB',
                    sprintf(
                        "Large database may impact performance.\n" .
                        "Current: %s\nConsider: Regular maintenance and cleanup",
                        ByteConverter::formatBytes($dbSize)
                    )
                );
            }
            
            // Check large tables
            $stmt = $pdo->query("
                SELECT 
                    table_name,
                    (data_length + index_length) as size
                FROM information_schema.TABLES
                WHERE table_schema = '$dbName'
                    AND (data_length + index_length) > 1073741824
                ORDER BY (data_length + index_length) DESC
            ");
            
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $tableName = $row['table_name'];
                $tableSize = (int) $row['size'];
                
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_MEDIUM,
                    "Large table: $tableName",
                    sprintf(
                        "Table size: %s\nLarge tables can impact query performance.",
                        ByteConverter::formatBytes($tableSize)
                    )
                );
            }
            
            // Check log tables
            $logTables = [
                'report_event' => 'Report events log',
                'customer_log' => 'Customer activity log',
                'customer_visitor' => 'Customer visitor log',
                'report_viewed_product_index' => 'Viewed products report',
                'report_compared_product_index' => 'Compared products report',
                'catalog_compare_item' => 'Compare items'
            ];
            
            foreach ($logTables as $table => $description) {
                $stmt = $pdo->query("
                    SELECT 
                        COUNT(*) as count,
                        (data_length + index_length) as size
                    FROM information_schema.TABLES
                    WHERE table_schema = '$dbName'
                        AND table_name = '$table'
                ");
                $result = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                if ($result && $result['count'] > 0) {
                    $tableSize = (int) $result['size'];
                    if ($tableSize > 100 * 1024 * 1024) { // 100MB
                        $issues[] = new Issue(
                            IssueInterface::PRIORITY_MEDIUM,
                            "Clean up $description table",
                            sprintf(
                                "Table '$table' is large and contains log data.\n" .
                                "Size: %s\nRecommended: Clean old log entries regularly",
                                ByteConverter::formatBytes($tableSize)
                            )
                        );
                    }
                }
            }
            
            // Check catalog size
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM catalog_product_entity");
            $productCount = (int) $stmt->fetch(\PDO::FETCH_ASSOC)['count'];
            
            if ($productCount > 100000) {
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_HIGH,
                    'Very large product catalog',
                    "Product count: " . number_format($productCount) . "\n" .
                    "Consider: Elasticsearch for search, careful indexing strategy"
                );
            } elseif ($productCount > 50000) {
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_MEDIUM,
                    'Large product catalog',
                    "Product count: " . number_format($productCount) . "\n" .
                    "Monitor indexing performance and search functionality"
                );
            }
            
            // Check URL rewrites
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM url_rewrite");
            $urlRewriteCount = (int) $stmt->fetch(\PDO::FETCH_ASSOC)['count'];
            
            if ($urlRewriteCount > 500000) {
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_HIGH,
                    'Excessive URL rewrites',
                    "URL rewrite count: " . number_format($urlRewriteCount) . "\n" .
                    "This can severely impact performance. Clean up unnecessary rewrites."
                );
            }
            
        } catch (\Exception $e) {
            $issues[] = new Issue(
                IssueInterface::PRIORITY_HIGH,
                'Database analysis failed',
                "Error: " . $e->getMessage()
            );
        }
        
        return $issues;
    }
}