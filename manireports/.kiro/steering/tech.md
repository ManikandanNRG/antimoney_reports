# Technical Stack

## Platform Requirements

- **Moodle**: 4.0 – 4.4 LTS
- **IOMAD**: 4.0 – 4.4 (optional)
- **PHP**: 7.4 – 8.2
- **Database**: MariaDB/MySQL (primary), PostgreSQL (supported)

## Plugin Type

Local plugin: `local/manireports`

## Core Technologies

### Backend
- **Moodle DML API**: All database operations
- **Moodle Task API**: Scheduled background jobs (cron)
- **Moodle File API**: Export file storage
- **Moodle Cache API**: Configuration and data caching
- **Moodle Privacy API**: GDPR compliance

### Frontend
- **Mustache**: Template engine (Moodle standard)
- **AMD JavaScript**: Modular JavaScript following Moodle patterns
- **Chart.js**: Client-side chart rendering
- **Bootstrap**: Responsive grid and UI components
- **AJAX**: Dynamic data loading without page reloads

### Libraries
- **PHPSpreadsheet**: XLSX export generation
- **mPDF or TCPDF**: PDF export generation

## Architecture Patterns

### Separation of Concerns
- `classes/api/`: Business logic and core functionality
- `classes/output/`: Rendering and presentation layer
- `classes/tasks/`: Background scheduled tasks
- `classes/reports/`: Report implementations
- `classes/charts/`: Chart generation
- `amd/src/`: JavaScript modules
- `templates/`: Mustache templates

### Key Design Patterns
- **Factory Pattern**: Chart creation (`chart_factory.php`)
- **Abstract Base Classes**: Reports and charts extend base classes
- **Dependency Injection**: Pass dependencies to constructors
- **Repository Pattern**: Data access through API classes

## Database Schema

### Custom Tables
- `manireports_customreports`: Custom report definitions
- `manireports_schedules`: Report scheduling configuration
- `manireports_schedule_recipients`: Schedule email recipients
- `manireports_report_runs`: Execution history and audit trail
- `manireports_usertime_sessions`: Active time tracking sessions
- `manireports_usertime_daily`: Aggregated daily time summaries
- `manireports_scorm_summary`: Pre-aggregated SCORM metrics
- `manireports_cache_summary`: Pre-computed report data
- `manireports_dashboards`: Custom dashboard configurations
- `manireports_dashboard_widgets`: Dashboard widget definitions
- `manireports_audit_logs`: Compliance and security audit trail

## Common Commands

### Development
```bash
# Purge caches during development
php admin/cli/purge_caches.php

# Run scheduled tasks manually
php admin/cli/scheduled_task.php --execute=\\local_manireports\\task\\time_aggregation

# Check code style
php local/codechecker/phpcs/bin/phpcs --standard=moodle local/manireports/
```

### Testing
```bash
# Run PHPUnit tests
php admin/tool/phpunit/cli/init.php
vendor/bin/phpunit --testsuite local_manireports_testsuite

# Run specific test
vendor/bin/phpunit local/manireports/tests/report_builder_test.php
```

### JavaScript
```bash
# Build AMD modules (from Moodle root)
npx grunt amd --root=local/manireports

# Watch for changes
npx grunt watch
```

## Performance Considerations

- **Indexing**: All foreign keys and frequently queried columns indexed
- **Pre-aggregation**: Heavy metrics computed by scheduled tasks
- **Caching**: Multi-level caching (application, query, session, browser)
- **Pagination**: All large result sets paginated
- **Query Optimization**: Use JOINs over subqueries, limit result sets

## Security Standards

- **Input Validation**: Use `required_param()` and `optional_param()` with PARAM_* types
- **SQL Safety**: Prepared statements with parameter binding for all queries
- **SQL Whitelist**: Custom reports restricted to SELECT on whitelisted tables
- **Output Encoding**: Use `s()`, `format_string()`, and proper escaping
- **Capability Checks**: Enforce on every page load and AJAX request
- **CSRF Protection**: Use `sesskey` parameter on all forms
- **Company Isolation**: Automatic filtering in IOMAD environments

## Coding Standards

Follow Moodle coding guidelines:
- PSR-1 and PSR-2 with Moodle-specific extensions
- PHPDoc comments on all classes and methods
- Namespace: `local_manireports`
- Class naming: `\local_manireports\api\report_builder`
- File naming: Lowercase with underscores
- Indentation: 4 spaces (no tabs)

## Coding Enforcement Rules

- Only Moodle DB API may be used for database queries
- No raw SQL in UI files; all queries must use parameter binding
- All HTML output must go through renderers and templates
- Chart.js is the only allowed charting library
- All export files must use Moodle's File API (filearea: `manireports_exports`)

## Cron & Scheduled Task Rules

Required cron tasks:
1. `cache_builder`
2. `report_scheduler`
3. `time_aggregation`
4. `scorm_summary`
5. `cleanup_old_data`

Each task must:
- Log start, finish, duration, and errors
- Fail gracefully without breaking cron
- Support safe re-running (idempotent)

## Database Schema Enforcement

The following tables are mandatory and may not be renamed or removed:
- `manireports_customreports`
- `manireports_schedules`
- `manireports_schedule_recipients`
- `manireports_report_runs`
- `manireports_usertime_sessions`
- `manireports_usertime_daily`
- `manireports_scorm_summary`
- `manireports_cache_summary`
- `manireports_dashboards`
- `manireports_dashboard_widgets`
- `manireports_audit_logs`

## Performance Requirements

- All heavy metrics must use pre-aggregation
- Cached JSON blobs should be used for charts
- All large result sets must be paginated
- All frequently filtered columns must be indexed

## Security Requirements

- Validate all inputs with Moodle PARAM_* types
- Enforce capability checks for all pages and AJAX endpoints
- Escape all output (`s()`, `format_string()`)
- SQL for custom reports must be restricted to whitelisted tables
