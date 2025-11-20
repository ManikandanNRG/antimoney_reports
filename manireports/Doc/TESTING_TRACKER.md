# ManiReports Plugin Testing Tracker

**Purpose**: Track testing progress and results for the ManiReports plugin upgrade.

**Instructions**: 
1. Run each test in order
2. Report the result to me (Pass/Fail with details)
3. I will mark it as ✅ PASS or ❌ FAIL
4. If failed, I will provide a fix

**Testing Date**: _________________
**Tester Name**: _________________
**Moodle Version**: _________________
**Plugin Version**: 2024111702

---

## 🎯 SECTION 1: QUICK TESTS (15 Minutes)
**Status**: ✅ COMPLETED (6/7 completed - Test 7 pending)

### Test 1: Verify Upgrade Success
**Status**: ✅ COMPLETED
**Command**:
```bash
mysql -u moodle_user -p moodle_db -e "SELECT value FROM mdl_config_plugins WHERE plugin='local_manireports' AND name='version';"
```
**Expected Result**: `2024111702`

**Your Result**:
```
+------------+
| value      |
+------------+
| 2024111702 |
+------------+
1 row in set (0.00 sec)
```

**Outcome**: ✅ PASS
**Notes**: Plugin version correctly upgraded to 2024111702 

---

### Test 2: Check All Tables Exist
**Status**: ✅ COMPLETED
**Command**:
```bash
mysql -u moodle_user -p moodle_db -e "SHOW TABLES LIKE 'mdl_manireports%';"
```
**Expected Result**: 11 tables listed:
- mdl_manireports_customreports
- mdl_manireports_schedules
- mdl_manireports_sched_recip
- mdl_manireports_report_runs
- mdl_manireports_time_sessions
- mdl_manireports_time_daily
- mdl_manireports_scorm_summary
- mdl_manireports_cache_summary
- mdl_manireports_dashboards
- mdl_manireports_dash_widgets
- mdl_manireports_audit_logs

**Your Result**:
```
+---------------------------------------------+
| Tables_in_aktrea_stage_1 (mdl_manireports%) |
+---------------------------------------------+
| mdl_manireports_audit_logs                  |
| mdl_manireports_cache_summary               |
| mdl_manireports_customreports               |
| mdl_manireports_dash_widgets                |
| mdl_manireports_dashboards                  |
| mdl_manireports_report_runs                 |
| mdl_manireports_sched_recip                 |
| mdl_manireports_schedules                   |
| mdl_manireports_scorm_summary               |
| mdl_manireports_time_daily                  |
| mdl_manireports_time_sessions               |
+---------------------------------------------+
11 rows in set (0.00 sec)
```

**Outcome**: ✅ PASS
**Notes**: All 11 required tables exist and are properly created 

---

### Test 3: Access Plugin Settings
**Status**: ✅ COMPLETED
**Steps**:
1. Login as admin
2. Navigate to: Site administration → Plugins → Local plugins → ManiReports
3. Click on "Settings" link
4. Check if settings page loads

**Expected Result**: Settings page loads with configuration options

**Your Result**:
- [x] Issue found: Clicking "ManiReports" showed category page instead of settings
- [x] **FIX APPLIED**: Modified settings.php to add Settings link to category
- [x] Cache cleared required: `sudo -u www-data php /var/www/html/moodle/admin/cli/purge_caches.php`

**Fix Details**:
```
Modified settings.php to:
1. Create ManiReports category in admin menu
2. Add "Settings" page as first item in category
3. Add Dashboard, Custom Reports, Schedules, Audit Log links below
```

**After Fix - Expected Behavior**:
When you click "ManiReports" you should see:
- Settings (configuration page)
- Dashboard
- Custom Reports
- Scheduled Reports
- Audit Log

**Outcome**: ✅ PASS (after fix and cache clear)
**Notes**: Settings page structure is correct, just needed proper menu organization 

---

### Test 4: Access Admin Dashboard
**Status**: ✅ COMPLETED
**Steps**:
1. Navigate to: `https://your-site.com/local/manireports/ui/dashboard.php`
2. Check if dashboard loads
3. Open browser console (F12) and check for JavaScript errors

**Expected Result**: 
- Dashboard loads with widgets (KPI cards, charts)
- No JavaScript errors in console

**Your Result**:
- [x] Dashboard loaded successfully
- [x] Widgets displayed
- [x] No JavaScript errors
- [x] All functionality working as expected

**Error Details** (if any):
```
None - dashboard loads and functions correctly
```

**Outcome**: ✅ PASS
**Notes**: Dashboard loads successfully with all widgets and charts displaying properly 

---

### Test 5: Run a Prebuilt Report
**Status**: ✅ COMPLETED
**Steps**:
1. Navigate to: `https://your-site.com/local/manireports/ui/report_view.php?report=course_completion`
2. Check if report executes

**Expected Result**: Report executes and shows data (or "No data" if empty)

**Your Result**:
- [x] Report executed successfully
- [x] Data displayed correctly
- [x] Report loads without errors
- [x] All report features working (filters, pagination, etc.)

**Error Details** (if any):
```
None - report executes successfully
```

**Outcome**: ✅ PASS
**Notes**: Course completion report loads and displays data correctly 

---

### Test 6: Test Export
**Status**: ✅ COMPLETED
**Steps**:
1. From the report page (Test 5), click "Export CSV"
2. Check if file downloads
3. Open the CSV file

**Expected Result**: 
- CSV file downloads successfully
- Data is readable in the file

**Your Result**:
- [x] CSV downloaded successfully
- [x] File opens correctly
- [x] Data is readable
- [x] **ISSUES FIXED**: 
  - Fixed duplicate `format_row()` method in base_report.php causing 500 errors
  - Fixed `round()` type errors in quiz_attempts.php and scorm_summary.php
  - Fixed `array_keys()` and `array_values()` errors when columns come from cache as stdClass
  - All export formats (CSV, XLSX, PDF) now handle cached data properly

**Fix Details**:
```
1. Removed duplicate format_row() method in base_report.php
2. Cast numeric values to float before round() in quiz_attempts and scorm_summary
3. Cast $columns to array at start of all export methods (CSV, XLSX, PDF)
4. Cast $columns to array in report_view.php before array operations
```

**Outcome**: ✅ PASS (after fixes)
**Notes**: Export functionality fully working for all formats, including when data is cached 

---

### Test 7: Check Error Logs
**Status**: ⏳ PENDING
**Command**:
```bash
tail -50 /var/www/html/moodledata/error.log | grep manireports
```

**Expected Result**: No new errors (or only old ones from before upgrade)

**Your Result**:
```
[Paste error log output here]
```

**Outcome**: ⏳ PENDING
**Notes**: 

---

## 📊 SECTION 2: STANDARD TESTS (1 Hour)
**Status**: ⏳ NOT STARTED

### Test 8: All Prebuilt Reports

#### Test 8.1: Course Completion Report
**Status**: ✅ COMPLETED
**Steps**:
1. Navigate to dashboard → Click "Course Completion" report
2. Apply date filter: "Last 30 days"
3. Test exports: CSV, XLSX, PDF
4. Test company filter (IOMAD)
5. Test "Clear Filters" button
6. Test "Back to Dashboard" navigation

**Expected Result**: 
- Report shows courses with completion data
- All 3 export formats download successfully
- Company filter works correctly
- Clear Filters button resets all filters
- Back to Dashboard button navigates to dashboard

**Your Result**:
- [x] Report executed successfully
- [x] CSV export works
- [x] XLSX export works
- [x] PDF export works
- [x] All export formats work on first and subsequent attempts (cache handling fixed)
- [x] **COMPANY FILTER FIX**: Fixed IOMAD company filtering in all reports
  - Added `apply_iomad_filter()` override in course_completion.php
  - Added `apply_iomad_filter()` override in course_progress.php
  - Added `apply_iomad_filter()` override in user_engagement.php
  - Added `apply_iomad_filter()` override in scorm_summary.php
  - Added `apply_iomad_filter()` override in quiz_attempts.php
  - Company filter now properly filters users by company membership
- [x] **USER SEARCH FIX**: Replaced User ID field with Username/Email search
  - Changed parameter from `userid` to `usersearch` in all reports
  - Changed filter type from `'user'` to `'text'` for better UX
  - Implemented LIKE search on username and email fields
  - Added `prepare_sql_params()` method to handle wildcard parameters
  - User can now search by typing partial username or email
- [x] **UI IMPROVEMENTS**: Added navigation and filter management
  - Added "Back to Dashboard" button at top of report pages
  - Added "Clear Filters" button next to Apply button
  - Added language strings for navigation

**Error Details** (if any):
```
None - all functionality working correctly after fixes
```

**Outcome**: ✅ PASS
**Notes**: 
- Export functionality verified working for all formats with proper cache handling
- Company filtering now works correctly across all reports
- User search is now user-friendly with text input instead of numeric ID
- Navigation and filter management improved 

---

#### Test 8.2: Course Progress Report
**Status**: ⏳ PENDING
**Steps**:
1. Navigate to: `/local/manireports/ui/report_view.php?report=course_progress`
2. Select a specific course from filter
3. Check execution time

**Expected Result**: 
- Shows per-user progress percentages
- Execution time < 10 seconds

**Your Result**:
- [ ] Report executed successfully
- [ ] Progress percentages displayed
- [ ] Execution time: _____ seconds
- [ ] Error occurred (describe below)

**Error Details** (if any):
```
[Paste error message]
```

**Outcome**: ⏳ PENDING
**Notes**: 

---

#### Test 8.3: SCORM Summary Report
**Status**: ⏳ PENDING
**Steps**:
1. Navigate to: `/local/manireports/ui/report_view.php?report=scorm_summary`
2. Check displayed data

**Expected Result**: 
- Shows SCORM activities with attempt counts
- Displays completion status and average time

**Your Result**:
- [ ] Report executed successfully
- [ ] SCORM data displayed
- [ ] Attempt counts shown
- [ ] Completion status shown
- [ ] Error occurred (describe below)

**Error Details** (if any):
```
[Paste error message]
```

**Outcome**: ⏳ PENDING
**Notes**: 

---

#### Test 8.4: User Engagement Report
**Status**: ⏳ PENDING
**Steps**:
1. Navigate to: `/local/manireports/ui/report_view.php?report=user_engagement`
2. Test date range filter

**Expected Result**: 
- Shows time spent and active days per user
- Data updates based on filter

**Your Result**:
- [ ] Report executed successfully
- [ ] Time spent data displayed
- [ ] Active days displayed
- [ ] Filter works
- [ ] Error occurred (describe below)

**Error Details** (if any):
```
[Paste error message]
```

**Outcome**: ⏳ PENDING
**Notes**: 

---

#### Test 8.5: Quiz Attempts Report
**Status**: ⏳ PENDING
**Steps**:
1. Navigate to: `/local/manireports/ui/report_view.php?report=quiz_attempts`
2. Check displayed data

**Expected Result**: 
- Shows quiz attempts with scores
- Displays average scores per quiz

**Your Result**:
- [ ] Report executed successfully
- [ ] Quiz attempts displayed
- [ ] Scores shown
- [ ] Average scores calculated
- [ ] Error occurred (describe below)

**Error Details** (if any):
```
[Paste error message]
```

**Outcome**: ⏳ PENDING
**Notes**: 

---

### Test 9: Custom SQL Reports
**Status**: ⏳ PENDING

#### Test 9.1: Create Custom SQL Report
**Steps**:
1. Navigate to: Site admin → Reports → ManiReports → Custom Reports
2. Click "Create New Report"
3. Fill form:
   - Name: `Test SQL Report - User List`
   - Type: `SQL`
   - SQL Query:
     ```sql
     SELECT u.id, u.firstname, u.lastname, u.email, u.timecreated
     FROM {user} u
     WHERE u.deleted = 0
     ORDER BY u.timecreated DESC
     LIMIT 20
     ```
4. Click Save

**Expected Result**: Report saved successfully

**Your Result**:
- [ ] Report created successfully
- [ ] Report appears in list
- [ ] Error occurred (describe below)

**Error Details** (if any):
```
[Paste error message]
```

**Outcome**: ⏳ PENDING
**Notes**: 

---

#### Test 9.2: Execute Custom SQL Report
**Steps**:
1. From Custom Reports list, find "Test SQL Report - User List"
2. Click "View"
3. Test "Export CSV"

**Expected Result**: 
- Report shows 20 users with their details
- CSV export works

**Your Result**:
- [ ] Report executed successfully
- [ ] Shows user data
- [ ] CSV export works
- [ ] Error occurred (describe below)

**Error Details** (if any):
```
[Paste error message]
```

**Outcome**: ⏳ PENDING
**Notes**: 

---

#### Test 9.3: Test SQL Security
**Steps**:
1. Try creating report with malicious SQL:
   ```sql
   SELECT * FROM {user}; DROP TABLE mdl_user; --
   ```
2. Try to save

**Expected Result**: Error message - "Invalid SQL query. Please check for blocked keywords"

**Your Result**:
- [ ] Error message displayed correctly
- [ ] SQL was blocked
- [ ] Security check failed (SQL was accepted)

**Error Details** (if any):
```
[Paste error message]
```

**Outcome**: ⏳ PENDING
**Notes**: 

---

### Test 10: GUI Report Builder (NEW FEATURE)
**Status**: ⏳ PENDING

#### Test 10.1: Access GUI Builder
**Steps**:
1. Navigate to: Site admin → Reports → ManiReports → GUI Report Builder
2. Check if interface loads

**Expected Result**: Builder interface loads with table selector

**Your Result**:
- [ ] GUI builder loaded successfully
- [ ] Table selector visible
- [ ] Error occurred (describe below)

**Error Details** (if any):
```
[Paste error message]
```

**Outcome**: ⏳ PENDING
**Notes**: 

---

#### Test 10.2: Create Simple GUI Report
**Steps**:
1. Click "Add Table" → Select `user`
2. Check columns: `firstname`, `lastname`, `email`
3. Click "Save Report"
4. Name: `Test GUI Report - Users`

**Expected Result**: Report saved successfully

**Your Result**:
- [ ] Report created successfully
- [ ] Report appears in custom reports list with "(GUI)" label
- [ ] Error occurred (describe below)

**Error Details** (if any):
```
[Paste error message]
```

**Outcome**: ⏳ PENDING
**Notes**: 

---

#### Test 10.3: Execute GUI Report
**Steps**:
1. Go to Custom Reports list
2. Find "Test GUI Report - Users (GUI)"
3. Click "View"
4. Test exports: CSV, XLSX, PDF

**Expected Result**: 
- Report shows user data
- All export formats work

**Your Result**:
- [ ] Report executed successfully
- [ ] User data displayed
- [ ] CSV export works
- [ ] XLSX export works
- [ ] PDF export works
- [ ] Error occurred (describe below)

**Error Details** (if any):
```
[Paste error message]
```

**Outcome**: ⏳ PENDING
**Notes**: 

---

#### Test 10.4: Create GUI Report with JOIN
**Steps**:
1. Click "Create New Report" in GUI builder
2. Add tables:
   - Table 1: `user`
   - Table 2: `user_enrolments` (JOIN on `user.id = user_enrolments.userid`)
3. Select columns: `user.firstname`, `user.lastname`, `COUNT(user_enrolments.id) as enrolments`
4. Add GROUP BY: `user.id`
5. Save as: `User Enrolment Count`
6. Execute report

**Expected Result**: Shows users with their enrollment counts

**Your Result**:
- [ ] Report with JOIN created successfully
- [ ] Report executed successfully
- [ ] Shows enrollment counts
- [ ] Error occurred (describe below)

**Error Details** (if any):
```
[Paste error message]
```

**Outcome**: ⏳ PENDING
**Notes**: 

---

### Test 11: Report Scheduling
**Status**: ⏳ PENDING

#### Test 11.1: Schedule Prebuilt Report
**Steps**:
1. Navigate to: Site admin → Reports → ManiReports → Scheduled Reports
2. Click "Create Schedule"
3. Fill form:
   - Name: `Daily Course Completion Report`
   - Report Category: `Prebuilt Reports`
   - Report Type: `Course Completion`
   - Format: `CSV`
   - Frequency: `Daily`
   - Time: `08:00`
   - Recipients: `your-email@example.com`
   - Enabled: `Yes`
4. Click Save

**Expected Result**: Schedule appears in list

**Your Result**:
- [ ] Schedule created successfully
- [ ] Schedule appears in list
- [ ] Error occurred (describe below)

**Error Details** (if any):
```
[Paste error message]
```

**Outcome**: ⏳ PENDING
**Notes**: 

---

#### Test 11.2: Schedule Custom Report (NEW)
**Steps**:
1. Click "Create Schedule"
2. Fill form:
   - Name: `Weekly User Report`
   - Report Category: `Custom Reports`
   - Custom Report: Select `Test GUI Report - Users`
   - Format: `XLSX`
   - Frequency: `Weekly`
   - Day: `Monday`
   - Time: `09:00`
   - Recipients: `your-email@example.com`
   - Enabled: `Yes`
3. Click Save

**Expected Result**: Schedule appears in list

**Your Result**:
- [ ] Schedule created successfully
- [ ] Custom report dropdown populated
- [ ] Schedule appears in list
- [ ] Error occurred (describe below)

**Error Details** (if any):
```
[Paste error message]
```

**Outcome**: ⏳ PENDING
**Notes**: 

---

#### Test 11.3: Test Manual Execution
**Steps**:
1. Run command:
```bash
sudo -u www-data php /var/www/html/moodle/admin/cli/scheduled_task.php \
  --execute=\\local_manireports\\task\\report_scheduler
```
2. Check email for report attachment
3. Check schedule list for updated "Last Run" time

**Expected Result**: 
- Task completes without errors
- Email received with attachment
- "Last Run" time updated

**Your Result**:
- [ ] Task completed successfully
- [ ] Email received
- [ ] Attachment included
- [ ] Last Run time updated
- [ ] Error occurred (describe below)

**Command Output**:
```
[Paste command output]
```

**Outcome**: ⏳ PENDING
**Notes**: 

---

### Test 12: Dashboard Builder (NEW FEATURE)
**Status**: ⏳ PENDING

#### Test 12.1: Access Dashboard Builder
**Steps**:
1. Navigate to: Site admin → Reports → ManiReports → Dashboard Builder
2. Check if interface loads

**Expected Result**: Builder interface loads with drag-and-drop grid

**Your Result**:
- [ ] Dashboard builder loaded successfully
- [ ] Drag-and-drop grid visible
- [ ] Error occurred (describe below)

**Error Details** (if any):
```
[Paste error message]
```

**Outcome**: ⏳ PENDING
**Notes**: 

---

#### Test 12.2: Create Custom Dashboard
**Steps**:
1. Click "Create New Dashboard"
2. Fill form:
   - Name: `My Custom Dashboard`
   - Scope: `Personal`
3. Add widgets:
   - KPI Widget: `Total Active Users`
   - Line Chart: `Course Completions Trend`
   - Bar Chart: `Top 10 Courses`
4. Arrange widgets in grid
5. Click "Save Dashboard"

**Expected Result**: Dashboard saved successfully

**Your Result**:
- [ ] Dashboard created successfully
- [ ] Widgets added successfully
- [ ] Drag-and-drop works
- [ ] Dashboard saved
- [ ] Error occurred (describe below)

**Error Details** (if any):
```
[Paste error message]
```

**Outcome**: ⏳ PENDING
**Notes**: 

---

#### Test 12.3: View Custom Dashboard
**Steps**:
1. Navigate to: My Dashboards
2. Select "My Custom Dashboard"
3. Check if widgets display data

**Expected Result**: Dashboard displays with all widgets showing data

**Your Result**:
- [ ] Dashboard loaded successfully
- [ ] All widgets displayed
- [ ] Data loaded in widgets
- [ ] Error occurred (describe below)

**Error Details** (if any):
```
[Paste error message]
```

**Outcome**: ⏳ PENDING
**Notes**: 

---

### Test 13: Drill-Down Functionality (NEW FEATURE)
**Status**: ⏳ PENDING

#### Test 13.1: Test Chart Click
**Steps**:
1. Access Admin Dashboard: `/local/manireports/ui/dashboard.php`
2. Find a chart (e.g., Course Usage chart)
3. Click on a data point in the chart

**Expected Result**: 
- Navigates to filtered report view
- Applied filters displayed at top in blue badges
- Data is filtered based on clicked value

**Your Result**:
- [ ] Drill-down navigation works
- [ ] Filters displayed correctly
- [ ] Data is filtered
- [ ] Error occurred (describe below)

**Error Details** (if any):
```
[Paste error message]
```

**Outcome**: ⏳ PENDING
**Notes**: 

---

#### Test 13.2: Test Filter Management
**Steps**:
1. From drill-down view, click "×" on a filter badge
2. Click "Clear All" button

**Expected Result**: 
- Individual filter removed when clicking "×"
- All filters cleared when clicking "Clear All"

**Your Result**:
- [ ] Individual filter removal works
- [ ] Clear All works
- [ ] View refreshes correctly
- [ ] Error occurred (describe below)

**Error Details** (if any):
```
[Paste error message]
```

**Outcome**: ⏳ PENDING
**Notes**: 

---

#### Test 13.3: Test Export from Drill-Down
**Steps**:
1. Drill down to filtered view
2. Click "Export CSV"
3. Open the CSV file

**Expected Result**: CSV contains only filtered data

**Your Result**:
- [ ] Export works from drill-down view
- [ ] CSV contains only filtered data
- [ ] Error occurred (describe below)

**Error Details** (if any):
```
[Paste error message]
```

**Outcome**: ⏳ PENDING
**Notes**: 

---

#### Test 13.4: Test Browser Back Button
**Steps**:
1. Drill down multiple levels
2. Click browser back button

**Expected Result**: Returns to previous view

**Your Result**:
- [ ] Back button works correctly
- [ ] Returns to previous view
- [ ] Error occurred (describe below)

**Error Details** (if any):
```
[Paste error message]
```

**Outcome**: ⏳ PENDING
**Notes**: 

---

### Test 14: Time Tracking
**Status**: ⏳ PENDING

#### Test 14.1: Test Heartbeat
**Steps**:
1. Open browser console (F12 → Console tab)
2. Enroll in a course and navigate to course page
3. Stay on page for 2 minutes
4. Watch console for heartbeat messages
5. Check Network tab for AJAX requests

**Expected Result**: 
- AJAX requests to `/local/manireports/ui/ajax/heartbeat.php` every 20-30 seconds
- Successful 200 responses

**Your Result**:
- [ ] Heartbeat requests visible in console
- [ ] Requests sent every 20-30 seconds
- [ ] Successful responses (200)
- [ ] Error occurred (describe below)

**Console Output**:
```
[Paste relevant console messages]
```

**Outcome**: ⏳ PENDING
**Notes**: 

---

#### Test 14.2: Verify Session Tracking
**Steps**:
1. Run command (replace YOUR_USER_ID with your actual user ID):
```bash
mysql -u moodle_user -p moodle_db -e "SELECT * FROM mdl_manireports_time_sessions WHERE userid = YOUR_USER_ID ORDER BY lastupdated DESC LIMIT 3;"
```

**Expected Result**: Active session record with recent `lastupdated` timestamp

**Your Result**:
```
[Paste query result]
```

**Outcome**: ⏳ PENDING
**Notes**: 

---

#### Test 14.3: Test Time Aggregation
**Steps**:
1. Run command:
```bash
sudo -u www-data php /var/www/html/moodle/admin/cli/scheduled_task.php \
  --execute=\\local_manireports\\task\\time_aggregation
```
2. Check daily summary:
```bash
mysql -u moodle_user -p moodle_db -e "SELECT * FROM mdl_manireports_time_daily WHERE userid = YOUR_USER_ID ORDER BY date DESC LIMIT 3;"
```

**Expected Result**: 
- Task completes without errors
- Daily summary records created

**Command Output**:
```
[Paste command output]
```

**Query Result**:
```
[Paste query result]
```

**Outcome**: ⏳ PENDING
**Notes**: 

---

### Test 15: Scheduled Tasks
**Status**: ⏳ PENDING

#### Test 15.1: List All Tasks
**Steps**:
1. Run command:
```bash
sudo -u www-data php /var/www/html/moodle/admin/cli/scheduled_task.php --list | grep manireports
```

**Expected Result**: 5 tasks listed:
- time_aggregation
- cache_builder
- report_scheduler
- scorm_summary
- cleanup_old_data

**Your Result**:
```
[Paste command output]
```

**Outcome**: ⏳ PENDING
**Notes**: 

---

#### Test 15.2: Execute Each Task
**Steps**:
Run each command and report results:

**Time Aggregation**:
```bash
sudo -u www-data php /var/www/html/moodle/admin/cli/scheduled_task.php \
  --execute=\\local_manireports\\task\\time_aggregation
```

**Cache Builder**:
```bash
sudo -u www-data php /var/www/html/moodle/admin/cli/scheduled_task.php \
  --execute=\\local_manireports\\task\\cache_builder
```

**SCORM Summary**:
```bash
sudo -u www-data php /var/www/html/moodle/admin/cli/scheduled_task.php \
  --execute=\\local_manireports\\task\\scorm_summary
```

**Cleanup Old Data**:
```bash
sudo -u www-data php /var/www/html/moodle/admin/cli/scheduled_task.php \
  --execute=\\local_manireports\\task\\cleanup_old_data
```

**Expected Result**: All tasks complete without errors

**Your Results**:
- [ ] time_aggregation: SUCCESS / FAILED
- [ ] cache_builder: SUCCESS / FAILED
- [ ] scorm_summary: SUCCESS / FAILED
- [ ] cleanup_old_data: SUCCESS / FAILED

**Command Outputs**:
```
[Paste outputs for any failed tasks]
```

**Outcome**: ⏳ PENDING
**Notes**: 

---

### Test 16: Audit Logging
**Status**: ⏳ PENDING

#### Test 16.1: Access Audit Log Viewer
**Steps**:
1. Navigate to: Site admin → Reports → ManiReports → Audit Logs
2. Check if page loads
3. Verify logged actions are visible

**Expected Result**: 
- Audit log page loads with entries
- Actions like report creation, schedule creation visible

**Your Result**:
- [ ] Audit log page loaded
- [ ] Entries visible
- [ ] Actions logged correctly
- [ ] Error occurred (describe below)

**Error Details** (if any):
```
[Paste error message]
```

**Outcome**: ⏳ PENDING
**Notes**: 

---

#### Test 16.2: Test Filters and Export
**Steps**:
1. Filter by date range
2. Filter by action type
3. Click "Export CSV"

**Expected Result**: 
- Filters work correctly
- Audit log exports successfully

**Your Result**:
- [ ] Date filter works
- [ ] Action filter works
- [ ] Export works
- [ ] Error occurred (describe below)

**Error Details** (if any):
```
[Paste error message]
```

**Outcome**: ⏳ PENDING
**Notes**: 

---

## 🔒 SECTION 3: SECURITY TESTS (15 Minutes)
**Status**: ⏳ NOT STARTED

### Test 17: Capability Enforcement
**Status**: ⏳ PENDING

**Steps**:
1. Login as student (non-admin user)
2. Try to access:
   - Admin dashboard: `/local/manireports/ui/dashboard.php`
   - Custom reports: `/local/manireports/ui/custom_reports.php`
   - Schedules: `/local/manireports/ui/schedules.php`

**Expected Result**: Access denied or redirected for all three

**Your Result**:
- [ ] Admin dashboard: Access denied
- [ ] Custom reports: Access denied
- [ ] Schedules: Access denied
- [ ] Security check failed (student has access)

**Error Details** (if any):
```
[Paste error message or describe what happened]
```

**Outcome**: ⏳ PENDING
**Notes**: 

---

### Test 18: SQL Injection Prevention
**Status**: ⏳ PENDING

**Steps**:
1. Create custom report with malicious SQL:
```sql
SELECT * FROM {user} WHERE id = 1; DROP TABLE mdl_user; --
```
2. Try to save

**Expected Result**: Error message about blocked keywords

**Your Result**:
- [ ] SQL blocked successfully
- [ ] Error message displayed
- [ ] Security check failed (SQL was accepted)

**Error Details** (if any):
```
[Paste error message]
```

**Outcome**: ⏳ PENDING
**Notes**: 

---

### Test 19: XSS Prevention
**Status**: ⏳ PENDING

**Steps**:
1. Create report with name: `<script>alert('XSS')</script>`
2. Save report
3. View report list

**Expected Result**: Script not executed, name displayed as text

**Your Result**:
- [ ] XSS prevented successfully
- [ ] Script displayed as text
- [ ] Security check failed (script executed)

**Error Details** (if any):
```
[Paste error message or describe what happened]
```

**Outcome**: ⏳ PENDING
**Notes**: 

---

## ✅ FINAL VERIFICATION
**Status**: ⏳ NOT STARTED

### Final Check 1: Error Logs
**Command**:
```bash
# Moodle error log
tail -100 /var/www/html/moodledata/error.log | grep manireports

# Apache/Nginx error log
sudo tail -100 /var/log/apache2/error.log | grep manireports
```

**Expected Result**: No new errors

**Your Result**:
```
[Paste error log output]
```

**Outcome**: ⏳ PENDING
**Notes**: 

---

### Final Check 2: Performance
**Measurements**:
- Dashboard load time: _____ seconds (Expected: < 3 seconds)
- Report execution time: _____ seconds (Expected: < 10 seconds)
- Export generation time: _____ seconds (Expected: < 30 seconds)

**Your Result**:
- [ ] All performance targets met
- [ ] Some targets not met (specify below)

**Details**:
```
[Provide details if targets not met]
```

**Outcome**: ⏳ PENDING
**Notes**: 

---

## 📊 TESTING SUMMARY

### Quick Tests (Section 1)
- Total Tests: 7
- Passed: ___
- Failed: ___
- Pending: ___

### Standard Tests (Section 2)
- Total Tests: 9
- Passed: ___
- Failed: ___
- Pending: ___

### Security Tests (Section 3)
- Total Tests: 3
- Passed: ___
- Failed: ___
- Pending: ___

### Overall Status
- **Total Tests**: 19
- **Passed**: ___
- **Failed**: ___
- **Success Rate**: ___%

### Critical Issues Found
```
[List any critical issues that need immediate attention]
```

### Non-Critical Issues Found
```
[List any minor issues or improvements needed]
```

### Recommendations
```
[Any recommendations for deployment or usage]
```

---

## 🎯 FINAL DECISION

**Is the plugin ready for production?**
- [ ] ✅ YES - All critical tests passed
- [ ] ⚠️ CONDITIONAL - Minor issues need fixing
- [ ] ❌ NO - Critical issues must be resolved

**Tester Signature**: _________________
**Date**: _________________

---

## 📝 NOTES FOR DEVELOPER

**Instructions for reporting results**:
1. Run each test in order
2. For each test, provide:
   - The actual result (command output, screenshot description, or observation)
   - Mark checkboxes as appropriate
   - Paste any error messages
3. Report to me: "Test X.Y completed - [PASS/FAIL] - [brief description]"
4. I will:
   - Mark the test as ✅ PASS or ❌ FAIL
   - Provide fixes for any failures
   - Update the summary

**Example Report Format**:
```
Test 1 completed - PASS - Version shows 2024111702
Test 2 completed - FAIL - Only 10 tables found, missing mdl_manireports_audit_logs
Test 3 completed - PASS - Settings page loads without errors
```

Let's start testing! Report Test 1 results when ready.
