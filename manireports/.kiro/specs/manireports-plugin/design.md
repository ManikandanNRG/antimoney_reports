# Design Document

## Overview

ManiReports is a Moodle local plugin (`local_manireports`) that provides comprehensive analytics and reporting capabilities for Moodle and IOMAD installations. The plugin architecture follows Moodle coding standards and leverages native Moodle APIs for database access, authentication, authorization, file handling, and task scheduling.

The design emphasizes modularity, performance, and security through:
- Separation of concerns with distinct API, output, and task layers
- Pre-aggregation and caching for heavy metrics
- Role-based access control using Moodle capabilities
- IOMAD multi-tenancy awareness at the query level
- Client-side rendering with AJAX for responsive UX

## Architecture

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                        User Interface                        │
│  (Mustache Templates + AMD JavaScript + Chart.js)           │
└─────────────────┬───────────────────────────────────────────┘
                  │
┌─────────────────▼───────────────────────────────────────────┐
│                    Presentation Layer                        │
│  - Dashboard Renderers                                       │
│  - Report Renderers                                          │
│  - AJAX Endpoints                                            │
└─────────────────┬───────────────────────────────────────────┘
                  │
┌─────────────────▼───────────────────────────────────────────┐
│                      Business Logic                          │
│  - Report Builder API                                        │
│  - Analytics Engine                                          │
│  - Time Tracking Engine                                      │
│  - Scheduler                                                 │
│  - IOMAD Filters                                             │
└─────────────────┬───────────────────────────────────────────┘
                  │
┌─────────────────▼───────────────────────────────────────────┐
│                      Data Access Layer                       │
│  - Moodle DML API ($DB)                                      │
│  - Custom Table Classes                                      │
│  - Cache API                                                 │
└─────────────────┬───────────────────────────────────────────┘
                  │
┌─────────────────▼───────────────────────────────────────────┐
│                         Database                             │
│  - Moodle Core Tables                                        │
│  - ManiReports Custom Tables                                 │
└─────────────────────────────────────────────────────────────┘
```

### Plugin Directory Structure


```
local/manireports/
├── classes/
│   ├── api/
│   │   ├── report_builder.php         # Core report generation engine
│   │   ├── analytics_engine.php       # Analytics calculations
│   │   ├── time_engine.php            # Time tracking logic
│   │   ├── scheduler.php              # Report scheduling
│   │   ├── iomad_filter.php           # Multi-tenant filtering
│   │   ├── export_engine.php          # CSV/XLSX/PDF export
│   │   └── cache_manager.php          # Pre-aggregation & caching
│   │
│   ├── charts/
│   │   ├── base_chart.php             # Abstract chart base class
│   │   ├── line_chart.php             # Line chart implementation
│   │   ├── bar_chart.php              # Bar chart implementation
│   │   ├── pie_chart.php              # Pie chart implementation
│   │   └── chart_factory.php          # Chart creation factory
│   │
│   ├── output/
│   │   ├── dashboard_renderer.php     # Dashboard rendering
│   │   ├── report_renderer.php        # Report table rendering
│   │   ├── widget_renderer.php        # Widget rendering
│   │   └── renderable/                # Renderable objects
│   │
│   ├── reports/
│   │   ├── base_report.php            # Abstract report base
│   │   ├── course_completion.php      # Course completion report
│   │   ├── course_progress.php        # Course progress report
│   │   ├── scorm_summary.php          # SCORM analytics report
│   │   ├── user_engagement.php        # User engagement report
│   │   └── quiz_attempts.php          # Quiz attempts report
│   │
│   ├── tasks/
│   │   ├── cache_builder.php          # Pre-aggregation task
│   │   ├── time_aggregation.php       # Time tracking aggregation
│   │   ├── report_scheduler.php       # Scheduled report execution
│   │   ├── scorm_summary.php          # SCORM data aggregation
│   │   └── cleanup_old_data.php       # Data retention cleanup
│   │
│   ├── privacy/
│   │   └── provider.php               # GDPR compliance
│   │
│   └── external/
│       └── api.php                    # Web service API
│
├── db/
│   ├── install.xml                    # Database schema
│   ├── access.php                     # Capabilities definition
│   ├── tasks.php                      # Scheduled tasks
│   ├── services.php                   # Web services
│   └── upgrade.php                    # Upgrade scripts
│
├── amd/
│   └── src/
│       ├── dashboard.js               # Dashboard interactions
│       ├── filters.js                 # Filter handling
│       ├── charts.js                  # Chart rendering
│       ├── heartbeat.js               # Time tracking heartbeat
│       ├── report_builder.js          # Custom report builder UI
│       └── ajax.js                    # AJAX utilities
│
├── templates/
│   ├── dashboard_admin.mustache       # Admin dashboard
│   ├── dashboard_manager.mustache     # Manager dashboard
│   ├── dashboard_teacher.mustache     # Teacher dashboard
│   ├── dashboard_student.mustache     # Student dashboard
│   ├── report_table.mustache          # Report table view
│   ├── widget_kpi.mustache            # KPI widget
│   ├── widget_chart.mustache          # Chart widget
│   └── filters.mustache               # Filter controls
│
├── ui/
│   ├── dashboard.php                  # Dashboard entry point
│   ├── reports.php                    # Reports listing
│   ├── report_view.php                # Individual report view
│   ├── report_builder.php             # Custom report builder
│   ├── schedules.php                  # Schedule management
│   └── audit.php                      # Audit log viewer
│
├── lang/
│   └── en/
│       └── local_manireports.php      # Language strings
│
├── version.php                        # Plugin version info
├── settings.php                       # Admin settings
└── lib.php                            # Plugin hooks
```

## Components and Interfaces

### 1. Report Builder API

**Purpose**: Core engine for generating reports from SQL queries or GUI configurations.

**Key Classes**:
- `report_builder`: Main report generation class
- `sql_validator`: Validates and sanitizes SQL queries
- `query_builder`: Builds SQL from GUI configurations
- `parameter_binder`: Safely binds parameters to queries

**Key Methods**:
```php
class report_builder {
    public function execute_report(int $reportid, array $params): array;
    public function validate_sql(string $sql): bool;
    public function build_from_config(object $config): string;
    public function apply_filters(string $sql, array $filters): string;
}
```

**Interfaces**:
- Input: Report ID or configuration object, parameters
- Output: Array of result rows, metadata (column names, types)
- Dependencies: Moodle DML API, iomad_filter, cache_manager



### 2. Analytics Engine

**Purpose**: Calculates engagement scores, at-risk indicators, and predictive metrics.

**Key Classes**:
- `analytics_engine`: Main analytics calculation class
- `engagement_calculator`: Computes engagement scores
- `risk_detector`: Identifies at-risk learners
- `rule_engine`: Evaluates configurable rules

**Key Methods**:
```php
class analytics_engine {
    public function calculate_engagement_score(int $userid, int $courseid): float;
    public function detect_at_risk_learners(array $filters): array;
    public function evaluate_rules(int $userid, array $rules): object;
    public function get_activity_metrics(int $userid, int $days): array;
}
```

**Engagement Score Calculation**:
```
Engagement Score = (
    time_spent_weight * normalized_time_spent +
    login_frequency_weight * normalized_login_frequency +
    activity_completion_weight * completion_percentage +
    interaction_weight * normalized_interactions
) * 100

Where weights sum to 1.0 and are configurable
```

**At-Risk Detection Rules**:
```
Risk Score = 0
IF time_spent < threshold_time THEN risk_score += 25
IF days_since_login > threshold_days THEN risk_score += 25
IF completion_percentage < threshold_completion THEN risk_score += 25
IF engagement_score < threshold_engagement THEN risk_score += 25

At-Risk = risk_score >= 50
```

### 3. Time Tracking Engine

**Purpose**: Records and aggregates user activity time using JavaScript heartbeat and log fallback.

**Key Classes**:
- `time_engine`: Main time tracking coordinator
- `heartbeat_handler`: Processes heartbeat signals
- `session_manager`: Manages active sessions
- `time_aggregator`: Aggregates sessions into daily summaries

**Key Methods**:
```php
class time_engine {
    public function record_heartbeat(int $userid, int $courseid, int $timestamp): void;
    public function close_inactive_sessions(int $timeout_seconds): void;
    public function aggregate_daily_time(string $date): void;
    public function get_user_time(int $userid, string $start_date, string $end_date): array;
}
```

**Heartbeat Flow**:
```
1. JavaScript sends heartbeat every 20-30 seconds (randomized)
2. Server receives heartbeat with userid, courseid, timestamp
3. If session exists and < 5 minutes old: update lastupdated
4. If session doesn't exist or > 5 minutes old: create new session
5. Cron task runs hourly to close sessions inactive > 10 minutes
6. Daily aggregation task sums session durations per user/course/day
```

**Session Table Structure**:
```
manireports_usertime_sessions:
- id
- userid
- courseid
- sessionstart (timestamp)
- lastupdated (timestamp)
- duration_seconds (computed on close)
```

**Daily Summary Table Structure**:
```
manireports_usertime_daily:
- id
- userid
- courseid
- date (Y-m-d)
- duration_seconds
- session_count
- lastupdated
```

### 4. Scheduler

**Purpose**: Manages scheduled report generation and email delivery.

**Key Classes**:
- `scheduler`: Main scheduling coordinator
- `schedule_manager`: CRUD operations for schedules
- `report_generator`: Generates scheduled reports
- `email_sender`: Sends reports via email

**Key Methods**:
```php
class scheduler {
    public function create_schedule(object $schedule): int;
    public function execute_due_schedules(): void;
    public function generate_and_send(int $scheduleid): bool;
    public function calculate_next_run(string $frequency, int $current_time): int;
}
```

**Schedule Execution Flow**:
```
1. Cron task runs every 15 minutes
2. Query schedules where next_run <= current_time AND active = 1
3. For each schedule:
   a. Create report_run record with status 'running'
   b. Execute report with configured parameters
   c. Generate file in specified format (CSV/XLSX/PDF)
   d. Store file using Moodle File API
   e. Send email to recipients with file attachment
   f. Update report_run with status 'completed' or 'failed'
   g. Calculate and update next_run timestamp
   h. Log to audit_logs
4. Retry failed schedules up to 3 times with exponential backoff
```

### 5. IOMAD Filter

**Purpose**: Automatically applies company-based filtering for multi-tenant IOMAD installations.

**Key Classes**:
- `iomad_filter`: Main filtering logic
- `company_resolver`: Resolves user's company assignments
- `query_modifier`: Modifies SQL to include company filters

**Key Methods**:
```php
class iomad_filter {
    public function is_iomad_installed(): bool;
    public function get_user_companies(int $userid): array;
    public function apply_company_filter(string $sql, int $userid): string;
    public function get_company_selector_options(int $userid): array;
}
```

**Filter Application Logic**:
```
IF IOMAD not installed:
    Return original SQL unchanged

IF user is site admin:
    Add optional company filter if company parameter provided
    
IF user is company manager:
    Get user's assigned companies
    Add WHERE clause: u.id IN (
        SELECT userid FROM company_users 
        WHERE companyid IN (user_companies)
    )
    
IF user is teacher/student:
    Apply standard Moodle role-based filtering
    Add company filter based on user's company
```

### 6. Export Engine

**Purpose**: Generates report exports in multiple formats.

**Key Classes**:
- `export_engine`: Main export coordinator
- `csv_exporter`: CSV generation
- `xlsx_exporter`: Excel generation using PHPSpreadsheet
- `pdf_exporter`: PDF generation using mPDF or TCPDF

**Key Methods**:
```php
class export_engine {
    public function export(array $data, string $format, array $options): stored_file;
    public function export_csv(array $data, array $headers): string;
    public function export_xlsx(array $data, array $headers): string;
    public function export_pdf(array $data, array $headers, array $charts): string;
}
```

**Export Format Specifications**:

**CSV**:
- UTF-8 encoding with BOM
- Comma delimiter, double-quote enclosure
- Headers in first row
- Date format: Y-m-d H:i:s

**XLSX**:
- Use PHPSpreadsheet library
- Auto-size columns
- Bold headers with background color
- Number formatting for numeric columns
- Date formatting for date columns

**PDF**:
- Use mPDF or TCPDF (configurable)
- A4 portrait orientation
- Include report title and generation timestamp
- Table with alternating row colors
- Optional chart images embedded

### 7. Cache Manager

**Purpose**: Pre-aggregates heavy metrics and manages cache lifecycle.

**Key Classes**:
- `cache_manager`: Main cache coordinator
- `aggregation_runner`: Executes pre-aggregation queries
- `cache_invalidator`: Invalidates stale cache entries

**Key Methods**:
```php
class cache_manager {
    public function get_cached_data(string $cache_key): ?object;
    public function set_cached_data(string $cache_key, object $data, int $ttl): void;
    public function invalidate_cache(string $cache_key): void;
    public function run_aggregations(): void;
}
```

**Caching Strategy**:
```
Cache Key Format: reporttype_referenceid_filters_hash

TTL (Time To Live):
- Dashboard widgets: 1 hour
- Trend reports (30/90 days): 6 hours
- Historical reports (12 months): 24 hours

Invalidation Triggers:
- Manual cache clear by admin
- TTL expiration
- Related data modification (e.g., course completion)

Pre-Aggregation Targets:
- 12-month enrollment trends
- 12-month completion trends
- Company-wide statistics
- SCORM summary metrics
```

## Data Models

### Database Schema



#### manireports_customreports
```xml
<TABLE NAME="manireports_customreports">
  <FIELDS>
    <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
    <FIELD NAME="name" TYPE="char" LENGTH="255" NOTNULL="true"/>
    <FIELD NAME="description" TYPE="text" NOTNULL="false"/>
    <FIELD NAME="type" TYPE="char" LENGTH="10" NOTNULL="true" DEFAULT="sql"/>
    <FIELD NAME="sqlquery" TYPE="text" NOTNULL="false"/>
    <FIELD NAME="configjson" TYPE="text" NOTNULL="false"/>
    <FIELD NAME="createdby" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="timecreated" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="timemodified" TYPE="int" LENGTH="10" NOTNULL="true"/>
  </FIELDS>
  <KEYS>
    <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
    <KEY NAME="createdby" TYPE="foreign" FIELDS="createdby" REFTABLE="user" REFFIELDS="id"/>
  </KEYS>
  <INDEXES>
    <INDEX NAME="type" UNIQUE="false" FIELDS="type"/>
  </INDEXES>
</TABLE>
```

#### manireports_schedules
```xml
<TABLE NAME="manireports_schedules">
  <FIELDS>
    <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
    <FIELD NAME="reportid" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="name" TYPE="char" LENGTH="255" NOTNULL="true"/>
    <FIELD NAME="frequency" TYPE="char" LENGTH="20" NOTNULL="true"/>
    <FIELD NAME="format" TYPE="char" LENGTH="10" NOTNULL="true" DEFAULT="csv"/>
    <FIELD NAME="paramsjson" TYPE="text" NOTNULL="false"/>
    <FIELD NAME="next_run" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="active" TYPE="int" LENGTH="1" NOTNULL="true" DEFAULT="1"/>
    <FIELD NAME="createdby" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="timecreated" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="timemodified" TYPE="int" LENGTH="10" NOTNULL="true"/>
  </FIELDS>
  <KEYS>
    <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
    <KEY NAME="reportid" TYPE="foreign" FIELDS="reportid" REFTABLE="manireports_customreports" REFFIELDS="id"/>
  </KEYS>
  <INDEXES>
    <INDEX NAME="next_run_active" UNIQUE="false" FIELDS="next_run,active"/>
  </INDEXES>
</TABLE>
```

#### manireports_schedule_recipients
```xml
<TABLE NAME="manireports_schedule_recipients">
  <FIELDS>
    <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
    <FIELD NAME="scheduleid" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="userid" TYPE="int" LENGTH="10" NOTNULL="true"/>
  </FIELDS>
  <KEYS>
    <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
    <KEY NAME="scheduleid" TYPE="foreign" FIELDS="scheduleid" REFTABLE="manireports_schedules" REFFIELDS="id"/>
    <KEY NAME="userid" TYPE="foreign" FIELDS="userid" REFTABLE="user" REFFIELDS="id"/>
  </KEYS>
  <INDEXES>
    <INDEX NAME="scheduleid" UNIQUE="false" FIELDS="scheduleid"/>
  </INDEXES>
</TABLE>
```

#### manireports_report_runs
```xml
<TABLE NAME="manireports_report_runs">
  <FIELDS>
    <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
    <FIELD NAME="reportid" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="scheduleid" TYPE="int" LENGTH="10" NOTNULL="false"/>
    <FIELD NAME="userid" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="status" TYPE="char" LENGTH="20" NOTNULL="true"/>
    <FIELD NAME="paramsjson" TYPE="text" NOTNULL="false"/>
    <FIELD NAME="rowcount" TYPE="int" LENGTH="10" NOTNULL="false"/>
    <FIELD NAME="fileitemid" TYPE="int" LENGTH="10" NOTNULL="false"/>
    <FIELD NAME="error" TYPE="text" NOTNULL="false"/>
    <FIELD NAME="timestarted" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="timefinished" TYPE="int" LENGTH="10" NOTNULL="false"/>
  </FIELDS>
  <KEYS>
    <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
    <KEY NAME="reportid" TYPE="foreign" FIELDS="reportid" REFTABLE="manireports_customreports" REFFIELDS="id"/>
  </KEYS>
  <INDEXES>
    <INDEX NAME="reportid_timestarted" UNIQUE="false" FIELDS="reportid,timestarted"/>
    <INDEX NAME="status" UNIQUE="false" FIELDS="status"/>
  </INDEXES>
</TABLE>
```

#### manireports_usertime_sessions
```xml
<TABLE NAME="manireports_usertime_sessions">
  <FIELDS>
    <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
    <FIELD NAME="userid" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="courseid" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="sessionstart" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="lastupdated" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="duration" TYPE="int" LENGTH="10" NOTNULL="false"/>
  </FIELDS>
  <KEYS>
    <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
    <KEY NAME="userid" TYPE="foreign" FIELDS="userid" REFTABLE="user" REFFIELDS="id"/>
    <KEY NAME="courseid" TYPE="foreign" FIELDS="courseid" REFTABLE="course" REFFIELDS="id"/>
  </KEYS>
  <INDEXES>
    <INDEX NAME="userid_courseid" UNIQUE="false" FIELDS="userid,courseid"/>
    <INDEX NAME="lastupdated" UNIQUE="false" FIELDS="lastupdated"/>
  </INDEXES>
</TABLE>
```

#### manireports_usertime_daily
```xml
<TABLE NAME="manireports_usertime_daily">
  <FIELDS>
    <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
    <FIELD NAME="userid" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="courseid" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="date" TYPE="char" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="duration" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0"/>
    <FIELD NAME="sessioncount" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0"/>
    <FIELD NAME="lastupdated" TYPE="int" LENGTH="10" NOTNULL="true"/>
  </FIELDS>
  <KEYS>
    <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
    <KEY NAME="userid" TYPE="foreign" FIELDS="userid" REFTABLE="user" REFFIELDS="id"/>
    <KEY NAME="courseid" TYPE="foreign" FIELDS="courseid" REFTABLE="course" REFFIELDS="id"/>
  </KEYS>
  <INDEXES>
    <INDEX NAME="userid_courseid_date" UNIQUE="true" FIELDS="userid,courseid,date"/>
    <INDEX NAME="date" UNIQUE="false" FIELDS="date"/>
  </INDEXES>
</TABLE>
```

#### manireports_scorm_summary
```xml
<TABLE NAME="manireports_scorm_summary">
  <FIELDS>
    <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
    <FIELD NAME="scormid" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="userid" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="attempts" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0"/>
    <FIELD NAME="completed" TYPE="int" LENGTH="1" NOTNULL="true" DEFAULT="0"/>
    <FIELD NAME="totaltime" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0"/>
    <FIELD NAME="score" TYPE="number" LENGTH="10" NOTNULL="false" DECIMALS="2"/>
    <FIELD NAME="lastaccess" TYPE="int" LENGTH="10" NOTNULL="false"/>
    <FIELD NAME="lastupdated" TYPE="int" LENGTH="10" NOTNULL="true"/>
  </FIELDS>
  <KEYS>
    <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
    <KEY NAME="scormid" TYPE="foreign" FIELDS="scormid" REFTABLE="scorm" REFFIELDS="id"/>
    <KEY NAME="userid" TYPE="foreign" FIELDS="userid" REFTABLE="user" REFFIELDS="id"/>
  </KEYS>
  <INDEXES>
    <INDEX NAME="scormid_userid" UNIQUE="true" FIELDS="scormid,userid"/>
  </INDEXES>
</TABLE>
```

#### manireports_cache_summary
```xml
<TABLE NAME="manireports_cache_summary">
  <FIELDS>
    <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
    <FIELD NAME="cachekey" TYPE="char" LENGTH="255" NOTNULL="true"/>
    <FIELD NAME="reporttype" TYPE="char" LENGTH="50" NOTNULL="true"/>
    <FIELD NAME="referenceid" TYPE="int" LENGTH="10" NOTNULL="false"/>
    <FIELD NAME="datajson" TYPE="text" NOTNULL="true"/>
    <FIELD NAME="lastgenerated" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="ttl" TYPE="int" LENGTH="10" NOTNULL="true"/>
  </FIELDS>
  <KEYS>
    <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
  </KEYS>
  <INDEXES>
    <INDEX NAME="cachekey" UNIQUE="true" FIELDS="cachekey"/>
    <INDEX NAME="reporttype" UNIQUE="false" FIELDS="reporttype"/>
    <INDEX NAME="lastgenerated" UNIQUE="false" FIELDS="lastgenerated"/>
  </INDEXES>
</TABLE>
```

#### manireports_dashboards
```xml
<TABLE NAME="manireports_dashboards">
  <FIELDS>
    <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
    <FIELD NAME="name" TYPE="char" LENGTH="255" NOTNULL="true"/>
    <FIELD NAME="description" TYPE="text" NOTNULL="false"/>
    <FIELD NAME="scope" TYPE="char" LENGTH="20" NOTNULL="true" DEFAULT="personal"/>
    <FIELD NAME="companyid" TYPE="int" LENGTH="10" NOTNULL="false"/>
    <FIELD NAME="layoutjson" TYPE="text" NOTNULL="true"/>
    <FIELD NAME="createdby" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="timecreated" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="timemodified" TYPE="int" LENGTH="10" NOTNULL="true"/>
  </FIELDS>
  <KEYS>
    <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
    <KEY NAME="createdby" TYPE="foreign" FIELDS="createdby" REFTABLE="user" REFFIELDS="id"/>
  </KEYS>
  <INDEXES>
    <INDEX NAME="scope" UNIQUE="false" FIELDS="scope"/>
    <INDEX NAME="companyid" UNIQUE="false" FIELDS="companyid"/>
  </INDEXES>
</TABLE>
```

#### manireports_dashboard_widgets
```xml
<TABLE NAME="manireports_dashboard_widgets">
  <FIELDS>
    <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
    <FIELD NAME="dashboardid" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="widgettype" TYPE="char" LENGTH="50" NOTNULL="true"/>
    <FIELD NAME="title" TYPE="char" LENGTH="255" NOTNULL="true"/>
    <FIELD NAME="position" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="configjson" TYPE="text" NOTNULL="true"/>
  </FIELDS>
  <KEYS>
    <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
    <KEY NAME="dashboardid" TYPE="foreign" FIELDS="dashboardid" REFTABLE="manireports_dashboards" REFFIELDS="id"/>
  </KEYS>
  <INDEXES>
    <INDEX NAME="dashboardid" UNIQUE="false" FIELDS="dashboardid"/>
  </INDEXES>
</TABLE>
```

#### manireports_audit_logs
```xml
<TABLE NAME="manireports_audit_logs">
  <FIELDS>
    <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
    <FIELD NAME="userid" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="action" TYPE="char" LENGTH="50" NOTNULL="true"/>
    <FIELD NAME="objecttype" TYPE="char" LENGTH="50" NOTNULL="true"/>
    <FIELD NAME="objectid" TYPE="int" LENGTH="10" NOTNULL="false"/>
    <FIELD NAME="details" TYPE="text" NOTNULL="false"/>
    <FIELD NAME="timecreated" TYPE="int" LENGTH="10" NOTNULL="true"/>
  </FIELDS>
  <KEYS>
    <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
    <KEY NAME="userid" TYPE="foreign" FIELDS="userid" REFTABLE="user" REFFIELDS="id"/>
  </KEYS>
  <INDEXES>
    <INDEX NAME="userid_timecreated" UNIQUE="false" FIELDS="userid,timecreated"/>
    <INDEX NAME="action" UNIQUE="false" FIELDS="action"/>
    <INDEX NAME="objecttype_objectid" UNIQUE="false" FIELDS="objecttype,objectid"/>
  </INDEXES>
</TABLE>
```

## Error Handling

### Error Categories

**1. User Input Errors**
- Invalid parameters (dates, IDs)
- Missing required fields
- Invalid filter combinations
- Response: User-friendly error message, HTTP 400

**2. Authorization Errors**
- Missing capabilities
- Company access violations
- Response: Permission denied message, HTTP 403

**3. Data Errors**
- Report not found
- Invalid SQL query
- Data integrity violations
- Response: Specific error message, HTTP 404 or 422

**4. System Errors**
- Database connection failures
- Query timeouts
- File system errors
- Response: Generic error message, log details, HTTP 500

**5. External Service Errors**
- Email delivery failures
- Export library errors
- Response: Retry mechanism, log error, notify admin

### Error Handling Strategy

```php
try {
    // Operation
} catch (dml_exception $e) {
    // Database error
    debugging('Database error: ' . $e->getMessage(), DEBUG_DEVELOPER);
    throw new moodle_exception('databaseerror', 'local_manireports');
} catch (required_capability_exception $e) {
    // Permission error
    throw new moodle_exception('nopermission', 'local_manireports');
} catch (Exception $e) {
    // Generic error
    debugging('Unexpected error: ' . $e->getMessage(), DEBUG_DEVELOPER);
    throw new moodle_exception('unexpectederror', 'local_manireports');
}
```

### Logging Strategy

**Error Logs**: Use Moodle debugging() and error_log()
**Audit Logs**: Custom audit_logs table for compliance
**Performance Logs**: Optional query timing logs for optimization



## Testing Strategy

### Unit Testing

**Target Coverage**: Core business logic classes (70%+ coverage)

**Test Classes**:
- `report_builder_test`: Test SQL validation, query building, parameter binding
- `analytics_engine_test`: Test engagement calculations, risk detection
- `time_engine_test`: Test session management, aggregation logic
- `iomad_filter_test`: Test company filtering logic
- `cache_manager_test`: Test cache operations, invalidation

**Test Data**: Use Moodle generators for users, courses, enrollments

**Example Test**:
```php
public function test_engagement_score_calculation() {
    $this->resetAfterTest(true);
    
    $user = $this->getDataGenerator()->create_user();
    $course = $this->getDataGenerator()->create_course();
    
    // Create test data
    $this->create_time_tracking_data($user->id, $course->id, 3600);
    $this->create_activity_completions($user->id, $course->id, 5);
    
    $engine = new \local_manireports\api\analytics_engine();
    $score = $engine->calculate_engagement_score($user->id, $course->id);
    
    $this->assertGreaterThan(0, $score);
    $this->assertLessThanOrEqual(100, $score);
}
```

### Integration Testing

**Target**: Test interactions between components

**Test Scenarios**:
- Report generation end-to-end (builder → execution → export)
- Scheduled report execution (scheduler → generator → email)
- Dashboard rendering with filters (UI → API → database)
- Time tracking flow (heartbeat → session → aggregation)

### Functional Testing

**Target**: Test user-facing features

**Test Cases**:
- Admin can create and execute custom SQL report
- Company manager sees only their company's data
- Teacher can view student progress dashboard
- Student can view personal time tracking
- Scheduled reports are delivered via email
- Export generates correct CSV/XLSX/PDF files

### Performance Testing

**Target**: Ensure acceptable performance under load

**Test Scenarios**:
- Dashboard load time with 1000 users
- Report execution time with 10000 rows
- Cache effectiveness (hit rate > 80%)
- Concurrent report executions (10 simultaneous)
- Time tracking heartbeat load (100 concurrent users)

**Performance Benchmarks**:
- Dashboard load: < 2 seconds (small dataset)
- Dashboard load: < 3 seconds (medium dataset with cache)
- Report execution: < 10 seconds (< 10000 rows)
- Export generation: < 30 seconds (< 50000 rows)
- Heartbeat processing: < 100ms per request

### Security Testing

**Target**: Verify security controls

**Test Cases**:
- SQL injection prevention in custom reports
- XSS prevention in report output
- CSRF protection on forms
- Capability enforcement on all endpoints
- Company isolation in IOMAD mode
- Parameter validation and sanitization

### Accessibility Testing

**Target**: WCAG 2.1 Level AA compliance

**Test Areas**:
- Keyboard navigation
- Screen reader compatibility
- Color contrast ratios
- Form labels and ARIA attributes
- Focus indicators

## UI/UX Design Patterns

### Dashboard Layout

**Grid System**: 12-column responsive grid using Bootstrap
**Widget Sizes**: 
- Small: 3 columns (KPI cards)
- Medium: 6 columns (charts)
- Large: 12 columns (tables)

**Color Scheme**:
- Primary: Moodle theme primary color
- Success: #28a745 (green)
- Warning: #ffc107 (yellow)
- Danger: #dc3545 (red)
- Info: #17a2b8 (blue)

### Chart Design

**Chart.js Configuration**:
```javascript
{
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'bottom'
        },
        tooltip: {
            mode: 'index',
            intersect: false
        }
    },
    scales: {
        y: {
            beginAtZero: true
        }
    }
}
```

**Chart Types**:
- Line charts: Trends over time
- Bar charts: Comparisons across categories
- Pie charts: Proportions and distributions
- KPI cards: Single metric with trend indicator

### Filter Controls

**Filter Types**:
- Date range picker: Start date + end date
- Company selector: Dropdown (IOMAD only)
- Course selector: Autocomplete search
- User selector: Autocomplete search
- Quick filters: Last 7 days, Last 30 days, Last 90 days

**Filter Behavior**:
- Filters persist in session storage
- Apply button triggers AJAX update
- Reset button clears all filters
- Loading indicator during data fetch

### Table Design

**Features**:
- Sortable columns (click header)
- Pagination (25/50/100 rows per page)
- Search/filter box
- Export button (CSV/XLSX/PDF)
- Responsive: Horizontal scroll on mobile

**Styling**:
- Striped rows for readability
- Hover effect on rows
- Bold headers with background color
- Right-align numeric columns

### Loading States

**Skeleton Loaders**: Show placeholder content while loading
**Spinners**: Use for button actions and small updates
**Progress Bars**: Use for long-running operations (exports)

### Error Messages

**Toast Notifications**: Non-blocking messages for success/info
**Alert Boxes**: Blocking messages for errors/warnings
**Inline Validation**: Real-time feedback on form fields

## Deployment and Configuration

### Installation Steps

1. Extract plugin to `moodle/local/manireports/`
2. Visit Site Administration → Notifications
3. Database tables created automatically
4. Configure plugin settings
5. Assign capabilities to roles
6. Enable scheduled tasks

### Configuration Settings

**General Settings**:
- Enable/disable time tracking
- Heartbeat interval (20-30 seconds)
- Session timeout (10 minutes)
- Cache TTL (1-24 hours)

**Report Settings**:
- Max SQL execution time (30-300 seconds)
- Max export rows (10000-100000)
- Allowed SQL tables (whitelist)
- Default export format (CSV/XLSX/PDF)

**Schedule Settings**:
- Max concurrent executions (1-10)
- Retry attempts (0-5)
- Retry delay (5-60 minutes)
- Email from address

**Privacy Settings**:
- Enable/disable time tracking
- Data retention period (30-365 days)
- Auto-cleanup old data

**Performance Settings**:
- Enable pre-aggregation
- Aggregation schedule (daily/weekly)
- Cache strategy (aggressive/moderate/minimal)

### Scheduled Tasks

**Task Schedule Recommendations**:
- `time_aggregation`: Every 1 hour
- `cache_builder`: Every 6 hours (off-peak)
- `report_scheduler`: Every 15 minutes
- `scorm_summary`: Every 1 hour
- `cleanup_old_data`: Daily at 2:00 AM

### Capability Assignments

**Default Assignments**:
```
Manager role:
- local/manireports:viewmanagerdashboard
- local/manireports:managereports
- local/manireports:schedule

Teacher role:
- local/manireports:viewteacherdashboard

Student role:
- local/manireports:viewstudentdashboard

Site Admin:
- All capabilities
```

## Performance Optimization

### Database Optimization

**Indexes**: All foreign keys and frequently queried columns
**Query Optimization**:
- Use JOIN instead of subqueries where possible
- Limit result sets with WHERE clauses
- Use EXPLAIN to analyze slow queries
- Avoid SELECT * in production queries

**Connection Pooling**: Use Moodle's built-in connection management

### Caching Strategy

**Cache Levels**:
1. **Application Cache**: Moodle Cache API for configuration
2. **Query Cache**: Pre-aggregated metrics in cache_summary table
3. **Session Cache**: Filter selections in user session
4. **Browser Cache**: Static assets (JS/CSS) with versioning

**Cache Invalidation**:
- Time-based: TTL expiration
- Event-based: On data modification
- Manual: Admin cache clear

### Frontend Optimization

**JavaScript**:
- Minified AMD modules
- Lazy loading for non-critical widgets
- Debounced filter inputs (300ms delay)
- Request cancellation for rapid filter changes

**Assets**:
- Compressed images
- SVG icons where possible
- CSS minification
- Browser caching headers

### Background Processing

**Async Operations**:
- Report generation (scheduled)
- Export file creation
- Email sending
- Pre-aggregation

**Queue Management**:
- Use Moodle adhoc tasks for one-time jobs
- Use scheduled tasks for recurring jobs
- Implement job priority levels

## Security Considerations

### Input Validation

**All User Input**:
- Use `required_param()` and `optional_param()`
- Specify PARAM_* type constants
- Validate ranges and formats
- Sanitize for output context

**SQL Query Validation**:
- Whitelist allowed tables
- Block DDL statements (DROP, CREATE, ALTER)
- Block DML statements (INSERT, UPDATE, DELETE)
- Require SELECT only
- Validate parameter placeholders

### Output Encoding

**HTML Output**: Use `s()` or `format_string()`
**JavaScript Output**: Use `json_encode()` with JSON_HEX_TAG
**URL Output**: Use `moodle_url` class
**SQL Output**: Use parameterized queries

### Authentication and Authorization

**Session Management**: Use Moodle session handling
**Capability Checks**: On every page load and AJAX request
**CSRF Protection**: Use `sesskey` parameter
**Company Isolation**: Enforce in all queries (IOMAD)

### Data Protection

**Sensitive Data**:
- Never log passwords or tokens
- Encrypt sensitive configuration (if needed)
- Mask personal data in logs

**File Security**:
- Use Moodle File API
- Check file permissions
- Validate file types
- Scan for malware (if available)

### Audit Trail

**Log All Actions**:
- Report creation/modification/deletion
- Schedule creation/modification/deletion
- Report executions
- Failed authorization attempts
- Configuration changes

## Maintenance and Support

### Monitoring

**Health Checks**:
- Scheduled task execution status
- Failed report runs
- Database table sizes
- Cache hit rates
- Error log entries

**Alerts**:
- Email admin on repeated task failures
- Notify on database errors
- Alert on disk space issues (exports)

### Backup and Recovery

**Backup Scope**:
- Custom reports (SQL and config)
- Schedules and recipients
- Dashboards and widgets
- Configuration settings

**Exclude from Backup**:
- Cached data (regenerate)
- Session data (temporary)
- Audit logs (optional)

### Upgrade Path

**Version Compatibility**:
- Support Moodle 4.0+ LTS versions
- Test on each minor version
- Provide upgrade.php for schema changes

**Data Migration**:
- Preserve custom reports
- Preserve schedules
- Migrate configuration settings
- Update database schema incrementally

### Documentation

**User Documentation**:
- Getting started guide
- Dashboard user guide
- Report builder tutorial
- Scheduling guide
- FAQ

**Administrator Documentation**:
- Installation guide
- Configuration reference
- Troubleshooting guide
- Performance tuning
- Security best practices

**Developer Documentation**:
- Architecture overview
- API reference
- Extension points
- Coding standards
- Testing guide

## Cloud Offload Module (Phase 4)

### Purpose

Offload two heavy operations from Moodle host to external cloud infrastructure:
1. **Bulk Email Sending** - Reminders, notifications, scheduled report delivery
2. **Certificate Generation** - PDF certificates for course completions

### Architecture

```
[Moodle Plugin] → [Cloud Ingest API/SQS] → [Worker Pool] → [Callback to Moodle]
                                              ↓
                                         [S3/R2 Storage]
                                              ↓
                                         [Email Provider]
```

### Components

#### 1. Cloud Job Manager

**Purpose**: Manage cloud job lifecycle, batching, and status tracking

**Key Classes**:
- `cloud_job_manager`: Main coordinator
- `job_batcher`: Split large recipient lists into chunks
- `job_status_tracker`: Track job and recipient status

**Key Methods**:
```php
class cloud_job_manager {
    public function create_bulk_email_job(array $recipients, object $template, array $options): string;
    public function create_certificate_job(int $userid, int $courseid, array $options): string;
    public function submit_job_to_cloud(string $jobid): bool;
    public function update_job_status(string $jobid, string $status, array $details): void;
    public function get_job_progress(string $jobid): object;
    public function retry_failed_recipients(string $jobid): int;
}
```

#### 2. Cloud Connector

**Purpose**: Abstract interface to cloud services (AWS/Cloudflare)

**Key Classes**:
- `cloud_connector`: Abstract base class
- `aws_connector`: AWS implementation (SQS + Lambda + SES + S3)
- `cloudflare_connector`: Cloudflare implementation (Queue + Workers + Email + R2)

**Key Methods**:
```php
abstract class cloud_connector {
    abstract public function send_job(object $job_payload): bool;
    abstract public function validate_callback(array $headers, string $body): bool;
    abstract public function get_presigned_url(string $file_key, int $ttl): string;
}
```

#### 3. Certificate Generator

**Purpose**: Generate PDF certificates locally or prepare for cloud generation

**Key Classes**:
- `certificate_generator`: Main generator
- `certificate_template`: Template management
- `certificate_data_provider`: Merge user/course data

**Key Methods**:
```php
class certificate_generator {
    public function generate_local(int $userid, int $courseid, string $template): stored_file;
    public function prepare_cloud_data(int $userid, int $courseid): array;
    public function get_available_templates(): array;
}
```

#### 4. Cloud Callback Handler

**Purpose**: Receive and process status updates from cloud workers

**Endpoint**: `ui/ajax/cloud_callback.php`

**Security**:
- HMAC-SHA256 signature validation
- Timestamp validation (prevent replay attacks)
- IP whitelist (optional)

**Processing**:
```php
// Validate signature
$expected_sig = hash_hmac('sha256', $body, $secret);
if (!hash_equals($expected_sig, $received_sig)) {
    http_response_code(401);
    exit;
}

// Update job status
$manager->update_recipient_status($jobid, $userid, $status, $details);
```

### Database Schema

#### manireports_cloud_jobs
```xml
<TABLE NAME="manireports_cloud_jobs">
  <FIELDS>
    <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
    <FIELD NAME="jobid" TYPE="char" LENGTH="36" NOTNULL="true"/>
    <FIELD NAME="tenantid" TYPE="int" LENGTH="10" NOTNULL="false"/>
    <FIELD NAME="type" TYPE="char" LENGTH="50" NOTNULL="true"/>
    <FIELD NAME="status" TYPE="char" LENGTH="20" NOTNULL="true" DEFAULT="pending"/>
    <FIELD NAME="total_recipients" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="completed_recipients" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0"/>
    <FIELD NAME="failed_recipients" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0"/>
    <FIELD NAME="payload_json" TYPE="text" NOTNULL="true"/>
    <FIELD NAME="createdby" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="timecreated" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="timestarted" TYPE="int" LENGTH="10" NOTNULL="false"/>
    <FIELD NAME="timecompleted" TYPE="int" LENGTH="10" NOTNULL="false"/>
  </FIELDS>
  <KEYS>
    <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
    <KEY NAME="createdby" TYPE="foreign" FIELDS="createdby" REFTABLE="user" REFFIELDS="id"/>
  </KEYS>
  <INDEXES>
    <INDEX NAME="jobid" UNIQUE="true" FIELDS="jobid"/>
    <INDEX NAME="status" UNIQUE="false" FIELDS="status"/>
    <INDEX NAME="tenantid" UNIQUE="false" FIELDS="tenantid"/>
  </INDEXES>
</TABLE>
```

#### manireports_cloud_job_recipients
```xml
<TABLE NAME="manireports_cloud_job_recipients">
  <FIELDS>
    <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
    <FIELD NAME="jobid" TYPE="char" LENGTH="36" NOTNULL="true"/>
    <FIELD NAME="userid" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="email" TYPE="char" LENGTH="255" NOTNULL="true"/>
    <FIELD NAME="status" TYPE="char" LENGTH="20" NOTNULL="true" DEFAULT="pending"/>
    <FIELD NAME="attempts" TYPE="int" LENGTH="2" NOTNULL="true" DEFAULT="0"/>
    <FIELD NAME="certificate_url" TYPE="text" NOTNULL="false"/>
    <FIELD NAME="error_message" TYPE="text" NOTNULL="false"/>
    <FIELD NAME="timesent" TYPE="int" LENGTH="10" NOTNULL="false"/>
  </FIELDS>
  <KEYS>
    <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
    <KEY NAME="userid" TYPE="foreign" FIELDS="userid" REFTABLE="user" REFFIELDS="id"/>
  </KEYS>
  <INDEXES>
    <INDEX NAME="jobid_userid" UNIQUE="true" FIELDS="jobid,userid"/>
    <INDEX NAME="status" UNIQUE="false" FIELDS="status"/>
  </INDEXES>
</TABLE>
```

### Job JSON Contract

```json
{
  "job_id": "550e8400-e29b-41d4-a716-446655440000",
  "tenant_id": 123,
  "type": "send_reminder",
  "courseid": 101,
  "created_by": 50,
  "recipients": [
    {
      "userid": 1001,
      "email": "user@example.com",
      "firstname": "John",
      "lastname": "Doe"
    }
  ],
  "template_id": "course_reminder_v1",
  "template_data": {
    "course_name": "Introduction to PHP",
    "due_date": "2025-12-31"
  },
  "generate_certificate": true,
  "certificate_template": "default",
  "callback_url": "https://yourmoodle.com/local/manireports/ui/ajax/cloud_callback.php",
  "callback_secret": "hmac_secret_key",
  "timestamp": "2025-11-18T12:00:00Z"
}
```

### Cloud Worker Responsibilities

1. Pull job from SQS queue
2. If certificate required:
   - Generate PDF using template
   - Upload to S3/R2
   - Generate presigned URL (configurable TTL)
3. Send email via provider (SES/SendGrid/Mailgun)
4. Callback to Moodle with status per recipient
5. Handle retries with exponential backoff
6. Move to DLQ after max retries

### Configuration Settings

**Cloud Offload Settings**:
```php
$settings->add(new admin_setting_configcheckbox(
    'local_manireports/cloud_offload_enabled',
    get_string('cloud_offload_enabled', 'local_manireports'),
    get_string('cloud_offload_enabled_desc', 'local_manireports'),
    0
));

$settings->add(new admin_setting_configselect(
    'local_manireports/cloud_mode',
    get_string('cloud_mode', 'local_manireports'),
    get_string('cloud_mode_desc', 'local_manireports'),
    'api_gateway',
    ['api_gateway' => 'API Gateway', 'sqs' => 'Direct SQS']
));

$settings->add(new admin_setting_configtext(
    'local_manireports/cloud_endpoint',
    get_string('cloud_endpoint', 'local_manireports'),
    get_string('cloud_endpoint_desc', 'local_manireports'),
    '',
    PARAM_URL
));

$settings->add(new admin_setting_configpasswordunmask(
    'local_manireports/cloud_auth_token',
    get_string('cloud_auth_token', 'local_manireports'),
    get_string('cloud_auth_token_desc', 'local_manireports'),
    ''
));

$settings->add(new admin_setting_configtext(
    'local_manireports/job_batch_size',
    get_string('job_batch_size', 'local_manireports'),
    get_string('job_batch_size_desc', 'local_manireports'),
    200,
    PARAM_INT
));

$settings->add(new admin_setting_configselect(
    'local_manireports/email_provider',
    get_string('email_provider', 'local_manireports'),
    get_string('email_provider_desc', 'local_manireports'),
    'ses',
    ['ses' => 'AWS SES', 'sendgrid' => 'SendGrid', 'mailgun' => 'Mailgun', 'custom' => 'Custom SMTP']
));
```

### UI Components

**Job Monitoring Dashboard** (`ui/cloud_jobs.php`):
- List all cloud jobs with status
- Filter by status, date, type
- View job details and recipient status
- Manual retry for failed recipients
- Export job logs

**Job Status Widget**:
- Progress bar showing completion percentage
- Real-time status updates via AJAX
- Recipient breakdown (pending/completed/failed)

### Security Considerations

**HMAC Validation**:
```php
$signature = hash_hmac('sha256', $request_body, $callback_secret);
$received_sig = $_SERVER['HTTP_X_SIGNATURE'] ?? '';

if (!hash_equals($signature, $received_sig)) {
    http_response_code(401);
    die('Invalid signature');
}
```

**Timestamp Validation**:
```php
$timestamp = $payload['timestamp'] ?? 0;
$now = time();

if (abs($now - $timestamp) > 300) { // 5 minutes
    http_response_code(401);
    die('Request expired');
}
```

### Monitoring and Alerts

**Metrics to Track**:
- SQS queue depth
- Job processing time
- Email delivery rate
- Bounce rate
- Failed job count
- Certificate generation time

**Alerts**:
- DLQ not empty
- High bounce rate (> 5%)
- Job processing time > threshold
- Cloud endpoint unreachable

### Cost Estimation (AWS)

For 50,000 emails/day:
- **SQS**: ~$0.40/month (1M requests free tier)
- **Lambda**: ~$5/month (compute time)
- **SES**: ~$5/month ($0.10 per 1000 emails)
- **S3**: ~$1/month (storage + requests)
- **Total**: ~$11-15/month

### Fallback Strategy

```php
if (!$cloud_enabled || !$cloud_available) {
    // Fall back to local processing
    $this->send_emails_locally($recipients);
    $this->generate_certificates_locally($users);
}
```

## Future Enhancements

### Phase 5 Features (Post-Cloud Offload)

**Advanced Analytics**:
- Machine learning-based predictions
- Cohort analysis
- Learning path analytics
- Competency-based reporting

**Integration**:
- LTI provider for external systems
- REST API for third-party tools
- Webhook notifications
- SSO integration

**Visualization**:
- Heatmaps for activity patterns
- Network graphs for collaboration
- Gantt charts for project timelines
- Geographic maps for user distribution

**Mobile**:
- Native mobile app
- Progressive Web App (PWA)
- Mobile-optimized dashboards
- Push notifications

**AI Features**:
- Natural language queries
- Automated insights
- Anomaly detection
- Recommendation engine

