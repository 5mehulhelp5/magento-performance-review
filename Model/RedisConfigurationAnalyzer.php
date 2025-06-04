<?php
/**
 * Copyright © Performance, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Performance\Review\Model;

use Magento\Framework\App\DeploymentConfig;
use Performance\Review\Api\RedisConfigurationAnalyzerInterface;
use Performance\Review\Api\Data\IssueInterface;
use Performance\Review\Model\IssueFactory;
use Psr\Log\LoggerInterface;

/**
 * Redis configuration analyzer for performance review
 *
 * @since 1.0.0
 */
class RedisConfigurationAnalyzer implements RedisConfigurationAnalyzerInterface
{
    /**
     * @var DeploymentConfig
     */
    private DeploymentConfig $deploymentConfig;

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
     * @param DeploymentConfig $deploymentConfig
     * @param IssueFactory $issueFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        DeploymentConfig $deploymentConfig,
        IssueFactory $issueFactory,
        LoggerInterface $logger
    ) {
        $this->deploymentConfig = $deploymentConfig;
        $this->issueFactory = $issueFactory;
        $this->logger = $logger;
    }

    /**
     * Analyze Redis configuration for performance issues
     *
     * @return IssueInterface[]
     */
    public function analyzeRedisConfiguration(): array
    {
        $issues = [];

        try {
            // Check if Redis is configured
            $redisUsage = $this->checkRedisUsage();
            if (!empty($redisUsage['issues'])) {
                $issues = array_merge($issues, $redisUsage['issues']);
            }

            // If Redis is configured, check its settings
            if ($redisUsage['configured']) {
                // Check cache backend configuration
                $cacheIssues = $this->checkCacheConfiguration();
                if (!empty($cacheIssues)) {
                    $issues = array_merge($issues, $cacheIssues);
                }

                // Check session configuration
                $sessionIssues = $this->checkSessionConfiguration();
                if (!empty($sessionIssues)) {
                    $issues = array_merge($issues, $sessionIssues);
                }

                // Check full page cache configuration
                $fpcIssues = $this->checkFullPageCacheConfiguration();
                if (!empty($fpcIssues)) {
                    $issues = array_merge($issues, $fpcIssues);
                }

                // Check for separate Redis instances
                $separationIssues = $this->checkRedisInstanceSeparation();
                if (!empty($separationIssues)) {
                    $issues = array_merge($issues, $separationIssues);
                }

                // Check Redis server configuration
                $serverIssues = $this->checkRedisServerConfiguration();
                if (!empty($serverIssues)) {
                    $issues = array_merge($issues, $serverIssues);
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Redis configuration analysis failed: ' . $e->getMessage());
        }

        return $issues;
    }

    /**
     * Check if Redis is being used
     *
     * @return array
     */
    private function checkRedisUsage(): array
    {
        $issues = [];
        $configured = false;

        // Check cache configuration
        $cacheConfig = $this->deploymentConfig->get('cache');
        $hasRedisCache = false;
        
        if (isset($cacheConfig['frontend'])) {
            foreach ($cacheConfig['frontend'] as $cache) {
                if (isset($cache['backend']) && strpos($cache['backend'], 'Redis') !== false) {
                    $hasRedisCache = true;
                    $configured = true;
                    break;
                }
            }
        }

        // Check session configuration
        $sessionConfig = $this->deploymentConfig->get('session');
        $hasRedisSession = isset($sessionConfig['save']) && $sessionConfig['save'] === 'redis';
        if ($hasRedisSession) {
            $configured = true;
        }

        // Check full page cache
        $fpcConfig = $this->deploymentConfig->get('cache/frontend/page_cache');
        $hasRedisFpc = isset($fpcConfig['backend']) && strpos($fpcConfig['backend'], 'Redis') !== false;
        if ($hasRedisFpc) {
            $configured = true;
        }

        // If no Redis is configured at all, recommend it
        if (!$configured) {
            $issues[] = $this->issueFactory->create([
                'priority' => IssueInterface::PRIORITY_HIGH,
                'category' => 'Redis Config',
                'issue' => 'Configure Redis for caching and sessions',
                'details' => 'Redis provides significant performance improvements over file-based storage',
                'current_value' => 'Not configured',
                'recommended_value' => 'Redis for cache, sessions, and FPC'
            ]);
        }

        return ['configured' => $configured, 'issues' => $issues];
    }

    /**
     * Check cache backend configuration
     *
     * @return IssueInterface[]
     */
    private function checkCacheConfiguration(): array
    {
        $issues = [];
        $cacheConfig = $this->deploymentConfig->get('cache/frontend/default');

        if (isset($cacheConfig['backend_options'])) {
            $options = $cacheConfig['backend_options'];

            // Check compression
            if (!isset($options['compress_data']) || $options['compress_data'] != '1') {
                $issues[] = $this->issueFactory->create([
                    'priority' => IssueInterface::PRIORITY_MEDIUM,
                    'category' => 'Redis Config',
                    'issue' => 'Enable Redis compression for cache',
                    'details' => 'Compression reduces memory usage with minimal CPU overhead',
                    'current_value' => 'Disabled',
                    'recommended_value' => 'compress_data = 1'
                ]);
            }

            // Check compression threshold
            if (isset($options['compress_data']) && $options['compress_data'] == '1') {
                $threshold = $options['compress_threshold'] ?? 2048;
                if ($threshold < 2048) {
                    $issues[] = $this->issueFactory->create([
                        'priority' => IssueInterface::PRIORITY_LOW,
                        'category' => 'Redis Config',
                        'issue' => 'Optimize Redis compression threshold',
                        'details' => 'Small values increase CPU usage',
                        'current_value' => $threshold,
                        'recommended_value' => '2048 or higher'
                    ]);
                }
            }
        }

        return $issues;
    }

    /**
     * Check session configuration
     *
     * @return IssueInterface[]
     */
    private function checkSessionConfiguration(): array
    {
        $issues = [];
        $sessionConfig = $this->deploymentConfig->get('session');

        if (isset($sessionConfig['save']) && $sessionConfig['save'] === 'redis') {
            // Check for disable_locking
            if (!isset($sessionConfig['redis']['disable_locking']) || $sessionConfig['redis']['disable_locking'] != '1') {
                $issues[] = $this->issueFactory->create([
                    'priority' => IssueInterface::PRIORITY_HIGH,
                    'category' => 'Redis Config',
                    'issue' => 'Disable Redis session locking',
                    'details' => 'Session locking can cause performance issues under high load',
                    'current_value' => 'Enabled',
                    'recommended_value' => 'disable_locking = 1'
                ]);
            }

            // Check max_concurrency
            $maxConcurrency = $sessionConfig['redis']['max_concurrency'] ?? 6;
            if ($maxConcurrency < 20) {
                $issues[] = $this->issueFactory->create([
                    'priority' => IssueInterface::PRIORITY_MEDIUM,
                    'category' => 'Redis Config',
                    'issue' => 'Increase Redis session max_concurrency',
                    'details' => 'Higher value allows more concurrent requests',
                    'current_value' => $maxConcurrency,
                    'recommended_value' => '20 or higher'
                ]);
            }

            // Check break_after_frontend
            $breakAfter = $sessionConfig['redis']['break_after_frontend'] ?? 5;
            if ($breakAfter < 5) {
                $issues[] = $this->issueFactory->create([
                    'priority' => IssueInterface::PRIORITY_LOW,
                    'category' => 'Redis Config',
                    'issue' => 'Optimize Redis session break_after_frontend',
                    'details' => 'Controls session lock wait time',
                    'current_value' => $breakAfter,
                    'recommended_value' => '5'
                ]);
            }

            // Check compression
            if (!isset($sessionConfig['redis']['compress_data']) || $sessionConfig['redis']['compress_data'] != '1') {
                $issues[] = $this->issueFactory->create([
                    'priority' => IssueInterface::PRIORITY_MEDIUM,
                    'category' => 'Redis Config',
                    'issue' => 'Enable Redis compression for sessions',
                    'details' => 'Reduces memory usage for session data',
                    'current_value' => 'Disabled',
                    'recommended_value' => 'compress_data = 1'
                ]);
            }
        } else if (!isset($sessionConfig['save']) || $sessionConfig['save'] === 'files') {
            $issues[] = $this->issueFactory->create([
                'priority' => IssueInterface::PRIORITY_HIGH,
                'category' => 'Redis Config',
                'issue' => 'Use Redis for session storage',
                'details' => 'File-based sessions are slow and don\'t scale well',
                'current_value' => $sessionConfig['save'] ?? 'files',
                'recommended_value' => 'redis'
            ]);
        }

        return $issues;
    }

    /**
     * Check full page cache configuration
     *
     * @return IssueInterface[]
     */
    private function checkFullPageCacheConfiguration(): array
    {
        $issues = [];
        $fpcConfig = $this->deploymentConfig->get('cache/frontend/page_cache');

        if (!isset($fpcConfig['backend']) || strpos($fpcConfig['backend'], 'Redis') === false) {
            $issues[] = $this->issueFactory->create([
                'priority' => IssueInterface::PRIORITY_HIGH,
                'category' => 'Redis Config',
                'issue' => 'Use Redis for full page cache',
                'details' => 'Redis provides better performance than file-based FPC',
                'current_value' => $fpcConfig['backend'] ?? 'File',
                'recommended_value' => 'Cm_Cache_Backend_Redis'
            ]);
        }

        return $issues;
    }

    /**
     * Check if separate Redis instances are used
     *
     * @return IssueInterface[]
     */
    private function checkRedisInstanceSeparation(): array
    {
        $issues = [];
        $databases = [];

        // Check cache database
        $cacheConfig = $this->deploymentConfig->get('cache/frontend/default/backend_options');
        if (isset($cacheConfig['database'])) {
            $databases['cache'] = $cacheConfig['database'];
        }

        // Check FPC database
        $fpcConfig = $this->deploymentConfig->get('cache/frontend/page_cache/backend_options');
        if (isset($fpcConfig['database'])) {
            $databases['fpc'] = $fpcConfig['database'];
        }

        // Check session database
        $sessionConfig = $this->deploymentConfig->get('session/redis');
        if (isset($sessionConfig['database'])) {
            $databases['session'] = $sessionConfig['database'];
        }

        // Check if all are using the same database
        $uniqueDatabases = array_unique(array_values($databases));
        if (count($databases) > 1 && count($uniqueDatabases) === 1) {
            $issues[] = $this->issueFactory->create([
                'priority' => IssueInterface::PRIORITY_HIGH,
                'category' => 'Redis Config',
                'issue' => 'Use separate Redis databases',
                'details' => 'Using the same Redis database for cache, sessions, and FPC can cause conflicts',
                'current_value' => 'All using database ' . $uniqueDatabases[0],
                'recommended_value' => 'Separate databases (e.g., cache:0, fpc:1, session:2)'
            ]);
        }

        // Check for separate Redis instances (different hosts/ports)
        $hosts = [];
        
        if (isset($cacheConfig['server'])) {
            $hosts['cache'] = $cacheConfig['server'] . ':' . ($cacheConfig['port'] ?? 6379);
        }
        
        if (isset($fpcConfig['server'])) {
            $hosts['fpc'] = $fpcConfig['server'] . ':' . ($fpcConfig['port'] ?? 6379);
        }
        
        if (isset($sessionConfig['host'])) {
            $hosts['session'] = $sessionConfig['host'] . ':' . ($sessionConfig['port'] ?? 6379);
        }

        $uniqueHosts = array_unique(array_values($hosts));
        if (count($hosts) === 3 && count($uniqueHosts) === 1) {
            $issue = $this->issueFactory->create([
                'priority' => IssueInterface::PRIORITY_MEDIUM,
                'category' => 'Redis Config',
                'issue' => 'Consider separate Redis instances',
                'details' => 'For high-traffic sites, separate Redis instances provide better isolation',
                'current_value' => 'Single Redis instance',
                'recommended_value' => 'Separate instances for cache, FPC, and sessions'
            ]);
            
            $issue->setData('redis_instances', $hosts);
            $issues[] = $issue;
        }

        return $issues;
    }

    /**
     * Check Redis server configuration
     *
     * @return IssueInterface[]
     */
    private function checkRedisServerConfiguration(): array
    {
        $issues = [];

        try {
            // Try to connect to Redis and check configuration
            $redisConfig = $this->getRedisConnectionConfig();
            
            if ($redisConfig) {
                $redis = new \Redis();
                if (@$redis->connect($redisConfig['host'], $redisConfig['port'])) {
                    if (isset($redisConfig['password']) && $redisConfig['password']) {
                        $redis->auth($redisConfig['password']);
                    }

                    // Get Redis configuration
                    $config = $redis->config('GET', '*');

                    // Check maxmemory-policy
                    if (isset($config['maxmemory-policy']) && 
                        !in_array($config['maxmemory-policy'], ['allkeys-lru', 'volatile-lru', 'allkeys-lfu', 'volatile-lfu'])) {
                        $issues[] = $this->issueFactory->create([
                            'priority' => IssueInterface::PRIORITY_HIGH,
                            'category' => 'Redis Config',
                            'issue' => 'Set appropriate Redis eviction policy',
                            'details' => 'LRU or LFU policies recommended for Magento',
                            'current_value' => $config['maxmemory-policy'],
                            'recommended_value' => 'allkeys-lru or volatile-lru'
                        ]);
                    }

                    // Check if maxmemory is set
                    if (!isset($config['maxmemory']) || $config['maxmemory'] == '0') {
                        $issues[] = $this->issueFactory->create([
                            'priority' => IssueInterface::PRIORITY_HIGH,
                            'category' => 'Redis Config',
                            'issue' => 'Set Redis maxmemory limit',
                            'details' => 'Without maxmemory, Redis can consume all available memory',
                            'current_value' => 'Unlimited',
                            'recommended_value' => 'Set based on available RAM (e.g., 2GB)'
                        ]);
                    }

                    // Check timeout
                    if (isset($config['timeout']) && $config['timeout'] != '0') {
                        $issues[] = $this->issueFactory->create([
                            'priority' => IssueInterface::PRIORITY_MEDIUM,
                            'category' => 'Redis Config',
                            'issue' => 'Disable Redis timeout',
                            'details' => 'Timeout can cause unexpected disconnections',
                            'current_value' => $config['timeout'],
                            'recommended_value' => '0'
                        ]);
                    }

                    // Check tcp-keepalive
                    $tcpKeepalive = $config['tcp-keepalive'] ?? '0';
                    if ($tcpKeepalive == '0') {
                        $issues[] = $this->issueFactory->create([
                            'priority' => IssueInterface::PRIORITY_LOW,
                            'category' => 'Redis Config',
                            'issue' => 'Enable Redis TCP keepalive',
                            'details' => 'Helps detect dead connections',
                            'current_value' => '0',
                            'recommended_value' => '60'
                        ]);
                    }

                    $redis->close();
                }
            }
        } catch (\Exception $e) {
            $this->logger->info('Could not connect to Redis to check server configuration: ' . $e->getMessage());
        }

        return $issues;
    }

    /**
     * Get Redis connection configuration
     *
     * @return array|null
     */
    private function getRedisConnectionConfig(): ?array
    {
        // Try cache configuration first
        $cacheConfig = $this->deploymentConfig->get('cache/frontend/default/backend_options');
        if (isset($cacheConfig['server'])) {
            return [
                'host' => $cacheConfig['server'],
                'port' => $cacheConfig['port'] ?? 6379,
                'password' => $cacheConfig['password'] ?? null
            ];
        }

        // Try session configuration
        $sessionConfig = $this->deploymentConfig->get('session/redis');
        if (isset($sessionConfig['host'])) {
            return [
                'host' => $sessionConfig['host'],
                'port' => $sessionConfig['port'] ?? 6379,
                'password' => $sessionConfig['password'] ?? null
            ];
        }

        // Try FPC configuration
        $fpcConfig = $this->deploymentConfig->get('cache/frontend/page_cache/backend_options');
        if (isset($fpcConfig['server'])) {
            return [
                'host' => $fpcConfig['server'],
                'port' => $fpcConfig['port'] ?? 6379,
                'password' => $fpcConfig['password'] ?? null
            ];
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function analyze(): array
    {
        return $this->analyzeRedisConfiguration();
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return 'Redis Configuration Analyzer';
    }

    /**
     * @inheritdoc
     */
    public function getCategory(): string
    {
        return 'Redis Config';
    }
}