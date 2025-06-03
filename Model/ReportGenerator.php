<?php

namespace Performance\Review\Model;

class ReportGenerator
{
    private const PRIORITY_COLORS = [
        'High' => "\033[31m",    // Red
        'Medium' => "\033[33m",  // Yellow
        'Low' => "\033[32m"      // Green
    ];

    private const RESET_COLOR = "\033[0m";

    public function generateReport(array $issues): string
    {
        $report = $this->generateHeader();
        
        // Group issues by category
        $groupedIssues = $this->groupIssuesByCategory($issues);
        
        // Generate sections
        foreach ($groupedIssues as $category => $categoryIssues) {
            $report .= $this->generateCategorySection($category, $categoryIssues);
        }
        
        // Add summary
        $report .= $this->generateSummary($issues);
        
        return $report;
    }

    private function generateHeader(): string
    {
        $header = "\n";
        $header .= str_repeat('=', 80) . "\n";
        $header .= "                    MAGENTO 2 PERFORMANCE REVIEW REPORT\n";
        $header .= str_repeat('=', 80) . "\n";
        $header .= "Generated: " . date('Y-m-d H:i:s') . "\n";
        $header .= str_repeat('=', 80) . "\n\n";
        
        return $header;
    }

    private function groupIssuesByCategory(array $issues): array
    {
        $grouped = [];
        foreach ($issues as $issue) {
            $category = $issue['category'] ?? 'Other';
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][] = $issue;
        }
        
        // Sort categories to show Config first, then Modules, then Codebase
        $sortedGrouped = [];
        $categoryOrder = ['Config', 'Modules', 'Codebase'];
        
        foreach ($categoryOrder as $category) {
            if (isset($grouped[$category])) {
                $sortedGrouped[$category] = $grouped[$category];
                unset($grouped[$category]);
            }
        }
        
        // Add any remaining categories
        foreach ($grouped as $category => $issues) {
            $sortedGrouped[$category] = $issues;
        }
        
        return $sortedGrouped;
    }

    private function generateCategorySection(string $category, array $issues): string
    {
        $section = "== $category ==\n";
        $section .= str_repeat('-', 80) . "\n";
        
        // Sort issues by priority (High -> Medium -> Low)
        usort($issues, function($a, $b) {
            $priorities = ['High' => 3, 'Medium' => 2, 'Low' => 1];
            return ($priorities[$b['priority']] ?? 0) - ($priorities[$a['priority']] ?? 0);
        });
        
        // Create table
        $section .= sprintf("%-10s | %-40s | %-25s\n", "Priority", "Recommendation", "Details");
        $section .= str_repeat('-', 10) . '+' . str_repeat('-', 42) . '+' . str_repeat('-', 27) . "\n";
        
        foreach ($issues as $issue) {
            $priority = $issue['priority'] ?? 'Unknown';
            $color = self::PRIORITY_COLORS[$priority] ?? '';
            $recommendation = $this->truncateString($issue['issue'] ?? 'N/A', 40);
            
            // First line of the table
            $section .= sprintf(
                "%s%-10s%s | %-40s | %-25s\n",
                $color,
                $priority,
                self::RESET_COLOR,
                $recommendation,
                $this->truncateString($issue['details'] ?? '', 25)
            );
            
            // Additional details on separate lines if needed
            if (strlen($issue['details'] ?? '') > 25) {
                $detailLines = $this->wrapText($issue['details'], 65);
                foreach (array_slice($detailLines, 1) as $line) {
                    $section .= sprintf("%-10s | %-40s | %s\n", "", "", $line);
                }
            }
            
            // Show current vs recommended values
            if (isset($issue['current_value']) && isset($issue['recommended_value'])) {
                $section .= sprintf(
                    "%-10s | %-40s | Current: %s\n",
                    "",
                    "",
                    $issue['current_value']
                );
                $section .= sprintf(
                    "%-10s | %-40s | Recommended: %s\n",
                    "",
                    "",
                    $issue['recommended_value']
                );
            }
            
            $section .= str_repeat('-', 10) . '+' . str_repeat('-', 42) . '+' . str_repeat('-', 27) . "\n";
        }
        
        $section .= "\n";
        return $section;
    }

    private function generateSummary(array $issues): string
    {
        $summary = "== Summary ==\n";
        $summary .= str_repeat('=', 80) . "\n\n";
        
        // Count issues by priority
        $priorityCounts = [
            'High' => 0,
            'Medium' => 0,
            'Low' => 0
        ];
        
        foreach ($issues as $issue) {
            $priority = $issue['priority'] ?? 'Unknown';
            if (isset($priorityCounts[$priority])) {
                $priorityCounts[$priority]++;
            }
        }
        
        $summary .= "Total Issues Found: " . count($issues) . "\n\n";
        
        foreach ($priorityCounts as $priority => $count) {
            $color = self::PRIORITY_COLORS[$priority];
            $summary .= sprintf(
                "  %s%-8s%s: %d issue%s\n",
                $color,
                $priority,
                self::RESET_COLOR,
                $count,
                $count !== 1 ? 's' : ''
            );
        }
        
        $summary .= "\n";
        $summary .= "Recommended Actions:\n";
        $summary .= "1. Address all High priority issues first\n";
        $summary .= "2. Review Medium priority issues based on your specific use case\n";
        $summary .= "3. Consider Low priority issues for optimization\n";
        $summary .= "\n";
        $summary .= "For detailed information on each issue, refer to the sections above.\n";
        $summary .= str_repeat('=', 80) . "\n";
        
        return $summary;
    }

    private function truncateString(string $string, int $length): string
    {
        if (strlen($string) <= $length) {
            return $string;
        }
        return substr($string, 0, $length - 3) . '...';
    }

    private function wrapText(string $text, int $width): array
    {
        $lines = [];
        $words = explode(' ', $text);
        $currentLine = '';
        
        foreach ($words as $word) {
            if (strlen($currentLine . ' ' . $word) <= $width) {
                $currentLine .= ($currentLine ? ' ' : '') . $word;
            } else {
                if ($currentLine) {
                    $lines[] = $currentLine;
                }
                $currentLine = $word;
            }
        }
        
        if ($currentLine) {
            $lines[] = $currentLine;
        }
        
        return $lines ?: [''];
    }
}