# Reminder Feature Specification - Version 1

## Overview
A native reminder system within `local_manireports` to send automated emails to users based on their course progress and engagement. This feature aims to replace external re-engagement plugins by leveraging existing data and the "Cloud Offload" capability.

## Database Schema

### 1. `manireports_reminders`
Stores the configuration for reminder rules.

| Field | Type | Description |
|---|---|---|
| `id` | int | Primary Key |
| `name` | char(255) | Name of the reminder (e.g., "Course Inactivity Warning") |
| `companyid` | int | IOMAD Company ID (0 for global/all) |
| `courseid` | int | Specific Course ID (0 for all courses) |
| `event_type` | char(50) | Trigger type (see Event Types below) |
| `condition_value` | int | Value for the condition (e.g., 7 for "7 days") |
| `subject` | char(255) | Email subject |
| `body` | text | Email body (HTML) |
| `enabled` | int(1) | 1 = Enabled, 0 = Disabled |
| `cloud_offload` | int(1) | 1 = Use Cloud Offload, 0 = Local Mail |
| `timecreated` | int | Timestamp |
| `timemodified` | int | Timestamp |
| `createdby` | int | User ID of creator |

### 2. `manireports_reminder_log`
Tracks sent reminders to prevent duplicates.

| Field | Type | Description |
|---|---|---|
| `id` | int | Primary Key |
| `reminderid` | int | FK to `manireports_reminders` |
| `userid` | int | User who received the reminder |
| `courseid` | int | Course context |
| `timesent` | int | Timestamp when sent |

## Event Types
The system will support the following trigger events, covering and exceeding standard re-engagement capabilities:

1.  **`after_enrollment`**: Send X days/hours after user is enrolled. (Standard Re-engagement)
2.  **`course_not_accessed`**: User enrolled but hasn't accessed the course for X days. (Re-engagement "Notify on inactivity")
3.  **`course_not_completed`**: User enrolled for X days but hasn't completed the course.
4.  **`activity_not_completed`**: User has not completed a specific activity by X days after enrollment.
5.  **`course_due_soon`**: Course due date is in X days.
6.  **`course_overdue`**: Course due date has passed by X days.

## Advanced Constraints (The "Pro" Features)
To ensure this is the "latest and updated" solution:
*   **`suppress_if_completed`**: Automatically cancel the reminder if the user completes the course before the trigger time.
*   **`target_cohort`**: Limit the reminder to specific cohorts (optional).
*   **`repeat_reminder`**: Option to repeat this email every X days until completion (up to a max limit).

## Email Placeholders
Supported placeholders in Subject and Body:
*   `{firstname}`
*   `{lastname}`
*   `{coursename}`
*   `{courselink}`
*   `{duedate}`
*   `{days_since_access}`
*   `{days_until_due}`

## Architecture

### Backend
*   **`classes/api/ReminderManager.php`**: Core logic to evaluate rules and fetch eligible users.
*   **`classes/task/process_reminders.php`**: Scheduled task (runs hourly/daily) to trigger the process.
*   **Integration**:
    *   Uses `CloudJobManager::create_job` for offloading.
    *   Uses Moodle's `email_to_user` for local sending.

### UI/UX
*   **Location**: New tab "Reminders" in the ManiReports Dashboard (`ui/dashboard.php`).
*   **List View**: Table showing active reminders with status toggle and "Run Now" button.
*   **Edit View**: Form to configure the reminder rule.

## Cloud Offload Integration
If `cloud_offload` is enabled:
1.  `ReminderManager` collects all recipients for a rule.
2.  Groups them by Company.
3.  Calls `CloudJobManager` to queue the job.
4.  Logs the "sent" status in `manireports_reminder_log` immediately (assuming successful queueing).

## Limitations
*   Requires Cron to be running.
*   "Not Accessed" relies on Moodle's log or `user_lastaccess` table.
