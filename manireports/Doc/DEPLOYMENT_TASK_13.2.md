# Deployment Guide for Task 13.2: Admin Dashboard

## Overview
This guide provides step-by-step instructions for deploying and testing the enhanced admin dashboard with site-wide statistics, IOMAD company display, course usage heatmaps, and inactive users widget.

## Files Modified/Created

### Modified Files:
1. `local/manireports/classes/output/dashboard_renderer.php`
   - Enhanced `get_admin_widgets()` with additional KPI widgets
   - Updated `render_admin_dashboard()` to include companies, course usage, and inactive users data
   - Added helper methods: `is_iomad_installed()`, `get_companies_data()`, `get_course_usage_data()`, `get_inactive_users_list()`

2. `local/manireports/templates/dashboard_admin.mustache`
   - Completely redesigned template with sections for:
     - Site-wide statistics (6 KPI widgets)
     - IOMAD companies list (if installed)
     - Course usage heatmap (top 10 courses)
     - Inactive users list (up to 20 users)
     - Available reports list

3. `local/manireports/lang/en/local_manireports.php`
   - Added language strings for new dashboard elements

## Deployment Steps

### 1. SSH into EC2 Instance
```bash
ssh user@your-ec2-instance.com
```

### 2. Navigate to Moodle Directory
```bash
cd /var/www/html/moodle  # Adjust path as needed
```

### 3. Deploy Updated Files
Upload the modified files via Git, SCP, or SFTP:
```bash
# If using Git
cd local/manireports
git pull origin main

# If using SCP (from local machine)
scp -r local/manireports/classes/output/dashboard_renderer.php user@ec2:/var/www/html/moodle/local/manireports/classes/output/
scp -r local/manireports/templates/dashboard_admin.mustache user@ec2:/var/www/html/moodle/local/manireports/templates/
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

### 6. Verify No Errors
```bash
tail -f /var/www/html/moodledata/error.log
```

## Testing Instructions

### Test 1: Access Admin Dashboard
1. Log in as a site administrator
2. Navigate to: `https://your-moodle-site.com/local/manireports/ui/dashboard.php`
3. Verify the dashboard loads without errors

### Test 2: Verify Site-Wide Statistics Widgets
Expected widgets:
- Total Users
- Total Courses
- Total Enrolments
- Active Users (30 days)
- Inactive Users (30 days)
- Completions (30 days)

Verify:
- All widgets display numeric values
- Inactive users widget shows warning styling (yellow border) if count > 0
- Values are accurate (cross-check with Moodle reports)

### Test 3: Verify IOMAD Companies Section (if IOMAD installed)
If IOMAD is installed:
- Companies section should be visible
- Each company should show:
  - Company name and short name
  - User count
  - Course count
- If no companies exist, should show "No companies found" message

If IOMAD is NOT installed:
- Companies section should not be visible

### Test 4: Verify Course Usage Heatmap
Expected behavior:
- Shows top 10 most accessed courses in last 30 days
- Each course displays:
  - Full name and short name
  - Active users count
  - Total accesses count
- If no data, shows "No course usage data available"

### Test 5: Verify Inactive Users Widget
Expected behavior:
- Shows up to 20 users who haven't logged in for 30+ days
- Each user displays:
  - Full name and email
  - Last access date (or "Never")
  - Days inactive (or "Never")
- Days inactive shown in warning color (yellow/orange)
- If no inactive users, shows success message "No inactive users found"

### Test 6: Verify Reports List
- All 5 prebuilt reports should be listed:
  - Course Completion
  - Course Progress
  - User Engagement
  - Quiz Attempts
  - SCORM Summary
- Each link should be clickable and navigate to the report view

### Test 7: Responsive Design
Test on different screen sizes:
- Desktop (1920x1080): All widgets should display in grid layout
- Tablet (768x1024): Widgets should stack appropriately
- Mobile (375x667): Single column layout

### Test 8: Performance
- Dashboard should load within 2-3 seconds
- No database timeout errors
- Check query performance in error log

## Troubleshooting

### Issue: Dashboard shows blank page
**Solution:**
```bash
# Check PHP error log
tail -f /var/log/apache2/error.log  # or nginx error log

# Check Moodle error log
tail -f /var/www/html/moodledata/error.log

# Clear caches again
sudo -u www-data php admin/cli/purge_caches.php
```

### Issue: "No permission" error
**Solution:**
- Verify user has `local/manireports:viewadmindashboard` capability
- Check capability assignment:
```bash
sudo -u www-data php admin/cli/cfg.php --name=debug --set=32767
# Then check Site Administration > Users > Permissions > Check permissions
```

### Issue: IOMAD companies not showing
**Solution:**
- Verify IOMAD is installed: Check if `/var/www/html/moodle/local/iomad/lib.php` exists
- Check if company table exists:
```bash
mysql -u moodle_user -p moodle_db -e "SHOW TABLES LIKE 'mdl_company';"
```

### Issue: Course usage shows no data
**Solution:**
- Verify logstore_standard is enabled
- Check if log table has data:
```bash
mysql -u moodle_user -p moodle_db -e "SELECT COUNT(*) FROM mdl_logstore_standard_log WHERE timecreated > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY));"
```

### Issue: Inactive users not displaying correctly
**Solution:**
- Verify user table has lastaccess data
- Check query manually:
```bash
mysql -u moodle_user -p moodle_db -e "SELECT id, firstname, lastname, lastaccess FROM mdl_user WHERE deleted = 0 AND suspended = 0 AND lastaccess < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY)) LIMIT 5;"
```

## Verification Checklist

- [ ] Dashboard loads without errors
- [ ] All 6 KPI widgets display correctly
- [ ] IOMAD companies section works (if applicable)
- [ ] Course usage heatmap shows top 10 courses
- [ ] Inactive users list displays correctly
- [ ] Reports list is functional
- [ ] Responsive design works on mobile/tablet
- [ ] Performance is acceptable (< 3 seconds)
- [ ] No errors in Moodle error log
- [ ] No errors in PHP error log

## Success Criteria

Task 13.2 is successfully deployed when:
1. Admin dashboard displays all required sections
2. Site-wide statistics are accurate
3. IOMAD companies are listed (if installed)
4. Course usage heatmap shows relevant data
5. Inactive users widget identifies users correctly
6. All functionality works without errors
7. Performance meets requirements (< 3 seconds load time)

## Next Steps

After successful deployment and testing:
1. Mark task 13.2 as complete in tasks.md
2. Proceed to task 13.3: Create company manager dashboard
3. Document any issues or improvements needed

## Support

If you encounter issues not covered in this guide:
1. Check Moodle error logs
2. Review PHP error logs
3. Verify database connectivity
4. Ensure all files are properly deployed
5. Confirm file permissions are correct
