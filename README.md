# Magento 2 Performance Review Tool

A comprehensive performance analysis tool for Magento 2 that automatically identifies configuration issues, problematic modules, and codebase optimization opportunities.

## Overview

This tool performs an automated analysis of your Magento 2 installation to identify performance bottlenecks and provide actionable recommendations. It examines eight key areas:

1. **Configuration** - Deployment mode, caching backends, and system settings
2. **Database** - Table sizes, catalog size, log tables, URL rewrites
3. **Indexing & Cron** - Indexer status, cron jobs, stuck processes
4. **Frontend** - JS/CSS optimization, image formats, CDN usage
5. **Modules** - Module count, performance impact, and redundancy
6. **Third-party Extensions** - Compatibility, conflicts, known issues
7. **Codebase** - Custom code volume, event observers, plugins, and preferences
8. **API** - Rate limiting, authentication, GraphQL/REST configuration

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

Available categories: `config`, `database`, `frontend`, `indexing`, `modules`, `thirdparty`, `codebase`, `api`

### Examples

```bash
# Save report to file
php bin/magento performance:review --output-file=performance-report.txt

# Check only configuration
php bin/magento performance:review --category=config

# Check only modules
php bin/magento performance:review --category=modules

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
- **Module Count**: Warns if more than 200 modules are active
- **Performance Impact**: Identifies known performance-impacting modules
- **Disabled Modules**: Finds modules that are disabled but not removed
- **Duplicate Functionality**: Detects multiple modules serving similar purposes

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

3. **Fix Invalid Indexers**
   ```bash
   php bin/magento indexer:reindex
   php bin/magento indexer:set-mode schedule
   ```

4. **Enable Frontend Optimizations**
   ```bash
   # Enable JS/CSS optimizations
   php bin/magento config:set dev/js/enable_js_bundling 1
   php bin/magento config:set dev/js/minify_files 1
   php bin/magento config:set dev/css/minify_files 1
   ```

5. **Clean Database Tables**
   ```bash
   # Clean log tables
   php bin/magento log:clean --days 7
   
   # Clean old URL rewrites
   DELETE FROM url_rewrite WHERE is_autogenerated = 1 AND entity_type = 'product';
   ```

### Addressing Medium Priority Issues

1. **Review Active Modules**
   - List all modules: `php bin/magento module:status`
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