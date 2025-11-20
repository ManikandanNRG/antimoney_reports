# Deployment Guide: Task 21 - External API Endpoints

## Overview

Task 21 implements RESTful JSON API endpoints for external integration with BI tools, mobile applications, and third-party systems. This enables programmatic access to ManiReports data.

## Files Created/Modified

### New Files
1. `classes/external/api.php` - External API class with all endpoint implementations
2. `API_DOCUMENTATION.md` - Comprehensive API documentation

### Modified Files
1. `db/services.php` - Added web service function definitions
2. `classes/output/dashboard_renderer.php` - Added API support method
3. `lang/en/local_manireports.php` - Added API error strings

## Deployment Steps

### 1. Upload Files to Server

```bash
# SSH into your EC2 instance
ssh user@your-ec2-instance.com

# Navigate to Moodle directory
cd /var/www/html/moodle/local/manireports

# Upload the modified files via Git or SCP
# If using Git:
git pull origin main

# Set proper permissions
sudo chown -R www-data:www-data classes/external/
sudo chmod -R 755 classes/external/
```

### 2. Clear Moodle Caches

```bash
# Clear all caches
sudo -u www-data php admin/cli/purge_caches.php

# Verify no errors
echo $?
```

### 3. Enable Web Services in Moodle

```bash
# Via browser, go to:
# Site Administration → Advanced features
# Enable "Enable web services"
# Save changes
```

Or via CLI:

```bash
sudo -u www-data php admin/cli/cfg.php --name=enablewebservices --set=1
```

### 4. Enable REST Protocol

```bash
# Via browser, go to:
# Site Administration → Server → Web services → Manage protocols
# Enable "REST protocol"
```

Or via database:

```bash
mysql -u moodle_user -p moodle_db -e "UPDATE mdl_config SET value='1' WHERE name='webserviceprotocols' AND value LIKE '%rest%';"
```

### 5. Create External Service

Via browser:
1. Go to **Site Administration → Server → Web services → External services**
2. Click "Add"
3. Fill in:
   - Name: `ManiReports API`
   - Short name: `manireports_api`
   - Enabled: Yes
   - Authorized users only: No (or Yes for restricted access)
4. Save
5. Click "Add functions" and add:
   - `local_manireports_get_dashboard_data`
   - `local_manireports_get_report_data`
   - `local_manireports_get_report_metadata`
   - `local_manireports_get_available_reports`

### 6. Create Web Service Token

Via browser:
1. Go to **Site Administration → Server → Web services → Manage tokens**
2. Click "Add"
3. Select:
   - User: (select a user with appropriate capabilities)
   - Service: ManiReports API
4. Save
5. Copy the generated token (you'll need this for testing)

Or via CLI:

```bash
# Create token for user ID 2 (admin)
sudo -u www-data php admin/cli/create_webservice_token.php \
  --userid=2 \
  --service=manireports_api
```

## Testing

### Test 1: Get Available Reports

```bash
# Replace YOUR_TOKEN and YOUR_MOODLE_URL
curl "https://YOUR_MOODLE_URL/webservice/rest/server.php" \
  -d "wstoken=YOUR_TOKEN" \
  -d "wsfunction=local_manireports_get_available_reports" \
  -d "moodlewsrestformat=json"
```

Expected output:
```json
{
  "success": true,
  "reports": [
    {
      "id": 0,
      "name": "Course Completion Report",
      "type": "prebuilt",
      "key": "course_completion"
    },
    ...
  ],
  "total": 5
}
```

### Test 2: Get Dashboard Data

```bash
curl "https://YOUR_MOODLE_URL/webservice/rest/server.php" \
  -d "wstoken=YOUR_TOKEN" \
  -d "wsfunction=local_manireports_get_dashboard_data" \
  -d "moodlewsrestformat=json" \
  -d "dashboardtype=admin" \
  -d "page=0" \
  -d "pagesize=25"
```

Expected output:
```json
{
  "success": true,
  "data": {
    "widgets": [...],
    "total": 5
  },
  "pagination": {
    "page": 0,
    "pagesize": 25,
    "total": 5,
    "totalpages": 1
  }
}
```

### Test 3: Get Report Metadata

```bash
curl "https://YOUR_MOODLE_URL/webservice/rest/server.php" \
  -d "wstoken=YOUR_TOKEN" \
  -d "wsfunction=local_manireports_get_report_metadata" \
  -d "moodlewsrestformat=json"
```

Expected output:
```json
{
  "success": true,
  "reports": [...],
  "total": 3
}
```

### Test 4: Execute Report

First, create a custom report via the UI, then:

```bash
curl "https://YOUR_MOODLE_URL/webservice/rest/server.php" \
  -d "wstoken=YOUR_TOKEN" \
  -d "wsfunction=local_manireports_get_report_data" \
  -d "moodlewsrestformat=json" \
  -d "reportid=1" \
  -d "page=0" \
  -d "pagesize=50"
```

## Verification Checklist

- [ ] Files uploaded and permissions set correctly
- [ ] Caches cleared successfully
- [ ] Web services enabled in Moodle
- [ ] REST protocol enabled
- [ ] External service created with all 4 functions
- [ ] Web service token created
- [ ] Test 1 (get_available_reports) passes
- [ ] Test 2 (get_dashboard_data) passes
- [ ] Test 3 (get_report_metadata) passes
- [ ] Test 4 (get_report_data) passes
- [ ] No errors in Moodle error log
- [ ] API documentation accessible

## Troubleshooting

### Issue: "Web service not available"

**Solution**:
```bash
# Check if web services are enabled
sudo -u www-data php admin/cli/cfg.php --name=enablewebservices

# Should output: 1
# If not, enable it:
sudo -u www-data php admin/cli/cfg.php --name=enablewebservices --set=1
```

### Issue: "Invalid token"

**Solution**:
1. Verify token exists in database:
```bash
mysql -u moodle_user -p moodle_db -e "SELECT * FROM mdl_external_tokens WHERE token='YOUR_TOKEN';"
```

2. Check token is not expired
3. Verify user has required capabilities

### Issue: "Function not found"

**Solution**:
```bash
# Clear caches
sudo -u www-data php admin/cli/purge_caches.php

# Verify function is registered
mysql -u moodle_user -p moodle_db -e "SELECT * FROM mdl_external_functions WHERE name LIKE 'local_manireports%';"
```

### Issue: "Access denied"

**Solution**:
1. Check user has required capability:
   - For dashboard: `local/manireports:viewadmindashboard` (or appropriate dashboard capability)
   - For reports: `local/manireports:managereports`

2. Assign capability via:
   - **Site Administration → Users → Permissions → Define roles**
   - Edit the user's role and add the capability

### Issue: "No data returned"

**Solution**:
1. Check Moodle error log:
```bash
tail -f /var/www/html/moodledata/error.log
```

2. Enable debugging:
```bash
sudo -u www-data php admin/cli/cfg.php --name=debug --set=32767
sudo -u www-data php admin/cli/cfg.php --name=debugdisplay --set=1
```

3. Make API call again and check error details

## Security Considerations

1. **Token Security**: Store tokens securely, never commit to version control
2. **HTTPS**: Always use HTTPS in production
3. **IP Restrictions**: Consider restricting API access by IP address
4. **Rate Limiting**: Monitor API usage and implement rate limiting if needed
5. **Capability Checks**: All endpoints enforce capability checks automatically
6. **IOMAD Filtering**: Company isolation is automatically applied

## Performance Considerations

1. **Pagination**: Always use pagination for large datasets (max pagesize: 100)
2. **Caching**: Dashboard data is cached according to cache settings
3. **Concurrent Requests**: Monitor concurrent API requests
4. **Database Load**: Heavy report queries may impact database performance

## Monitoring

### Check API Usage

```bash
# Check web service logs
mysql -u moodle_user -p moodle_db -e "
SELECT 
    FROM_UNIXTIME(timecreated) as time,
    userid,
    other
FROM mdl_logstore_standard_log 
WHERE component = 'webservice' 
  AND target = 'webservice'
ORDER BY timecreated DESC 
LIMIT 20;
"
```

### Monitor Error Rate

```bash
# Check for API errors in last hour
grep "local_manireports\\\external\\\api" /var/www/html/moodledata/error.log | \
  grep "$(date -d '1 hour ago' '+%Y-%m-%d %H')" | \
  wc -l
```

## Next Steps

1. Share API documentation with integration teams
2. Create example client code in Python/JavaScript
3. Set up monitoring and alerting for API errors
4. Consider implementing API rate limiting
5. Document any custom API endpoints for specific use cases

## Rollback Plan

If issues occur:

```bash
# 1. Disable the external service
mysql -u moodle_user -p moodle_db -e "
UPDATE mdl_external_services 
SET enabled=0 
WHERE shortname='manireports_api';
"

# 2. Clear caches
sudo -u www-data php admin/cli/purge_caches.php

# 3. Restore previous version of files if needed
git checkout HEAD~1 -- classes/external/api.php db/services.php
```

## Support

For issues:
1. Check Moodle error logs: `/var/www/html/moodledata/error.log`
2. Review API documentation: `API_DOCUMENTATION.md`
3. Test with cURL commands provided above
4. Verify web service configuration in Moodle admin

## Completion Criteria

Task 21 is complete when:
- [x] All 4 API endpoints are implemented
- [x] Web service definitions are created
- [x] Pagination support is implemented (max 100 items per page)
- [x] Error handling returns appropriate HTTP status codes and JSON errors
- [x] Capability checks are enforced on all endpoints
- [x] API documentation is complete
- [x] All test cases pass successfully
- [x] No errors in Moodle error log
