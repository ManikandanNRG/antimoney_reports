# AMD Module Deployment Guide

## Understanding AMD in Moodle

Moodle uses AMD (Asynchronous Module Definition) for JavaScript modules. There are two ways to handle AMD modules:

### Option 1: Development Mode (Recommended for Testing)
Moodle can load AMD modules directly from `amd/src/` without building.

**Requirements:**
- Set `$CFG->cachejs = false;` in your Moodle `config.php`
- Purge all caches after changes

**Advantages:**
- No build step required
- Faster development cycle
- Immediate changes visible after cache purge

**Steps:**
1. SSH into your server
2. Edit `/var/www/html/config.php` (or your Moodle path)
3. Add or ensure this line exists:
   ```php
   $CFG->cachejs = false;
   ```
4. Purge caches:
   ```bash
   sudo -u www-data php /var/www/html/admin/cli/purge_caches.php
   ```

### Option 2: Production Mode (Build Required)
For production, AMD modules should be minified and placed in `amd/build/`.

**This requires:**
- Node.js and npm installed on the server
- Grunt installed
- Running the build command

**Steps:**
1. SSH into your server
2. Navigate to Moodle root:
   ```bash
   cd /var/www/html
   ```
3. Run Grunt build:
   ```bash
   npx grunt amd --root=local/manireports
   ```

## Current Plugin AMD Modules

The following AMD modules exist in `local/manireports/amd/src/`:

1. **charts.js** - Chart rendering with Chart.js
2. **filters.js** - Filter handling and AJAX updates
3. **heartbeat.js** - Time tracking heartbeat
4. **dashboard_builder.js** - Dashboard builder UI
5. **report_builder_gui.js** - GUI report builder
6. **drilldown.js** - Drill-down functionality

## Quick Fix for Your Server

Since you're on a remote server and want to test immediately:

**Run these commands via SSH:**

```bash
# 1. Check if cachejs is disabled
grep "cachejs" /var/www/html/config.php

# 2. If not found or set to true, edit config.php
sudo nano /var/www/html/config.php

# 3. Add this line before the "require_once" at the end:
$CFG->cachejs = false;

# 4. Save and exit (Ctrl+X, Y, Enter)

# 5. Purge all caches
sudo -u www-data php /var/www/html/admin/cli/purge_caches.php

# 6. Test your dashboards and reports
```

## Verification

After setting `cachejs = false` and purging caches:

1. Open browser console (F12)
2. Navigate to: `https://dev.aktrea.net/local/manireports/ui/dashboard.php`
3. Check Network tab for AMD module loads
4. Look for requests to `/local/manireports/amd/src/charts.js`
5. No 404 errors = AMD modules loading correctly

## Troubleshooting

**Problem:** JavaScript not working, console shows AMD errors

**Solution:**
```bash
# Clear browser cache completely
# Purge Moodle caches again
sudo -u www-data php /var/www/html/admin/cli/purge_caches.php

# Check file permissions
sudo chown -R www-data:www-data /var/www/html/local/manireports/amd/
sudo chmod -R 755 /var/www/html/local/manireports/amd/
```

**Problem:** Still not loading

**Solution:** Check Moodle error logs
```bash
tail -50 /opt/moodledata/moodledata.log | grep -i amd
```

## For Production Deployment

When ready for production, you should:

1. Install Node.js and npm on server
2. Run the Grunt build command
3. Set `$CFG->cachejs = true;` in config.php
4. Purge caches

This will use the minified versions from `amd/build/` for better performance.

## Current Status

✅ All AMD source files exist in `amd/src/`
⏳ Build directory (`amd/build/`) not created yet
✅ Modules will work with `cachejs = false`

**Recommendation:** Use development mode (`cachejs = false`) for testing, then build for production later.
