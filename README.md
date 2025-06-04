# Magento 2 Performance Review Tool

A comprehensive performance analysis tool for Magento 2.4.8 that automatically identifies configuration issues, problematic modules, infrastructure optimization opportunities, and codebase improvements.

## Overview

This tool performs an automated analysis of your Magento 2 installation to identify performance bottlenecks and provide actionable recommendations. It examines eleven key areas:

1. **Configuration** - Deployment mode, caching backends, and system settings
2. **PHP Configuration** - PHP version, memory limits, OPcache settings, realpath cache
3. **MySQL Configuration** - InnoDB settings, buffer pool, query optimization
4. **Redis Configuration** - Cache backend, session storage, eviction policies
5. **Database** - Table sizes, catalog size, log tables, URL rewrites
6. **Indexing & Cron** - Indexer status, cron jobs, stuck processes
7. **Frontend** - JS/CSS optimization, image formats, CDN usage
8. **Modules** - Module count (excluding core), performance impact, and redundancy
9. **Third-party Extensions** - Compatibility, conflicts, known issues
10. **Codebase** - Custom code volume, event observers, plugins, and preferences
11. **API** - Rate limiting, authentication, GraphQL/REST configuration

## Features

- **Automated Analysis**: Single command to analyze your entire Magento installation
- **Priority-based Reporting**: Issues categorized as High, Medium, or Low priority
- **Colored Output**: Visual indicators for different priority levels (red, yellow, green)
- **Flexible Output**: View in terminal or save to file
- **Category Filtering**: Run specific checks only
- **CI/CD Ready**: Exit codes based on issue severity

## Installation

1. The module is located in `app/code/Performance/Review`
2. Enable the module:
   ```bash
   php bin/magento module:enable Performance_Review
   php bin/magento setup:upgrade
   php bin/magento setup:di:compile
   ```

## Usage

### Basic Usage
Run the complete performance review:
```bash
php bin/magento performance:review
```

### Command Options

| Option | Description | Example |
|--------|-------------|---------|
| `--output-file` or `-o` | Save report to file | `--output-file=report.txt` |
| `--category` or `-c` | Run specific category only | `--category=database` |
| `--no-color` | Disable colored output | `--no-color` |
| `--details` | Show detailed information (e.g., module list) | `--details` |

Available categories: `config`, `php`, `mysql`, `redis`, `database`, `frontend`, `indexing`, `modules`, `thirdparty`, `codebase`, `api`

### Examples

```bash
# Save report to file
php bin/magento performance:review --output-file=performance-report.txt

# Check only configuration
php bin/magento performance:review --category=config

# Check PHP configuration
php bin/magento performance:review --category=php

# Check MySQL configuration
php bin/magento performance:review --category=mysql

# Check Redis configuration
php bin/magento performance:review --category=redis

# Check only modules with detailed list
php bin/magento performance:review --category=modules --details

# Check only codebase
php bin/magento performance:review --category=codebase

# Save report without colors (useful for CI/CD)
php bin/magento performance:review --output-file=report.txt --no-color
```

## What It Checks

### Configuration Checks
- **Deployment Mode**: Warns if running in developer mode
- **Redis Configuration**: Checks if Redis is configured for caching
- **Cache Status**: Verifies all cache types are enabled

### PHP Configuration Checks
- **PHP Version**: Validates PHP version compatibility (8.1, 8.2, 8.3 for Magento 2.4.8)
- **Memory Settings**: memory_limit, max_execution_time, max_input_vars, upload sizes
- **Realpath Cache**: Size and TTL for file system performance
- **OPcache Settings**: Memory consumption, max files, validation settings
- **Problematic Extensions**: Detects Xdebug, ionCube Loader in production

### MySQL Configuration Checks
- **Version Check**: MySQL 8.0+ or MariaDB 10.4+ recommended
- **InnoDB Settings**: Buffer pool size (70% RAM), thread concurrency, log file size
- **Connection Settings**: max_connections, thread_cache_size, table_open_cache
- **Query Cache**: Warns if enabled (deprecated in MySQL 8.0)
- **Performance Schema**: Suggests disabling in production for overhead reduction
- **Storage Engine**: Ensures all tables use InnoDB

### Redis Configuration Checks
- **Connection**: Validates Redis server accessibility
- **Version**: Redis 6.0+ or 7.0+ recommended
- **Memory Settings**: maxmemory and eviction policies
- **Persistence**: AOF and RDB settings for data durability
- **Performance**: TCP settings, timeout values, database count

### Database Checks
- **Database Size**: Warns if database exceeds 20GB/50GB
- **Table Sizes**: Identifies tables larger than 1GB
- **Product/Category Count**: Warns if catalog is very large (>100k products)
- **Flat Tables**: Checks if flat tables are appropriate for catalog size
- **Log Tables**: Identifies bloated log tables (report_event, customer_log, etc.)
- **URL Rewrites**: Warns if URL rewrite table has excessive entries

### Indexing & Cron Checks
- **Invalid Indexers**: Identifies indexers that need reindexing
- **Indexer Mode**: Warns if indexers are in "Update on Save" mode
- **Cron Status**: Checks if cron is running regularly
- **Stuck Jobs**: Identifies cron jobs running for >2 hours
- **Pending Jobs**: Warns if too many jobs are queued
- **Error Rate**: Monitors cron job failures

### Frontend Checks
- **JavaScript**: Bundling, minification, and merging settings
- **CSS**: Minification, merging, and critical CSS configuration
- **Images**: WebP support and lazy loading
- **Static Content**: Signing for cache busting
- **Full Page Cache**: Varnish configuration and TTL
- **CDN**: Checks if CDN is configured for static/media files

### Module Analysis
- **Module Count**: Warns if more than 200 non-core modules are active (excludes Magento core modules)
- **Performance Impact**: Identifies known performance-impacting modules
- **Disabled Modules**: Finds modules that are disabled but not removed
- **Duplicate Functionality**: Detects multiple modules serving similar purposes
- **Detailed View**: Use `--details` flag to see the complete list of modules

### Third-party Extension Checks
- **Extension Count**: Warns if >30-50 third-party extensions
- **Known Issues**: Identifies extensions with known performance problems
- **Conflicts**: Detects multiple extensions for same functionality
- **Compatibility**: Checks version compatibility with Magento
- **Code Quality**: Identifies outdated coding practices

### Codebase Analysis
- **Custom Code Volume**: Warns if app/code contains >5000 files
- **Event Observers**: Warns if >100 observers configured
- **Plugins**: Warns if >200 plugins configured
- **Preferences**: Warns if >50 class preferences configured

### API Checks
- **Rate Limiting**: Ensures API rate limits are configured
- **Authentication**: Checks OAuth token management
- **GraphQL**: Query depth and complexity limits
- **REST API**: Page size limits
- **Caching**: API response caching configuration

## Understanding the Report

### Priority Levels

- **High** (Red): Critical issues that significantly impact performance
- **Medium** (Yellow): Important issues that should be reviewed
- **Low** (Green): Minor optimizations that can be addressed later

### Exit Codes

- `0` - Success (no high priority issues found)
- `1` - Failure (high priority issues found)

This makes the tool suitable for CI/CD pipelines where you want builds to fail if critical performance issues are detected.

## Example Output

```
================================================================================
                    MAGENTO 2 PERFORMANCE REVIEW REPORT
================================================================================
Generated: 2025-06-03 11:16:55
================================================================================

== Config ==
--------------------------------------------------------------------------------
Priority   | Recommendation                           | Details                  
----------+------------------------------------------+---------------------------
High       | Switch from developer mode to product... | Developer mode significa...
           |                                          | be used in production.
           |                                          | Current: developer
           |                                          | Recommended: production
----------+------------------------------------------+---------------------------
High       | Configure Redis for cache storage        | Using Redis for cache ...
           |                                          | performance.
           |                                          | Current: File-based cache
           |                                          | Recommended: Redis cache backend
----------+------------------------------------------+---------------------------

== Modules ==
--------------------------------------------------------------------------------
Priority   | Recommendation                           | Details                  
----------+------------------------------------------+---------------------------
Medium     | Review and reduce the number of activ... | You have 363 active mod...
           |                                          | performance.
           |                                          | Current: 363 modules
           |                                          | Recommended: Less than 200 modules
----------+------------------------------------------+---------------------------

== Summary ==
================================================================================

Total Issues Found: 6

  High    : 2 issues
  Medium  : 3 issues
  Low     : 1 issue

Recommended Actions:
1. Address all High priority issues first
2. Review Medium priority issues based on your specific use case
3. Consider Low priority issues for optimization
```

## Recommendations

### Addressing High Priority Issues

1. **Switch to Production Mode**
   ```bash
   php bin/magento deploy:mode:set production
   ```

2. **Configure Redis**
   ```bash
   # Install Redis
   sudo apt-get install redis-server
   
   # Configure Magento to use Redis
   php bin/magento setup:config:set --cache-backend=redis --cache-backend-redis-server=127.0.0.1 --cache-backend-redis-db=0
   ```

3. **Optimize PHP Configuration**
   ```bash
   # Edit php.ini
   memory_limit = 2048M
   max_execution_time = 18000
   realpath_cache_size = 10M
   realpath_cache_ttl = 86400
   
   # OPcache settings
   opcache.enable = 1
   opcache.memory_consumption = 512
   opcache.max_accelerated_files = 130000
   opcache.validate_timestamps = 0  # Production only
   opcache.interned_strings_buffer = 32
   ```

4. **Optimize MySQL Configuration**
   ```bash
   # Edit my.cnf or my.ini
   [mysqld]
   innodb_buffer_pool_size = 70% of RAM  # e.g., 8G for 12GB RAM
   innodb_thread_concurrency = 0
   innodb_flush_log_at_trx_commit = 2
   innodb_log_file_size = 512M
   innodb_buffer_pool_instances = 8
   max_connections = 1000
   thread_cache_size = 100
   table_open_cache = 8000
   ```

5. **Fix Invalid Indexers**
   ```bash
   php bin/magento indexer:reindex
   php bin/magento indexer:set-mode schedule
   ```

6. **Enable Frontend Optimizations**
   ```bash
   # Enable JS/CSS optimizations
   php bin/magento config:set dev/js/enable_js_bundling 1
   php bin/magento config:set dev/js/minify_files 1
   php bin/magento config:set dev/css/minify_files 1
   ```

7. **Clean Database Tables**
   ```bash
   # Clean log tables
   php bin/magento log:clean --days 7
   
   # Clean old URL rewrites
   DELETE FROM url_rewrite WHERE is_autogenerated = 1 AND entity_type = 'product';
   ```

### Addressing Medium Priority Issues

1. **Review Active Modules**
   - List all modules: `php bin/magento module:status`
   - List only non-core modules: `php bin/magento performance:review --category=modules --details`
   - Disable unnecessary modules: `php bin/magento module:disable Vendor_Module`

2. **Optimize Heavy Modules**
   - Consider if you really need Google Analytics/Optimizer
   - Review Elasticsearch usage vs. MySQL search
   - Optimize layered navigation attributes

3. **Clean Up Code**
   - Review and consolidate event observers
   - Replace preferences with plugins where possible
   - Minimize plugin usage

### Addressing Low Priority Issues

1. **Remove Disabled Modules**
   ```bash
   # After disabling modules, remove their code
   composer remove vendor/module
   ```

## Common Production Issues Addressed

Based on real-world Magento 2 deployments, this tool focuses on the most common performance problems:

1. **Database Performance** (30% of issues)
   - Oversized log tables
   - Excessive URL rewrites
   - Large catalog without proper indexing

2. **Frontend Performance** (25% of issues)
   - Missing JS/CSS optimization
   - No CDN configuration
   - Large unoptimized images

3. **Indexing & Cron** (20% of issues)
   - Stuck cron jobs
   - Real-time indexers
   - Failed scheduled tasks

4. **Third-party Extensions** (15% of issues)
   - Conflicting extensions
   - Outdated/incompatible modules
   - Performance-heavy extensions

5. **API Performance** (10% of issues)
   - No rate limiting
   - Missing caching
   - Unoptimized queries

## Customization

The tool's thresholds can be modified by editing the analyzer classes in:
- `Model/ConfigurationChecker.php`
- `Model/PhpConfigurationAnalyzer.php`
- `Model/MysqlConfigurationAnalyzer.php`
- `Model/RedisConfigurationAnalyzer.php`
- `Model/ModuleAnalyzer.php`
- `Model/CodebaseAnalyzer.php`
- `Model/DatabaseAnalyzer.php`
- `Model/FrontendAnalyzer.php`
- `Model/IndexerCronAnalyzer.php`
- `Model/ThirdPartyAnalyzer.php`
- `Model/ApiAnalyzer.php`

## Support

For issues or feature requests, please create an issue in your project repository.

## License

This module is part of your Magento 2 installation and follows the same license terms.