<?php
/**
 * Copyright © Performance, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Performance\Review\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Performance\Review\Api\ConfigurationCheckerInterface;
use Performance\Review\Model\ModuleAnalyzer;
use Performance\Review\Model\CodebaseAnalyzer;
use Performance\Review\Model\DatabaseAnalyzer;
use Performance\Review\Model\FrontendAnalyzer;
use Performance\Review\Model\IndexerCronAnalyzer;
use Performance\Review\Model\ThirdPartyAnalyzer;
use Performance\Review\Model\ApiAnalyzer;
use Performance\Review\Model\ReportGenerator;
use Performance\Review\Model\IssueFactory;
use Performance\Review\Api\Data\IssueInterface;
use Psr\Log\LoggerInterface;

/**
 * Performance review command
 *
 * @since 1.0.0
 */
class PerformanceReviewCommand extends Command
{
    /**
     * Exit code constants
     */
    private const EXIT_CODE_SUCCESS = 0;
    private const EXIT_CODE_FAILURE = 1;

    /**
     * @var ConfigurationCheckerInterface
     */
    private ConfigurationCheckerInterface $configurationChecker;

    /**
     * @var ModuleAnalyzer
     */
    private ModuleAnalyzer $moduleAnalyzer;

    /**
     * @var CodebaseAnalyzer
     */
    private CodebaseAnalyzer $codebaseAnalyzer;

    /**
     * @var DatabaseAnalyzer
     */
    private DatabaseAnalyzer $databaseAnalyzer;

    /**
     * @var FrontendAnalyzer
     */
    private FrontendAnalyzer $frontendAnalyzer;

    /**
     * @var IndexerCronAnalyzer
     */
    private IndexerCronAnalyzer $indexerCronAnalyzer;

    /**
     * @var ThirdPartyAnalyzer
     */
    private ThirdPartyAnalyzer $thirdPartyAnalyzer;

    /**
     * @var ApiAnalyzer
     */
    private ApiAnalyzer $apiAnalyzer;

    /**
     * @var ReportGenerator
     */
    private ReportGenerator $reportGenerator;

    /**
     * @var IssueFactory
     */
    private IssueFactory $issueFactory;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * Constructor
     *
     * @param ConfigurationCheckerInterface $configurationChecker
     * @param ModuleAnalyzer $moduleAnalyzer
     * @param CodebaseAnalyzer $codebaseAnalyzer
     * @param DatabaseAnalyzer $databaseAnalyzer
     * @param FrontendAnalyzer $frontendAnalyzer
     * @param IndexerCronAnalyzer $indexerCronAnalyzer
     * @param ThirdPartyAnalyzer $thirdPartyAnalyzer
     * @param ApiAnalyzer $apiAnalyzer
     * @param ReportGenerator $reportGenerator
     * @param IssueFactory $issueFactory
     * @param LoggerInterface $logger
     * @param string|null $name
     */
    public function __construct(
        ConfigurationCheckerInterface $configurationChecker,
        ModuleAnalyzer $moduleAnalyzer,
        CodebaseAnalyzer $codebaseAnalyzer,
        DatabaseAnalyzer $databaseAnalyzer,
        FrontendAnalyzer $frontendAnalyzer,
        IndexerCronAnalyzer $indexerCronAnalyzer,
        ThirdPartyAnalyzer $thirdPartyAnalyzer,
        ApiAnalyzer $apiAnalyzer,
        ReportGenerator $reportGenerator,
        IssueFactory $issueFactory,
        LoggerInterface $logger,
        string $name = null
    ) {
        $this->configurationChecker = $configurationChecker;
        $this->moduleAnalyzer = $moduleAnalyzer;
        $this->codebaseAnalyzer = $codebaseAnalyzer;
        $this->databaseAnalyzer = $databaseAnalyzer;
        $this->frontendAnalyzer = $frontendAnalyzer;
        $this->indexerCronAnalyzer = $indexerCronAnalyzer;
        $this->thirdPartyAnalyzer = $thirdPartyAnalyzer;
        $this->apiAnalyzer = $apiAnalyzer;
        $this->reportGenerator = $reportGenerator;
        $this->issueFactory = $issueFactory;
        $this->logger = $logger;
        parent::__construct($name);
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
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
                'Run review for specific category only (config, modules, codebase, database, frontend, indexing, thirdparty, api)'
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
                'Show detailed information for issues (e.g., list of modules)'
            );
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $startTime = microtime(true);
        
        $output->writeln('<info>Starting Magento 2 Performance Review...</info>');
        $output->writeln('');
        
        $issues = [];
        $category = $input->getOption('category');
        
        try {
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
                $moduleIssues = $this->convertArraysToIssues($this->moduleAnalyzer->analyzeModules());
                $issues = array_merge($issues, $moduleIssues);
                $output->writeln('<info>✓</info>');
            }
            
            // Run codebase analysis
            if (!$category || $category === 'codebase') {
                $output->write('Analyzing codebase... ');
                $codebaseIssues = $this->convertArraysToIssues($this->codebaseAnalyzer->analyzeCodebase());
                $issues = array_merge($issues, $codebaseIssues);
                $output->writeln('<info>✓</info>');
            }
            
            // Run database analysis
            if (!$category || $category === 'database') {
                $output->write('Analyzing database... ');
                $databaseIssues = $this->databaseAnalyzer->analyzeDatabase();
                $issues = array_merge($issues, $databaseIssues);
                $output->writeln('<info>✓</info>');
            }
            
            // Run frontend analysis
            if (!$category || $category === 'frontend') {
                $output->write('Analyzing frontend configuration... ');
                $frontendIssues = $this->convertArraysToIssues($this->frontendAnalyzer->analyzeFrontend());
                $issues = array_merge($issues, $frontendIssues);
                $output->writeln('<info>✓</info>');
            }
            
            // Run indexer and cron analysis
            if (!$category || $category === 'indexing') {
                $output->write('Analyzing indexers and cron... ');
                $indexerCronIssues = $this->convertArraysToIssues($this->indexerCronAnalyzer->analyzeIndexerCron());
                $issues = array_merge($issues, $indexerCronIssues);
                $output->writeln('<info>✓</info>');
            }
            
            // Run third-party extension analysis
            if (!$category || $category === 'thirdparty') {
                $output->write('Analyzing third-party extensions... ');
                $thirdPartyIssues = $this->convertArraysToIssues($this->thirdPartyAnalyzer->analyzeThirdPartyExtensions());
                $issues = array_merge($issues, $thirdPartyIssues);
                $output->writeln('<info>✓</info>');
            }
            
            // Run API analysis
            if (!$category || $category === 'api') {
                $output->write('Analyzing API configuration... ');
                $apiIssues = $this->convertArraysToIssues($this->apiAnalyzer->analyzeApi());
                $issues = array_merge($issues, $apiIssues);
                $output->writeln('<info>✓</info>');
            }
        } catch (\Exception $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            $this->logger->error('Performance review failed', ['exception' => $e]);
            return self::EXIT_CODE_FAILURE;
        }
        
        $output->writeln('');
        
        // Generate report
        $showDetails = $input->getOption('details');
        $report = $this->reportGenerator->generateReport($issues, $showDetails);
        
        // Handle output
        $outputFile = $input->getOption('output-file');
        if ($outputFile) {
            try {
                file_put_contents($outputFile, $report);
                $output->writeln("<info>Report saved to: $outputFile</info>");
            } catch (\Exception $e) {
                $output->writeln('<error>Failed to save report: ' . $e->getMessage() . '</error>');
                return self::EXIT_CODE_FAILURE;
            }
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
        $highPriorityCount = $this->countHighPriorityIssues($issues);
        
        if ($highPriorityCount > 0) {
            $output->writeln("<error>Found {$highPriorityCount} high priority issues that should be addressed.</error>");
            return self::EXIT_CODE_FAILURE;
        }
        
        return self::EXIT_CODE_SUCCESS;
    }

    /**
     * Convert array issues to IssueInterface objects
     *
     * @param array $arrayIssues
     * @return IssueInterface[]
     */
    private function convertArraysToIssues(array $arrayIssues): array
    {
        $issues = [];
        foreach ($arrayIssues as $arrayIssue) {
            if (is_array($arrayIssue)) {
                $issues[] = $this->issueFactory->create($arrayIssue);
            } elseif ($arrayIssue instanceof IssueInterface) {
                $issues[] = $arrayIssue;
            }
        }
        return $issues;
    }

    /**
     * Count high priority issues
     *
     * @param IssueInterface[] $issues
     * @return int
     */
    private function countHighPriorityIssues(array $issues): int
    {
        $count = 0;
        foreach ($issues as $issue) {
            if ($issue->getPriority() === IssueInterface::PRIORITY_HIGH) {
                $count++;
            }
        }
        return $count;
    }
}