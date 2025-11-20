# Deployment Instructions for Task 19.3: GUI Builder Integration

## Overview
This task integrates the GUI report builder with the report system, enabling GUI-built reports to be:
- Saved as custom reports
- Scheduled for automated execution
- Exported in all formats (CSV, XLSX, PDF)

## Changes Made

### 1. Database Schema Updates
- Added `reportid` field to `manireports_schedules` table
- Added foreign key constraint for `reportid`
- Updated version to 2024111702

### 2. Core API Updates
- **report_builder.php**: Updated to handle GUI-type reports
  - `execute_report()` now generates SQL from GUI config
  - `save_report()` validates GUI configurations
  - `update_report()` validates GUI configurations
  - Added `validate_gui_config()` method

### 3. Scheduler Updates
- **report_scheduler.php**: Updated to execute custom reports
  - Handles both SQL and GUI custom reports
  - Uses report_builder API for custom reports

### 4. UI Updates
- **schedule_edit.php**: Added support for custom reports
  - New report category selector (prebuilt/custom)
  - Custom report dropdown populated from database
  - Form handling updated for custom reports

### 5. Language Strings
- Added strings for GUI builder integration
- Added table and column label fallbacks

## Deployment Steps

### Step 1: Upload Files
```bash
# SSH into EC2 instance
ssh user@your-ec2-instance.com

# Navigate to plugin directory
cd /var/www/html/moodle/local/manireports

# Upload updated files via Git or SCP
```

### Step 2: Run Database Upgrade
```bash
# Run Moodle upgrade
sudo -u www-data php /var/www/html/moodle/admin/cli/upgrade.php --non-interactive
```

### Step 3: Clear Caches
```bash
# Purge all caches
sudo -u www-data php /var/www/html/moodle/admin/cli/purge_caches.php
```

### Step 4: Verify Database Changes
```bash
# Check if reportid field was added
mysql -u moodle_user -p moodle_db -e "DESCRIBE mdl_manireports_schedules;"

# Should show reportid field with INT(10) type
```

## Testing Instructions

### Test 1: Create GUI Report
1. Navigate to: Site administration > Reports > ManiReports > GUI Report Builder
2. Create a simple report:
   - Add table: user
   - Add columns: firstname, lastname, email
   - Save report with name "Test GUI Report"
3. Verify report is saved in custom reports list

### Test 2: Schedule GUI Report
1. Navigate to: Site administration > Reports > ManiReports > Scheduled Reports
2. Click "Create Schedule"
3. Fill in form:
   - Name: "Test GUI Schedule"
   - Report Category: Custom Reports
   - Custom Report: Select "Test GUI Report"
   - Format: CSV
   - Frequency: Daily
   - Recipients: your-email@example.com
   - Enabled: Yes
4. Save schedule
5. Verify schedule appears in list

### Test 3: Execute Scheduled Report Manually
```bash
# Run report scheduler task manually
sudo -u www-data php /var/www/html/moodle/admin/cli/scheduled_task.php \
  --execute=\\local_manireports\\task\\report_scheduler
```

### Test 4: Verify Export Formats
1. Navigate to custom reports list
2. Open "Test GUI Report"
3. Test export in each format:
   - CSV
   - XLSX
   - PDF
4. Verify all exports contain correct data

## Verification Checklist

- [ ] Database upgrade completed without errors
- [ ] reportid field exists in manireports_schedules table
- [ ] GUI reports can be created and saved
- [ ] GUI reports can be scheduled
- [ ] Scheduled GUI reports execute successfully
- [ ] GUI reports export in all formats (CSV, XLSX, PDF)
- [ ] SQL reports still work (backward compatibility)
- [ ] Prebuilt reports still work (backward compatibility)
- [ ] No errors in Moodle error log

## Rollback Plan

If issues occur:

```bash
# Restore database backup
mysql -u moodle_user -p moodle_db < backup_before_upgrade.sql

# Restore plugin files
cd /var/www/html/moodle/local
rm -rf manireports
tar -xzf manireports_backup.tar.gz

# Clear caches
sudo -u www-data php /var/www/html/moodle/admin/cli/purge_caches.php
```

## Notes

- GUI reports use the same execution engine as SQL reports
- IOMAD filtering is automatically applied to GUI reports
- GUI reports support all export formats
- Existing schedules for prebuilt reports are not affected
- The reporttype field distinguishes between prebuilt and custom reports

## Requirements Satisfied

- ✅ 15.4: GUI-built reports can be scheduled
- ✅ 15.5: GUI reports support all export formats
- ✅ GUI configuration saved as JSON
- ✅ SQL generated from configuration on execution
