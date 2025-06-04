<?php
declare(strict_types=1);

namespace Performance\Review\Phar\Analyzer;

use Performance\Review\Phar\AnalyzerInterface;
use Performance\Review\Phar\Issue;
use Performance\Review\Phar\IssueInterface;
use Performance\Review\Phar\Util\MagentoHelper;

class IndexerCronAnalyzer implements AnalyzerInterface
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
                    'Unable to analyze indexers and cron',
                    'Database connection required for analysis'
                );
                return $issues;
            }
            
            // Check indexer status
            $indexerIssues = $this->checkIndexerStatus($pdo);
            $issues = array_merge($issues, $indexerIssues);
            
            // Check cron status
            $cronIssues = $this->checkCronStatus($pdo);
            $issues = array_merge($issues, $cronIssues);
            
        } catch (\Exception $e) {
            $issues[] = new Issue(
                IssueInterface::PRIORITY_HIGH,
                'Indexer/Cron analysis failed',
                "Error: " . $e->getMessage()
            );
        }
        
        return $issues;
    }
    
    private function checkIndexerStatus(\PDO $pdo): array
    {
        $issues = [];
        
        try {
            // Check indexer_state table
            $stmt = $pdo->query("
                SELECT indexer_id, status, updated
                FROM indexer_state
            ");
            
            $invalidIndexers = [];
            $realtimeIndexers = [];
            $outdatedIndexers = [];
            
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                if ($row['status'] === 'invalid') {
                    $invalidIndexers[] = $row['indexer_id'];
                }
                
                // Check if indexer is outdated (not updated in last 24 hours)
                $lastUpdate = strtotime($row['updated']);
                if ($lastUpdate && (time() - $lastUpdate) > 86400) {
                    $outdatedIndexers[] = $row['indexer_id'];
                }
            }
            
            // Check for invalid indexers
            if (!empty($invalidIndexers)) {
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_HIGH,
                    'Invalid indexers detected',
                    "The following indexers need reindexing: " . implode(', ', $invalidIndexers) . "\n" .
                    "Run: php bin/magento indexer:reindex"
                );
            }
            
            // Check for outdated indexers
            if (!empty($outdatedIndexers)) {
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_MEDIUM,
                    'Outdated indexers',
                    "The following indexers haven't been updated in 24 hours: " . implode(', ', $outdatedIndexers) . "\n" .
                    "Check if indexer cron is running properly"
                );
            }
            
            // Check mview_state for real-time indexers
            $stmt = $pdo->query("
                SELECT view_id, mode
                FROM mview_state
                WHERE mode = 'enabled'
            ");
            
            $realtimeCount = $stmt->rowCount();
            if ($realtimeCount > 0) {
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_HIGH,
                    'Real-time indexers detected',
                    "Found $realtimeCount indexers in real-time mode.\n" .
                    "Real-time indexing impacts performance.\n" .
                    "Recommended: Switch to 'Update by Schedule' mode"
                );
            }
            
        } catch (\Exception $e) {
            // Table might not exist in all versions
        }
        
        return $issues;
    }
    
    private function checkCronStatus(\PDO $pdo): array
    {
        $issues = [];
        
        try {
            // Check cron_schedule table
            $stmt = $pdo->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'running' THEN 1 ELSE 0 END) as running,
                    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success,
                    SUM(CASE WHEN status = 'missed' THEN 1 ELSE 0 END) as missed,
                    SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as error
                FROM cron_schedule
                WHERE scheduled_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            
            $stats = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            // Check if cron is running
            if ($stats['total'] == 0) {
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_HIGH,
                    'Cron not running',
                    "No cron jobs found in the last 24 hours.\n" .
                    "Cron is essential for Magento operation.\n" .
                    "Set up cron jobs as per Magento documentation"
                );
            } else {
                // Check for high error rate
                $errorRate = ($stats['error'] / $stats['total']) * 100;
                if ($errorRate > 10) {
                    $issues[] = new Issue(
                        IssueInterface::PRIORITY_HIGH,
                        'High cron error rate',
                        sprintf(
                            "%.1f%% of cron jobs are failing.\n" .
                            "Errors: %d, Total: %d\n" .
                            "Check var/log/cron.log for details",
                            $errorRate,
                            $stats['error'],
                            $stats['total']
                        )
                    );
                }
                
                // Check for missed jobs
                if ($stats['missed'] > 100) {
                    $issues[] = new Issue(
                        IssueInterface::PRIORITY_MEDIUM,
                        'Many missed cron jobs',
                        "Found {$stats['missed']} missed cron jobs.\n" .
                        "This indicates cron congestion or performance issues"
                    );
                }
                
                // Check for stuck jobs
                $stmt = $pdo->query("
                    SELECT job_code, executed_at
                    FROM cron_schedule
                    WHERE status = 'running'
                        AND executed_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)
                ");
                
                $stuckJobs = [];
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $stuckJobs[] = $row['job_code'];
                }
                
                if (!empty($stuckJobs)) {
                    $issues[] = new Issue(
                        IssueInterface::PRIORITY_HIGH,
                        'Stuck cron jobs detected',
                        "The following jobs have been running for over 2 hours: " . 
                        implode(', ', array_slice($stuckJobs, 0, 5)) . 
                        (count($stuckJobs) > 5 ? ' and ' . (count($stuckJobs) - 5) . ' more' : '') . "\n" .
                        "These may need to be manually cleared"
                    );
                }
                
                // Check for too many pending jobs
                if ($stats['pending'] > 1000) {
                    $issues[] = new Issue(
                        IssueInterface::PRIORITY_MEDIUM,
                        'Large cron queue',
                        "Found {$stats['pending']} pending cron jobs.\n" .
                        "Large queues can indicate performance issues"
                    );
                }
            }
            
            // Check cron groups configuration
            $stmt = $pdo->query("
                SELECT DISTINCT job_code
                FROM cron_schedule
                WHERE job_code LIKE 'indexer_%'
                    AND scheduled_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            
            if ($stmt->rowCount() === 0) {
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_MEDIUM,
                    'Indexer cron jobs not scheduled',
                    "No indexer cron jobs found in recent schedule.\n" .
                    "Ensure index cron group is configured properly"
                );
            }
            
        } catch (\Exception $e) {
            // Table might not exist
        }
        
        return $issues;
    }
}