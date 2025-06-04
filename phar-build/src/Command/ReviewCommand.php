<?php
declare(strict_types=1);

namespace Performance\Review\Phar\Command;

use Performance\Review\Phar\Analyzer\ConfigurationAnalyzer;
use Performance\Review\Phar\Analyzer\PhpAnalyzer;
use Performance\Review\Phar\Analyzer\MysqlAnalyzer;
use Performance\Review\Phar\Analyzer\DatabaseAnalyzer;
use Performance\Review\Phar\Analyzer\ModuleAnalyzer;
use Performance\Review\Phar\Analyzer\RedisAnalyzer;
use Performance\Review\Phar\Analyzer\CodebaseAnalyzer;
use Performance\Review\Phar\Analyzer\FrontendAnalyzer;
use Performance\Review\Phar\Analyzer\IndexerCronAnalyzer;
use Performance\Review\Phar\Analyzer\ThirdPartyAnalyzer;
use Performance\Review\Phar\Analyzer\ApiAnalyzer;
use Performance\Review\Phar\ReportGenerator;
use Performance\Review\Phar\IssueInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ReviewCommand extends Command
{
    private array $analyzers = [
        'config' => ConfigurationAnalyzer::class,
        'php' => PhpAnalyzer::class,
        'mysql' => MysqlAnalyzer::class,
        'redis' => RedisAnalyzer::class,
        'database' => DatabaseAnalyzer::class,
        'modules' => ModuleAnalyzer::class,
        'codebase' => CodebaseAnalyzer::class,
        'frontend' => FrontendAnalyzer::class,
        'indexing' => IndexerCronAnalyzer::class,
        'thirdparty' => ThirdPartyAnalyzer::class,
        'api' => ApiAnalyzer::class,
    ];

    protected function configure(): void
    {
        $this->setName('review')
            ->setDescription('Run performance review on a Magento 2 installation')
            ->addOption(
                'magento-root',
                'm',
                InputOption::VALUE_REQUIRED,
                'Path to Magento root directory',
                getcwd()
            )
            ->addOption(
                'category',
                'c',
                InputOption::VALUE_REQUIRED,
                'Run specific category only (config, php, mysql, redis, database, modules, codebase, frontend, indexing, thirdparty, api)'
            )
            ->addOption(
                'output-file',
                'o',
                InputOption::VALUE_REQUIRED,
                'Save report to file'
            )
            ->addOption(
                'no-color',
                null,
                InputOption::VALUE_NONE,
                'Disable colored output'
            )
            ->addOption(
                'details',
                'd',
                InputOption::VALUE_NONE,
                'Show detailed information'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $magentoRoot = $input->getOption('magento-root');
        $category = $input->getOption('category');
        $outputFile = $input->getOption('output-file');
        $noColor = $input->getOption('no-color');
        
        // Validate Magento root
        if (!$this->isValidMagentoRoot($magentoRoot)) {
            $output->writeln('<error>Invalid Magento root directory. Please specify the correct path with --magento-root</error>');
            return Command::FAILURE;
        }
        
        // Determine which analyzers to run
        $analyzersToRun = [];
        if ($category) {
            if (!isset($this->analyzers[$category])) {
                $output->writeln('<error>Invalid category. Available categories: ' . implode(', ', array_keys($this->analyzers)) . '</error>');
                return Command::FAILURE;
            }
            $analyzersToRun = [$category => $this->analyzers[$category]];
        } else {
            $analyzersToRun = $this->analyzers;
        }
        
        // Run analysis
        $output->writeln('Running Magento 2 Performance Review...');
        $output->writeln('Magento root: ' . $magentoRoot);
        $output->writeln('');
        
        $results = [];
        foreach ($analyzersToRun as $name => $analyzerClass) {
            $output->write("Analyzing $name... ");
            try {
                $analyzer = new $analyzerClass();
                $issues = $analyzer->analyze($magentoRoot);
                $results[$name] = $issues;
                $output->writeln('<info>Done</info>');
            } catch (\Exception $e) {
                $output->writeln('<error>Failed: ' . $e->getMessage() . '</error>');
                $results[$name] = [];
            }
        }
        
        // Generate report
        if ($outputFile) {
            $fileOutput = new \Symfony\Component\Console\Output\StreamOutput(
                fopen($outputFile, 'w'),
                OutputInterface::VERBOSITY_NORMAL,
                false // No decoration for file output
            );
            $reportGenerator = new ReportGenerator();
            $reportGenerator->generate($results, $fileOutput, false);
            fclose($fileOutput->getStream());
            $output->writeln("\nReport saved to: $outputFile");
        } else {
            $reportGenerator = new ReportGenerator();
            $reportGenerator->generate($results, $output, !$noColor);
        }
        
        // Determine exit code based on high priority issues
        $highPriorityCount = 0;
        foreach ($results as $issues) {
            foreach ($issues as $issue) {
                if ($issue->getPriority() === IssueInterface::PRIORITY_HIGH) {
                    $highPriorityCount++;
                }
            }
        }
        
        return $highPriorityCount > 0 ? Command::FAILURE : Command::SUCCESS;
    }
    
    private function isValidMagentoRoot(string $path): bool
    {
        return file_exists($path . '/app/etc/env.php') 
            && file_exists($path . '/app/bootstrap.php')
            && file_exists($path . '/pub/index.php');
    }
}