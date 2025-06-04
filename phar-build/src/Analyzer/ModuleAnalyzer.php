<?php
declare(strict_types=1);

namespace Performance\Review\Phar\Analyzer;

use Performance\Review\Phar\AnalyzerInterface;
use Performance\Review\Phar\Issue;
use Performance\Review\Phar\IssueInterface;
use Performance\Review\Phar\Util\MagentoHelper;

class ModuleAnalyzer implements AnalyzerInterface
{
    private array $performanceImpactingModules = [
        'Magento_GoogleAnalytics' => 'Can add frontend overhead',
        'Magento_GoogleOptimizer' => 'Adds tracking scripts',
        'Magento_NewRelicReporting' => 'Performance monitoring overhead',
        'Magento_Swagger' => 'Not needed in production',
        'Magento_SwaggerWebapi' => 'Not needed in production',
        'Magento_SwaggerWebapiAsync' => 'Not needed in production',
        'Magento_Version' => 'Security risk in production',
        'Magento_AdminAnalytics' => 'Admin tracking overhead',
        'Magento_TwoFactorAuth' => 'Can slow admin login if misconfigured'
    ];

    private array $moduleCategories = [
        'payment' => ['Payment', 'Paypal', 'Braintree', 'Authorizenet'],
        'shipping' => ['Shipping', 'Ups', 'Usps', 'Fedex', 'Dhl'],
        'search' => ['Search', 'Elasticsearch', 'CatalogSearch'],
        'import' => ['Import', 'Export'],
        'analytics' => ['Analytics', 'GoogleAnalytics', 'GoogleOptimizer', 'NewRelic']
    ];

    public function analyze(string $magentoRoot): array
    {
        $issues = [];
        
        try {
            $modules = MagentoHelper::getActiveModules($magentoRoot);
            $nonCoreModules = array_filter($modules, function($module) {
                return !MagentoHelper::isCoreModule($module);
            });
            
            $totalModules = count($modules);
            $nonCoreCount = count($nonCoreModules);
            
            // Check total module count
            if ($nonCoreCount > 200) {
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_HIGH,
                    'Excessive number of modules',
                    "You have $nonCoreCount non-core modules active (excluding Magento core modules).\n" .
                    "This can significantly impact performance.\n" .
                    "Recommended: Less than 200 non-core modules\n" .
                    "Use --details flag to see the complete list"
                );
            } elseif ($nonCoreCount > 100) {
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_MEDIUM,
                    'High number of modules',
                    "You have $nonCoreCount non-core modules active.\n" .
                    "Consider reviewing and disabling unused modules.\n" .
                    "Recommended: Less than 100 non-core modules"
                );
            }
            
            // Check for performance-impacting modules
            $impactingModules = [];
            foreach ($modules as $module) {
                if (isset($this->performanceImpactingModules[$module])) {
                    $impactingModules[$module] = $this->performanceImpactingModules[$module];
                }
            }
            
            if (!empty($impactingModules)) {
                $details = "Modules with potential performance impact:\n";
                foreach ($impactingModules as $module => $reason) {
                    $details .= "- $module: $reason\n";
                }
                
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_MEDIUM,
                    'Performance-impacting modules detected',
                    $details
                );
            }
            
            // Check for duplicate functionality
            $duplicates = $this->findDuplicateFunctionality($modules);
            if (!empty($duplicates)) {
                $details = "Multiple modules providing similar functionality:\n";
                foreach ($duplicates as $category => $moduleList) {
                    $details .= "- $category: " . implode(', ', $moduleList) . "\n";
                }
                
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_LOW,
                    'Potential duplicate module functionality',
                    $details
                );
            }
            
        } catch (\Exception $e) {
            $issues[] = new Issue(
                IssueInterface::PRIORITY_HIGH,
                'Module analysis failed',
                "Error: " . $e->getMessage()
            );
        }
        
        return $issues;
    }
    
    private function findDuplicateFunctionality(array $modules): array
    {
        $duplicates = [];
        
        foreach ($this->moduleCategories as $category => $keywords) {
            $found = [];
            foreach ($modules as $module) {
                foreach ($keywords as $keyword) {
                    if (stripos($module, $keyword) !== false) {
                        $found[] = $module;
                        break;
                    }
                }
            }
            
            if (count($found) > 2) {
                $duplicates[$category] = $found;
            }
        }
        
        return $duplicates;
    }
}