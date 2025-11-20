# ManiReports Administrator Guide

## Table of Contents

1. [Installation](#installation)
2. [Configuration](#configuration)
3. [User Management](#user-management)
4. [Performance Tuning](#performance-tuning)
5. [Security](#security)
6. [Maintenance](#maintenance)
7. [Troubleshooting](#troubleshooting)

## Installation

### Requirements

- Moodle 4.0 - 4.4 LTS
- PHP 7.4 - 8.2
- MariaDB/MySQL or PostgreSQL
- IOMAD 4.0 - 4.4 (optional)

### Installation Steps

1. **Upload Plugin**:
   ```bash
   cd /var/www/html/moodle/local
   git clone https://github.com/your-repo/manireports.git
   # or upload via ZIP
   ```

2. **Set Permissions**:
   ```bash
   sudo chown -R www-data:www-data manireports
   sudo chmod -R 755 manireports
   ```

3. **Run Upgrade**:
   ```bash
   sudo -u www-data php admin/cli/upgrade.php --non-interactive
   ```

4. **Clear Caches**:
   ```bash
   sudo -u www-data php admin/cli/purge_caches.php
   ```

5. **Create Indexes**:
   ```bash
   sudo -u www-data php local/manireports/cli/ensure_indexes.php
   ```

### Post-Installation

1. Navigate to **Site Administration → Plugins → ManiReports**
2. Configure settings (see Configuration section)
3. Assign capabilities to roles
4. Test dashboards and reports

## Configuration

### General Settings

**Time Tracking**:
- Enable/disable time tracking
- Heartbeat interval: 20-30 seconds recommended
- Session timeout: 10 minutes default

**Cache Settings**:
- Dashboard cache TTL: 3600s (1 hour)
- Trend reports TTL: 21600s (6 hours)
- Historical reports TTL: 86400s (24 hours)

**Report Execution**:
- Query timeout: 60 seconds
- Max concurrent reports: 5
- Adjust based on server capacity

### Data Retention

**Audit Logs**: 365 days (1 year)  
**Report Runs**: 90 days (3 months)  
**Cache Data**: Based on TTL  
**Session Data**: 7 days  

Configure at: Site Administration → Plugins → ManiReports → Data Retention Settings

### At-Risk Learner Detection

**Thresholds**:
- Minimum time spent: 2 hours
- Maximum days since login: 7 days
- Minimum completion: 30%

**Alerts**:
- Email notifications to managers
- Dashboard alerts
- Acknowledgment tracking

### Performance Optimization

**Database Indexes**:
- Automatically created on install
- Run `ensure_indexes.php` to verify

**Concurrent Reports**:
- Default: 5 simultaneous reports
- Increase for powerful servers
- Decrease if database overload

**Page Size**:
- Default: 100 rows per page
- Adjust based on typical report sizes

### xAPI Integration

**Enable xAPI** (if logstore installed):
- Integrates xAPI statement data
- Video engagement metrics
- Enhanced engagement scores

**Configuration**:
- Enable xAPI integration: Yes/No
- xAPI score weight: 0.3 (30%)

## User Management

### Capabilities

**View Dashboards**:
- `local/manireports:viewadmindashboard` - Admin dashboard
- `local/manireports:viewmanagerdashboard` - Manager dashboard
- `local/manireports:viewteacherdashboard` - Teacher dashboard
- `local/manireports:viewstudentdashboard` - Student dashboard

**Manage Reports**:
- `local/manireports:managereports` - Create/edit/delete reports
- `local/manireports:customreports` - Create custom SQL reports
- `local/manireports:schedule` - Schedule reports

### Role Assignment

**Recommended Assignments**:

| Capability | Manager | Teacher | Student |
|------------|---------|---------|---------|
| viewadmindashboard | No | No | No |
| viewmanagerdashboard | Yes | No | No |
| viewteacherdashboard | No | Yes | No |
| viewstudentdashboard | No | No | Yes |
| managereports | Yes | No | No |
| customreports | No | No | No |
| schedule | Yes | No | No |

### IOMAD Multi-Tenancy

**Company Isolation**:
- Automatic filtering by company
- Managers see only their company
- Teachers see only their courses
- Students see only their data

**Configuration**:
- No additional setup required
- Automatic detection of IOMAD
- Company selector for multi-company users

## Performance Tuning

### Database Optimization

**Indexes**:
```bash
# Verify indexes
sudo -u www-data php local/manireports/cli/ensure_indexes.php

# Check index usage
mysql -u root -p -e "SHOW INDEX FROM mdl_manireports_usertime_daily;"
```

**Query Optimization**:
- Use prepared statements
- Limit result sets
- Add WHERE clauses
- Use appropriate indexes

### Caching Strategy

**Cache Levels**:
1. Application cache (Moodle cache API)
2. Query result cache (manireports_cache_summary)
3. Browser cache (static assets)

**Cache Invalidation**:
- Automatic on data changes
- Manual via admin UI
- Scheduled cleanup task

### Scheduled Tasks

**Configure Task Timing**:

| Task | Frequency | Recommended Time |
|------|-----------|------------------|
| cache_builder | Every 6 hours | 2:00 AM, 8:00 AM, 2:00 PM, 8:00 PM |
| time_aggregation | Hourly | Every hour |
| report_scheduler | Every 15 min | Continuous |
| scorm_summary | Hourly | Every hour |
| cleanup_old_data | Daily | 2:00 AM |

**Configure via**:
- Site Administration → Server → Scheduled Tasks
- Search for "manireports"
- Edit timing for each task

### Server Resources

**Small Installation** (<1,000 users):
- 2 CPU cores
- 4GB RAM
- Max concurrent reports: 3-5

**Medium Installation** (1,000-10,000 users):
- 4 CPU cores
- 8GB RAM
- Max concurrent reports: 5-10

**Large Installation** (>10,000 users):
- 8+ CPU cores
- 16GB+ RAM
- Max concurrent reports: 10-20

## Security

### Input Validation

**All user input validated**:
- PARAM_* types enforced
- SQL injection prevention
- XSS protection

### SQL Whitelist

**Allowed Tables**:
- Core Moodle tables (user, course, etc.)
- Grade tables
- Activity tables (quiz, scorm, assign, forum)
- ManiReports tables

**Blocked Operations**:
- DROP, CREATE, ALTER, TRUNCATE
- INSERT, UPDATE, DELETE
- GRANT, REVOKE, EXEC

### CSRF Protection

**All forms protected**:
- Sesskey validation
- Token-based protection
- Automatic logging of violations

### Rate Limiting

**API Endpoints**:
- 60 requests per minute (default)
- Per-user limits
- Automatic blocking on violation

### Security Audit

**Run Regular Audits**:
```bash
sudo -u www-data php local/manireports/cli/security_audit.php

# Auto-fix issues
sudo -u www-data php local/manireports/cli/security_audit.php --fix
```

### Security Headers

**Automatically Added**:
- X-Content-Type-Options: nosniff
- X-Frame-Options: SAMEORIGIN
- X-XSS-Protection: 1; mode=block
- Content-Security-Policy

## Maintenance

### Regular Tasks

**Daily**:
- Check failed jobs dashboard
- Review error logs
- Monitor disk space

**Weekly**:
- Run security audit
- Review audit logs
- Check system health

**Monthly**:
- Clear old failed jobs
- Review data retention
- Update documentation

### Monitoring

**System Health**:
```bash
# Check health status
curl https://your-site.com/local/manireports/ui/performance.php
```

**Failed Jobs**:
- Site Administration → Plugins → ManiReports → Failed Jobs
- Review and retry failed tasks
- Clear old jobs (30+ days)

**Performance Metrics**:
- Site Administration → Plugins → ManiReports → Performance Monitoring
- Table sizes
- Cache hit rates
- Concurrent report usage

### Backup

**Include in Moodle Backup**:
- Database tables (automatic)
- Custom reports
- Schedules
- Dashboards

**Backup Command**:
```bash
# Database backup
mysqldump -u moodle_user -p moodle_db > backup_$(date +%Y%m%d).sql

# Plugin files
tar -czf manireports_backup_$(date +%Y%m%d).tar.gz local/manireports/
```

### Updates

**Update Process**:
1. Backup database and files
2. Upload new version
3. Run upgrade: `php admin/cli/upgrade.php`
4. Clear caches: `php admin/cli/purge_caches.php`
5. Test functionality

## Troubleshooting

### Common Issues

**Reports Not Loading**:
1. Check error logs
2. Verify database connection
3. Clear caches
4. Check capability permissions

**Time Tracking Not Working**:
1. Verify JavaScript enabled
2. Check heartbeat interval setting
3. Review browser console for errors
4. Verify scheduled task running

**Scheduled Reports Not Sending**:
1. Check cron is running
2. Verify email configuration
3. Review failed jobs dashboard
4. Check report_scheduler task

**Performance Issues**:
1. Check concurrent report limit
2. Verify indexes exist
3. Review cache hit rates
4. Monitor server resources

### Debug Mode

**Enable Debugging**:
```php
// In config.php
$CFG->debug = (E_ALL | E_STRICT);
$CFG->debugdisplay = 1;
```

**Check Logs**:
```bash
tail -f /var/www/html/moodledata/error.log
```

### Support Resources

- User Guide
- Developer Documentation
- Troubleshooting Guide
- GitHub Issues

---

**Version**: 1.0  
**Last Updated**: 2024
