# ManiReports Plugin Upgrade Testing Guide

## Pre-Upgrade Verification

### ✅ Table Name Validation
All table names are within Moodle's 28-character limit:

| Table Name | Length | Status |
|------------|--------|--------|
| manireports_customreports | 25 chars | ✅ OK |
| manireports_schedules | 21 chars | ✅ OK |
| manireports_sched_recip | 23 chars | ✅ OK |
| manireports_report_runs | 23 chars | ✅ OK |
| manireports_time_sessions | 25 chars | ✅ OK |
| manireports_time_daily | 22 chars | ✅ OK |
| manireports_scorm_summary | 25 chars | ✅ OK |
| manireports_cache_summary | 25 chars | ✅ OK |
| manireports_dashboards | 22 chars | ✅ OK |
| manireports_dash_widgets | 24 chars | ✅ OK |
| manireports_audit_logs | 22 chars | ✅ OK |

**All table names are valid!** ✅

### Current Version Information
- **Plugin Version**: 2024111702
- **Release**: v1.0.0-alpha
- **Moodle Requirement**: 4.0+

### Upgrade Path
The upgrade.php script handles two upgrade steps:

1. **Version 2024111701**: Adds missing fields to schedules table
   - userid, reporttype, parameters, enabled, lastrun, nextrun, failcount

2. **Version 2024111702**: Adds custom report support
   - reportid field with foreign key to manireports_customreports

## Pre-Installation Checklist

Before upgrading, complete these steps:

### 1. Backup Everything
```bash
# SSH into EC2
ssh user@your-ec2-instance.com

# Backup database
mysqldump -u moodle_user -p moodle_db > /tmp/moodle_backup_$(date +%Y%m%d_%H%M%S).sql

# Backup plugin directory
cd /var/www/html/moodle/local
tar -czf /tmp/manireports_backup_$(date +%Y%m%d_%H%M%S).tar.gz manireports/

# Backup moodledata
tar -czf /tmp/moodledata_backup_$(date +%Y%m%d_%H%M%S).tar.gz /var/www/html/moodledata/
```

### 2. Enable Maintenance Mode
```bash
# Enable maintenance mode
sudo -u www-data php /var/www/html/moodle/admin/cli/maintenance.php --enable
```

### 3. Check Current Plugin Version
```bash
# Check current version in database
mysql -u moodle_user -p moodle_db -e "SELECT name, value FROM mdl_config_plugins WHERE plugin='local_manireports' AND name='version';"
```

## Installation Steps

### Step 1: Upload New Plugin Files
```bash
# Navigate to Moodle local directory
cd /var/www/html/moodle/local

# If using Git
cd manireports
git pull origin main

# OR if uploading via SCP from local machine
# scp -r local/manireports/* user@your-ec2-instance.com:/var/www/html/moodle/local/manireports/

# Set proper permissions
sudo chown -R www-data:www-data /var/www/html/moodle/local/manireports
sudo chmod -R 755 /var/www/html/moodle/local/manireports
```

### Step 2: Run Database Upgrade
```bash
# Run Moodle upgrade (this will execute upgrade.php)
sudo -u www-data php /var/www/html/moodle/admin/cli/upgrade.php --non-interactive

# Check for errors in output
# Should see: "local_manireports: Upgrade to version 2024111702 succeeded"
```

### Step 3: Build AMD JavaScript Modules
```bash
# Navigate to Moodle root
cd /var/www/html/moodle

# Build AMD modules
sudo -u www-data npx grunt amd --root=local/manireports

# Verify build
ls -la /var/www/html/moodle/local/manireports/amd/build/
# Should see: charts.min.js, dashboard.js, filters.js, heartbeat.js, drilldown.min.js, etc.
```

### Step 4: Clear All Caches
```bash
# Purge all caches
sudo -u www-data php /var/www/html/moodle/admin/cli/purge_caches.php

# Clear language cache
sudo -u www-data php /var/www/html/moodle/admin/cli/purge_caches.php --lang

# Clear theme cache
sudo -u www-data php /var/www/html/moodle/admin/cli/purge_caches.php --theme
```

### Step 5: Disable Maintenance Mode
```bash
# Disable maintenance mode
sudo -u www-data php /var/www/html/moodle/admin/cli/maintenance.php --disable
```

## Post-Installation Verification

### Verify Database Changes
```bash
# Check if upgrade ran successfully
mysql -u moodle_user -p moodle_db -e "SELECT name, value FROM mdl_config_plugins WHERE plugin='local_manireports' AND name='version';"
# Should show: 2024111702

# Verify all tables exist
mysql -u moodle_user -p moodle_db -e "SHOW TABLES LIKE 'mdl_manireports%';"
# Should show all 11 tables

# Check schedules table structure
mysql -u moodle_user -p moodle_db -e "DESCRIBE mdl_manireports_schedules;"
# Should include: reportid field

# Check foreign key
mysql -u moodle_user -p moodle_db -e "SHOW CREATE TABLE mdl_manireports_schedules\G"
# Should show foreign key constraint for reportid
```

### Verify File Structure
```bash
# Check if all new files exist
ls -la /var/www/html/moodle/local/manireports/amd/src/drilldown.js
ls -la /var/www/html/moodle/local/manireports/ui/dashboard_builder.php
ls -la /var/www/html/moodle/local/manireports/ui/report_builder_gui.php
ls -la /var/www/html/moodle/local/manireports/classes/api/dashboard_manager.php
ls -la /var/www/html/moodle/local/manireports/classes/api/widget_manager.php
ls -la /var/www/html/moodle/local/manireports/classes/api/query_builder.php
```

## Comprehensive Testing Scenarios

### Test 1: Basic Plugin Access ✅
**Objective**: Verify plugin is accessible and no fatal errors

1. **Login as Admin**
   - URL: `https://your-moodle-site.com`
   - Login with admin credentials

2. **Access Plugin Settings**
   - Navigate to: Site administration → Plugins → Local plugins → ManiReports
   - Expected: Settings page loads without errors
   - Verify: All configuration options are visible

3. **Check for Errors**
   - Check Moodle error log: `/var/www/html/moodledata/error.log`
   - Expected: No PHP errors or warnings related to manireports

**Pass Criteria**: ✅ No errors, settings page loads

---

### Test 2: Database Tables and Data Integrity ✅
**Objective**: Verify all tables exist and old data is preserved

1. **Check Table Existence**
```bash
mysql -u moodle_user -p moodle_db -e "
SELECT TABLE_NAME, TABLE_ROWS 
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'moodle_db' 
AND TABLE_NAME LIKE 'mdl_manireports%';"
```
Expected: All 11 tables listed

2. **Verify Old Data Preserved**
```bash
# Check if old custom reports still exist
mysql -u moodle_user -p moodle_db -e "SELECT id, name, type FROM mdl_manireports_customreports;"

# Check if old schedules still exist
mysql -u moodle_user -p moodle_db -e "SELECT id, name, reporttype FROM mdl_manireports_schedules;"

# Check if audit logs preserved
mysql -u moodle_user -p moodle_db -e "SELECT COUNT(*) FROM mdl_manireports_audit_logs;"
```
Expected: All old data intact

**Pass Criteria**: ✅ All tables exist, old data preserved

---

### Test 3: Admin Dashboard ✅
**Objective**: Verify admin dashboard loads and displays data

1. **Access Admin Dashboard**
   - URL: `https://your-moodle-site.com/local/manireports/ui/dashboard.php`
   - Expected: Dashboard loads without errors

2. **Verify Dashboard Widgets**
   - Check for KPI widgets (Total Users, Total Courses, etc.)
   - Check for charts (Course Usage, Active Users)
   - Check for tables (Inactive Users)
   - Expected: All widgets display data or "No data" message

3. **Test Filters**
   - Change date range filter (Last 7 days, Last 30 days, Last 90 days)
   - Expected: Dashboard updates without page reload
   - Check browser console for JavaScript errors

4. **Test Responsive Design**
   - Resize browser window to mobile size
   - Expected: Dashboard adapts to smaller screen

**Pass Criteria**: ✅ Dashboard loads, widgets display, filters work

---

### Test 4: Prebuilt Reports ✅
**Objective**: Verify all 5 prebuilt reports work

#### 4.1 Course Completion Report
1. Navigate to: Reports → ManiReports → Course Completion
2. Apply filters (date range, course)
3. Verify data displays in table
4. Test export (CSV, XLSX, PDF)
5. Expected: Report executes in < 10 seconds

#### 4.2 Course Progress Report
1. Navigate to: Reports → ManiReports → Course Progress
2. Select a specific course
3. Verify user progress percentages display
4. Test export
5. Expected: Shows per-user completion data

#### 4.3 SCORM Summary Report
1. Navigate to: Reports → ManiReports → SCORM Summary
2. Verify SCORM activities listed
3. Check attempt counts and completion status
4. Test export
5. Expected: Shows aggregated SCORM data

#### 4.4 User Engagement Report
1. Navigate to: Reports → ManiReports → User Engagement
2. Verify time spent data displays
3. Check active days calculation
4. Test export
5. Expected: Shows engagement metrics

#### 4.5 Quiz Attempts Report
1. Navigate to: Reports → ManiReports → Quiz Attempts
2. Verify quiz attempts listed
3. Check average scores
4. Test export
5. Expected: Shows quiz analytics

**Pass Criteria**: ✅ All 5 reports execute successfully, exports work

---

### Test 5: Custom SQL Reports ✅
**Objective**: Verify custom SQL report creation and execution

1. **Create Custom SQL Report**
   - Navigate to: Site administration → Reports → ManiReports → Custom Reports
   - Click "Create New Report"
   - Fill in form:
     - Name: "Test SQL Report"
     - Type: SQL
     - SQL Query:
       ```sql
       SELECT u.id, u.firstname, u.lastname, u.email
       FROM {user} u
       WHERE u.deleted = 0
       LIMIT 10
       ```
   - Save report

2. **Execute Custom Report**
   - Click "View" on the created report
   - Expected: Report executes and shows 10 users
   - Verify columns display correctly

3. **Test SQL Validation**
   - Try to create report with invalid SQL (e.g., DROP TABLE)
   - Expected: Error message about blocked keywords

4. **Test Export**
   - Export report in CSV, XLSX, PDF
   - Expected: All formats work

**Pass Criteria**: ✅ Can create, execute, and export custom SQL reports

---

### Test 6: GUI Report Builder (NEW) ✅
**Objective**: Verify visual report builder works

1. **Access GUI Builder**
   - Navigate to: Site administration → Reports → ManiReports → GUI Report Builder
   - Expected: Builder interface loads

2. **Create Simple Report**
   - Click "Add Table" → Select "user"
   - Select columns: firstname, lastname, email
   - Click "Save Report"
   - Name: "Test GUI Report"
   - Expected: Report saved successfully

3. **Execute GUI Report**
   - Navigate to Custom Reports list
   - Find "Test GUI Report (GUI)"
   - Click "View"
   - Expected: Report executes and shows user data

4. **Test Complex Report**
   - Create report with JOIN:
     - Table 1: user
     - Table 2: user_enrolments (JOIN on userid)
     - Columns: user.firstname, user.lastname, COUNT(enrolments)
     - GROUP BY: user.id
   - Expected: Report shows enrollment counts per user

5. **Test Filters**
   - Add filter: user.email LIKE '%@example.com'
   - Expected: Only matching users shown

**Pass Criteria**: ✅ Can create and execute GUI reports with joins and filters

---

### Test 7: Report Scheduling ✅
**Objective**: Verify scheduled reports work

1. **Create Schedule for Prebuilt Report**
   - Navigate to: Site administration → Reports → ManiReports → Scheduled Reports
   - Click "Create Schedule"
   - Fill in form:
     - Name: "Daily Course Completion"
     - Report Category: Prebuilt Reports
     - Report Type: Course Completion
     - Format: CSV
     - Frequency: Daily
     - Time: 08:00
     - Recipients: your-email@example.com
     - Enabled: Yes
   - Save schedule

2. **Create Schedule for Custom Report (NEW)**
   - Click "Create Schedule"
   - Fill in form:
     - Name: "Weekly GUI Report"
     - Report Category: Custom Reports
     - Custom Report: Select "Test GUI Report"
     - Format: XLSX
     - Frequency: Weekly
     - Day: Monday
     - Time: 09:00
     - Recipients: your-email@example.com
     - Enabled: Yes
   - Save schedule

3. **Test Manual Execution**
```bash
# Run report scheduler task manually
sudo -u www-data php /var/www/html/moodle/admin/cli/scheduled_task.php \
  --execute=\\local_manireports\\task\\report_scheduler
```
   - Expected: Task executes without errors
   - Check email for report attachment

4. **Verify Schedule List**
   - Navigate back to Scheduled Reports
   - Expected: Both schedules listed
   - Check "Last Run" and "Next Run" times

**Pass Criteria**: ✅ Can schedule both prebuilt and custom reports, manual execution works

---

### Test 8: Dashboard Builder (NEW) ✅
**Objective**: Verify custom dashboard creation

1. **Access Dashboard Builder**
   - Navigate to: Site administration → Reports → ManiReports → Dashboard Builder
   - Expected: Builder interface loads with drag-and-drop grid

2. **Create Custom Dashboard**
   - Click "Create New Dashboard"
   - Name: "Test Dashboard"
   - Scope: Personal
   - Add widgets:
     - KPI Widget: Total Users
     - Line Chart: Course Completions Over Time
     - Bar Chart: Top 10 Courses
     - Table: Recent Activity
   - Arrange widgets in grid
   - Click "Save Dashboard"

3. **View Custom Dashboard**
   - Navigate to: My Dashboards
   - Select "Test Dashboard"
   - Expected: Dashboard displays with all widgets
   - Verify data loads in each widget

4. **Edit Dashboard**
   - Click "Edit Dashboard"
   - Remove one widget
   - Add new widget
   - Rearrange layout
   - Save changes
   - Expected: Changes persist

**Pass Criteria**: ✅ Can create, view, and edit custom dashboards

---

### Test 9: Drill-Down Functionality (NEW) ✅
**Objective**: Verify chart drill-down works

1. **Access Dashboard with Charts**
   - Navigate to Admin Dashboard
   - Find a chart widget (e.g., Course Usage chart)

2. **Test Chart Click**
   - Click on a data point in the chart
   - Expected: Navigates to filtered report view
   - Verify applied filters displayed at top

3. **Test Filter Management**
   - Click "×" on a filter badge to remove it
   - Expected: Filter removed, view refreshes
   - Click "Clear All" button
   - Expected: All filters cleared

4. **Test Back Navigation**
   - Drill down multiple levels
   - Click "← Back" button
   - Expected: Returns to previous view

5. **Test Export from Drill-Down**
   - Drill down to filtered view
   - Click "Export CSV"
   - Expected: Exported file contains only filtered data

**Pass Criteria**: ✅ Drill-down works, filters manageable, export includes filters

---

### Test 10: Time Tracking ✅
**Objective**: Verify time tracking heartbeat works

1. **Enable Browser Console**
   - Open browser developer tools (F12)
   - Go to Console tab

2. **Access a Course**
   - Enroll in a course
   - Navigate to course page
   - Stay on page for 2 minutes

3. **Monitor Heartbeat**
   - Watch console for heartbeat AJAX requests
   - Expected: Requests sent every 20-30 seconds
   - Check Network tab for `/local/manireports/ui/ajax/heartbeat.php` calls

4. **Verify Session Tracking**
```bash
# Check active sessions
mysql -u moodle_user -p moodle_db -e "SELECT * FROM mdl_manireports_time_sessions WHERE userid = YOUR_USER_ID ORDER BY lastupdated DESC LIMIT 5;"
```
   - Expected: Active session record exists
   - lastupdated timestamp should be recent

5. **Test Time Aggregation**
```bash
# Run time aggregation task
sudo -u www-data php /var/www/html/moodle/admin/cli/scheduled_task.php \
  --execute=\\local_manireports\\task\\time_aggregation
```
   - Expected: Task completes without errors

6. **Check Daily Summaries**
```bash
mysql -u moodle_user -p moodle_db -e "SELECT * FROM mdl_manireports_time_daily WHERE userid = YOUR_USER_ID ORDER BY date DESC LIMIT 5;"
```
   - Expected: Daily summary records created

**Pass Criteria**: ✅ Heartbeat sends, sessions tracked, aggregation works

---

### Test 11: Export Functionality ✅
**Objective**: Verify all export formats work

1. **Test CSV Export**
   - Open any report
   - Click "Export CSV"
   - Expected: CSV file downloads
   - Open in Excel/LibreOffice
   - Verify: Data is correct, UTF-8 encoding works

2. **Test XLSX Export**
   - Click "Export Excel"
   - Expected: XLSX file downloads
   - Open in Excel
   - Verify: Formatting preserved, columns auto-sized

3. **Test PDF Export**
   - Click "Export PDF"
   - Expected: PDF file downloads
   - Open in PDF reader
   - Verify: Table formatted correctly, readable

4. **Test Large Dataset Export**
   - Run report with > 1000 rows
   - Export in all formats
   - Expected: All exports complete within 30 seconds

**Pass Criteria**: ✅ All 3 formats work, large datasets export successfully

---

### Test 12: IOMAD Multi-Tenancy (If Applicable) ✅
**Objective**: Verify company isolation works

**Note**: Only test if IOMAD is installed

1. **Create Test Companies**
   - Create Company A and Company B
   - Assign users to each company

2. **Test Company Manager Dashboard**
   - Login as Company A manager
   - Access dashboard
   - Expected: Only Company A data visible

3. **Test Report Filtering**
   - Run Course Completion report
   - Expected: Only Company A courses shown
   - Verify: Cannot see Company B data

4. **Test Cross-Company Access**
   - Try to access Company B data via URL manipulation
   - Expected: Access denied or no data shown

**Pass Criteria**: ✅ Company isolation enforced, no data leakage

---

### Test 13: Scheduled Tasks ✅
**Objective**: Verify all scheduled tasks execute

1. **List All Tasks**
```bash
sudo -u www-data php /var/www/html/moodle/admin/cli/scheduled_task.php --list | grep manireports
```
Expected tasks:
- time_aggregation
- cache_builder
- report_scheduler
- scorm_summary
- cleanup_old_data

2. **Test Each Task Manually**
```bash
# Time aggregation
sudo -u www-data php /var/www/html/moodle/admin/cli/scheduled_task.php \
  --execute=\\local_manireports\\task\\time_aggregation

# Cache builder
sudo -u www-data php /var/www/html/moodle/admin/cli/scheduled_task.php \
  --execute=\\local_manireports\\task\\cache_builder

# Report scheduler
sudo -u www-data php /var/www/html/moodle/admin/cli/scheduled_task.php \
  --execute=\\local_manireports\\task\\report_scheduler

# SCORM summary
sudo -u www-data php /var/www/html/moodle/admin/cli/scheduled_task.php \
  --execute=\\local_manireports\\task\\scorm_summary

# Cleanup old data
sudo -u www-data php /var/www/html/moodle/admin/cli/scheduled_task.php \
  --execute=\\local_manireports\\task\\cleanup_old_data
```
Expected: All tasks complete without errors

3. **Check Task Logs**
```bash
# Check Moodle task log
tail -f /var/www/html/moodledata/error.log | grep manireports
```
Expected: No errors

**Pass Criteria**: ✅ All 5 tasks execute successfully

---

### Test 14: Audit Logging ✅
**Objective**: Verify audit trail works

1. **Access Audit Log Viewer**
   - Navigate to: Site administration → Reports → ManiReports → Audit Logs
   - Expected: Audit log page loads

2. **Verify Logged Actions**
   - Check for recent actions:
     - Report creation
     - Schedule creation
     - Dashboard creation
     - Report execution
   - Expected: All actions logged with timestamp, user, details

3. **Test Filtering**
   - Filter by user
   - Filter by date range
   - Filter by action type
   - Expected: Filters work correctly

4. **Test Export**
   - Export audit log to CSV
   - Expected: All audit entries exported

**Pass Criteria**: ✅ Audit logging works, all actions tracked

---

### Test 15: Performance Testing ✅
**Objective**: Verify performance meets requirements

1. **Dashboard Load Time**
   - Clear browser cache
   - Access admin dashboard
   - Measure load time (use browser DevTools Network tab)
   - Expected: < 3 seconds

2. **Report Execution Time**
   - Run Course Completion report with 100+ courses
   - Measure execution time
   - Expected: < 10 seconds

3. **Export Performance**
   - Export report with 10,000+ rows
   - Measure export time
   - Expected: < 30 seconds

4. **Concurrent Users**
   - Have 5 users access dashboards simultaneously
   - Expected: No slowdown or errors

**Pass Criteria**: ✅ All operations within performance targets

---

### Test 16: Security Testing ✅
**Objective**: Verify security controls work

1. **Test Capability Enforcement**
   - Login as student
   - Try to access admin dashboard
   - Expected: Access denied

2. **Test SQL Injection Prevention**
   - Create custom report with malicious SQL:
     ```sql
     SELECT * FROM {user}; DROP TABLE mdl_user; --
     ```
   - Expected: Error message, query blocked

3. **Test XSS Prevention**
   - Create report with name: `<script>alert('XSS')</script>`
   - View report list
   - Expected: Script not executed, name escaped

4. **Test CSRF Protection**
   - Submit form without sesskey
   - Expected: Error message

**Pass Criteria**: ✅ All security controls working

---

### Test 17: Mobile Responsiveness ✅
**Objective**: Verify mobile compatibility

1. **Test on Mobile Device**
   - Access dashboard on smartphone
   - Expected: Layout adapts to small screen

2. **Test Touch Interactions**
   - Tap on chart data points
   - Swipe through tables
   - Expected: Touch gestures work

3. **Test Filters on Mobile**
   - Apply filters on mobile
   - Expected: Filter UI usable on small screen

**Pass Criteria**: ✅ Plugin usable on mobile devices

---

## Error Checking

### Check Moodle Error Log
```bash
# Monitor error log in real-time
tail -f /var/www/html/moodledata/error.log

# Search for manireports errors
grep -i "manireports" /var/www/html/moodledata/error.log | tail -50
```

### Check Apache/Nginx Error Log
```bash
# Apache
sudo tail -f /var/log/apache2/error.log

# Nginx
sudo tail -f /var/log/nginx/error.log
```

### Check Database Errors
```bash
# Check MySQL error log
sudo tail -f /var/log/mysql/error.log
```

### Check Browser Console
- Open browser DevTools (F12)
- Check Console tab for JavaScript errors
- Check Network tab for failed AJAX requests

## Rollback Procedure

If upgrade fails or issues found:

```bash
# 1. Enable maintenance mode
sudo -u www-data php /var/www/html/moodle/admin/cli/maintenance.php --enable

# 2. Restore database
mysql -u moodle_user -p moodle_db < /tmp/moodle_backup_YYYYMMDD_HHMMSS.sql

# 3. Restore plugin files
cd /var/www/html/moodle/local
sudo rm -rf manireports
sudo tar -xzf /tmp/manireports_backup_YYYYMMDD_HHMMSS.tar.gz

# 4. Clear caches
sudo -u www-data php /var/www/html/moodle/admin/cli/purge_caches.php

# 5. Disable maintenance mode
sudo -u www-data php /var/www/html/moodle/admin/cli/maintenance.php --disable
```

## Success Criteria Summary

### Critical (Must Pass)
- ✅ Upgrade completes without errors
- ✅ All 11 tables exist
- ✅ Old data preserved
- ✅ Admin dashboard loads
- ✅ All 5 prebuilt reports work
- ✅ Custom SQL reports work
- ✅ Report scheduling works
- ✅ Export formats work (CSV, XLSX, PDF)
- ✅ No errors in logs

### Important (Should Pass)
- ✅ GUI report builder works
- ✅ Dashboard builder works
- ✅ Drill-down functionality works
- ✅ Time tracking heartbeat works
- ✅ All scheduled tasks execute
- ✅ Audit logging works
- ✅ Performance within targets
- ✅ Security controls enforced

### Nice to Have (Can Pass)
- ✅ Mobile responsive
- ✅ IOMAD multi-tenancy (if applicable)
- ✅ Concurrent user handling

## Testing Completion Checklist

Use this checklist to track your testing progress:

- [ ] Pre-upgrade backup completed
- [ ] Upgrade executed successfully
- [ ] Database version updated to 2024111702
- [ ] All 11 tables exist
- [ ] AMD modules built
- [ ] Caches cleared
- [ ] Test 1: Basic plugin access ✅
- [ ] Test 2: Database integrity ✅
- [ ] Test 3: Admin dashboard ✅
- [ ] Test 4: Prebuilt reports (all 5) ✅
- [ ] Test 5: Custom SQL reports ✅
- [ ] Test 6: GUI report builder ✅
- [ ] Test 7: Report scheduling ✅
- [ ] Test 8: Dashboard builder ✅
- [ ] Test 9: Drill-down functionality ✅
- [ ] Test 10: Time tracking ✅
- [ ] Test 11: Export functionality ✅
- [ ] Test 12: IOMAD multi-tenancy ✅
- [ ] Test 13: Scheduled tasks ✅
- [ ] Test 14: Audit logging ✅
- [ ] Test 15: Performance testing ✅
- [ ] Test 16: Security testing ✅
- [ ] Test 17: Mobile responsiveness ✅
- [ ] No errors in Moodle log
- [ ] No errors in Apache/Nginx log
- [ ] No JavaScript errors in browser console

## Support

If you encounter issues during testing:

1. Check error logs (Moodle, Apache/Nginx, MySQL)
2. Enable debugging in Moodle config.php
3. Check browser console for JavaScript errors
4. Review deployment guides for specific features
5. Use rollback procedure if necessary

## Estimated Testing Time

- **Quick Test** (Critical features only): 30-45 minutes
- **Standard Test** (All important features): 2-3 hours
- **Comprehensive Test** (All features + performance): 4-6 hours

## Conclusion

This guide provides comprehensive testing coverage for the ManiReports plugin upgrade. Follow each test scenario systematically and document any issues found.

**Remember**: The plugin has been thoroughly developed and all features are implemented. This testing is to verify the upgrade process and ensure everything works in your specific environment.

Good luck with your upgrade! 🚀
