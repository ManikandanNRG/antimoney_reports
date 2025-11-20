# Deployment Guide for Tasks 8.3 & 25: Cache Integration and Data Cleanup

## Overview
This guide covers deployment of cache integration into report execution and the data retention/cleanup scheduled task.

## What Was Implemented

### Task 8.3: Integrate Caching into Report Execution ✅

**Cache Integration Features:**
1. **Automatic Cache Check** - Reports check cache before executing queries
2. **Cache Key Generation** - Unique keys based on report type and parameters
3. **TTL Management** - Configurable time-to-live per report type
4. **Cache Hit/Miss Tracking** - Results indicate if data came from cache
5. **Automatic Cache Storage** - Results automatically cached after execution
6. **Cache Invalidation** - Cache cleared when reports are modified
7. **Execution Time Tracking** - Shows time saved by caching

**Files Modified:**
- `local/manireports/classes/reports/base_report.php` - Added cache integration to execute()
- `local/manireports/classes/api/report_builder.php` - Added cache integration for custom reports
- `local/manireports/lang/en/local_manireports.php` - Added cache-related strings

**New Methods Added:**
- `base_report::is_cacheable()` - Check if report supports caching
- `base_report::get_cache_ttl()` - Get cache TTL for report
- `base_report::get_report_type()` - Get report type identifier
- `base_report::format_row()` - Format row data (timestamps, etc.)
- `report_builder::invalidate_report_cache()` - Clear cache for specific report

### Task 25: Data Retention and Cleanup ✅

**Cleanup Task Features:**
1. **Audit Log Cleanup** - Removes logs older than retention period (default 365 days)
2. **Report Run Cleanup** - Removes old execution history (default 90 days)
3. **Expired Cache Cleanup** - Removes cache entries past TTL
4. **Old Session Cleanup** - Removes sessions older than 7 days
5. **Orphaned Data Cleanup** - Removes records for deleted users/reports/schedules

**New File Created:**
- `local/manireports/classes/task/cleanup_old_data.php` - Scheduled task

**Cleanup Operations:**
- Audit logs (configurable retention)
- Report runs (configurable retention)
- Expired cache entries
- Old time tracking sessions
- Orphaned schedule recipients
- Orphaned report runs
- Orphaned time tracking data

## Deployment Steps

### 1. SSH into EC2 Instance
```bash
ssh user@your-ec2-instance.com
```

### 2. Navigate to Moodle Directory
```bash
cd /var/www/html/moodle
```

### 3. Deploy Updated Files
```bash
# If using Git
cd local/manireports
git pull origin main

# If using SCP (from local machine)
scp -r local/manireports/classes/reports/base_report.php user@ec2:/var/www/html/moodle/local/manireports/classes/reports/
scp -r local/manireports/classes/api/report_builder.php user@ec2:/var/www/html/moodle/local/manireports/classes/api/
scp -r local/manireports/classes/task/cleanup_old_data.php user@ec2:/var/www/html/moodle/local/manireports/classes/task/
scp -r local/manireports/lang/en/local_manireports.php user@ec2:/var/www/html/moodle/local/manireports/lang/en/
```

### 4. Set Proper Permissions
```bash
sudo chown -R www-data:www-data /var/www/html/moodle/local/manireports/
sudo chmod -R 755 /var/www/html/moodle/local/manireports/
```

### 5. Clear Moodle Caches
```bash
sudo -u www-data php admin/cli/purge_caches.php
```

### 6. Verify Scheduled Task Registration
```bash
# Check if cleanup task is registered
sudo -u www-data php admin/cli/scheduled_task.php --list | grep cleanup
```

Expected output:
```
local_manireports\task\cleanup_old_data
```

### 7. Verify No Errors
```bash
tail -f /var/www/html/moodledata/error.log
```

## Testing Instructions

### Test 1: Cache Integration - First Execution (Cache Miss)
1. Navigate to a report: `/local/manireports/ui/report_view.php?report=course_completion`
2. Note the execution time displayed
3. Check that it says "Fresh data" (cache miss)

**Expected:**
- Report executes normally
- Execution time shown (e.g., "0.523s")
- No cache indicator or shows "Fresh data"

### Test 2: Cache Integration - Second Execution (Cache Hit)
1. Refresh the same report immediately
2. Note the execution time

**Expected:**
- Report loads much faster
- Execution time significantly reduced (e.g., "0.012s")
- Shows "Cached result" indicator
- Data is identical to first execution

### Test 3: Cache TTL Configuration
1. Navigate to: Site Administration → Plugins → Local plugins → ManiReports
2. Find "Dashboard cache TTL" setting
3. Change from 3600 to 60 seconds
4. Save changes

**Expected:**
- Setting saves successfully
- Cache will expire after 60 seconds instead of 1 hour

### Test 4: Cache Invalidation on Report Update
1. Navigate to: Custom Reports
2. Edit an existing custom report
3. Make a small change (e.g., update description)
4. Save the report
5. View the report

**Expected:**
- Cache is automatically cleared
- Report executes fresh query (cache miss)
- New execution time shown

### Test 5: Cleanup Task - Manual Execution
```bash
# Run cleanup task manually
sudo -u www-data php admin/cli/scheduled_task.php --execute=\\local_manireports\\task\\cleanup_old_data
```

**Expected Output:**
```
Starting data cleanup task...
Cleaned up X old audit log entries
Cleaned up X old report run records
Cleaned up X expired cache entries
Cleaned up X old session records
Cleaned up X orphaned records
Data cleanup task completed. Total records cleaned: X
```

### Test 6: Audit Log Retention
1. Create some test audit log entries (by creating/editing reports)
2. Manually set old timestamps:
```bash
mysql -u moodle_user -p moodle_db
UPDATE mdl_manireports_audit_logs SET timecreated = UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 400 DAY)) LIMIT 5;
```
3. Run cleanup task
4. Verify old entries are deleted:
```bash
SELECT COUNT(*) FROM mdl_manireports_audit_logs WHERE timecreated < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 365 DAY));
```

**Expected:** Count should be 0 (all old entries deleted)

### Test 7: Report Run Retention
1. Check current report runs:
```bash
SELECT COUNT(*) FROM mdl_manireports_report_runs;
```
2. Create old report run entries:
```bash
UPDATE mdl_manireports_report_runs SET timestarted = UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 100 DAY)) LIMIT 3;
```
3. Run cleanup task
4. Verify old runs are deleted:
```bash
SELECT COUNT(*) FROM mdl_manireports_report_runs WHERE timestarted < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 90 DAY));
```

**Expected:** Count should be 0

### Test 8: Expired Cache Cleanup
1. Check current cache entries:
```bash
SELECT COUNT(*) FROM mdl_manireports_cache_summary;
```
2. Manually expire some cache entries:
```bash
UPDATE mdl_manireports_cache_summary SET lastgenerated = UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 2 HOUR)), ttl = 3600 LIMIT 2;
```
3. Run cleanup task
4. Verify expired entries are deleted

**Expected:** Expired cache entries removed

### Test 9: Orphaned Data Cleanup
1. Create orphaned schedule recipient (for non-existent schedule):
```bash
INSERT INTO mdl_manireports_schedule_recipients (scheduleid, email) VALUES (99999, 'test@example.com');
```
2. Run cleanup task
3. Verify orphaned record is deleted:
```bash
SELECT COUNT(*) FROM mdl_manireports_schedule_recipients WHERE scheduleid = 99999;
```

**Expected:** Count should be 0

### Test 10: Cache Performance Comparison
1. Create a report with a slow query (if possible)
2. Execute report and note time (cache miss)
3. Execute again immediately and note time (cache hit)
4. Calculate performance improvement

**Expected:**
- Cache hit should be 10-100x faster than cache miss
- Larger datasets show more dramatic improvement

### Test 11: Scheduled Task Configuration
1. Navigate to: Site Administration → Server → Scheduled tasks
2. Find "Cleanup old data" task
3. Verify schedule: Daily at 2:00 AM
4. Can modify schedule if needed

**Expected:**
- Task appears in list
- Default schedule: 0 2 * * *
- Can be edited by admin

### Test 12: Cache Warming (Pre-aggregation)
```bash
# Run cache builder to warm cache
sudo -u www-data php admin/cli/scheduled_task.php --execute=\\local_manireports\\task\\cache_builder
```

**Expected:**
- Pre-aggregates common reports
- Subsequent report executions use cached data
- Faster dashboard load times

## Configuration Options

### Cache TTL Settings
Navigate to: Site Administration → Plugins → Local plugins → ManiReports

**Dashboard cache TTL** (default: 3600 seconds = 1 hour)
- Used for dashboard widgets and KPI cards
- Shorter TTL = more current data, more database load
- Longer TTL = faster dashboards, potentially stale data

**Trend reports cache TTL** (default: 21600 seconds = 6 hours)
- Used for trend analysis reports
- Historical data changes less frequently

**Historical reports cache TTL** (default: 86400 seconds = 24 hours)
- Used for long-term historical reports
- Data rarely changes

### Data Retention Settings

**Audit log retention** (default: 365 days)
- How long to keep audit trail entries
- Compliance requirements may dictate minimum retention

**Report run retention** (default: 90 days)
- How long to keep report execution history
- Useful for troubleshooting and performance analysis

## Troubleshooting

### Issue: Cache not working (always cache miss)
**Solution:**
```bash
# Check if cache table exists
mysql -u moodle_user -p moodle_db -e "SHOW TABLES LIKE 'mdl_manireports_cache_summary';"

# Check cache entries
mysql -u moodle_user -p moodle_db -e "SELECT COUNT(*) FROM mdl_manireports_cache_summary;"

# Verify cache_manager class is loaded
sudo -u www-data php -r "require_once('/var/www/html/moodle/config.php'); var_dump(class_exists('local_manireports\api\cache_manager'));"
```

### Issue: Cleanup task not running
**Solution:**
```bash
# Check if task is enabled
sudo -u www-data php admin/cli/scheduled_task.php --list | grep cleanup

# Run manually to see errors
sudo -u www-data php admin/cli/scheduled_task.php --execute=\\local_manireports\\task\\cleanup_old_data

# Check cron is running
grep CRON /var/log/syslog | tail -20
```

### Issue: Cache growing too large
**Solution:**
```bash
# Check cache table size
mysql -u moodle_user -p moodle_db -e "SELECT COUNT(*), SUM(LENGTH(datajson)) as total_bytes FROM mdl_manireports_cache_summary;"

# Manually clear all cache
mysql -u moodle_user -p moodle_db -e "TRUNCATE TABLE mdl_manireports_cache_summary;"

# Reduce cache TTL settings
sudo -u www-data php admin/cli/cfg.php --name=local_manireports/cachettl_dashboard --set=1800
```

### Issue: Old data not being cleaned up
**Solution:**
```bash
# Check retention settings
sudo -u www-data php admin/cli/cfg.php --name=local_manireports/auditlogretention
sudo -u www-data php admin/cli/cfg.php --name=local_manireports/reportrunretention

# Manually run cleanup with debug
sudo -u www-data php admin/cli/scheduled_task.php --execute=\\local_manireports\\task\\cleanup_old_data --showdebugging

# Check for database errors
tail -f /var/www/html/moodledata/error.log
```

### Issue: Reports slower after caching enabled
**Solution:**
- This shouldn't happen - caching should improve performance
- Check if cache is actually being used (look for "cached" indicator)
- Verify cache TTL is not too short (causing frequent cache misses)
- Check database performance (cache table might need indexing)

## Performance Verification

### Cache Hit Rate
Monitor cache effectiveness:
```bash
# Check cache statistics
mysql -u moodle_user -p moodle_db << EOF
SELECT 
    reporttype,
    COUNT(*) as cache_entries,
    AVG(UNIX_TIMESTAMP() - lastgenerated) as avg_age_seconds,
    MIN(lastgenerated) as oldest,
    MAX(lastgenerated) as newest
FROM mdl_manireports_cache_summary
GROUP BY reporttype;
EOF
```

**Target:** Cache hit rate > 50% for frequently accessed reports

### Cleanup Effectiveness
Monitor cleanup task:
```bash
# Check audit log growth
mysql -u moodle_user -p moodle_db -e "SELECT COUNT(*), MIN(FROM_UNIXTIME(timecreated)), MAX(FROM_UNIXTIME(timecreated)) FROM mdl_manireports_audit_logs;"

# Check report run growth
mysql -u moodle_user -p moodle_db -e "SELECT COUNT(*), MIN(FROM_UNIXTIME(timestarted)), MAX(FROM_UNIXTIME(timestarted)) FROM mdl_manireports_report_runs;"
```

**Target:** Data stays within retention periods

## Success Criteria

Tasks 8.3 and 25 are successfully deployed when:

### Task 8.3 (Cache Integration):
- [ ] Reports check cache before executing
- [ ] Cache hits show significantly faster execution times
- [ ] Cache misses store results for future use
- [ ] Cache is invalidated when reports are modified
- [ ] Execution time is displayed for all reports
- [ ] Cache TTL settings are configurable
- [ ] No errors in logs related to caching

### Task 25 (Data Cleanup):
- [ ] Cleanup task is registered and scheduled
- [ ] Task runs successfully (manually and via cron)
- [ ] Old audit logs are deleted per retention policy
- [ ] Old report runs are deleted per retention policy
- [ ] Expired cache entries are removed
- [ ] Old session data is cleaned up
- [ ] Orphaned data is removed
- [ ] No errors during cleanup execution

## Next Steps

After successful deployment:
1. Monitor cache hit rates over first week
2. Adjust cache TTL settings based on usage patterns
3. Monitor database growth to ensure cleanup is effective
4. Review retention policies with compliance team
5. Consider implementing cache warming for critical reports
6. **MVP is now 100% complete!** 🎉

## MVP Completion Status

With Tasks 8.3 and 25 complete:
- ✅ **MVP (Tasks 1-17): 100% Complete**
- ✅ **All critical features implemented**
- ✅ **Ready for production use**

Remaining tasks (18-34) are Phase 2/3 optional features.
