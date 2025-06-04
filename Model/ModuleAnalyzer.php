<?php
/**
 * Copyright © Performance, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Performance\Review\Model;

use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use Magento\Framework\App\DeploymentConfig;
use Performance\Review\Api\Data\IssueInterface;
use Performance\Review\Model\IssueFactory;
use Psr\Log\LoggerInterface;

/**
 * Module analyzer for performance review
 *
 * @since 1.0.0
 */
class ModuleAnalyzer
{
    /**
     * Module count thresholds
     */
    private const MODULE_COUNT_WARNING = 100;
    private const MODULE_COUNT_CRITICAL = 200;
    
    /**
     * Known performance-impacting modules
     */
    private const PERFORMANCE_IMPACT_MODULES = [
        'Magento_Logging' => 'Extensive database logging can impact performance',
        'Magento_CustomerSegment' => 'Customer segmentation requires additional processing',
        'Magento_TargetRule' => 'Dynamic product recommendations increase load',
        'Magento_AdminGws' => 'Admin role restrictions add overhead',
        'Magento_Elasticsearch' => 'Consider if search volume justifies the overhead',
        'Magento_GoogleOptimizer' => 'A/B testing adds tracking overhead',
        'Magento_GoogleAnalytics' => 'External tracking calls can slow down pages',
        'Magento_Swatches' => 'Heavy on product listing pages',
        'Magento_LayeredNavigation' => 'Can be resource intensive with many attributes'
    ];

    /**
     * @var ModuleListInterface
     */
    private ModuleListInterface $moduleList;

    /**
     * @var ModuleDirReader
     */
    private ModuleDirReader $moduleDirReader;

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
     * @param ModuleListInterface $moduleList
     * @param ModuleDirReader $moduleDirReader
     * @param DeploymentConfig $deploymentConfig
     * @param IssueFactory $issueFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        ModuleListInterface $moduleList,
        ModuleDirReader $moduleDirReader,
        DeploymentConfig $deploymentConfig,
        IssueFactory $issueFactory,
        LoggerInterface $logger
    ) {
        $this->moduleList = $moduleList;
        $this->moduleDirReader = $moduleDirReader;
        $this->deploymentConfig = $deploymentConfig;
        $this->issueFactory = $issueFactory;
        $this->logger = $logger;
    }

    /**
     * Analyze modules for performance issues
     *
     * @return IssueInterface[]
     */
    public function analyzeModules(): array
    {
        $issues = [];
        
        // Get all modules
        $allModules = $this->moduleList->getAll();
        $configuredModules = $this->deploymentConfig->get('modules') ?? [];
        
        // Separate core and third-party modules
        $coreModules = [];
        $thirdPartyModules = [];
        
        foreach ($allModules as $moduleName => $moduleInfo) {
            if ($this->isCoreModule($moduleName)) {
                $coreModules[$moduleName] = $moduleInfo;
            } else {
                $thirdPartyModules[$moduleName] = $moduleInfo;
            }
        }
        
        // Count only third-party modules
        $thirdPartyCount = count($thirdPartyModules);
        
        // Check module count (only third-party)
        if ($thirdPartyCount > self::MODULE_COUNT_CRITICAL) {
            $issue = $this->issueFactory->create([
                'priority' => IssueInterface::PRIORITY_HIGH,
                'category' => 'Modules',
                'issue' => 'Excessive number of third-party modules',
                'details' => sprintf(
                    'You have %d third-party modules (excluding %d Magento core modules). Too many modules can impact performance.',
                    $thirdPartyCount,
                    count($coreModules)
                ),
                'current_value' => $thirdPartyCount . ' third-party modules',
                'recommended_value' => 'Less than ' . self::MODULE_COUNT_WARNING . ' third-party modules'
            ]);
            
            // Add module list as additional data
            $issue->setData('module_list', array_keys($thirdPartyModules));
            $issue->setData('module_count_breakdown', [
                'core' => count($coreModules),
                'third_party' => $thirdPartyCount,
                'total' => count($allModules)
            ]);
            
            $issues[] = $issue;
        } elseif ($thirdPartyCount > self::MODULE_COUNT_WARNING) {
            $issue = $this->issueFactory->create([
                'priority' => IssueInterface::PRIORITY_MEDIUM,
                'category' => 'Modules',
                'issue' => 'Large number of third-party modules',
                'details' => sprintf(
                    'You have %d third-party modules (excluding %d Magento core modules). Consider reviewing module necessity.',
                    $thirdPartyCount,
                    count($coreModules)
                ),
                'current_value' => $thirdPartyCount . ' third-party modules',
                'recommended_value' => 'Less than ' . self::MODULE_COUNT_WARNING . ' third-party modules'
            ]);
            
            // Add module list as additional data
            $issue->setData('module_list', array_keys($thirdPartyModules));
            $issue->setData('module_count_breakdown', [
                'core' => count($coreModules),
                'third_party' => $thirdPartyCount,
                'total' => count($allModules)
            ]);
            
            $issues[] = $issue;
        }

        // Check for performance-impacting modules
        $impactingModules = [];
        foreach (self::PERFORMANCE_IMPACT_MODULES as $module => $reason) {
            if (isset($allModules[$module])) {
                $impactingModules[$module] = $reason;
            }
        }

        if (!empty($impactingModules)) {
            $details = "The following modules are known to impact performance:\n";
            foreach ($impactingModules as $module => $reason) {
                $details .= "- $module: $reason\n";
            }
            
            $issue = $this->issueFactory->create([
                'priority' => IssueInterface::PRIORITY_MEDIUM,
                'category' => 'Modules',
                'issue' => 'Consider optimizing or disabling heavy modules',
                'details' => trim($details),
                'current_value' => count($impactingModules) . ' performance-impacting modules active',
                'recommended_value' => 'Review necessity of each module'
            ]);
            
            $issue->setData('impacting_modules', $impactingModules);
            
            $issues[] = $issue;
        }

        // Check for disabled but not removed modules
        $disabledModules = [];
        foreach ($configuredModules as $module => $enabled) {
            if ($enabled === 0 && !$this->isCoreModule($module)) {
                $disabledModules[] = $module;
            }
        }

        if (count($disabledModules) > 0) {
            $issue = $this->issueFactory->create([
                'priority' => IssueInterface::PRIORITY_LOW,
                'category' => 'Modules',
                'issue' => 'Remove disabled modules completely',
                'details' => sprintf(
                    'You have %d disabled modules. Consider removing them completely to improve code deployment speed.',
                    count($disabledModules)
                ),
                'current_value' => count($disabledModules) . ' disabled modules',
                'recommended_value' => '0 disabled modules'
            ]);
            
            $issue->setData('disabled_modules', $disabledModules);
            
            $issues[] = $issue;
        }

        // Check for duplicate functionality modules
        $duplicateFunctionality = $this->checkDuplicateFunctionality($allModules);
        if (!empty($duplicateFunctionality)) {
            $issue = $this->issueFactory->create([
                'priority' => IssueInterface::PRIORITY_MEDIUM,
                'category' => 'Modules',
                'issue' => 'Review modules with overlapping functionality',
                'details' => 'Multiple modules providing similar functionality: ' . implode(', ', array_keys($duplicateFunctionality)),
                'current_value' => 'Duplicate functionality detected',
                'recommended_value' => 'Use single module per functionality'
            ]);
            
            $issue->setData('duplicate_modules', $duplicateFunctionality);
            
            $issues[] = $issue;
        }

        return $issues;
    }

    /**
     * Check if module is a core Magento module
     *
     * @param string $moduleName
     * @return bool
     */
    private function isCoreModule(string $moduleName): bool
    {
        // Core Magento modules
        if (strpos($moduleName, 'Magento_') === 0) {
            return true;
        }
        
        // Other core vendor modules that ship with Magento
        $coreVendors = [
            'PayPal_',
            'Klarna_',
            'Vertex_',
            'Dotdigitalgroup_',
            'Amazon_',
            'Yotpo_'
        ];
        
        foreach ($coreVendors as $vendor) {
            if (strpos($moduleName, $vendor) === 0) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check for modules with duplicate functionality
     *
     * @param array $activeModules
     * @return array
     */
    private function checkDuplicateFunctionality(array $activeModules): array
    {
        $duplicates = [];
        $moduleNames = array_keys($activeModules);
        
        // Check for multiple payment modules
        $paymentModules = array_filter($moduleNames, function($module) {
            return !$this->isCoreModule($module) && 
                   (strpos($module, 'Payment') !== false || 
                    strpos($module, 'Checkout') !== false ||
                    strpos($module, '_Pay') !== false);
        });
        
        if (count($paymentModules) > 5) {
            $duplicates['payment'] = [
                'count' => count($paymentModules),
                'modules' => array_values($paymentModules)
            ];
        }

        // Check for multiple shipping modules
        $shippingModules = array_filter($moduleNames, function($module) {
            return !$this->isCoreModule($module) && 
                   (strpos($module, 'Shipping') !== false || 
                    strpos($module, 'Carrier') !== false ||
                    strpos($module, 'Delivery') !== false);
        });
        
        if (count($shippingModules) > 5) {
            $duplicates['shipping'] = [
                'count' => count($shippingModules),
                'modules' => array_values($shippingModules)
            ];
        }

        // Check for multiple search/navigation modules
        $searchModules = array_filter($moduleNames, function($module) {
            return !$this->isCoreModule($module) && 
                   (strpos($module, 'Search') !== false || 
                    strpos($module, 'Navigation') !== false ||
                    strpos($module, 'Filter') !== false);
        });
        
        if (count($searchModules) > 3) {
            $duplicates['search'] = [
                'count' => count($searchModules),
                'modules' => array_values($searchModules)
            ];
        }

        return $duplicates;
    }
}