# ManiReports - Comprehensive Testing Scenarios (Tasks 21-30)

## Task 21: API Endpoints for External Integration

### Test Scenario 21.1: API Authentication
**Steps:**
1. Create test script with API token
2. Call API endpoint:
   ```bash
   curl -X POST https://your-moodle.com/webservice/rest/server.php \
     -d "wstoken=YOUR_TOKEN" \
     -d "wsfunction=local_manireports_get_dashboard_data" \
     -d "moodlewsrestformat=json"
   ```
3. Verify authentication works
4. Test with invalid token
5. Verify authentication fails

**Expected Result:** API authentication works correctly

### Test Scenario 21.2: Dashboard Data API
**Steps:**
1. Call API to get dashboard data:
   ```bash
   curl -X POST https://your-moodle.com/webservice/rest/server.php \
     -d "wstoken=YOUR_TOKEN" \
     -d "wsfunction=local_manireports_get_dashboard_data" \
     -d "dashboardid=1" \
     -d "moodlewsrestformat=json"
   ```
2. Verify returns JSON with:
   - widgets array
   - data for each widget
   - metadata
3. Verify data is correct

**Expected Result:** Dashboard API returns correct data

### Test Scenario 21.3: Report Data API
**Steps:**
1. Call API to get report data:
   ```bash
   curl -X POST https://your-moodle.com/webservice/rest/server.php \
     -d "wstoken=YOUR_TOKEN" \
     -d "wsfunction=local_manireports_get_report_data" \
     -d "reporttype=course_completion" \
     -d "moodlewsrestformat=json"
   ```
2. Verify returns JSON with:
   - rows array
   - columns array
   - pagination info
3. Verify data is correct

**Expected Result:** Report API returns correct data

### Test Scenario 21.4: API Pagination
**Steps:**
1. Call API with pagination:
   ```bash
   curl -X POST https://your-moodle.com/webservice/rest/server.php \
     -d "wstoken=YOUR_TOKEN" \
     -d "wsfunction=local_manireports_get_report_data" \
     -d "reporttype=course_completion" \
     -d "page=1" \
     -d "pagesize=10" \
     -d "moodlewsrestformat=json"
   ```
2. Verify returns 10 rows
3. Request page 2
4. Verify different rows returned
5. Verify pagination metadata correct

**Expected Result:** API pagination works correctly

### Test Scenario 21.5: API Error Handling
**Steps:**
1. Call API with invalid parameters
2. Verify returns error JSON with:
   - error code
   - error message
3. Call API with missing required parameter
4. Verify returns appropriate error

**Expected Result:** API error handling works correctly

---

## Task 22: xAPI Integration

### Test Scenario 22.1: xAPI Detection
**Steps:**
1. Check if xAPI logstore installed:
   ```php
   $xapi_installed = \local_manireports\api\xapi_integration::is_xapi_installed();
   ```
2. Verify returns true if installed, false otherwise

**Expected Result:** xAPI detection works

### Test Scenario 22.2: xAPI Data Extraction
**Prerequisites:** xAPI logstore must be installed
**Steps:**
1. Extract xAPI statements:
   ```php
   $engine = new \local_manireports\api\xapi_integration();
   $statements = $engine->get_xapi_statements($userid, $courseid);
   ```
2. Verify returns array of statements
3. Verify statements have required fields

**Expected Result:** xAPI data extracted correctly

### Test Scenario 22.3: Video Watch Time
**Steps:**
1. Extract video watch time from xAPI:
   ```php
   $engine = new \local_manireports\api\xapi_integration();
   $watch_time = $engine->get_video_watch_time($userid, $courseid);
   ```
2. Verify returns time in seconds
3. Verify calculation is reasonable

**Expected Result:** Video watch time calculated correctly

### Test Scenario 22.4: xAPI Dashboard Widget
**Steps:**
1. Navigate to dashboard
2. Verify xAPI widget displays (if xAPI installed):
   - Video watch time
   - xAPI statement count
   - Engagement metrics
3. Verify data is accurate

**Expected Result:** xAPI widget displays correctly

### Test Scenario 22.5: xAPI Graceful Degradation
**Steps:**
1. Disable xAPI logstore
2. Navigate to dashboard
3. Verify dashboard still works
4. Verify xAPI widget hidden or shows N/A
5. Verify no errors in logs

**Expected Result:** Plugin works without xAPI

---

## Task 23: At-Risk Learner Dashboard

### Test Scenario 23.1: At-Risk Detection
**Steps:**
1. Navigate to: Dashboard → At-Risk Learners
2. Verify page loads
3. Verify displays list of at-risk learners:
   - User name
   - Risk score
   - Contributing factors
   - Last activity date
4. Verify data is accurate

**Expected Result:** At-risk learners displayed correctly

### Test Scenario 23.2: Risk Score Calculation
**Steps:**
1. Check database for at-risk flags:
   ```sql
   SELECT * FROM mdl_manireports_atrisk_ack 
   WHERE userid = [user_id];
   ```
2. Verify risk score between 0-100
3. Verify factors contributing to score

**Expected Result:** Risk scores calculated correctly

### Test Scenario 23.3: Acknowledge At-Risk
**Steps:**
1. On at-risk dashboard, click "Acknowledge" for a learner
2. Add note: "Contacted student"
3. Click "Save"
4. Verify acknowledgment recorded in database
5. Verify learner removed from at-risk list

**Expected Result:** At-risk acknowledgment works

### Test Scenario 23.4: Intervention Tracking
**Steps:**
1. Acknowledge at-risk learner
2. Add intervention note
3. Navigate back to at-risk dashboard
4. Verify note visible in history
5. Verify can add multiple notes

**Expected Result:** Intervention notes tracked

### Test Scenario 23.5: Email Notifications
**Steps:**
1. Configure at-risk email notifications
2. Flag new at-risk learner
3. Verify manager receives email
4. Verify email contains:
   - Learner name
   - Risk score
   - Contributing factors

**Expected Result:** Email notifications sent correctly

---

## Task 24: Privacy API for GDPR Compliance

### Test Scenario 24.1: Data Export
**Steps:**
1. Login as admin
2. Navigate to: Site administration → Users → Privacy
3. Request data export for a user
4. Verify export includes:
   - Time tracking data
   - Audit logs
   - Custom reports
   - Dashboard configurations
5. Verify data is in readable format

**Expected Result:** User data exported correctly

### Test Scenario 24.2: Data Deletion
**Steps:**
1. Request data deletion for a user
2. Verify deletion includes:
   - Time tracking sessions
   - Time tracking daily summaries
   - Audit logs
   - Custom reports
3. Verify data deleted from database
4. Verify user can still use plugin

**Expected Result:** User data deleted correctly

### Test Scenario 24.3: Privacy Metadata
**Steps:**
1. Check privacy metadata:
   ```php
   $provider = new \local_manireports\privacy\provider();
   $metadata = $provider->get_metadata([]);
   ```
2. Verify metadata describes:
   - Tables storing user data
   - Data retention policies
   - Data purposes

**Expected Result:** Privacy metadata complete

---

## Task 25: Data Retention and Cleanup

### Test Scenario 25.1: Audit Log Cleanup
**Steps:**
1. Configure audit log retention: 90 days
2. Create old audit log entries (> 90 days old)
3. Run cleanup task:
   ```bash
   sudo -u www-data php admin/cli/scheduled_task.php \
     --execute=\\local_manireports\\task\\cleanup_old_data
   ```
4. Verify old logs deleted
5. Verify recent logs retained

**Expected Result:** Audit logs cleaned up correctly

### Test Scenario 25.2: Report Run Cleanup
**Steps:**
1. Configure report run retention: 30 days
2. Create old report run records (> 30 days old)
3. Run cleanup task
4. Verify old runs deleted
5. Verify recent runs retained

**Expected Result:** Report runs cleaned up correctly

### Test Scenario 25.3: Cache Cleanup
**Steps:**
1. Create cache entries with TTL
2. Wait for TTL to expire
3. Run cleanup task
4. Verify expired cache deleted
5. Verify valid cache retained

**Expected Result:** Cache cleaned up correctly

### Test Scenario 25.4: Session Cleanup
**Steps:**
1. Create old session records (> 30 days old)
2. Run cleanup task
3. Verify old sessions deleted
4. Verify recent sessions retained

**Expected Result:** Sessions cleaned up correctly

---

## Task 26: Performance Optimizations

### Test Scenario 26.1: Database Indexes
**Steps:**
1. Check database indexes:
   ```sql
   SHOW INDEX FROM mdl_manireports_time_sessions;
   SHOW INDEX FROM mdl_manireports_time_daily;
   SHOW INDEX FROM mdl_manireports_report_runs;
   ```
2. Verify indexes on:
   - userid
   - courseid
   - date columns
3. Verify indexes improve query performance

**Expected Result:** Database indexes created correctly

### Test Scenario 26.2: Query Performance
**Steps:**
1. Run complex report query
2. Measure execution time (should be < 3 seconds)
3. Verify query uses indexes
4. Verify no full table scans

**Expected Result:** Queries execute quickly

### Test Scenario 26.3: Pagination Performance
**Steps:**
1. Navigate to report with 10,000+ rows
2. Verify pagination works
3. Verify page loads quickly (< 1 second)
4. Verify only current page data loaded

**Expected Result:** Pagination improves performance

### Test Scenario 26.4: JavaScript Minification
**Steps:**
1. Check amd/build/ directory
2. Verify .min.js files exist for all modules
3. Verify minified files are smaller than source
4. Verify minified files work correctly

**Expected Result:** JavaScript minified correctly

### Test Scenario 26.5: Concurrent Execution Limits
**Steps:**
1. Configure max concurrent reports: 3
2. Start 5 reports simultaneously
3. Verify only 3 execute concurrently
4. Verify others queued
5. Verify queue processes correctly

**Expected Result:** Concurrent execution limited correctly

---

## Task 27: Security Hardening

### Test Scenario 27.1: SQL Injection Prevention
**Steps:**
1. Try SQL injection in report parameter:
   ```
   courseid: 1; DROP TABLE mdl_course;--
   ```
2. Verify injection blocked
3. Verify error message shown
4. Verify table not dropped

**Expected Result:** SQL injection prevented

### Test Scenario 27.2: XSS Prevention
**Steps:**
1. Create report with XSS payload in name:
   ```
   <script>alert('XSS')</script>
   ```
2. Navigate to report
3. Verify script not executed
4. Verify payload escaped in HTML

**Expected Result:** XSS prevented

### Test Scenario 27.3: CSRF Protection
**Steps:**
1. Create form without sesskey
2. Try to submit
3. Verify submission blocked
4. Verify error message shown

**Expected Result:** CSRF protection working

### Test Scenario 27.4: Capability Checks
**Steps:**
1. Login as student
2. Try to access admin dashboard
3. Verify access denied
4. Verify error message shown
5. Check audit log for failed attempt

**Expected Result:** Capability checks enforced

### Test Scenario 27.5: Rate Limiting
**Steps:**
1. Make 100 API requests in 1 second
2. Verify requests rate limited
3. Verify 429 status code returned
4. Verify can retry after delay

**Expected Result:** Rate limiting working

---

## Task 28: Error Handling and Resilience

### Test Scenario 28.1: Try-Catch Blocks
**Steps:**
1. Cause database error (disconnect database)
2. Try to run report
3. Verify error caught
4. Verify user-friendly error message shown
5. Verify error logged

**Expected Result:** Errors handled gracefully

### Test Scenario 28.2: Retry Logic
**Steps:**
1. Create schedule with failing report
2. Run scheduler task
3. Verify task retries with exponential backoff
4. Verify failcount increments
5. Verify email alert sent after 3 failures

**Expected Result:** Retry logic working

### Test Scenario 28.3: Failed Job Management
**Steps:**
1. Navigate to: Site administration → Reports → Failed Jobs
2. Verify failed jobs displayed
3. Click "Retry" on failed job
4. Verify job retried
5. Verify status updated

**Expected Result:** Failed job management working

### Test Scenario 28.4: Timeout Handling
**Steps:**
1. Create very complex report (will timeout)
2. Execute report
3. Verify timeout caught
4. Verify error message shown
5. Verify user can retry

**Expected Result:** Timeouts handled correctly

### Test Scenario 28.5: Email Alerts
**Steps:**
1. Configure email alerts for failures
2. Cause report failure
3. Verify admin receives email
4. Verify email contains:
   - Error message
   - Report name
   - Timestamp

**Expected Result:** Email alerts sent correctly

---

## Task 29: Language Strings

### Test Scenario 29.1: Language String Coverage
**Steps:**
1. Navigate through entire plugin
2. Verify all UI text is in language file
3. Verify no hardcoded strings
4. Check lang/en/local_manireports.php
5. Verify 210+ strings defined

**Expected Result:** All strings in language file

### Test Scenario 29.2: String Placeholders
**Steps:**
1. Check strings with dynamic content
2. Verify use placeholders: {$a->name}
3. Verify placeholders replaced correctly
4. Test with different values

**Expected Result:** String placeholders work correctly

---

## Task 30: Documentation

### Test Scenario 30.1: User Guide
**Steps:**
1. Read Doc/USER_GUIDE.md
2. Verify covers:
   - Dashboard usage
   - Report creation
   - Export functionality
   - Filter usage
3. Verify instructions are clear
4. Verify screenshots/examples included

**Expected Result:** User guide complete and clear

### Test Scenario 30.2: Admin Guide
**Steps:**
1. Read Doc/ADMIN_GUIDE.md
2. Verify covers:
   - Installation
   - Configuration
   - Security settings
   - Performance tuning
3. Verify instructions are clear

**Expected Result:** Admin guide complete and clear

### Test Scenario 30.3: Developer Documentation
**Steps:**
1. Read Doc/DEVELOPER.md
2. Verify covers:
   - Architecture overview
   - API reference
   - Extension points
   - Database schema
3. Verify code examples included

**Expected Result:** Developer documentation complete

### Test Scenario 30.4: Troubleshooting Guide
**Steps:**
1. Read Doc/TROUBLESHOOTING.md
2. Verify covers common issues:
   - Installation problems
   - Performance issues
   - Permission errors
   - Data not showing
3. Verify solutions provided

**Expected Result:** Troubleshooting guide complete

---

## Summary Testing Checklist

- [ ] All 30 tasks tested
- [ ] No critical errors found
- [ ] Performance acceptable (< 3 seconds for dashboards)
- [ ] Security vulnerabilities addressed
- [ ] IOMAD filtering working (if applicable)
- [ ] All exports working (CSV, XLSX, PDF)
- [ ] Email delivery working
- [ ] Scheduled tasks executing
- [ ] Audit logging working
- [ ] Documentation complete
- [ ] Ready for production deployment


---

## DETAILED TESTING INSTRUCTIONS FOR PART 3

### Quick Reference for All Tasks 21-30

**Task 21: API Endpoints**
- Test authentication: Use valid API token
- Test dashboard API: `curl -X POST https://your-moodle.com/webservice/rest/server.php -d "wstoken=TOKEN" -d "wsfunction=local_manireports_get_dashboard_data" -d "moodlewsrestformat=json"`
- Test report API: `curl -X POST https://your-moodle.com/webservice/rest/server.php -d "wstoken=TOKEN" -d "wsfunction=local_manireports_get_report_data" -d "reporttype=course_completion" -d "moodlewsrestformat=json"`
- Test pagination: Add `page=1&pagesize=10` parameters
- Test error handling: Use invalid parameters, verify error JSON returned

**Task 22: xAPI Integration**
- Check if xAPI logstore installed: `mysql -u moodle_user -p moodle_db -e "SHOW TABLES LIKE 'mdl_logstore_xapi%';"`
- If installed, navigate to dashboard
- Verify xAPI widget displays (video watch time, engagement metrics)
- If not installed, verify dashboard still works without xAPI
- Verify no errors in logs

**Task 23: At-Risk Learner Dashboard**
- Navigate to: Dashboard → At-Risk Learners
- Verify list displays at-risk learners with risk scores
- Verify contributing factors shown (low time, no login, low completion)
- Click "Acknowledge" on a learner
- Add intervention note
- Verify acknowledgment recorded in database: `SELECT * FROM mdl_manireports_atrisk_ack;`
- Verify learner removed from at-risk list

**Task 24: Privacy API (GDPR)**
- Navigate to: Site administration → Users → Privacy
- Request data export for a user
- Verify export includes: time tracking, audit logs, custom reports
- Request data deletion for a user
- Verify data deleted from database
- Verify user can still use plugin

**Task 25: Data Retention & Cleanup**
- Configure retention periods in settings
- Create old records (> retention period)
- Run cleanup task: `sudo -u www-data php admin/cli/scheduled_task.php --execute=\\local_manireports\\task\\cleanup_old_data`
- Verify old records deleted
- Verify recent records retained
- Check: audit logs, report runs, cache, sessions

**Task 26: Performance Optimizations**
- Check database indexes: `SHOW INDEX FROM mdl_manireports_time_sessions;`
- Verify indexes on: userid, courseid, date columns
- Run complex report query
- Measure execution time (should be < 3 seconds)
- Verify pagination works for large result sets
- Check JavaScript minified: `ls -la local/manireports/amd/build/`
- Verify .min.js files exist

**Task 27: Security Hardening**
- Test SQL injection: Try `courseid: 1; DROP TABLE mdl_course;--`
- Verify injection blocked
- Test XSS: Create report with `<script>alert('XSS')</script>` in name
- Verify script not executed
- Test CSRF: Try form submission without sesskey
- Verify submission blocked
- Test capability checks: Login as student, try to access admin dashboard
- Verify access denied
- Check audit log for failed attempt

**Task 28: Error Handling & Resilience**
- Cause database error (disconnect database)
- Try to run report
- Verify error caught and user-friendly message shown
- Check error logged: `tail -f /var/www/html/moodledata/error.log`
- Create failing schedule
- Run scheduler task
- Verify task retries with exponential backoff
- Verify failcount increments
- Navigate to: Site administration → Reports → Failed Jobs
- Verify failed jobs displayed
- Click "Retry" on failed job
- Verify job retried

**Task 29: Language Strings**
- Navigate through entire plugin
- Verify all UI text is in language file
- Check: `local/manireports/lang/en/local_manireports.php`
- Verify 210+ strings defined
- Verify no hardcoded strings
- Test with different language (if available)

**Task 30: Documentation**
- Read: `Doc/USER_GUIDE.md`
- Verify covers: dashboard usage, report creation, export, filters
- Read: `Doc/ADMIN_GUIDE.md`
- Verify covers: installation, configuration, security, performance tuning
- Read: `Doc/DEVELOPER.md`
- Verify covers: architecture, API reference, extension points, database schema
- Read: `Doc/TROUBLESHOOTING.md`
- Verify covers: common issues and solutions

---

## FINAL TESTING CHECKLIST

After completing all 30 tasks, verify:

- [ ] All 30 tasks tested successfully
- [ ] No critical errors found
- [ ] Performance acceptable (< 3 seconds for dashboards)
- [ ] Security vulnerabilities addressed
- [ ] IOMAD filtering working (if applicable)
- [ ] All exports working (CSV, XLSX, PDF)
- [ ] Email delivery working
- [ ] Scheduled tasks executing
- [ ] Audit logging working
- [ ] Documentation complete
- [ ] Error logs clean (no PHP errors)
- [ ] Database integrity verified
- [ ] Ready for production deployment

---

## TROUBLESHOOTING COMMON ISSUES

### Issue: Report not loading
**Solution:**
1. Check error log: `tail -f /var/www/html/moodledata/error.log`
2. Clear caches: `sudo -u www-data php admin/cli/purge_caches.php`
3. Check database connection: `mysql -u moodle_user -p moodle_db -e "SELECT 1;"`
4. Verify plugin installed: `mysql -u moodle_user -p moodle_db -e "SELECT * FROM mdl_config_plugins WHERE plugin='local_manireports';"`

### Issue: Heartbeat not working
**Solution:**
1. Check Network tab for heartbeat requests
2. Verify JavaScript loaded: Check `amd/src/heartbeat.js` in Network tab
3. Check browser console for errors: F12 → Console tab
4. Verify AJAX endpoint accessible: `curl https://your-moodle.com/local/manireports/ui/ajax/heartbeat.php`

### Issue: Scheduled tasks not running
**Solution:**
1. Check cron configured: `sudo crontab -u www-data -l`
2. Run task manually: `sudo -u www-data php admin/cli/scheduled_task.php --execute=\\local_manireports\\task\\cache_builder`
3. Check task logs: `mysql -u moodle_user -p moodle_db -e "SELECT * FROM mdl_task_log WHERE component='local_manireports' ORDER BY timestarted DESC LIMIT 5;"`

### Issue: Permission denied errors
**Solution:**
1. Fix file permissions: `sudo chown -R www-data:www-data /var/www/html/moodle/local/manireports/`
2. Fix directory permissions: `sudo chmod -R 755 /var/www/html/moodle/local/manireports/`
3. Verify Moodle data directory: `sudo chown -R www-data:www-data /var/www/html/moodledata/`

### Issue: Charts not rendering
**Solution:**
1. Check Chart.js loaded: Network tab → search for "chart.js"
2. Check browser console for JavaScript errors: F12 → Console
3. Verify chart data returned: Check Network tab for AJAX response
4. Clear browser cache: Ctrl+Shift+Delete

---

## DEPLOYMENT VERIFICATION COMMANDS

Run these commands to verify plugin is ready for production:

```bash
# 1. Verify plugin installed
sudo -u www-data php admin/cli/cfg.php --component=local_manireports

# 2. Check all tables exist
mysql -u moodle_user -p moodle_db -e "
SELECT COUNT(*) as table_count FROM information_schema.tables 
WHERE table_schema = 'moodle_db' AND table_name LIKE 'mdl_manireports_%';
"

# 3. Verify scheduled tasks registered
sudo -u www-data php admin/cli/scheduled_task.php --list | grep manireports

# 4. Check error log is clean
tail -20 /var/www/html/moodledata/error.log

# 5. Verify database integrity
sudo -u www-data php admin/cli/maintenance.php --enable
sudo -u www-data php admin/cli/maintenance.php --disable

# 6. Test cron execution
sudo -u www-data php admin/cli/cron.php

# 7. Verify file permissions
ls -la /var/www/html/moodle/local/manireports/

# 8. Check plugin version
mysql -u moodle_user -p moodle_db -e "
SELECT value FROM mdl_config_plugins 
WHERE plugin='local_manireports' AND name='version';
"
```

**Expected Results:**
- Plugin version: 2024111704
- All 13 tables exist
- All 5 scheduled tasks registered
- Error log clean (no PHP errors)
- File permissions: 755 for directories, 644 for files
- Cron executes without errors

---

## SIGN-OFF CHECKLIST

When all tests pass, complete this checklist:

- [ ] All 30 tasks tested and verified
- [ ] No critical bugs found
- [ ] Performance meets requirements
- [ ] Security audit passed
- [ ] Documentation reviewed
- [ ] Database backup created
- [ ] Deployment commands verified
- [ ] Ready for production release

**Date Tested:** _______________
**Tested By:** _______________
**Status:** ✓ READY FOR PRODUCTION
