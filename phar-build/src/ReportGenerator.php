<?php
declare(strict_types=1);

namespace Performance\Review\Phar;

use Symfony\Component\Console\Output\OutputInterface;

class ReportGenerator
{
    private const COLORS = [
        IssueInterface::PRIORITY_HIGH => 'red',
        IssueInterface::PRIORITY_MEDIUM => 'yellow',
        IssueInterface::PRIORITY_LOW => 'green'
    ];

    public function generate(array $analysisResults, OutputInterface $output, bool $useColor = true): void
    {
        $this->writeHeader($output);
        
        $totalIssues = 0;
        $priorityCounts = [
            IssueInterface::PRIORITY_HIGH => 0,
            IssueInterface::PRIORITY_MEDIUM => 0,
            IssueInterface::PRIORITY_LOW => 0
        ];
        
        foreach ($analysisResults as $category => $issues) {
            if (empty($issues)) {
                continue;
            }
            
            $this->writeCategoryHeader($output, $category);
            
            foreach ($issues as $issue) {
                $this->writeIssue($output, $issue, $useColor);
                $totalIssues++;
                $priorityCounts[$issue->getPriority()]++;
            }
        }
        
        $this->writeSummary($output, $totalIssues, $priorityCounts, $useColor);
    }
    
    private function writeHeader(OutputInterface $output): void
    {
        $output->writeln(str_repeat('=', 80));
        $output->writeln(str_pad('MAGENTO 2 PERFORMANCE REVIEW REPORT', 80, ' ', STR_PAD_BOTH));
        $output->writeln(str_repeat('=', 80));
        $output->writeln('Generated: ' . date('Y-m-d H:i:s'));
        $output->writeln(str_repeat('=', 80));
        $output->writeln('');
    }
    
    private function writeCategoryHeader(OutputInterface $output, string $category): void
    {
        $output->writeln('== ' . ucfirst($category) . ' ==');
        $output->writeln(str_repeat('-', 80));
        $output->writeln(sprintf("%-10s | %-40s | %-25s", 'Priority', 'Recommendation', 'Details'));
        $output->writeln(str_repeat('-', 10) . '+' . str_repeat('-', 42) . '+' . str_repeat('-', 27));
    }
    
    private function writeIssue(OutputInterface $output, IssueInterface $issue, bool $useColor): void
    {
        $priority = strtoupper($issue->getPriority());
        $recommendation = $this->wrapText($issue->getRecommendation(), 40);
        $details = $this->wrapText($issue->getDetails(), 25);
        
        $recommendationLines = explode("\n", $recommendation);
        $detailLines = explode("\n", $details);
        $maxLines = max(count($recommendationLines), count($detailLines));
        
        for ($i = 0; $i < $maxLines; $i++) {
            $priorityText = $i === 0 ? $priority : '';
            $recommendationText = $recommendationLines[$i] ?? '';
            $detailText = $detailLines[$i] ?? '';
            
            if ($useColor && $i === 0) {
                $color = self::COLORS[$issue->getPriority()] ?? 'white';
                $line = sprintf(
                    "<fg=%s>%-10s</> | %-40s | %-25s",
                    $color,
                    $priorityText,
                    $recommendationText,
                    $detailText
                );
            } else {
                $line = sprintf(
                    "%-10s | %-40s | %-25s",
                    $priorityText,
                    $recommendationText,
                    $detailText
                );
            }
            
            $output->writeln($line);
        }
        
        $output->writeln(str_repeat('-', 10) . '+' . str_repeat('-', 42) . '+' . str_repeat('-', 27));
    }
    
    private function writeSummary(OutputInterface $output, int $totalIssues, array $priorityCounts, bool $useColor): void
    {
        $output->writeln('');
        $output->writeln('== Summary ==');
        $output->writeln(str_repeat('=', 80));
        $output->writeln('');
        $output->writeln("Total Issues Found: $totalIssues");
        $output->writeln('');
        
        foreach ($priorityCounts as $priority => $count) {
            if ($count > 0) {
                if ($useColor) {
                    $color = self::COLORS[$priority] ?? 'white';
                    $output->writeln(sprintf(
                        "  <fg=%s>%-8s: %d issues</>",
                        $color,
                        ucfirst($priority),
                        $count
                    ));
                } else {
                    $output->writeln(sprintf(
                        "  %-8s: %d issues",
                        ucfirst($priority),
                        $count
                    ));
                }
            }
        }
        
        $output->writeln('');
        $output->writeln('Recommended Actions:');
        $output->writeln('1. Address all High priority issues first');
        $output->writeln('2. Review Medium priority issues based on your specific use case');
        $output->writeln('3. Consider Low priority issues for optimization');
        $output->writeln('');
    }
    
    private function wrapText(string $text, int $width): string
    {
        $lines = [];
        $currentLine = '';
        
        $words = preg_split('/\s+/', $text);
        foreach ($words as $word) {
            if (strlen($currentLine . ' ' . $word) > $width) {
                if ($currentLine !== '') {
                    $lines[] = $currentLine;
                    $currentLine = $word;
                } else {
                    // Word is longer than width, split it
                    $lines[] = substr($word, 0, $width);
                    $currentLine = substr($word, $width);
                }
            } else {
                $currentLine = trim($currentLine . ' ' . $word);
            }
        }
        
        if ($currentLine !== '') {
            $lines[] = $currentLine;
        }
        
        return implode("\n", $lines);
    }
}