# Phase 4: Selective Cloud Offload - Detailed Implementation Plan

## 1. Executive Summary
The goal of Phase 4 is to decouple high-resource operations (Bulk Emailing and Certificate Generation) from the local Moodle server. By offloading these tasks to a cloud provider (AWS or Cloudflare), we ensure the Moodle interface remains snappy even when sending 50,000+ emails or generating thousands of PDF certificates.

This system is **selective** and **configuration-driven**, allowing administrators to choose which companies or specific schedules utilize the cloud infrastructure.

## 2. Architecture Overview

### Core Components
1.  **Moodle Scheduler (Cron)**: The trigger point. Instead of processing locally, it delegates to the `CloudJobManager`.
2.  **CloudJobManager**: The brain. It decides whether to process locally or offload. It batches data and creates "Jobs".
3.  **CloudConnector**: The bridge. An abstract layer connecting Moodle to AWS SQS or Cloudflare Workers.
4.  **Cloud Worker (External)**: A serverless function (Lambda/Worker) that receives the job, processes the heavy lifting (sending emails/generating PDFs), and calls back to Moodle.
5.  **Callback Handler**: A secure API endpoint in Moodle that receives status updates from the Cloud Worker.

### Data Flow
`Trigger (Cron/UI)` -> `CloudJobManager` -> `DB (Pending Job)` -> `CloudConnector` -> `External Queue` -> `Cloud Worker` -> `External Service (SES/S3)` -> `Callback` -> `DB (Job Complete)`

## 3. Detailed Workflows

### A. Re-engagement Reminders (Automated)
*   **Source**: The existing `mod_reengagement` plugin's cron task.
*   **Interception**: We modify `reengagement_crontask` to collect users into a batch instead of emailing them one-by-one.
*   **Logic**:
    1.  Cron runs, identifies 500 users due for a reminder.
    2.  `EmailOffloadHandler` checks if the Company has "Cloud Offload" enabled.
    3.  **If Yes**: Creates a single `EMAIL_REENGAGEMENT` job with 500 recipients and the HTML template.
    4.  **If No**: Falls back to standard Moodle `email_to_user`.

### B. Standard Welcome Emails (Bulk Action)
*   **Scenario**: Admin uploads 1,000 users via CSV or API. Moodle doesn't send emails immediately to avoid timeout.
*   **Trigger**: Admin goes to **Moodle > Reports > Cloud Jobs > Bulk Email Tools**.
*   **UI Flow**:
    1.  Select "Welcome Emails (Standard)".
    2.  Filter: "Created in last 24 hours" OR "Upload CSV".
    3.  Preview: Shows list of 1,000 users.
    4.  Click "Send via Cloud".
*   **Processing**:
    1.  System generates a temporary password for each user (if needed).
    2.  Creates an `EMAIL_WELCOME_STD` job.
    3.  Cloud Worker sends the "New Account" email with login details.

### C. License Allocation Emails (IOMAD Specific)
*   **Scenario**: Users are enrolled in a course via an IOMAD License. They need a specific "License Allocation" email with course details.
*   **Trigger**: **Event Interception** (`\block_iomad_company_admin\event\user_license_assigned`).
*   **Logic**:
    1.  `local_manireports` listens for the IOMAD license event.
    2.  Checks if the Company has "Cloud Offload" enabled.
    3.  **If Yes**:
        *   Fetches License info (`mdl_companylicense`), Course info (`mdl_course`), and User info.
        *   Creates an `EMAIL_WELCOME_LIC` job.
        *   (Optional) Suppresses the default IOMAD email if possible (or we advise disabling it in IOMAD settings).
    4.  **If No**: Does nothing (lets IOMAD handle it).

### D. Certificate Generation (High Volume)
*   **Scenario**: 5,000 users complete a compliance course on Friday.
*   **Trigger**: Scheduled Task or "Generate Certificates" button.
*   **Processing**:
    1.  `CertificateGenerator` prepares the data (User Name, Course, Date, Grade).
    2.  Creates a `CERTIFICATE_GEN` job.
    3.  Cloud Worker generates PDFs using a lightweight library (e.g., `pdfkit`).
    4.  Uploads PDFs to S3/R2.
    5.  Returns the public URLs to Moodle.
    6.  Moodle updates the user's record with the Certificate URL.

## 4. Database Schema

### `mdl_manireports_cloud_jobs`
| Field | Type | Description |
| :--- | :--- | :--- |
| `id` | BIGINT | Primary Key |
| `job_uuid` | VARCHAR(36) | Unique Job ID (sent to cloud) |
| `type` | VARCHAR(20) | `EMAIL_REENGAGEMENT`, `EMAIL_WELCOME_STD`, `EMAIL_WELCOME_LIC`, `CERTIFICATE` |
| `status` | VARCHAR(20) | `PENDING`, `PROCESSING`, `COMPLETED`, `FAILED`, `PARTIAL` |
| `provider` | VARCHAR(20) | `AWS`, `CLOUDFLARE` |
| `created_by` | BIGINT | User ID who triggered it (or 0 for cron) |
| `companyid` | BIGINT | IOMAD Company ID (for billing/logging) |
| `total_records` | INT | Number of recipients |
| `processed_records`| INT | Number of successes |
| `failed_records` | INT | Number of failures |
| `payload_summary`| TEXT | JSON summary (Subject, Template ID) |
| `timecreated` | INT | Timestamp |
| `timemodified` | INT | Timestamp |

### `mdl_manireports_cloud_job_recipients`
| Field | Type | Description |
| :--- | :--- | :--- |
| `id` | BIGINT | Primary Key |
| `jobid` | BIGINT | FK to `cloud_jobs` |
| `userid` | BIGINT | Moodle User ID |
| `email` | VARCHAR(100) | Recipient Email |
| `status` | VARCHAR(20) | `PENDING`, `SENT`, `FAILED` |
| `external_id` | VARCHAR(100) | Message ID from SES/SendGrid |
| `error_message` | TEXT | If failed, why? |
| `artifact_url` | TEXT | URL of generated certificate (if applicable) |

### `mdl_manireports_company_cloud_settings`
| Field | Type | Description |
| :--- | :--- | :--- |
| `id` | BIGINT | Primary Key |
| `companyid` | BIGINT | IOMAD Company ID |
| `enabled` | TINYINT | 1 = Cloud Offload Active |
| `email_provider` | VARCHAR(20) | `AWS`, `CLOUDFLARE`, `SYSTEM` (Inherit) |
| `threshold` | INT | Min users to trigger cloud (e.g., 50) |

## 5. Class Structure

### `classes/api/CloudJobManager.php`
*   `create_job(string $type, array $payload, array $recipients)`: Main entry point.
*   `process_callback(string $job_uuid, array $result)`: Updates DB status.
*   `retry_job(int $job_id)`: Re-queues failed items.

### `classes/api/EmailOffloadHandler.php`
*   `handle_reengagement_batch(array $users, $template)`: Prepares Re-engagement job.
*   `handle_welcome_batch(array $user_ids, $type)`: Prepares Welcome job.
*   `get_new_users_sql()`: Helper to find users for Welcome emails.
*   `get_license_users_sql()`: Helper to find users for License emails.

### `classes/api/connectors/AwsConnector.php`
*   `submit(array $job_data)`: Pushes JSON to SQS.

### `classes/api/connectors/CloudflareConnector.php`
*   `submit(array $job_data)`: POSTs JSON to Worker URL.

## 6. User Interface Design

### A. Global Settings (`settings.php`)
*   **Cloud Provider**: Radio [AWS | Cloudflare].
*   **AWS Settings**: Access Key, Secret, Region, SQS URL, S3 Bucket.
*   **Cloudflare Settings**: Account ID, API Token, Worker URL.

### B. Cloud Jobs Dashboard (`ui/cloud_jobs.php`)
*   **Tabs**: [Active Jobs] [History] [Bulk Email Tools] [Configuration].
*   **Active Jobs**: Table showing Job ID, Type, Progress (ProgressBar), Status.
*   **History**: Log of past jobs with "View Details" (shows recipient list).

### C. Bulk Email Tools (New Tab)
*   **Card 1: Standard Welcome**
    *   "Send welcome emails to users created in the last [X] hours."
    *   Button: "Preview & Send".
*   **Card 2: License Allocation**
    *   "Send license emails to users enrolled via license in last [X] hours."
    *   Button: "Preview & Send".
*   **Card 3: Custom CSV**
    *   "Upload CSV (user_id, custom_msg)"
    *   Button: "Upload".

## 7. Implementation Steps
1.  **Database**: Create tables defined in Section 4.
2.  **Backend Core**: Implement `CloudJobManager` and `CloudConnector`.
3.  **Email Handler**: Implement `EmailOffloadHandler` with logic for all 3 email types.
4.  **UI Construction**: Build the `CloudJobs` dashboard and "Bulk Email Tools" tab.
5.  **Integration**: Hook into `reengagement` cron and Scheduler.
6.  **Testing**: Verify with Mock Connector (log to file) first, then real Cloud.
