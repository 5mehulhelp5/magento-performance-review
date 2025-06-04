<?php
declare(strict_types=1);

namespace Performance\Review\Phar\Analyzer;

use Performance\Review\Phar\AnalyzerInterface;
use Performance\Review\Phar\Issue;
use Performance\Review\Phar\IssueInterface;

class CodebaseAnalyzer implements AnalyzerInterface
{
    public function analyze(string $magentoRoot): array
    {
        $issues = [];
        
        try {
            // Check app/code directory
            $appCodePath = $magentoRoot . '/app/code';
            if (!is_dir($appCodePath)) {
                return $issues;
            }
            
            // Count files in app/code
            $fileCount = $this->countFilesRecursive($appCodePath);
            if ($fileCount > 5000) {
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_MEDIUM,
                    'Large custom codebase',
                    "Your app/code directory contains $fileCount files.\n" .
                    "Large codebases can impact performance.\n" .
                    "Consider: Code review, removing unused modules"
                );
            }
            
            // Check for common performance issues in di.xml files
            $diXmlFiles = $this->findFiles($appCodePath, 'di.xml');
            $pluginCount = 0;
            $preferenceCount = 0;
            $observerCount = 0;
            
            foreach ($diXmlFiles as $file) {
                $content = file_get_contents($file);
                $pluginCount += substr_count($content, '<plugin');
                $preferenceCount += substr_count($content, '<preference');
            }
            
            // Count observers in events.xml files
            $eventsXmlFiles = $this->findFiles($appCodePath, 'events.xml');
            foreach ($eventsXmlFiles as $file) {
                $content = file_get_contents($file);
                $observerCount += substr_count($content, '<observer');
            }
            
            // Check plugin count
            if ($pluginCount > 200) {
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_HIGH,
                    'Excessive number of plugins',
                    "Found $pluginCount plugins in custom code.\n" .
                    "Too many plugins can significantly impact performance.\n" .
                    "Review and consolidate plugins where possible"
                );
            } elseif ($pluginCount > 100) {
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_MEDIUM,
                    'High number of plugins',
                    "Found $pluginCount plugins in custom code.\n" .
                    "Consider reviewing plugin usage"
                );
            }
            
            // Check preference count
            if ($preferenceCount > 50) {
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_HIGH,
                    'Excessive use of preferences',
                    "Found $preferenceCount preferences in custom code.\n" .
                    "Preferences should be replaced with plugins where possible.\n" .
                    "Preferences can cause conflicts and upgrade issues"
                );
            } elseif ($preferenceCount > 25) {
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_MEDIUM,
                    'High number of preferences',
                    "Found $preferenceCount preferences in custom code.\n" .
                    "Consider using plugins instead of preferences"
                );
            }
            
            // Check observer count
            if ($observerCount > 100) {
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_MEDIUM,
                    'High number of event observers',
                    "Found $observerCount observers in custom code.\n" .
                    "Many observers can impact performance.\n" .
                    "Review observer necessity and consolidate where possible"
                );
            }
            
            // Check for layout XML files
            $layoutFiles = $this->findLayoutFiles($appCodePath);
            $layoutUpdateCount = 0;
            
            foreach ($layoutFiles as $file) {
                $content = file_get_contents($file);
                // Count various layout operations that can impact performance
                $layoutUpdateCount += substr_count($content, '<referenceBlock');
                $layoutUpdateCount += substr_count($content, '<referenceContainer');
                $layoutUpdateCount += substr_count($content, '<move');
                $layoutUpdateCount += substr_count($content, '<remove');
            }
            
            if ($layoutUpdateCount > 500) {
                $issues[] = new Issue(
                    IssueInterface::PRIORITY_MEDIUM,
                    'Excessive layout updates',
                    "Found $layoutUpdateCount layout updates in custom code.\n" .
                    "Too many layout updates can slow page rendering.\n" .
                    "Consider consolidating layout changes"
                );
            }
            
        } catch (\Exception $e) {
            $issues[] = new Issue(
                IssueInterface::PRIORITY_HIGH,
                'Codebase analysis failed',
                "Error: " . $e->getMessage()
            );
        }
        
        return $issues;
    }
    
    private function countFilesRecursive(string $dir): int
    {
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $count++;
            }
        }
        
        return $count;
    }
    
    private function findFiles(string $dir, string $filename): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getFilename() === $filename) {
                $files[] = $file->getPathname();
            }
        }
        
        return $files;
    }
    
    private function findLayoutFiles(string $dir): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && 
                strpos($file->getPath(), '/view/') !== false &&
                strpos($file->getPath(), '/layout/') !== false &&
                $file->getExtension() === 'xml') {
                $files[] = $file->getPathname();
            }
        }
        
        return $files;
    }
}