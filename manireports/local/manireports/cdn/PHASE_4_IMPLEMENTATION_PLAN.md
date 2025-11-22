# Phase 4: Selective Cloud Offload & Flexible Scheduling (Merged Plan)

## Goal Description
Implement a robust "Selective Cloud Offload" system that allows Moodle to offload high-volume email sending and certificate generation to cloud providers (AWS/Cloudflare).

**Key Strategy (Merged Approach):**
1.  **Moodle Plugin Logic**: Use the **IOMAD-safe** approach (intercepting events, handling `mdl_user_preferences` for temp passwords, and suppressing `mdl_email` duplicates).
2.  **Cloud Worker Logic**: Adopt the **"Other AI"** Python/JS worker code for AWS Lambda/Cloudflare Workers as it is robust and ready-to-use.

## User Review Required
> [!IMPORTANT]
> **Database Changes**: This plan involves modifying the `manireports_schedules` table and adding new `manireports_cloud_*` tables.
> **IOMAD Specifics**: This implementation assumes IOMAD is present. It interacts with `block_iomad_company_admin` events and `mdl_companylicense_users`.

## Proposed Changes

### Database Schema
#### [MODIFY] [install.xml](file:///D:/antigravity/manireports/local/manireports/db/install.xml)
- Add columns to `manireports_schedules`:
    - `cloud_preference` (VARCHAR 20, default 'auto')
    - `action_type` (VARCHAR 20, default 'email')
    - `custom_interval` (INT 10, default 0)
    - `suppresstarget` (INT 10, default 0)
    - `suppress_course_completion` (TINYINT 1, default 0)
- Add new tables for Cloud Jobs:
    - `manireports_cloud_jobs` (Stores job metadata, status, counts)
    - `manireports_cloud_job_recipients` (Stores recipient data, status, error logs)
    - `manireports_cloud_company_settings` (Stores per-company AWS/Cloudflare credentials)

#### [MODIFY] [upgrade.php](file:///D:/antigravity/manireports/local/manireports/db/upgrade.php)
- Add upgrade steps to apply the above schema changes.

### Core Logic (IOMAD-Safe)
#### [NEW] [classes/api/CloudJobManager.php](file:///D:/antigravity/manireports/local/manireports/classes/api/CloudJobManager.php)
- **Purpose**: Central manager for creating and tracking jobs.
- **Key Methods**: `create_job`, `submit_job`, `handle_callback`.

#### [NEW] [classes/api/EmailOffloadHandler.php](file:///D:/antigravity/manireports/local/manireports/classes/api/EmailOffloadHandler.php)
- **Purpose**: Intercepts events and prepares data.
- **Critical Logic**:
    - **Temp Passwords**: Fetch from `get_user_preferences('iomad_temporary')`.
    - **Suppression**: Delete corresponding records from `mdl_email` to prevent duplicates.
    - **Events**: Listen to `\core\event\user_created` and `\block_iomad_company_admin\event\user_license_assigned`.

#### [NEW] [classes/api/connectors/AwsConnector.php](file:///D:/antigravity/manireports/local/manireports/classes/api/connectors/AwsConnector.php)
- **Purpose**: Sends payload to AWS SQS.

### Cloud Workers (Adopted from Other AI)
#### [NEW] [cloud_workers/aws/lambda_handler.py](file:///D:/antigravity/manireports/local/manireports/cloud_workers/aws/lambda_handler.py)
- **Purpose**: AWS Lambda function to process SQS messages and send SES emails.

#### [NEW] [cloud_workers/cloudflare/worker.js](file:///D:/antigravity/manireports/local/manireports/cloud_workers/cloudflare/worker.js)
- **Purpose**: Cloudflare Worker script for email processing.

### UI Updates
#### [MODIFY] [ui/schedule_edit.php](file:///D:/antigravity/manireports/local/manireports/ui/schedule_edit.php)
- Add Cloud Offload controls (Action Type, Cloud Preference).

#### [NEW] [ui/cloud_jobs.php](file:///D:/antigravity/manireports/local/manireports/ui/cloud_jobs.php)
- Dashboard to view offloaded job status.

## Verification Plan

### Automated Tests
- **PHPUnit**:
    - Test `CloudJobManager` creates DB records correctly.
    - Test `EmailOffloadHandler` correctly identifies IOMAD temp passwords.

### Manual Verification
1.  **IOMAD License Allocation**:
    - Allocate licenses to a user.
    - Verify `manireports_cloud_jobs` has a new job.
    - Verify `mdl_email` does **NOT** have the default email (suppression worked).
2.  **CSV Upload**:
    - Upload users via CSV.
    - Verify Cloud Job created with correct temp passwords.
