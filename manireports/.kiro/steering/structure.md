# Project Structure

## Directory Organization

```
local/manireports/
├── classes/                    # PHP classes (PSR-4 autoloaded)
│   ├── api/                   # Core business logic
│   │   ├── report_builder.php      # Report generation engine
│   │   ├── analytics_engine.php    # Engagement and risk calculations
│   │   ├── time_engine.php         # Time tracking coordinator
│   │   ├── scheduler.php           # Report scheduling
│   │   ├── iomad_filter.php        # Multi-tenant filtering
│   │   ├── export_engine.php       # CSV/XLSX/PDF export
│   │   ├── cache_manager.php       # Pre-aggregation & caching
│   │   └── audit_logger.php        # Audit trail logging
│   │
│   ├── charts/                # Chart generation
│   │   ├── base_chart.php          # Abstract base class
│   │   ├── line_chart.php          # Line chart implementation
│   │   ├── bar_chart.php           # Bar chart implementation
│   │   ├── pie_chart.php           # Pie chart implementation
│   │   └── chart_factory.php       # Factory for chart creation
│   │
│   ├── output/                # Rendering layer
│   │   ├── dashboard_renderer.php  # Dashboard rendering
│   │   ├── report_renderer.php     # Report table rendering
│   │   ├── widget_renderer.php     # Widget rendering
│   │   └── renderable/             # Renderable objects
│   │
│   ├── reports/               # Report implementations
│   │   ├── base_report.php         # Abstract report base
│   │   ├── course_completion.php   # Course completion report
│   │   ├── course_progress.php     # Course progress report
│   │   ├── scorm_summary.php       # SCORM analytics report
│   │   ├── user_engagement.php     # User engagement report
│   │   └── quiz_attempts.php       # Quiz attempts report
│   │
│   ├── tasks/                 # Scheduled background tasks
│   │   ├── cache_builder.php       # Pre-aggregation task
│   │   ├── time_aggregation.php    # Time tracking aggregation
│   │   ├── report_scheduler.php    # Scheduled report execution
│   │   ├── scorm_summary.php       # SCORM data aggregation
│   │   └── cleanup_old_data.php    # Data retention cleanup
│   │
│   ├── privacy/               # GDPR compliance
│   │   └── provider.php            # Privacy API implementation
│   │
│   └── external/              # Web service API
│       └── api.php                 # External API functions
│
├── db/                        # Database definitions
│   ├── install.xml            # Database schema
│   ├── access.php             # Capabilities definition
│   ├── tasks.php              # Scheduled tasks registration
│   ├── services.php           # Web services definition
│   └── upgrade.php            # Upgrade scripts
│
├── amd/                       # JavaScript (AMD modules)
│   ├── src/                   # Source files
│   │   ├── dashboard.js            # Dashboard interactions
│   │   ├── filters.js              # Filter handling
│   │   ├── charts.js               # Chart rendering
│   │   ├── heartbeat.js            # Time tracking heartbeat
│   │   ├── report_builder.js       # Custom report builder UI
│   │   └── ajax.js                 # AJAX utilities
│   └── build/                 # Compiled/minified (auto-generated)
│
├── templates/                 # Mustache templates
│   ├── dashboard_admin.mustache    # Admin dashboard
│   ├── dashboard_manager.mustache  # Manager dashboard
│   ├── dashboard_teacher.mustache  # Teacher dashboard
│   ├── dashboard_student.mustache  # Student dashboard
│   ├── report_table.mustache       # Report table view
│   ├── widget_kpi.mustache         # KPI widget
│   ├── widget_chart.mustache       # Chart widget
│   └── filters.mustache            # Filter controls
│
├── ui/                        # User interface entry points
│   ├── dashboard.php               # Dashboard entry point
│   ├── reports.php                 # Reports listing
│   ├── report_view.php             # Individual report view
│   ├── report_builder.php          # Custom report builder
│   ├── schedules.php               # Schedule management
│   ├── audit.php                   # Audit log viewer
│   └── ajax/                       # AJAX endpoints
│       ├── dashboard_data.php      # Dashboard data endpoint
│       ├── report_data.php         # Report data endpoint
│       └── heartbeat.php           # Time tracking endpoint
│
├── lang/                      # Language strings
│   └── en/
│       └── local_manireports.php   # English language pack
│
├── tests/                     # PHPUnit tests
│   ├── report_builder_test.php
│   ├── analytics_engine_test.php
│   ├── time_engine_test.php
│   └── iomad_filter_test.php
│
├── version.php                # Plugin version and metadata
├── settings.php               # Admin settings page
└── lib.php                    # Plugin lifecycle hooks
```

## Key Architectural Layers

### 1. Data Access Layer
- Moodle DML API (`$DB`) for all database operations
- Custom table classes in `classes/api/`
- No direct SQL in UI files

### 2. Business Logic Layer
- API classes in `classes/api/` handle core functionality
- Report classes in `classes/reports/` implement specific reports
- Task classes in `classes/tasks/` handle background processing

### 3. Presentation Layer
- Renderer classes in `classes/output/` prepare data for display
- Mustache templates in `templates/` define HTML structure
- AMD modules in `amd/src/` handle client-side interactions

### 4. UI Layer
- PHP files in `ui/` serve as entry points
- Perform capability checks and parameter validation
- Delegate to business logic and renderers

## File Naming Conventions

- **PHP Classes**: Lowercase with underscores (e.g., `report_builder.php`)
- **JavaScript**: Lowercase with underscores (e.g., `dashboard.js`)
- **Templates**: Lowercase with underscores (e.g., `dashboard_admin.mustache`)
- **Language Strings**: Plugin name (e.g., `local_manireports.php`)

## Namespace Structure

All classes use the namespace: `\local_manireports\{subnamespace}\{classname}`

Examples:
- `\local_manireports\api\report_builder`
- `\local_manireports\output\dashboard_renderer`
- `\local_manireports\task\time_aggregation`

## Configuration Files

- **version.php**: Plugin version, requires, component name
- **settings.php**: Admin configuration options
- **db/install.xml**: Initial database schema
- **db/access.php**: Capability definitions and default assignments
- **db/tasks.php**: Scheduled task definitions with default schedules

## Data Flow Patterns

### Dashboard Rendering
1. User accesses `ui/dashboard.php`
2. Capability check performed
3. Dashboard renderer instantiated
4. Data fetched via API classes
5. Template rendered with data
6. JavaScript initializes charts via AJAX

### Report Execution
1. User requests report via UI
2. Parameters validated
3. `report_builder` executes query
4. IOMAD filters applied automatically
5. Results paginated
6. Renderer formats output
7. Template displays results

### Time Tracking
1. JavaScript heartbeat sends signal every 20-30s
2. AJAX endpoint receives heartbeat
3. `time_engine` updates session record
4. Scheduled task aggregates sessions hourly
5. Daily summaries stored in database
6. Reports query aggregated data

### Scheduled Reports
1. Cron task runs every 15 minutes
2. `scheduler` identifies due schedules
3. Report executed with saved parameters
4. Export file generated
5. Email sent to recipients
6. Audit log entry created

## Enforced Directory Rules

- The directory structure must remain fixed; no new root folders
- All PHP business logic must stay inside `classes/`
- No PHP logic may be placed inside templates or JS
- All JS must be AMD modules in `amd/src/`
- No React, Vue, Angular, or other frameworks may be added

## IOMAD Filtering Rules

All reports must apply strict company isolation via `iomad_filter`:

- Company managers only see their own company data
- Teachers only see users enrolled in their courses
- Students only see their own data
- Admin sees everything

**This filtering is mandatory for every report.**

## Report Builder Contract

Every report class must:

- Extend `base_report.php`
- Implement:
  - `get_fields()`
  - `get_filters()`
  - `get_data()`
- Apply IOMAD filters
- Support pagination
- Support CSV/XLSX export
