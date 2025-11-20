# Deployment Guide: Task 28 - Error Handling and Resilience

## Overview

Task 28 implements comprehensive error handling and resilience features to ensure ManiReports can recover from failures, retry operations, and provide administrators with tools to manage and monitor system health.

## Requirements Addressed

- **Requirement 23.1**: Retry logic with exponential backoff
- **Requirement 23.2**: Comprehensive error logging with context
- **Requirement 23.3**: Admin UI for failed job management
- **Requirement 23.4**: Query timeout handling
- **Requirement 23.5**: Email alerts for repeated failures

## Files Created

### 1. Error Handler Class
- `classes/api/error_handler.php` - Error handling and resilience utilities

### 2. User Interface
- `ui/failed_jobs.php` - Failed jobs management dashboard

### 3. Database Schema
- Added `manireports_failed_jobs` table to `db/install.xml`

## Files Modified

### 1. Settings Configuration
- `settings.php` - Added failed jobs link

### 2. Language Strings
- `lang/en/local_manireports.php` - Added error handling strings

## Features Implemented

### 1. Retry Logic with Exponential Backoff ✅

**Implementation:**
- Automatic retry up to 3 attempts
- Exponential backoff (2s, 4s, 8s)
- Configurable retry count
- Full error logging on each attempt

**Usage:**
```php
$handler = new \local_manireports\api\error_handler();

$result = $handler->execute_with_retry(function() {
    // Your operation here
    return perform_operation();
}, 3, 'Operation context');
```

### 2. Comprehensive Error Logging ✅

**Implementation:**
- Full exception details (message, code, file, line)
- Stack trace capture
- User context
- Additional metadata
- Audit log integration

**Usage:**
```php
try {
    // Operation
} catch (\Exception $e) {
    error_handler::log_error($e, 'Context description', [
        'additional' => 'data',
    ]);
}
```

### 3. Failed Job Management ✅

**Implementation:**
- Automatic recording of task failures
- Retry tracking
- Manual retry capability
- Bulk cleanup of old jobs
- Detailed error information

**Database Table:**
```sql
CREATE TABLE mdl_manireports_failed_jobs (
    id BIGINT PRIMARY KEY,
    taskname VARCHAR(255),
    error TEXT,
    stacktrace TEXT,
    context TEXT,
    timefailed BIGINT,
    retrycount INT DEFAULT 0,
    lastretry BIGINT
);
```

### 4. Admin UI for Failed Jobs ✅

**Features:**
- System health dashboard
- Failed jobs list
- One-click retry
- Job deletion
- Bulk cleanup (30+ days)
- Stack trace viewing

**Access:**
- Site Administration → Plugins → ManiReports → Failed Jobs
- Requires: `local/manireports:viewadmindashboard`

### 5. Email Alerts for Repeated Failures ✅

**Implementation:**
- Monitors failures in 24-hour window
- Sends alert after 3 failures
- Emails all site administrators
- Includes failure count and management link

**Alert Trigger:**
- 3+ failures of same task in 24 hours

### 6. Timeout Handling ✅

**Implementation:**
- Configurable timeout per operation
- Automatic time limit adjustment
- Timeout detection and logging
- Graceful cleanup

**Usage:**
```php
$result = error_handler::execute_with_timeout(function() {
    // Long-running operation
    return process_data();
}, 60, 'Data processing');
```

### 7. Safe Execution Wrapper ✅

**Implementation:**
- Try-catch wrapper
- Automatic error logging
- Default return value on error
- Non-blocking failures

**Usage:**
```php
$result = error_handler::safe_execute(function() {
    // Potentially failing operation
    return get_data();
}, 'Get data operation', []);
```

### 8. System Health Monitoring ✅

**Checks:**
- Database connectivity
- Failed job count
- Disk space
- Overall system status

**Status Levels:**
- Healthy: All checks pass
- Warning: Minor issues detected
- Critical: Major issues require attention

## Deployment Steps

### Step 1: Upload Files

```bash
# SSH into server
ssh user@your-ec2-instance.com

# Navigate to Moodle directory
cd /var/www/html/moodle/local/manireports

# Upload files
git pull origin main

# Set permissions
sudo chown -R www-data:www-data classes/api/error_handler.php
sudo chown -R www-data:www-data ui/failed_jobs.php
sudo chmod 644 classes/api/error_handler.php
sudo chmod 644 ui/failed_jobs.php
```

### Step 2: Run Database Upgrade

```bash
# Run upgrade to create failed_jobs table
sudo -u www-data php admin/cli/upgrade.php --non-interactive

# Verify table was created
mysql -u moodle_user -p moodle_db -e "DESCRIBE mdl_manireports_failed_jobs;"
```

### Step 3: Clear Caches

```bash
sudo -u www-data php admin/cli/purge_caches.php
```

### Step 4: Verify Failed Jobs UI

1. Log in as admin
2. Go to **Site Administration → Plugins → ManiReports → Failed Jobs**
3. Verify page loads without errors
4. Check system health status

## Testing

### Test 1: Retry Logic

```bash
cat > /tmp/test_retry.php << 'EOF'
<?php
require_once('/var/www/html/moodle/config.php');
require_login();

use \local_manireports\api\error_handler;

$attempts = 0;

try {
    $result = error_handler::execute_with_retry(function() use (&$attempts) {
        $attempts++;
        echo "Attempt $attempts\n";
        
        if ($attempts < 3) {
            throw new Exception("Simulated failure");
        }
        
        return "Success!";
    }, 3, 'Test operation');
    
    echo "Result: $result\n";
    echo "Total attempts: $attempts\n";
} catch (Exception $e) {
    echo "Failed after $attempts attempts: " . $e->getMessage() . "\n";
}
EOF

sudo -u www-data php /tmp/test_retry.php
```

### Test 2: Error Logging

```bash
cat > /tmp/test_error_logging.php << 'EOF'
<?php
require_once('/var/www/html/moodle/config.php');
require_login();

use \local_manireports\api\error_handler;

try {
    throw new Exception("Test error for logging");
} catch (Exception $e) {
    error_handler::log_error($e, 'Test context', [
        'test_data' => 'value',
        'user_id' => 2,
    ]);
    echo "Error logged successfully\n";
}

// Check audit log
$logs = $DB->get_records('manireports_audit_logs', ['action' => 'error'], 'timecreated DESC', '*', 0, 1);
if (!empty($logs)) {
    $log = reset($logs);
    echo "Found error log: " . $log->details . "\n";
}
EOF

sudo -u www-data php /tmp/test_error_logging.php
```

### Test 3: Failed Job Recording

```bash
cat > /tmp/test_failed_job.php << 'EOF'
<?php
require_once('/var/www/html/moodle/config.php');
require_login();

use \local_manireports\api\error_handler;

// Simulate task failure
$exception = new Exception("Test task failure");
error_handler::handle_task_failure(
    '\\local_manireports\\task\\test_task',
    $exception,
    ['test' => 'context']
);

echo "Failed job recorded\n";

// Check failed jobs
$jobs = $DB->get_records('manireports_failed_jobs', null, 'timefailed DESC', '*', 0, 1);
if (!empty($jobs)) {
    $job = reset($jobs);
    echo "Found failed job: " . $job->taskname . "\n";
    echo "Error: " . $job->error . "\n";
}
EOF

sudo -u www-data php /tmp/test_failed_job.php
```

### Test 4: Timeout Handling

```bash
cat > /tmp/test_timeout.php << 'EOF'
<?php
require_once('/var/www/html/moodle/config.php');
require_login();

use \local_manireports\api\error_handler;

try {
    $result = error_handler::execute_with_timeout(function() {
        sleep(2);
        return "Completed";
    }, 5, 'Test timeout');
    
    echo "✓ Operation completed within timeout: $result\n";
} catch (Exception $e) {
    echo "✗ Operation timed out: " . $e->getMessage() . "\n";
}

try {
    $result = error_handler::execute_with_timeout(function() {
        sleep(10);
        return "Should timeout";
    }, 3, 'Test timeout');
    
    echo "✗ Should have timed out\n";
} catch (Exception $e) {
    echo "✓ Correctly timed out: " . $e->getMessage() . "\n";
}
EOF

sudo -u www-data php /tmp/test_timeout.php
```

### Test 5: System Health Check

```bash
cat > /tmp/test_health.php << 'EOF'
<?php
require_once('/var/www/html/moodle/config.php');
require_login();

use \local_manireports\api\error_handler;

$health = error_handler::check_system_health();

echo "System Status: " . $health['status'] . "\n\n";

if (!empty($health['checks'])) {
    echo "Checks:\n";
    foreach ($health['checks'] as $check) {
        echo "  ✓ $check\n";
    }
}

if (!empty($health['warnings'])) {
    echo "\nWarnings:\n";
    foreach ($health['warnings'] as $warning) {
        echo "  ⚠ $warning\n";
    }
}

if (!empty($health['errors'])) {
    echo "\nErrors:\n";
    foreach ($health['errors'] as $error) {
        echo "  ✗ $error\n";
    }
}
EOF

sudo -u www-data php /tmp/test_health.php
```

### Test 6: Email Alerts

```bash
# Create multiple failures to trigger alert
cat > /tmp/test_alert.php << 'EOF'
<?php
require_once('/var/www/html/moodle/config.php');
require_login();

use \local_manireports\api\error_handler;

// Create 3 failures
for ($i = 1; $i <= 3; $i++) {
    $exception = new Exception("Test failure $i");
    error_handler::handle_task_failure(
        '\\local_manireports\\task\\test_alert_task',
        $exception
    );
    echo "Created failure $i\n";
}

echo "Alert should be sent to administrators\n";
echo "Check admin email inbox\n";
EOF

sudo -u www-data php /tmp/test_alert.php
```

## Integration with Scheduled Tasks

To integrate error handling into scheduled tasks:

```php
namespace local_manireports\task;

class my_task extends \core\task\scheduled_task {
    
    public function get_name() {
        return get_string('mytask', 'local_manireports');
    }
    
    public function execute() {
        $handler = new \local_manireports\api\error_handler();
        
        try {
            // Use retry logic for critical operations
            $handler->execute_with_retry(function() {
                $this->process_data();
            }, 3, 'My task data processing');
            
        } catch (\Exception $e) {
            // Log and record failure
            $handler->handle_task_failure(
                get_class($this),
                $e,
                ['context' => 'additional info']
            );
            
            // Re-throw to mark task as failed
            throw $e;
        }
    }
    
    private function process_data() {
        // Task logic here
    }
}
```

## Monitoring and Maintenance

### Regular Checks

```bash
# Check failed jobs count
mysql -u moodle_user -p moodle_db -e "
SELECT COUNT(*) as failed_jobs FROM mdl_manireports_failed_jobs;
"

# Check recent failures
mysql -u moodle_user -p moodle_db -e "
SELECT taskname, COUNT(*) as count 
FROM mdl_manireports_failed_jobs 
WHERE timefailed > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 24 HOUR))
GROUP BY taskname;
"

# Check system health via CLI
cat > /tmp/check_health.php << 'EOF'
<?php
require_once('/var/www/html/moodle/config.php');
use \local_manireports\api\error_handler;
$health = error_handler::check_system_health();
echo json_encode($health, JSON_PRETTY_PRINT) . "\n";
EOF

sudo -u www-data php /tmp/check_health.php
```

### Cleanup Old Jobs

```bash
# Manual cleanup
cat > /tmp/cleanup_jobs.php << 'EOF'
<?php
require_once('/var/www/html/moodle/config.php');
use \local_manireports\api\error_handler;
$count = error_handler::clear_old_failed_jobs(30);
echo "Cleared $count old jobs\n";
EOF

sudo -u www-data php /tmp/cleanup_jobs.php
```

## Troubleshooting

### Issue: Too Many Failed Jobs

**Solution:**
1. Review failed jobs in admin UI
2. Identify common failure patterns
3. Fix underlying issues
4. Retry failed jobs
5. Clear old jobs

### Issue: Email Alerts Not Sending

**Solution:**
```bash
# Check Moodle email configuration
php admin/cli/test_outgoing_mail_configuration.php

# Verify admin users exist
mysql -u moodle_user -p moodle_db -e "
SELECT id, username, email FROM mdl_user WHERE id IN (
    SELECT userid FROM mdl_role_assignments WHERE roleid = 1
);
"
```

### Issue: System Health Shows Critical

**Solution:**
1. Check database connectivity
2. Review disk space
3. Clear old data
4. Restart services if needed

## Success Criteria

Task 28 is complete when:

- ✅ Error handler class implemented
- ✅ Retry logic with exponential backoff working
- ✅ Comprehensive error logging implemented
- ✅ Failed jobs table created
- ✅ Admin UI for failed jobs functional
- ✅ Email alerts sending
- ✅ Timeout handling implemented
- ✅ System health monitoring working
- ✅ All tests pass
- ✅ Documentation complete

## Next Steps

1. Monitor failed jobs regularly
2. Review error logs for patterns
3. Adjust retry counts based on failure types
4. Set up automated health checks
5. Proceed to Task 29: Language Strings

---

**Task 28 Status**: ✅ COMPLETE

**Deployment Date**: [To be filled]

**Deployed By**: [To be filled]

**Health Check Results**: [To be filled]
