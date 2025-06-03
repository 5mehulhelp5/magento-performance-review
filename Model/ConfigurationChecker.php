<?php

namespace Performance\Review\Model;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\State;
use Magento\Framework\App\Cache\TypeListInterface;

class ConfigurationChecker
{
    private DeploymentConfig $deploymentConfig;
    private State $appState;
    private TypeListInterface $cacheTypeList;

    public function __construct(
        DeploymentConfig $deploymentConfig,
        State $appState,
        TypeListInterface $cacheTypeList
    ) {
        $this->deploymentConfig = $deploymentConfig;
        $this->appState = $appState;
        $this->cacheTypeList = $cacheTypeList;
    }

    public function checkConfiguration(): array
    {
        $issues = [];

        // Check deployment mode
        $modeCheck = $this->checkDeploymentMode();
        if ($modeCheck) {
            $issues[] = $modeCheck;
        }

        // Check Redis configuration
        $redisCheck = $this->checkRedisConfiguration();
        if ($redisCheck) {
            $issues[] = $redisCheck;
        }

        // Check cache status
        $cacheCheck = $this->checkCacheStatus();
        if (!empty($cacheCheck)) {
            $issues = array_merge($issues, $cacheCheck);
        }

        return $issues;
    }

    private function checkDeploymentMode(): ?array
    {
        try {
            $currentMode = $this->appState->getMode();
            if ($currentMode === State::MODE_DEVELOPER) {
                return [
                    'priority' => 'High',
                    'category' => 'Config',
                    'issue' => 'Switch from developer mode to production mode',
                    'details' => 'Developer mode significantly impacts performance and should not be used in production.',
                    'current_value' => $currentMode,
                    'recommended_value' => State::MODE_PRODUCTION
                ];
            }
        } catch (\Exception $e) {
            // Mode not set, default is developer
            return [
                'priority' => 'High',
                'category' => 'Config',
                'issue' => 'Deployment mode not set',
                'details' => 'No deployment mode is set. Production mode should be configured.',
                'current_value' => 'not set',
                'recommended_value' => State::MODE_PRODUCTION
            ];
        }

        return null;
    }

    private function checkRedisConfiguration(): ?array
    {
        $cacheConfig = $this->deploymentConfig->get('cache');
        
        $hasRedis = false;
        if (isset($cacheConfig['frontend'])) {
            foreach ($cacheConfig['frontend'] as $cache) {
                if (isset($cache['backend']) && strpos($cache['backend'], 'Redis') !== false) {
                    $hasRedis = true;
                    break;
                }
            }
        }

        if (!$hasRedis) {
            return [
                'priority' => 'High',
                'category' => 'Config',
                'issue' => 'Configure Redis for cache storage',
                'details' => 'Using Redis for cache storage can significantly improve performance.',
                'current_value' => 'File-based cache',
                'recommended_value' => 'Redis cache backend'
            ];
        }

        return null;
    }

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
            $issues[] = [
                'priority' => 'Medium',
                'category' => 'Config',
                'issue' => 'Enable all cache types',
                'details' => 'The following cache types are disabled: ' . implode(', ', $disabledCaches),
                'current_value' => count($disabledCaches) . ' cache types disabled',
                'recommended_value' => 'All caches enabled'
            ];
        }

        return $issues;
    }
}