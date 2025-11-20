# ManiReports Troubleshooting Guide

## Table of Contents

1. [Installation Issues](#installation-issues)
2. [Dashboard Problems](#dashboard-problems)
3. [Report Issues](#report-issues)
4. [Scheduled Reports](#scheduled-reports)
5. [Time Tracking](#time-tracking)
6. [Performance Issues](#performance-issues)
7. [Security Issues](#security-issues)
8. [Error Messages](#error-messages)

## Installation Issues

### Plugin Not Appearing After Upload

**Symptoms**: Plugin doesn't show in plugin list

**Solutions**:
1. Check file permissions:
   ```bash
   sudo chown -R www-data:www-data local/manireports
   sudo chmod -R 755 local/manireports
   ```

2. Clear caches:
   ```bash
   sudo -u www-data php admin/cli/purge_caches.php
   ```

3. Run upgrade:
   ```bash
   sudo -u www-data php admin/cli/upgrade.php
   ```

### Database Tables Not Created

**Symptoms**: Errors about missing tables

**Solutions**:
1. Check install.xml syntax
2. Run upgrade manually:
   ```bash
   sudo -u www-data php admin/cli/upgrade.php --non-interactive
   ```

3. Verify database permissions:
   ```sql
   SHOW GRANTS FOR 'moodle_user'@'localhost';
   ```

### Permission Denied Errors

**Symptoms**: Cannot write to directories

**Solutions**:
```bash
# Fix ownership
sudo chown -R www-data:www-data /var/www/html/moodle/local/manireports

# Fix permissions
sudo chmod -R 755 /var/www/html/moodle/local/manireports
sudo chmod -R 644 /var/www/html/moodle/local/manireports/**/*.php
```

## Dashboard Problems

### Dashboard Not Loading

**Symptoms**: Blank page or error

**Solutions**:
1. Check error logs:
   ```bash
   tail -f /var/www/html/moodledata/error.log
   ```

2. Enable debugging in config.php:
   ```php
   $CFG->debug = (E_ALL | E_STRICT);
   $CFG->debugdisplay = 1;
   ```

3. Verify capability:
   - Check user has appropriate dashboard capability
   - Site Administration → Users → Permissions → Check permissions

4. Clear caches:
   ```bash
   sudo -u www-data php admin/cli/purge_caches.php
   ```

### Widgets Not Displaying Data

**Symptoms**: Empty widgets or "No data"

**Solutions**:
1. Check if data exists in database
2. Verify date range filters
3. Check company filters (IOMAD)
4. Clear cache for specific widget
5. Run cache builder task:
   ```bash
   sudo -u www-data php admin/cli/scheduled_task.php --execute=\\local_manireports\\task\\cache_builder
   ```

### Slow Dashboard Loading

**Symptoms**: Dashboard takes >5 seconds to load

**Solutions**:
1. Check cache hit rate (Performance Monitoring)
2. Verify indexes exist:
   ```bash
   sudo -u www-data php local/manireports/cli/ensure_indexes.php
   ```

3. Increase cache TTL in settings
4. Reduce concurrent report limit
5. Check server resources (CPU, RAM, disk)

## Report Issues

### Custom Report Not Executing

**Symptoms**: Error when running custom report

**Solutions**:
1. Validate SQL syntax
2. Check table whitelist:
   ```php
   $builder = new \local_manireports\api\report_builder();
   $tables = $builder::get_allowed_tables();
   print_r($tables);
   ```

3. Verify parameters match query:
   - Use `:paramname` format
   - Provide all required parameters

4. Check for blocked keywords (DROP, INSERT, etc.)

### SQL Injection Error

**Symptoms**: "Dangerous SQL keyword detected"

**Solutions**:
- Remove blocked keywords (DROP, INSERT, UPDATE, DELETE)
- Use only SELECT queries
- Use parameter binding for values
- Check whitelist tables only

### Report Timeout

**Symptoms**: "Query timeout exceeded"

**Solutions**:
1. Optimize query:
   - Add WHERE clauses
   - Limit result set
   - Use appropriate indexes

2. Increase timeout:
   - Site Administration → Plugins → ManiReports
   - Increase "Query timeout" setting

3. Add indexes to queried tables

### Export Fails

**Symptoms**: Error when exporting to CSV/XLSX/PDF

**Solutions**:
1. Check disk space:
   ```bash
   df -h
   ```

2. Verify file permissions on moodledata
3. Check PHP memory limit:
   ```php
   ini_get('memory_limit');
   ```

4. Reduce result set size (add filters)

## Scheduled Reports

### Reports Not Sending

**Symptoms**: Scheduled reports not delivered

**Solutions**:
1. Check cron is running:
   ```bash
   # Check last cron run
   mysql -u moodle_user -p -e "SELECT * FROM mdl_task_scheduled WHERE classname LIKE '%manireports%';"
   ```

2. Verify email configuration:
   ```bash
   php admin/cli/test_outgoing_mail_configuration.php
   ```

3. Check failed jobs:
   - Site Administration → Plugins → ManiReports → Failed Jobs

4. Run scheduler manually:
   ```bash
   sudo -u www-data php admin/cli/scheduled_task.php --execute=\\local_manireports\\task\\report_scheduler
   ```

### Schedule Not Running at Correct Time

**Symptoms**: Reports run at wrong time

**Solutions**:
1. Check server timezone:
   ```bash
   date
   timedatectl
   ```

2. Verify Moodle timezone:
   - Site Administration → Location → Location settings

3. Check schedule configuration:
   - Scheduled Reports → Edit schedule
   - Verify time and frequency

### Email Not Received

**Symptoms**: Report generated but email not received

**Solutions**:
1. Check spam folder
2. Verify recipient email addresses
3. Check email logs:
   ```bash
   tail -f /var/log/mail.log
   ```

4. Test email configuration:
   ```bash
   php admin/cli/test_outgoing_mail_configuration.php
   ```

## Time Tracking

### Time Not Being Tracked

**Symptoms**: Zero time shown for users

**Solutions**:
1. Verify time tracking enabled:
   - Site Administration → Plugins → ManiReports
   - Check "Enable time tracking"

2. Check JavaScript console for errors:
   - Open browser developer tools (F12)
   - Look for heartbeat errors

3. Verify heartbeat endpoint accessible:
   ```bash
   curl https://your-site.com/local/manireports/ui/ajax/heartbeat.php
   ```

4. Run aggregation task:
   ```bash
   sudo -u www-data php admin/cli/scheduled_task.php --execute=\\local_manireports\\task\\time_aggregation
   ```

### Inaccurate Time Data

**Symptoms**: Time values seem wrong

**Solutions**:
1. Check heartbeat interval setting (should be 20-30s)
2. Verify session timeout setting (should be 10 min)
3. Check for JavaScript errors blocking heartbeat
4. Review aggregation logic in time_aggregation task

## Performance Issues

### High Database Load

**Symptoms**: Slow queries, database CPU high

**Solutions**:
1. Check concurrent reports:
   - Performance Monitoring → Concurrent Reports

2. Reduce max concurrent reports:
   - Site Administration → Plugins → ManiReports
   - Lower "Maximum concurrent reports"

3. Verify indexes:
   ```bash
   sudo -u www-data php local/manireports/cli/ensure_indexes.php
   ```

4. Optimize slow queries:
   ```bash
   # Enable slow query log
   mysql -u root -p -e "SET GLOBAL slow_query_log = 'ON';"
   mysql -u root -p -e "SET GLOBAL long_query_time = 2;"
   
   # Review slow queries
   tail -f /var/log/mysql/slow-query.log
   ```

### High Memory Usage

**Symptoms**: PHP memory errors, server swapping

**Solutions**:
1. Reduce page size for reports
2. Increase PHP memory limit:
   ```php
   // In php.ini
   memory_limit = 256M
   ```

3. Enable pagination on all reports
4. Clear old cache data

### Disk Space Issues

**Symptoms**: "No space left on device"

**Solutions**:
1. Check disk usage:
   ```bash
   df -h
   du -sh /var/www/html/moodledata/*
   ```

2. Clear old data:
   - Failed Jobs → Clear Old Jobs
   - Run cleanup task:
     ```bash
     sudo -u www-data php admin/cli/scheduled_task.php --execute=\\local_manireports\\task\\cleanup_old_data
     ```

3. Remove old export files
4. Reduce data retention periods

## Security Issues

### Rate Limit Exceeded

**Symptoms**: "Rate limit exceeded" error

**Solutions**:
1. Wait for time window to expire (default: 60 seconds)
2. Increase rate limit in code if legitimate use
3. Check for automated scripts hitting API

### CSRF Token Invalid

**Symptoms**: "Invalid sesskey" error

**Solutions**:
1. Refresh page to get new sesskey
2. Check session timeout settings
3. Verify cookies enabled in browser
4. Clear browser cache

### Access Denied

**Symptoms**: "Access denied" or permission error

**Solutions**:
1. Check user capabilities:
   - Site Administration → Users → Permissions → Check permissions

2. Verify role assignments:
   - User profile → Roles

3. Check company assignment (IOMAD):
   - Verify user assigned to correct company

4. Review audit logs:
   - Site Administration → Plugins → ManiReports → Audit Logs

## Error Messages

### "Too many reports are currently running"

**Cause**: Concurrent report limit reached

**Solution**:
- Wait for current reports to finish
- Increase max concurrent reports setting
- Check for stuck reports in Failed Jobs

### "Query timeout exceeded"

**Cause**: Query took longer than timeout setting

**Solution**:
- Optimize query (add WHERE, LIMIT)
- Increase query timeout setting
- Add appropriate indexes

### "Table not whitelisted"

**Cause**: Query references non-allowed table

**Solution**:
- Use only whitelisted tables
- Check allowed tables list in report builder
- Contact administrator to add table to whitelist

### "Dangerous SQL keyword detected"

**Cause**: Query contains blocked operation

**Solution**:
- Remove DROP, INSERT, UPDATE, DELETE statements
- Use only SELECT queries
- Use parameter binding for values

### "Invalid parameter type"

**Cause**: Parameter doesn't match expected type

**Solution**:
- Check PARAM_* type matches data
- Verify parameter name matches query
- Provide all required parameters

### "Cache write failed"

**Cause**: Cannot write to cache table

**Solution**:
- Check database permissions
- Verify disk space
- Check cache table exists

## Getting Help

### Collect Diagnostic Information

Before requesting help, collect:

1. **Error logs**:
   ```bash
   tail -100 /var/www/html/moodledata/error.log
   ```

2. **System info**:
   - Moodle version
   - PHP version
   - Database type and version
   - Server OS

3. **Plugin version**:
   - Site Administration → Plugins → Plugin overview
   - Find "ManiReports"

4. **Steps to reproduce**:
   - What you were trying to do
   - What happened
   - What you expected

### Support Channels

- GitHub Issues
- Moodle Forums
- Documentation
- Administrator Guide

---

**Version**: 1.0  
**Last Updated**: 2024
