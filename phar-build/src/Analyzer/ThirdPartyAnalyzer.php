<?php
declare(strict_types=1);

namespace Performance\Review\Phar\Analyzer;

use Performance\Review\Phar\AnalyzerInterface;
use Performance\Review\Phar\Issue;
use Performance\Review\Phar\IssueInterface;
use Performance\Review\Phar\Util\MagentoHelper;

class ThirdPartyAnalyzer implements AnalyzerInterface
{
    private array $knownProblematicExtensions = [
        'Amasty_*' => ['pattern' => true, 'issue' => 'Some Amasty extensions can impact performance'],
        'Mirasvit_*' => ['pattern' => true, 'issue' => 'Some Mirasvit extensions can be resource-intensive'],
        'Xtento_*' => ['pattern' => true, 'issue' => 'Export/Import extensions can consume significant resources'],
        'Mageplaza_*' => ['pattern' => true, 'issue' => 'Some Mageplaza extensions add frontend overhead'],
        'Mageworx_*' => ['pattern' => true, 'issue' => 'Complex features may impact performance'],
        'Webkul_*' => ['pattern' => true, 'issue' => 'Marketplace modules can be resource-intensive'],
        'Wyomind_*' => ['pattern' => true, 'issue' => 'Feed generation can consume resources'],
    ];
    
    private array $extensionCategories = [
        'layered_navigation' => ['Improved_Layered', 'Amasty_Shopby', 'Smile_ElasticsuiteCatalog'],
        'search' => ['Algolia_', 'Klevu_', 'Doofinder_', 'Smile_ElasticsuiteCatalog'],
        'checkout' => ['Amasty_Checkout', 'OneStepCheckout', 'IWD_Opc'],
        'email' => ['Dotdigitalgroup_', 'Ebizmarts_', 'Klaviyo_'],
        'page_builder' => ['Magezon_', 'Blueskytechco_PageBuilder', 'MGS_'],
    ];

    public function analyze(string $magentoRoot): array
    {
        $issues = [];
        
        try {
            $modules = MagentoHelper::getActiveModules($magentoRoot);
            
            // Filter third-party modules
            $thirdPartyModules = array_filter($modules, function($module) {
                return !MagentoHelper::isCoreModule($module) && 
                       !strpos($module, 'Performance_') === 0; // Exclude our own modules
            });
            
            $thirdPartyCount = count($thirdPartyModules);
            
            // Check total count
            if ($thirdPartyCount > 50) {
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_HIGH,
                    'Excessive third-party extensions',
                    "You have $thirdPartyCount third-party extensions installed.\n" .
                    "This can significantly impact performance and stability.\n" .
                    "Review and remove unnecessary extensions"
                );
            } elseif ($thirdPartyCount > 30) {
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_MEDIUM,
                    'Many third-party extensions',
                    "You have $thirdPartyCount third-party extensions installed.\n" .
                    "Consider reviewing and consolidating functionality"
                );
            }
            
            // Check for known problematic extensions
            $problematicFound = [];
            foreach ($thirdPartyModules as $module) {
                foreach ($this->knownProblematicExtensions as $pattern => $info) {
                    if ($info['pattern']) {
                        $searchPattern = str_replace('*', '', $pattern);
                        if (strpos($module, $searchPattern) === 0) {
                            $problematicFound[$module] = $info['issue'];
                        }
                    } elseif ($module === $pattern) {
                        $problematicFound[$module] = $info['issue'];
                    }
                }
            }
            
            if (!empty($problematicFound)) {
                $details = "Extensions that may need review:\n";
                foreach ($problematicFound as $module => $issue) {
                    $details .= "- $module: $issue\n";
                }
                
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_MEDIUM,
                    'Extensions with known performance considerations',
                    $details
                );
            }
            
            // Check for duplicate functionality
            $duplicates = $this->findDuplicateFunctionality($thirdPartyModules);
            if (!empty($duplicates)) {
                $details = "Multiple extensions providing similar functionality:\n";
                foreach ($duplicates as $category => $moduleList) {
                    $details .= "- $category: " . implode(', ', $moduleList) . "\n";
                }
                
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_MEDIUM,
                    'Duplicate extension functionality',
                    $details . "\nConsider consolidating to reduce overhead"
                );
            }
            
            // Check for outdated extensions (based on common patterns)
            $outdatedPatterns = [
                'M1_' => 'Possible Magento 1 compatibility module',
                '_M1' => 'Possible Magento 1 compatibility module',
                'Unirgy_' => 'May need updates for latest Magento versions',
            ];
            
            $possiblyOutdated = [];
            foreach ($thirdPartyModules as $module) {
                foreach ($outdatedPatterns as $pattern => $reason) {
                    if (strpos($module, $pattern) !== false) {
                        $possiblyOutdated[$module] = $reason;
                    }
                }
            }
            
            if (!empty($possiblyOutdated)) {
                $details = "Extensions that may be outdated:\n";
                foreach ($possiblyOutdated as $module => $reason) {
                    $details .= "- $module: $reason\n";
                }
                
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_LOW,
                    'Possibly outdated extensions',
                    $details
                );
            }
            
            // Check composer.json for version constraints
            $composerFile = $magentoRoot . '/composer.json';
            if (file_exists($composerFile)) {
                $composer = json_decode(file_get_contents($composerFile), true);
                $require = $composer['require'] ?? [];
                
                $devVersions = 0;
                foreach ($require as $package => $version) {
                    if (strpos($version, 'dev-') === 0 || $version === '*') {
                        $devVersions++;
                    }
                }
                
                if ($devVersions > 0) {
                    $issues[] = new Issue(
                        IssueInterface::PRIORITY_MEDIUM,
                        'Unstable extension versions',
                        "Found $devVersions extensions using dev or wildcard versions.\n" .
                        "This can lead to instability and unexpected updates.\n" .
                        "Use specific version constraints in composer.json"
                    );
                }
            }
            
        } catch (\Exception $e) {
            $issues[] = new Issue(
                IssueInterface::PRIORITY_HIGH,
                'Third-party analysis failed',
                "Error: " . $e->getMessage()
            );
        }
        
        return $issues;
    }
    
    private function findDuplicateFunctionality(array $modules): array
    {
        $duplicates = [];
        
        foreach ($this->extensionCategories as $category => $patterns) {
            $found = [];
            foreach ($modules as $module) {
                foreach ($patterns as $pattern) {
                    if (strpos($module, $pattern) !== false) {
                        $found[] = $module;
                    }
                }
            }
            
            if (count($found) > 1) {
                $duplicates[$category] = $found;
            }
        }
        
        return $duplicates;
    }
}