<?php
/**
 * Copyright © Performance, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Performance\Review\Model;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\State;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Exception\LocalizedException;
use Performance\Review\Api\ConfigurationCheckerInterface;
use Performance\Review\Api\Data\IssueInterface;
use Performance\Review\Model\IssueFactory;
use Psr\Log\LoggerInterface;

/**
 * Configuration checker implementation
 *
 * @since 1.0.0
 */
class ConfigurationChecker implements ConfigurationCheckerInterface
{
    /**
     * Cache backend constants
     */
    private const CACHE_BACKEND_REDIS = 'Redis';
    private const CACHE_BACKEND_FILE = 'File-based cache';

    /**
     * @var DeploymentConfig
     */
    private DeploymentConfig $deploymentConfig;

    /**
     * @var State
     */
    private State $appState;

    /**
     * @var TypeListInterface
     */
    private TypeListInterface $cacheTypeList;

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
     * @param State $appState
     * @param TypeListInterface $cacheTypeList
     * @param IssueFactory $issueFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        DeploymentConfig $deploymentConfig,
        State $appState,
        TypeListInterface $cacheTypeList,
        IssueFactory $issueFactory,
        LoggerInterface $logger
    ) {
        $this->deploymentConfig = $deploymentConfig;
        $this->appState = $appState;
        $this->cacheTypeList = $cacheTypeList;
        $this->issueFactory = $issueFactory;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function checkConfiguration(): array
    {
        $issues = [];

        try {
            // Check deployment mode
            $modeIssue = $this->checkDeploymentMode();
            if ($modeIssue) {
                $issues[] = $modeIssue;
            }

            // Check Redis configuration
            $redisIssue = $this->checkRedisConfiguration();
            if ($redisIssue) {
                $issues[] = $redisIssue;
            }

            // Check cache status
            $cacheIssues = $this->checkCacheStatus();
            if (!empty($cacheIssues)) {
                $issues = array_merge($issues, $cacheIssues);
            }
        } catch (\Exception $e) {
            $this->logger->error('Configuration check failed: ' . $e->getMessage());
            throw new LocalizedException(__('Failed to check configuration: %1', $e->getMessage()));
        }

        return $issues;
    }

    /**
     * Check deployment mode configuration
     *
     * @return IssueInterface|null
     */
    private function checkDeploymentMode(): ?IssueInterface
    {
        try {
            $currentMode = $this->appState->getMode();
            if ($currentMode === State::MODE_DEVELOPER) {
                return $this->issueFactory->create([
                    'priority' => IssueInterface::PRIORITY_HIGH,
                    'category' => 'Config',
                    'issue' => 'Switch from developer mode to production mode',
                    'details' => 'Developer mode significantly impacts performance and should not be used in production.',
                    'current_value' => $currentMode,
                    'recommended_value' => State::MODE_PRODUCTION
                ]);
            }
        } catch (LocalizedException $e) {
            // Mode not set, default is developer
            return $this->issueFactory->create([
                'priority' => IssueInterface::PRIORITY_HIGH,
                'category' => 'Config',
                'issue' => 'Deployment mode not set',
                'details' => 'No deployment mode is set. Production mode should be configured.',
                'current_value' => 'not set',
                'recommended_value' => State::MODE_PRODUCTION
            ]);
        }

        return null;
    }

    /**
     * Check Redis cache configuration
     *
     * @return IssueInterface|null
     */
    private function checkRedisConfiguration(): ?IssueInterface
    {
        $cacheConfig = $this->deploymentConfig->get('cache');
        
        $hasRedis = false;
        if (isset($cacheConfig['frontend'])) {
            foreach ($cacheConfig['frontend'] as $cache) {
                if (isset($cache['backend']) && strpos($cache['backend'], self::CACHE_BACKEND_REDIS) !== false) {
                    $hasRedis = true;
                    break;
                }
            }
        }

        if (!$hasRedis) {
            return $this->issueFactory->create([
                'priority' => IssueInterface::PRIORITY_HIGH,
                'category' => 'Config',
                'issue' => 'Configure Redis for cache storage',
                'details' => 'Using Redis for cache storage can significantly improve performance.',
                'current_value' => self::CACHE_BACKEND_FILE,
                'recommended_value' => 'Redis cache backend'
            ]);
        }

        return null;
    }

    /**
     * Check cache type status
     *
     * @return IssueInterface[]
     */
    private function checkCacheStatus(): array
    {
        $issues = [];
        $cacheTypes = $this->cacheTypeList->getTypes();
        $disabledCaches = [];

        foreach ($cacheTypes as $cacheCode => $cacheType) {
            if (!$cacheType->getStatus()) {
                $disabledCaches[] = $cacheCode;
            }
        }

        if (!empty($disabledCaches)) {
            $issues[] = $this->issueFactory->create([
                'priority' => IssueInterface::PRIORITY_MEDIUM,
                'category' => 'Config',
                'issue' => 'Enable all cache types',
                'details' => 'The following cache types are disabled: ' . implode(', ', $disabledCaches),
                'current_value' => count($disabledCaches) . ' cache types disabled',
                'recommended_value' => 'All caches enabled'
            ]);
        }

        return $issues;
    }
}