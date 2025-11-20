# ManiReports - Critical Remaining Tests

**Status**: 12 out of 19 tests completed (63%)
**Remaining**: 7 critical tests

---

## ✅ COMPLETED (12/19)

1. ✅ Verify Upgrade Success
2. ✅ Check All Tables Exist
3. ✅ Access Plugin Settings
4. ✅ Access Admin Dashboard
5. ✅ Run Prebuilt Report (Course Completion)
6. ✅ Test Export (CSV, XLSX, PDF)
7. ✅ Course Completion Report (with filters)
8. ✅ Course Progress Report
9. ✅ SCORM Summary Report
10. ✅ User Engagement Report
11. ✅ Quiz Attempts Report
12. ⏳ Check Error Logs (PENDING - do this last)

---

## 🔴 CRITICAL REMAINING TESTS (7 tests)

### TEST 1: Custom SQL Reports (10 minutes)
**Why Critical**: Verifies custom report builder works and is secure

#### Step 1.1: Create Custom SQL Report
1. Navigate to: **Site administration → Reports → ManiReports → Custom Reports**
2. Click **"Create New Report"** button
3. Fill in the form:
   - **Name**: `Test SQL Report - Active Users`
   - **Description**: `List of active users with enrollment count`
   - **Report Type**: Select **"SQL"**
   - **SQL Query**: Copy and paste this:
   ```sql
   SELECT u.id, 
          u.firstname, 
          u.lastname, 
          u.email, 
          u.timecreated,
          COUNT(DISTINCT ue.id) as enrollments
   FROM {user} u
   LEFT JOIN {user_enrolments} ue ON ue.userid = u.id
   WHERE u.deleted = 0
   GROUP BY u.id, u.firstname, u.lastname, u.email, u.timecreated
   ORDER BY enrollments DESC
   LIMIT 20
   ```
4. Click **"Save Report"**

**Expected Result**: Report saved successfully, appears in custom reports list

**If Error**: Report the error message

---

#### Step 1.2: Execute Custom SQL Report
1. From the Custom Reports list, find **"Test SQL Report - Active Users"**
2. Click **"View"** button
3. Check if data displays correctly
4. Click **"Export CSV"**
5. Open the downloaded CSV file

**Expected Result**: 
- Report shows 20 users with enrollment counts
- CSV downloads and opens correctly

**If Error**: Report the error message

---

#### Step 1.3: Test SQL Security (IMPORTANT!)
1. Click **"Create New Report"** again
2. Try to create a report with malicious SQL:
   - **Name**: `Malicious Test`
   - **SQL Query**: 
   ```sql
   SELECT * FROM {user}; DROP TABLE mdl_user; --
   ```
3. Click **"Save Report"**

**Expected Result**: Error message like "Invalid SQL query" or "Blocked keywords detected"

**If Error**: If the SQL is accepted, this is a CRITICAL SECURITY ISSUE - report immediately!

---

### TEST 2: Report Scheduling (10 minutes)
**Why Critical**: Verifies automated report delivery works

#### Step 2.1: Create Schedule for Prebuilt Report
1. Navigate to: **Site administration → Reports → ManiReports → Scheduled Reports**
2. Click **"Create Schedule"** button
3. Fill in the form:
   - **Schedule Name**: `Daily Course Completion`
   - **Report Category**: Select **"Prebuilt Reports"**
   - **Report Type**: Select **"Course Completion"**
   - **Export Format**: Select **"CSV"**
   - **Frequency**: Select **"Daily"**
   - **Time**: Enter **"08:00"**
   - **Recipients**: Enter your email address
   - **Enabled**: Check the box
4. Click **"Save Schedule"**

**Expected Result**: Schedule appears in the schedules list

**If Error**: Report the error message

---

#### Step 2.2: Test Manual Execution
1. SSH into your server
2. Run this command:
```bash
sudo -u www-data php /var/www/html/admin/cli/scheduled_task.php --execute=\\local_manireports\\task\\report_scheduler
```
3. Wait for command to complete (should take 10-30 seconds)
4. Check your email inbox

**Expected Result**: 
- Command completes without errors
- You receive an email with CSV attachment
- Schedule list shows updated "Last Run" time

**Command Output to Check**:
```
Execute scheduled task: Report scheduler (local_manireports\task\report_scheduler)
... started [timestamp]
... used [X] dbqueries
... used [X] seconds
... used [X]MB memory
... completed [timestamp]
Scheduled task complete: Report scheduler
```

**If Error**: Copy and paste the error message

---

#### Step 2.3: Create Schedule for Custom Report
1. Click **"Create Schedule"** again
2. Fill in the form:
   - **Schedule Name**: `Weekly User Report`
   - **Report Category**: Select **"Custom Reports"**
   - **Custom Report**: Select **"Test SQL Report - Active Users"**
   - **Export Format**: Select **"XLSX"**
   - **Frequency**: Select **"Weekly"**
   - **Day of Week**: Select **"Monday"**
   - **Time**: Enter **"09:00"**
   - **Recipients**: Enter your email
   - **Enabled**: Check the box
3. Click **"Save Schedule"**

**Expected Result**: Schedule saved successfully

**If Error**: Report the error message

---

### TEST 3: Audit Logging (5 minutes)
**Why Critical**: Verifies compliance and tracking works

#### Step 3.1: Access Audit Log
1. Navigate to: **Site administration → Reports → ManiReports → Audit Logs**
2. Check if page loads
3. Look for recent entries (report creations, schedule creations from previous tests)

**Expected Result**: 
- Audit log page loads
- Shows entries for:
  - Custom report creation
  - Schedule creation
  - Report executions

**If Error**: Report what you see or error message

---

#### Step 3.2: Test Audit Log Filters
1. Use the **Date Range** filter to show only today's entries
2. Use the **Action Type** filter to show only "Report Created" actions
3. Click **"Export CSV"**

**Expected Result**: 
- Filters work correctly
- CSV exports successfully

**If Error**: Report the error message

---

### TEST 4: Scheduled Tasks (10 minutes)
**Why Critical**: Verifies background jobs work

#### Step 4.1: List All Tasks
Run this command:
```bash
sudo -u www-data php /var/www/html/admin/cli/scheduled_task.php --list | grep manireports
```

**Expected Result**: Should show 5 tasks:
```
local_manireports\task\time_aggregation
local_manireports\task\cache_builder
local_manireports\task\report_scheduler
local_manireports\task\scorm_summary
local_manireports\task\cleanup_old_data
```

**If Error**: Copy and paste what you see

---

#### Step 4.2: Execute Each Task
Run these commands one by one:

**Cache Builder**:
```bash
sudo -u www-data php /var/www/html/admin/cli/scheduled_task.php --execute=\\local_manireports\\task\\cache_builder
```

**SCORM Summary**:
```bash
sudo -u www-data php /var/www/html/admin/cli/scheduled_task.php --execute=\\local_manireports\\task\\scorm_summary
```

**Time Aggregation**:
```bash
sudo -u www-data php /var/www/html/admin/cli/scheduled_task.php --execute=\\local_manireports\\task\\time_aggregation
```

**Cleanup Old Data**:
```bash
sudo -u www-data php /var/www/html/admin/cli/scheduled_task.php --execute=\\local_manireports\\task\\cleanup_old_data
```

**Expected Result**: Each task completes with "Scheduled task complete" message

**If Error**: Copy and paste the error for the failed task

---

### TEST 5: Security - Capability Enforcement (5 minutes)
**Why Critical**: Prevents unauthorized access

#### Step 5.1: Test Student Access
1. Create or login as a **student** user (non-admin)
2. Try to access these URLs:
   - `https://your-site.com/local/manireports/ui/dashboard.php`
   - `https://your-site.com/local/manireports/ui/custom_reports.php`
   - `https://your-site.com/local/manireports/ui/schedules.php`

**Expected Result**: All three should show "Access denied" or redirect to login

**If Error**: If student can access any of these, this is a CRITICAL SECURITY ISSUE!

---

### TEST 6: Security - SQL Injection (Already done in Test 1.3)
**Status**: ✅ Will be tested in Test 1.3 above

---

### TEST 7: Final Error Log Check (5 minutes)
**Why Critical**: Ensures no hidden errors

#### Step 7.1: Check Moodle Error Log
```bash
tail -100 /opt/moodledata/moodledata.log | grep -i error
```

**Expected Result**: No new errors related to manireports

**If Errors Found**: Copy and paste them

---

#### Step 7.2: Check Apache Error Log
```bash
sudo tail -100 /var/log/apache2/error.log | grep manireports
```

**Expected Result**: No PHP errors or warnings

**If Errors Found**: Copy and paste them

---

## 📊 TESTING SUMMARY

### Tests Completed: 12/19 (63%)
- ✅ All 5 prebuilt reports working
- ✅ Filters working (company, date, course, user search)
- ✅ Exports working (CSV, XLSX, PDF)
- ✅ Navigation working (Back to Dashboard, Clear Filters)

### Critical Tests Remaining: 7
1. ⏳ Custom SQL Reports (3 sub-tests)
2. ⏳ Report Scheduling (3 sub-tests)
3. ⏳ Audit Logging (2 sub-tests)
4. ⏳ Scheduled Tasks (2 sub-tests)
5. ⏳ Security - Capability Enforcement
6. ⏳ Security - SQL Injection (part of Test 1)
7. ⏳ Final Error Log Check (2 sub-tests)

**Estimated Time**: 45 minutes total

---

## 🎯 QUICK CHECKLIST

After completing all tests, verify:

- [ ] All 5 prebuilt reports load and display data
- [ ] All filters work correctly
- [ ] All export formats work (CSV, XLSX, PDF)
- [ ] Custom SQL reports can be created and executed
- [ ] SQL injection is blocked (security test passed)
- [ ] Report scheduling works and emails are delivered
- [ ] All 5 scheduled tasks run without errors
- [ ] Student users cannot access admin features
- [ ] Audit log tracks all actions
- [ ] No errors in error logs

---

## 🚀 READY FOR PRODUCTION?

**YES** if:
- ✅ All critical tests pass
- ✅ No security issues found
- ✅ No errors in logs
- ✅ Performance is acceptable

**NO** if:
- ❌ Any security test fails
- ❌ Scheduled tasks fail
- ❌ Critical errors in logs

---

## 📝 TESTING INSTRUCTIONS

1. **Do tests in order** (1 through 7)
2. **Report results** after each test:
   - "Test 1 - PASS" or "Test 1 - FAIL: [error message]"
3. **Take screenshots** if you encounter errors
4. **Copy error messages** exactly as they appear

**Let's start with Test 1: Custom SQL Reports**

