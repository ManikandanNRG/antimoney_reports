# Deployment Guide for Tasks 4.2 & 4.3: SQL Validator and Custom Report Management

## Overview
This guide covers deployment of the enhanced SQL validator with strict security controls and the complete custom report management system with CRUD operations.

## What Was Implemented

### Task 4.2: SQL Validator and Security Layer ✅
**Enhanced Security Features:**
1. **Comment Removal** - Strips SQL comments to prevent injection
2. **Strict Keyword Blocking** - Word boundary matching for blocked keywords (DROP, CREATE, ALTER, etc.)
3. **Multiple Statement Prevention** - Blocks semicolon-separated queries
4. **UNION Query Validation** - Validates each part of UNION queries
5. **Dangerous Function Blocking** - Blocks LOAD_FILE, BENCHMARK, SLEEP, etc.
6. **Enhanced Table Validation** - Supports both {tablename} and mdl_tablename formats
7. **Parameter Validation** - Validates parameter naming and prevents ? placeholders
8. **Query Timeout Enforcement** - Enforces configurable timeout limits
9. **Execution Time Tracking** - Returns execution time with results

**Files Modified:**
- `local/manireports/classes/api/report_builder.php` - Enhanced validation methods
- `local/manireports/lang/en/local_manireports.php` - Added error messages

### Task 4.3: Custom Report Management ✅
**CRUD Operations:**
1. **Create** - Save new custom SQL reports with validation
2. **Read** - List and view custom reports
3. **Update** - Edit existing reports
4. **Delete** - Remove reports (with cascade delete of schedules)
5. **Execute** - Run custom reports with pagination

**New Files Created:**
- `local/manireports/ui/custom_reports.php` - List custom reports
- `local/manireports/ui/custom_report_edit.php` - Create/edit form

**Files Modified:**
- `local/manireports/classes/api/report_builder.php` - CRUD methods already existed
- `local/manireports/ui/report_view.php` - Added support for custom reports by ID
- `local/manireports/settings.php` - Added admin menu links
- `local/manireports/lang/en/local_manireports.php` - Added UI strings

## Deployment Steps

### 1. SSH into EC2 Instance
```bash
ssh user@your-ec2-instance.com
```

### 2. Navigate to Moodle Directory
```bash
cd /var/www/html/moodle
```

### 3. Deploy Updated Files
```bash
# If using Git
cd local/manireports
git pull origin main

# If using SCP (from local machine)
scp -r local/manireports/classes/api/report_builder.php user@ec2:/var/www/html/moodle/local/manireports/classes/api/
scp -r local/manireports/ui/custom_reports.php user@ec2:/var/www/html/moodle/local/manireports/ui/
scp -r local/manireports/ui/custom_report_edit.php user@ec2:/var/www/html/moodle/local/manireports/ui/
scp -r local/manireports/ui/report_view.php user@ec2:/var/www/html/moodle/local/manireports/ui/
scp -r local/manireports/settings.php user@ec2:/var/www/html/moodle/local/manireports/
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

### Test 1: SQL Validator - Blocked Keywords
1. Navigate to: Site Administration → Plugins → Local plugins → ManiReports → Custom Reports
2. Click "Create Custom Report"
3. Try to create a report with blocked keywords:

**Test Case 1: DROP keyword**
```sql
SELECT * FROM {user}; DROP TABLE {user};
```
**Expected:** Validation error - "Blocked keyword found: DROP"

**Test Case 2: INSERT keyword**
```sql
INSERT INTO {user} (username) VALUES ('hacker')
```
**Expected:** Validation error - "Query must start with SELECT"

**Test Case 3: UNION with non-whitelisted table**
```sql
SELECT id FROM {user} UNION SELECT id FROM {config}
```
**Expected:** Validation error - "Table not whitelisted: config"

### Test 2: SQL Validator - Dangerous Functions
**Test Case: LOAD_FILE function**
```sql
SELECT LOAD_FILE('/etc/passwd') FROM {user}
```
**Expected:** Validation error - "Dangerous function found: LOAD_FILE"

### Test 3: SQL Validator - Valid Query
**Test Case: Valid report**
```sql
SELECT u.id, u.firstname, u.lastname, u.email, u.lastaccess
FROM {user} u
WHERE u.deleted = 0 AND u.suspended = 0
ORDER BY u.lastname ASC
```
**Expected:** Report saves successfully

### Test 4: Custom Report CRUD - Create
1. Navigate to: Custom Reports
2. Click "Create Custom Report"
3. Fill in:
   - Name: "Active Users Report"
   - Description: "List of all active users"
   - SQL Query: (use valid query from Test 3)
4. Click "Save Report"

**Expected:**
- Success message: "Report created successfully"
- Redirected to custom reports list
- New report appears in list

### Test 5: Custom Report CRUD - Read/List
1. Navigate to: Custom Reports
2. Verify report list displays:
   - Report name
   - Description
   - Type (SQL)
   - Created date
   - Action buttons (View, Edit, Delete)

### Test 6: Custom Report CRUD - Execute
1. From custom reports list, click "View" on a report
2. Verify:
   - Report executes successfully
   - Results displayed in table
   - Execution time shown
   - Pagination works (if > 25 rows)
   - Export button available

### Test 7: Custom Report CRUD - Update
1. From custom reports list, click "Edit" on a report
2. Modify the description
3. Click "Save Report"

**Expected:**
- Success message: "Report updated successfully"
- Changes reflected in report list

### Test 8: Custom Report CRUD - Delete
1. From custom reports list, click "Delete" on a report
2. Confirm deletion in popup

**Expected:**
- Confirmation dialog appears
- After confirmation: "Report deleted successfully"
- Report removed from list

### Test 9: Query Timeout Enforcement
1. Create a report with a slow query (if possible):
```sql
SELECT u1.id, u2.id, COUNT(*)
FROM {user} u1
CROSS JOIN {user} u2
CROSS JOIN {course} c
GROUP BY u1.id, u2.id
```
2. Set query timeout to 5 seconds: Site Administration → Plugins → Local plugins → ManiReports → Query timeout
3. Execute the report

**Expected:**
- If query exceeds 5 seconds: "Query execution exceeded timeout limit of 5 seconds"
- Execution stops gracefully

### Test 10: Parameter Validation
1. Create a report with parameters:
```sql
SELECT * FROM {user} WHERE id = :userid
```
2. Execute report without providing :userid parameter

**Expected:** Error - "Missing parameter value: userid"

### Test 11: Admin Menu Navigation
1. Navigate to: Site Administration → Plugins → Local plugins → ManiReports
2. Verify menu items appear:
   - Dashboard
   - Custom Reports
   - Schedules
   - Audit Logs

3. Click each link and verify pages load correctly

### Test 12: Capability Enforcement
1. Log in as a non-admin user (teacher or student)
2. Try to access: /local/manireports/ui/custom_reports.php

**Expected:** "You do not have permission to access this page"

3. Grant capability: Site Administration → Users → Permissions → Define roles
4. Edit "Manager" role, add "local/manireports:customreports"
5. Log in as manager, verify access granted

### Test 13: Audit Logging
1. Create a custom report
2. Navigate to: Audit Logs
3. Verify entry exists:
   - Action: "create"
   - Object Type: "report"
   - Details: Report name

4. Update the report
5. Verify new audit entry: Action "update"

6. Delete the report
7. Verify new audit entry: Action "delete"

## Troubleshooting

### Issue: "Invalid SQL query" error on valid query
**Solution:**
- Check if all tables are whitelisted
- View allowed tables: They're displayed on the create/edit form
- Add table to whitelist if needed (requires code modification)

### Issue: Query timeout too short
**Solution:**
```bash
# Increase timeout via CLI
sudo -u www-data php admin/cli/cfg.php --name=local_manireports/querytimeout --set=120
```

### Issue: Custom reports page shows blank
**Solution:**
```bash
# Check PHP error log
tail -f /var/log/apache2/error.log

# Check Moodle error log
tail -f /var/www/html/moodledata/error.log

# Clear caches
sudo -u www-data php admin/cli/purge_caches.php
```

### Issue: "Class not found" error
**Solution:**
```bash
# Verify file permissions
ls -la /var/www/html/moodle/local/manireports/classes/api/report_builder.php

# Should be readable by www-data
sudo chown www-data:www-data /var/www/html/moodle/local/manireports/classes/api/report_builder.php
```

### Issue: SQL injection test passes (security concern)
**Solution:**
- This should NOT happen with the enhanced validator
- Check that report_builder.php was properly updated
- Verify validate_sql() method includes all security checks
- Report as critical bug if injection is possible

## Security Verification Checklist

- [ ] Blocked keywords (DROP, CREATE, ALTER, etc.) are rejected
- [ ] Multiple statements (semicolon-separated) are rejected
- [ ] Dangerous functions (LOAD_FILE, BENCHMARK, etc.) are rejected
- [ ] Non-whitelisted tables are rejected
- [ ] SQL comments are stripped
- [ ] UNION queries are validated
- [ ] Parameter placeholders are validated
- [ ] Query timeout is enforced
- [ ] Capability checks prevent unauthorized access
- [ ] Audit logging captures all CRUD operations

## Performance Verification

- [ ] Report execution completes within timeout
- [ ] Execution time is displayed
- [ ] Pagination works for large result sets
- [ ] Count query doesn't timeout
- [ ] Multiple concurrent reports don't crash server

## Success Criteria

Tasks 4.2 and 4.3 are successfully deployed when:

1. **SQL Validator (4.2):**
   - All blocked keywords are rejected
   - Dangerous functions are blocked
   - Non-whitelisted tables are rejected
   - Query timeout is enforced
   - No SQL injection is possible

2. **Custom Report Management (4.3):**
   - Users can create custom SQL reports
   - Reports can be listed, viewed, edited, and deleted
   - Reports execute correctly with pagination
   - Admin menu links work
   - Capability enforcement works
   - Audit logging captures all operations

## Next Steps

After successful deployment:
1. Test with real-world queries
2. Monitor query performance
3. Adjust timeout settings if needed
4. Train users on custom report creation
5. Document common report patterns
6. Proceed to Task 8.3 (Cache integration)

## Support

If issues persist:
1. Check all error logs
2. Verify file permissions
3. Confirm database tables exist
4. Test with simple queries first
5. Gradually increase complexity
