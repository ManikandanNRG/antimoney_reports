# xAPI Integration Guide for ManiReports

## Overview

ManiReports includes optional integration with xAPI (Experience API) to enhance engagement metrics with data from xAPI-enabled activities, particularly video content. This integration provides deeper insights into learner behavior beyond traditional Moodle interactions.

## What is xAPI?

xAPI (Experience API, also known as Tin Can API) is a specification for learning technology that makes it possible to collect data about learning experiences. It tracks and stores learning activities in a Learning Record Store (LRS).

## Prerequisites

### Required Plugins

1. **xAPI Logstore Plugin**: `logstore_xapi`
   - Available from: https://moodle.org/plugins/logstore_xapi
   - Or: https://github.com/xAPI-vle/moodle-logstore_xapi

2. **xAPI-enabled Activity Plugins** (optional but recommended):
   - H5P activities with xAPI support
   - Interactive Video plugins
   - SCORM packages with xAPI statements
   - Custom activities that emit xAPI statements

### Installation Steps

1. **Install xAPI Logstore Plugin**:
   ```bash
   cd /var/www/html/moodle/admin/tool/log/store
   git clone https://github.com/xAPI-vle/moodle-logstore_xapi.git xapi
   
   # Set permissions
   sudo chown -R www-data:www-data xapi/
   sudo chmod -R 755 xapi/
   ```

2. **Enable the Plugin**:
   - Go to **Site Administration → Plugins → Logging → Manage log stores**
   - Enable "xAPI log store"
   - Configure LRS endpoint (if using external LRS)

3. **Verify Installation**:
   ```bash
   # Check if xAPI tables exist
   mysql -u moodle_user -p moodle_db -e "SHOW TABLES LIKE '%logstore_xapi%';"
   ```

## Enabling xAPI Integration in ManiReports

### Via Web Interface

1. Go to **Site Administration → Plugins → Local plugins → ManiReports**
2. Scroll to "xAPI Integration Settings"
3. Check "Enable xAPI Integration"
4. Set "xAPI Score Weight" (default: 0.3 = 30%)
5. Save changes

### Via CLI

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

## Features

### 1. Enhanced Engagement Scoring

xAPI data is integrated into the engagement score calculation:

**Without xAPI**:
- Time spent: 40%
- Login frequency: 30%
- Activity completion: 30%

**With xAPI** (default weight 0.3):
- Base score (time + login + completion): 70%
- xAPI score: 30%

**xAPI Score Components**:
- Activity level (40%): Number of xAPI statements
- Engagement diversity (30%): Variety of activity types (verbs)
- Video completion (30%): Video completion rate

### 2. Video Analytics

Tracks video engagement metrics:
- Total watch time
- Videos started
- Videos completed
- Completion rate

**Supported Video Verbs**:
- `http://adlnet.gov/expapi/verbs/played`
- `http://adlnet.gov/expapi/verbs/completed`
- `https://w3id.org/xapi/video/verbs/played`
- `https://w3id.org/xapi/video/verbs/completed`

### 3. xAPI Dashboard Widgets

Displays xAPI-specific metrics:
- xAPI engagement score
- Video watch time
- Activity count
- Unique activity types

### 4. Graceful Degradation

If xAPI is not available or disabled:
- No errors occur
- Standard engagement metrics are used
- xAPI widgets show "not available" message

## API Usage

### Check xAPI Availability

```php
$xapi = new \local_manireports\api\xapi_integration();

if ($xapi->is_xapi_available()) {
    echo "xAPI logstore is installed and enabled";
}

if ($xapi->is_xapi_enabled()) {
    echo "xAPI integration is enabled in ManiReports settings";
}
```

### Get xAPI Statements

```php
$userid = 123;
$courseid = 456;
$startdate = strtotime('-30 days');
$enddate = time();

$statements = $xapi->get_xapi_statements($userid, $courseid, $startdate, $enddate);

foreach ($statements as $statement) {
    $data = json_decode($statement->statement, true);
    $verb = $data['verb']['display']['en-US'] ?? 'unknown';
    echo "Activity: $verb\n";
}
```

### Get Video Engagement

```php
$metrics = $xapi->get_video_engagement($userid, $courseid, 30); // Last 30 days

echo "Videos completed: " . $metrics['videos_completed'] . "\n";
echo "Total watch time: " . $metrics['total_watch_time'] . " seconds\n";
echo "Completion rate: " . $metrics['completion_rate'] . "%\n";
```

### Get xAPI Engagement Score

```php
$score = $xapi->get_xapi_engagement_score($userid, $courseid, 30);
echo "xAPI Engagement Score: $score / 100\n";
```

### Enhance Existing Engagement Score

```php
$baseScore = 75.5; // From traditional metrics
$enhancedScore = $xapi->enhance_engagement_score($userid, $courseid, $baseScore);

echo "Base score: $baseScore\n";
echo "Enhanced score: $enhancedScore\n";
```

### Get Widget Data

```php
// Single course
$data = $xapi->get_xapi_widget_data($userid, $courseid);

// All courses
$data = $xapi->get_xapi_widget_data($userid, 0);

if ($data['available']) {
    echo "Engagement score: " . $data['engagement_score'] . "\n";
    echo "Activity count: " . $data['activity_count'] . "\n";
    echo "Unique verbs: " . $data['unique_verbs'] . "\n";
} else {
    echo $data['message']; // "xAPI not available"
}
```

## Dashboard Integration

### Adding xAPI Widget to Dashboard

```php
// In dashboard renderer
$xapi = new \local_manireports\api\xapi_integration();

if ($xapi->is_xapi_available() && $xapi->is_xapi_enabled()) {
    $widgetdata = $xapi->get_xapi_widget_data($userid, $courseid);
    
    // Add to dashboard
    $widgets[] = [
        'type' => 'xapi',
        'title' => get_string('xapi:engagement', 'local_manireports'),
        'data' => $widgetdata,
    ];
}
```

### AJAX Endpoint

Fetch xAPI data via AJAX:

```javascript
fetch('/local/manireports/ui/xapi_widget.php?userid=123&courseid=456')
    .then(response => response.json())
    .then(data => {
        if (data.available) {
            console.log('Engagement score:', data.engagement_score);
            console.log('Video metrics:', data.video_metrics);
        } else {
            console.log(data.message);
        }
    });
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

## Troubleshooting

### xAPI Not Detected

**Problem**: ManiReports shows "xAPI not available"

**Solutions**:

1. **Check Plugin Installation**:
   ```bash
   ls -la /var/www/html/moodle/admin/tool/log/store/xapi
   ```

2. **Check Plugin is Enabled**:
   ```bash
   sudo -u www-data php admin/cli/cfg.php \
     --component=tool_log \
     --name=enabled_stores
   ```
   Should include `logstore_xapi`

3. **Enable via Web Interface**:
   - **Site Administration → Plugins → Logging → Manage log stores**
   - Enable "xAPI log store"

### No xAPI Data

**Problem**: xAPI integration enabled but no data shown

**Solutions**:

1. **Check xAPI Statements Exist**:
   ```bash
   mysql -u moodle_user -p moodle_db -e "
   SELECT COUNT(*) as count 
   FROM mdl_logstore_xapi_log 
   WHERE userid = 123 AND courseid = 456;
   "
   ```

2. **Verify Activities Emit xAPI**:
   - Not all activities emit xAPI statements
   - Check activity plugin documentation
   - H5P and interactive videos typically emit xAPI

3. **Check Date Range**:
   - xAPI data is queried for last 30 days by default
   - Ensure activities occurred within this period

### Performance Issues

**Problem**: Slow dashboard loading with xAPI enabled

**Solutions**:

1. **Add Database Indexes**:
   ```sql
   CREATE INDEX idx_xapi_user_course_time 
   ON mdl_logstore_xapi_log(userid, courseid, timecreated);
   ```

2. **Reduce xAPI Score Weight**:
   - Lower weight = less xAPI queries
   - Set to 0.2 or 0.1

3. **Implement Caching**:
   - xAPI data can be cached like other metrics
   - Use cache_manager for xAPI results

### Video Time Not Tracked

**Problem**: Video watch time shows 0

**Solutions**:

1. **Check Video Plugin Compatibility**:
   - Ensure video plugin emits xAPI statements
   - Check for supported verb URIs

2. **Verify Statement Format**:
   ```php
   $statements = $xapi->get_xapi_statements($userid, $courseid);
   foreach ($statements as $stmt) {
       $data = json_decode($stmt->statement, true);
       print_r($data); // Check structure
   }
   ```

3. **Check Duration Format**:
   - Duration should be in ISO 8601 format (e.g., "PT1H30M")
   - Or numeric seconds in extensions

## Best Practices

### 1. Gradual Rollout

- Start with xAPI disabled
- Enable for pilot group
- Monitor performance and data quality
- Gradually enable for all users

### 2. Weight Tuning

- Start with default weight (0.3)
- Analyze correlation with learning outcomes
- Adjust based on your context
- Document changes

### 3. Data Quality

- Regularly audit xAPI statements
- Verify video plugins emit correct data
- Check for anomalies in engagement scores
- Clean up invalid statements

### 4. Performance Monitoring

- Monitor query execution time
- Add indexes as needed
- Implement caching for heavy queries
- Consider archiving old xAPI data

### 5. User Communication

- Explain xAPI metrics to users
- Provide context for engagement scores
- Document what activities contribute
- Set clear expectations

## Security Considerations

1. **Access Control**: xAPI data respects Moodle capabilities
2. **Data Privacy**: xAPI statements may contain sensitive data
3. **GDPR Compliance**: Include xAPI data in privacy exports
4. **Company Isolation**: IOMAD filtering applies to xAPI queries

## Testing

### Manual Testing

1. **Enable xAPI Integration**:
   - Enable in settings
   - Verify no errors

2. **Generate xAPI Data**:
   - Complete H5P activity
   - Watch video
   - Check statements created

3. **View Dashboard**:
   - Check xAPI widget appears
   - Verify metrics display
   - Test with/without data

4. **Test Engagement Score**:
   - Compare with/without xAPI
   - Verify weight calculation
   - Check score range (0-100)

### Automated Testing

```php
// Test xAPI availability
$xapi = new \local_manireports\api\xapi_integration();
assert($xapi->is_xapi_available() === true);

// Test engagement score
$score = $xapi->get_xapi_engagement_score(123, 456, 30);
assert($score >= 0 && $score <= 100);

// Test video metrics
$metrics = $xapi->get_video_engagement(123, 456, 30);
assert(isset($metrics['total_watch_time']));
assert(isset($metrics['completion_rate']));
```

## Support

For issues with xAPI integration:

1. Check Moodle error logs
2. Verify xAPI logstore plugin is working
3. Test with simple xAPI activities first
4. Review this documentation
5. Check xAPI statement format

## References

- xAPI Specification: https://github.com/adlnet/xAPI-Spec
- xAPI Logstore Plugin: https://moodle.org/plugins/logstore_xapi
- H5P xAPI: https://h5p.org/documentation/x-api
- Video xAPI Profile: https://w3id.org/xapi/video

## Changelog

### Version 1.0 (2024-11-19)
- Initial xAPI integration
- Video analytics support
- Enhanced engagement scoring
- Dashboard widgets
- Graceful degradation
