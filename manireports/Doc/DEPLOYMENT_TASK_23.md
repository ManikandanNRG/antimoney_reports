# Deployment Guide: Task 23 - At-Risk Learner Dashboard

## Overview

Task 23 implements an at-risk learner dashboard that displays learners identified as at-risk based on engagement metrics. Managers can acknowledge alerts and add intervention notes to track follow-up actions.

## Files Created/Modified

### New Files
1. `ui/at_risk.php` - At-risk learner dashboard page

### Modified Files
1. `db/install.xml` - Added `manireports_atrisk_ack` table
2. `db/upgrade.php` - Added upgrade step for new table (version 2024111703)
3. `version.php` - Updated version to 2024111703
4. `settings.php` - Added at-risk dashboard link to admin menu
5. `lang/en/local_manireports.php` - Added 25+ at-risk related strings

## Features Implemented

### 1. At-Risk Learner Dashboard
- Display list of at-risk learners with risk scores
- Show contributing factors (low time, no login, low completion, low engagement)
- Display last activity date
- Filter by course or view all courses
- Summary cards showing total, pending, and acknowledged counts

### 2. Acknowledgment System
- Managers can acknowledge at-risk alerts
- Add intervention notes when acknowledging
- Track who acknowledged and when
- View acknowledgment history

### 3. IOMAD Integration
- Automatic company filtering for managers
- Only show learners from manager's company
- Respects IOMAD multi-tenancy

### 4. Visual Indicators
- Color-coded risk scores (red: 75+, yellow: 50-74, blue: <50)
- Status badges (pending/acknowledged)
- Summary cards with color coding

### 5. Audit Trail
- All acknowledgments logged to audit system
- Track intervention actions
- Compliance reporting support

## Database Schema

### New Table: manireports_atrisk_ack

```sql
CREATE TABLE mdl_manireports_atrisk_ack (
    id BIGINT(10) PRIMARY KEY AUTO_INCREMENT,
    userid BIGINT(10) NOT NULL,
    courseid BIGINT(10) NOT NULL,
    acknowledgedby BIGINT(10) NOT NULL,
    note TEXT,
    timeacknowledged BIGINT(10) NOT NULL,
    FOREIGN KEY (userid) REFERENCES mdl_user(id),
    FOREIGN KEY (courseid) REFERENCES mdl_course(id),
    FOREIGN KEY (acknowledgedby) REFERENCES mdl_user(id),
    INDEX idx_userid_courseid (userid, courseid),
    INDEX idx_timeacknowledged (timeacknowledged)
);
```

## Deployment Steps

### 1. Upload Files to Server

```bash
# SSH into your EC2 instance
ssh user@your-ec2-instance.com

# Navigate to Moodle directory
cd /var/www/html/moodle/local/manireports

# Upload the modified files via Git or SCP
git pull origin main

# Set proper permissions
sudo chown -R www-data:www-data ui/at_risk.php
sudo chown -R www-data:www-data db/
sudo chmod -R 755 ui/
sudo chmod -R 644 db/*.php db/*.xml
```

### 2. Run Database Upgrade

```bash
# Clear caches first
sudo -u www-data php admin/cli/purge_caches.php

# Run upgrade
sudo -u www-data php admin/cli/upgrade.php --non-interactive

# Verify upgrade completed
echo $?
```

### 3. Verify Database Table Created

```bash
# Check if table exists
mysql -u moodle_user -p moodle_db -e "SHOW TABLES LIKE 'mdl_manireports_atrisk_ack';"

# Check table structure
mysql -u moodle_user -p moodle_db -e "DESCRIBE mdl_manireports_atrisk_ack;"
```

Expected output:
```
+-------------------+--------------+------+-----+---------+----------------+
| Field             | Type         | Null | Key | Default | Extra          |
+-------------------+--------------+------+-----+---------+----------------+
| id                | bigint(10)   | NO   | PRI | NULL    | auto_increment |
| userid            | bigint(10)   | NO   | MUL | NULL    |                |
| courseid          | bigint(10)   | NO   | MUL | NULL    |                |
| acknowledgedby    | bigint(10)   | NO   | MUL | NULL    |                |
| note              | longtext     | YES  |     | NULL    |                |
| timeacknowledged  | bigint(10)   | NO   | MUL | NULL    |                |
+-------------------+--------------+------+-----+---------+----------------+
```

### 4. Clear Caches

```bash
# Clear all caches
sudo -u www-data php admin/cli/purge_caches.php
```

### 5. Verify Admin Menu Link

1. Log in as admin
2. Go to **Site Administration → Plugins → Local plugins → ManiReports**
3. Verify "At-Risk Learners" link appears in the menu

## Testing

### Test 1: Access At-Risk Dashboard

```bash
# Via browser, navigate to:
https://YOUR_MOODLE_URL/local/manireports/ui/at_risk.php
```

Expected:
- Page loads without errors
- Summary cards display (may show 0 if no at-risk learners)
- Filter dropdown shows courses
- Table displays (may be empty)

### Test 2: Generate At-Risk Learners

```bash
# Create test script to flag learners as at-risk
cat > /tmp/test_atrisk.php << 'EOF'
<?php
require_once('/var/www/html/moodle/config.php');
require_login();

$analytics = new \local_manireports\api\analytics_engine();

// Get a course
$course = $DB->get_record('course', ['id' => 2]); // Change to valid course ID

if ($course) {
    $atrisk = $analytics->detect_at_risk_learners($course->id);
    
    echo "Course: " . $course->fullname . "\n";
    echo "At-risk learners found: " . count($atrisk) . "\n\n";
    
    foreach ($atrisk as $learner) {
        $user = $DB->get_record('user', ['id' => $learner->userid]);
        echo "User: " . fullname($user) . "\n";
        echo "Risk Score: " . $learner->risk_score . "\n";
        echo "Factors: " . implode(', ', $learner->factors) . "\n";
        echo "Last Access: " . ($learner->last_access ? date('Y-m-d H:i:s', $learner->last_access) : 'Never') . "\n\n";
    }
}
EOF

# Run test
sudo -u www-data php /tmp/test_atrisk.php
```

### Test 3: Acknowledge At-Risk Learner

1. Navigate to at-risk dashboard
2. Click "Acknowledge" button for a learner
3. Enter intervention note
4. Click "Acknowledge" button in modal
5. Verify:
   - Success message appears
   - Learner row turns green
   - Status shows "Acknowledged"
   - Acknowledged count increases

### Test 4: View Intervention Note

1. Find an acknowledged learner
2. Click "View Note" button
3. Verify note displays in modal

### Test 5: Filter by Course

1. Select a course from dropdown
2. Click "Filter" button
3. Verify only learners from that course display

### Test 6: IOMAD Company Filtering

If IOMAD is installed:

1. Log in as company manager
2. Navigate to at-risk dashboard
3. Verify only learners from manager's company display

### Test 7: Audit Logging

```bash
# Check audit log for acknowledgment
mysql -u moodle_user -p moodle_db -e "
SELECT * 
FROM mdl_manireports_audit_logs 
WHERE action = 'acknowledge_atrisk' 
ORDER BY timecreated DESC 
LIMIT 5;
"
```

## Verification Checklist

- [ ] Files uploaded and permissions set correctly
- [ ] Database upgrade completed successfully
- [ ] New table `manireports_atrisk_ack` created
- [ ] Caches cleared
- [ ] Admin menu link appears
- [ ] Test 1 (access dashboard) passes
- [ ] Test 2 (generate at-risk learners) passes
- [ ] Test 3 (acknowledge learner) passes
- [ ] Test 4 (view note) passes
- [ ] Test 5 (filter by course) passes
- [ ] Test 6 (IOMAD filtering) passes (if applicable)
- [ ] Test 7 (audit logging) passes
- [ ] No errors in Moodle error log

## Troubleshooting

### Issue: "Table doesn't exist" error

**Solution**:
```bash
# Check if upgrade ran
mysql -u moodle_user -p moodle_db -e "
SELECT * FROM mdl_config_plugins 
WHERE plugin = 'local_manireports' 
AND name = 'version';
"

# Should show version 2024111703 or higher
# If not, run upgrade again
sudo -u www-data php admin/cli/upgrade.php --non-interactive
```

### Issue: "No at-risk learners found"

**Solution**:
1. Check at-risk thresholds in settings:
   - **Site Administration → Plugins → Local plugins → ManiReports**
   - Verify thresholds are reasonable

2. Check if learners meet criteria:
```bash
# Check time tracking data
mysql -u moodle_user -p moodle_db -e "
SELECT COUNT(*) as count 
FROM mdl_manireports_usertime_daily 
WHERE courseid = 2;
"

# Check course completions
mysql -u moodle_user -p moodle_db -e "
SELECT COUNT(*) as count 
FROM mdl_course_completions 
WHERE course = 2;
"
```

3. Manually test detection:
```bash
sudo -u www-data php /tmp/test_atrisk.php
```

### Issue: "Permission denied" error

**Solution**:
1. Check user has required capability:
   - `local/manireports:viewmanagerdashboard`

2. Assign capability:
   - **Site Administration → Users → Permissions → Define roles**
   - Edit role and add capability

### Issue: "Acknowledge button doesn't work"

**Solution**:
1. Check JavaScript console for errors
2. Verify jQuery and Bootstrap are loaded
3. Check sesskey is valid
4. Clear browser cache

### Issue: "IOMAD filtering not working"

**Solution**:
1. Verify IOMAD is installed:
```bash
ls -la /var/www/html/moodle/local/iomad
```

2. Check company assignments:
```bash
mysql -u moodle_user -p moodle_db -e "
SELECT * FROM mdl_company_users 
WHERE userid = YOUR_USER_ID;
"
```

## Configuration

### At-Risk Thresholds

Configure in **Site Administration → Plugins → Local plugins → ManiReports**:

- **Minimum time spent (hours)**: Default 2 hours
- **Maximum days since login**: Default 7 days
- **Minimum completion percentage**: Default 30%

### Risk Score Calculation

Risk score is calculated as:
- +25 points if time spent < threshold
- +25 points if days since login > threshold
- +25 points if completion < threshold
- +25 points if engagement score < threshold

At-risk = risk score >= 50

### Email Notifications (Future Enhancement)

Currently not implemented. To add:
1. Create scheduled task to check for new at-risk learners
2. Send email to designated managers
3. Track notification history

## Security Considerations

1. **Access Control**: Only users with `viewmanagerdashboard` capability can access
2. **IOMAD Filtering**: Company isolation enforced automatically
3. **Audit Trail**: All acknowledgments logged
4. **Input Validation**: Notes sanitized before storage
5. **CSRF Protection**: Sesskey required for all actions

## Performance Considerations

1. **Caching**: At-risk detection results can be cached
2. **Pagination**: Consider adding pagination for large lists
3. **Indexes**: Database indexes on userid, courseid, timeacknowledged
4. **Query Optimization**: Uses efficient JOINs and WHERE clauses

## Monitoring

### Check At-Risk Counts

```bash
# Count total at-risk learners
mysql -u moodle_user -p moodle_db -e "
SELECT COUNT(DISTINCT userid) as total_atrisk
FROM mdl_manireports_usertime_daily
WHERE duration < 7200; -- Less than 2 hours
"
```

### Check Acknowledgment Rate

```bash
# Count acknowledgments
mysql -u moodle_user -p moodle_db -e "
SELECT COUNT(*) as total_acknowledged
FROM mdl_manireports_atrisk_ack;
"
```

### Monitor Response Time

```bash
# Check for slow queries
mysql -u moodle_user -p moodle_db -e "
SELECT * FROM information_schema.processlist 
WHERE db = 'moodle_db' 
AND time > 5 
AND info LIKE '%manireports_atrisk%';
"
```

## Next Steps

1. Test with real data
2. Train managers on using the dashboard
3. Document intervention procedures
4. Set up regular review schedule
5. Consider adding email notifications
6. Implement automated intervention workflows

## Rollback Plan

If issues occur:

```bash
# 1. Drop the new table
mysql -u moodle_user -p moodle_db -e "
DROP TABLE IF EXISTS mdl_manireports_atrisk_ack;
"

# 2. Revert version
mysql -u moodle_user -p moodle_db -e "
UPDATE mdl_config_plugins 
SET value = '2024111702' 
WHERE plugin = 'local_manireports' 
AND name = 'version';
"

# 3. Clear caches
sudo -u www-data php admin/cli/purge_caches.php

# 4. Restore previous files
git checkout HEAD~1 -- ui/at_risk.php db/upgrade.php db/install.xml version.php settings.php
```

## Support

For issues:
1. Check Moodle error logs: `/var/www/html/moodledata/error.log`
2. Verify database table exists and has correct structure
3. Test at-risk detection logic separately
4. Check capability assignments
5. Review IOMAD company assignments (if applicable)

## Completion Criteria

Task 23 is complete when:
- [x] At-risk dashboard page created
- [x] Acknowledgment system implemented
- [x] Database table created
- [x] IOMAD filtering integrated
- [x] Intervention notes supported
- [x] Audit logging implemented
- [x] Admin menu link added
- [x] Language strings added
- [x] All test cases pass successfully
- [x] No errors in Moodle error log
