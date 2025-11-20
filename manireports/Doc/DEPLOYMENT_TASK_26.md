# Deployment Guide: Task 26 - Performance Optimizations

## Overview

Task 26 implements comprehensive performance optimizations to ensure ManiReports scales efficiently with increasing data volumes. This includes database indexing, query optimization, concurrent execution limits, pagination, and performance monitoring.

## Requirements Addressed

- **Requirement 20.1**: Database indexes on userid, courseid, and date columns
- **Requirement 20.2**: Pagination for all reports with >100 rows
- **Requirement 20.3**: Pre-aggregation tasks during off-peak hours
- **Requirement 20.4**: Concurrent report execution limits
- **Requirement 20.5**: Dashboard loads within 3 seconds for 10,000 users

## Files Created

### 1. Core Performance Optimizer
- `classes/api/performance_optimizer.php` - Performance optimization engine

### 2. User Interface
- `ui/performance.php` - Performance monitoring dashboard

### 3. CLI Tools
- `cli/ensure_indexes.php` - Command-line tool to create indexes

### 4. Build Directory
- `amd/build/.gitkeep` - Placeholder for minified JavaScript

## Files Modified

### 1. Settings Configuration
- `settings.php` - Added performance optimization settings

### 2. Language Strings
- `lang/en/local_manireports.php` - Added 20+ performance-related strings

### 3. Report Builder
- `classes/api/report_builder.php` - Added concurrent execution checks

## Features Implemented

### 1. Database Indexing ✅

**Indexes Created:**
- `manireports_usertime_sessions`:
  - `userid_courseid_idx` (userid, courseid)
  - `lastupdated_idx` (lastupdated)
  
- `manireports_usertime_daily`:
  - `userid_courseid_date_idx` (userid, courseid, date)
  - `date_idx` (date)
  
- `manireports_audit_logs`:
  - `userid_timecreated_idx` (userid, timecreated)
  - `action_idx` (action)
  
- `manireports_report_runs`:
  - `reportid_timestarted_idx` (reportid, timestarted)
  - `userid_timestarted_idx` (userid, timestarted)

**Benefits:**
- Faster query execution on filtered data
- Improved JOIN performance
- Reduced database load

### 2. Concurrent Execution Limits ✅

**Implementation:**
- Maximum concurrent reports configurable (default: 5)
- Automatic queuing when limit reached
- Prevents database overload
- User-friendly error messages

**Configuration:**
- Admin setting: `max_concurrent_reports`
- Default value: 5
- Adjustable based on server capacity

### 3. Pagination ✅

**Implementation:**
- Automatic pagination for large result sets
- Configurable page size (default: 100 rows)
- Metadata includes: page, pagesize, total, totalpages, hasmore
- Efficient array slicing

**Configuration:**
- Admin setting: `default_page_size`
- Default value: 100
- Prevents memory issues with large datasets

### 4. Query Timeout ✅

**Implementation:**
- Configurable query timeout (default: 30 seconds)
- Prevents runaway queries
- Logged for monitoring

**Configuration:**
- Admin setting: `query_timeout`
- Default value: 30 seconds

### 5. Performance Monitoring Dashboard ✅

**Features:**
- Table size statistics
- Concurrent report utilization
- Cache hit rate monitoring
- Task scheduling recommendations
- One-click index creation

**Access:**
- Site Administration → Plugins → Local plugins → ManiReports → Performance Monitoring
- Requires: `local/manireports:viewadmindashboard` capability

### 6. JavaScript Optimization ✅

**Already Implemented:**
- Request debouncing on filters (300ms delay)
- Prevents excessive AJAX requests
- Improves user experience

**Build Process:**
- Minification via Grunt: `npx grunt amd --root=local/manireports`
- Minified files stored in `amd/build/`

## Deployment Steps

### Step 1: Upload Files to Server

```bash
# SSH into your EC2 instance
ssh user@your-ec2-instance.com

# Navigate to Moodle directory
cd /var/www/html/moodle/local/manireports

# Upload files via Git
git pull origin main

# Or upload via SCP
scp -r local/manireports/* user@server:/var/www/html/moodle/local/manireports/

# Set proper permissions
sudo chown -R www-data:www-data classes/api/performance_optimizer.php
sudo chown -R www-data:www-data ui/performance.php
sudo chown -R www-data:www-data cli/ensure_indexes.php
sudo chmod 755 classes/api/performance_optimizer.php
sudo chmod 755 ui/performance.php
sudo chmod 755 cli/ensure_indexes.php
```

### Step 2: Clear Caches

```bash
# Clear all Moodle caches
sudo -u www-data php admin/cli/purge_caches.php

# Verify no errors
echo $?
```

### Step 3: Create Database Indexes

```bash
# Run the index creation script
sudo -u www-data php local/manireports/cli/ensure_indexes.php

# Expected output:
# ManiReports - Ensure Database Indexes
# Checking and creating required indexes...
# 
# Results:
#   Indexes checked: 8
#   Indexes created: 8
# 
# Done!
```

### Step 4: Configure Performance Settings

1. Log in as admin
2. Go to **Site Administration → Plugins → Local plugins → ManiReports**
3. Scroll to "Performance Optimization Settings"
4. Configure:
   - **Maximum Concurrent Reports**: 5 (adjust based on server capacity)
   - **Default Page Size**: 100 (adjust based on typical report sizes)
   - **Query Timeout**: 30 seconds
5. Click "Save changes"

### Step 5: Verify Performance Dashboard

1. Go to **Site Administration → Plugins → Local plugins → ManiReports → Performance Monitoring**
2. Verify the dashboard loads without errors
3. Check statistics are displayed correctly
4. Test "Ensure Database Indexes" button

### Step 6: Minify JavaScript (Optional)

```bash
# Navigate to Moodle root
cd /var/www/html/moodle

# Install Grunt if not already installed
npm install -g grunt-cli

# Run Grunt to minify AMD modules
npx grunt amd --root=local/manireports

# Verify minified files created
ls -la local/manireports/amd/build/
```

## Testing

### Test 1: Verify Database Indexes

```bash
# Check indexes on usertime_sessions table
mysql -u moodle_user -p moodle_db -e "
SHOW INDEX FROM mdl_manireports_usertime_sessions;
"

# Expected output should include:
# - userid_courseid_idx
# - lastupdated_idx

# Check indexes on usertime_daily table
mysql -u moodle_user -p moodle_db -e "
SHOW INDEX FROM mdl_manireports_usertime_daily;
"

# Expected output should include:
# - userid_courseid_date_idx
# - date_idx
```

### Test 2: Test Concurrent Execution Limit

```bash
# Create test script
cat > /tmp/test_concurrent.php << 'EOF'
<?php
require_once('/var/www/html/moodle/config.php');
require_login();

$optimizer = new \local_manireports\api\performance_optimizer();

echo "Current concurrent reports: " . $optimizer->get_concurrent_report_count() . "\n";
echo "Can execute report: " . ($optimizer->can_execute_report() ? 'Yes' : 'No') . "\n";

// Simulate running reports by creating report_runs records
for ($i = 0; $i < 6; $i++) {
    $run = new stdClass();
    $run->reportid = 1;
    $run->userid = 2;
    $run->timestarted = time();
    $run->status = 'running';
    $run->parameters = '{}';
    
    $DB->insert_record('manireports_report_runs', $run);
    
    echo "Created run " . ($i + 1) . "\n";
    echo "Can execute report: " . ($optimizer->can_execute_report() ? 'Yes' : 'No') . "\n";
}

// Cleanup
$DB->delete_records('manireports_report_runs', ['status' => 'running']);
echo "Cleaned up test data\n";
EOF

# Run test
sudo -u www-data php /tmp/test_concurrent.php
```

### Test 3: Test Pagination

```bash
# Create test script
cat > /tmp/test_pagination.php << 'EOF'
<?php
require_once('/var/www/html/moodle/config.php');
require_login();

$optimizer = new \local_manireports\api\performance_optimizer();

// Create test data
$data = range(1, 250);

// Test pagination
$result = $optimizer->paginate_results($data, 0, 100);

echo "Page: " . $result['page'] . "\n";
echo "Page size: " . $result['pagesize'] . "\n";
echo "Total records: " . $result['total'] . "\n";
echo "Total pages: " . $result['totalpages'] . "\n";
echo "Has more: " . ($result['hasmore'] ? 'Yes' : 'No') . "\n";
echo "Data count: " . count($result['data']) . "\n";

// Test page 2
$result2 = $optimizer->paginate_results($data, 1, 100);
echo "\nPage 2 data count: " . count($result2['data']) . "\n";
echo "Has more: " . ($result2['hasmore'] ? 'Yes' : 'No') . "\n";
EOF

# Run test
sudo -u www-data php /tmp/test_pagination.php

# Expected output:
# Page: 0
# Page size: 100
# Total records: 250
# Total pages: 3
# Has more: Yes
# Data count: 100
# 
# Page 2 data count: 100
# Has more: Yes
```

### Test 4: Test Performance Dashboard

1. Log in as admin
2. Go to **Site Administration → Plugins → Local plugins → ManiReports → Performance Monitoring**
3. Verify:
   - Table sizes are displayed
   - Concurrent reports shows 0/5 (0%)
   - Cache statistics are shown (if cache data exists)
   - Task recommendations are listed
4. Click "Ensure Database Indexes"
5. Verify success message appears

### Test 5: Test Concurrent Limit in Report Execution

```bash
# Create test script to simulate concurrent report execution
cat > /tmp/test_report_limit.php << 'EOF'
<?php
require_once('/var/www/html/moodle/config.php');
require_login();

// Create 5 running reports
for ($i = 0; $i < 5; $i++) {
    $run = new stdClass();
    $run->reportid = 1;
    $run->userid = 2;
    $run->timestarted = time();
    $run->status = 'running';
    $run->parameters = '{}';
    $DB->insert_record('manireports_report_runs', $run);
}

// Try to execute a report (should fail)
$builder = new \local_manireports\api\report_builder();

try {
    $result = $builder->execute_report(1);
    echo "ERROR: Report should have been blocked!\n";
} catch (\moodle_exception $e) {
    if ($e->errorcode === 'toomanyreports') {
        echo "SUCCESS: Report execution blocked as expected\n";
        echo "Error message: " . $e->getMessage() . "\n";
    } else {
        echo "ERROR: Wrong exception: " . $e->errorcode . "\n";
    }
}

// Cleanup
$DB->delete_records('manireports_report_runs', ['status' => 'running']);
echo "Cleaned up test data\n";
EOF

# Run test
sudo -u www-data php /tmp/test_report_limit.php
```

### Test 6: Test Debouncing in UI

1. Log in as any user
2. Go to a dashboard with filters
3. Open browser developer console (F12)
4. Type rapidly in a filter field
5. Observe network tab - should see debounced requests (not one per keystroke)

## Performance Monitoring

### Monitor Query Performance

```bash
# Check slow query log (if enabled)
tail -f /var/log/mysql/slow-query.log

# Monitor table sizes
mysql -u moodle_user -p moodle_db -e "
SELECT 
    table_name,
    table_rows,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)'
FROM information_schema.TABLES
WHERE table_schema = 'moodle_db'
    AND table_name LIKE 'mdl_manireports%'
ORDER BY (data_length + index_length) DESC;
"
```

### Monitor Concurrent Reports

```bash
# Create monitoring script
cat > /tmp/monitor_reports.sh << 'EOF'
#!/bin/bash
while true; do
    clear
    echo "=== ManiReports Performance Monitor ==="
    echo "Time: $(date)"
    echo ""
    
    mysql -u moodle_user -p'password' moodle_db -e "
    SELECT 
        COUNT(*) as running_reports,
        MIN(timestarted) as oldest_start,
        MAX(timestarted) as newest_start
    FROM mdl_manireports_report_runs
    WHERE status = 'running';
    "
    
    sleep 5
done
EOF

chmod +x /tmp/monitor_reports.sh
/tmp/monitor_reports.sh
```

### Monitor Cache Hit Rate

```bash
# Check cache performance
mysql -u moodle_user -p moodle_db -e "
SELECT 
    COUNT(*) as total_entries,
    SUM(CASE WHEN (UNIX_TIMESTAMP() - lastgenerated) <= ttl THEN 1 ELSE 0 END) as valid_entries,
    ROUND(SUM(CASE WHEN (UNIX_TIMESTAMP() - lastgenerated) <= ttl THEN 1 ELSE 0 END) / COUNT(*) * 100, 2) as hit_rate_percent
FROM mdl_manireports_cache_summary;
"
```

## Performance Tuning Recommendations

### 1. Adjust Concurrent Report Limit

Based on server capacity:
- **Small server** (2 CPU, 4GB RAM): 3-5 concurrent reports
- **Medium server** (4 CPU, 8GB RAM): 5-10 concurrent reports
- **Large server** (8+ CPU, 16GB+ RAM): 10-20 concurrent reports

### 2. Optimize Page Size

Based on typical report sizes:
- **Small reports** (<1000 rows): 100-200 per page
- **Medium reports** (1000-10000 rows): 50-100 per page
- **Large reports** (>10000 rows): 25-50 per page

### 3. Schedule Heavy Tasks Off-Peak

Configure these tasks to run during low-traffic hours (e.g., 2:00 AM - 4:00 AM):
- `cache_builder` - Heavy database operations
- `scorm_summary` - Aggregates SCORM data
- `time_aggregation` - Processes session data

### 4. Monitor and Adjust

Regularly check the Performance Monitoring dashboard:
- If concurrent reports frequently hit limit, increase `max_concurrent_reports`
- If cache hit rate < 70%, increase cache TTL values
- If table sizes grow rapidly, review data retention policies

## Troubleshooting

### Issue: Indexes Not Created

**Symptoms:**
- CLI script reports errors
- Queries still slow

**Solution:**
```bash
# Check database permissions
mysql -u moodle_user -p moodle_db -e "SHOW GRANTS;"

# Manually create indexes
mysql -u moodle_user -p moodle_db << 'EOF'
CREATE INDEX userid_courseid_idx ON mdl_manireports_usertime_sessions(userid, courseid);
CREATE INDEX lastupdated_idx ON mdl_manireports_usertime_sessions(lastupdated);
-- Repeat for other indexes
EOF
```

### Issue: Performance Dashboard Not Loading

**Symptoms:**
- Blank page or error

**Solution:**
```bash
# Check error logs
tail -f /var/www/html/moodledata/error.log

# Verify permissions
ls -la local/manireports/ui/performance.php

# Clear caches
sudo -u www-data php admin/cli/purge_caches.php
```

### Issue: Reports Still Slow

**Symptoms:**
- Reports take >30 seconds
- Timeout errors

**Solution:**
1. Check if indexes exist (see Test 1)
2. Verify cache is enabled
3. Check table sizes - may need archiving
4. Review custom SQL queries for optimization
5. Increase `query_timeout` if legitimate long-running queries

### Issue: Too Many Concurrent Reports Error

**Symptoms:**
- Users see "Too many reports running" error frequently

**Solution:**
1. Check current concurrent count in Performance Dashboard
2. Increase `max_concurrent_reports` setting
3. Review long-running reports - may need optimization
4. Consider adding more server resources

## Rollback Plan

If issues occur after deployment:

```bash
# 1. Backup current state
mysqldump -u moodle_user -p moodle_db > backup_before_rollback.sql

# 2. Remove indexes (if causing issues)
mysql -u moodle_user -p moodle_db << 'EOF'
DROP INDEX userid_courseid_idx ON mdl_manireports_usertime_sessions;
DROP INDEX lastupdated_idx ON mdl_manireports_usertime_sessions;
-- Repeat for other indexes
EOF

# 3. Restore previous files
git checkout HEAD~1 local/manireports/classes/api/performance_optimizer.php
git checkout HEAD~1 local/manireports/classes/api/report_builder.php

# 4. Clear caches
sudo -u www-data php admin/cli/purge_caches.php

# 5. Verify site functionality
```

## Success Criteria

Task 26 is complete when:

- ✅ All 8 database indexes created successfully
- ✅ Concurrent execution limit enforced (default: 5)
- ✅ Pagination implemented for all large result sets
- ✅ Performance monitoring dashboard accessible
- ✅ CLI tool for index creation works
- ✅ Settings configurable via admin interface
- ✅ No PHP syntax errors
- ✅ All tests pass
- ✅ Documentation complete

## Next Steps

After Task 26:
1. Monitor performance metrics for 1 week
2. Adjust settings based on actual usage patterns
3. Review slow query logs for optimization opportunities
4. Consider implementing query result caching for frequently-run reports
5. Proceed to Task 27: Security Hardening

## Support

For issues or questions:
1. Check Moodle error logs: `/var/www/html/moodledata/error.log`
2. Review Performance Monitoring dashboard
3. Check database slow query log
4. Verify all indexes exist
5. Ensure settings are configured appropriately

---

**Task 26 Status**: ✅ COMPLETE

**Deployment Date**: [To be filled during deployment]

**Deployed By**: [To be filled during deployment]

**Notes**: [Add any deployment-specific notes]
