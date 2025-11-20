# Implementation Plan

- [x] 1. Set up plugin foundation and core structure





  - Create plugin directory structure with classes/, db/, amd/, templates/, ui/, lang/ folders
  - Implement version.php with plugin metadata and version information
  - Create lib.php with plugin lifecycle hooks
  - Implement settings.php with admin configuration options
  - Create lang/en/local_manireports.php with initial language strings
  - _Requirements: 24.1, 24.2_

- [x] 2. Implement database schema and installation




  - [x] 2.1 Create db/install.xml with all table definitions


    - Define manireports_customreports table for custom report storage
    - Define manireports_schedules and manireports_schedule_recipients tables
    - Define manireports_report_runs table for execution history
    - Define manireports_usertime_sessions and manireports_usertime_daily tables
    - Define manireports_scorm_summary table for SCORM aggregations
    - Define manireports_cache_summary table for pre-computed metrics
    - Define manireports_dashboards and manireports_dashboard_widgets tables
    - Define manireports_audit_logs table for compliance tracking
    - _Requirements: 5.1, 9.2, 10.1, 11.2, 12.1_
  

  - [x] 2.2 Create db/access.php with capability definitions

    - Define local/manireports:viewadmindashboard capability
    - Define local/manireports:viewmanagerdashboard capability
    - Define local/manireports:viewteacherdashboard capability
    - Define local/manireports:viewstudentdashboard capability
    - Define local/manireports:managereports capability
    - Define local/manireports:schedule capability
    - Define local/manireports:customreports capability
    - Map capabilities to default roles (manager, teacher, student)
    - _Requirements: 14.1, 14.2_

  

  - [x] 2.3 Create db/tasks.php with scheduled task definitions

    - Define time_aggregation task (hourly)
    - Define cache_builder task (every 6 hours)
    - Define report_scheduler task (every 15 minutes)
    - Define scorm_summary task (hourly)
    - Define cleanup_old_data task (daily)
    - _Requirements: 5.2, 9.3, 10.1, 11.1, 22.3_

- [x] 3. Implement IOMAD filter and multi-tenancy support



  - Create classes/api/iomad_filter.php class
  - Implement is_iomad_installed() method to detect IOMAD
  - Implement get_user_companies() method to retrieve user's company assignments
  - Implement apply_company_filter() method to modify SQL queries with company constraints
  - Implement get_company_selector_options() method for UI dropdown
  - _Requirements: 3.1, 3.2, 3.3, 3.4_

- [x] 4. Implement core report builder API





  - [x] 4.1 Create report builder foundation

    - Create classes/api/report_builder.php class
    - Implement execute_report() method with parameter binding
    - Implement validate_sql() method with whitelist checking
    - Implement apply_filters() method for dynamic filtering
    - _Requirements: 6.1, 6.2, 6.3, 21.2_
  

  - [x] 4.2 Create SQL validator and security layer


    - Implement SQL whitelist for allowed tables
    - Block DDL statements (DROP, CREATE, ALTER, TRUNCATE)
    - Block DML statements (INSERT, UPDATE, DELETE)
    - Validate parameter placeholders match query
    - Implement query timeout enforcement
    - _Requirements: 6.1, 6.4, 21.1, 21.2_




  
  - [x] 4.3 Create custom report management

    - Implement CRUD operations for manireports_customreports table
    - Create report save/update/delete methods
    - Implement report listing with filtering
    - Add audit logging for report operations
    - _Requirements: 6.1, 6.2, 12.3_

- [-] 5. Implement prebuilt core reports


  - [x] 5.1 Create base report class

    - Create classes/reports/base_report.php abstract class
    - Define get_sql() abstract method
    - Define get_columns() abstract method
    - Implement execute() method using report_builder
    - Implement apply_iomad_filter() integration
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5_
  




  - [x] 5.2 Implement course completion report
    - Create classes/reports/course_completion.php extending base_report
    - Write SQL query joining mdl_course_completions and mdl_course
    - Implement date range filtering
    - Implement company filtering for IOMAD
    - Calculate completion percentages
    - _Requirements: 1.1, 4.1_
  
  - [x] 5.3 Implement course progress report

    - Create classes/reports/course_progress.php extending base_report
    - Write SQL query for per-user completion data
    - Calculate progress percentages from activity completions
    - Implement user and course filtering
    - _Requirements: 4.2_


  

  - [x] 5.4 Implement SCORM summary report

    - Create classes/reports/scorm_summary.php extending base_report
    - Write SQL query aggregating from manireports_scorm_summary
    - Display attempt counts, completion status, average time
    - Implement SCORM activity filtering


    - _Requirements: 4.3, 10.2_

  

  - [x] 5.5 Implement user engagement report

    - Create classes/reports/user_engagement.php extending base_report
    - Write SQL query joining manireports_usertime_daily
    - Calculate active days for last 7 and 30 days
    - Sum time spent per user

    - _Requirements: 4.4_
  
  - [x] 5.6 Implement quiz attempts report

    - Create classes/reports/quiz_attempts.php extending base_report
    - Write SQL query from mdl_quiz_attempts
    - Calculate attempt counts and average scores
    - Group by course and quiz
    - _Requirements: 4.5_

- [x] 6. Implement time tracking engine



  - [x] 6.1 Create time tracking API classes


    - Create classes/api/time_engine.php class
    - Implement record_heartbeat() method for session updates
    - Implement close_inactive_sessions() method

    - Implement get_user_time() method for retrieving aggregated data
    - _Requirements: 9.2, 9.4_
  

  - [x] 6.2 Create JavaScript heartbeat module
    - Create amd/src/heartbeat.js AMD module
    - Implement periodic heartbeat sending (20-30 seconds randomized)
    - Send AJAX requests with userid, courseid, timestamp

    - Handle network errors gracefully
    - Store last heartbeat time in sessionStorage
    - _Requirements: 9.1_

  
  - [x] 6.3 Create AJAX endpoint for heartbeat
    - Create ui/ajax/heartbeat.php endpoint

    - Validate sesskey and user authentication
    - Call time_engine->record_heartbeat()
    - Return JSON success/error response

    - _Requirements: 9.2_
  
  - [x] 6.4 Create time aggregation scheduled task
    - Create classes/tasks/time_aggregation.php task
    - Implement execute() method
    - Close inactive sessions (> 10 minutes)
    - Aggregate sessions into daily summaries
    - Update manireports_usertime_daily table
    - _Requirements: 9.3_

- [x] 7. Implement SCORM analytics aggregation



  - [x] 7.1 Create SCORM summary task


    - Create classes/tasks/scorm_summary.php task
    - Query mdl_scorm_scoes_track for raw tracking data
    - Calculate attempt counts per user/SCORM

    - Calculate total time from tracking entries
    - Determine completion status
    - Calculate average scores
    - Update manireports_scorm_summary table
    - _Requirements: 10.1, 10.2_
  

  - [x] 7.2 Implement incremental SCORM updates
    - Track last processed timestamp
    - Process only new tracking entries
    - Update existing summary records
    - Optimize for large datasets
    - _Requirements: 10.5_

- [x] 8. Implement caching and pre-aggregation



  - [x] 8.1 Create cache manager API



    - Create classes/api/cache_manager.php class
    - Implement get_cached_data() method
    - Implement set_cached_data() method with TTL
    - Implement invalidate_cache() method
    - Generate cache keys from report type and filters
    - _Requirements: 11.2, 11.3_
  

  - [x] 8.2 Create cache builder scheduled task


    - Create classes/tasks/cache_builder.php task
    - Identify reports configured for pre-aggregation
    - Execute heavy queries (12-month trends)
    - Store results as JSON in manireports_cache_summary


    - Update lastgenerated timestamp
    - _Requirements: 11.1, 11.2_

  
  - [x] 8.3 Integrate caching into report execution



    - Check cache before executing queries
    - Return cached data if valid (within TTL)
    - Execute query and cache result if cache miss
    - Implement cache warming for common queries
    - _Requirements: 11.3, 11.4_

- [x] 9. Implement analytics engine
  - [x] 9.1 Create analytics engine API


    - Create classes/api/analytics_engine.php class
    - Implement calculate_engagement_score() method
    - Implement get_activity_metrics() method
    - Define engagement score formula with configurable weights
    - _Requirements: Req 16 (at-risk detection foundation)_


  
  - [x] 9.2 Implement at-risk learner detection
    - Implement detect_at_risk_learners() method
    - Implement evaluate_rules() method
    - Calculate risk scores based on time, login, completion thresholds
    - Flag learners with risk_score >= 50


    - Store at-risk status in analytics results

    - _Requirements: 16.2, 16.3_
  
  - [x] 9.3 Create at-risk configuration interface


    - Add settings for risk thresholds (time, days, completion)
    - Create admin UI for rule configuration
    - Implement rule validation
    - _Requirements: 16.1_



- [x] 10. Implement export engine
  - [x] 10.1 Create export engine foundation
    - Create classes/api/export_engine.php class
    - Implement export() method with format routing
    - Implement file storage using Moodle File API
    - Return stored_file object

    - _Requirements: 13.1, 13.5_

  
  - [x] 10.2 Implement CSV export
    - Implement export_csv() method
    - Generate UTF-8 with BOM

    - Use comma delimiter and double-quote enclosure
    - Include headers in first row
    - Format dates as Y-m-d H:i:s
    - _Requirements: 13.2_
  
  - [x] 10.3 Implement XLSX export
    - Implement export_xlsx() method using PHPSpreadsheet
    - Create workbook with formatted headers
    - Auto-size columns
    - Apply number and date formatting
    - _Requirements: 13.3_
  
  - [x] 10.4 Implement PDF export


    - Implement export_pdf() method using mPDF or TCPDF
    - Generate A4 portrait document
    - Include report title and timestamp
    - Render table with alternating row colors
    - Optionally embed chart images
    - _Requirements: 13.4_

- [x] 11. Implement report scheduling system
  - [x] 11.1 Create scheduler API


    - Create classes/api/scheduler.php class
    - Implement create_schedule() method
    - Implement calculate_next_run() method for frequency calculation
    - Implement get_due_schedules() method
    - _Requirements: 5.1_
  

  - [x] 11.2 Create schedule management UI

    - Create ui/schedules.php page
    - Display list of schedules with status
    - Implement create/edit/delete schedule forms
    - Add recipient management interface
    - Implement capability checks
    - _Requirements: 5.1_
  


  - [x] 11.3 Create report scheduler task
    - Create classes/tasks/report_scheduler.php task
    - Query schedules where next_run <= current_time
    - Create report_run record with status 'running'
    - Execute report with configured parameters
    - Generate export file
    - Send email with attachment
    - Update report_run with completion status
    - Calculate and update next_run
    - Implement retry logic with exponential backoff
    - _Requirements: 5.2, 5.3, 5.4, 23.1_

  

  - [x] 11.4 Implement email delivery
    - Use Moodle email_to_user() function
    - Attach generated file
    - Include report summary in email body
    - Handle email failures gracefully
    - Log delivery status
    - _Requirements: 5.3_

- [x] 12. Implement audit logging
  - [x] 12.1 Create audit logger utility


    - Create classes/api/audit_logger.php class
    - Implement log_action() method
    - Store userid, action, objecttype, objectid, details, timestamp
    - Insert into manireports_audit_logs table
    - _Requirements: 12.3_
  


  - [x] 12.2 Integrate audit logging throughout plugin
    - Log report creation/modification/deletion
    - Log schedule creation/modification/deletion
    - Log dashboard creation/modification/deletion
    - Log failed authorization attempts
    - Log configuration changes
    - _Requirements: 12.3, 21.5_

  
  - [x] 12.3 Create audit log viewer UI

    - Create ui/audit.php page
    - Display audit logs with filtering (user, date, action)
    - Implement pagination
    - Add export capability
    - Restrict access to administrators
    - _Requirements: 12.4_
  

  - [x] 12.4 Implement report run history

    - Display run history for each report
    - Show start/finish times, duration, status
    - Link to generated files
    - Display error messages for failed runs
    - _Requirements: 12.2_

- [x] 13. Implement role-based dashboards



  - [x] 13.1 Create dashboard renderer

    - Create classes/output/dashboard_renderer.php class
    - Implement render_dashboard() method
    - Implement render_widget() method
    - Support multiple widget types (KPI, chart, table)
    - _Requirements: 2.1, 2.2, 2.3, 2.4_
  

  - [x] 13.2 Create admin dashboard





    - Create templates/dashboard_admin.mustache template
    - Create ui/dashboard.php with role detection
    - Display site-wide statistics widgets
    - Show all companies (IOMAD)
    - Include course usage heatmaps
    - Display inactive users widget
    - _Requirements: 2.1_


  
  - [x] 13.3 Create company manager dashboard
    - Create templates/dashboard_manager.mustache template
    - Filter all data to manager's company
    - Display company-specific statistics
    - Show department reports
    - Include completion and progress widgets


    - _Requirements: 2.2, 3.2_
  
  - [x] 13.4 Create teacher dashboard
    - Create templates/dashboard_teacher.mustache template
    - Display student progress for teacher's courses
    - Show activity completion statistics
    - Include quiz analytics widget

    - Display time spent per user
    - _Requirements: 2.3_
  
  - [x] 13.5 Create student dashboard

    - Create templates/dashboard_student.mustache template
    - Display personal progress

    - Show time tracking statistics
    - Display course completion status
    - Show upcoming activity deadlines
    - _Requirements: 2.4_

- [x] 14. Implement course completion dashboard (MVP)

  - Create ui/course_completion.php page
  - Implement date range filter (30/90 days)
  - Implement company filter for IOMAD
  - Display table with course name, enrollment count, completion %
  - Render trend chart using Chart.js
  - Implement CSV export button
  - Ensure load time < 2 seconds for 100 courses
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5_

- [x] 15. Implement chart rendering system
  - [x] 15.1 Create chart factory and base classes


    - Create classes/charts/base_chart.php abstract class
    - Create classes/charts/chart_factory.php factory
    - Define get_chart_data() abstract method
    - Define get_chart_config() abstract method
    - _Requirements: 7.3_


  
  - [x] 15.2 Implement chart type classes
    - Create classes/charts/line_chart.php for trend charts
    - Create classes/charts/bar_chart.php for comparisons
    - Create classes/charts/pie_chart.php for distributions


    - Each class returns Chart.js compatible configuration
    - _Requirements: 7.3_
  
  - [x] 15.3 Create Chart.js AMD module
    - Create amd/src/charts.js module
    - Implement renderChart() function
    - Fetch chart data via AJAX


    - Initialize Chart.js with configuration
    - Handle chart updates on filter changes
    - _Requirements: 7.3_

- [x] 16. Implement AJAX filter system
  - [x] 16.1 Create filter JavaScript module
    - Create amd/src/filters.js AMD module

    - Implement filter change event handlers
    - Debounce filter inputs (300ms)
    - Send AJAX requests to update widgets
    - Update URL parameters for bookmarking
    - Store filter state in sessionStorage
    - _Requirements: 7.2_
  
  - [x] 16.2 Create filter templates

    - Create templates/filters.mustache template
    - Include date range picker
    - Include company selector (IOMAD)
    - Include course autocomplete
    - Include user autocomplete
    - Add quick filter buttons (7/30/90 days)
    - _Requirements: 7.2_
  
  - [x] 16.3 Create AJAX endpoints for data fetching
    - Create ui/ajax/dashboard_data.php endpoint
    - Create ui/ajax/report_data.php endpoint
    - Validate parameters and capabilities
    - Apply filters to queries
    - Return JSON responses
    - _Requirements: 7.2_

- [x] 17. Implement responsive UI foundation
  - Create base CSS using Bootstrap grid
  - Implement mobile-responsive layouts
  - Create loading indicators and skeleton loaders
  - Implement toast notifications for success/info messages
  - Create alert boxes for errors/warnings
  - Ensure all dashboards work on desktop, tablet, mobile
  - _Requirements: 7.1, 7.4, 7.5_

- [x] 18. Implement custom dashboard builder (Phase 2)







  - [x] 18.1 Create dashboard management API

    - Create classes/api/dashboard_manager.php class
    - Implement create_dashboard() method
    - Implement save_dashboard_layout() method
    - Implement get_dashboard() method
    - Implement delete_dashboard() method
    - _Requirements: 8.4_

  
  - [x] 18.2 Create widget configuration system

    - Define widget types (KPI, line, bar, pie, table)
    - Create widget configuration schema
    - Implement widget data source mapping
    - Store widget configs as JSON
    - _Requirements: 8.1, 8.3_
  
  - [x] 18.3 Create dashboard builder UI



    - Create ui/dashboard_builder.php page
    - Implement drag-and-drop grid layout
    - Create widget palette
    - Implement widget configuration modal
    - Add save/cancel buttons
    - Implement dashboard scope selection (personal/global/company)
    - _Requirements: 8.1, 8.2, 8.3, 8.5_
  

  - [x] 18.4 Create dashboard builder JavaScript
    - Create amd/src/dashboard_builder.js module
    - Implement drag-and-drop using jQuery sortable
    - Handle widget add/remove/resize
    - Implement widget configuration UI
    - Save layout as JSON via AJAX
    - _Requirements: 8.2, 8.4_

- [-] 19. Implement GUI report builder (Phase 3)



  - [x] 19.1 Create query builder API

    - Create classes/api/query_builder.php class
    - Implement build_sql_from_config() method
    - Support table selection and joins
    - Support column selection
    - Support WHERE conditions
    - Support GROUP BY and ORDER BY
    - _Requirements: 15.4_
  

  - [x] 19.2 Create GUI report builder UI

    - Create ui/report_builder_gui.php page
    - Display table selector with available Moodle tables
    - Display column selector with data types
    - Implement filter builder with appropriate input controls
    - Add grouping and sorting options
    - Preview generated SQL (read-only)
    - _Requirements: 15.1, 15.2, 15.3_
  
  - [x] 19.3 Integrate GUI builder with report system










    - Save GUI configuration as JSON
    - Generate SQL from configuration on execution
    - Allow GUI reports to be scheduled
    - Support export in all formats
    - _Requirements: 15.4, 15.5_

- [x] 20. Implement drill-down functionality (Phase 3)





  - Create click handlers on chart data points
  - Extract dimension and value from clicked element
  - Build filtered report URL with parameters
  - Open filtered report view
  - Display applied filters prominently
  - Maintain navigation history
  - Support export from drill-down view
  - _Requirements: 17.1, 17.2, 17.3, 17.4, 17.5_

- [x] 21. Implement API endpoints for external integration (Phase 3)




  - [x] 21.1 Create web service definitions


    - Create db/services.php with service definitions
    - Define external functions for dashboard data
    - Define external functions for report execution
    - Define external functions for metadata retrieval
    - _Requirements: 18.1_
  
  - [x] 21.2 Implement external API classes


    - Create classes/external/api.php class
    - Implement get_dashboard_data_parameters() and execute()
    - Implement get_report_data_parameters() and execute()
    - Implement capability checks in all methods
    - Return JSON-serializable data structures
    - _Requirements: 18.2, 18.3_
  
  - [x] 21.3 Implement pagination support

    - Add page and pagesize parameters
    - Calculate total pages
    - Return pagination metadata
    - Limit maximum page size
    - _Requirements: 18.4_
  
  - [x] 21.4 Implement error handling for API


    - Return appropriate HTTP status codes
    - Return error messages in JSON format
    - Log API errors
    - _Requirements: 18.5_

- [x] 22. Implement xAPI integration (Phase 3)
  - Check for xAPI logstore plugin installation
  - Query xAPI statements for engagement data
  - Extract video watch time from xAPI statements
  - Integrate xAPI metrics into engagement calculations
  - Create xAPI-specific dashboard widgets
  - Add configuration toggle for xAPI integration
  - Handle absence of xAPI gracefully
  - _Requirements: 19.1, 19.2, 19.3, 19.4, 19.5_

- [x] 23. Implement at-risk learner dashboard (Phase 3)
  - Create ui/at_risk.php page
  - Display list of flagged learners with risk scores
  - Show contributing factors (low time, no login, low completion)
  - Display last activity date
  - Implement acknowledge button for managers
  - Add notes field for intervention tracking
  - Send optional email notifications to managers
  - _Requirements: 16.3, 16.4, 16.5_

- [x] 24. Implement privacy API for GDPR compliance
  - Create classes/privacy/provider.php class
  - Implement get_metadata() method describing data storage
  - Implement export_user_data() method
  - Implement delete_data_for_user() method
  - Implement delete_data_for_users() method
  - Include time tracking, audit logs, custom reports in export
  - _Requirements: 22.4_

- [x] 25. Implement data retention and cleanup
  - Create classes/tasks/cleanup_old_data.php task
  - Implement cleanup for audit logs based on retention setting
  - Implement cleanup for report runs based on retention setting
  - Implement cleanup for cached data based on TTL
  - Implement cleanup for old session data
  - Add admin settings for retention periods
  - _Requirements: 22.2, 22.3_

- [x] 26. Implement performance optimizations



  - Add database indexes on userid, courseid, date columns
  - Implement pagination for all large result sets
  - Configure pre-aggregation tasks for off-peak hours
  - Implement concurrent execution limits for reports
  - Add query timeout enforcement
  - Optimize JavaScript with minification
  - Implement request debouncing on filters
  - _Requirements: 20.1, 20.2, 20.3, 20.4, 20.5_

- [x] 27. Implement security hardening


  - Review all user input validation
  - Ensure all queries use prepared statements
  - Implement SQL whitelist enforcement
  - Add CSRF protection to all forms
  - Sanitize all output for XSS prevention
  - Implement rate limiting on API endpoints
  - Add security headers to responses
  - _Requirements: 21.1, 21.2, 21.3, 21.4, 21.5_

- [x] 28. Implement error handling and resilience



  - Add try-catch blocks around all critical operations
  - Implement retry logic for scheduled tasks
  - Log all errors with context
  - Create admin UI for failed job management
  - Implement manual retry for failed schedules
  - Send email alerts on repeated failures
  - Add timeout handling for long queries
  - _Requirements: 23.1, 23.2, 23.3, 23.4, 23.5_

- [x] 29. Create comprehensive language strings


  - Add all UI labels and messages to lang file
  - Include error messages
  - Include help text for settings
  - Include capability descriptions
  - Ensure all strings use placeholders for dynamic content
  - _Requirements: 24.2_

- [x] 30. Create documentation



  - Write user guide for dashboards and reports
  - Write administrator guide for configuration
  - Write developer documentation for API
  - Create troubleshooting guide
  - Document database schema
  - Create installation guide
  - _Requirements: 24.5_

- [ ]* 31. Write PHPUnit tests for core functionality
  - Write tests for report_builder class
  - Write tests for analytics_engine class
  - Write tests for time_engine class
  - Write tests for iomad_filter class
  - Write tests for cache_manager class
  - Write tests for scheduler class
  - Achieve 70%+ code coverage for API classes
  - _Requirements: 24.3_

- [ ]* 32. Write JavaScript unit tests
  - Write tests for dashboard.js module
  - Write tests for filters.js module
  - Write tests for charts.js module
  - Write tests for heartbeat.js module
  - Use Mocha or Jest for testing
  - _Requirements: 24.4_

- [ ] 33. Perform integration and functional testing
  - Test complete report generation workflow
  - Test scheduled report execution and delivery
  - Test dashboard rendering with filters
  - Test time tracking end-to-end
  - Test IOMAD company isolation
  - Test all export formats
  - Test capability enforcement
  - Verify performance benchmarks
  - _Requirements: All requirements_

- [ ] 34. Final polish and deployment preparation
  - Review all code for Moodle coding standards compliance
  - Run Moodle Code Checker
  - Optimize database queries
  - Minify JavaScript and CSS
  - Test on multiple Moodle versions (4.0-4.4)
  - Test on different databases (MySQL, PostgreSQL)
  - Create release notes
  - Package plugin for distribution
  - _Requirements: 24.1, 24.2_
