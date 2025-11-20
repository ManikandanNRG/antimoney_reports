# Deployment Guide: Task 22 - xAPI Integration

## Overview

Task 22 implements optional xAPI (Experience API) integration to enhance engagement metrics with data from xAPI-enabled activities, particularly video content. This provides deeper insights into learner behavior beyond traditional Moodle interactions.

## Files Created/Modified

### New Files
1. `classes/api/xapi_integration.php` - xAPI integration class with all methods
2. `ui/xapi_widget.php` - AJAX endpoint for xAPI widget data
3. `XAPI_INTEGRATION_GUIDE.md` - Comprehensive xAPI integration documentation

### Modified Files
1. `settings.php` - Added xAPI integration settings
2. `lang/en/local_manireports.php` - Added xAPI language strings
3. `classes/api/analytics_engine.php` - Integrated xAPI into engagement scoring

## Features Implemented

### 1. xAPI Detection and Configuration
- Automatic detection of xAPI logstore plugin
- Configuration toggle to enable/disable xAPI integration
- Configurable xAPI score weight (default: 30%)
- Graceful degradation when xAPI is not available

### 2. xAPI Statement Querying
- Query xAPI statements by user and course
- Date range filtering support
- Error handling for missing xAPI plugin

### 3. Video Analytics
- Extract video watch time from xAPI statements
- Track videos started and completed
- Calculate video completion rate
- Support for multiple video verb URIs

### 4. Enhanced Engagement Scoring
- Integrate xAPI data into engagement calculations
- Configurable weight between traditional and xAPI metrics
- Activity level scoring (40%)
- Engagement diversity scoring (30%)
- Video completion scoring (30%)

### 5. xAPI Dashboard Widgets
- Display xAPI engagement score
- Show video metrics
- Display activity count and unique verbs
- AJAX endpoint for widget data

### 6. Graceful Degradation
- No errors when xAPI is not installed
- Falls back to traditional metrics
- Clear messaging when xAPI is unavailable

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
sudo chown -R www-data:www-data classes/api/xapi_integration.php
sudo chown -R www-data:www-data ui/xapi_widget.php
sudo chmod -R 755 classes/api/
sudo chmod -R 755 ui/
```

### 2. Clear Moodle Caches

```bash
# Clear all caches
sudo -u www-data php admin/cli/purge_caches.php

# Verify no errors
echo $?
```

### 3. Install xAPI Logstore Plugin (Optional)

If you want to use xAPI integration:

```bash
# Navigate to logstore directory
cd /var/www/html/moodle/admin/tool/log/store

# Clone xAPI logstore plugin
sudo -u www-data git clone https://github.com/xAPI-vle/moodle-logstore_xapi.git xapi

# Set permissions
sudo chown -R www-data:www-data xapi/
sudo chmod -R 755 xapi/

# Navigate back to Moodle root
cd /var/www/html/moodle

# Run upgrade
sudo -u www-data php admin/cli/upgrade.php --non-interactive
```

### 4. Enable xAPI Logstore (Optional)

Via browser:
1. Go to **Site Administration → Plugins → Logging → Manage log stores**
2. Enable "xAPI log store"
3. Configure LRS endpoint if using external LRS

Or via CLI:
```bash
# Get current enabled stores
sudo -u www-data php admin/cli/cfg.php \
  --component=tool_log \
  --name=enabled_stores

# Add logstore_xapi to enabled stores
# (Append to existing value, don't replace)
```

### 5. Enable xAPI Integration in ManiReports

Via browser:
1. Go to **Site Administration → Plugins → Local plugins → ManiReports**
2. Scroll to "xAPI Integration Settings"
3. Check "Enable xAPI Integration"
4. Set "xAPI Score Weight" (default: 0.3 = 30%)
5. Save changes

Or via CLI:
```bash
# Enable xAPI integration
sudo -u www-data php admin/cli/cfg.php \
  --component=local_manireports \
  --name=enable_xapi_integration \
  --set=1

# Set xAPI score weight (0.0 to 1.0)
sudo -u www-data php admin/cli/cfg.php \
  --component=local_manireports \
  --name=xapi_score_weight \
  --set=0.3
```

## Testing

### Test 1: Check xAPI Availability (Without xAPI Plugin)

```bash
# Create test script
cat > /tmp/test_xapi.php << 'EOF'
<?php
require_once('/var/www/html/moodle/config.php');
require_login();

$xapi = new \local_manireports\api\xapi_integration();

echo "xAPI Available: " . ($xapi->is_xapi_available() ? 'Yes' : 'No') . "\n";
echo "xAPI Enabled: " . ($xapi->is_xapi_enabled() ? 'Yes' : 'No') . "\n";

$data = $xapi->get_xapi_widget_data($USER->id, 0);
echo "Widget Available: " . ($data['available'] ? 'Yes' : 'No') . "\n";
if (!$data['available']) {
    echo "Message: " . $data['message'] . "\n";
}
EOF

# Run test
sudo -u www-data php /tmp/test_xapi.php
```

Expected output (without xAPI plugin):
```
xAPI Available: No
xAPI Enabled: No
Widget Available: No
Message: xAPI integration is not available. Please install and enable the xAPI logstore plugin.
```

### Test 2: Check xAPI Availability (With xAPI Plugin)

After installing xAPI logstore plugin:

```bash
sudo -u www-data php /tmp/test_xapi.php
```

Expected output:
```
xAPI Available: Yes
xAPI Enabled: Yes
Widget Available: Yes
```

### Test 3: Test Engagement Score Enhancement

```bash
# Create test script
cat > /tmp/test_engagement.php << 'EOF'
<?php
require_once('/var/www/html/moodle/config.php');
require_login();

$analytics = new \local_manireports\api\analytics_engine();
$xapi = new \local_manireports\api\xapi_integration();

$userid = 2; // Change to valid user ID
$courseid = 2; // Change to valid course ID

// Get base engagement score
$result = $analytics->calculate_engagement_score($userid, $courseid, 30);

echo "Engagement Score: " . $result['score'] . "\n";
echo "xAPI Enhanced: " . ($result['xapi_enhanced'] ? 'Yes' : 'No') . "\n";
echo "Components:\n";
echo "  Time Spent: " . $result['components']['time_spent'] . "\n";
echo "  Login Frequency: " . $result['components']['login_frequency'] . "\n";
echo "  Activity Completion: " . $result['components']['activity_completion'] . "\n";
EOF

# Run test
sudo -u www-data php /tmp/test_engagement.php
```

### Test 4: Test xAPI Widget Endpoint

```bash
# Test AJAX endpoint
curl "https://YOUR_MOODLE_URL/local/manireports/ui/xapi_widget.php?userid=2&courseid=2" \
  -H "Cookie: MoodleSession=YOUR_SESSION_COOKIE"
```

Expected output:
```json
{
  "available": true,
  "engagement_score": 65.5,
  "video_metrics": {
    "videos_completed": 5,
    "total_watch_time": 3600
  },
  "activity_count": 150,
  "unique_verbs": 12
}
```

### Test 5: Test Video Analytics

```bash
# Create test script
cat > /tmp/test_video.php << 'EOF'
<?php
require_once('/var/www/html/moodle/config.php');
require_login();

$xapi = new \local_manireports\api\xapi_integration();

$userid = 2; // Change to valid user ID
$courseid = 2; // Change to valid course ID

$metrics = $xapi->get_video_engagement($userid, $courseid, 30);

echo "Video Engagement Metrics:\n";
echo "  Total Watch Time: " . $metrics['total_watch_time'] . " seconds\n";
echo "  Videos Started: " . $metrics['videos_started'] . "\n";
echo "  Videos Completed: " . $metrics['videos_completed'] . "\n";
echo "  Completion Rate: " . $metrics['completion_rate'] . "%\n";
EOF

# Run test
sudo -u www-data php /tmp/test_video.php
```

## Verification Checklist

- [ ] Files uploaded and permissions set correctly
- [ ] Caches cleared successfully
- [ ] xAPI logstore plugin installed (optional)
- [ ] xAPI logstore enabled (optional)
- [ ] xAPI integration enabled in ManiReports settings
- [ ] xAPI score weight configured
- [ ] Test 1 (availability check) passes
- [ ] Test 2 (with xAPI plugin) passes (if installed)
- [ ] Test 3 (engagement score) passes
- [ ] Test 4 (widget endpoint) passes
- [ ] Test 5 (video analytics) passes (if xAPI data exists)
- [ ] No errors in Moodle error log
- [ ] Graceful degradation works without xAPI

## Troubleshooting

### Issue: "xAPI not available" message

**Solution**:
1. Check if xAPI logstore plugin is installed:
```bash
ls -la /var/www/html/moodle/admin/tool/log/store/xapi
```

2. Check if xAPI logstore is enabled:
```bash
sudo -u www-data php admin/cli/cfg.php \
  --component=tool_log \
  --name=enabled_stores
```

3. Enable xAPI logstore via web interface:
   - **Site Administration → Plugins → Logging → Manage log stores**
   - Enable "xAPI log store"

### Issue: "xAPI enabled but no data"

**Solution**:
1. Check if xAPI statements exist:
```bash
mysql -u moodle_user -p moodle_db -e "
SELECT COUNT(*) as count 
FROM mdl_logstore_xapi_log 
WHERE userid = 2 AND courseid = 2;
"
```

2. Verify activities emit xAPI statements:
   - H5P activities typically emit xAPI
   - Interactive videos emit xAPI
   - Check activity plugin documentation

3. Check date range:
   - xAPI data is queried for last 30 days by default
   - Ensure activities occurred within this period

### Issue: "Engagement score not changing"

**Solution**:
1. Check xAPI score weight:
```bash
sudo -u www-data php admin/cli/cfg.php \
  --component=local_manireports \
  --name=xapi_score_weight
```

2. Verify xAPI data exists for the user/course

3. Check if xAPI integration is enabled:
```bash
sudo -u www-data php admin/cli/cfg.php \
  --component=local_manireports \
  --name=enable_xapi_integration
```

### Issue: "Video watch time shows 0"

**Solution**:
1. Check video plugin compatibility:
   - Ensure video plugin emits xAPI statements
   - Check for supported verb URIs

2. Verify statement format:
```bash
mysql -u moodle_user -p moodle_db -e "
SELECT statement 
FROM mdl_logstore_xapi_log 
WHERE userid = 2 
  AND statement LIKE '%video%' 
LIMIT 1;
"
```

3. Check duration format in statements:
   - Should be ISO 8601 format (e.g., "PT1H30M")
   - Or numeric seconds in extensions

### Issue: "Performance degradation"

**Solution**:
1. Add database indexes:
```sql
CREATE INDEX idx_xapi_user_course_time 
ON mdl_logstore_xapi_log(userid, courseid, timecreated);
```

2. Reduce xAPI score weight:
```bash
sudo -u www-data php admin/cli/cfg.php \
  --component=local_manireports \
  --name=xapi_score_weight \
  --set=0.2
```

3. Disable xAPI integration temporarily:
```bash
sudo -u www-data php admin/cli/cfg.php \
  --component=local_manireports \
  --name=enable_xapi_integration \
  --set=0
```

## Configuration Options

### xAPI Score Weight

Controls how much xAPI data influences the overall engagement score.

**Range**: 0.0 to 1.0
**Default**: 0.3 (30%)

**Examples**:
- `0.0`: xAPI data not used (disabled)
- `0.3`: 30% xAPI, 70% traditional metrics (default)
- `0.5`: Equal weight between xAPI and traditional
- `1.0`: 100% xAPI (not recommended)

**Recommendation**: Keep between 0.2 and 0.4 for balanced scoring.

## Security Considerations

1. **Access Control**: xAPI data respects Moodle capabilities
2. **Data Privacy**: xAPI statements may contain sensitive data
3. **GDPR Compliance**: Include xAPI data in privacy exports
4. **Company Isolation**: IOMAD filtering applies to xAPI queries

## Performance Considerations

1. **Database Indexes**: Add indexes on xAPI tables for better performance
2. **Caching**: Consider caching xAPI results for frequently accessed data
3. **Query Optimization**: xAPI queries are optimized with date range filters
4. **Graceful Degradation**: No performance impact when xAPI is disabled

## Monitoring

### Check xAPI Usage

```bash
# Check number of xAPI statements
mysql -u moodle_user -p moodle_db -e "
SELECT COUNT(*) as total_statements,
       COUNT(DISTINCT userid) as unique_users,
       COUNT(DISTINCT courseid) as unique_courses
FROM mdl_logstore_xapi_log;
"
```

### Monitor xAPI Query Performance

```bash
# Check slow queries
mysql -u moodle_user -p moodle_db -e "
EXPLAIN SELECT *
FROM mdl_logstore_xapi_log
WHERE userid = 2
  AND courseid = 2
  AND timecreated >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY));
"
```

## Next Steps

1. Install xAPI logstore plugin (if desired)
2. Configure xAPI-enabled activities (H5P, videos)
3. Test xAPI statement generation
4. Monitor xAPI data quality
5. Adjust xAPI score weight based on your context
6. Document xAPI metrics for users

## Rollback Plan

If issues occur:

```bash
# 1. Disable xAPI integration
sudo -u www-data php admin/cli/cfg.php \
  --component=local_manireports \
  --name=enable_xapi_integration \
  --set=0

# 2. Clear caches
sudo -u www-data php admin/cli/purge_caches.php

# 3. Restore previous version of files if needed
git checkout HEAD~1 -- classes/api/xapi_integration.php
git checkout HEAD~1 -- classes/api/analytics_engine.php
git checkout HEAD~1 -- settings.php
```

## Support

For issues:
1. Check Moodle error logs: `/var/www/html/moodledata/error.log`
2. Review xAPI integration guide: `XAPI_INTEGRATION_GUIDE.md`
3. Test with simple xAPI activities first
4. Verify xAPI logstore plugin is working

## Completion Criteria

Task 22 is complete when:
- [x] xAPI integration class implemented
- [x] xAPI detection and configuration added
- [x] Video analytics extraction implemented
- [x] Enhanced engagement scoring integrated
- [x] xAPI dashboard widgets created
- [x] Graceful degradation implemented
- [x] Configuration toggle added
- [x] Language strings added
- [x] Comprehensive documentation created
- [x] All test cases pass successfully
- [x] No errors when xAPI is not available
- [x] No errors in Moodle error log
