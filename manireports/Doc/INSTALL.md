# ManiReports Installation Guide

## Quick Installation (EC2 Server)

### 1. Upload Plugin Files

```bash
# SSH into your EC2 instance
ssh user@your-ec2-instance.com

# Navigate to Moodle local plugins directory
cd /var/www/html/moodle/local/

# Upload the manireports folder (via Git, SCP, or SFTP)
# Example using Git:
# git clone <your-repo-url> manireports

# Or copy files manually
```

### 2. Set Permissions

```bash
# Set proper ownership
sudo chown -R www-data:www-data manireports/
sudo chmod -R 755 manireports/
```

### 3. Install Plugin

```bash
# Clear Moodle caches
sudo -u www-data php admin/cli/purge_caches.php

# Run database upgrade to create tables
sudo -u www-data php admin/cli/upgrade.php --non-interactive
```

### 4. Verify Installation

```bash
# Check if tables were created
mysql -u moodle_user -p moodle_db -e "SHOW TABLES LIKE 'mdl_manireports%';"

# Expected output: 11 tables
# - mdl_manireports_audit_logs
# - mdl_manireports_cache_summary
# - mdl_manireports_customreports
# - mdl_manireports_dash_widgets
# - mdl_manireports_dashboards
# - mdl_manireports_report_runs
# - mdl_manireports_sched_recip
# - mdl_manireports_schedules
# - mdl_manireports_scorm_summary
# - mdl_manireports_time_daily
# - mdl_manireports_time_sessions

# Check scheduled tasks are registered
sudo -u www-data php admin/cli/scheduled_task.php --list | grep manireports

# Expected output: 5 tasks
```

## Access the Plugin

### 1. Via Browser

1. Log into your Moodle site as admin
2. Navigate to: **Site administration > Plugins > Local plugins > ManiReports**
3. Configure plugin settings if needed

### 2. Access Dashboard

**Direct URL:**
```
https://your-moodle-site.com/local/manireports/ui/dashboard.php
```

**Via Navigation:**
- Look for "ManiReports" in the main navigation menu
- Click to access your role-based dashboard

## What You Can See Now

### Admin Dashboard
- Total Users, Total Courses, Total Enrolments widgets
- Links to all 5 prebuilt reports:
  - Course Completion
  - Course Progress
  - User Engagement
  - Quiz Attempts
  - SCORM Summary

### Manager Dashboard
- Company-specific statistics (if IOMAD installed)
- Course Completion and Course Progress reports

### Teacher Dashboard
- My Courses widget
- Course Progress and Quiz Attempts reports

### Student Dashboard
- Enrolled Courses and Completed Courses widgets
- Personal progress information

## View Reports

Click any report link from the dashboard to view:
- **Course Completion**: Enrollment and completion statistics per course
- **Course Progress**: Individual user progress with activity completion
- **User Engagement**: Time spent and active days (requires time tracking - not yet implemented)
- **Quiz Attempts**: Quiz statistics with scores
- **SCORM Summary**: SCORM activity analytics (requires SCORM aggregation - not yet implemented)

## Troubleshooting

### Plugin Not Showing in Menu
```bash
# Clear all caches
sudo -u www-data php admin/cli/purge_caches.php

# Check capabilities are assigned
# Go to: Site administration > Users > Permissions > Define roles
# Verify roles have appropriate local/manireports:view* capabilities
```

### Permission Denied Error
- Ensure your user has at least one of these capabilities:
  - `local/manireports:viewadmindashboard`
  - `local/manireports:viewmanagerdashboard`
  - `local/manireports:viewteacherdashboard`
  - `local/manireports:viewstudentdashboard`

### Database Errors
```bash
# Check Moodle error log
tail -f /var/www/html/moodledata/error.log

# Verify database tables exist
mysql -u moodle_user -p moodle_db -e "SHOW TABLES LIKE 'mdl_manireports%';"
```

### Reports Show No Data
- This is normal if you have a fresh Moodle installation
- Create some test courses, enroll users, and add completions
- Some reports (User Engagement, SCORM Summary) require additional tasks to be implemented

## What's NOT Working Yet

The following features require additional tasks to be completed:

- ❌ **Time Tracking** - Heartbeat JavaScript (Task 6)
- ❌ **SCORM Analytics** - Data aggregation (Task 7)
- ❌ **Caching** - Pre-aggregation (Task 8)
- ❌ **Export** - CSV/Excel/PDF downloads (Task 10)
- ❌ **Scheduled Reports** - Automated email delivery (Task 11)
- ❌ **Audit Logging** - Full audit trail (Task 12)
- ❌ **Charts** - Visual charts on reports (Task 15)
- ❌ **AJAX Filters** - Dynamic filtering (Task 16)

## Next Steps

To get full functionality, continue implementing:
1. Task 6 - Time tracking (for engagement data)
2. Task 7 - SCORM aggregation (for SCORM reports)
3. Task 10 - Export engine (for downloads)
4. Task 15 - Chart rendering (for visualizations)

## Support

For issues or questions:
1. Check Moodle error logs
2. Verify all files are uploaded correctly
3. Ensure proper permissions are set
4. Check database tables were created

Enjoy ManiReports! 🎉
