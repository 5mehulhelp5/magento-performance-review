<?php

namespace Performance\Review\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\ReadInterface;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;

class CodebaseAnalyzer
{
    private Filesystem $filesystem;
    private DirectoryList $directoryList;
    private ModuleDirReader $moduleDirReader;
    private ReadInterface $appCodeDirectory;

    public function __construct(
        Filesystem $filesystem,
        DirectoryList $directoryList,
        ModuleDirReader $moduleDirReader
    ) {
        $this->filesystem = $filesystem;
        $this->directoryList = $directoryList;
        $this->moduleDirReader = $moduleDirReader;
        $this->appCodeDirectory = $filesystem->getDirectoryRead(DirectoryList::APP);
    }

    public function analyzeCodebase(): array
    {
        $issues = [];

        // Analyze custom code volume
        $customCodeAnalysis = $this->analyzeCustomCode();
        if (!empty($customCodeAnalysis)) {
            $issues = array_merge($issues, $customCodeAnalysis);
        }

        // Analyze event observers
        $eventAnalysis = $this->analyzeEventObservers();
        if (!empty($eventAnalysis)) {
            $issues = array_merge($issues, $eventAnalysis);
        }

        // Analyze plugins
        $pluginAnalysis = $this->analyzePlugins();
        if (!empty($pluginAnalysis)) {
            $issues = array_merge($issues, $pluginAnalysis);
        }

        // Analyze preferences
        $preferenceAnalysis = $this->analyzePreferences();
        if (!empty($preferenceAnalysis)) {
            $issues = array_merge($issues, $preferenceAnalysis);
        }

        return $issues;
    }

    private function analyzeCustomCode(): array
    {
        $issues = [];
        $customCodePath = 'code';
        
        try {
            if ($this->appCodeDirectory->isDirectory($customCodePath)) {
                $fileCount = 0;
                $totalSize = 0;
                
                $this->countFilesRecursive($customCodePath, $fileCount, $totalSize);
                
                if ($fileCount > 5000) {
                    $issues[] = [
                        'priority' => 'Medium',
                        'category' => 'Codebase',
                        'issue' => 'Review custom code volume',
                        'details' => sprintf(
                            'You have approximately %d files in app/code. Large amounts of custom code can impact performance.',
                            $fileCount
                        ),
                        'current_value' => $fileCount . ' files',
                        'recommended_value' => 'Minimize custom code, use composer packages where possible'
                    ];
                }
            }
        } catch (\Exception $e) {
            // Directory might not exist
        }

        return $issues;
    }

    private function analyzeEventObservers(): array
    {
        $issues = [];
        $observerCount = 0;
        $eventConfigs = $this->findConfigFiles('events.xml');
        
        foreach ($eventConfigs as $configFile) {
            try {
                $content = $this->appCodeDirectory->readFile($configFile);
                $observerCount += substr_count($content, '<observer');
            } catch (\Exception $e) {
                continue;
            }
        }

        if ($observerCount > 100) {
            $issues[] = [
                'priority' => 'Medium',
                'category' => 'Codebase',
                'issue' => 'Optimize event observers',
                'details' => sprintf(
                    'Found %d event configuration files. Excessive event observers can impact performance.',
                    count($eventConfigs)
                ),
                'current_value' => $observerCount . ' observers across ' . count($eventConfigs) . ' files',
                'recommended_value' => 'Review and consolidate observers where possible'
            ];
        }

        return $issues;
    }

    private function analyzePlugins(): array
    {
        $issues = [];
        $pluginCount = 0;
        $diConfigs = $this->findConfigFiles('di.xml');
        
        foreach ($diConfigs as $configFile) {
            try {
                $content = $this->appCodeDirectory->readFile($configFile);
                $pluginCount += substr_count($content, '<plugin');
            } catch (\Exception $e) {
                continue;
            }
        }

        if ($pluginCount > 200) {
            $issues[] = [
                'priority' => 'Medium',
                'category' => 'Codebase',
                'issue' => 'Review plugin usage',
                'details' => sprintf(
                    'Found approximately %d plugin configurations. Excessive plugins can cause performance issues.',
                    $pluginCount
                ),
                'current_value' => $pluginCount . ' plugins',
                'recommended_value' => 'Use plugins sparingly, consider events or service contracts'
            ];
        }

        return $issues;
    }

    private function analyzePreferences(): array
    {
        $issues = [];
        $preferenceCount = 0;
        $diConfigs = $this->findConfigFiles('di.xml');
        
        foreach ($diConfigs as $configFile) {
            try {
                $content = $this->appCodeDirectory->readFile($configFile);
                $preferenceCount += substr_count($content, '<preference');
            } catch (\Exception $e) {
                continue;
            }
        }

        if ($preferenceCount > 50) {
            $issues[] = [
                'priority' => 'Medium',
                'category' => 'Codebase',
                'issue' => 'Review preference usage',
                'details' => sprintf(
                    'Found approximately %d preference configurations. Excessive class preferences can cause performance issues.',
                    $preferenceCount
                ),
                'current_value' => $preferenceCount . ' preferences',
                'recommended_value' => 'Use plugins instead of preferences where possible'
            ];
        }

        return $issues;
    }

    private function countFilesRecursive(string $path, int &$fileCount, int &$totalSize): void
    {
        try {
            $items = $this->appCodeDirectory->read($path);
            foreach ($items as $item) {
                $itemPath = $path . '/' . $item;
                if ($this->appCodeDirectory->isDirectory($itemPath)) {
                    $this->countFilesRecursive($itemPath, $fileCount, $totalSize);
                } else {
                    $fileCount++;
                    try {
                        $stat = $this->appCodeDirectory->stat($itemPath);
                        $totalSize += $stat['size'] ?? 0;
                    } catch (\Exception $e) {
                        // Skip if we can't stat the file
                    }
                }
            }
        } catch (\Exception $e) {
            // Skip directories we can't read
        }
    }

    private function findConfigFiles(string $filename): array
    {
        $configFiles = [];
        $this->findFilesRecursive('code', $filename, $configFiles);
        return $configFiles;
    }

    private function findFilesRecursive(string $path, string $filename, array &$files): void
    {
        try {
            $items = $this->appCodeDirectory->read($path);
            foreach ($items as $item) {
                $itemPath = $path . '/' . $item;
                if ($this->appCodeDirectory->isDirectory($itemPath)) {
                    if ($item === 'etc') {
                        // Check for config file in etc directory
                        $configPath = $itemPath . '/' . $filename;
                        if ($this->appCodeDirectory->isFile($configPath)) {
                            $files[] = $configPath;
                        }
                        // Also check area-specific configs
                        foreach (['adminhtml', 'frontend', 'global', 'webapi_rest', 'webapi_soap', 'crontab'] as $area) {
                            $areaConfigPath = $itemPath . '/' . $area . '/' . $filename;
                            if ($this->appCodeDirectory->isFile($areaConfigPath)) {
                                $files[] = $areaConfigPath;
                            }
                        }
                    } else {
                        $this->findFilesRecursive($itemPath, $filename, $files);
                    }
                }
            }
        } catch (\Exception $e) {
            // Skip directories we can't read
        }
    }
}