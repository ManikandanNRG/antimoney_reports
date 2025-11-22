# Phase 4 Cloud Offload - Complete Flow Diagram & Architecture

## High-Level System Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                          MOODLE SERVER (Local)                              │
│                                                                              │
│  ┌──────────────────────────────────────────────────────────────────────┐  │
│  │                    User Actions                                      │  │
│  │  • CSV Import  • License Allocation  • Reengagement Campaign        │  │
│  └──────────────────────────────────────────────────────────────────────┘  │
│                                  ↓                                           │
│  ┌──────────────────────────────────────────────────────────────────────┐  │
│  │              Email Interceptor (email_interceptor.php)              │  │
│  │  Detects bulk email operations and captures email details           │  │
│  └──────────────────────────────────────────────────────────────────────┘  │
│                                  ↓                                           │
│  ┌──────────────────────────────────────────────────────────────────────┐  │
│  │           Cloud Job Manager (cloud_job_manager.php)                 │  │
│  │  • Creates job record in database                                   │  │
│  │  • Stores recipients and email details                              │  │
│  │  • Submits job to cloud provider                                    │  │
│  └──────────────────────────────────────────────────────────────────────┘  │
│                                  ↓                                           │
│  ┌──────────────────────────────────────────────────────────────────────┐  │
│  │         Cloud Connector (aws_connector.php / cloudflare_connector)   │  │
│  │  • Authenticates with cloud provider                                │  │
│  │  • Sends job to SQS/Queue                                           │  │
│  │  • Handles retries and errors                                       │  │
│  └──────────────────────────────────────────────────────────────────────┘  │
│                                  ↓                                           │
│  ┌──────────────────────────────────────────────────────────────────────┐  │
│  │              Database (manireports_cloud_jobs)                       │  │
│  │  • Job status tracking                                              │  │
│  │  • Email recipient lists                                            │  │
│  │  • Execution history                                                │  │
│  └──────────────────────────────────────────────────────────────────────┘  │
│                                  ↓                                           │
│  ┌──────────────────────────────────────────────────────────────────────┐  │
│  │              Cloud Jobs Dashboard (cloud_jobs.php)                   │  │
│  │  • View job status                                                  │  │
│  │  • Monitor progress                                                 │  │
│  │  • Retry failed jobs                                                │  │
│  └──────────────────────────────────────────────────────────────────────┘  │
│                                  ↑                                           │
└──────────────────────────────────┼───────────────────────────────────────────┘
                                   │
                    ┌──────────────┴──────────────┐
                    │                             │
                    ↓                             ↓
        ┌─────────────────────┐      ┌─────────────────────┐
        │   AWS CLOUD         │      │  CLOUDFLARE CLOUD   │
        │                     │      │                     │
        │ ┌─────────────────┐ │      │ ┌─────────────────┐ │
        │ │  SQS Queue      │ │      │ │  Queue/KV       │ │
        │ │  (receives job) │ │      │ │  (receives job) │ │
        │ └────────┬────────┘ │      │ └────────┬────────┘ │
        │          ↓          │      │          ↓          │
        │ ┌─────────────────┐ │      │ ┌─────────────────┐ │
        │ │  Lambda         │ │      │ │  Worker         │ │
        │ │  (processes)    │ │      │ │  (processes)    │ │
        │ └────────┬────────┘ │      │ └────────┬────────┘ │
        │          ↓          │      │          ↓          │
        │ ┌─────────────────┐ │      │ ┌─────────────────┐ │
        │ │  SES            │ │      │ │  Email API      │ │
        │ │  (sends emails) │ │      │ │  (sends emails) │ │
        │ └────────┬────────┘ │      │ └────────┬────────┘ │
        │          ↓          │      │          ↓          │
        └──────────┼──────────┘      └──────────┼──────────┘
                   │                            │
                   └────────────┬───────────────┘
                                ↓
                    ┌─────────────────────────┐
                    │  Callback to Moodle     │
                    │  (cloud_callback.php)   │
                    │  • Job ID               │
                    │  • Status               │
                    │  • Emails sent          │
                    │  • Errors               │
                    └────────────┬────────────┘
                                 ↓
                    ┌─────────────────────────┐
                    │  Update Job Status      │
                    │  in Database            │
                    └─────────────────────────┘
```

## Scenario 1: CSV Bulk User Import with Temp Passwords

```
STEP 1: Admin uploads CSV file
┌─────────────────────────────────────────────────────────────────┐
│ Admin goes to: Site Admin → Users → Upload Users               │
│ Uploads CSV with 500 users                                      │
│ Moodle generates temp passwords for each user                   │
└─────────────────────────────────────────────────────────────────┘
                              ↓
STEP 2: Email Interceptor detects bulk email
┌─────────────────────────────────────────────────────────────────┐
│ Event: user_created (fired 500 times)                           │
│ Email Interceptor (email_interceptor.php) catches event         │
│ Detects: "This is bulk operation (500 emails)"                  │
│ Captures: recipient emails, temp passwords, sender info         │
└─────────────────────────────────────────────────────────────────┘
                              ↓
STEP 3: Create Cloud Job
┌─────────────────────────────────────────────────────────────────┐
│ Cloud Job Manager creates record:                               │
│ {                                                               │
│   job_id: 12345,                                                │
│   type: 'csv_import',                                           │
│   status: 'pending',                                            │
│   email_count: 500,                                             │
│   company_id: 1,                                                │
│   created_at: 2024-11-22 10:30:00,                              │
│   recipients: [                                                 │
│     {email: 'user1@example.com', password: 'TempPass123'},      │
│     {email: 'user2@example.com', password: 'TempPass456'},      │
│     ...                                                         │
│   ]                                                             │
│ }                                                               │
└─────────────────────────────────────────────────────────────────┘
                              ↓
STEP 4: Submit to Cloud Provider
┌─────────────────────────────────────────────────────────────────┐
│ Cloud Connector (aws_connector.php):                            │
│ • Authenticates with AWS using credentials                      │
│ • Sends job to SQS queue                                        │
│ • Returns: Queue message ID                                     │
│ Status changes: pending → queued                                │
└─────────────────────────────────────────────────────────────────┘
                              ↓
STEP 5: Admin sees confirmation
┌─────────────────────────────────────────────────────────────────┐
│ Message: "500 emails queued for cloud delivery"                 │
│ Admin can view progress in: ManiReports → Cloud Jobs            │
└─────────────────────────────────────────────────────────────────┘
                              ↓
STEP 6: Lambda processes job (AWS side)
┌─────────────────────────────────────────────────────────────────┐
│ Lambda function (lambda_handler.py):                            │
│ • Polls SQS queue                                               │
│ • Retrieves job 12345                                           │
│ • Fetches 500 recipients from Moodle database                   │
│ • For each recipient:                                           │
│   - Compose email with temp password                            │
│   - Call AWS SES SendEmail API                                  │
│   - Log result (success/failure)                                │
│ • After all emails sent:                                        │
│   - Prepare callback data                                       │
│   - Send HTTPS POST to Moodle callback endpoint                 │
└─────────────────────────────────────────────────────────────────┘
                              ↓
STEP 7: Callback received by Moodle
┌─────────────────────────────────────────────────────────────────┐
│ Endpoint: /local/manireports/ui/ajax/cloud_callback.php         │
│ Receives:                                                       │
│ {                                                               │
│   job_id: 12345,                                                │
│   status: 'completed',                                          │
│   emails_sent: 498,                                             │
│   emails_failed: 2,                                             │
│   errors: [                                                     │
│     'invalid_email@example.com',                                │
│     'bounced_user@example.com'                                  │
│   ]                                                             │
│ }                                                               │
└─────────────────────────────────────────────────────────────────┘
                              ↓
STEP 8: Update job status
┌─────────────────────────────────────────────────────────────────┐
│ Cloud Job Manager updates database:                             │
│ • Status: completed                                             │
│ • Emails sent: 498                                              │
│ • Emails failed: 2                                              │
│ • Completed at: 2024-11-22 10:35:00                             │
│ • Error log: stored for review                                  │
└─────────────────────────────────────────────────────────────────┘
                              ↓
STEP 9: Admin reviews results
┌─────────────────────────────────────────────────────────────────┐
│ Admin views: ManiReports → Cloud Jobs → Job 12345               │
│ Sees:                                                           │
│ • Status: Completed                                             │
│ • 498 emails sent successfully                                  │
│ • 2 emails failed (with reasons)                                │
│ • Duration: 5 minutes                                           │
│ • Can retry failed emails if needed                             │
└─────────────────────────────────────────────────────────────────┘
```

## Scenario 2: License Allocation with Fixed Passwords

```
STEP 1: Admin allocates licenses
┌─────────────────────────────────────────────────────────────────┐
│ Admin goes to: IOMAD → License Allocation                       │
│ Allocates 200 licenses to Company A                             │
│ Moodle sends welcome emails with license codes                  │
└─────────────────────────────────────────────────────────────────┘
                              ↓
STEP 2: Email Interceptor detects
┌─────────────────────────────────────────────────────────────────┐
│ Event: license_allocated (fired 200 times)                      │
│ License Allocation Handler (license_allocation_handler.php)     │
│ Detects: "This is bulk license allocation (200 emails)"         │
│ Captures: license codes, recipient emails, company info         │
└─────────────────────────────────────────────────────────────────┘
                              ↓
STEP 3: Create Cloud Job
┌─────────────────────────────────────────────────────────────────┐
│ Cloud Job Manager creates record:                               │
│ {                                                               │
│   job_id: 12346,                                                │
│   type: 'license_allocation',                                   │
│   status: 'pending',                                            │
│   email_count: 200,                                             │
│   company_id: 5,                                                │
│   recipients: [                                                 │
│     {email: 'manager1@company.com', license_code: 'LIC-001'},   │
│     {email: 'manager2@company.com', license_code: 'LIC-002'},   │
│     ...                                                         │
│   ]                                                             │
│ }                                                               │
└─────────────────────────────────────────────────────────────────┘
                              ↓
STEP 4: Submit to Cloud
┌─────────────────────────────────────────────────────────────────┐
│ Cloud Connector sends to SQS                                    │
│ Status: pending → queued                                        │
└─────────────────────────────────────────────────────────────────┘
                              ↓
STEP 5: Lambda processes
┌─────────────────────────────────────────────────────────────────┐
│ Lambda:                                                         │
│ • Retrieves job 12346                                           │
│ • For each of 200 recipients:                                   │
│   - Compose email with license code                             │
│   - Send via SES                                                │
│ • All 200 sent successfully                                     │
│ • Send callback to Moodle                                       │
└─────────────────────────────────────────────────────────────────┘
                              ↓
STEP 6: Callback & Update
┌─────────────────────────────────────────────────────────────────┐
│ Moodle receives callback:                                       │
│ • Status: completed                                             │
│ • Emails sent: 200                                              │
│ • Emails failed: 0                                              │
│ • Database updated                                              │
└─────────────────────────────────────────────────────────────────┘
                              ↓
STEP 7: Admin confirmation
┌─────────────────────────────────────────────────────────────────┐
│ Admin sees: "200 license allocation emails sent successfully"   │
│ All managers received their license codes                       │
└─────────────────────────────────────────────────────────────────┘
```

## Scenario 3: Reengagement Campaign Reminders

```
STEP 1: Admin triggers reengagement campaign
┌─────────────────────────────────────────────────────────────────┐
│ Admin goes to: ManiReports → Reengagement                       │
│ Selects: "Inactive students (30+ days)"                         │
│ Finds: 350 inactive students                                    │
│ Clicks: "Send reminder emails"                                  │
└─────────────────────────────────────────────────────────────────┘
                              ↓
STEP 2: Reengagement Observer detects
┌─────────────────────────────────────────────────────────────────┐
│ Event: reengagement_campaign_triggered                          │
│ Reengagement Observer (reengagement_observer.php)               │
│ Detects: "This is bulk reengagement (350 emails)"               │
│ Captures: student emails, course info, reminder message         │
└─────────────────────────────────────────────────────────────────┘
                              ↓
STEP 3: Create Cloud Job
┌─────────────────────────────────────────────────────────────────┐
│ Cloud Job Manager creates record:                               │
│ {                                                               │
│   job_id: 12347,                                                │
│   type: 'reengagement',                                         │
│   status: 'pending',                                            │
│   email_count: 350,                                             │
│   company_id: 1,                                                │
│   recipients: [                                                 │
│     {email: 'student1@example.com', course: 'Math 101'},        │
│     {email: 'student2@example.com', course: 'English 201'},     │
│     ...                                                         │
│   ]                                                             │
│ }                                                               │
└─────────────────────────────────────────────────────────────────┘
                              ↓
STEP 4: Submit to Cloud
┌─────────────────────────────────────────────────────────────────┐
│ Cloud Connector sends to SQS                                    │
│ Status: pending → queued                                        │
└─────────────────────────────────────────────────────────────────┘
                              ↓
STEP 5: Lambda processes
┌─────────────────────────────────────────────────────────────────┐
│ Lambda:                                                         │
│ • Retrieves job 12347                                           │
│ • For each of 350 students:                                     │
│   - Compose personalized reminder email                         │
│   - Include course name and engagement tips                     │
│   - Send via SES                                                │
│ • 348 sent successfully, 2 bounced                              │
│ • Send callback to Moodle                                       │
└─────────────────────────────────────────────────────────────────┘
                              ↓
STEP 6: Callback & Update
┌─────────────────────────────────────────────────────────────────┐
│ Moodle receives callback:                                       │
│ • Status: completed                                             │
│ • Emails sent: 348                                              │
│ • Emails failed: 2                                              │
│ • Failed emails logged for follow-up                            │
└─────────────────────────────────────────────────────────────────┘
                              ↓
STEP 7: Admin reviews campaign
┌─────────────────────────────────────────────────────────────────┐
│ Admin views: ManiReports → Cloud Jobs → Job 12347               │
│ Sees:                                                           │
│ • 348 reminders sent                                            │
│ • 2 bounced (invalid emails)                                    │
│ • Can retry or remove bounced addresses                         │
└─────────────────────────────────────────────────────────────────┘
```

## Error Handling & Retry Flow

```
STEP 1: Job fails during processing
┌─────────────────────────────────────────────────────────────────┐
│ Lambda encounters error:                                        │
│ • Network timeout                                               │
│ • SES rate limit exceeded                                       │
│ • Invalid recipient email                                       │
│ • Database connection error                                     │
└─────────────────────────────────────────────────────────────────┘
                              ↓
STEP 2: Lambda retries
┌─────────────────────────────────────────────────────────────────┐
│ Lambda retry logic:                                             │
│ • Retry 1: Wait 5 seconds, try again                            │
│ • Retry 2: Wait 30 seconds, try again                           │
│ • Retry 3: Wait 2 minutes, try again                            │
│ • If still fails: Mark as failed, send callback                 │
└─────────────────────────────────────────────────────────────────┘
                              ↓
STEP 3: Callback with partial results
┌─────────────────────────────────────────────────────────────────┐
│ Lambda sends callback:                                          │
│ {                                                               │
│   job_id: 12345,                                                │
│   status: 'partial_failure',                                    │
│   emails_sent: 450,                                             │
│   emails_failed: 50,                                            │
│   errors: [                                                     │
│     'rate_limit_exceeded',                                      │
│     'invalid_email@example.com',                                │
│     ...                                                         │
│   ]                                                             │
│ }                                                               │
└─────────────────────────────────────────────────────────────────┘
                              ↓
STEP 4: Admin retries failed emails
┌─────────────────────────────────────────────────────────────────┐
│ Admin views job details                                         │
│ Sees: 50 failed emails                                          │
│ Clicks: "Retry failed emails"                                   │
│ New job created for 50 failed emails                            │
│ Submitted to cloud again                                        │
└─────────────────────────────────────────────────────────────────┘
                              ↓
STEP 5: Retry succeeds
┌─────────────────────────────────────────────────────────────────┐
│ Lambda processes retry job                                      │
│ 48 of 50 succeed                                                │
│ 2 permanently fail (invalid emails)                             │
│ Callback sent to Moodle                                         │
└─────────────────────────────────────────────────────────────────┘
```

## Database Flow

```
User Action
    ↓
┌─────────────────────────────────────────────────────────────────┐
│ manireports_cloud_jobs                                          │
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │ job_id | type | status | email_count | company_id | ...    │ │
│ │ 12345  | csv  | queued | 500         | 1          | ...    │ │
│ └─────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
    ↓
┌─────────────────────────────────────────────────────────────────┐
│ manireports_cloud_recipients                                    │
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │ recipient_id | job_id | email | data | status | ...        │ │
│ │ 1            | 12345  | u1@.. | {...} | pending | ...      │ │
│ │ 2            | 12345  | u2@.. | {...} | pending | ...      │ │
│ └─────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
    ↓
┌─────────────────────────────────────────────────────────────────┐
│ manireports_cloud_import_batches                                │
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │ batch_id | job_id | batch_num | status | sent_count | ...  │ │
│ │ 1        | 12345  | 1         | sent   | 100        | ...  │ │
│ │ 2        | 12345  | 2         | sent   | 100        | ...  │ │
│ └─────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
    ↓
┌─────────────────────────────────────────────────────────────────┐
│ manireports_cloud_company_settings                              │
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │ company_id | aws_key | aws_secret | sqs_url | ses_email | ..│
│ │ 1          | AKIA... | wJal...    | https..| noreply@.. | ..│
│ └─────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
```

## Key Components Summary

| Component | Purpose | Location |
|-----------|---------|----------|
| **Email Interceptor** | Detects bulk email operations | `classes/api/email_interceptor.php` |
| **Cloud Job Manager** | Creates and manages cloud jobs | `classes/api/cloud_job_manager.php` |
| **Cloud Connector** | Abstract base for cloud providers | `classes/api/cloud_connector.php` |
| **AWS Connector** | AWS-specific implementation | `classes/api/aws_connector.php` |
| **Cloudflare Connector** | Cloudflare-specific implementation | `classes/api/cloudflare_connector.php` |
| **Lambda Handler** | AWS Lambda worker code | `cloud_workers/aws/lambda_handler.py` |
| **Cloudflare Worker** | Cloudflare worker code | `cloud_workers/cloudflare/worker.js` |
| **Cloud Callback** | Receives status updates from cloud | `ui/ajax/cloud_callback.php` |
| **Cloud Jobs UI** | Admin dashboard for monitoring | `ui/cloud_jobs.php` |
| **License Handler** | Handles license allocation emails | `classes/api/license_allocation_handler.php` |
| **Reengagement Observer** | Handles reengagement campaigns | `classes/observers/reengagement_observer.php` |
| **User Observer** | Handles user creation events | `classes/observers/user_observer.php` |

## Event Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                    MOODLE EVENTS                                │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  user_created (CSV import)                                      │
│       ↓                                                         │
│  user_observer.php catches event                                │
│       ↓                                                         │
│  email_interceptor.php detects bulk operation                   │
│       ↓                                                         │
│  cloud_job_manager.php creates job                              │
│       ↓                                                         │
│  aws_connector.php sends to SQS                                 │
│                                                                 │
│  ─────────────────────────────────────────────────────────────  │
│                                                                 │
│  license_allocated (License allocation)                         │
│       ↓                                                         │
│  license_allocation_handler.php catches event                   │
│       ↓                                                         │
│  email_interceptor.php detects bulk operation                   │
│       ↓                                                         │
│  cloud_job_manager.php creates job                              │
│       ↓                                                         │
│  aws_connector.php sends to SQS                                 │
│                                                                 │
│  ─────────────────────────────────────────────────────────────  │
│                                                                 │
│  reengagement_campaign_triggered (Reengagement)                 │
│       ↓                                                         │
│  reengagement_observer.php catches event                        │
│       ↓                                                         │
│  email_interceptor.php detects bulk operation                   │
│       ↓                                                         │
│  cloud_job_manager.php creates job                              │
│       ↓                                                         │
│  aws_connector.php sends to SQS                                 │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

## Duplicate Prevention Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                  DUPLICATE PREVENTION                           │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ STEP 1: Email Interceptor receives event                        │
│         Checks: Has this email already been queued?             │
│         Uses: Hash of (recipient + job_type + timestamp)        │
│                                                                 │
│ STEP 2: If duplicate detected                                   │
│         Action: Skip this email                                 │
│         Log: "Duplicate email skipped"                          │
│                                                                 │
│ STEP 3: If new email                                            │
│         Action: Add to job recipients                           │
│         Mark: email_hash in database                            │
│                                                                 │
│ STEP 4: Lambda processes job                                    │
│         Checks: Has this recipient already received email?      │
│         Uses: Database query on manireports_cloud_recipients    │
│                                                                 │
│ STEP 5: If already sent                                         │
│         Action: Skip recipient                                  │
│         Log: "Email already sent to recipient"                  │
│                                                                 │
│ STEP 6: If not sent                                             │
│         Action: Send email                                      │
│         Mark: status = 'sent' in database                       │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

## Status Transitions

```
Job Status Flow:
pending → queued → processing → completed
                ↓
            partial_failure → retry_pending → processing → completed
                ↓
            failed → manual_retry → processing → completed

Recipient Status Flow:
pending → processing → sent
       ↓
    failed → retry_pending → processing → sent
       ↓
    permanently_failed
```
