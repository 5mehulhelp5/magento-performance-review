<?php

namespace Performance\Review\Model;

class PerformanceAnalyzer
{
    private ConfigurationChecker $configurationChecker;
    private ModuleAnalyzer $moduleAnalyzer;
    private CodebaseAnalyzer $codebaseAnalyzer;

    public function __construct(
        ConfigurationChecker $configurationChecker,
        ModuleAnalyzer $moduleAnalyzer,
        CodebaseAnalyzer $codebaseAnalyzer
    ) {
        $this->configurationChecker = $configurationChecker;
        $this->moduleAnalyzer = $moduleAnalyzer;
        $this->codebaseAnalyzer = $codebaseAnalyzer;
    }

    public function analyze(): array
    {
        $results = [
            'timestamp' => date('Y-m-d H:i:s'),
            'issues' => [],
            'summary' => []
        ];

        // Run all analyzers
        $configIssues = $this->configurationChecker->checkConfiguration();
        $moduleIssues = $this->moduleAnalyzer->analyzeModules();
        $codebaseIssues = $this->codebaseAnalyzer->analyzeCodebase();

        // Merge all issues
        $results['issues'] = array_merge($configIssues, $moduleIssues, $codebaseIssues);

        // Generate summary
        $results['summary'] = $this->generateSummary($results['issues']);

        return $results;
    }

    private function generateSummary(array $issues): array
    {
        $summary = [
            'total_issues' => count($issues),
            'by_priority' => [
                'High' => 0,
                'Medium' => 0,
                'Low' => 0
            ],
            'by_category' => [
                'Config' => 0,
                'Modules' => 0,
                'Codebase' => 0
            ]
        ];

        foreach ($issues as $issue) {
            $priority = $issue['priority'] ?? 'Unknown';
            if (isset($summary['by_priority'][$priority])) {
                $summary['by_priority'][$priority]++;
            }

            $category = $issue['category'] ?? 'Other';
            if (isset($summary['by_category'][$category])) {
                $summary['by_category'][$category]++;
            } else {
                $summary['by_category'][$category] = 1;
            }
        }

        return $summary;
    }

    public function getRecommendedActions(array $issues): array
    {
        $actions = [];

        // High priority actions
        $highPriorityIssues = array_filter($issues, function($issue) {
            return ($issue['priority'] ?? '') === 'High';
        });

        if (!empty($highPriorityIssues)) {
            $actions[] = [
                'priority' => 'Immediate',
                'action' => 'Address all high priority issues first',
                'issues' => array_map(function($issue) {
                    return $issue['issue'] ?? '';
                }, $highPriorityIssues)
            ];
        }

        // Configuration improvements
        $configIssues = array_filter($issues, function($issue) {
            return ($issue['category'] ?? '') === 'Config';
        });

        if (!empty($configIssues)) {
            $actions[] = [
                'priority' => 'Short-term',
                'action' => 'Optimize Magento configuration',
                'issues' => array_map(function($issue) {
                    return $issue['issue'] ?? '';
                }, $configIssues)
            ];
        }

        // Module optimization
        $moduleIssues = array_filter($issues, function($issue) {
            return ($issue['category'] ?? '') === 'Modules';
        });

        if (!empty($moduleIssues)) {
            $actions[] = [
                'priority' => 'Medium-term',
                'action' => 'Review and optimize module usage',
                'issues' => array_map(function($issue) {
                    return $issue['issue'] ?? '';
                }, $moduleIssues)
            ];
        }

        return $actions;
    }
}