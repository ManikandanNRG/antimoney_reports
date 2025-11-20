# Phase 4: Selective Cloud Offload & Flexible Scheduling

## Goal Description
Implement a robust "Selective Cloud Offload" system that allows Moodle to offload high-volume email sending and certificate generation to cloud providers (AWS/Cloudflare). Crucially, this system will be **configuration-driven**, allowing admins to specify per-schedule preferences for:
1.  **Action Type**: Send Email, Generate Certificate, or Both.
2.  **Cloud Preference**: Force Cloud, Force Local, or Auto (based on volume).
3.  **Flexible Frequency**: Support custom intervals (e.g., "Every 2 days").

## User Review Required
> [!IMPORTANT]
> **Database Changes**: This plan involves modifying the `manireports_schedules` table to add `cloud_preference`, `action_type`, and `custom_interval` columns.
> **Configuration**: Admins will need to configure Cloud Provider settings (AWS/Cloudflare) in the main settings page before Cloud Offload works.

## Proposed Changes

### Database Schema
#### [MODIFY] [install.xml](file:///D:/antigravity/manireports/local/manireports/db/install.xml)
- Add columns to `manireports_schedules`:
    - `cloud_preference` (VARCHAR 20, default 'auto')
    - `action_type` (VARCHAR 20, default 'email')
    - `custom_interval` (INT 10, default 0)
    - `suppresstarget` (INT 10, default 0) - ID of course module to check for completion
    - `suppress_course_completion` (TINYINT 1, default 0) - Stop if course is complete
- Add new tables for Cloud Jobs (as per original Phase 4 plan):
    - `manireports_cloud_jobs`
    - `manireports_cloud_job_recipients`

#### [MODIFY] [upgrade.php](file:///D:/antigravity/manireports/local/manireports/db/upgrade.php)
- Add upgrade steps to apply the above schema changes.

### UI Updates
#### [MODIFY] [ui/schedule_edit.php](file:///D:/antigravity/manireports/local/manireports/ui/schedule_edit.php)
- Add "Action Type" select (Email, Certificate, Both).
- Add "Cloud Preference" select (Auto, Force Cloud, Force Local).
- Add "Custom" to Frequency options.
- Add "Interval (days)" text field (shown only when Frequency is "Custom").
- Add "Stop Reminders" section:
    - "If Course Complete" (Checkbox).
    - "If Activity Complete" (Dropdown of course modules - only if report is course-specific).

#### [MODIFY] [ui/schedules.php](file:///D:/antigravity/manireports/local/manireports/ui/schedules.php)
- Update table to show "Action Type" and "Cloud Pref".

### Core Logic
#### [MODIFY] [classes/api/scheduler.php](file:///D:/antigravity/manireports/local/manireports/classes/api/scheduler.php)
- Update `create_schedule` and `update_schedule` to save new fields.
- Update `calculate_next_run` to handle `custom` frequency with `custom_interval`.
- Update execution logic to respect `cloud_preference` and `action_type`.
- **New Logic**: Filter recipients based on `suppresstarget` and `suppress_course_completion`.
    - Use `\core_completion\info` for course completion.
    - Use `course_modules_completion` table for activity completion.

### Cloud Backend (New Components)
#### [NEW] [classes/api/cloud_job_manager.php](file:///D:/antigravity/manireports/local/manireports/classes/api/cloud_job_manager.php)
- Logic to package data and create cloud jobs.

#### [NEW] [classes/api/cloud_connector.php](file:///D:/antigravity/manireports/local/manireports/classes/api/cloud_connector.php)
- Abstract base class for cloud providers.

#### [NEW] [classes/api/aws_connector.php](file:///D:/antigravity/manireports/local/manireports/classes/api/aws_connector.php)
- AWS SQS implementation.

#### [NEW] [ui/cloud_jobs.php](file:///D:/antigravity/manireports/local/manireports/ui/cloud_jobs.php)
- Dashboard to view offloaded job status.

## Verification Plan

### Automated Tests
- **PHPUnit**:
    - Test `scheduler::calculate_next_run` with 'custom' frequency (e.g., every 3 days).
    - Test `scheduler::create_schedule` stores new fields correctly.
    - Test `cloud_job_manager` creates DB records correctly.

### Manual Verification
1.  **Schedule Creation**:
    - Go to "Manage Schedules".
    - Create a new schedule.
    - Select "Custom" frequency -> Enter "2" days.
    - Select "Cloud Preference" -> "Force Cloud".
    - Select "Action Type" -> "Certificate Only".
    - Save.
2.  **Database Check**:
    - Verify `manireports_schedules` has correct values.
3.  **Execution (Mock)**:
    - Trigger the schedule (via CLI or waiting).
    - Verify a "Cloud Job" is created in `manireports_cloud_jobs` instead of local email sending.
