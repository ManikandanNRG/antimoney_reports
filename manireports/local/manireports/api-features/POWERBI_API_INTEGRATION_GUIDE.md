# PowerBI + ManiReports API Integration Guide

## Overview

This guide explains how to integrate ManiReports with PowerBI (or any BI tool) to visualize course completion data, user progress, and other analytics. It covers data flow, security, IOMAD multi-tenant filtering, and real-world implementation examples.

---

## YES - PowerBI Can Visualize This Data

PowerBI can connect to ManiReports via REST API and visualize:
- Course completion status and percentages
- Who completed courses and who didn't
- Completion trends over time
- User engagement metrics
- Custom report data

---

## Step 1: What Data PowerBI Will Receive

When PowerBI calls the API for Company A's course completion data, it receives this JSON response:

```json
{
  "success": true,
  "report": {
    "id": 1,
    "name": "Course Completion Report",
    "description": "Shows course completion status"
  },
  "columns": [
    "coursename",
    "shortname", 
    "enrolled",
    "completed",
    "completion_percentage"
  ],
  "data": [
    {
      "coursename": "Python Basics",
      "shortname": "PY101",
      "enrolled": 150,
      "completed": 120,
      "completion_percentage": 80.00
    },
    {
      "coursename": "Advanced Python",
      "shortname": "PY201",
      "enrolled": 80,
      "completed": 45,
      "completion_percentage": 56.25
    },
    {
      "coursename": "Web Development",
      "shortname": "WEB101",
      "enrolled": 200,
      "completed": 180,
      "completion_percentage": 90.00
    }
  ],
  "pagination": {
    "page": 0,
    "pagesize": 25,
    "total": 3,
    "totalpages": 1
  }
}
```

### PowerBI Can Visualize:
- **Bar Chart**: Course names vs completion percentage
- **Table**: Who completed, who didn't, completion %
- **KPI Cards**: Total enrolled, total completed, average completion %
- **Drill-down**: Click on a course to see individual user details
- **Trend Analysis**: Completion rates over time

---

## Step 2: How Data is Fetched (Database Source)

The API fetches from the **MAIN MOODLE DATABASE** (not custom tables):

### Moodle Tables Used:
```
├── {course}                    ← Course names, IDs
├── {enrol}                     ← Enrollment records
├── {user_enrolments}           ← User enrollment status
├── {user}                      ← User information
└── {course_completions}        ← Completion status & dates
```

### The SQL Query (from course_completion.php):

```sql
SELECT c.id,
       c.fullname AS coursename,
       c.shortname,
       COUNT(DISTINCT ue.userid) AS enrolled,
       COUNT(DISTINCT cc.userid) AS completed,
       ROUND((COUNT(DISTINCT cc.userid) * 100.0 / COUNT(DISTINCT ue.userid)), 2) AS completion_percentage
FROM {course} c
JOIN {enrol} e ON e.courseid = c.id
JOIN {user_enrolments} ue ON ue.enrolid = e.id
JOIN {user} u ON u.id = ue.userid
LEFT JOIN {course_completions} cc ON cc.course = c.id 
  AND cc.userid = ue.userid 
  AND cc.timecompleted IS NOT NULL
WHERE c.id > 1
  AND u.deleted = 0
  AND ue.status = 0
GROUP BY c.id, c.fullname, c.shortname
ORDER BY c.fullname ASC
```

**Key Points:**
- Queries live Moodle data (not cached)
- Joins enrollment and completion tables
- Calculates completion percentage dynamically
- Filters out deleted users and inactive enrollments

---

## Step 3: How Company Data is Protected (IOMAD Filtering)

This is the **critical security layer** that prevents Company B from seeing Company A's data.

### The Protection Mechanism:

When PowerBI (or any client) calls the API:

```
API Call:
GET /webservice/rest/server.php?wsfunction=local_manireports_get_report_data
    &reportid=1
    &parameters[companyid]=5
    &wstoken=COMPANY_A_TOKEN
```

**The API does this:**

1. **Authenticates the token** → Identifies which user/company is making the request
2. **Checks company access** → Verifies the user belongs to Company A (companyid=5)
3. **Applies IOMAD filter** → Modifies the SQL query to ONLY include Company A's users

### The IOMAD Filter Code (from iomad_filter.php):

```php
// Get user's companies
$companies = iomad_filter::get_user_companies($userid);
// Returns: [5] (only Company A)

// Build company filter
$companyfilter = " AND u.id IN (
    SELECT cu.userid 
    FROM {company_users} cu 
    WHERE cu.companyid IN (5)
)";

// Modified SQL becomes:
SELECT c.id, c.fullname, ...
FROM {course} c
...
WHERE c.id > 1
  AND u.deleted = 0
  AND ue.status = 0
  AND u.id IN (
    SELECT cu.userid 
    FROM {company_users} cu 
    WHERE cu.companyid = 5  ← ONLY Company A users
  )
GROUP BY ...
```

**Result:** The query ONLY returns courses and users from Company A. Company B's data is completely invisible.

---

## Step 4: Real-World Scenario

### Company A (100k users) - PowerBI Integration:

```
PowerBI Dashboard Setup:
1. Create new data source
2. Connect to: https://your-moodle.com/webservice/rest/server.php
3. Authentication: Use Company A's API token
4. Query: local_manireports_get_report_data
5. Parameters:
   - reportid: 1 (Course Completion Report)
   - companyid: 5 (Company A)
   - page: 0
   - pagesize: 100

PowerBI receives:
- 150 courses from Company A
- 100,000 enrolled users
- Completion percentages
- Can create visualizations:
  ✓ Completion rate by course
  ✓ Top 10 courses by completion
  ✓ Bottom 10 courses (at-risk)
  ✓ Completion trend over time
  ✓ User completion status table
```

### Company B (5k users) - Same Setup:

```
PowerBI Dashboard Setup:
1. Create new data source
2. Connect to: https://your-moodle.com/webservice/rest/server.php
3. Authentication: Use Company B's API token
4. Query: local_manireports_get_report_data
5. Parameters:
   - reportid: 1 (Course Completion Report)
   - companyid: 6 (Company B)
   - page: 0
   - pagesize: 100

PowerBI receives:
- 25 courses from Company B ONLY
- 5,000 enrolled users ONLY
- Company A's data is NEVER visible
```

---

## Step 5: Data Flow Diagram

```
PowerBI (Company A)
    ↓
API Call with Company A Token
    ↓
ManiReports API (external/api.php)
    ↓
Authenticate Token → Verify Company A
    ↓
Report Builder (report_builder.php)
    ↓
IOMAD Filter Applied
    ↓
Modified SQL Query:
   "SELECT ... WHERE companyid = 5"
    ↓
Moodle Database
    ↓
Returns ONLY Company A data
    ↓
PowerBI receives JSON
    ↓
PowerBI visualizes Company A data
```

---

## Step 6: Security Layers

| Layer | Protection |
|-------|-----------|
| **Authentication** | API token validates user identity |
| **Authorization** | Check if user belongs to requested company |
| **SQL Filtering** | IOMAD filter modifies query at database level |
| **Parameter Validation** | Whitelist allowed tables and columns |
| **Audit Logging** | Log all API calls for compliance |

### Authentication Flow:

```php
// 1. Token validation
$token = required_param('wstoken', PARAM_ALPHANUM);
$user = $DB->get_record('external_tokens', ['token' => $token]);

// 2. User identification
$userid = $user->userid;

// 3. Company access check
$companies = iomad_filter::get_user_companies($userid);
if (!in_array($requested_companyid, $companies)) {
    throw new moodle_exception('noaccess');
}

// 4. Query execution with filter
$sql = iomad_filter::apply_company_filter($sql, $userid, 'u', $requested_companyid);
```

---

## Step 7: What If Company B Tries to Access Company A Data?

```
PowerBI (Company B) tries:
GET /webservice/rest/server.php?wsfunction=local_manireports_get_report_data
    &reportid=1
    &parameters[companyid]=5  ← Company A's ID
    &wstoken=COMPANY_B_TOKEN

Result:
1. API authenticates token → Company B user
2. Checks access → User belongs to Company B (companyid=6)
3. Requested companyid=5 (Company A)
4. Access denied! ✗

Error returned:
{
  "success": false,
  "error": "You do not have access to this company",
  "errorcode": 403
}
```

---

## Step 8: API Endpoints Available

### 1. Get Dashboard Data
```
Function: local_manireports_get_dashboard_data
Parameters:
  - dashboardtype: admin|manager|teacher|student
  - filters: {companyid, courseid, startdate, enddate}
  - page: 0
  - pagesize: 25

Returns: Dashboard widgets with data
```

### 2. Get Report Data
```
Function: local_manireports_get_report_data
Parameters:
  - reportid: 1
  - parameters: {companyid, courseid, userid, startdate, enddate}
  - page: 0
  - pagesize: 25

Returns: Report data with columns and pagination
```

### 3. Get Report Metadata
```
Function: local_manireports_get_report_metadata
Parameters:
  - reportid: 1 (optional, omit for all reports)

Returns: Report details and available parameters
```

### 4. Get Available Reports
```
Function: local_manireports_get_available_reports
Parameters: (none)

Returns: List of all reports user can access
```

---

## Step 9: PowerBI Configuration Example

### Setting Up PowerBI Connection:

1. **Open PowerBI Desktop**
2. **Get Data → Web**
3. **Enter URL:**
   ```
   https://your-moodle.com/webservice/rest/server.php?wsfunction=local_manireports_get_report_data&reportid=1&parameters[companyid]=5&wstoken=YOUR_TOKEN&moodlewsrestformat=json
   ```

4. **Load Data**
5. **Transform Data** (if needed)
6. **Create Visualizations:**
   - Bar chart: Course vs Completion %
   - Table: Detailed course data
   - KPI: Total completion rate
   - Gauge: Average completion %

### Refresh Schedule:
- Set refresh frequency in PowerBI (hourly, daily, etc.)
- Each refresh calls the API with current parameters
- Data is always up-to-date with Moodle

---

## Step 10: Pagination for Large Datasets

For companies with 100k+ users, use pagination:

```
First call:
GET /webservice/rest/server.php?wsfunction=local_manireports_get_report_data
    &reportid=1
    &parameters[companyid]=5
    &page=0
    &pagesize=100
    &wstoken=TOKEN

Response:
{
  "data": [...100 records...],
  "pagination": {
    "page": 0,
    "pagesize": 100,
    "total": 5000,
    "totalpages": 50
  }
}

Next call:
&page=1  ← Get next 100 records
```

---

## Step 11: Performance Considerations

### Query Optimization:
- Queries are optimized with proper JOINs
- Indexes on foreign keys and frequently filtered columns
- Pre-aggregation for heavy metrics (optional)
- Caching available for repeated queries

### Timeout Settings:
- Default query timeout: 60 seconds
- Configurable in admin settings
- Large datasets may need pagination

### Best Practices:
- Use pagination for large result sets
- Filter by date range when possible
- Limit to specific company/course when possible
- Schedule refreshes during off-peak hours

---

## Step 12: Troubleshooting

### Issue: "Access Denied" Error
**Solution:** Verify user token belongs to the requested company

### Issue: No Data Returned
**Solution:** Check if company has enrolled users in courses

### Issue: Query Timeout
**Solution:** Use pagination or filter by date range

### Issue: Slow Performance
**Solution:** Check database indexes, use caching, reduce page size

---

## Summary

| Question | Answer |
|----------|--------|
| **Can PowerBI see course completion data?** | ✅ YES - via API |
| **What data is returned?** | Enrolled count, completed count, completion % |
| **Where is data fetched from?** | Main Moodle database (course, enrol, user_enrolments, course_completions) |
| **How is Company A protected?** | IOMAD filter modifies SQL to only include Company A users |
| **Can Company B see Company A data?** | ❌ NO - API checks company access before returning data |
| **Is data filtered at DB level?** | ✅ YES - SQL query is modified before execution |
| **Can Company A see other companies?** | ❌ NO - unless they're site admin |
| **Is data real-time?** | ✅ YES - queries live Moodle database |
| **Can I use custom reports?** | ✅ YES - via GUI or SQL report builder |
| **Is pagination supported?** | ✅ YES - for large datasets |

---

## Enterprise-Grade Security

This implementation provides:
- ✅ Multi-tenant isolation (IOMAD)
- ✅ Token-based authentication
- ✅ Role-based access control
- ✅ SQL injection prevention
- ✅ Audit logging
- ✅ Company-level data filtering
- ✅ Capability-based authorization

Perfect for organizations with 100k+ users across multiple companies!
