# ManiReports Quick Upgrade Checklist

## ✅ Pre-Upgrade Verification Complete

### Table Names (All within 28 char limit)
- ✅ manireports_customreports (25 chars)
- ✅ manireports_schedules (21 chars)
- ✅ manireports_sched_recip (23 chars)
- ✅ manireports_report_runs (23 chars)
- ✅ manireports_time_sessions (25 chars)
- ✅ manireports_time_daily (22 chars)
- ✅ manireports_scorm_summary (25 chars)
- ✅ manireports_cache_summary (25 chars)
- ✅ manireports_dashboards (22 chars)
- ✅ manireports_dash_widgets (24 chars)
- ✅ manireports_audit_logs (22 chars)

### Upgrade Script Verification
- ✅ upgrade.php exists and is correct
- ✅ Version 2024111701: Adds schedule fields
- ✅ Version 2024111702: Adds reportid for custom reports
- ✅ All field additions check for existence first (safe)
- ✅ Foreign keys properly defined

## Quick Installation Steps

```bash
# 1. Backup
mysqldump -u moodle_user -p moodle_db > /tmp/backup_$(date +%Y%m%d).sql
tar -czf /tmp/manireports_backup.tar.gz /var/www/html/moodle/local/manireports/

# 2. Enable maintenance
sudo -u www-data php /var/www/html/moodle/admin/cli/maintenance.php --enable

# 3. Upload files (via Git or SCP)
cd /var/www/html/moodle/local/manireports
git pull origin main

# 4. Set permissions
sudo chown -R www-data:www-data /var/www/html/moodle/local/manireports
sudo chmod -R 755 /var/www/html/moodle/local/manireports

# 5. Run upgrade
sudo -u www-data php /var/www/html/moodle/admin/cli/upgrade.php --non-interactive

# 6. Build AMD modules
cd /var/www/html/moodle
sudo -u www-data npx grunt amd --root=local/manireports

# 7. Clear caches
sudo -u www-data php /var/www/html/moodle/admin/cli/purge_caches.php

# 8. Disable maintenance
sudo -u www-data php /var/www/html/moodle/admin/cli/maintenance.php --disable
```

## Quick Test Checklist (15 minutes)

### 1. Basic Access ✅
- [ ] Login as admin
- [ ] Navigate to Site admin → Plugins → Local plugins → ManiReports
- [ ] Settings page loads without errors

### 2. Database ✅
```bash
mysql -u moodle_user -p moodle_db -e "SELECT name, value FROM mdl_config_plugins WHERE plugin='local_manireports' AND name='version';"
# Should show: 2024111702
```

### 3. Dashboard ✅
- [ ] Access: `/local/manireports/ui/dashboard.php`
- [ ] Dashboard loads with widgets
- [ ] No JavaScript errors in console

### 4. Reports ✅
- [ ] Run Course Completion report
- [ ] Export to CSV
- [ ] Both work without errors

### 5. New Features ✅
- [ ] Access GUI Report Builder: `/local/manireports/ui/report_builder_gui.php`
- [ ] Access Dashboard Builder: `/local/manireports/ui/dashboard_builder.php`
- [ ] Both pages load

### 6. Scheduled Tasks ✅
```bash
sudo -u www-data php /var/www/html/moodle/admin/cli/scheduled_task.php --list | grep manireports
# Should show 5 tasks
```

### 7. Error Check ✅
```bash
tail -50 /var/www/html/moodledata/error.log | grep manireports
# Should show no errors (or only old ones)
```

## What's New in This Version

### New Features
1. ✅ **GUI Report Builder** - Visual query builder
2. ✅ **Dashboard Builder** - Custom dashboard creation
3. ✅ **Drill-Down** - Click charts to filter reports
4. ✅ **Custom Report Scheduling** - Schedule GUI reports
5. ✅ **Enhanced Exports** - Better PDF/XLSX formatting

### Database Changes
- Added `reportid` field to schedules table
- Added foreign key constraint for custom reports
- All changes are backward compatible

### New Files
- `amd/src/drilldown.js` - Drill-down functionality
- `ui/dashboard_builder.php` - Dashboard builder UI
- `ui/report_builder_gui.php` - GUI report builder
- `classes/api/dashboard_manager.php` - Dashboard API
- `classes/api/widget_manager.php` - Widget API
- `classes/api/query_builder.php` - Query builder API

## Expected Results

### After Upgrade
- ✅ Plugin version: 2024111702
- ✅ All 11 tables exist
- ✅ Old data preserved
- ✅ New features accessible
- ✅ No errors in logs

### Performance
- Dashboard load: < 3 seconds
- Report execution: < 10 seconds
- Export generation: < 30 seconds

## Troubleshooting

### Issue: Upgrade fails
```bash
# Check error log
tail -50 /var/www/html/moodledata/error.log

# Check database
mysql -u moodle_user -p moodle_db -e "SHOW TABLES LIKE 'mdl_manireports%';"
```

### Issue: JavaScript not working
```bash
# Rebuild AMD modules
cd /var/www/html/moodle
sudo -u www-data npx grunt amd --root=local/manireports
sudo -u www-data php /var/www/html/moodle/admin/cli/purge_caches.php
```

### Issue: Permission errors
```bash
sudo chown -R www-data:www-data /var/www/html/moodle/local/manireports
sudo chmod -R 755 /var/www/html/moodle/local/manireports
```

## Rollback (If Needed)

```bash
# 1. Restore database
mysql -u moodle_user -p moodle_db < /tmp/backup_YYYYMMDD.sql

# 2. Restore files
cd /var/www/html/moodle/local
sudo rm -rf manireports
sudo tar -xzf /tmp/manireports_backup.tar.gz

# 3. Clear caches
sudo -u www-data php /var/www/html/moodle/admin/cli/purge_caches.php
```

## Success Criteria

✅ **Upgrade Successful If:**
- Version shows 2024111702
- All tables exist
- Dashboard loads
- Reports work
- Exports work
- No errors in logs

## Next Steps After Upgrade

1. **Test Core Features** (use UPGRADE_TESTING_GUIDE.md)
2. **Configure Settings** (Site admin → Plugins → ManiReports)
3. **Create Test Reports** (GUI builder)
4. **Create Test Dashboard** (Dashboard builder)
5. **Schedule Reports** (if needed)
6. **Train Users** (on new features)

## Support Files

- `UPGRADE_TESTING_GUIDE.md` - Comprehensive testing (17 test scenarios)
- `PROJECT_STATUS_CLARIFICATION.md` - Feature status
- `DEPLOYMENT_TASK_*.md` - Individual feature deployment guides
- `INSTALL.md` - Fresh installation guide

## Estimated Time

- **Installation**: 10-15 minutes
- **Quick Testing**: 15 minutes
- **Full Testing**: 2-3 hours

## Contact

For issues:
1. Check error logs
2. Review deployment guides
3. Enable Moodle debugging
4. Check browser console

---

**Ready to upgrade? Follow the Quick Installation Steps above!** 🚀
