# ManiReports - Comprehensive Testing Scenarios (Tasks 11-20)

## Task 11: Report Scheduling System - DETAILED

### Test Scenario 11.1: Schedule Creation - DETAILED

#### Step 1: Navigate to Schedules
1. Login as admin
2. Navigate to: **Dashboard → Schedules**

**Expected:** Page loads without errors

#### Step 2: Create Schedule
1. Click **"Create Schedule"**
2. Fill form:
   - Name: **"Weekly Course Completion"**
   - Report: **"Course Completion"**
   - Frequency: **"Weekly"**
   - Format: **"CSV"**
   - Day: **"Monday"**
   - Time: **"09:00"**
3. Click **"Add Recipient"**
4. Select users to receive report
5. Click **"Save"**

**Expected:** Schedule created and appears in list

#### Step 3: Verify in Database
```bash
mysql -u moodle_user -p moodle_db -e "
SELECT id, name, frequency, format, enabled, nextrun 
FROM mdl_manireports_schedules 
WHERE name = 'Weekly Course Completion';
"
```

**Expected:** Schedule record exists with correct data

### Test Scenario 11.2: Schedule Execution
**Steps:**
1. Create schedule with next_run = now
2. Run scheduler task:
   ```bash
   sudo -u www-data php admin/cli/scheduled_task.php \
     --execute=\\local_manireports\\task\\report_scheduler
   ```
3. Check database:
   ```sql
   SELECT * FROM mdl_manireports_report_runs 
   WHERE scheduleid = [schedule_id] 
   ORDER BY timestarted DESC LIMIT 1;
   ```
4. Verify record has:
   - status = 'completed'
   - rowcount > 0
   - timefinished set

**Expected Result:** Schedule executed successfully

### Test Scenario 11.3: Email Delivery
**Steps:**
1. Create schedule with test user as recipient
2. Run scheduler task
3. Check test user's email inbox
4. Verify email received with:
   - Subject containing report name
   - CSV attachment
   - Report summary in body

**Expected Result:** Email delivered with attachment

### Test Scenario 11.4: Schedule Retry Logic
**Steps:**
1. Create schedule
2. Manually set status to 'failed' in database
3. Run scheduler task
4. Verify task retries with exponential backoff
5. Check failcount increments

**Expected Result:** Failed schedules retried correctly

---

## Task 12: Audit Logging

### Test Scenario 12.1: Audit Log Recording
**Steps:**
1. Create a custom report
2. Check database:
   ```sql
   SELECT * FROM mdl_manireports_audit_logs 
   WHERE action = 'create' 
   AND objecttype = 'customreport' 
   ORDER BY timecreated DESC LIMIT 1;
   ```
3. Verify record has:
   - userid (your ID)
   - action = 'create'
   - objecttype = 'customreport'
   - objectid (report ID)
   - details (JSON)
   - timecreated (recent)

**Expected Result:** Audit log entry created

### Test Scenario 12.2: Audit Log Viewer
**Steps:**
1. Navigate to: Site administration → Reports → Audit Logs
2. Verify page loads
3. Apply filters:
   - User filter
   - Date range filter
   - Action filter
4. Verify logs display with:
   - User name
   - Action
   - Object type
   - Timestamp
5. Test pagination

**Expected Result:** Audit logs viewable and filterable

### Test Scenario 12.3: Report Run History
**Steps:**
1. Navigate to custom report
2. Click "View History"
3. Verify table shows:
   - Start time
   - Finish time
   - Duration
   - Status
   - Row count
   - Error message (if failed)
4. Click on run to view details

**Expected Result:** Report run history visible

---

## Task 13: Role-Based Dashboards

### Test Scenario 13.1: Admin Dashboard
**Steps:**
1. Login as admin
2. Navigate to: Dashboard
3. Verify page displays:
   - Site-wide statistics
   - All companies (if IOMAD)
   - Course usage heatmap
   - Inactive users widget
   - Multiple charts
4. Test filters work
5. Verify load time < 2 seconds

**Expected Result:** Admin dashboard displays correctly

### Test Scenario 13.2: Manager Dashboard (IOMAD)
**Steps:**
1. Login as company manager
2. Navigate to: Dashboard
3. Verify displays only company data:
   - Company statistics
   - Department reports
   - Completion widgets
   - Progress widgets
4. Verify cannot see other companies' data

**Expected Result:** Manager dashboard shows company-specific data

### Test Scenario 13.3: Teacher Dashboard
**Steps:**
1. Login as teacher
2. Navigate to: Dashboard
3. Verify displays:
   - Student progress for teacher's courses
   - Activity completion statistics
   - Quiz analytics
   - Time spent per user
4. Verify only shows teacher's courses

**Expected Result:** Teacher dashboard shows course-specific data

### Test Scenario 13.4: Student Dashboard
**Steps:**
1. Login as student
2. Navigate to: Dashboard
3. Verify displays:
   - Personal progress
   - Time tracking statistics
   - Course completion status
   - Upcoming deadlines
4. Verify only shows student's own data

**Expected Result:** Student dashboard shows personal data only

---

## Task 14: Course Completion Dashboard (MVP)

### Test Scenario 14.1: MVP Dashboard Load
**Steps:**
1. Navigate to: Dashboard → Course Completion
2. Measure load time (should be < 2 seconds)
3. Verify displays:
   - Course name column
   - Enrollment count column
   - Completion % column
   - Trend chart
4. Verify data is accurate

**Expected Result:** MVP dashboard loads quickly with correct data

### Test Scenario 14.2: MVP Filters
**Steps:**
1. Apply date filter (30 days)
2. Verify data updates
3. Apply date filter (90 days)
4. Verify data updates
5. Apply company filter (IOMAD)
6. Verify only company courses shown

**Expected Result:** Filters work correctly

### Test Scenario 14.3: MVP Export
**Steps:**
1. Click "Export as CSV"
2. Verify file downloads
3. Open and verify data matches table

**Expected Result:** Export works correctly

---

## Task 15: Chart Rendering System

### Test Scenario 15.1: Line Chart Rendering
**Steps:**
1. Navigate to dashboard
2. Verify line charts render:
   - Trend lines visible
   - X-axis labels correct
   - Y-axis labels correct
   - Legend visible
3. Hover over data points
4. Verify tooltips show values

**Expected Result:** Line charts render correctly

### Test Scenario 15.2: Bar Chart Rendering
**Steps:**
1. Navigate to report with bar chart
2. Verify bars render:
   - Correct heights
   - Colors distinct
   - Labels visible
3. Hover over bars
4. Verify tooltips show values

**Expected Result:** Bar charts render correctly

### Test Scenario 15.3: Pie Chart Rendering
**Steps:**
1. Navigate to report with pie chart
2. Verify pie slices render:
   - Correct sizes
   - Colors distinct
   - Labels visible
3. Hover over slices
4. Verify tooltips show percentages

**Expected Result:** Pie charts render correctly

---

## Task 16: AJAX Filter System

### Test Scenario 16.1: Filter Change Detection
**Steps:**
1. Navigate to dashboard
2. Open browser DevTools → Network tab
3. Change date filter
4. Verify AJAX request sent to `/local/manireports/ui/ajax/dashboard_data.php`
5. Verify response contains updated data
6. Verify dashboard updates without page reload

**Expected Result:** Filters trigger AJAX updates

### Test Scenario 16.2: Filter Debouncing
**Steps:**
1. Navigate to dashboard
2. Open Network tab
3. Rapidly change filter multiple times
4. Verify only one AJAX request sent (debounced)
5. Verify request sent 300ms after last change

**Expected Result:** Filter changes debounced correctly

### Test Scenario 16.3: Filter State Persistence
**Steps:**
1. Apply filters on dashboard
2. Refresh page
3. Verify filters still applied
4. Check URL parameters
5. Verify filters in sessionStorage

**Expected Result:** Filter state persisted across page reloads

---

## Task 17: Responsive UI Foundation

### Test Scenario 17.1: Desktop Responsiveness
**Steps:**
1. Open dashboard on desktop (1920x1080)
2. Verify layout looks good
3. Verify all elements visible
4. Verify no horizontal scrolling

**Expected Result:** Desktop layout correct

### Test Scenario 17.2: Tablet Responsiveness
**Steps:**
1. Open dashboard on tablet (768x1024)
2. Verify layout adapts
3. Verify all elements visible
4. Verify no horizontal scrolling
5. Verify touch-friendly buttons

**Expected Result:** Tablet layout correct

### Test Scenario 17.3: Mobile Responsiveness
**Steps:**
1. Open dashboard on mobile (375x667)
2. Verify layout adapts
3. Verify all elements visible
4. Verify no horizontal scrolling
5. Verify touch-friendly buttons
6. Verify charts readable

**Expected Result:** Mobile layout correct

### Test Scenario 17.4: Loading Indicators
**Steps:**
1. Navigate to dashboard
2. Verify loading spinner appears while data loads
3. Verify skeleton loaders show for tables
4. Verify loading completes when data arrives

**Expected Result:** Loading indicators display correctly

---

## Task 18: Custom Dashboard Builder

### Test Scenario 18.1: Dashboard Creation
**Steps:**
1. Navigate to: Dashboard → Dashboard Builder
2. Click "Create Dashboard"
3. Enter name: "Test Dashboard"
4. Select scope: "Personal"
5. Click "Create"
6. Verify dashboard builder opens

**Expected Result:** Dashboard created and builder opens

### Test Scenario 18.2: Widget Addition
**Steps:**
1. In dashboard builder, click "Add Widget"
2. Select widget type: "KPI"
3. Configure:
   - Title: "Total Courses"
   - Data source: "Course Completion"
4. Click "Save"
5. Verify widget appears on grid

**Expected Result:** Widget added to dashboard

### Test Scenario 18.3: Widget Drag-Drop
**Steps:**
1. In dashboard builder, drag widget to new position
2. Verify position updates
3. Drag another widget
4. Verify both positions update

**Expected Result:** Drag-drop works correctly

### Test Scenario 18.4: Widget Resize
**Steps:**
1. In dashboard builder, resize widget
2. Verify size updates
3. Verify other widgets reflow

**Expected Result:** Widget resizing works

### Test Scenario 18.5: Dashboard Save
**Steps:**
1. Configure dashboard with multiple widgets
2. Click "Save Dashboard"
3. Verify success message
4. Navigate away and back
5. Verify dashboard layout persisted

**Expected Result:** Dashboard configuration saved

---

## Task 19: GUI Report Builder

### Test Scenario 19.1: Table Selection
**Steps:**
1. Navigate to: Reports → GUI Report Builder
2. Click "Select Table"
3. Verify list of available tables:
   - mdl_course
   - mdl_user
   - mdl_course_completions
   - etc.
4. Select "mdl_course"
5. Verify table selected

**Expected Result:** Table selection works

### Test Scenario 19.2: Column Selection
**Steps:**
1. After selecting table, view available columns
2. Select columns:
   - id
   - fullname
   - category
3. Verify columns selected
4. Verify data types shown

**Expected Result:** Column selection works

### Test Scenario 19.3: Filter Builder
**Steps:**
1. Add filter condition:
   - Column: "category"
   - Operator: "="
   - Value: "1"
2. Verify filter added
3. Add another filter
4. Verify multiple filters work

**Expected Result:** Filter builder works

### Test Scenario 19.4: SQL Preview
**Steps:**
1. Configure report with table, columns, filters
2. View SQL preview
3. Verify SQL is correct
4. Verify SQL is read-only

**Expected Result:** SQL preview shows correct query

### Test Scenario 19.5: Report Execution
**Steps:**
1. Configure report
2. Click "Execute"
3. Verify results display in table
4. Verify data matches SQL query

**Expected Result:** Report executes correctly

---

## Task 20: Drill-Down Functionality

### Test Scenario 20.1: Chart Click Handler
**Steps:**
1. Navigate to dashboard with chart
2. Click on data point in chart
3. Verify drill-down report opens
4. Verify filtered data shown

**Expected Result:** Chart drill-down works

### Test Scenario 20.2: Drill-Down Filters
**Steps:**
1. Perform drill-down from chart
2. Verify applied filters displayed
3. Verify data filtered correctly
4. Verify can modify filters

**Expected Result:** Drill-down filters applied correctly

### Test Scenario 20.3: Navigation History
**Steps:**
1. Perform drill-down
2. Perform another drill-down
3. Click browser back button
4. Verify previous drill-down view restored

**Expected Result:** Navigation history works

### Test Scenario 20.4: Drill-Down Export
**Steps:**
1. Perform drill-down
2. Click "Export as CSV"
3. Verify file downloads
4. Verify only filtered data exported

**Expected Result:** Drill-down export works


---

## DETAILED TESTING INSTRUCTIONS FOR PART 2

### Quick Reference for All Tasks 11-20

**Task 11: Report Scheduling**
- Create schedule via UI
- Verify in database: `SELECT * FROM mdl_manireports_schedules;`
- Run scheduler task: `sudo -u www-data php admin/cli/scheduled_task.php --execute=\\local_manireports\\task\\report_scheduler`
- Check report_runs table: `SELECT * FROM mdl_manireports_report_runs;`
- Verify email received by recipient

**Task 12: Audit Logging**
- Perform action (create/edit/delete report)
- Check audit log: `SELECT * FROM mdl_manireports_audit_logs ORDER BY timecreated DESC LIMIT 1;`
- Verify: userid, action, objecttype, objectid, timestamp
- Navigate to: Site administration → Reports → Audit Logs
- Verify logs visible and filterable

**Task 13: Role-Based Dashboards**
- Login as admin → Navigate to Dashboard
- Verify admin dashboard displays site-wide statistics
- Login as manager → Verify manager dashboard shows company data only
- Login as teacher → Verify teacher dashboard shows course data only
- Login as student → Verify student dashboard shows personal data only

**Task 14: Course Completion Dashboard (MVP)**
- Navigate to: Dashboard → Course Completion
- Measure load time (should be < 2 seconds)
- Verify table displays: course name, enrollment count, completion %
- Test filters: date range, company (IOMAD)
- Test export: CSV, XLSX, PDF
- Verify chart renders with trend data

**Task 15: Chart Rendering**
- Navigate to dashboard with charts
- Verify Chart.js library loaded (check Network tab)
- Verify charts render: line, bar, pie
- Hover over data points
- Verify tooltips show values
- Verify legend visible

**Task 16: AJAX Filter System**
- Navigate to dashboard
- Open Network tab
- Change filter
- Verify AJAX request sent to `/local/manireports/ui/ajax/dashboard_data.php`
- Verify dashboard updates without page reload
- Verify URL parameters updated
- Verify filter state persisted in sessionStorage

**Task 17: Responsive UI**
- Test on desktop (1920x1080): Verify layout correct
- Test on tablet (768x1024): Verify responsive layout
- Test on mobile (375x667): Verify mobile layout
- Verify no horizontal scrolling
- Verify touch-friendly buttons

**Task 18: Custom Dashboard Builder**
- Navigate to: Dashboard → Dashboard Builder
- Click "Create Dashboard"
- Add widgets (KPI, chart, table)
- Drag widgets to reposition
- Resize widgets
- Click "Save Dashboard"
- Navigate away and back
- Verify layout persisted

**Task 19: GUI Report Builder**
- Navigate to: Reports → GUI Report Builder
- Select table from dropdown
- Select columns
- Add filter conditions
- View SQL preview
- Click "Execute"
- Verify results display
- Test export

**Task 20: Drill-Down Functionality**
- Navigate to dashboard with chart
- Click on data point in chart
- Verify drill-down report opens
- Verify filtered data shown
- Verify applied filters displayed
- Test browser back button
- Test export from drill-down view
