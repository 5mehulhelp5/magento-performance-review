#!/usr/bin/env php
<?php
/**
 * Build script to create magento-performance-review.phar
 */

$buildRoot = __DIR__;
$pharFile = $buildRoot . '/magento-performance-review.phar';

// Remove existing phar if it exists
if (file_exists($pharFile)) {
    unlink($pharFile);
}

// Check if composer dependencies are installed
if (!file_exists($buildRoot . '/vendor/autoload.php')) {
    echo "Installing composer dependencies...\n";
    passthru("cd $buildRoot && composer install --no-dev --optimize-autoloader");
}

try {
    $phar = new Phar($pharFile, 0, 'magento-performance-review.phar');
    
    // Start buffering
    $phar->startBuffering();
    
    // Add all PHP files
    $phar->buildFromDirectory($buildRoot, '/\.(php|json)$/');
    
    // Set the stub
    $stub = <<<'STUB'
#!/usr/bin/env php
<?php
Phar::mapPhar('magento-performance-review.phar');
require 'phar://magento-performance-review.phar/bin/magento-performance-review';
__HALT_COMPILER();
STUB;
    
    $phar->setStub($stub);
    
    // Stop buffering and write changes
    $phar->stopBuffering();
    
    // Make the phar executable
    chmod($pharFile, 0755);
    
    echo "Successfully created: $pharFile\n";
    echo "File size: " . number_format(filesize($pharFile)) . " bytes\n";
    echo "\nUsage:\n";
    echo "  php $pharFile [options]\n";
    echo "  php $pharFile --magento-root=/path/to/magento\n";
    echo "  php $pharFile --category=config --output-file=report.txt\n";
    
} catch (Exception $e) {
    echo "Error creating phar: " . $e->getMessage() . "\n";
    exit(1);
}