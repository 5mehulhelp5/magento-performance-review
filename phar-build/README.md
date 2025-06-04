# Magento Performance Review - Standalone PHAR Version

A standalone version of the Magento 2 Performance Review tool that can be run independently of a Magento installation.

## Building the PHAR

1. Install dependencies:
   ```bash
   composer install
   ```

2. Build the PHAR file:
   ```bash
   php build-phar.php
   ```

This will create `magento-performance-review.phar` in the current directory.

## Usage

### From any directory:
```bash
php /path/to/magento-performance-review.phar --magento-root=/path/to/magento
```

### From Magento root directory:
```bash
php /path/to/magento-performance-review.phar
```

### Options

- `--magento-root` or `-m`: Path to Magento root directory (default: current directory)
- `--category` or `-c`: Run specific category only (config, php, mysql, database, modules)
- `--output-file` or `-o`: Save report to file
- `--no-color`: Disable colored output
- `--details` or `-d`: Show detailed information

### Examples

```bash
# Full analysis
php magento-performance-review.phar --magento-root=/var/www/magento2

# Check only configuration
php magento-performance-review.phar --magento-root=/var/www/magento2 --category=config

# Save report to file
php magento-performance-review.phar --magento-root=/var/www/magento2 --output-file=report.txt

# CI/CD usage (no colors)
php magento-performance-review.phar --magento-root=/var/www/magento2 --no-color
```

## Available Analyzers

1. **config** - Deployment mode, cache backends, session storage
2. **php** - PHP version, memory limits, OPcache settings
3. **mysql** - MySQL/MariaDB version and configuration
4. **redis** - Redis configuration for cache and sessions
5. **database** - Database size, table analysis, catalog size
6. **modules** - Module count and performance impact
7. **codebase** - Code quality, plugins, preferences, observers
8. **frontend** - JS/CSS optimization, Varnish, static content
9. **indexing** - Indexer status, cron jobs, stuck processes
10. **thirdparty** - Third-party extension analysis
11. **api** - REST/GraphQL configuration, rate limiting

## Exit Codes

- `0` - Success (no high priority issues found)
- `1` - Failure (high priority issues found)

## Requirements

- PHP 7.4 or higher
- Access to Magento's `app/etc/env.php` file
- Database access (for database/mysql analyzers)

## Notes

This standalone version now includes all analyzers from the full Magento module:
- All configuration checks work independently
- Database-dependent analyzers require database access
- Some checks may have reduced functionality compared to the full module
- The tool provides comprehensive analysis without requiring Magento framework