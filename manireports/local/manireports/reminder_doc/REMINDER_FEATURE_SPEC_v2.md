# Reminder Feature Specification - Version 2 (Final)

## Overview
A native, enterprise-grade reminder system within `local_manireports` to send automated emails to users based on their course progress and engagement. This feature completely replaces external re-engagement plugins by leveraging existing data and the "Cloud Offload" capability.

## Terminology
*   **Reminder Rule**: Configuration defining trigger, frequency, template, recipients (per company/course/activity).
*   **Reminder Instance**: An applied rule for a specific user + course (tracks state through reminder sequence).
*   **Reminder Job**: Single send attempt for a single recipient (enqueued to SQS).
*   **Template**: Reusable email content with placeholders.
*   **Campaign**: Optional grouping of jobs with same template/time window (for analytics).

## Database Schema

### 1. `manireports_reminder_rule`
Configuration for reminder rules.

| Field | Type | Description |
|---|---|---|
| `id` | int(10) | Primary Key |
| `companyid` | int(10) | IOMAD Company ID (0 = global) |
| `name` | char(255) | Rule name |
| `courseid` | int(10) | Course ID (nullable, 0 = all courses) |
| `activityid` | int(10) | Activity ID (nullable) |
| `trigger_type` | char(20) | ENUM: 'enrol', 'start_date', 'incomplete_after', 'custom' |
| `trigger_value` | text | JSON/Int value for trigger (days/hours) |
| `emaildelay` | int(10) | Seconds between reminders |
| `remindercount` | int(10) | Max number of reminders to send |
| `send_to_user` | int(1) | Bool: Send to learner |
| `send_to_managers` | int(1) | Bool: Send to managers |
| `thirdparty_emails` | text | CSV of external emails |
| `templateid` | int(10) | FK to `manireports_template` |
| `enabled` | int(1) | Bool |
| `timecreated` | int(10) | Timestamp |
| `timemodified` | int(10) | Timestamp |

**Indexes**:
- `companyid`
- `enabled`
- `courseid`

### 2. `manireports_reminder_instance`
Tracks the state of a reminder for a specific user (state machine).

| Field | Type | Description |
|---|---|---|
| `id` | int(10) | Primary Key |
| `ruleid` | int(10) | FK to `manireports_reminder_rule` |
| `userid` | int(10) | User ID |
| `courseid` | int(10) | Course ID |
| `activityid` | int(10) | Activity ID (nullable) |
| `next_send` | int(10) | Timestamp for next send |
| `emailsent` | int(10) | Count of emails sent so far |
| `completed` | int(1) | Bool: If user completed the goal |
| `deadline` | int(10) | Optional deadline timestamp |
| `timecreated` | int(10) | Timestamp |

**Indexes**:
- `next_send, completed` (composite for cron query)
- `ruleid, userid` (composite for lookups)
- `userid, courseid`

### 3. `manireports_reminder_job` (Audit)
Audit log for every send attempt.

| Field | Type | Description |
|---|---|---|
| `id` | int(10) | Primary Key |
| `instanceid` | int(10) | FK to `manireports_reminder_instance` |
| `message_id` | char(36) | UUID for deduplication |
| `job_id` | int(10) | Cloud Job ID (FK to manireports_cloud_jobs) |
| `recipient_email` | char(255) | Email address |
| `status` | char(20) | ENUM: 'enqueued', 'submitted', 'delivered', 'failed', 'local_sent' |
| `attempts` | int(10) | Retry attempts |
| `last_attempt_ts` | int(10) | Timestamp of last attempt |
| `payload` | text | JSON payload sent |
| `error` | text | Error message |

**Indexes**:
- `status, last_attempt_ts` (for retry queries)
- `message_id` (unique for deduplication)
- `instanceid`

### 4. `manireports_template`
Reusable email templates.

| Field | Type | Description |
|---|---|---|
| `id` | int(10) | Primary Key |
| `companyid` | int(10) | Company ID (0 = global) |
| `name` | char(255) | Template name |
| `subject` | char(255) | Email subject |
| `body_html` | text | HTML Body |
| `body_text` | text | Text Body (auto-generated from HTML) |
| `placeholders` | text | JSON list of used placeholders |
| `enabled` | int(1) | Bool |

**Indexes**:
- `companyid, enabled`

## Trigger Types & Logic

### Supported Triggers
1.  **`enrol`**: Send X days/hours after user is enrolled
2.  **`start_date`**: Send on specific date (course start date + offset)
3.  **`incomplete_after`**: User enrolled for X days but hasn't completed
4.  **`custom`**: JSON-based custom logic (extensible)

### Trigger Value Format
```json
{
  "days": 7,
  "hours": 0,
  "condition": "not_accessed" // or "not_completed", "overdue"
}
```

## Email Placeholders
Supported placeholders in Subject and Body:
*   `{firstname}`, `{lastname}`, `{email}`
*   `{coursename}`, `{courselink}`
*   `{activityname}`, `{activitylink}`
*   `{duedate}`, `{days_left}`, `{days_since_access}`
*   `{profile_*}` (any Moodle profile field)

## Architecture

### Backend Components

#### 1. **`classes/api/ReminderManager.php`**
Core business logic:
- `create_rule($data)`: Create reminder rule
- `get_eligible_users($ruleid)`: Evaluate trigger conditions
- `create_instances($ruleid)`: Spawn instances for eligible users
- `render_template($templateid, $user, $course)`: Placeholder replacement

#### 2. **`classes/task/process_reminders.php`**
Scheduled task (runs every 5-10 minutes):
```php
1. Query: SELECT * FROM reminder_instance 
   WHERE next_send <= NOW() AND completed = 0 AND emailsent < max
2. For each instance:
   a. Atomic claim: UPDATE ... SET emailsent = emailsent + 1 
      WHERE id = ? AND emailsent = ?
   b. If claim successful (1 row updated):
      - Render template
      - Create reminder_job record
      - Submit to CloudJobManager OR send locally
   c. If claim failed (0 rows): Skip (another process claimed it)
```

#### 3. **`classes/api/TemplateEngine.php`**
Template rendering and placeholder replacement.

### Atomic Claiming (Concurrency Safety)
**Problem**: Multiple cron processes could send duplicate emails.

**Solution**: Compare-and-swap UPDATE
```sql
UPDATE manireports_reminder_instance 
SET emailsent = emailsent + 1, 
    next_send = ? 
WHERE id = ? 
  AND emailsent = ? -- Current value
  AND completed = 0
```
- If UPDATE returns 1 row → We claimed it
- If UPDATE returns 0 rows → Someone else claimed it, skip

### Cloud Offload Integration
If rule has cloud offload enabled:
1.  `ReminderManager` collects all recipients (user + managers + third-party)
2.  Creates `reminder_job` records with `message_id` (UUID)
3.  Calls `CloudJobManager::create_job()` with payload
4.  Lambda deduplicates using `message_id` (DynamoDB/Redis TTL)
5.  Lambda sends via SES and calls back to update `reminder_job.status`

### UI/UX

#### Admin Screens
1.  **Rules List** (`ui/reminders.php`):
    - Table: Name, Company, Course, Trigger, Status, Actions
    - Actions: Edit, Disable, Delete, "Run Now"

2.  **Rule Editor** (`ui/reminder_edit.php`):
    - Form: Name, Company, Course, Trigger Type, Delay, Count, Template
    - Template selector with preview

3.  **Template Manager** (`ui/templates.php`):
    - CRUD for templates
    - Live preview with sample data

4.  **Delivery Dashboard** (`ui/reminder_dashboard.php`):
    - KPIs: Queued, Sent, Failed
    - Chart: Delivery trends
    - Table: Recent jobs with status

## Suppression & Completion Logic

### Auto-Suppression
Before sending, check:
1.  **Course completion**: If user completed course → mark instance as `completed = 1`, skip
2.  **Activity completion**: If user completed target activity → skip
3.  **Unsubscribe**: Check user preference `reminder_unsubscribe` → skip
4.  **Deadline passed**: If `deadline < now` → mark expired, skip

### Manager Notifications
If `send_to_managers = 1`:
- Query IOMAD company_users for manager relationships
- Create separate `reminder_job` for each manager
- Use same template but with manager-specific placeholders

## Performance Optimizations

### Indexing Strategy
All hot queries are indexed:
- Cron query: `(next_send, completed)` composite index
- Lookup: `(ruleid, userid)` composite index
- Audit: `(status, last_attempt_ts)` for retry logic

### Cleanup Task
Add to `cleanup_old_data` task:
```php
// Delete reminder_job records older than 90 days
DELETE FROM manireports_reminder_job 
WHERE last_attempt_ts < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 90 DAY))
```

## Limitations & Constraints
*   Requires Moodle Cron to be running (every 1-5 minutes recommended)
*   "Not Accessed" trigger relies on `user_lastaccess` table accuracy
*   Manager detection requires IOMAD company structure
*   Cloud offload requires AWS/Cloudflare configuration
