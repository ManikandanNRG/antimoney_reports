<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Plugin strings are defined here.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin name.
$string['pluginname'] = 'ManiReports';

// Capabilities.
$string['manireports:viewadmindashboard'] = 'View admin dashboard';
$string['manireports:viewmanagerdashboard'] = 'View company manager dashboard';
$string['manireports:viewteacherdashboard'] = 'View teacher dashboard';
$string['manireports:viewstudentdashboard'] = 'View student dashboard';
$string['manireports:managereports'] = 'Manage reports';
$string['manireports:schedule'] = 'Schedule reports';
$string['manireports:customreports'] = 'Create custom reports';

// General settings.
$string['generalsettings'] = 'General Settings';
$string['enabletimetracking'] = 'Enable time tracking';
$string['enabletimetracking_desc'] = 'Enable JavaScript-based time tracking with heartbeat mechanism';
$string['heartbeatinterval'] = 'Heartbeat interval';
$string['heartbeatinterval_desc'] = 'Time in seconds between heartbeat signals (20-30 recommended)';
$string['sessiontimeout'] = 'Session timeout';
$string['sessiontimeout_desc'] = 'Time in minutes before a session is considered inactive';

// Cache settings.
$string['cachesettings'] = 'Cache Settings';
$string['cachettl_dashboard'] = 'Dashboard cache TTL';
$string['cachettl_dashboard_desc'] = 'Time to live for dashboard widget cache in seconds (default: 3600 = 1 hour)';
$string['cachettl_trends'] = 'Trend reports cache TTL';
$string['cachettl_trends_desc'] = 'Time to live for trend report cache in seconds (default: 21600 = 6 hours)';
$string['cachettl_historical'] = 'Historical reports cache TTL';
$string['cachettl_historical_desc'] = 'Time to live for historical report cache in seconds (default: 86400 = 24 hours)';

// Report settings.
$string['reportsettings'] = 'Report Execution Settings';
$string['querytimeout'] = 'Query timeout';
$string['querytimeout_desc'] = 'Maximum execution time for report queries in seconds';
$string['maxconcurrentreports'] = 'Maximum concurrent reports';
$string['maxconcurrentreports_desc'] = 'Maximum number of reports that can execute simultaneously';

// Data retention settings.
$string['retentionsettings'] = 'Data Retention Settings';
$string['auditlogretention'] = 'Audit log retention';
$string['auditlogretention_desc'] = 'Number of days to retain audit log entries';
$string['reportrunretention'] = 'Report run retention';
$string['reportrunretention_desc'] = 'Number of days to retain report run history';

// At-risk learner settings.
$string['atrisksettings'] = 'At-Risk Learner Detection';
$string['atrisk_mintime'] = 'Minimum time spent (hours)';
$string['atrisk_mintime_desc'] = 'Minimum hours spent in course to avoid at-risk flag';
$string['atrisk_maxdays'] = 'Maximum days since login';
$string['atrisk_maxdays_desc'] = 'Maximum days since last login before at-risk flag';
$string['atrisk_mincompletion'] = 'Minimum completion percentage';
$string['atrisk_mincompletion_desc'] = 'Minimum completion percentage to avoid at-risk flag';

// Dashboard strings.
$string['dashboard'] = 'Dashboard';
$string['admindashboard'] = 'Admin Dashboard';
$string['managerdashboard'] = 'Manager Dashboard';
$string['teacherdashboard'] = 'Teacher Dashboard';
$string['studentdashboard'] = 'Student Dashboard';

// Report strings.
$string['reports'] = 'Reports';
$string['customreports'] = 'Custom Reports';
$string['customreport'] = 'Custom Report';
$string['schedules'] = 'Scheduled Reports';
$string['createcustomreport'] = 'Create Custom Report';
$string['nocustomreports'] = 'No custom reports found. Create one to get started.';
$string['reportname'] = 'Report Name';
$string['reporttype'] = 'Report Type';
$string['sqlreport'] = 'SQL Report';
$string['sqlquery'] = 'SQL Query';
$string['sqlquery_help'] = 'Enter a SELECT query using Moodle table notation {tablename}. Only whitelisted tables are allowed. Use named parameters like :paramname for dynamic values.';
$string['allowedtables'] = 'Allowed Tables';
$string['savereport'] = 'Save Report';
$string['reportcreated'] = 'Report created successfully';
$string['reportupdated'] = 'Report updated successfully';
$string['reportdeleted'] = 'Report deleted successfully';
$string['confirmdelete'] = 'Are you sure you want to delete this report? This action cannot be undone.';
$string['auditlog'] = 'Audit Log';
$string['auditlogs'] = 'Audit Logs';
$string['action'] = 'Action';
$string['objecttype'] = 'Object Type';
$string['objectid'] = 'Object ID';
$string['details'] = 'Details';
$string['ipaddress'] = 'IP Address';
$string['noauditlogs'] = 'No audit logs found';
$string['viewauditlog'] = 'View Audit Log';
$string['attempts'] = 'Attempts';
$string['score'] = 'Score';
$string['lastaccess'] = 'Last Access';
$string['createschedule'] = 'Create Schedule';
$string['editschedule'] = 'Edit Schedule';
$string['deleteschedule'] = 'Delete Schedule';
$string['schedulename'] = 'Schedule Name';
$string['frequency'] = 'Frequency';
$string['daily'] = 'Daily';
$string['weekly'] = 'Weekly';
$string['monthly'] = 'Monthly';
$string['recipients'] = 'Recipients';
$string['addrecipient'] = 'Add Recipient';
$string['enabled'] = 'Enabled';
$string['disabled'] = 'Disabled';
$string['lastrun'] = 'Last Run';
$string['nextrun'] = 'Next Run';
$string['status'] = 'Status';
$string['actions'] = 'Actions';
$string['noschedules'] = 'No schedules found';
$string['schedulecreated'] = 'Schedule created successfully';
$string['scheduleupdated'] = 'Schedule updated successfully';
$string['scheduledeleted'] = 'Schedule deleted successfully';
$string['recipients_help'] = 'Enter one email address per line. These recipients will receive the scheduled report.';
$string['coursecompletion'] = 'Course Completion';
$string['coursecompletion_desc'] = 'View course completion statistics with enrollment and completion percentages';
$string['courseprogress'] = 'Course Progress';
$string['courseprogress_desc'] = 'View individual user progress across courses with activity completion tracking';
$string['scormsummary'] = 'SCORM Summary';
$string['scormsummary_desc'] = 'View SCORM activity analytics including attempts, completion, and scores';
$string['userengagement'] = 'User Engagement';
$string['userengagement_desc'] = 'View user engagement metrics including time spent and active days';
$string['quizattempts'] = 'Quiz Attempts';
$string['quizattempts_desc'] = 'View quiz attempt statistics including scores and completion rates';

// Report column strings.
$string['completionpercentage'] = 'Completion %';
$string['progresspercentage'] = 'Progress %';
$string['totalactivities'] = 'Total Activities';
$string['completedactivities'] = 'Completed Activities';
$string['scormname'] = 'SCORM Activity';
$string['scormactivity'] = 'SCORM Activity';
$string['totaltime'] = 'Total Time';
$string['timespent7days'] = 'Time (7 days)';
$string['timespent30days'] = 'Time (30 days)';
$string['activedays7'] = 'Active Days (7)';
$string['activedays30'] = 'Active Days (30)';
$string['quizname'] = 'Quiz';
$string['totalattempts'] = 'Total Attempts';
$string['finishedattempts'] = 'Finished Attempts';
$string['averagescore'] = 'Average Score';
$string['bestscore'] = 'Best Score';
$string['lastattempt'] = 'Last Attempt';
$string['datefrom'] = 'Date From';
$string['dateto'] = 'Date To';
$string['shortname'] = 'Short Name';
$string['enrolled'] = 'Enrolled';
$string['timecompleted'] = 'Time Completed';
$string['quiz'] = 'Quiz';
$string['totalrecords'] = 'Total records: {$a}';

// Common strings.
$string['export'] = 'Export';
$string['exportcsv'] = 'Export CSV';
$string['exportxlsx'] = 'Export Excel';
$string['exportpdf'] = 'Export PDF';
$string['filters'] = 'Filters';
$string['daterange'] = 'Date Range';
$string['company'] = 'Company';
$string['course'] = 'Course';
$string['user'] = 'User';
$string['loading'] = 'Loading...';
$string['nodata'] = 'No data available';
$string['executiontime'] = 'Execution time';
$string['cachedhit'] = 'Cached result';
$string['cachemiss'] = 'Fresh data';
$string['totalusers'] = 'Total Users';
$string['totalcourses'] = 'Total Courses';
$string['totalenrolments'] = 'Total Enrolments';
$string['mycourses'] = 'My Courses';
$string['enrolledcourses'] = 'Enrolled Courses';
$string['completedcourses'] = 'Completed Courses';
$string['activeusers30days'] = 'Active Users (30 days)';
$string['inactiveusers30days'] = 'Inactive Users (30 days)';
$string['completions30days'] = 'Completions (30 days)';
$string['companies'] = 'Companies';
$string['nocompanies'] = 'No companies found';
$string['users'] = 'Users';
$string['courses'] = 'Courses';
$string['courseusage'] = 'Course Usage (Top 10)';
$string['courseusagedesc'] = 'Most accessed courses in the last 30 days';
$string['activeusers'] = 'Active Users';
$string['totalaccesses'] = 'Total Accesses';
$string['nocourseusage'] = 'No course usage data available';
$string['inactiveusers'] = 'Inactive Users';
$string['inactiveusersdesc'] = 'Users who have not logged in for 30 days or more';
$string['daysinactive'] = 'Days Inactive';
$string['noinactiveusers'] = 'No inactive users found';

// Task strings.
$string['task_timeaggregation'] = 'Time tracking aggregation';
$string['task_cachebuilder'] = 'Cache builder';
$string['task_reportscheduler'] = 'Report scheduler';
$string['task_scormsummary'] = 'SCORM summary aggregation';
$string['task_cleanupolddata'] = 'Cleanup old data';

// Error messages.
$string['error:nopermission'] = 'You do not have permission to access this page';
$string['error:invalidparameters'] = 'Invalid parameters provided';
$string['error:reportnotfound'] = 'Report not found';
$string['error:databaseerror'] = 'Database error occurred: {$a}';
$string['error:unexpectederror'] = 'An unexpected error occurred';
$string['error:unsupportedformat'] = 'Unsupported export format: {$a}';
$string['error:schedulenotfound'] = 'Schedule not found';
$string['error:invalidfrequency'] = 'Invalid frequency: {$a}';
$string['error:invalidcharttype'] = 'Invalid chart type: {$a}';
$string['error:invalidsql'] = 'Invalid SQL query. Please check for blocked keywords, non-whitelisted tables, or syntax errors.';
$string['error:querytimeout'] = 'Query execution exceeded timeout limit of {$a} seconds';
$string['error:reportnamerequired'] = 'Report name is required';
$string['error:sqlqueryrequired'] = 'SQL query is required for SQL-type reports';

// Privacy.
$string['privacy:metadata:manireports_time_sessions'] = 'Stores user time tracking session data';
$string['privacy:metadata:manireports_time_sessions:userid'] = 'User ID';
$string['privacy:metadata:manireports_time_sessions:courseid'] = 'Course ID';
$string['privacy:metadata:manireports_time_sessions:sessionstart'] = 'Session start timestamp';
$string['privacy:metadata:manireports_time_sessions:lastupdated'] = 'Last update timestamp';
$string['privacy:metadata:manireports_time_sessions:duration'] = 'Session duration in seconds';

$string['privacy:metadata:manireports_time_daily'] = 'Stores aggregated daily time tracking data';
$string['privacy:metadata:manireports_time_daily:userid'] = 'User ID';
$string['privacy:metadata:manireports_time_daily:courseid'] = 'Course ID';
$string['privacy:metadata:manireports_time_daily:date'] = 'Date';
$string['privacy:metadata:manireports_time_daily:duration'] = 'Total duration in seconds';

$string['privacy:metadata:manireports_audit_logs'] = 'Stores audit trail of user actions';
$string['privacy:metadata:manireports_audit_logs:userid'] = 'User ID';
$string['privacy:metadata:manireports_audit_logs:action'] = 'Action performed';
$string['privacy:metadata:manireports_audit_logs:objecttype'] = 'Object type';
$string['privacy:metadata:manireports_audit_logs:objectid'] = 'Object ID';
$string['privacy:metadata:manireports_audit_logs:details'] = 'Action details';
$string['privacy:metadata:manireports_audit_logs:timecreated'] = 'Time created';

// Dashboard builder strings.
$string['confirmunsavedchanges'] = 'You have unsaved changes. Are you sure you want to leave?';
$string['confirmremovewidget'] = 'Are you sure you want to remove this widget?';
$string['unsavedchanges'] = 'You have unsaved changes.';
$string['dashboardnamerequired'] = 'Dashboard name is required.';
$string['dashboardsaved'] = 'Dashboard saved successfully.';
$string['addwidget'] = 'Add Widget';
$string['editwidget'] = 'Edit Widget';
$string['widgettitle'] = 'Widget Title';
$string['widgettype'] = 'Widget Type';
$string['datasource'] = 'Data Source';
$string['widgetwidth'] = 'Width (%)';
$string['widgetheight'] = 'Height (px)';
$string['kpiwidget'] = 'KPI Widget';
$string['linechartwidget'] = 'Line Chart';
$string['barchartwidget'] = 'Bar Chart';
$string['piechartwidget'] = 'Pie Chart';
$string['tablewidget'] = 'Table Widget';

// GUI Report Builder
$string['guireportbuilder'] = 'GUI Report Builder';
$string['selecttables'] = 'Select Tables';
$string['selecttable'] = 'Select a table...';
$string['addtable'] = 'Add Table';
$string['selectcolumns'] = 'Select Columns';
$string['joins'] = 'Table Joins';
$string['addjoin'] = 'Add Join';
$string['filterlogic'] = 'Filter Logic';
$string['grouping'] = 'Grouping';
$string['addgroupby'] = 'Add Group By';
$string['sorting'] = 'Sorting';
$string['addorderby'] = 'Add Order By';
$string['sqlpreview'] = 'SQL Preview';
$string['savereport'] = 'Save Report';
$string['reportname'] = 'Report Name';
$string['invalidconfig'] = 'Invalid configuration: {$a}';
$string['invalidtable'] = 'Invalid table: {$a}';
$string['invalidreporttype'] = 'Invalid report type';
$string['reportsaved'] = 'Report saved successfully';
$string['none'] = 'None';

// Table labels
$string['table_user'] = 'Users';
$string['table_course'] = 'Courses';
$string['table_course_categories'] = 'Course Categories';
$string['table_enrol'] = 'Enrolment Methods';
$string['table_user_enrolments'] = 'User Enrolments';
$string['table_course_completions'] = 'Course Completions';
$string['table_course_modules'] = 'Course Modules';
$string['table_course_modules_completion'] = 'Module Completions';
$string['table_grade_grades'] = 'Grades';
$string['table_grade_items'] = 'Grade Items';
$string['table_quiz'] = 'Quizzes';
$string['table_quiz_attempts'] = 'Quiz Attempts';
$string['table_scorm'] = 'SCORM Activities';
$string['table_scorm_scoes_track'] = 'SCORM Tracking';
$string['table_logstore_standard_log'] = 'Activity Logs';
$string['table_role_assignments'] = 'Role Assignments';
$string['table_context'] = 'Contexts';

// Column labels (fallback - will use column name if not defined)
$string['column_user_id'] = 'User ID';
$string['column_user_username'] = 'Username';
$string['column_user_firstname'] = 'First Name';
$string['column_user_lastname'] = 'Last Name';
$string['column_user_email'] = 'Email';
$string['column_course_id'] = 'Course ID';
$string['column_course_fullname'] = 'Course Name';
$string['column_course_shortname'] = 'Short Name';
$string['column_course_timecreated'] = 'Created Time';

// Schedule form strings.
$string['reportcategory'] = 'Report Category';
$string['prebuiltreports'] = 'Prebuilt Reports';
$string['prebuiltreport'] = 'Prebuilt Report';
$string['selectreport'] = 'Select a report...';
$string['configjsonrequired'] = 'GUI configuration is required';
$string['invalidconfig'] = 'Invalid report configuration';
$string['reportsaved'] = 'Report saved successfully';

// GUI Report Builder strings.
$string['guireportbuilder'] = 'GUI Report Builder';
$string['selecttables'] = 'Select Tables';
$string['selecttable'] = 'Select a table...';
$string['addtable'] = 'Add Table';
$string['selectcolumns'] = 'Select Columns';
$string['joins'] = 'Table Joins';
$string['addjoin'] = 'Add Join';
$string['filters'] = 'Filters';
$string['addfilter'] = 'Add Filter';
$string['filterlogic'] = 'Filter Logic';
$string['grouping'] = 'Grouping';
$string['addgroupby'] = 'Add Group By';
$string['sorting'] = 'Sorting';
$string['addorderby'] = 'Add Order By';
$string['sqlpreview'] = 'SQL Preview';

// Table labels (fallback).
$string['table_user'] = 'Users';
$string['table_course'] = 'Courses';
$string['table_course_categories'] = 'Course Categories';
$string['table_enrol'] = 'Enrolment Methods';
$string['table_user_enrolments'] = 'User Enrolments';
$string['table_course_completions'] = 'Course Completions';
$string['table_course_modules'] = 'Course Modules';
$string['table_course_modules_completion'] = 'Module Completions';
$string['table_grade_grades'] = 'Grades';
$string['table_grade_items'] = 'Grade Items';
$string['table_quiz'] = 'Quizzes';
$string['table_quiz_attempts'] = 'Quiz Attempts';
$string['table_scorm'] = 'SCORM Activities';
$string['table_scorm_scoes_track'] = 'SCORM Tracking';
$string['table_logstore_standard_log'] = 'Activity Logs';
$string['table_role_assignments'] = 'Role Assignments';
$string['table_context'] = 'Contexts';

// Column labels (fallback - will use column name if not defined).
$string['column_user_id'] = 'User ID';
$string['column_user_username'] = 'Username';
$string['column_user_firstname'] = 'First Name';
$string['column_user_lastname'] = 'Last Name';
$string['column_user_email'] = 'Email';
$string['column_course_id'] = 'Course ID';
$string['column_course_fullname'] = 'Course Name';
$string['column_course_shortname'] = 'Short Name';

// Drill-down strings.
$string['appliedfilters'] = 'Applied Filters';
$string['clearfilters'] = 'Clear Filters';
$string['drilldown'] = 'Drill Down';
$string['drilldownview'] = 'Drill-Down View';
$string['backtoparent'] = 'Back to Parent View';
$string['exportdrilldown'] = 'Export Drill-Down Data';
$string['nodrilldowndata'] = 'No drill-down data available';
$string['drilldownfailed'] = 'Failed to load drill-down view';

// Navigation strings.
$string['backtodashboard'] = 'Back to Dashboard';
$string['clearfilters'] = 'Clear Filters';
$string['appliedfilters'] = 'Applied Filters';
$string['filters'] = 'Filters';

// User filter strings.
$string['usernameoremail'] = 'Username or Email';

$string['visualization'] = 'Visualization';
$string['top10courses'] = 'Top 10 Courses';
$string['numberofusers'] = 'Number of Users';
$string['timespenthours'] = 'Time Spent (Hours)';
$string['incomplete'] = 'Incomplete';
$string['notattempted'] = 'Not Attempted';
$string['attempts'] = 'Attempts';
$string['averagescore'] = 'Average Score';

// Manager Dashboard strings.
$string['managerdashboard'] = 'Manager Dashboard';
$string['companyusers'] = 'Company Users';
$string['companycourses'] = 'Company Courses';
$string['nocompanyassigned'] = 'No company assigned to your account. Please contact your administrator.';
$string['recentusers'] = 'Recent Users';
$string['nousers'] = 'No users found';
$string['nocourses'] = 'No courses found';
$string['enrolled'] = 'Enrolled';
$string['completed'] = 'Completed';
$string['rate'] = 'Rate';
$string['company'] = 'Company';

// Teacher Dashboard strings.
$string['teacherdashboard'] = 'Teacher Dashboard';
$string['teacherdashboarddesc'] = 'Overview of your courses and students';
$string['mystudents'] = 'My Students';
$string['activestudents7days'] = 'Active Students (7 days)';
$string['pendinggrading'] = 'Pending Grading';
$string['quizattempts7days'] = 'Quiz Attempts (7 days)';
$string['studentprogress'] = 'Student Progress';
$string['students'] = 'Students';
$string['student'] = 'Student';
$string['progress'] = 'Progress';
$string['nostudents'] = 'No students found';
$string['recentactivity'] = 'Recent Activity';
$string['activity'] = 'Activity';
$string['type'] = 'Type';
$string['time'] = 'Time';
$string['submission'] = 'Submission';

// Time Tracking settings.
$string['enabletimetracking'] = 'Enable Time Tracking';
$string['enabletimetracking_desc'] = 'Enable automatic time tracking using JavaScript heartbeat';
$string['heartbeatinterval'] = 'Heartbeat Interval';
$string['heartbeatinterval_desc'] = 'How often to send heartbeat signals (in seconds). Default: 25';
$string['sessiontimeout'] = 'Session Timeout';
$string['sessiontimeout_desc'] = 'Time before a session is considered inactive (in minutes). Default: 10';
$string['generalsettings'] = 'General Settings';
$string['cachesettings'] = 'Cache Settings';
$string['reportsettings'] = 'Report Settings';
$string['retentionsettings'] = 'Data Retention Settings';
$string['atrisksettings'] = 'At-Risk Learner Settings';
$string['cachettl_dashboard'] = 'Dashboard Cache TTL';
$string['cachettl_dashboard_desc'] = 'Cache time-to-live for dashboard widgets (in seconds)';
$string['cachettl_trends'] = 'Trends Cache TTL';
$string['cachettl_trends_desc'] = 'Cache time-to-live for trend reports (in seconds)';
$string['cachettl_historical'] = 'Historical Cache TTL';
$string['cachettl_historical_desc'] = 'Cache time-to-live for historical reports (in seconds)';
$string['querytimeout'] = 'Query Timeout';
$string['querytimeout_desc'] = 'Maximum time for query execution (in seconds)';
$string['maxconcurrentreports'] = 'Max Concurrent Reports';
$string['maxconcurrentreports_desc'] = 'Maximum number of reports that can run simultaneously';
$string['auditlogretention'] = 'Audit Log Retention';
$string['auditlogretention_desc'] = 'How long to keep audit logs (in days)';
$string['reportrunretention'] = 'Report Run Retention';
$string['reportrunretention_desc'] = 'How long to keep report run history (in days)';
$string['atrisk_mintime'] = 'Minimum Time Threshold';
$string['atrisk_mintime_desc'] = 'Minimum hours spent to not be at-risk';
$string['atrisk_maxdays'] = 'Maximum Days Since Login';
$string['atrisk_maxdays_desc'] = 'Maximum days since last login before flagged as at-risk';
$string['atrisk_mincompletion'] = 'Minimum Completion Percentage';
$string['atrisk_mincompletion_desc'] = 'Minimum completion percentage to not be at-risk';

// Scheduled task names.
$string['task_timeaggregation'] = 'Time Tracking Aggregation';
$string['task_cachebuilder'] = 'Cache Builder';
$string['task_reportscheduler'] = 'Report Scheduler';
$string['task_scormsummary'] = 'SCORM Summary Aggregation';
$string['task_cleanupolddata'] = 'Cleanup Old Data';

// External API strings.
$string['api:invaliddashboardtype'] = 'Invalid dashboard type specified';
$string['api:reportnotfound'] = 'Report not found or you do not have permission to access it';
$string['api:invalidparameters'] = 'Invalid parameters provided to API';
$string['api:unauthorized'] = 'You are not authorized to access this API endpoint';
$string['api:servererror'] = 'An internal server error occurred';
$string['api:ratelimitexceeded'] = 'API rate limit exceeded. Please try again later';
$string['api:invalidpagesize'] = 'Invalid page size. Maximum allowed is 100';
$string['api:noreportdata'] = 'No data available for this report';
$string['api:executionfailed'] = 'Report execution failed: {$a}';
$string['api:invalidfilters'] = 'Invalid filter parameters provided';

// xAPI Integration strings.
$string['xapisettings'] = 'xAPI Integration Settings';
$string['enable_xapi_integration'] = 'Enable xAPI Integration';
$string['enable_xapi_integration_desc'] = 'Enable integration with xAPI logstore plugin for enhanced engagement metrics';
$string['xapi_score_weight'] = 'xAPI Score Weight';
$string['xapi_score_weight_desc'] = 'Weight of xAPI score in overall engagement calculation (0.0 to 1.0). Default: 0.3 (30%)';
$string['xapi:notavailable'] = 'xAPI integration is not available. Please install and enable the xAPI logstore plugin.';
$string['xapi:disabled'] = 'xAPI integration is disabled in plugin settings';
$string['xapi:engagement'] = 'xAPI Engagement';
$string['xapi:videoengagement'] = 'Video Engagement';
$string['xapi:activitycount'] = 'xAPI Activities';
$string['xapi:uniqueverbs'] = 'Unique Activity Types';
$string['xapi:videoscompleted'] = 'Videos Completed';
$string['xapi:totalwatchtime'] = 'Total Watch Time';
$string['xapi:watchtime'] = 'Watch Time';
$string['xapi:completionrate'] = 'Video Completion Rate';

// At-Risk Learner Dashboard strings.
$string['atriskdashboard'] = 'At-Risk Learners';
$string['atrisk:summary'] = 'Summary';
$string['atrisk:totalcount'] = 'Total At-Risk';
$string['atrisk:pendingcount'] = 'Pending Review';
$string['atrisk:acknowledgedcount'] = 'Acknowledged';
$string['atrisk:nolearners'] = 'No at-risk learners found';
$string['atrisk:riskscore'] = 'Risk Score';
$string['atrisk:factors'] = 'Contributing Factors';
$string['atrisk:lastactivity'] = 'Last Activity';
$string['atrisk:status'] = 'Status';
$string['atrisk:factor_low_time'] = 'Low time spent';
$string['atrisk:factor_no_login'] = 'No recent login';
$string['atrisk:factor_low_completion'] = 'Low completion';
$string['atrisk:factor_low_engagement'] = 'Low engagement';
$string['atrisk:acknowledgetitle'] = 'Acknowledge At-Risk Learner';
$string['atrisk:acknowledgeconfirm'] = 'You are acknowledging that {$a} is at-risk. Please add any intervention notes below.';
$string['atrisk:note'] = 'Intervention Note';
$string['atrisk:noteplaceholder'] = 'Enter details about intervention actions taken or planned...';
$string['atrisk:acknowledged'] = 'At-risk learner acknowledged successfully';
$string['atrisk:interventionnote'] = 'Intervention Note';
$string['acknowledged'] = 'Acknowledged';
$string['pending'] = 'Pending';
$string['acknowledge'] = 'Acknowledge';
$string['viewnote'] = 'View Note';
$string['allcourses'] = 'All Courses';

// Privacy API strings.
$string['privacy:metadata:usertime_sessions'] = 'Time tracking session data';
$string['privacy:metadata:usertime_sessions:userid'] = 'User ID';
$string['privacy:metadata:usertime_sessions:courseid'] = 'Course ID';
$string['privacy:metadata:usertime_sessions:sessionstart'] = 'Session start timestamp';
$string['privacy:metadata:usertime_sessions:lastupdated'] = 'Last update timestamp';
$string['privacy:metadata:usertime_sessions:duration'] = 'Session duration in seconds';

$string['privacy:metadata:usertime_daily'] = 'Daily aggregated time tracking data';
$string['privacy:metadata:usertime_daily:userid'] = 'User ID';
$string['privacy:metadata:usertime_daily:courseid'] = 'Course ID';
$string['privacy:metadata:usertime_daily:date'] = 'Date';
$string['privacy:metadata:usertime_daily:duration'] = 'Total duration in seconds';
$string['privacy:metadata:usertime_daily:sessioncount'] = 'Number of sessions';

$string['privacy:metadata:customreports'] = 'Custom reports created by users';
$string['privacy:metadata:customreports:name'] = 'Report name';
$string['privacy:metadata:customreports:description'] = 'Report description';
$string['privacy:metadata:customreports:sqlquery'] = 'SQL query';
$string['privacy:metadata:customreports:configjson'] = 'Report configuration';
$string['privacy:metadata:customreports:createdby'] = 'User who created the report';
$string['privacy:metadata:customreports:timecreated'] = 'Creation timestamp';
$string['privacy:metadata:customreports:timemodified'] = 'Modification timestamp';

$string['privacy:metadata:report_runs'] = 'Report execution history';
$string['privacy:metadata:report_runs:userid'] = 'User who executed the report';
$string['privacy:metadata:report_runs:reportid'] = 'Report ID';
$string['privacy:metadata:report_runs:status'] = 'Execution status';
$string['privacy:metadata:report_runs:timestarted'] = 'Start timestamp';
$string['privacy:metadata:report_runs:timefinished'] = 'Finish timestamp';

$string['privacy:metadata:audit_logs'] = 'Audit trail of user actions';
$string['privacy:metadata:audit_logs:userid'] = 'User who performed the action';
$string['privacy:metadata:audit_logs:action'] = 'Action performed';
$string['privacy:metadata:audit_logs:objecttype'] = 'Object type';
$string['privacy:metadata:audit_logs:objectid'] = 'Object ID';
$string['privacy:metadata:audit_logs:details'] = 'Action details';
$string['privacy:metadata:audit_logs:timecreated'] = 'Timestamp';

$string['privacy:metadata:schedule_recipients'] = 'Scheduled report recipients';
$string['privacy:metadata:schedule_recipients:userid'] = 'Recipient user ID';
$string['privacy:metadata:schedule_recipients:scheduleid'] = 'Schedule ID';

$string['privacy:metadata:dashboards'] = 'Custom dashboards created by users';
$string['privacy:metadata:dashboards:name'] = 'Dashboard name';
$string['privacy:metadata:dashboards:description'] = 'Dashboard description';
$string['privacy:metadata:dashboards:layoutjson'] = 'Dashboard layout configuration';
$string['privacy:metadata:dashboards:createdby'] = 'User who created the dashboard';
$string['privacy:metadata:dashboards:timecreated'] = 'Creation timestamp';
$string['privacy:metadata:dashboards:timemodified'] = 'Modification timestamp';

$string['privacy:metadata:atrisk_ack'] = 'At-risk learner acknowledgments';
$string['privacy:metadata:atrisk_ack:userid'] = 'At-risk learner user ID';
$string['privacy:metadata:atrisk_ack:courseid'] = 'Course ID';
$string['privacy:metadata:atrisk_ack:acknowledgedby'] = 'User who acknowledged';
$string['privacy:metadata:atrisk_ack:note'] = 'Intervention note';
$string['privacy:metadata:atrisk_ack:timeacknowledged'] = 'Acknowledgment timestamp';

$string['privacy:path:timetracking'] = 'Time Tracking';
$string['privacy:path:sessions'] = 'Sessions';
$string['privacy:path:daily'] = 'Daily Aggregates';
$string['privacy:path:customreports'] = 'Custom Reports';
$string['privacy:path:reportruns'] = 'Report Executions';
$string['privacy:path:auditlogs'] = 'Audit Logs';
$string['privacy:path:schedules'] = 'Schedule Recipients';
$string['privacy:path:dashboards'] = 'Dashboards';
$string['privacy:path:atrisk'] = 'At-Risk Learners';
$string['privacy:path:atrisk_subject'] = 'As Subject';
$string['privacy:path:atrisk_acknowledger'] = 'As Acknowledger';

// Performance optimization strings.
$string['performancesettings'] = 'Performance Optimization Settings';
$string['max_concurrent_reports'] = 'Maximum Concurrent Reports';
$string['max_concurrent_reports_desc'] = 'Maximum number of reports that can execute simultaneously (prevents database overload)';
$string['default_page_size'] = 'Default Page Size';
$string['default_page_size_desc'] = 'Default number of rows per page for paginated reports';
$string['query_timeout'] = 'Query Timeout';
$string['query_timeout_desc'] = 'Maximum time in seconds for report queries to execute';
$string['toomanyreports'] = 'Too many reports are currently running. Please try again in a moment.';
$string['performance'] = 'Performance Monitoring';
$string['performanceoverview'] = 'Performance Overview';
$string['tablesizes'] = 'Table Sizes';
$string['table'] = 'Table';
$string['records'] = 'Records';
$string['concurrentreports'] = 'Concurrent Reports';
$string['currentlyrunning'] = 'Currently Running';
$string['maximumallowed'] = 'Maximum Allowed';
$string['utilization'] = 'Utilization';
$string['cachestatistics'] = 'Cache Statistics';
$string['totalcacheentries'] = 'Total Cache Entries';
$string['validcacheentries'] = 'Valid Cache Entries';
$string['cachehitrate'] = 'Cache Hit Rate';
$string['taskrecommendations'] = 'Task Scheduling Recommendations';
$string['task'] = 'Task';
$string['recommendation'] = 'Recommendation';
$string['reason'] = 'Reason';
$string['actions'] = 'Actions';
$string['ensureindexes'] = 'Ensure Database Indexes';
$string['indexesensured'] = 'Checked {$a->checked} indexes, created {$a->created} new indexes';

// Security strings.
$string['ratelimitexceeded'] = 'Rate limit exceeded. Please try again later.';
$string['dangerousql'] = 'Dangerous SQL keyword detected: {$a}';
$string['sqlmustselect'] = 'SQL queries must start with SELECT';
$string['multiplestatements'] = 'Multiple SQL statements are not allowed';
$string['invalidfile'] = 'Invalid file upload';
$string['filetoolarge'] = 'File is too large. Maximum size: {$a->max} bytes';
$string['invalidfiletype'] = 'Invalid file type: {$a}';
$string['invalidurl'] = 'Invalid URL';
$string['externalurlnotallowed'] = 'External URLs are not allowed';
$string['securityviolation'] = 'Security violation detected';

// Error handling strings.
$string['failedjobs'] = 'Failed Jobs';
$string['systemhealth'] = 'System Health';
$string['status'] = 'Status';
$string['nofailedjobs'] = 'No failed jobs found. System is running smoothly!';
$string['timefailed'] = 'Time Failed';
$string['retrycount'] = 'Retry Count';
$string['retry'] = 'Retry';
$string['jobretried'] = 'Job retried successfully';
$string['jobretryfailed'] = 'Job retry failed. Check error logs for details.';
$string['jobdeleted'] = 'Job deleted successfully';
$string['clearoldjobs'] = 'Clear Old Jobs (30+ days)';
$string['jobscleared'] = 'Cleared {$a} old jobs';
$string['stacktrace'] = 'Stack Trace';
$string['error'] = 'Error';
$string['taskfailurealert'] = 'ManiReports Task Failure: {$a}';
$string['taskfailurealertbody'] = 'The task "{$a->taskname}" has failed {$a->count} times in the last 24 hours. Please review the failed jobs at: {$a->url}';

// Common UI strings.
$string['save'] = 'Save';
$string['cancel'] = 'Cancel';
$string['delete'] = 'Delete';
$string['edit'] = 'Edit';
$string['view'] = 'View';
$string['back'] = 'Back';
$string['next'] = 'Next';
$string['previous'] = 'Previous';
$string['search'] = 'Search';
$string['filter'] = 'Filter';
$string['export'] = 'Export';
$string['import'] = 'Import';
$string['refresh'] = 'Refresh';
$string['loading'] = 'Loading...';
$string['settings'] = 'Settings';
$string['actions'] = 'Actions';
$string['confirm'] = 'Confirm';
$string['yes'] = 'Yes';
$string['no'] = 'No';

// Time/Date strings.
$string['today'] = 'Today';
$string['yesterday'] = 'Yesterday';
$string['thisweek'] = 'This Week';
$string['lastweek'] = 'Last Week';
$string['thismonth'] = 'This Month';
$string['lastmonth'] = 'Last Month';
$string['daterange'] = 'Date Range';
$string['from'] = 'From';
$string['to'] = 'To';
$string['startdate'] = 'Start Date';
$string['enddate'] = 'End Date';

// Status strings.
$string['active'] = 'Active';
$string['inactive'] = 'Inactive';
$string['enabled'] = 'Enabled';
$string['disabled'] = 'Disabled';
$string['success'] = 'Success';
$string['warning'] = 'Warning';
$string['pending'] = 'Pending';
$string['completed'] = 'Completed';
$string['running'] = 'Running';
$string['failed'] = 'Failed';

// Help text strings.
$string['help'] = 'Help';
$string['documentation'] = 'Documentation';
$string['userguide'] = 'User Guide';
$string['adminquide'] = 'Administrator Guide';
$string['troubleshooting'] = 'Troubleshooting';

// Validation messages.
$string['required'] = 'This field is required';
$string['invalid'] = 'Invalid value';
$string['toolong'] = 'Value is too long';
$string['tooshort'] = 'Value is too short';
$string['mustbepositive'] = 'Value must be positive';
$string['mustbenumeric'] = 'Value must be numeric';

// Confirmation messages.
$string['confirmdelete'] = 'Are you sure you want to delete this item?';
$string['confirmcancel'] = 'Are you sure you want to cancel? Unsaved changes will be lost.';
$string['deleteconfirm'] = 'Delete Confirmation';
$string['cannotundo'] = 'This action cannot be undone.';

// Success messages.
$string['savesuccess'] = 'Saved successfully';
$string['deletesuccess'] = 'Deleted successfully';
$string['updatesuccess'] = 'Updated successfully';
$string['createsuccess'] = 'Created successfully';

// Error messages (general).
$string['saveerror'] = 'Error saving data';
$string['deleteerror'] = 'Error deleting item';
$string['updateerror'] = 'Error updating data';
$string['createerror'] = 'Error creating item';
$string['notfound'] = 'Item not found';
$string['accessdenied'] = 'Access denied';
$string['invalidrequest'] = 'Invalid request';

// Privacy strings.
$string['privacy:metadata'] = 'The ManiReports plugin stores user activity data for reporting purposes.';
$string['privacy:metadata:manireports_usertime_sessions'] = 'User time tracking sessions';
$string['privacy:metadata:manireports_usertime_sessions:userid'] = 'User ID';
$string['privacy:metadata:manireports_usertime_sessions:courseid'] = 'Course ID';
$string['privacy:metadata:manireports_usertime_sessions:sessionstart'] = 'Session start time';
$string['privacy:metadata:manireports_usertime_sessions:lastupdated'] = 'Last activity time';
$string['privacy:metadata:manireports_usertime_daily'] = 'Daily time tracking summaries';
$string['privacy:metadata:manireports_usertime_daily:userid'] = 'User ID';
$string['privacy:metadata:manireports_usertime_daily:courseid'] = 'Course ID';
$string['privacy:metadata:manireports_usertime_daily:date'] = 'Date';
$string['privacy:metadata:manireports_usertime_daily:totalseconds'] = 'Total time in seconds';
$string['privacy:metadata:manireports_audit_logs'] = 'Audit log entries';
$string['privacy:metadata:manireports_audit_logs:userid'] = 'User ID';
$string['privacy:metadata:manireports_audit_logs:action'] = 'Action performed';
$string['privacy:metadata:manireports_audit_logs:details'] = 'Action details';
$string['privacy:metadata:manireports_audit_logs:timecreated'] = 'Time of action';

// Task names.
$string['task:cache_builder'] = 'Build report cache';
$string['task:time_aggregation'] = 'Aggregate time tracking data';
$string['task:report_scheduler'] = 'Execute scheduled reports';
$string['task:scorm_summary'] = 'Aggregate SCORM data';
$string['task:cleanup_old_data'] = 'Clean up old data';

// Capability descriptions.
$string['manireports:viewadmindashboard_desc'] = 'View the administrator dashboard with site-wide statistics';
$string['manireports:viewmanagerdashboard_desc'] = 'View the company manager dashboard with company-specific data';
$string['manireports:viewteacherdashboard_desc'] = 'View the teacher dashboard with student progress data';
$string['manireports:viewstudentdashboard_desc'] = 'View personal progress and time tracking data';
$string['manireports:managereports_desc'] = 'Create, edit, and delete custom reports';
$string['manireports:schedule_desc'] = 'Schedule reports for automatic generation and delivery';
$string['manireports:customreports_desc'] = 'Create custom SQL reports with access to whitelisted tables';

// Reminder feature strings.
$string['reminders'] = 'Reminders';
$string['templates'] = 'Templates';
$string['reminderdashboard'] = 'Reminder Dashboard';
$string['addnewrule'] = 'Add New Rule';
$string['norulesfound'] = 'No reminder rules found';
$string['ruledeleted'] = 'Reminder rule deleted successfully';
$string['instancescreated'] = '{$a} reminder instances created';
$string['addtemplate'] = 'Add Template';
$string['edittemplate'] = 'Edit Template';
$string['templatecreated'] = 'Template created successfully';
$string['templateupdated'] = 'Template updated successfully';
$string['notemplates'] = 'No templates found';
$string['task_process_reminders'] = 'Process Reminders';

