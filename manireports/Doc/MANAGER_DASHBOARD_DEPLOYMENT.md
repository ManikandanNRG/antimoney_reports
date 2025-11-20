# Manager Dashboard - Deployment & Testing Guide

**Task**: Task 13.3 - Manager Dashboard Implementation
**Status**: ✅ COMPLETE
**Time Taken**: 30 minutes

---

## What Was Implemented

### 1. Dashboard Renderer Updates
**File**: `classes/output/dashboard_renderer.php`

**Changes**:
- ✅ Implemented `get_manager_widgets()` with company filtering
- ✅ Updated `render_manager_dashboard()` to include company data
- ✅ Added `get_user_company()` helper method
- ✅ Added `get_company_users_list()` method
- ✅ Added `get_company_courses_list()` method

**Features**:
- Company-specific KPI widgets (users, courses, enrollments, completions)
- Active/inactive user tracking per company
- Recent users list (top 10)
- Company courses with completion rates

### 2. Template Updates
**File**: `templates/dashboard_manager.mustache`

**Features**:
- Company name display
- 6 KPI widgets with company-filtered data
- Recent users table
- Company courses table with completion rates
- Quick links to reports
- Warning message if no company assigned

### 3. Language Strings
**File**: `lang/en/local_manireports.php`

**Added**:
- managerdashboard
- companyusers
- companycourses
- nocompanyassigned
- recentusers
- nousers
- nocourses
- enrolled
- completed
- rate
- company

---

## Deployment Steps

### Step 1: Upload Files to Server
```bash
# Upload the modified files via your method (Git, SCP, etc.)
# Files to upload:
# - classes/output/dashboard_renderer.php
# - templates/dashboard_manager.mustache
# - lang/en/local_manireports.php
```

### Step 2: Purge Caches
```bash
# SSH into your server
ssh user@dev.aktrea.net

# Purge all caches
sudo -u www-data php /var/www/html/admin/cli/purge_caches.php
```

### Step 3: Verify Database Tables
```bash
# Check if IOMAD tables exist
mysql -u moodle_user -p moodle_db -e "SHOW TABLES LIKE '%company%';"

# Expected tables:
# - company
# - company_users
# - company_course
```

---

## Testing Instructions

### Test 1: Access Manager Dashboard

**Prerequisites**:
- User must have `local/manireports:viewmanagerdashboard` capability
- User must be assigned to a company in IOMAD

**Steps**:
1. Login as a company manager user
2. Navigate to: `https://dev.aktrea.net/local/manireports/ui/dashboard.php`
3. Verify the dashboard loads

**Expected Result**:
- Dashboard title: "Manager Dashboard"
- Company name displayed at top
- 6 KPI widgets showing company-specific data
- Recent users table (up to 10 users)
- Company courses table (up to 10 courses)
- Quick links to reports

**If No Company Assigned**:
- Warning message: "No company assigned to your account"
- No widgets or data displayed

---

### Test 2: Verify Company Filtering

**Steps**:
1. Note the company name displayed
2. Check the KPI values (users, courses, etc.)
3. Compare with actual company data in IOMAD

**Verification Queries**:
```sql
-- Get company ID for user
SELECT cu.companyid, c.name 
FROM mdl_company_users cu
JOIN mdl_company c ON c.id = cu.companyid
WHERE cu.userid = YOUR_USER_ID;

-- Count users in company
SELECT COUNT(*) FROM mdl_company_users WHERE companyid = COMPANY_ID;

-- Count courses in company
SELECT COUNT(*) FROM mdl_company_course WHERE companyid = COMPANY_ID;
```

**Expected Result**:
- Dashboard numbers match database queries
- Only company-specific data is shown
- No data from other companies visible

---

### Test 3: Test Recent Users List

**Steps**:
1. Check the "Recent Users" table
2. Verify users belong to the manager's company
3. Check last access dates

**Expected Result**:
- Up to 10 users displayed
- All users belong to manager's company
- Last access dates are accurate
- Users sorted by lastname, firstname

---

### Test 4: Test Company Courses List

**Steps**:
1. Check the "Company Courses" table
2. Verify completion rates are calculated correctly
3. Check enrolled and completed user counts

**Expected Result**:
- Up to 10 courses displayed
- All courses belong to manager's company
- Completion rate = (completed / enrolled) * 100
- Numbers are accurate

---

### Test 5: Test Report Links

**Steps**:
1. Click each report link:
   - Course Completion
   - Course Progress
   - User Engagement
   - Schedules
2. Verify reports load with company filtering

**Expected Result**:
- All links work
- Reports show only company-specific data
- No errors in browser console

---

## Troubleshooting

### Issue 1: "No company assigned" Warning

**Cause**: User is not assigned to any company in IOMAD

**Solution**:
```sql
-- Check user's company assignment
SELECT * FROM mdl_company_users WHERE userid = YOUR_USER_ID;

-- If no record, assign user to a company
INSERT INTO mdl_company_users (companyid, userid) VALUES (COMPANY_ID, USER_ID);
```

### Issue 2: Dashboard Shows Zero Values

**Cause**: Company has no users or courses assigned

**Solution**:
```sql
-- Assign users to company
INSERT INTO mdl_company_users (companyid, userid) VALUES (COMPANY_ID, USER_ID);

-- Assign courses to company
INSERT INTO mdl_company_course (companyid, courseid) VALUES (COMPANY_ID, COURSE_ID);
```

### Issue 3: Template Not Loading

**Cause**: Cache not cleared or template syntax error

**Solution**:
```bash
# Purge caches again
sudo -u www-data php /var/www/html/admin/cli/purge_caches.php

# Check Moodle error log
tail -50 /opt/moodledata/moodledata.log | grep manireports
```

### Issue 4: Database Errors

**Cause**: IOMAD tables don't exist or have different structure

**Solution**:
```bash
# Check if IOMAD is installed
ls -la /var/www/html/local/iomad/

# Check IOMAD version
grep '$plugin->version' /var/www/html/local/iomad/version.php
```

---

## Verification Checklist

Before marking complete, verify:

- [ ] Dashboard loads without errors
- [ ] Company name is displayed
- [ ] All 6 KPI widgets show correct values
- [ ] Recent users table displays (if users exist)
- [ ] Company courses table displays (if courses exist)
- [ ] All report links work
- [ ] Company filtering is working (no other company data visible)
- [ ] No errors in browser console
- [ ] No errors in Moodle error log
- [ ] Template renders correctly on mobile devices

---

## Next Steps

After successful testing:

1. ✅ Mark Task 13.3 as complete
2. ⏳ Move to Task 13.4: Teacher Dashboard (1.5 hours)
3. ⏳ Continue with remaining tasks

---

## Notes

- Manager dashboard requires IOMAD to be installed
- If IOMAD is not installed, dashboard will show warning
- Company filtering is automatic based on user's company assignment
- Dashboard is responsive and works on mobile devices

**Estimated Testing Time**: 15-20 minutes
