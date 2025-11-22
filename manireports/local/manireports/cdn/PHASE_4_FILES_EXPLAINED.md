# Phase 4 Implementation - Files Explained

## Overview

Phase 4 adds cloud email offload capability to ManiReports. This document explains each file created and its role in the system.

---

## 1. Database Schema Files

### `db/install.xml` (Updated)

**Purpose:** Defines the database tables for cloud offload functionality

**New Tables Added:**

#### `manireports_cloud_jobs`
Stores information about each cloud email job.

```xml
<TABLE NAME="manireports_cloud_jobs">
  <FIELDS>
    <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true" />
    <FIELD NAME="type" TYPE="char" LENGTH="50" NOTNULL="true" />
    <!-- csv_import, license_allocation, reengagement -->
    <FIELD NAME="status" TYPE="char" LENGTH="50" NOTNULL="true" />
    <!-- pending, queued, processing, completed, partial_failure, failed -->
    <FIELD NAME="email_count" TYPE="int" LENGTH="10" NOTNULL="true" />
    <FIELD NAME="emails_sent" TYPE="int" LENGTH="10" NOTNULL="false" />
    <FIELD NAME="emails_failed" TYPE="int" LENGTH="10" NOTNULL="false" />
    <FIELD NAME="company_id" TYPE="int" LENGTH="10" NOTNULL="true" />
    <FIELD NAME="created_at" TYPE="int" LENGTH="10" NOTNULL="true" />
    <FIELD NAME="started_at" TYPE="int" LENGTH="10" NOTNULL="false" />
    <FIELD NAME="completed_at" TYPE="int" LENGTH="10" NOTNULL="false" />
    <FIELD NAME="error_log" TYPE="text" NOTNULL="false" />
  </FIELDS>
  <KEYS>
    <KEY NAME="primary" TYPE="primary" FIELDS="id" />
    <KEY NAME="company_id" TYPE="foreign" FIELDS="company_id" REFTABLE="company" REFFIELDS="id" />
  </KEYS>
</TABLE>
```

**What it stores:**
- Job metadata (type, status, counts)
- Timestamps for tracking
- Error information for debugging
- Company ID for IOMAD filtering

#### `manireports_cloud_recipients`
Stores individual email recipients for each job.

```xml
<TABLE NAME="manireports_cloud_recipients">
  <FIELDS>
    <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true" />
    <FIELD NAME="job_id" TYPE="int" LENGTH="10" NOTNULL="true" />
    <FIELD NAME="email" TYPE="char" LENGTH="255" NOTNULL="true" />
    <FIELD NAME="recipient_data" TYPE="text" NOTNULL="false" />
    <!-- JSON: {password, license_code, course_name, etc} -->
    <FIELD NAME="status" TYPE="char" LENGTH="50" NOTNULL="true" />
    <!-- pending, sent, failed, bounced -->
    <FIELD NAME="sent_at" TYPE="int" LENGTH="10" NOTNULL="false" />
    <FIELD NAME="error_message" TYPE="text" NOTNULL="false" />
  </FIELDS>
  <KEYS>
    <KEY NAME="primary" TYPE="primary" FIELDS="id" />
    <KEY NAME="job_id" TYPE="foreign" FIELDS="job_id" REFTABLE="manireports_cloud_jobs" REFFIELDS="id" />
  </KEYS>
</TABLE>
```

**What it stores:**
- Individual recipient emails
- Recipient-specific data (passwords, license codes, etc.)
- Per-recipient status tracking
- Error details for failed sends

#### `manireports_cloud_import_batches`
Tracks batches of emails sent (for progress monitoring).

```xml
<TABLE NAME="manireports_cloud_import_batches">
  <FIELDS>
    <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true" />
    <FIELD NAME="job_id" TYPE="int" LENGTH="10" NOTNULL="true" />
    <FIELD NAME="batch_number" TYPE="int" LENGTH="10" NOTNULL="true" />
    <FIELD NAME="status" TYPE="char" LENGTH="50" NOTNULL="true" />
    <!-- pending, processing, sent, failed -->
    <FIELD NAME="sent_count" TYPE="int" LENGTH="10" NOTNULL="false" />
    <FIELD NAME="failed_count" TYPE="int" LENGTH="10" NOTNULL="false" />
    <FIELD NAME="created_at" TYPE="int" LENGTH="10" NOTNULL="true" />
    <FIELD NAME="completed_at" TYPE="int" LENGTH="10" NOTNULL="false" />
  </FIELDS>
  <KEYS>
    <KEY NAME="primary" TYPE="primary" FIELDS="id" />
    <KEY NAME="job_id" TYPE="foreign" FIELDS="job_id" REFTABLE="manireports_cloud_jobs" REFFIELDS="id" />
  </KEYS>
</TABLE>
```

**What it stores:**
- Batch-level progress (for large jobs split into batches)
- Batch status and counts
- Timing information

#### `manireports_cloud_company_settings`
Stores AWS/Cloudflare credentials per company (for IOMAD).

```xml
<TABLE NAME="manireports_cloud_company_settings">
  <FIELDS>
    <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true" />
    <FIELD NAME="company_id" TYPE="int" LENGTH="10" NOTNULL="true" />
    <FIELD NAME="provider" TYPE="char" LENGTH="50" NOTNULL="true" />
    <!-- aws, cloudflare -->
    <FIELD NAME="aws_access_key" TYPE="char" LENGTH="255" NOTNULL="false" />
    <FIELD NAME="aws_secret_key" TYPE="char" LENGTH="255" NOTNULL="false" />
    <FIELD NAME="aws_region" TYPE="char" LENGTH="50" NOTNULL="false" />
    <FIELD NAME="sqs_queue_url" TYPE="char" LENGTH="500" NOTNULL="false" />
    <FIELD NAME="ses_sender_email" TYPE="char" LENGTH="255" NOTNULL="false" />
    <FIELD NAME="cloudflare_api_token" TYPE="char" LENGTH="255" NOTNULL="false" />
    <FIELD NAME="cloudflare_account_id" TYPE="char" LENGTH="255" NOTNULL="false" />
    <FIELD NAME="enabled" TYPE="int" LENGTH="1" NOTNULL="true" DEFAULT="0" />
  </FIELDS>
  <KEYS>
    <KEY NAME="primary" TYPE="primary" FIELDS="id" />
    <KEY NAME="company_id" TYPE="foreign" FIELDS="company_id" REFTABLE="company" REFFIELDS="id" />
  </KEYS>
</TABLE>
```

**What it stores:**
- Cloud provider credentials (encrypted in Moodle)
- Provider-specific configuration
- Per-company settings for IOMAD

---

## 2. Core API Classes

### `classes/api/email_interceptor.php`

**Purpose:** Intercepts bulk email operations and routes them to cloud

**Key Methods:**

```php
public function should_offload_email($recipient, $subject, $body)
// Determines if email should be sent to cloud
// Returns: true/false

public function create_job_from_event($event_data)
// Creates cloud job from event data
// Returns: job_id

public function detect_bulk_operation($recipients)
// Detects if this is a bulk operation (>10 emails)
// Returns: true/false

public function get_email_type($event)
// Determines email type (csv_import, license_allocation, reengagement)
// Returns: email_type string
```

**How it works:**

1. Listens for Moodle events (user_created, license_allocated, etc.)
2. Checks if cloud offload is enabled
3. Detects if it's a bulk operation
4. Extracts email details
5. Calls cloud_job_manager to create job
6. Returns job_id to prevent duplicate sends

**Example Usage:**
```php
$interceptor = new email_interceptor();
if ($interceptor->should_offload_email($user->email, $subject, $body)) {
    $job_id = $interceptor->create_job_from_event($event_data);
    // Email will be sent via cloud, not locally
}
```

---

### `classes/api/cloud_job_manager.php`

**Purpose:** Manages the complete lifecycle of cloud email jobs

**Key Methods:**

```php
public function create_job($type, $recipients, $company_id)
// Creates new job record
// Returns: job_id

public function submit_job_to_cloud($job_id)
// Submits job to cloud provider (SQS/Queue)
// Returns: queue_message_id

public function update_job_status($job_id, $status, $data)
// Updates job status in database
// Returns: true/false

public function get_job_details($job_id)
// Retrieves complete job information
// Returns: job object

public function handle_callback($job_id, $callback_data)
// Processes callback from cloud provider
// Returns: true/false

public function retry_failed_job($job_id)
// Retries a failed job
// Returns: new_job_id
```

**Database Operations:**

```php
// Create job
INSERT INTO manireports_cloud_jobs 
(type, status, email_count, company_id, created_at)
VALUES ('csv_import', 'pending', 500, 1, NOW())

// Add recipients
INSERT INTO manireports_cloud_recipients 
(job_id, email, recipient_data, status)
VALUES (12345, 'user@example.com', '{"password":"..."}', 'pending')

// Update status
UPDATE manireports_cloud_jobs 
SET status = 'completed', emails_sent = 498, completed_at = NOW()
WHERE id = 12345
```

**Example Usage:**
```php
$manager = new cloud_job_manager();

// Create job
$job_id = $manager->create_job(
    'csv_import',
    [
        ['email' => 'user1@example.com', 'password' => 'TempPass123'],
        ['email' => 'user2@example.com', 'password' => 'TempPass456'],
    ],
    1  // company_id
);

// Submit to cloud
$manager->submit_job_to_cloud($job_id);

// Later, handle callback
$manager->handle_callback($job_id, [
    'status' => 'completed',
    'emails_sent' => 2,
    'emails_failed' => 0
]);
```

---

### `classes/api/cloud_connector.php`

**Purpose:** Abstract base class for cloud providers

**Key Methods:**

```php
abstract public function authenticate()
// Authenticates with cloud provider
// Returns: true/false

abstract public function submit_job($job_data)
// Submits job to cloud provider
// Returns: queue_message_id

abstract public function get_job_status($job_id)
// Gets job status from cloud provider
// Returns: status string

abstract public function retry_job($job_id)
// Retries a failed job
// Returns: true/false
```

**Architecture:**
- Defines interface for all cloud providers
- Handles common logic (error handling, logging)
- Delegates provider-specific logic to subclasses

---

### `classes/api/aws_connector.php`

**Purpose:** AWS-specific implementation (SQS + SES)

**Key Methods:**

```php
public function authenticate()
// Authenticates with AWS using credentials
// Uses: AWS SDK

public function submit_job($job_data)
// Sends job to SQS queue
// Returns: SQS message ID

public function get_job_status($job_id)
// Queries job status from database
// Returns: status

public function send_test_email($recipient)
// Sends test email via SES
// Returns: true/false
```

**AWS Integration:**

```php
// Initialize AWS SDK
$client = new SqsClient([
    'version' => 'latest',
    'region'  => $this->aws_region,
    'credentials' => [
        'key'    => $this->aws_access_key,
        'secret' => $this->aws_secret_key,
    ]
]);

// Send job to SQS
$result = $client->sendMessage([
    'QueueUrl'    => $this->sqs_queue_url,
    'MessageBody' => json_encode($job_data),
]);

return $result['MessageId'];
```

**Example Usage:**
```php
$connector = new aws_connector($company_id);
$connector->authenticate();

$job_data = [
    'job_id' => 12345,
    'type' => 'csv_import',
    'recipients' => [...]
];

$message_id = $connector->submit_job($job_data);
```

---

### `classes/api/cloudflare_connector.php`

**Purpose:** Cloudflare-specific implementation (Workers + Email API)

**Key Methods:**

```php
public function authenticate()
// Authenticates with Cloudflare API
// Uses: Cloudflare API token

public function submit_job($job_data)
// Sends job to Cloudflare KV store
// Returns: KV key

public function get_job_status($job_id)
// Queries job status from KV store
// Returns: status

public function send_test_email($recipient)
// Sends test email via Cloudflare Email API
// Returns: true/false
```

---

### `classes/api/license_allocation_handler.php`

**Purpose:** Handles license allocation email events

**Key Methods:**

```php
public function on_license_allocated($event)
// Triggered when license is allocated
// Extracts: license code, recipient email, company info
// Creates: cloud job

public function extract_license_data($license_id)
// Extracts license details
// Returns: license data array

public function compose_license_email($recipient, $license_code)
// Composes email with license code
// Returns: email body
```

**Event Handling:**

```php
// In db/events.php
$observers = [
    [
        'eventname' => '\iomad\event\license_allocated',
        'callback'  => '\local_manireports\api\license_allocation_handler::on_license_allocated',
    ],
];
```

---

### `classes/api/error_handler.php`

**Purpose:** Centralized error handling for cloud operations

**Key Methods:**

```php
public function handle_error($error_code, $error_message, $context)
// Logs error and determines retry strategy
// Returns: retry_strategy

public function should_retry($error_code)
// Determines if error is retryable
// Returns: true/false

public function get_retry_delay($retry_count)
// Calculates exponential backoff delay
// Returns: delay in seconds
```

**Error Types:**

```php
const ERROR_NETWORK_TIMEOUT = 'network_timeout';
const ERROR_RATE_LIMIT = 'rate_limit_exceeded';
const ERROR_INVALID_EMAIL = 'invalid_email';
const ERROR_AUTHENTICATION = 'authentication_failed';
const ERROR_DATABASE = 'database_error';
```

---

## 3. Event Observers

### `classes/observers/user_observer.php`

**Purpose:** Observes user creation events (CSV import)

**Key Methods:**

```php
public static function user_created(\core\event\user_created $event)
// Triggered when user is created
// Detects: bulk operation
// Creates: cloud job if bulk

public static function detect_bulk_user_creation($user_id)
// Checks if this is part of bulk import
// Returns: true/false
```

**Event Flow:**

```
CSV Import → Moodle creates 500 users → user_created event fired 500 times
    ↓
user_observer catches event
    ↓
Detects: "This is bulk operation (500 events in 10 seconds)"
    ↓
email_interceptor creates job
    ↓
cloud_job_manager submits to cloud
```

---

### `classes/observers/reengagement_observer.php`

**Purpose:** Observes reengagement campaign events

**Key Methods:**

```php
public static function campaign_triggered(\local_manireports\event\reengagement_campaign_triggered $event)
// Triggered when reengagement campaign starts
// Extracts: inactive students, course info
// Creates: cloud job

public static function extract_inactive_students($days)
// Finds students inactive for N days
// Returns: student list
```

---

## 4. UI & AJAX Endpoints

### `ui/cloud_jobs.php`

**Purpose:** Admin dashboard for monitoring cloud jobs

**Features:**

```php
// Display all jobs
$jobs = $DB->get_records('manireports_cloud_jobs', 
    ['company_id' => $company_id], 
    'created_at DESC'
);

// Show job details
- Job ID
- Type (CSV import, License allocation, Reengagement)
- Status (Pending, Queued, Processing, Completed, Failed)
- Email count
- Emails sent / failed
- Duration
- Error log

// Actions
- View details
- Retry failed emails
- Cancel job
- Download error report
```

**Template:** `templates/cloud_jobs_list.mustache`

---

### `ui/cloud_job_view.php`

**Purpose:** Detailed view of a single job

**Shows:**

```
Job Details:
- Job ID: 12345
- Type: CSV Import
- Status: Completed
- Created: 2024-11-22 10:30:00
- Completed: 2024-11-22 10:35:00
- Duration: 5 minutes

Email Statistics:
- Total: 500
- Sent: 498
- Failed: 2
- Success rate: 99.6%

Failed Emails:
- invalid_email@example.com (Invalid email format)
- bounced_user@example.com (Bounced)

Actions:
- Retry failed emails
- Download recipient list
- View error log
```

---

### `ui/ajax/cloud_callback.php`

**Purpose:** Receives status updates from cloud provider

**Endpoint:** `POST /local/manireports/ui/ajax/cloud_callback.php`

**Receives:**

```json
{
  "job_id": 12345,
  "status": "completed",
  "emails_sent": 498,
  "emails_failed": 2,
  "errors": [
    "invalid_email@example.com",
    "bounced_user@example.com"
  ],
  "timestamp": "2024-11-22T10:35:00Z"
}
```

**Processing:**

```php
// Verify callback signature
if (!$this->verify_callback_signature($request)) {
    return error_response('Invalid signature');
}

// Update job status
$manager = new cloud_job_manager();
$manager->handle_callback($job_id, $callback_data);

// Log audit trail
audit_logger::log('cloud_job_completed', $job_id);

// Return success
return success_response('Job updated');
```

---

## 5. Cloud Worker Code

### `cloud_workers/aws/lambda_handler.py`

**Purpose:** AWS Lambda function that processes email jobs

**Flow:**

```python
def lambda_handler(event, context):
    # 1. Parse SQS message
    for record in event['Records']:
        job_data = json.loads(record['body'])
        job_id = job_data['job_id']
        
        # 2. Fetch job details from Moodle
        job = fetch_job_from_moodle(job_id)
        recipients = fetch_recipients(job_id)
        
        # 3. Send emails
        sent_count = 0
        failed_count = 0
        errors = []
        
        for recipient in recipients:
            try:
                # Compose email
                email_body = compose_email(recipient)
                
                # Send via SES
                send_email_via_ses(
                    to=recipient['email'],
                    subject=job['subject'],
                    body=email_body
                )
                
                sent_count += 1
                
            except Exception as e:
                failed_count += 1
                errors.append(str(e))
        
        # 4. Send callback to Moodle
        send_callback_to_moodle({
            'job_id': job_id,
            'status': 'completed',
            'emails_sent': sent_count,
            'emails_failed': failed_count,
            'errors': errors
        })
        
        # 5. Delete message from SQS
        delete_message_from_sqs(record)
```

**Key Features:**

- Polls SQS queue
- Fetches job details from Moodle database
- Sends emails via AWS SES
- Handles errors and retries
- Sends callback to Moodle
- Deletes processed messages

---

### `cloud_workers/cloudflare/worker.js`

**Purpose:** Cloudflare Worker that processes email jobs

**Flow:**

```javascript
export default {
  async fetch(request, env, ctx) {
    // 1. Check if this is a job processing request
    if (request.method === 'POST' && request.url.includes('/process-job')) {
      const jobData = await request.json();
      
      // 2. Fetch job details from Moodle
      const job = await fetchJobFromMoodle(jobData.job_id);
      const recipients = await fetchRecipients(jobData.job_id);
      
      // 3. Send emails
      let sentCount = 0;
      let failedCount = 0;
      const errors = [];
      
      for (const recipient of recipients) {
        try {
          // Compose email
          const emailBody = composeEmail(recipient);
          
          // Send via Cloudflare Email API
          await sendEmailViaCloudflare({
            to: recipient.email,
            subject: job.subject,
            body: emailBody
          });
          
          sentCount++;
          
        } catch (error) {
          failedCount++;
          errors.push(error.message);
        }
      }
      
      // 4. Send callback to Moodle
      await sendCallbackToMoodle({
        job_id: jobData.job_id,
        status: 'completed',
        emails_sent: sentCount,
        emails_failed: failedCount,
        errors: errors
      });
      
      return new Response(JSON.stringify({success: true}));
    }
  }
};
```

---

## 6. Database Events

### `db/events.php`

**Purpose:** Registers event observers

```php
$observers = [
    [
        'eventname' => '\core\event\user_created',
        'callback'  => '\local_manireports\observers\user_observer::user_created',
    ],
    [
        'eventname' => '\iomad\event\license_allocated',
        'callback'  => '\local_manireports\api\license_allocation_handler::on_license_allocated',
    ],
    [
        'eventname' => '\local_manireports\event\reengagement_campaign_triggered',
        'callback'  => '\local_manireports\observers\reengagement_observer::campaign_triggered',
    ],
];
```

---

## 7. Templates

### `templates/cloud_jobs_list.mustache`

**Purpose:** Renders list of cloud jobs

```mustache
<div class="cloud-jobs-container">
  <h2>Cloud Email Jobs</h2>
  
  <table class="table">
    <thead>
      <tr>
        <th>Job ID</th>
        <th>Type</th>
        <th>Status</th>
        <th>Emails</th>
        <th>Sent</th>
        <th>Failed</th>
        <th>Created</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      {{#jobs}}
      <tr>
        <td>{{id}}</td>
        <td>{{type}}</td>
        <td><span class="badge badge-{{status}}">{{status}}</span></td>
        <td>{{email_count}}</td>
        <td>{{emails_sent}}</td>
        <td>{{emails_failed}}</td>
        <td>{{created_at}}</td>
        <td>
          <a href="cloud_job_view.php?id={{id}}">View</a>
          {{#can_retry}}<a href="#" onclick="retryJob({{id}})">Retry</a>{{/can_retry}}
        </td>
      </tr>
      {{/jobs}}
    </tbody>
  </table>
</div>
```

---

## 8. JavaScript

### `amd/src/cloud_jobs.js`

**Purpose:** Client-side interactions for cloud jobs dashboard

```javascript
define(['jquery', 'core/ajax'], function($, ajax) {
    return {
        init: function() {
            // Load jobs via AJAX
            this.loadJobs();
            
            // Refresh every 5 seconds
            setInterval(() => this.loadJobs(), 5000);
        },
        
        loadJobs: function() {
            ajax.call([{
                methodname: 'local_manireports_get_cloud_jobs',
                args: {},
                done: (response) => {
                    this.renderJobs(response);
                }
            }]);
        },
        
        retryJob: function(jobId) {
            ajax.call([{
                methodname: 'local_manireports_retry_cloud_job',
                args: {job_id: jobId},
                done: () => {
                    this.loadJobs();
                }
            }]);
        }
    };
});
```

---

## 9. Tests

### `tests/cloud_job_manager_test.php`

**Purpose:** PHPUnit tests for cloud job manager

```php
class cloud_job_manager_test extends \advanced_testcase {
    
    public function test_create_job() {
        $manager = new cloud_job_manager();
        $job_id = $manager->create_job('csv_import', [...], 1);
        $this->assertNotEmpty($job_id);
    }
    
    public function test_submit_job_to_cloud() {
        $manager = new cloud_job_manager();
        $job_id = $manager->create_job('csv_import', [...], 1);
        $result = $manager->submit_job_to_cloud($job_id);
        $this->assertTrue($result);
    }
    
    public function test_handle_callback() {
        $manager = new cloud_job_manager();
        $job_id = $manager->create_job('csv_import', [...], 1);
        $manager->submit_job_to_cloud($job_id);
        
        $result = $manager->handle_callback($job_id, [
            'status' => 'completed',
            'emails_sent' => 100,
            'emails_failed' => 0
        ]);
        
        $this->assertTrue($result);
    }
}
```

---

## File Dependency Map

```
User Action (CSV Import, License Allocation, Reengagement)
    ↓
Event fired (user_created, license_allocated, etc.)
    ↓
Observer catches event (user_observer.php, license_allocation_handler.php)
    ↓
email_interceptor.php detects bulk operation
    ↓
cloud_job_manager.php creates job record
    ↓
aws_connector.php / cloudflare_connector.php submits to cloud
    ↓
Database updated (manireports_cloud_jobs, manireports_cloud_recipients)
    ↓
Cloud provider processes (Lambda, Cloudflare Worker)
    ↓
cloud_callback.php receives status update
    ↓
cloud_job_manager.php updates job status
    ↓
cloud_jobs.php displays results to admin
```

---

## Configuration Files

### `settings.php` (Updated)

Adds cloud offload settings:

```php
$settings->add(new admin_setting_heading(
    'local_manireports/cloud_offload',
    get_string('cloud_offload', 'local_manireports'),
    ''
));

$settings->add(new admin_setting_configcheckbox(
    'local_manireports/cloud_offload_enabled',
    get_string('cloud_offload_enabled', 'local_manireports'),
    '',
    0
));

$settings->add(new admin_setting_configselect(
    'local_manireports/cloud_provider',
    get_string('cloud_provider', 'local_manireports'),
    '',
    'aws',
    ['aws' => 'AWS', 'cloudflare' => 'Cloudflare']
));

// AWS settings
$settings->add(new admin_setting_configtext(
    'local_manireports/aws_access_key',
    get_string('aws_access_key', 'local_manireports'),
    '',
    ''
));

// ... more settings
```

---

## Summary Table

| File | Purpose | Key Responsibility |
|------|---------|-------------------|
| `email_interceptor.php` | Intercepts bulk emails | Detect & route to cloud |
| `cloud_job_manager.php` | Manages job lifecycle | Create, submit, track jobs |
| `cloud_connector.php` | Abstract cloud interface | Define provider contract |
| `aws_connector.php` | AWS implementation | SQS + SES integration |
| `cloudflare_connector.php` | Cloudflare implementation | Workers + Email API |
| `license_allocation_handler.php` | License events | Handle license emails |
| `user_observer.php` | User creation events | Handle CSV import emails |
| `reengagement_observer.php` | Reengagement events | Handle campaign emails |
| `cloud_jobs.php` | Admin dashboard | Display job status |
| `cloud_job_view.php` | Job details | Show detailed info |
| `cloud_callback.php` | Receive updates | Process cloud callbacks |
| `lambda_handler.py` | AWS Lambda worker | Send emails via SES |
| `worker.js` | Cloudflare worker | Send emails via API |
| `cloud_jobs_list.mustache` | Job list template | Render job table |
| `cloud_jobs.js` | Client-side logic | AJAX interactions |

This completes the Phase 4 implementation with full cloud email offload capability!
