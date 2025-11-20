# Requirements Document

## Introduction

ManiReports is a self-hosted advanced analytics and reporting plugin for Moodle/IOMAD that combines enterprise-grade analytics capabilities with modern UI/UX and simple deployment. The plugin provides role-based dashboards, multi-tenant support, custom report building, scheduled reporting, time tracking, and predictive analytics without requiring external cloud services. It targets Moodle 4.0–4.4 LTS and IOMAD 4.0–4.4 installations running PHP 7.4–8.2 with MariaDB/MySQL databases.

## Glossary

- **ManiReports_System**: The local_manireports Moodle plugin
- **IOMAD**: Multi-tenant Moodle distribution supporting company/department hierarchies
- **Company_Manager**: IOMAD role managing users within a specific company
- **Report_Schedule**: Configuration defining automated report generation and delivery
- **Dashboard**: Configurable view containing widgets displaying analytics data
- **Widget**: Individual chart, table, or KPI card component within a dashboard
- **Time_Tracking_Engine**: Subsystem recording user activity duration via JavaScript heartbeat and log analysis
- **Custom_Report**: User-defined report created via SQL or GUI builder
- **Pre_Aggregation**: Process of computing and caching heavy metrics for fast retrieval
- **At_Risk_Learner**: User identified by rule engine as likely to fail or disengage
- **Heartbeat**: JavaScript mechanism sending periodic activity signals to track session duration
- **SCORM_Summary**: Aggregated analytics from SCORM activity tracking data
- **Capability**: Moodle permission controlling access to specific features
- **Cron_Task**: Scheduled background job executed by Moodle task scheduler
- **Export_Format**: Output format for reports (CSV, XLSX, PDF)
- **Audit_Log**: Record of user actions for compliance and troubleshooting

## Requirements

### Requirement 1: Course Completion Dashboard

**User Story:** As an administrator or company manager, I want to view course completion metrics with enrollment counts, completion percentages, and trends, so that I can monitor training effectiveness across my organization.

#### Acceptance Criteria

1. WHEN the user accesses the course completion dashboard, THE ManiReports_System SHALL display a table containing course name, enrollment count, completion percentage, and trend data for the selected date range
2. WHEN the user selects a date range filter, THE ManiReports_System SHALL update the dashboard to show completion trends for the last 30 days or 90 days based on the selection
3. WHERE IOMAD is installed, WHEN a Company_Manager accesses the dashboard, THE ManiReports_System SHALL filter displayed courses to only those associated with the manager's company
4. WHEN the user clicks the export button, THE ManiReports_System SHALL generate a CSV file containing the same data rows displayed in the dashboard table
5. THE ManiReports_System SHALL load the course completion dashboard with data from Moodle core tables within 2 seconds for datasets containing up to 100 courses

### Requirement 2: Role-Based Dashboard Access

**User Story:** As a system administrator, I want different user roles to see dashboards appropriate to their responsibilities, so that users access only the data they are authorized to view.

#### Acceptance Criteria

1. WHEN a user with administrator capability accesses ManiReports_System, THE ManiReports_System SHALL display the admin dashboard containing site-wide statistics across all companies
2. WHEN a Company_Manager accesses ManiReports_System, THE ManiReports_System SHALL display only data for users and courses within the manager's assigned company
3. WHEN a teacher accesses ManiReports_System, THE ManiReports_System SHALL display student progress, activity completion, and quiz analytics for courses where the teacher has teaching capability
4. WHEN a student accesses ManiReports_System, THE ManiReports_System SHALL display only the student's personal progress, time tracking, and course completion data
5. THE ManiReports_System SHALL enforce capability checks on every dashboard request and deny access with an error message when the user lacks required permissions

### Requirement 3: Multi-Tenant IOMAD Support

**User Story:** As a company manager in an IOMAD installation, I want all reports to automatically filter to my company's data, so that I cannot view or access other companies' information.

#### Acceptance Criteria

1. WHERE IOMAD is installed, WHEN any user executes a report query, THE ManiReports_System SHALL automatically apply company filters based on the user's company assignment
2. WHERE IOMAD is installed, WHEN a user with multi-company access views a dashboard, THE ManiReports_System SHALL display a company selector dropdown containing only companies the user is authorized to access
3. WHERE IOMAD is installed, WHEN a Company_Manager attempts to access data from a different company, THE ManiReports_System SHALL deny the request and return an authorization error
4. WHERE IOMAD is installed, THE ManiReports_System SHALL include company identifier in all database queries retrieving user, course, or enrollment data
5. WHERE IOMAD is not installed, THE ManiReports_System SHALL operate without company filters and display data according to standard Moodle role permissions

### Requirement 4: Prebuilt Core Reports

**User Story:** As a training manager, I want access to standard reports for course completion, user progress, SCORM activities, engagement, and quiz attempts, so that I can analyze learning outcomes without creating custom reports.

#### Acceptance Criteria

1. THE ManiReports_System SHALL provide a course completion report displaying completion status per course and per company with data matching the mdl_course_completions table
2. THE ManiReports_System SHALL provide a course progress report displaying per-user completion percentages with data matching Moodle core progress tracking
3. THE ManiReports_System SHALL provide a SCORM summary report displaying attempt counts, completion status, and average time spent with data aggregated from mdl_scorm_scoes_track table
4. THE ManiReports_System SHALL provide a user engagement report displaying active days and time spent for the last 7 days and last 30 days
5. THE ManiReports_System SHALL provide a quiz attempts summary report displaying attempt counts, scores, and completion rates per course with data from mdl_quiz_attempts table

### Requirement 5: Scheduled Report Generation and Delivery

**User Story:** As an administrator, I want to schedule reports to be automatically generated and emailed to recipients on a daily, weekly, or monthly basis, so that stakeholders receive timely updates without manual intervention.

#### Acceptance Criteria

1. WHEN an administrator creates a Report_Schedule, THE ManiReports_System SHALL store the schedule configuration including report identifier, frequency, recipients, and export format
2. WHEN the Moodle Cron_Task executes, THE ManiReports_System SHALL identify Report_Schedules with next_run timestamps in the past and generate the corresponding reports
3. WHEN a scheduled report is generated, THE ManiReports_System SHALL create a file attachment in the specified Export_Format and send an email to all configured recipients
4. WHEN a scheduled report completes, THE ManiReports_System SHALL create an Audit_Log entry containing start time, finish time, duration, status, and file reference
5. IF a scheduled report generation fails, THE ManiReports_System SHALL log the error details and update the schedule status to indicate failure

### Requirement 6: Custom SQL Report Builder

**User Story:** As an administrator, I want to create custom reports using SQL queries with parameters, so that I can answer specific analytical questions not covered by prebuilt reports.

#### Acceptance Criteria

1. WHEN an administrator with custom report capability creates a custom SQL report, THE ManiReports_System SHALL validate the SQL query against a whitelist of allowed tables and operations
2. WHEN an administrator saves a Custom_Report with parameters, THE ManiReports_System SHALL store the parameterized SQL query using prepared statement placeholders
3. WHEN a user executes a Custom_Report with parameters, THE ManiReports_System SHALL bind parameter values using safe parameter binding to prevent SQL injection
4. WHEN a Custom_Report executes, THE ManiReports_System SHALL enforce a maximum execution time limit configured in plugin settings
5. THE ManiReports_System SHALL restrict Custom_Report creation to users with the local/manireports:customreports capability

### Requirement 7: Responsive UI with AJAX Filtering

**User Story:** As a report user, I want a modern responsive interface with interactive filters that update charts without page reloads, so that I can explore data efficiently on desktop and mobile devices.

#### Acceptance Criteria

1. THE ManiReports_System SHALL render all dashboards using responsive layouts that adapt to desktop, tablet, and mobile screen sizes
2. WHEN a user changes a filter value, THE ManiReports_System SHALL update dashboard widgets via AJAX requests without performing a full page reload
3. THE ManiReports_System SHALL render all charts client-side using Chart.js library with data fetched from JSON endpoints
4. THE ManiReports_System SHALL use Mustache templates for all HTML rendering to maintain consistency with Moodle standards
5. WHILE a dashboard is loading data, THE ManiReports_System SHALL display loading indicators to provide visual feedback to the user

### Requirement 8: Custom Dashboard Builder

**User Story:** As an administrator, I want to create custom dashboards by dragging and dropping widgets, configuring filters, and saving layouts, so that I can build tailored views for different stakeholder groups.

#### Acceptance Criteria

1. WHEN an administrator accesses the dashboard builder, THE ManiReports_System SHALL display a palette of available Widget types including KPI cards, line charts, bar charts, pie charts, and tables
2. WHEN an administrator drags a Widget onto the dashboard canvas, THE ManiReports_System SHALL allow positioning and resizing of the Widget within a grid layout
3. WHEN an administrator configures a Widget, THE ManiReports_System SHALL provide options to select data source, filters, and display settings
4. WHEN an administrator saves a Dashboard, THE ManiReports_System SHALL persist the configuration as JSON including Widget types, positions, sizes, and filter settings
5. WHERE a Dashboard is marked as global, THE ManiReports_System SHALL make the Dashboard available to all users with appropriate capabilities; WHERE a Dashboard is marked as company-specific, THE ManiReports_System SHALL restrict access to users within the specified company

### Requirement 9: Time Tracking Engine

**User Story:** As a training manager, I want to track how much time users spend in courses and activities, so that I can measure engagement and identify users who may need additional support.

#### Acceptance Criteria

1. WHEN a user views a course page, THE ManiReports_System SHALL initialize a JavaScript Heartbeat that sends activity signals to the server every 20 to 30 seconds
2. WHEN the Time_Tracking_Engine receives a Heartbeat signal, THE ManiReports_System SHALL create or update a session record in the manireports_usertime_sessions table with the current timestamp
3. WHEN the time tracking Cron_Task executes, THE ManiReports_System SHALL aggregate session data into daily summaries stored in the manireports_usertime_daily table
4. IF JavaScript Heartbeat is unavailable or disabled, THE ManiReports_System SHALL fall back to computing time estimates from Moodle log entries
5. THE ManiReports_System SHALL provide a setting to enable or disable the Time_Tracking_Engine for privacy compliance

### Requirement 10: SCORM Deep Analytics

**User Story:** As a course administrator, I want detailed SCORM analytics including attempt counts, suspend status, interactions, and average time, so that I can understand how learners engage with SCORM content.

#### Acceptance Criteria

1. WHEN the SCORM summary Cron_Task executes, THE ManiReports_System SHALL aggregate data from the mdl_scorm_scoes_track table into the manireports_scorm_summary table
2. THE ManiReports_System SHALL calculate attempt counts, completion status, suspend data status, interaction counts, and average time spent per SCORM activity
3. WHEN a user views a SCORM analytics report, THE ManiReports_System SHALL display aggregated metrics with accuracy within 10 percent of raw log data
4. THE ManiReports_System SHALL provide drill-down capability to view individual attempt details including SCO interactions and tracking data
5. THE ManiReports_System SHALL update SCORM_Summary data incrementally to minimize database load on large installations

### Requirement 11: Pre-Aggregation and Caching

**User Story:** As a system administrator, I want heavy metrics like 12-month trends to be pre-computed and cached, so that dashboards load quickly even with large datasets.

#### Acceptance Criteria

1. WHEN the cache builder Cron_Task executes, THE ManiReports_System SHALL identify reports configured for Pre_Aggregation and compute their metrics
2. WHEN Pre_Aggregation completes, THE ManiReports_System SHALL store the computed results as JSON blobs in the manireports_cache_summary table with a timestamp
3. WHEN a user requests a dashboard with cached metrics, THE ManiReports_System SHALL retrieve pre-computed JSON data instead of executing heavy database joins
4. THE ManiReports_System SHALL load dashboards using cached data within 1 second for medium-sized datasets containing up to 1000 users
5. THE ManiReports_System SHALL provide a setting to configure cache duration and automatically invalidate cached data older than the configured threshold

### Requirement 12: Report Run and Audit Logging

**User Story:** As a compliance officer, I want to see a history of all report executions including who ran them, when, and what data was accessed, so that I can audit system usage and troubleshoot issues.

#### Acceptance Criteria

1. WHEN a user executes a report manually or via schedule, THE ManiReports_System SHALL create a record in the manireports_report_runs table containing user identifier, report identifier, start time, and parameters
2. WHEN a report execution completes, THE ManiReports_System SHALL update the run record with finish time, duration, status, row count, and file reference if applicable
3. WHEN a user creates, modifies, or deletes a Custom_Report or Report_Schedule, THE ManiReports_System SHALL create an Audit_Log entry recording the action, user, timestamp, and affected object
4. THE ManiReports_System SHALL provide an audit log viewer accessible to administrators showing all logged actions with filtering by user, date range, and action type
5. THE ManiReports_System SHALL retain Audit_Log entries according to the configured retention policy with automatic cleanup of old entries

### Requirement 13: Multiple Export Formats

**User Story:** As a report user, I want to export reports in CSV, Excel, or PDF format, so that I can share data with stakeholders who prefer different file types.

#### Acceptance Criteria

1. WHEN a user requests a report export, THE ManiReports_System SHALL provide format options including CSV, XLSX, and PDF
2. WHEN a user exports to CSV format, THE ManiReports_System SHALL generate a comma-separated values file with column headers matching the displayed report
3. WHEN a user exports to XLSX format, THE ManiReports_System SHALL generate an Excel-compatible file using a PHP library with proper cell formatting
4. WHEN a user exports to PDF format, THE ManiReports_System SHALL generate a PDF document containing the report table and any associated charts
5. THE ManiReports_System SHALL ensure exported files contain the same data rows and columns as displayed in the on-screen report

### Requirement 14: Role-Based Access Control

**User Story:** As a system administrator, I want fine-grained capabilities to control who can view dashboards, create reports, and schedule deliveries, so that I can enforce security policies appropriate to my organization.

#### Acceptance Criteria

1. THE ManiReports_System SHALL define capabilities including local/manireports:viewadmindashboard, local/manireports:viewmanagerdashboard, local/manireports:viewteacherdashboard, local/manireports:viewstudentdashboard, local/manireports:managereports, local/manireports:schedule, and local/manireports:customreports
2. WHEN a user attempts to access a dashboard or feature, THE ManiReports_System SHALL check the user's assigned capabilities and grant access only if the required capability is present
3. WHEN a user lacks the required capability for a requested action, THE ManiReports_System SHALL deny access and display an error message indicating insufficient permissions
4. THE ManiReports_System SHALL allow administrators to assign capabilities to custom roles through the standard Moodle permissions interface
5. THE ManiReports_System SHALL enforce capability checks on all UI endpoints, AJAX endpoints, and API endpoints

### Requirement 15: GUI-Based Report Builder

**User Story:** As a non-technical manager, I want to create reports using a visual interface without writing SQL, so that I can build custom reports without requiring database expertise.

#### Acceptance Criteria

1. WHEN a user accesses the GUI report builder, THE ManiReports_System SHALL display a visual interface for selecting tables, columns, joins, filters, and groupings
2. WHEN a user selects a table, THE ManiReports_System SHALL display available columns with human-readable labels and data type indicators
3. WHEN a user adds a filter, THE ManiReports_System SHALL provide appropriate input controls based on the column data type including text fields, date pickers, and dropdowns
4. WHEN a user saves a GUI-built report, THE ManiReports_System SHALL generate and store the equivalent SQL query along with the GUI configuration JSON
5. THE ManiReports_System SHALL allow GUI-built reports to be scheduled and exported using the same mechanisms as SQL-based Custom_Reports

### Requirement 16: At-Risk Learner Detection

**User Story:** As a training coordinator, I want the system to automatically identify learners at risk of failing or disengaging based on configurable rules, so that I can intervene proactively.

#### Acceptance Criteria

1. THE ManiReports_System SHALL provide a rule configuration interface where administrators can define At_Risk_Learner detection thresholds including minimum time spent, maximum days since login, and minimum completion percentage
2. WHEN the at-risk detection Cron_Task executes, THE ManiReports_System SHALL evaluate all active learners against configured rules and calculate a risk score from 0 to 100
3. WHEN a learner meets at-risk criteria, THE ManiReports_System SHALL flag the learner record and optionally send an email notification to designated managers
4. THE ManiReports_System SHALL provide an at-risk learners dashboard displaying flagged users with their risk scores, contributing factors, and last activity date
5. WHEN a manager views an At_Risk_Learner record, THE ManiReports_System SHALL provide an option to acknowledge the alert and add notes

### Requirement 17: Interactive Drill-Down Reports

**User Story:** As a data analyst, I want to click on chart data points to drill down into detailed filtered views, so that I can investigate anomalies and trends without creating multiple reports.

#### Acceptance Criteria

1. WHEN a user clicks on a chart data point, THE ManiReports_System SHALL open a detailed report view filtered to the selected dimension and value
2. WHEN a drill-down report is displayed, THE ManiReports_System SHALL show the applied filters prominently and allow the user to modify or remove them
3. WHEN a user exports from a drill-down view, THE ManiReports_System SHALL generate a file containing only the filtered dataset matching the current view
4. THE ManiReports_System SHALL maintain drill-down navigation history allowing users to navigate back to previous views
5. THE ManiReports_System SHALL support drill-down on line charts, bar charts, and pie charts with appropriate filter context

### Requirement 18: API Endpoints for External Integration

**User Story:** As a business intelligence developer, I want JSON API endpoints to retrieve report data programmatically, so that I can integrate ManiReports data into external BI tools or mobile applications.

#### Acceptance Criteria

1. THE ManiReports_System SHALL provide RESTful JSON API endpoints for retrieving dashboard data, report results, and metadata
2. WHEN an external client requests data via API, THE ManiReports_System SHALL authenticate the request using Moodle web service tokens
3. WHEN an API request is authenticated, THE ManiReports_System SHALL enforce the same capability checks applied to UI access
4. THE ManiReports_System SHALL support pagination parameters on API endpoints returning large datasets with page size limits
5. THE ManiReports_System SHALL return appropriate HTTP status codes and error messages in JSON format when API requests fail

### Requirement 19: xAPI and Video Analytics Integration

**User Story:** As an advanced analytics user, I want to incorporate xAPI event data and video engagement metrics into engagement scores, so that I can measure learning activities beyond traditional Moodle interactions.

#### Acceptance Criteria

1. WHERE the xAPI logstore plugin is installed, WHEN the engagement calculation runs, THE ManiReports_System SHALL query xAPI statement data and include it in engagement metrics
2. WHERE video activity plugins are present, WHEN the engagement calculation runs, THE ManiReports_System SHALL extract video watch time and completion data
3. THE ManiReports_System SHALL provide a configuration setting to enable or disable xAPI integration
4. WHEN xAPI data is available, THE ManiReports_System SHALL display xAPI-based engagement metrics in dedicated dashboard widgets
5. THE ManiReports_System SHALL gracefully handle absence of xAPI or video plugins by excluding those metrics without causing errors

### Requirement 20: Performance and Scalability

**User Story:** As a system administrator of a large Moodle installation, I want the plugin to handle thousands of users and courses efficiently, so that reporting does not degrade site performance.

#### Acceptance Criteria

1. THE ManiReports_System SHALL create database indexes on userid, courseid, and date columns in all time tracking and summary tables
2. THE ManiReports_System SHALL implement pagination for all reports displaying more than 100 rows with configurable page size
3. THE ManiReports_System SHALL execute Pre_Aggregation tasks during off-peak hours as configured by administrators
4. THE ManiReports_System SHALL limit concurrent report executions to prevent database overload with queuing for excess requests
5. THE ManiReports_System SHALL complete dashboard loads within 3 seconds for installations with up to 10000 active users when using cached data

### Requirement 21: Security and Data Protection

**User Story:** As a security officer, I want the plugin to sanitize all inputs, use prepared statements, and enforce strict access controls, so that the system is protected against SQL injection and unauthorized data access.

#### Acceptance Criteria

1. THE ManiReports_System SHALL use prepared statements with parameter binding for all database queries containing user-supplied input
2. WHEN processing Custom_Report SQL queries, THE ManiReports_System SHALL validate queries against a whitelist of allowed tables and reject queries containing DROP, DELETE, UPDATE, or INSERT statements
3. THE ManiReports_System SHALL sanitize all user input using Moodle's required_param and optional_param functions with appropriate PARAM types
4. THE ManiReports_System SHALL restrict Custom_Report creation to users with administrator or specifically granted custom report capability
5. THE ManiReports_System SHALL log all failed authorization attempts in the Audit_Log for security monitoring

### Requirement 22: Privacy and Compliance

**User Story:** As a privacy officer, I want controls to disable time tracking, configure data retention, and comply with privacy regulations, so that the plugin respects user privacy and organizational policies.

#### Acceptance Criteria

1. THE ManiReports_System SHALL provide a setting to globally disable the Time_Tracking_Engine including JavaScript Heartbeat functionality
2. THE ManiReports_System SHALL provide retention period settings for Audit_Log entries, report run history, and cached data
3. WHEN retention periods expire, THE ManiReports_System SHALL automatically delete old records via scheduled Cron_Task
4. THE ManiReports_System SHALL implement Moodle privacy API to support data export and deletion requests for GDPR compliance
5. THE ManiReports_System SHALL provide a privacy policy statement describing what data is collected and how it is used

### Requirement 23: Resilience and Error Handling

**User Story:** As a system administrator, I want scheduled jobs to retry on failure and provide clear error logs, so that I can troubleshoot issues and ensure reliable report delivery.

#### Acceptance Criteria

1. WHEN a scheduled report generation fails, THE ManiReports_System SHALL retry the operation up to 3 times with exponential backoff delays
2. WHEN a Cron_Task encounters an error, THE ManiReports_System SHALL log the full error message, stack trace, and context to the Moodle error log
3. THE ManiReports_System SHALL provide an administrative interface displaying failed scheduled reports with error details and manual retry options
4. WHEN a report execution exceeds the configured timeout, THE ManiReports_System SHALL terminate the query and log a timeout error
5. THE ManiReports_System SHALL send email notifications to administrators when critical Cron_Tasks fail repeatedly

### Requirement 24: Maintainability and Code Quality

**User Story:** As a plugin maintainer, I want well-structured modular code with documentation and tests, so that the plugin is easy to understand, extend, and maintain over time.

#### Acceptance Criteria

1. THE ManiReports_System SHALL organize code into logical namespaces and classes following Moodle coding standards
2. THE ManiReports_System SHALL include PHPDoc comments for all public classes, methods, and functions describing parameters, return values, and purpose
3. THE ManiReports_System SHALL provide PHPUnit test coverage for critical API classes including report builder, analytics engine, and time tracking
4. THE ManiReports_System SHALL include JavaScript unit tests for AMD modules handling dashboard interactions and AJAX requests
5. THE ManiReports_System SHALL provide developer documentation describing plugin architecture, database schema, and extension points

### Requirement 25: Cloud Offload for Heavy Operations (Phase 4)

**User Story:** As a system administrator managing large-scale email campaigns and certificate generation, I want to offload heavy processing to external cloud infrastructure, so that Moodle server performance is not impacted by bulk operations.

#### Acceptance Criteria

1. WHEN cloud offload is enabled, THE ManiReports_System SHALL provide configuration options for cloud endpoint, authentication token, AWS region, SQS queue URL, and email provider selection
2. WHEN a bulk email job is created, THE ManiReports_System SHALL batch recipients into configurable chunk sizes and create job records in the manireports_cloud_jobs table
3. WHEN a cloud job is submitted, THE ManiReports_System SHALL send job JSON to the configured cloud endpoint via API Gateway or directly to SQS queue with proper authentication
4. WHEN the cloud worker processes a job, THE ManiReports_System SHALL accept callback requests at the cloud_callback endpoint and validate HMAC signatures before updating job status
5. WHEN certificate generation is requested, THE ManiReports_System SHALL support cloud-based PDF generation with presigned S3/R2 URLs or local generation as fallback
6. WHEN a cloud job fails, THE ManiReports_System SHALL implement retry logic with exponential backoff and move failed jobs to a dead letter queue after maximum retry attempts
7. THE ManiReports_System SHALL provide a job monitoring UI displaying job status, recipient status, progress percentage, and manual retry options for failed jobs
8. THE ManiReports_System SHALL support multiple email providers including AWS SES, SendGrid, Mailgun, and custom SMTP with provider-specific configuration
9. WHERE IOMAD is installed, THE ManiReports_System SHALL include tenant_id in cloud job payloads and enforce company isolation in callback processing
10. THE ManiReports_System SHALL provide fallback to local processing when cloud offload is disabled or cloud services are unavailable
11. THE ManiReports_System SHALL log all cloud job operations to audit logs including job creation, status updates, and callback events
12. THE ManiReports_System SHALL handle up to 50,000 email recipients per job with batching and support configurable rate limiting to comply with email provider quotas
