<?php

namespace Performance\Review\Model;

use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Indexer\Model\Indexer\CollectionFactory as IndexerCollectionFactory;
use Magento\Cron\Model\ResourceModel\Schedule\CollectionFactory as ScheduleCollectionFactory;
use Magento\Framework\App\ResourceConnection;

class IndexerCronAnalyzer
{
    private IndexerRegistry $indexerRegistry;
    private IndexerCollectionFactory $indexerCollectionFactory;
    private ScheduleCollectionFactory $scheduleCollectionFactory;
    private ResourceConnection $resourceConnection;

    public function __construct(
        IndexerRegistry $indexerRegistry,
        IndexerCollectionFactory $indexerCollectionFactory,
        ScheduleCollectionFactory $scheduleCollectionFactory,
        ResourceConnection $resourceConnection
    ) {
        $this->indexerRegistry = $indexerRegistry;
        $this->indexerCollectionFactory = $indexerCollectionFactory;
        $this->scheduleCollectionFactory = $scheduleCollectionFactory;
        $this->resourceConnection = $resourceConnection;
    }

    public function analyzeIndexerCron(): array
    {
        $issues = [];

        // Check indexer status
        $indexerIssues = $this->checkIndexerStatus();
        if (!empty($indexerIssues)) {
            $issues = array_merge($issues, $indexerIssues);
        }

        // Check indexer mode
        $modeIssues = $this->checkIndexerMode();
        if (!empty($modeIssues)) {
            $issues = array_merge($issues, $modeIssues);
        }

        // Check cron status
        $cronIssues = $this->checkCronStatus();
        if (!empty($cronIssues)) {
            $issues = array_merge($issues, $cronIssues);
        }

        // Check stuck cron jobs
        $stuckCronIssues = $this->checkStuckCronJobs();
        if (!empty($stuckCronIssues)) {
            $issues = array_merge($issues, $stuckCronIssues);
        }

        // Check cron schedule
        $scheduleIssues = $this->checkCronSchedule();
        if (!empty($scheduleIssues)) {
            $issues = array_merge($issues, $scheduleIssues);
        }

        return $issues;
    }

    private function checkIndexerStatus(): array
    {
        $issues = [];
        $invalidIndexers = [];
        $workingIndexers = [];
        
        try {
            $indexerCollection = $this->indexerCollectionFactory->create();
            
            foreach ($indexerCollection as $indexer) {
                if ($indexer->getStatus() == 'invalid') {
                    $invalidIndexers[] = $indexer->getTitle();
                } elseif ($indexer->getStatus() == 'working') {
                    $workingIndexers[] = $indexer->getTitle();
                }
            }
            
            if (!empty($invalidIndexers)) {
                $issues[] = [
                    'priority' => 'High',
                    'category' => 'Indexing',
                    'issue' => 'Invalid indexers detected',
                    'details' => 'The following indexers are invalid and need reindexing: ' . implode(', ', $invalidIndexers),
                    'current_value' => count($invalidIndexers) . ' invalid indexers',
                    'recommended_value' => 'Run: php bin/magento indexer:reindex'
                ];
            }
            
            if (!empty($workingIndexers)) {
                $issues[] = [
                    'priority' => 'Medium',
                    'category' => 'Indexing',
                    'issue' => 'Indexers currently processing',
                    'details' => 'The following indexers are currently processing: ' . implode(', ', $workingIndexers) . '. This may indicate stuck processes.',
                    'current_value' => count($workingIndexers) . ' working indexers',
                    'recommended_value' => 'Monitor indexer progress, check for stuck processes'
                ];
            }
        } catch (\Exception $e) {
            // Skip if we can't check indexers
        }

        return $issues;
    }

    private function checkIndexerMode(): array
    {
        $issues = [];
        $realtimeIndexers = [];
        
        try {
            $indexerCollection = $this->indexerCollectionFactory->create();
            
            foreach ($indexerCollection as $indexer) {
                if (!$indexer->isScheduled()) {
                    $realtimeIndexers[] = $indexer->getTitle();
                }
            }
            
            if (!empty($realtimeIndexers)) {
                $issues[] = [
                    'priority' => 'High',
                    'category' => 'Indexing',
                    'issue' => 'Indexers set to "Update on Save" mode',
                    'details' => 'The following indexers are in realtime mode which impacts admin performance: ' . implode(', ', $realtimeIndexers),
                    'current_value' => count($realtimeIndexers) . ' realtime indexers',
                    'recommended_value' => 'Set all indexers to "Update by Schedule" mode'
                ];
            }
        } catch (\Exception $e) {
            // Skip if we can't check indexer modes
        }

        return $issues;
    }

    private function checkCronStatus(): array
    {
        $issues = [];
        
        try {
            $connection = $this->resourceConnection->getConnection();
            
            // Check last cron run
            $lastRun = $connection->fetchOne(
                "SELECT MAX(executed_at) FROM cron_schedule WHERE status = 'success'"
            );
            
            if ($lastRun) {
                $lastRunTime = strtotime($lastRun);
                $currentTime = time();
                $timeDiff = $currentTime - $lastRunTime;
                
                if ($timeDiff > 3600) { // More than 1 hour
                    $issues[] = [
                        'priority' => 'High',
                        'category' => 'Cron',
                        'issue' => 'Cron not running regularly',
                        'details' => sprintf(
                            'Last successful cron run was %s ago. Cron should run every minute.',
                            $this->formatTimeDiff($timeDiff)
                        ),
                        'current_value' => 'Last run: ' . $lastRun,
                        'recommended_value' => 'Ensure cron is configured and running every minute'
                    ];
                } elseif ($timeDiff > 300) { // More than 5 minutes
                    $issues[] = [
                        'priority' => 'Medium',
                        'category' => 'Cron',
                        'issue' => 'Cron running infrequently',
                        'details' => sprintf(
                            'Last successful cron run was %d minutes ago.',
                            round($timeDiff / 60)
                        ),
                        'current_value' => 'Running every ' . round($timeDiff / 60) . ' minutes',
                        'recommended_value' => 'Configure cron to run every minute'
                    ];
                }
            } else {
                $issues[] = [
                    'priority' => 'High',
                    'category' => 'Cron',
                    'issue' => 'No successful cron runs found',
                    'details' => 'Cron has never run successfully. This prevents background tasks from executing.',
                    'current_value' => 'Cron not configured',
                    'recommended_value' => 'Configure system cron: * * * * * php bin/magento cron:run'
                ];
            }
        } catch (\Exception $e) {
            // Skip if we can't check cron status
        }

        return $issues;
    }

    private function checkStuckCronJobs(): array
    {
        $issues = [];
        
        try {
            $connection = $this->resourceConnection->getConnection();
            
            // Check for stuck running jobs (running for more than 2 hours)
            $stuckJobs = $connection->fetchOne(
                "SELECT COUNT(*) FROM cron_schedule 
                WHERE status = 'running' 
                AND executed_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)"
            );
            
            if ($stuckJobs > 0) {
                $issues[] = [
                    'priority' => 'High',
                    'category' => 'Cron',
                    'issue' => 'Stuck cron jobs detected',
                    'details' => sprintf(
                        'Found %d cron jobs that have been running for more than 2 hours. These may be stuck.',
                        $stuckJobs
                    ),
                    'current_value' => $stuckJobs . ' stuck jobs',
                    'recommended_value' => 'Investigate and clean up stuck cron jobs'
                ];
            }
            
            // Check for excessive pending jobs
            $pendingJobs = $connection->fetchOne(
                "SELECT COUNT(*) FROM cron_schedule WHERE status = 'pending'"
            );
            
            if ($pendingJobs > 1000) {
                $issues[] = [
                    'priority' => 'Medium',
                    'category' => 'Cron',
                    'issue' => 'Excessive pending cron jobs',
                    'details' => sprintf(
                        'Found %d pending cron jobs. This indicates cron is not processing jobs fast enough.',
                        $pendingJobs
                    ),
                    'current_value' => $pendingJobs . ' pending jobs',
                    'recommended_value' => 'Increase cron frequency or investigate performance issues'
                ];
            }
        } catch (\Exception $e) {
            // Skip if we can't check stuck jobs
        }

        return $issues;
    }

    private function checkCronSchedule(): array
    {
        $issues = [];
        
        try {
            $scheduleCollection = $this->scheduleCollectionFactory->create();
            $scheduleCollection->addFieldToFilter('status', 'error');
            $scheduleCollection->addFieldToFilter(
                'created_at',
                ['gteq' => date('Y-m-d H:i:s', strtotime('-24 hours'))]
            );
            $scheduleCollection->setPageSize(100);
            
            $errorCount = $scheduleCollection->getSize();
            
            if ($errorCount > 50) {
                $issues[] = [
                    'priority' => 'High',
                    'category' => 'Cron',
                    'issue' => 'High cron error rate',
                    'details' => sprintf(
                        'Found %d cron errors in the last 24 hours. This indicates problems with scheduled tasks.',
                        $errorCount
                    ),
                    'current_value' => $errorCount . ' errors in 24 hours',
                    'recommended_value' => 'Check var/log/cron.log for error details'
                ];
            } elseif ($errorCount > 10) {
                $issues[] = [
                    'priority' => 'Medium',
                    'category' => 'Cron',
                    'issue' => 'Cron errors detected',
                    'details' => sprintf(
                        'Found %d cron errors in the last 24 hours.',
                        $errorCount
                    ),
                    'current_value' => $errorCount . ' errors in 24 hours',
                    'recommended_value' => 'Review cron error logs'
                ];
            }
        } catch (\Exception $e) {
            // Skip if we can't check cron schedule
        }

        return $issues;
    }

    private function formatTimeDiff(int $seconds): string
    {
        if ($seconds < 3600) {
            return round($seconds / 60) . ' minutes';
        } elseif ($seconds < 86400) {
            return round($seconds / 3600) . ' hours';
        } else {
            return round($seconds / 86400) . ' days';
        }
    }
}