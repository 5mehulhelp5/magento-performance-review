<?php

namespace Performance\Review\Model;

use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use Magento\Framework\App\DeploymentConfig;

class ModuleAnalyzer
{
    private ModuleListInterface $moduleList;
    private ModuleDirReader $moduleDirReader;
    private DeploymentConfig $deploymentConfig;

    // Known performance-impacting modules
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

    public function __construct(
        ModuleListInterface $moduleList,
        ModuleDirReader $moduleDirReader,
        DeploymentConfig $deploymentConfig
    ) {
        $this->moduleList = $moduleList;
        $this->moduleDirReader = $moduleDirReader;
        $this->deploymentConfig = $deploymentConfig;
    }

    public function analyzeModules(): array
    {
        $issues = [];
        
        // Get all modules
        $allModules = $this->moduleList->getNames();
        $activeModules = $this->moduleList->getAll();
        $configuredModules = $this->deploymentConfig->get('modules') ?? [];
        
        // Count active modules
        $activeCount = count($activeModules);
        if ($activeCount > 200) {
            $issues[] = [
                'priority' => 'Medium',
                'category' => 'Modules',
                'issue' => 'Review and reduce the number of active modules',
                'details' => sprintf('You have %d active modules. Having too many modules can impact performance.', $activeCount),
                'current_value' => $activeCount . ' modules',
                'recommended_value' => 'Less than 200 modules'
            ];
        }

        // Check for performance-impacting modules
        $impactingModules = [];
        foreach (self::PERFORMANCE_IMPACT_MODULES as $module => $reason) {
            if (isset($activeModules[$module])) {
                $impactingModules[$module] = $reason;
            }
        }

        if (!empty($impactingModules)) {
            $details = "The following modules are known to impact performance:\n";
            foreach ($impactingModules as $module => $reason) {
                $details .= "- $module: $reason\n";
            }
            
            $issues[] = [
                'priority' => 'Medium',
                'category' => 'Modules',
                'issue' => 'Consider optimizing or disabling heavy modules',
                'details' => trim($details),
                'current_value' => count($impactingModules) . ' performance-impacting modules active',
                'recommended_value' => 'Review necessity of each module'
            ];
        }

        // Check for disabled but not removed modules
        $disabledModules = [];
        foreach ($configuredModules as $module => $enabled) {
            if ($enabled === 0) {
                $disabledModules[] = $module;
            }
        }

        if (count($disabledModules) > 0) {
            $issues[] = [
                'priority' => 'Low',
                'category' => 'Modules',
                'issue' => 'Remove disabled modules completely',
                'details' => sprintf(
                    'You have %d disabled modules. Consider removing them completely to improve code deployment speed. Disabled modules: %s',
                    count($disabledModules),
                    implode(', ', array_slice($disabledModules, 0, 10)) . (count($disabledModules) > 10 ? '...' : '')
                ),
                'current_value' => count($disabledModules) . ' disabled modules',
                'recommended_value' => '0 disabled modules'
            ];
        }

        // Check for duplicate functionality modules
        $duplicateFunctionality = $this->checkDuplicateFunctionality($activeModules);
        if (!empty($duplicateFunctionality)) {
            $issues[] = [
                'priority' => 'Medium',
                'category' => 'Modules',
                'issue' => 'Review modules with overlapping functionality',
                'details' => 'Multiple modules providing similar functionality: ' . implode(', ', $duplicateFunctionality),
                'current_value' => 'Duplicate functionality detected',
                'recommended_value' => 'Use single module per functionality'
            ];
        }

        return $issues;
    }

    private function checkDuplicateFunctionality(array $activeModules): array
    {
        $duplicates = [];
        
        // Check for multiple payment modules
        $paymentModules = array_filter(array_keys($activeModules), function($module) {
            return strpos($module, 'Payment') !== false || strpos($module, 'Paypal') !== false;
        });
        
        if (count($paymentModules) > 5) {
            $duplicates[] = 'Multiple payment modules (' . count($paymentModules) . ')';
        }

        // Check for multiple shipping modules
        $shippingModules = array_filter(array_keys($activeModules), function($module) {
            return strpos($module, 'Shipping') !== false || strpos($module, 'Carrier') !== false;
        });
        
        if (count($shippingModules) > 5) {
            $duplicates[] = 'Multiple shipping modules (' . count($shippingModules) . ')';
        }

        return $duplicates;
    }
}