# ManiReports - Comprehensive Testing Scenarios (Tasks 1-10)

## Task 1: Plugin Foundation & Structure

### Test Scenario 1.1: Plugin Installation
**Steps:**
1. SSH to EC2 server
2. Navigate to `/var/www/html/moodle/local/`
3. Deploy manireports folder
4. Run: `sudo -u www-data php admin/cli/upgrade.php --non-interactive`
5. Login to Moodle as admin
6. Navigate to: Site administration → Plugins → Local plugins
7. Verify ManiReports appears in list with version 2024111704

**Expected Result:** Plugin installed successfully, version shows correctly

---

## Task 2: Database Schema & Installation

### Test Scenario 2.1: Database Tables Creation
**Steps:**
1. After plugin installation, connect to database:
   ```bash
   mysql -u moodle_user -p moodle_db
   ```
2. Run query:
   ```sql
   SELECT table_name FROM information_schema.tables 
   WHERE table_schema = 'moodle_db' 
   AND table_name LIKE 'mdl_manireports_%' 
   ORDER BY table_name;
   ```
3. Verify all 13 tables exist:
   - mdl_manireports_audit_logs
   - mdl_manireports_atrisk_ack
   - mdl_manireports_cache_summary
   - mdl_manireports_customreports
   - mdl_manireports_dash_widgets
   - mdl_manireports_dashboards
   - mdl_manireports_failed_jobs
   - mdl_manireports_report_runs
   - mdl_manireports_sched_recip
   - mdl_manireports_schedules
   - mdl_manireports_scorm_summary
   - mdl_manireports_time_daily
   - mdl_manireports_time_sessions

**Expected Result:** All 13 tables created with correct structure

### Test Scenario 2.2: Capabilities Definition
**Steps:**
1. Login as admin
2. Navigate to: Site administration → Users → Permissions → Define roles
3. Search for capabilities containing "manireports"
4. Verify these capabilities exist:
   - local/manireports:viewadmindashboard
   - local/manireports:viewmanagerdashboard
   - local/manireports:viewteacherdashboard
   - local/manireports:viewstudentdashboard
   - local/manireports:managereports
   - local/manireports:schedule
   - local/manireports:customreports

**Expected Result:** All capabilities defined and assignable to roles

### Test Scenario 2.3: Scheduled Tasks Registration
**Steps:**
1. SSH to server
2. Run: `sudo -u www-data php admin/cli/scheduled_task.php --list | grep manireports`
3. Verify these tasks appear:
   - \local_manireports\task\cache_builder
   - \local_manireports\task\cleanup_old_data
   - \local_manireports\task\report_scheduler
   - \local_manireports\task\scorm_summary
   - \local_manireports\task\time_aggregation

**Expected Result:** All 5 scheduled tasks registered

---

## Task 3: IOMAD Filter & Multi-Tenancy

### Test Scenario 3.1: IOMAD Detection
**Steps:**
1. SSH to server: `ssh user@your-ec2-instance.com`
2. Create test script:
   ```bash
   cat > /var/www/html/moodle/test_iomad.php << 'EOF'
   <?php
   require_once('/var/www/html/moodle/config.php');
   require_login();
   
   $filter = new \local_manireports\api\iomad_filter();
   $installed = $filter->is_iomad_installed();
   
   echo "IOMAD Installed: " . ($installed ? "YES ✓" : "NO ✗") . "\n";
   ?>
   EOF
   ```
3. Run: `sudo -u www-data php /var/www/html/moodle/test_iomad.php`

**Expected Result:** 
- If IOMAD installed: "IOMAD Installed: YES ✓"
- If not installed: "IOMAD Installed: NO ✗"

---

### Test Scenario 3.2: Company Filtering (IOMAD Only) - DETAILED

**Prerequisites:** IOMAD must be installed

#### Step 1: Create Test Companies
1. Login to Moodle as admin
2. Navigate to: **IOMAD → Companies**
3. Click **"Add Company"**
4. Fill form:
   - Company name: **"Test Company A"**
   - Company shortname: **"tca"**
   - Click **"Save"**
5. Repeat to create **"Test Company B"** (shortname: **"tcb"**)

**Verify:** Both companies appear in company list

#### Step 2: Create Test Users and Assign to Companies
1. Navigate to: **IOMAD → Users**
2. Click **"Add User"**
3. Create User 1:
   - Username: **"manager_a"**
   - Email: **"manager_a@test.com"**
   - First name: **"Manager"**
   - Last name: **"A"**
   - Password: **"Test123!@#"**
   - Click **"Save"**
4. Edit user and assign to Company A as Company Manager
5. Repeat for User 2 (**"manager_b"**) assigned to Company B

**Verify:** Both users assigned to different companies

#### Step 3: Create Test Courses in Each Company
1. Navigate to: **IOMAD → Courses**
2. Create Course 1:
   - Course name: **"Company A Course"**
   - Course code: **"CAC"**
   - Company: **"Test Company A"**
   - Click **"Save"**
3. Create Course 2:
   - Course name: **"Company B Course"**
   - Course code: **"CBC"**
   - Company: **"Test Company B"**
   - Click **"Save"**

**Verify:** Courses created and assigned to companies

#### Step 4: Enroll Users in Courses
1. Navigate to: **Courses → Company A Course**
2. Click **"Participants"** → **"Enroll users"**
3. Select both **"manager_a"** and **"manager_b"**
4. Click **"Enroll"**
5. Repeat for **"Company B Course"**

**Verify:** Both managers enrolled in both courses

#### Step 5: Test Company Filtering - Manager A View
1. Logout
2. Login as **"manager_a"** (password: **"Test123!@#"**)
3. Navigate to: **Dashboard → Reports → Course Completion**
4. Check the report data

**Expected Results:**
- ✅ Should see **"Company A Course"** in report
- ❌ Should NOT see **"Company B Course"** in report

**If you see Company B data:** Company filtering is NOT working - FAIL

#### Step 6: Test Company Filtering - Manager B View
1. Logout
2. Login as **"manager_b"** (password: **"Test123!@#"**)
3. Navigate to: **Dashboard → Reports → Course Completion**

**Expected Results:**
- ✅ Should see **"Company B Course"** in report
- ❌ Should NOT see **"Company A Course"** in report

**If you see Company A data:** Company filtering is NOT working - FAIL

#### Step 7: Verify Database Query Filtering
1. SSH to server
2. Create test script:
   ```bash
   cat > /var/www/html/moodle/test_company_filter.php << 'EOF'
   <?php
   require_once('/var/www/html/moodle/config.php');
   require_login();
   
   $filter = new \local_manireports\api\iomad_filter();
   $companies = $filter->get_user_companies($USER->id);
   
   echo "User ID: " . $USER->id . "\n";
   echo "Companies: " . json_encode($companies) . "\n";
   
   $sql = "SELECT id, fullname FROM {course}";
   $filtered_sql = $filter->apply_company_filter($sql, $USER->id);
   
   echo "Original SQL: " . $sql . "\n";
   echo "Filtered SQL: " . $filtered_sql . "\n";
   ?>
   EOF
   ```
3. Run: `sudo -u www-data php /var/www/html/moodle/test_company_filter.php`

**Expected Output:**
- Shows user's company IDs
- Filtered SQL includes WHERE clause with company filter
- Example: `WHERE company IN (1,2)`

**Result:** Company filtering working correctly ✓

---

## Task 4: Core Report Builder API - DETAILED

### Test Scenario 4.1: SQL Validation - DETAILED

#### Step 1: Create Test Script
SSH to server and create file: `test_sql_validation.php`

```bash
cat > /var/www/html/moodle/test_sql_validation.php << 'EOF'
<?php
require_once('/var/www/html/moodle/config.php');
require_login();

$builder = new \local_manireports\api\report_builder();

// Test 1: Valid SELECT query
echo "=== TEST 1: Valid SELECT ===\n";
$sql = "SELECT id, fullname FROM {course} WHERE category = ?";
$result = $builder->validate_sql($sql);
echo "SQL: " . $sql . "\n";
echo "Result: " . ($result ? "VALID ✓" : "INVALID ✗") . "\n\n";

// Test 2: Invalid DROP query
echo "=== TEST 2: Invalid DROP ===\n";
$sql = "DROP TABLE {course}";
$result = $builder->validate_sql($sql);
echo "SQL: " . $sql . "\n";
echo "Result: " . ($result ? "VALID ✗ (SHOULD BE INVALID)" : "INVALID ✓") . "\n\n";

// Test 3: Invalid INSERT query
echo "=== TEST 3: Invalid INSERT ===\n";
$sql = "INSERT INTO {course} (fullname) VALUES ('Test')";
$result = $builder->validate_sql($sql);
echo "SQL: " . $sql . "\n";
echo "Result: " . ($result ? "VALID ✗ (SHOULD BE INVALID)" : "INVALID ✓") . "\n\n";

// Test 4: Invalid UPDATE query
echo "=== TEST 4: Invalid UPDATE ===\n";
$sql = "UPDATE {course} SET fullname = 'Test' WHERE id = 1";
$result = $builder->validate_sql($sql);
echo "SQL: " . $sql . "\n";
echo "Result: " . ($result ? "VALID ✗ (SHOULD BE INVALID)" : "INVALID ✓") . "\n\n";

// Test 5: Invalid DELETE query
echo "=== TEST 5: Invalid DELETE ===\n";
$sql = "DELETE FROM {course} WHERE id = 1";
$result = $builder->validate_sql($sql);
echo "SQL: " . $sql . "\n";
echo "Result: " . ($result ? "VALID ✗ (SHOULD BE INVALID)" : "INVALID ✓") . "\n\n";

// Test 6: Valid JOIN query
echo "=== TEST 6: Valid JOIN ===\n";
$sql = "SELECT c.id, c.fullname, u.firstname FROM {course} c 
        JOIN {course_completions} cc ON c.id = cc.course 
        JOIN {user} u ON cc.userid = u.id";
$result = $builder->validate_sql($sql);
echo "SQL: " . $sql . "\n";
echo "Result: " . ($result ? "VALID ✓" : "INVALID ✗") . "\n\n";
?>
EOF
```

#### Step 2: Run Test Script
```bash
cd /var/www/html/moodle
sudo -u www-data php test_sql_validation.php
```

#### Step 3: Verify Results
**Expected Output:**
```
=== TEST 1: Valid SELECT ===
SQL: SELECT id, fullname FROM {course} WHERE category = ?
Result: VALID ✓

=== TEST 2: Invalid DROP ===
SQL: DROP TABLE {course}
Result: INVALID ✓

=== TEST 3: Invalid INSERT ===
SQL: INSERT INTO {course} (fullname) VALUES ('Test')
Result: INVALID ✓

=== TEST 4: Invalid UPDATE ===
SQL: UPDATE {course} SET fullname = 'Test' WHERE id = 1
Result: INVALID ✓

=== TEST 5: Invalid DELETE ===
SQL: DELETE FROM {course} WHERE id = 1
Result: INVALID ✓

=== TEST 6: Valid JOIN ===
SQL: SELECT c.id, c.fullname, u.firstname FROM {course} c...
Result: VALID ✓
```

**If any test fails:** SQL validation not working correctly - FAIL

---

### Test Scenario 4.2: Report Execution - DETAILED

#### Step 1: Create Test Script
```bash
cat > /var/www/html/moodle/test_report_execution.php << 'EOF'
<?php
require_once('/var/www/html/moodle/config.php');
require_login();

$builder = new \local_manireports\api\report_builder();

// Test 1: Simple SELECT without parameters
echo "=== TEST 1: Simple SELECT ===\n";
$sql = "SELECT id, fullname FROM {course} LIMIT 5";
$params = [];
try {
    $result = $builder->execute_report($sql, $params);
    echo "Rows returned: " . count($result) . "\n";
    if (count($result) > 0) {
        echo "First row: " . json_encode($result[0]) . "\n";
        echo "Result: SUCCESS ✓\n\n";
    } else {
        echo "Result: NO DATA (might be OK if no courses)\n\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Result: FAILED ✗\n\n";
}

// Test 2: SELECT with parameter binding
echo "=== TEST 2: SELECT with Parameters ===\n";
$sql = "SELECT id, fullname FROM {course} WHERE category = ?";
$params = [1];
try {
    $result = $builder->execute_report($sql, $params);
    echo "Rows returned: " . count($result) . "\n";
    echo "Result: SUCCESS ✓\n\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Result: FAILED ✗\n\n";
}

// Test 3: SELECT with multiple parameters
echo "=== TEST 3: Multiple Parameters ===\n";
$sql = "SELECT id, fullname FROM {course} WHERE category = ? AND visible = ?";
$params = [1, 1];
try {
    $result = $builder->execute_report($sql, $params);
    echo "Rows returned: " . count($result) . "\n";
    echo "Result: SUCCESS ✓\n\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Result: FAILED ✗\n\n";
}

// Test 4: SELECT with JOIN
echo "=== TEST 4: SELECT with JOIN ===\n";
$sql = "SELECT c.id, c.fullname, COUNT(cc.id) as completions 
        FROM {course} c 
        LEFT JOIN {course_completions} cc ON c.id = cc.course 
        GROUP BY c.id, c.fullname 
        LIMIT 5";
$params = [];
try {
    $result = $builder->execute_report($sql, $params);
    echo "Rows returned: " . count($result) . "\n";
    if (count($result) > 0) {
        echo "First row: " . json_encode($result[0]) . "\n";
    }
    echo "Result: SUCCESS ✓\n\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Result: FAILED ✗\n\n";
}

// Test 5: Parameter count mismatch (should fail)
echo "=== TEST 5: Parameter Mismatch (Should Fail) ===\n";
$sql = "SELECT id, fullname FROM {course} WHERE category = ? AND visible = ?";
$params = [1]; // Only 1 param, but query needs 2
try {
    $result = $builder->execute_report($sql, $params);
    echo "Result: FAILED ✗ (Should have thrown error)\n\n";
} catch (Exception $e) {
    echo "Error caught: " . $e->getMessage() . "\n";
    echo "Result: SUCCESS ✓ (Error caught correctly)\n\n";
}
?>
EOF
```

#### Step 2: Run Test Script
```bash
cd /var/www/html/moodle
sudo -u www-data php test_report_execution.php
```

#### Step 3: Verify Results
**Expected Output:**
- Test 1: SUCCESS ✓ (returns courses)
- Test 2: SUCCESS ✓ (returns filtered courses)
- Test 3: SUCCESS ✓ (returns filtered courses)
- Test 4: SUCCESS ✓ (returns joined data)
- Test 5: SUCCESS ✓ (error caught)

**If any test fails:** Report execution not working - FAIL

---

## Task 5: Prebuilt Core Reports - DETAILED

### Test Scenario 5.1: Course Completion Report - DETAILED

#### Step 1: Navigate to Report
1. Login as admin
2. Navigate to: **Dashboard → Reports → Course Completion**

**Expected:** Page loads without errors

#### Step 2: Verify Report Data
1. Check table displays columns:
   - ✓ Course name
   - ✓ Enrollment count
   - ✓ Completion percentage
   - ✓ Last updated date

2. Verify data is accurate:
   ```bash
   mysql -u moodle_user -p moodle_db -e "
   SELECT COUNT(*) as course_count FROM mdl_course WHERE id > 1;
   SELECT COUNT(*) as completion_count FROM mdl_course_completions;
   "
   ```
   - Compare with report row count

**Expected:** Data matches database

#### Step 3: Test Date Filter
1. Click **"Date Range"** filter
2. Select **"Last 30 days"**
3. Click **"Apply"**

**Expected:**
- Report updates
- Only courses with recent completions shown
- Data changes from previous view

#### Step 4: Test Company Filter (IOMAD)
1. If IOMAD installed, click **"Company"** filter
2. Select **"Test Company A"**
3. Click **"Apply"**

**Expected:**
- Report updates
- Only Company A courses shown
- Data filtered correctly

#### Step 5: Measure Load Time
1. Open browser DevTools → Network tab
2. Refresh page
3. Check load time

**Expected:** Load time < 2 seconds

#### Step 6: Test CSV Export
1. Click **"Export as CSV"**
2. Verify file downloads
3. Open file in text editor
4. Verify:
   - Headers in first row
   - Data properly formatted
   - UTF-8 encoding

**Expected:** CSV file valid and readable

#### Step 7: Test Chart Rendering
1. Scroll down to see chart
2. Verify chart displays:
   - Title visible
   - Trend line visible
   - X-axis labels (dates)
   - Y-axis labels (percentages)
3. Hover over data points
4. Verify tooltips show values

**Expected:** Chart renders correctly with interactive tooltips

---

### Test Scenario 5.2: Course Progress Report - DETAILED

#### Step 1: Navigate to Report
1. Navigate to: **Dashboard → Reports → Course Progress**

**Expected:** Page loads without errors

#### Step 2: Select Course
1. Click **"Course"** dropdown
2. Select a course with enrolled users
3. Click **"Apply"**

**Expected:**
- Report updates
- Shows users enrolled in selected course
- Displays progress for each user

#### Step 3: Verify Report Data
1. Check table displays:
   - User name
   - Progress percentage
   - Last activity date
   - Status (in progress/completed)

2. Verify data accuracy:
   ```bash
   mysql -u moodle_user -p moodle_db -e "
   SELECT u.firstname, u.lastname, cc.timecompleted 
   FROM mdl_course_completions cc 
   JOIN mdl_user u ON cc.userid = u.id 
   WHERE cc.course = [course_id];
   "
   ```

**Expected:** Report data matches database

#### Step 4: Test User Filter
1. Click **"User"** filter
2. Type user name
3. Click **"Apply"**

**Expected:**
- Report filters to show only that user
- Data updates correctly

#### Step 5: Test Pagination
1. If > 50 users, verify pagination controls appear
2. Click "Next Page"
3. Verify different users shown

**Expected:** Pagination works correctly

---

### Test Scenario 5.3: SCORM Summary Report - DETAILED

#### Step 1: Verify SCORM Data Exists
```bash
mysql -u moodle_user -p moodle_db -e "
SELECT COUNT(*) as scorm_count FROM mdl_scorm;
SELECT COUNT(*) as tracking_count FROM mdl_scorm_scoes_track;
"
```

**Expected:** Both counts > 0

If counts are 0, create test SCORM activity first.

#### Step 2: Navigate to Report
1. Navigate to: **Dashboard → Reports → SCORM Summary**

**Expected:** Page loads without errors

#### Step 3: Verify Report Data
1. Check table displays:
   - SCORM name
   - User name
   - Attempt count
   - Completion status (Yes/No)
   - Average score
   - Last access date

2. Verify data accuracy:
   ```bash
   mysql -u moodle_user -p moodle_db -e "
   SELECT * FROM mdl_manireports_scorm_summary LIMIT 5;
   "
   ```

**Expected:** Report data matches summary table

#### Step 4: Test SCORM Filter
1. Click **"SCORM Activity"** filter
2. Select a SCORM activity
3. Click **"Apply"**

**Expected:**
- Report filters to show only that SCORM
- Data updates correctly

---

### Test Scenario 5.4: User Engagement Report - DETAILED

#### Step 1: Generate Time Tracking Data
1. Login as student
2. Navigate to course
3. Stay on page for 2-3 minutes
4. Verify heartbeat requests sent (check Network tab)

#### Step 2: Run Time Aggregation Task
```bash
sudo -u www-data php admin/cli/scheduled_task.php \
  --execute=\\local_manireports\\task\\time_aggregation
```

**Expected:** Task completes without errors

#### Step 3: Navigate to Report
1. Navigate to: **Dashboard → Reports → User Engagement**

**Expected:** Page loads without errors

#### Step 4: Verify Report Data
1. Check table displays:
   - User name
   - Active days (7-day)
   - Active days (30-day)
   - Total time spent (in hours/minutes)

2. Verify data accuracy:
   ```bash
   mysql -u moodle_user -p moodle_db -e "
   SELECT userid, date, duration FROM mdl_manireports_time_daily 
   ORDER BY date DESC LIMIT 10;
   "
   ```

**Expected:** Report data matches time_daily table

#### Step 5: Test Date Range Filter
1. Click **"Date Range"** filter
2. Select **"Last 7 days"**
3. Click **"Apply"**

**Expected:**
- Report updates
- Shows only 7-day active data
- Time calculations updated

---

### Test Scenario 5.5: Quiz Attempts Report - DETAILED

#### Step 1: Verify Quiz Data Exists
```bash
mysql -u moodle_user -p moodle_db -e "
SELECT COUNT(*) as quiz_count FROM mdl_quiz;
SELECT COUNT(*) as attempt_count FROM mdl_quiz_attempts;
"
```

**Expected:** Both counts > 0

If counts are 0, create test quiz first.

#### Step 2: Navigate to Report
1. Navigate to: **Dashboard → Reports → Quiz Attempts**

**Expected:** Page loads without errors

#### Step 3: Verify Report Data
1. Check table displays:
   - Quiz name
   - User name
   - Attempt count
   - Average score
   - Highest score
   - Last attempt date

2. Verify data accuracy:
   ```bash
   mysql -u moodle_user -p moodle_db -e "
   SELECT q.name, u.firstname, u.lastname, COUNT(qa.id) as attempts, AVG(qa.sumgrades) as avg_score
   FROM mdl_quiz_attempts qa
   JOIN mdl_quiz q ON qa.quiz = q.id
   JOIN mdl_user u ON qa.userid = u.id
   GROUP BY q.id, u.id
   LIMIT 10;
   "
   ```

**Expected:** Report data matches database

#### Step 4: Test Course Filter
1. Click **"Course"** filter
2. Select a course with quizzes
3. Click **"Apply"**

**Expected:**
- Report filters to show only that course's quizzes
- Data updates correctly

---

## Task 6: Time Tracking Engine - DETAILED

### Test Scenario 6.1: Heartbeat Recording - DETAILED

#### Step 1: Login and Navigate to Course
1. Login as student
2. Navigate to a course page
3. Open browser DevTools: **F12 → Network tab**

#### Step 2: Monitor Heartbeat Requests
1. Filter Network tab for: **"heartbeat"**
2. Stay on page for 1-2 minutes
3. Verify AJAX requests appear every 25-30 seconds
4. Click on one request to view details

**Expected:**
- Request URL: `/local/manireports/ui/ajax/heartbeat.php`
- Request method: POST
- Request body includes: userid, courseid, timestamp, sesskey
- Response: JSON with success status

#### Step 3: Verify Request Frequency
1. Note timestamps of requests
2. Calculate interval between requests
3. Verify interval is 25-30 seconds (randomized)

**Expected:** Heartbeat requests sent regularly at correct interval

---

### Test Scenario 6.2: Session Recording - DETAILED

#### Step 1: Check Database After Heartbeat
```bash
# Get your user ID first
mysql -u moodle_user -p moodle_db -e "
SELECT id FROM mdl_user WHERE username = 'admin' LIMIT 1;
"
# Then check sessions (replace [your_id] with actual ID)
mysql -u moodle_user -p moodle_db -e "
SELECT * FROM mdl_manireports_time_sessions 
WHERE userid = [your_id] 
ORDER BY sessionstart DESC LIMIT 1;
"
```

**Expected Record Should Have:**
- userid: [your_id]
- courseid: [course_id]
- sessionstart: (timestamp when you logged in)
- lastupdated: (recent timestamp, updated by heartbeat)
- duration: (NULL until aggregated)

#### Step 2: Verify Session Updates
1. Wait 2 minutes
2. Run same query again
3. Verify lastupdated timestamp changed

**Expected:** lastupdated timestamp updated by heartbeat

---

### Test Scenario 6.3: Time Aggregation Task - DETAILED

#### Step 1: Run Time Aggregation Task
```bash
sudo -u www-data php admin/cli/scheduled_task.php \
  --execute=\\local_manireports\\task\\time_aggregation
```

**Expected Output:**
```
Task started: local_manireports\task\time_aggregation
Task completed successfully
```

#### Step 2: Check Daily Summaries
```bash
mysql -u moodle_user -p moodle_db -e "
SELECT * FROM mdl_manireports_time_daily 
WHERE userid = [your_id] 
ORDER BY date DESC LIMIT 1;
"
```

**Expected Record Should Have:**
- userid: [your_id]
- courseid: [course_id]
- date: (today's date in Y-m-d format)
- duration: (total seconds, should be > 0)
- sessioncount: (number of sessions, should be > 0)
- lastupdated: (recent timestamp)

#### Step 3: Verify Calculations
1. Calculate expected duration: (number of sessions × ~25-30 seconds)
2. Compare with duration in database
3. Verify duration is reasonable

**Expected:** Daily summaries created with correct calculations

---

## Task 7: SCORM Analytics Aggregation - DETAILED

### Test Scenario 7.1: SCORM Summary Task - DETAILED

#### Step 1: Verify SCORM Data Exists
```bash
mysql -u moodle_user -p moodle_db -e "
SELECT COUNT(*) as scorm_count FROM mdl_scorm;
SELECT COUNT(*) as tracking_count FROM mdl_scorm_scoes_track;
"
```

**Expected:** Both counts > 0

#### Step 2: Run SCORM Summary Task
```bash
sudo -u www-data php admin/cli/scheduled_task.php \
  --execute=\\local_manireports\\task\\scorm_summary
```

**Expected Output:**
```
Task started: local_manireports\task\scorm_summary
Task completed successfully
```

#### Step 3: Check Summary Table
```bash
mysql -u moodle_user -p moodle_db -e "
SELECT * FROM mdl_manireports_scorm_summary LIMIT 5;
"
```

**Expected Records Should Have:**
- scormid: (SCORM activity ID)
- userid: (user ID)
- attempts: (number of attempts, > 0)
- completed: (0 or 1)
- totaltime: (time in seconds)
- score: (average score, or NULL)
- lastaccess: (timestamp)
- lastupdated: (recent timestamp)

#### Step 4: Verify Data Accuracy
```bash
# Compare with raw tracking data
mysql -u moodle_user -p moodle_db -e "
SELECT scormid, userid, COUNT(*) as attempts 
FROM mdl_scorm_scoes_track 
GROUP BY scormid, userid 
LIMIT 5;
"
```

**Expected:** Summary attempt counts match raw tracking data

---

## Task 8: Caching & Pre-Aggregation - DETAILED

### Test Scenario 8.1: Cache Builder Task - DETAILED

#### Step 1: Run Cache Builder Task
```bash
sudo -u www-data php admin/cli/scheduled_task.php \
  --execute=\\local_manireports\\task\\cache_builder
```

**Expected Output:**
```
Task started: local_manireports\task\cache_builder
Task completed successfully
```

#### Step 2: Check Cache Table
```bash
mysql -u moodle_user -p moodle_db -e "
SELECT * FROM mdl_manireports_cache_summary LIMIT 5;
"
```

**Expected Records Should Have:**
- cachekey: (unique cache key)
- reporttype: (report type name)
- referenceid: (reference ID, or NULL)
- datajson: (JSON data, should be valid JSON)
- lastgenerated: (recent timestamp)
- ttl: (time to live in seconds)

#### Step 3: Verify Cache Data
```bash
mysql -u moodle_user -p moodle_db -e "
SELECT cachekey, reporttype, LENGTH(datajson) as data_size, ttl 
FROM mdl_manireports_cache_summary 
LIMIT 5;
"
```

**Expected:**
- data_size > 0 (cache has data)
- ttl > 0 (cache has expiration time)
- Multiple cache entries for different reports

---

### Test Scenario 8.2: Cache Hit Performance - DETAILED

#### Step 1: Measure First Load (Cache Miss)
1. Navigate to dashboard
2. Open browser DevTools → Network tab
3. Note total load time (should be slower)
4. Record time: **[first_load_time]**

#### Step 2: Measure Second Load (Cache Hit)
1. Refresh page (F5)
2. Note total load time (should be faster)
3. Record time: **[second_load_time]**

#### Step 3: Calculate Performance Improvement
```
Improvement = (first_load_time - second_load_time) / first_load_time × 100%
```

**Expected:**
- Second load time < first load time
- Improvement > 50% (at least 50% faster)
- Example: First load 3s, Second load 0.5s = 83% improvement

---

## Task 9: Analytics Engine - DETAILED

### Test Scenario 9.1: Engagement Score Calculation - DETAILED

#### Step 1: Create Test Script
```bash
cat > /var/www/html/moodle/test_engagement.php << 'EOF'
<?php
require_once('/var/www/html/moodle/config.php');
require_login();

$engine = new \local_manireports\api\analytics_engine();

// Get a user ID (use admin for testing)
$userid = 2; // Usually admin is ID 2

// Get a course ID
$courseid = 2; // Usually first course is ID 2

// Calculate engagement score
$score = $engine->calculate_engagement_score($userid, $courseid);

echo "User ID: " . $userid . "\n";
echo "Course ID: " . $courseid . "\n";
echo "Engagement Score: " . $score . "\n";
echo "Score Range: 0-100\n";

if ($score >= 0 && $score <= 100) {
    echo "Result: VALID ✓\n";
} else {
    echo "Result: INVALID ✗ (Score out of range)\n";
}
?>
EOF
```

#### Step 2: Run Test Script
```bash
cd /var/www/html/moodle
sudo -u www-data php test_engagement.php
```

**Expected Output:**
```
User ID: 2
Course ID: 2
Engagement Score: 75
Score Range: 0-100
Result: VALID ✓
```

**Expected:** Score between 0-100

---

### Test Scenario 9.2: At-Risk Detection - DETAILED

#### Step 1: Create Test Script
```bash
cat > /var/www/html/moodle/test_atrisk.php << 'EOF'
<?php
require_once('/var/www/html/moodle/config.php');
require_login();

$engine = new \local_manireports\api\analytics_engine();

// Get a course ID
$courseid = 2;

// Detect at-risk learners
$atrisk = $engine->detect_at_risk_learners($courseid);

echo "Course ID: " . $courseid . "\n";
echo "At-Risk Learners Found: " . count($atrisk) . "\n";

if (count($atrisk) > 0) {
    echo "\nFirst At-Risk Learner:\n";
    echo json_encode($atrisk[0], JSON_PRETTY_PRINT) . "\n";
}

echo "\nResult: SUCCESS ✓\n";
?>
EOF
```

#### Step 2: Run Test Script
```bash
cd /var/www/html/moodle
sudo -u www-data php test_atrisk.php
```

**Expected Output:**
```
Course ID: 2
At-Risk Learners Found: 3

First At-Risk Learner:
{
  "userid": 5,
  "risk_score": 65,
  "factors": ["low_time", "no_login"]
}

Result: SUCCESS ✓
```

**Expected:** Returns array of at-risk learners with risk scores

---

## Task 10: Export Engine - DETAILED

### Test Scenario 10.1: CSV Export - DETAILED

#### Step 1: Navigate to Report and Export
1. Navigate to: **Dashboard → Reports → Course Completion**
2. Click **"Export as CSV"**
3. Verify file downloads (check Downloads folder)

#### Step 2: Verify CSV Format
1. Open file in text editor (Notepad, VS Code, etc.)
2. Verify:
   - First row contains headers
   - Data rows follow
   - Comma-separated values
   - Proper quoting for values with commas

**Expected CSV Format:**
```
"Course Name","Enrollment Count","Completion %","Last Updated"
"Course 1","25","80%","2025-01-15 10:30:00"
"Course 2","18","65%","2025-01-14 15:45:00"
```

#### Step 3: Verify Encoding
1. Check file encoding: Should be UTF-8 with BOM
2. Open in Excel/LibreOffice
3. Verify special characters display correctly

**Expected:** CSV file valid and properly formatted

---

### Test Scenario 10.2: XLSX Export - DETAILED

#### Step 1: Navigate to Report and Export
1. Navigate to: **Dashboard → Reports → Course Completion**
2. Click **"Export as XLSX"**
3. Verify file downloads

#### Step 2: Open in Excel/LibreOffice
1. Open downloaded file
2. Verify:
   - Headers formatted (bold, colored background)
   - Columns auto-sized
   - Numbers formatted correctly
   - Dates formatted as YYYY-MM-DD HH:MM:SS

**Expected:** XLSX file valid and properly formatted

---

### Test Scenario 10.3: PDF Export - DETAILED

#### Step 1: Navigate to Report and Export
1. Navigate to: **Dashboard → Reports → Course Completion**
2. Click **"Export as PDF"**
3. Verify file downloads

#### Step 2: Open in PDF Reader
1. Open downloaded file
2. Verify:
   - Report title visible
   - Timestamp included
   - Table rendered with data
   - Alternating row colors for readability
   - All data visible (no cutoff)

**Expected:** PDF file valid and properly formatted

---

## Task 7: SCORM Analytics Aggregation

### Test Scenario 7.1: SCORM Summary Task
**Steps:**
1. Ensure SCORM activities have tracking data
2. Run SCORM summary task:
   ```bash
   sudo -u www-data php admin/cli/scheduled_task.php \
     --execute=\\local_manireports\\task\\scorm_summary
   ```
3. Check database:
   ```sql
   SELECT * FROM mdl_manireports_scorm_summary LIMIT 5;
   ```
4. Verify records have:
   - scormid
   - userid
   - attempts
   - completed (0 or 1)
   - totaltime
   - score
   - lastaccess

**Expected Result:** SCORM data aggregated into summary table

---

## Task 8: Caching & Pre-Aggregation

### Test Scenario 8.1: Cache Builder Task
**Steps:**
1. Run cache builder task:
   ```bash
   sudo -u www-data php admin/cli/scheduled_task.php \
     --execute=\\local_manireports\\task\\cache_builder
   ```
2. Check database:
   ```sql
   SELECT * FROM mdl_manireports_cache_summary LIMIT 5;
   ```
3. Verify records have:
   - cachekey
   - reporttype
   - datajson (JSON data)
   - lastgenerated (recent timestamp)
   - ttl

**Expected Result:** Cache data generated and stored

### Test Scenario 8.2: Cache Hit Performance
**Steps:**
1. Navigate to dashboard
2. Open browser DevTools → Network tab
3. Note load time (first load - cache miss)
4. Refresh page
5. Note load time (second load - cache hit)
6. Verify second load is significantly faster

**Expected Result:** Cached data loads faster than fresh queries

---

## Task 9: Analytics Engine

### Test Scenario 9.1: Engagement Score Calculation
**Steps:**
1. Create test script to calculate engagement:
   ```php
   $engine = new \local_manireports\api\analytics_engine();
   $score = $engine->calculate_engagement_score($userid, $courseid);
   ```
2. Verify score is between 0-100
3. Test with different user activity levels
4. Verify high-activity users have higher scores

**Expected Result:** Engagement scores calculated correctly

### Test Scenario 9.2: At-Risk Detection
**Steps:**
1. Run analytics to detect at-risk learners:
   ```php
   $engine = new \local_manireports\api\analytics_engine();
   $atrisk = $engine->detect_at_risk_learners($courseid);
   ```
2. Verify returns array of at-risk users
3. Check database for at-risk flags
4. Verify users with low engagement are flagged

**Expected Result:** At-risk learners identified correctly

---

## Task 10: Export Engine

### Test Scenario 10.1: CSV Export
**Steps:**
1. Navigate to any report
2. Click "Export as CSV"
3. Verify file downloads
4. Open in text editor
5. Verify:
   - Headers in first row
   - Data properly comma-separated
   - Dates formatted as Y-m-d H:i:s
   - UTF-8 encoding with BOM

**Expected Result:** CSV file valid and properly formatted

### Test Scenario 10.2: XLSX Export
**Steps:**
1. Navigate to any report
2. Click "Export as XLSX"
3. Verify file downloads
4. Open in Excel/LibreOffice
5. Verify:
   - Headers formatted (bold, colored)
   - Columns auto-sized
   - Numbers formatted correctly
   - Dates formatted correctly

**Expected Result:** XLSX file valid and properly formatted

### Test Scenario 10.3: PDF Export
**Steps:**
1. Navigate to any report
2. Click "Export as PDF"
3. Verify file downloads
4. Open in PDF reader
5. Verify:
   - Report title visible
   - Timestamp included
   - Table rendered with alternating row colors
   - All data visible

**Expected Result:** PDF file valid and properly formatted
