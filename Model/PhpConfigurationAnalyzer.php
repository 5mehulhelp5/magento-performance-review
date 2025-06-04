<?php
/**
 * Copyright © Performance, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Performance\Review\Model;

use Performance\Review\Api\PhpConfigurationAnalyzerInterface;
use Performance\Review\Api\Data\IssueInterface;
use Performance\Review\Model\IssueFactory;
use Performance\Review\Util\ByteConverter;
use Psr\Log\LoggerInterface;

/**
 * PHP configuration analyzer for performance review
 *
 * @since 1.0.0
 */
class PhpConfigurationAnalyzer implements PhpConfigurationAnalyzerInterface
{
    /**
     * Recommended PHP settings for Magento 2.4.8
     */
    private const RECOMMENDED_SETTINGS = [
        'memory_limit' => ['value' => '2048M', 'min' => 2147483648, 'priority' => IssueInterface::PRIORITY_HIGH],
        'max_execution_time' => ['value' => '18000', 'min' => 18000, 'priority' => IssueInterface::PRIORITY_HIGH],
        'max_input_vars' => ['value' => '75000', 'min' => 75000, 'priority' => IssueInterface::PRIORITY_MEDIUM],
        'post_max_size' => ['value' => '64M', 'min' => 67108864, 'priority' => IssueInterface::PRIORITY_MEDIUM],
        'upload_max_filesize' => ['value' => '64M', 'min' => 67108864, 'priority' => IssueInterface::PRIORITY_MEDIUM],
        'realpath_cache_size' => ['value' => '10M', 'min' => 10485760, 'priority' => IssueInterface::PRIORITY_HIGH],
        'realpath_cache_ttl' => ['value' => '86400', 'min' => 86400, 'priority' => IssueInterface::PRIORITY_MEDIUM],
    ];

    /**
     * OPcache settings for optimal performance
     */
    private const OPCACHE_SETTINGS = [
        'opcache.enable' => ['value' => '1', 'expected' => '1', 'priority' => IssueInterface::PRIORITY_HIGH],
        'opcache.memory_consumption' => ['value' => '512', 'min' => 512, 'priority' => IssueInterface::PRIORITY_HIGH],
        'opcache.max_accelerated_files' => ['value' => '130000', 'min' => 130000, 'priority' => IssueInterface::PRIORITY_HIGH],
        'opcache.validate_timestamps' => ['value' => '0', 'expected' => '0', 'priority' => IssueInterface::PRIORITY_HIGH, 'production_only' => true],
        'opcache.revalidate_freq' => ['value' => '0', 'expected' => '0', 'priority' => IssueInterface::PRIORITY_MEDIUM],
        'opcache.interned_strings_buffer' => ['value' => '32', 'min' => 32, 'priority' => IssueInterface::PRIORITY_MEDIUM],
        'opcache.fast_shutdown' => ['value' => '1', 'expected' => '1', 'priority' => IssueInterface::PRIORITY_LOW],
        'opcache.enable_file_override' => ['value' => '1', 'expected' => '1', 'priority' => IssueInterface::PRIORITY_LOW],
        'opcache.huge_code_pages' => ['value' => '1', 'expected' => '1', 'priority' => IssueInterface::PRIORITY_LOW],
    ];

    /**
     * @var IssueFactory
     */
    private IssueFactory $issueFactory;

    /**
     * @var ByteConverter
     */
    private ByteConverter $byteConverter;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * Constructor
     *
     * @param IssueFactory $issueFactory
     * @param ByteConverter $byteConverter
     * @param LoggerInterface $logger
     */
    public function __construct(
        IssueFactory $issueFactory,
        ByteConverter $byteConverter,
        LoggerInterface $logger
    ) {
        $this->issueFactory = $issueFactory;
        $this->byteConverter = $byteConverter;
        $this->logger = $logger;
    }

    /**
     * Analyze PHP configuration for performance issues
     *
     * @return IssueInterface[]
     */
    public function analyzePHPConfiguration(): array
    {
        $issues = [];

        try {
            // Check PHP version
            $versionIssues = $this->checkPhpVersion();
            if (!empty($versionIssues)) {
                $issues = array_merge($issues, $versionIssues);
            }

            // Check general PHP settings
            $settingsIssues = $this->checkPhpSettings();
            if (!empty($settingsIssues)) {
                $issues = array_merge($issues, $settingsIssues);
            }

            // Check OPcache configuration
            $opcacheIssues = $this->checkOpcacheSettings();
            if (!empty($opcacheIssues)) {
                $issues = array_merge($issues, $opcacheIssues);
            }

            // Check for problematic extensions
            $extensionIssues = $this->checkProblematicExtensions();
            if (!empty($extensionIssues)) {
                $issues = array_merge($issues, $extensionIssues);
            }
        } catch (\Exception $e) {
            $this->logger->error('PHP configuration analysis failed: ' . $e->getMessage());
        }

        return $issues;
    }

    /**
     * Check PHP version compatibility
     *
     * @return IssueInterface[]
     */
    private function checkPhpVersion(): array
    {
        $issues = [];
        $currentVersion = PHP_VERSION;
        $versionParts = explode('.', $currentVersion);
        $majorMinor = $versionParts[0] . '.' . $versionParts[1];

        // Magento 2.4.8 supports PHP 8.1, 8.2, and 8.3
        $supportedVersions = ['8.1', '8.2', '8.3'];
        
        if (!in_array($majorMinor, $supportedVersions)) {
            $issues[] = $this->issueFactory->create([
                'priority' => IssueInterface::PRIORITY_HIGH,
                'category' => 'PHP Config',
                'issue' => 'PHP version not optimal for Magento 2.4.8',
                'details' => sprintf(
                    'Current PHP version %s. Magento 2.4.8 supports PHP %s',
                    $currentVersion,
                    implode(', ', $supportedVersions)
                ),
                'current_value' => $currentVersion,
                'recommended_value' => 'PHP 8.2 (recommended) or 8.3'
            ]);
        } elseif ($majorMinor === '8.1') {
            $issues[] = $this->issueFactory->create([
                'priority' => IssueInterface::PRIORITY_LOW,
                'category' => 'PHP Config',
                'issue' => 'Consider upgrading to PHP 8.2 or 8.3',
                'details' => 'PHP 8.2 and 8.3 offer better performance improvements',
                'current_value' => $currentVersion,
                'recommended_value' => 'PHP 8.2 or 8.3'
            ]);
        }

        return $issues;
    }

    /**
     * Check general PHP settings
     *
     * @return IssueInterface[]
     */
    private function checkPhpSettings(): array
    {
        $issues = [];

        foreach (self::RECOMMENDED_SETTINGS as $setting => $config) {
            $currentValue = ini_get($setting);
            
            if ($currentValue === false) {
                continue; // Setting doesn't exist
            }

            $currentBytes = $this->byteConverter->convertToBytes($currentValue);
            
            if ($currentBytes < $config['min']) {
                $issues[] = $this->issueFactory->create([
                    'priority' => $config['priority'],
                    'category' => 'PHP Config',
                    'issue' => sprintf('Increase PHP %s', str_replace('_', ' ', $setting)),
                    'details' => $this->getSettingDescription($setting),
                    'current_value' => $currentValue,
                    'recommended_value' => $config['value']
                ]);
            }
        }

        return $issues;
    }

    /**
     * Check OPcache settings
     *
     * @return IssueInterface[]
     */
    private function checkOpcacheSettings(): array
    {
        $issues = [];

        // Check if OPcache is loaded
        if (!extension_loaded('Zend OPcache')) {
            $issues[] = $this->issueFactory->create([
                'priority' => IssueInterface::PRIORITY_HIGH,
                'category' => 'PHP Config',
                'issue' => 'Enable OPcache extension',
                'details' => 'OPcache is not loaded. This extension is critical for PHP performance.',
                'current_value' => 'Not installed',
                'recommended_value' => 'Enabled'
            ]);
            return $issues;
        }

        // Check OPcache settings
        foreach (self::OPCACHE_SETTINGS as $setting => $config) {
            $currentValue = ini_get($setting);
            
            if ($currentValue === false) {
                continue; // Setting doesn't exist
            }

            // Skip production-only checks if not in production
            if (isset($config['production_only']) && $config['production_only']) {
                // Check if we're in production mode (you might want to inject State to check this properly)
                continue;
            }

            $failed = false;
            if (isset($config['expected'])) {
                $failed = $currentValue !== $config['expected'];
            } elseif (isset($config['min'])) {
                $failed = (int)$currentValue < $config['min'];
            }

            if ($failed) {
                $issues[] = $this->issueFactory->create([
                    'priority' => $config['priority'],
                    'category' => 'PHP Config',
                    'issue' => sprintf('Optimize %s', $setting),
                    'details' => $this->getOpcacheSettingDescription($setting),
                    'current_value' => $currentValue,
                    'recommended_value' => $config['value']
                ]);
            }
        }

        return $issues;
    }

    /**
     * Check for problematic PHP extensions
     *
     * @return IssueInterface[]
     */
    private function checkProblematicExtensions(): array
    {
        $issues = [];

        // Check for ionCube Loader (known to cause issues)
        if (extension_loaded('ionCube Loader')) {
            $issues[] = $this->issueFactory->create([
                'priority' => IssueInterface::PRIORITY_MEDIUM,
                'category' => 'PHP Config',
                'issue' => 'ionCube Loader detected',
                'details' => 'ionCube Loader can cause performance issues and conflicts with OPcache',
                'current_value' => 'Enabled',
                'recommended_value' => 'Disabled unless required'
            ]);
        }

        // Check for Xdebug in production
        if (extension_loaded('xdebug')) {
            $issues[] = $this->issueFactory->create([
                'priority' => IssueInterface::PRIORITY_HIGH,
                'category' => 'PHP Config',
                'issue' => 'Xdebug enabled',
                'details' => 'Xdebug significantly impacts performance and should be disabled in production',
                'current_value' => 'Enabled',
                'recommended_value' => 'Disabled in production'
            ]);
        }

        return $issues;
    }


    /**
     * Get description for PHP setting
     *
     * @param string $setting
     * @return string
     */
    private function getSettingDescription(string $setting): string
    {
        $descriptions = [
            'memory_limit' => 'Insufficient memory limit can cause out of memory errors during heavy operations',
            'max_execution_time' => 'Low execution time can cause timeouts during import/export or reindexing',
            'max_input_vars' => 'Too low can cause issues with large forms and configurations',
            'post_max_size' => 'Limits the size of POST data, affecting file uploads and API calls',
            'upload_max_filesize' => 'Limits file upload size for products, images, and imports',
            'realpath_cache_size' => 'Larger cache improves file system performance',
            'realpath_cache_ttl' => 'Longer TTL reduces file system calls',
        ];

        return $descriptions[$setting] ?? 'This setting affects PHP performance';
    }

    /**
     * Get description for OPcache setting
     *
     * @param string $setting
     * @return string
     */
    private function getOpcacheSettingDescription(string $setting): string
    {
        $descriptions = [
            'opcache.enable' => 'OPcache must be enabled for optimal PHP performance',
            'opcache.memory_consumption' => 'More memory allows caching more PHP files',
            'opcache.max_accelerated_files' => 'Magento has many PHP files, this should be set high',
            'opcache.validate_timestamps' => 'Disable in production to avoid file system checks',
            'opcache.revalidate_freq' => 'How often to check for file changes',
            'opcache.interned_strings_buffer' => 'Memory for interned strings optimization',
            'opcache.fast_shutdown' => 'Enables faster shutdown sequence',
            'opcache.enable_file_override' => 'Optimizes file_exists and is_file functions',
            'opcache.huge_code_pages' => 'Can improve performance on systems with huge page support',
        ];

        return $descriptions[$setting] ?? 'This OPcache setting affects performance';
    }

    /**
     * @inheritdoc
     */
    public function analyze(): array
    {
        return $this->analyzePHPConfiguration();
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return 'PHP Configuration Analyzer';
    }

    /**
     * @inheritdoc
     */
    public function getCategory(): string
    {
        return 'PHP Config';
    }
}