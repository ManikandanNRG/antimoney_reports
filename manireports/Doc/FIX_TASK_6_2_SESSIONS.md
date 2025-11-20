# Fix: Task 6.2 - Session Recording Not Working

## Problem Summary

**Status:** ✗ FAIL
- ✓ Heartbeat requests ARE being sent (Task 6.1 PASS)
- ✗ Session records are NOT being created (Task 6.2 FAIL)

**Root Cause:** Time tracking setting is not enabled in the plugin configuration.

---

## Solution

### Step 1: Enable Time Tracking via CLI

SSH to your EC2 server and run:

```bash
ssh user@your-ec2-instance.com
cd /var/www/html/moodle
sudo -u www-data php local/manireports/cli/enable_timetracking.php
```

**Expected Output:**
```
=== ManiReports Time Tracking Configuration ===

✓ Time tracking enabled
✓ Heartbeat interval set to 25 seconds
✓ Session timeout set to 10 minutes

=== Verification ===
Time Tracking Enabled: YES ✓
Heartbeat Interval: 25 seconds
Session Timeout: 10 minutes

=== Database Tables ===
manireports_time_sessions: EXISTS ✓
manireports_time_daily: EXISTS ✓

=== Current Data ===
Active Sessions: 0
Daily Records: 0

✓ Time tracking configuration complete!
Heartbeat requests should now create session records.
```

### Step 2: Verify via Admin Panel (Optional)

1. Login to Moodle as admin
2. Navigate to: **Site administration → Local plugins → ManiReports → Settings**
3. Verify these settings are enabled:
   - ✓ **Enable time tracking:** Checked
   - ✓ **Heartbeat interval:** 25 seconds
   - ✓ **Session timeout:** 10 minutes

### Step 3: Clear Moodle Cache

```bash
sudo -u www-data php admin/cli/purge_caches.php
```

### Step 4: Re-Test Task 6.2

1. Login to Moodle as a student
2. Navigate to a course page
3. Stay on page for 2-3 minutes (let heartbeat requests send)
4. Run database query:

```bash
mysql -u moodle_user -p moodle_db
```

```sql
SELECT * FROM mdl_manireports_time_sessions 
WHERE userid = 12960 
ORDER BY sessionstart DESC LIMIT 1;
```

**Expected Result:** Should now show session record with:
- `userid`: 12960
- `courseid`: [course_id]
- `sessionstart`: (recent timestamp)
- `lastupdated`: (recent timestamp)
- `duration`: (calculated duration)

---

## Why This Happened

The plugin has time tracking **disabled by default** for performance reasons. The setting must be explicitly enabled:

1. **settings.php** defines the setting with default value `1` (enabled)
2. However, this only applies to **new installations**
3. On existing installations, the setting may not be in the database
4. The `record_heartbeat()` method checks: `if (!get_config('local_manireports', 'enabletimetracking')) return false;`
5. If the setting doesn't exist, `get_config()` returns `false`, so heartbeats are ignored

---

## Verification Checklist

After running the fix script, verify:

- [ ] CLI script ran successfully
- [ ] Output shows "Time Tracking Enabled: YES ✓"
- [ ] Heartbeat interval shows 25 seconds
- [ ] Session timeout shows 10 minutes
- [ ] Database tables exist
- [ ] Cache cleared
- [ ] New session records appear in database after heartbeat requests

---

## Next Steps

Once Task 6.2 is fixed:

1. **Re-run Task 6.2 test:** Verify session records are now created
2. **Run Task 6.3:** Time aggregation task
3. **Continue with Tasks 7-10**

---

## Troubleshooting

### If sessions still not appearing:

1. **Check if heartbeat requests are actually being sent:**
   - Open browser DevTools (F12)
   - Go to Network tab
   - Filter for "heartbeat"
   - Stay on course page for 1 minute
   - Should see multiple heartbeat.php requests

2. **Check if heartbeat.php is returning success:**
   - Click on heartbeat.php request
   - Go to Response tab
   - Should show: `{"success":true,"timestamp":...}`

3. **Check Moodle error log:**
   ```bash
   tail -f /var/www/html/moodledata/error.log
   ```
   - Look for any PHP errors related to time_engine or heartbeat

4. **Verify user is enrolled in course:**
   - Heartbeat requires user to have `moodle/course:view` capability
   - Check user enrollment in course

5. **Check database permissions:**
   ```bash
   mysql -u moodle_user -p moodle_db -e "SHOW GRANTS FOR 'moodle_user'@'localhost';"
   ```
   - Should have INSERT, UPDATE, DELETE permissions on manireports tables

---

## Quick Reference

**Enable Time Tracking:**
```bash
sudo -u www-data php local/manireports/cli/enable_timetracking.php
```

**Check Setting:**
```bash
mysql -u moodle_user -p moodle_db -e "SELECT * FROM mdl_config_plugins WHERE plugin='local_manireports' AND name='enabletimetracking';"
```

**View Recent Sessions:**
```bash
mysql -u moodle_user -p moodle_db -e "SELECT userid, courseid, sessionstart, lastupdated FROM mdl_manireports_time_sessions ORDER BY lastupdated DESC LIMIT 10;"
```

**Clear Sessions (for testing):**
```bash
mysql -u moodle_user -p moodle_db -e "DELETE FROM mdl_manireports_time_sessions;"
```
