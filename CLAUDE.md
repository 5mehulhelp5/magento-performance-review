# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Module Overview

The Performance Review module is a comprehensive Magento 2 performance analysis tool that automatically identifies configuration issues, problematic modules, infrastructure optimization opportunities, and codebase improvements across 11 key areas.

## Key Architecture

### Module Structure
- **Api/** - Interfaces for analyzers and data models
- **Console/Command/** - CLI command entry point (`PerformanceReviewCommand.php`)
- **Model/** - Concrete implementations of all analyzers
- **Test/Unit/** - PHPUnit tests with mocking
- **Util/** - Helper utilities (ByteConverter)

### Core Interfaces
- `AnalyzerInterface` - Base interface all analyzers implement
- `ConfigurationCheckerInterface` - Configuration checking contract
- `IssueInterface` - Performance issue data structure
- Specific analyzer interfaces for PHP, MySQL, Redis

### Analyzer Components (11 total)
1. **ConfigurationChecker** - Deployment mode, cache backends, cache status
2. **PhpConfigurationAnalyzer** - PHP version, memory, OPcache, realpath cache
3. **MysqlConfigurationAnalyzer** - MySQL/MariaDB settings, InnoDB configuration
4. **RedisConfigurationAnalyzer** - Redis cache/session configuration
5. **DatabaseAnalyzer** - Table sizes, log tables, catalog size
6. **IndexerCronAnalyzer** - Indexer status, cron jobs, stuck processes
7. **FrontendAnalyzer** - JS/CSS optimization, CDN, Varnish
8. **ModuleAnalyzer** - Module count, performance impact, conflicts
9. **ThirdPartyAnalyzer** - Extension compatibility and conflicts
10. **CodebaseAnalyzer** - Plugins, preferences, observers, layout updates
11. **ApiAnalyzer** - REST/GraphQL configuration, rate limiting

## Common Development Commands

### Running the Performance Review
```bash
# Full analysis
php bin/magento performance:review

# Save to file
php bin/magento performance:review --output-file=report.txt

# Run specific category
php bin/magento performance:review --category=database

# Show detailed information (e.g., module list)
php bin/magento performance:review --category=modules --details

# CI/CD mode (no colors)
php bin/magento performance:review --no-color
```

Available categories: `config`, `php`, `mysql`, `redis`, `database`, `frontend`, `indexing`, `modules`, `thirdparty`, `codebase`, `api`

### Testing Commands
```bash
# Run unit tests for this module
php vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Performance/Review/Test/Unit/

# Run specific test
php vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Performance/Review/Test/Unit/Model/ConfigurationCheckerTest.php

# Code standards check
vendor/bin/phpcs --standard=Magento2 app/code/Performance/Review/

# Static analysis
vendor/bin/phpstan analyse app/code/Performance/Review/
```

### Module Management
```bash
# Enable module
php bin/magento module:enable Performance_Review
php bin/magento setup:upgrade
php bin/magento setup:di:compile

# Disable module
php bin/magento module:disable Performance_Review
```

## Architectural Patterns

### Dependency Injection
All dependencies are injected via constructor. Key dependencies include:
- `LoggerInterface` - Custom logger writes to `var/log/performance_review.log`
- `IssueFactory` - Factory for creating Issue objects
- Various Magento core classes for accessing configuration and system state

### Command Pattern
`PerformanceReviewCommand` orchestrates all analyzers:
1. Validates category filter
2. Runs selected analyzers
3. Collects issues with priorities (High/Medium/Low)
4. Generates formatted report with color coding
5. Returns appropriate exit code for CI/CD

### Issue Reporting
Each analyzer returns an array of `IssueInterface` objects with:
- `priority` - High (red), Medium (yellow), Low (green)
- `recommendation` - Short actionable title
- `details` - Detailed explanation with current/recommended values

### Report Generation
`ReportGenerator` creates formatted output:
- Table format with priority indicators
- Color coding based on priority
- Summary statistics
- Exit code based on high priority issue count

## Development Guidelines

### Adding New Analyzers
1. Create interface in `Api/` if needed
2. Implement analyzer in `Model/` implementing `AnalyzerInterface`
3. Add to `CATEGORY_MAP` in `PerformanceReviewCommand`
4. Register in `di.xml` if special configuration needed
5. Write unit tests in `Test/Unit/Model/`

### Testing Patterns
Tests use PHPUnit with extensive mocking:
```php
$this->scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
$this->scopeConfigMock->expects($this->once())
    ->method('getValue')
    ->with('path/to/config')
    ->willReturn('value');
```

### Issue Priority Guidelines
- **High**: Critical performance impact, must fix for production
- **Medium**: Significant impact, should review based on use case
- **Low**: Minor optimization, nice to have

## Module Configuration

### Dependencies
Core Magento modules: Catalog, Backend, Config, Indexer, Integration

### Logging
Virtual logger type configured to write to `var/log/performance_review.log`

### ACL Resources
Admin resource: `Performance_Review::performance_review`