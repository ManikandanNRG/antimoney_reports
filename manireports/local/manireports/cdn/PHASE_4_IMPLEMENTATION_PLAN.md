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

### UI Implementation (Dashboard V6 Integration)

### [NEW] [classes/output/cloud_offload_data_loader.php](file:///D:/antigravity/manireports/local/manireports/classes/output/cloud_offload_data_loader.php)
- **Purpose**: Dedicated data loader for Email and Certificate tabs (Cloud Offload features).
- **Methods**:
    - `get_cloud_jobs($type, $limit)`: Fetch jobs filtered by type (email/certificate).
    - `get_job_stats()`: KPI data for offload jobs.
    - `get_company_settings($companyid)`: Fetch configuration.

### [MODIFY] [designs/dashboard_v6_ultimate.php](file:///D:/antigravity/manireports/local/manireports/designs/dashboard_v6_ultimate.php)
- Instantiate `cloud_offload_data_loader` for "Email" and "Certificates" tabs.
- **Email Tab**:
    - Show "Active Email Jobs" (from Cloud Offload).
    - Show "Email History".
    - Show "Email Offload Settings" (AWS/Cloudflare).
- **Certificates Tab**:
    - Show "Active Certificate Jobs".
    - Show "Certificate History".
    - Show "Certificate Offload Settings".

## Verification Plan

### Automated Tests
- Unit tests for `CloudJobManager` and `EmailOffloadHandler`.

### Manual Verification
1.  **Dashboard V6**:
    - Open `designs/dashboard_v6_ultimate.php`.
    - Click "Cloud" tab.
    - Verify KPI cards show correct data.
    - Verify Active Jobs table updates when a job is running.
    - Test Settings form: Select company, enter keys, save. Verify persistence.
2.  **End-to-End**:
    - Trigger a CSV upload.
    - Watch it appear in "Active Jobs" on the dashboard.
    - Wait for completion and check "Job History".
