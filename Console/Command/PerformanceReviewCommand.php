<?php

namespace Performance\Review\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Performance\Review\Model\ConfigurationChecker;
use Performance\Review\Model\ModuleAnalyzer;
use Performance\Review\Model\CodebaseAnalyzer;
use Performance\Review\Model\ReportGenerator;

class PerformanceReviewCommand extends Command
{
    private ConfigurationChecker $configurationChecker;
    private ModuleAnalyzer $moduleAnalyzer;
    private CodebaseAnalyzer $codebaseAnalyzer;
    private ReportGenerator $reportGenerator;

    public function __construct(
        ConfigurationChecker $configurationChecker,
        ModuleAnalyzer $moduleAnalyzer,
        CodebaseAnalyzer $codebaseAnalyzer,
        ReportGenerator $reportGenerator,
        string $name = null
    ) {
        $this->configurationChecker = $configurationChecker;
        $this->moduleAnalyzer = $moduleAnalyzer;
        $this->codebaseAnalyzer = $codebaseAnalyzer;
        $this->reportGenerator = $reportGenerator;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('performance:review')
            ->setDescription('Run a comprehensive performance review of your Magento 2 installation')
            ->addOption(
                'output-file',
                'o',
                InputOption::VALUE_OPTIONAL,
                'Save the report to a file instead of displaying it'
            )
            ->addOption(
                'category',
                'c',
                InputOption::VALUE_OPTIONAL,
                'Run review for specific category only (config, modules, codebase)'
            )
            ->addOption(
                'no-color',
                null,
                InputOption::VALUE_NONE,
                'Disable colored output'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $startTime = microtime(true);
        
        $output->writeln('<info>Starting Magento 2 Performance Review...</info>');
        $output->writeln('');
        
        $issues = [];
        $category = $input->getOption('category');
        
        // Run configuration checks
        if (!$category || $category === 'config') {
            $output->write('Checking configuration... ');
            $configIssues = $this->configurationChecker->checkConfiguration();
            $issues = array_merge($issues, $configIssues);
            $output->writeln('<info>✓</info>');
        }
        
        // Run module analysis
        if (!$category || $category === 'modules') {
            $output->write('Analyzing modules... ');
            $moduleIssues = $this->moduleAnalyzer->analyzeModules();
            $issues = array_merge($issues, $moduleIssues);
            $output->writeln('<info>✓</info>');
        }
        
        // Run codebase analysis
        if (!$category || $category === 'codebase') {
            $output->write('Analyzing codebase... ');
            $codebaseIssues = $this->codebaseAnalyzer->analyzeCodebase();
            $issues = array_merge($issues, $codebaseIssues);
            $output->writeln('<info>✓</info>');
        }
        
        $output->writeln('');
        
        // Generate report
        $report = $this->reportGenerator->generateReport($issues);
        
        // Handle output
        $outputFile = $input->getOption('output-file');
        if ($outputFile) {
            file_put_contents($outputFile, $report);
            $output->writeln("<info>Report saved to: $outputFile</info>");
        } else {
            // If no-color option is set, strip ANSI color codes
            if ($input->getOption('no-color')) {
                $report = preg_replace('/\033\[[0-9;]*m/', '', $report);
            }
            $output->write($report);
        }
        
        $executionTime = round(microtime(true) - $startTime, 2);
        $output->writeln('');
        $output->writeln("<info>Performance review completed in {$executionTime} seconds.</info>");
        
        // Return exit code based on severity of issues found
        $highPriorityCount = 0;
        foreach ($issues as $issue) {
            if (($issue['priority'] ?? '') === 'High') {
                $highPriorityCount++;
            }
        }
        
        if ($highPriorityCount > 0) {
            $output->writeln("<error>Found {$highPriorityCount} high priority issues that should be addressed.</error>");
            return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }
}