<?php

namespace Performance\Review\Model;

use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\App\ProductMetadataInterface;

class ThirdPartyAnalyzer
{
    private ModuleListInterface $moduleList;
    private ComponentRegistrarInterface $componentRegistrar;
    private File $fileDriver;
    private ProductMetadataInterface $productMetadata;

    // Known problematic extensions
    private const PROBLEMATIC_EXTENSIONS = [
        'Amasty_*' => ['pattern' => true, 'issue' => 'Some Amasty extensions have known performance impacts'],
        'Mirasvit_*' => ['pattern' => true, 'issue' => 'Some Mirasvit extensions can be resource intensive'],
        'Xtento_*' => ['pattern' => true, 'issue' => 'Export/Import extensions can impact performance during operations'],
        'Aheadworks_*' => ['pattern' => true, 'issue' => 'Some extensions add significant database queries'],
        'Mageplaza_*' => ['pattern' => true, 'issue' => 'Multiple Mageplaza extensions can conflict and slow down the site'],
    ];

    // Extensions that commonly conflict
    private const CONFLICTING_EXTENSIONS = [
        'layered_navigation' => [
            'extensions' => ['Amasty_Shopby', 'Mirasvit_LayeredNavigation', 'Mageplaza_LayeredNavigation'],
            'issue' => 'Multiple layered navigation extensions can conflict'
        ],
        'search' => [
            'extensions' => ['Amasty_ElasticSearch', 'Mirasvit_Search', 'Smile_ElasticsuiteCore'],
            'issue' => 'Multiple search extensions can cause conflicts and duplicate functionality'
        ],
        'seo' => [
            'extensions' => ['Amasty_SeoToolkit', 'Mirasvit_Seo', 'Mageplaza_Seo'],
            'issue' => 'Multiple SEO extensions can create duplicate meta tags and conflicts'
        ]
    ];

    public function __construct(
        ModuleListInterface $moduleList,
        ComponentRegistrarInterface $componentRegistrar,
        File $fileDriver,
        ProductMetadataInterface $productMetadata
    ) {
        $this->moduleList = $moduleList;
        $this->componentRegistrar = $componentRegistrar;
        $this->fileDriver = $fileDriver;
        $this->productMetadata = $productMetadata;
    }

    public function analyzeThirdPartyExtensions(): array
    {
        $issues = [];

        // Check third-party extension count
        $countIssues = $this->checkThirdPartyCount();
        if (!empty($countIssues)) {
            $issues = array_merge($issues, $countIssues);
        }

        // Check for known problematic extensions
        $problematicIssues = $this->checkProblematicExtensions();
        if (!empty($problematicIssues)) {
            $issues = array_merge($issues, $problematicIssues);
        }

        // Check for conflicting extensions
        $conflictIssues = $this->checkConflictingExtensions();
        if (!empty($conflictIssues)) {
            $issues = array_merge($issues, $conflictIssues);
        }

        // Check extension compatibility
        $compatibilityIssues = $this->checkExtensionCompatibility();
        if (!empty($compatibilityIssues)) {
            $issues = array_merge($issues, $compatibilityIssues);
        }

        // Check for outdated extensions
        $outdatedIssues = $this->checkOutdatedExtensions();
        if (!empty($outdatedIssues)) {
            $issues = array_merge($issues, $outdatedIssues);
        }

        return $issues;
    }

    private function checkThirdPartyCount(): array
    {
        $issues = [];
        $thirdPartyModules = [];
        
        $allModules = $this->moduleList->getNames();
        foreach ($allModules as $moduleName) {
            if (!$this->isCoreModule($moduleName)) {
                $thirdPartyModules[] = $moduleName;
            }
        }
        
        $count = count($thirdPartyModules);
        
        if ($count > 50) {
            $issues[] = [
                'priority' => 'High',
                'category' => 'Third-party',
                'issue' => 'Excessive third-party extensions',
                'details' => sprintf(
                    'You have %d third-party extensions installed. Each extension adds overhead and potential conflicts.',
                    $count
                ),
                'current_value' => $count . ' third-party extensions',
                'recommended_value' => 'Audit and remove unnecessary extensions'
            ];
        } elseif ($count > 30) {
            $issues[] = [
                'priority' => 'Medium',
                'category' => 'Third-party',
                'issue' => 'Large number of third-party extensions',
                'details' => sprintf(
                    'You have %d third-party extensions. Consider consolidating functionality.',
                    $count
                ),
                'current_value' => $count . ' third-party extensions',
                'recommended_value' => 'Review extension necessity'
            ];
        }

        return $issues;
    }

    private function checkProblematicExtensions(): array
    {
        $issues = [];
        $foundProblematic = [];
        
        $allModules = $this->moduleList->getAll();
        
        foreach ($allModules as $moduleName => $moduleInfo) {
            foreach (self::PROBLEMATIC_EXTENSIONS as $pattern => $info) {
                if ($info['pattern']) {
                    $patternWithoutAsterisk = str_replace('*', '', $pattern);
                    if (strpos($moduleName, $patternWithoutAsterisk) === 0) {
                        $foundProblematic[$pattern][] = $moduleName;
                    }
                } elseif ($moduleName === $pattern) {
                    $foundProblematic[$pattern][] = $moduleName;
                }
            }
        }
        
        if (!empty($foundProblematic)) {
            $details = "Found extensions with known performance impacts:\n";
            foreach ($foundProblematic as $pattern => $modules) {
                $info = self::PROBLEMATIC_EXTENSIONS[$pattern];
                $details .= sprintf("- %s: %s\n", implode(', ', $modules), $info['issue']);
            }
            
            $issues[] = [
                'priority' => 'Medium',
                'category' => 'Third-party',
                'issue' => 'Extensions with known performance impacts',
                'details' => trim($details),
                'current_value' => 'Potentially problematic extensions found',
                'recommended_value' => 'Review extension necessity and configuration'
            ];
        }

        return $issues;
    }

    private function checkConflictingExtensions(): array
    {
        $issues = [];
        $allModules = array_keys($this->moduleList->getAll());
        
        foreach (self::CONFLICTING_EXTENSIONS as $category => $info) {
            $found = array_intersect($info['extensions'], $allModules);
            
            if (count($found) > 1) {
                $issues[] = [
                    'priority' => 'High',
                    'category' => 'Third-party',
                    'issue' => sprintf('Conflicting %s extensions', $category),
                    'details' => sprintf(
                        '%s. Found: %s',
                        $info['issue'],
                        implode(', ', $found)
                    ),
                    'current_value' => count($found) . ' conflicting extensions',
                    'recommended_value' => 'Use only one extension per functionality'
                ];
            }
        }

        return $issues;
    }

    private function checkExtensionCompatibility(): array
    {
        $issues = [];
        $incompatibleExtensions = [];
        
        $magentoVersion = $this->productMetadata->getVersion();
        $allModules = $this->moduleList->getAll();
        
        foreach ($allModules as $moduleName => $moduleInfo) {
            if (!$this->isCoreModule($moduleName)) {
                $modulePath = $this->componentRegistrar->getPath(
                    'module',
                    $moduleName
                );
                
                if ($modulePath) {
                    $composerFile = $modulePath . '/composer.json';
                    if ($this->fileDriver->isExists($composerFile)) {
                        try {
                            $composerData = json_decode(
                                $this->fileDriver->fileGetContents($composerFile),
                                true
                            );
                            
                            // Check if module specifies Magento version requirement
                            if (isset($composerData['require']['magento/product-community-edition'])) {
                                $requirement = $composerData['require']['magento/product-community-edition'];
                                // Simple check - in reality, you'd use a version constraint parser
                                if (strpos($requirement, '2.4.8') === false && 
                                    strpos($requirement, '^2.4') === false &&
                                    strpos($requirement, '~2.4') === false) {
                                    $incompatibleExtensions[] = $moduleName;
                                }
                            }
                        } catch (\Exception $e) {
                            // Skip if we can't read composer.json
                        }
                    }
                }
            }
        }
        
        if (!empty($incompatibleExtensions)) {
            $issues[] = [
                'priority' => 'High',
                'category' => 'Third-party',
                'issue' => 'Potentially incompatible extensions',
                'details' => sprintf(
                    'The following extensions may not be compatible with Magento %s: %s',
                    $magentoVersion,
                    implode(', ', array_slice($incompatibleExtensions, 0, 5))
                ),
                'current_value' => count($incompatibleExtensions) . ' potentially incompatible',
                'recommended_value' => 'Verify extension compatibility with current Magento version'
            ];
        }

        return $issues;
    }

    private function checkOutdatedExtensions(): array
    {
        $issues = [];
        $oldExtensions = [];
        
        $allModules = $this->moduleList->getAll();
        
        foreach ($allModules as $moduleName => $moduleInfo) {
            if (!$this->isCoreModule($moduleName)) {
                $modulePath = $this->componentRegistrar->getPath(
                    'module',
                    $moduleName
                );
                
                if ($modulePath) {
                    // Check for deprecated practices
                    $registrationFile = $modulePath . '/registration.php';
                    if ($this->fileDriver->isExists($registrationFile)) {
                        try {
                            $content = $this->fileDriver->fileGetContents($registrationFile);
                            
                            // Check for old-style registration
                            if (strpos($content, 'Mage::register') !== false ||
                                strpos($content, 'Magento\Framework\Component\ComponentRegistrar::register') === false) {
                                $oldExtensions[] = $moduleName;
                            }
                        } catch (\Exception $e) {
                            // Skip if we can't read file
                        }
                    }
                }
            }
        }
        
        if (!empty($oldExtensions)) {
            $issues[] = [
                'priority' => 'Medium',
                'category' => 'Third-party',
                'issue' => 'Outdated extension code detected',
                'details' => sprintf(
                    'The following extensions use outdated coding practices: %s',
                    implode(', ', array_slice($oldExtensions, 0, 5))
                ),
                'current_value' => count($oldExtensions) . ' outdated extensions',
                'recommended_value' => 'Update extensions to use modern Magento 2 practices'
            ];
        }

        return $issues;
    }

    private function isCoreModule(string $moduleName): bool
    {
        return strpos($moduleName, 'Magento_') === 0 || 
               strpos($moduleName, 'PayPal_') === 0 ||
               strpos($moduleName, 'Klarna_') === 0 ||
               strpos($moduleName, 'Vertex_') === 0 ||
               strpos($moduleName, 'Dotdigitalgroup_') === 0 ||
               strpos($moduleName, 'Amazon_') === 0;
    }
}