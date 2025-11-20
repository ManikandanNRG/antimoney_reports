# Cloud Offload Implementation Plan - Phase 4

## Overview

**Purpose**: Offload heavy operations (bulk email sending and certificate generation) to external cloud infrastructure to avoid overloading Moodle server.

**Target Volume**: 50,000 emails per job
**Cloud Providers**: AWS (primary), Cloudflare (cost-effective alternative)
**Implementation Phase**: Phase 4 (after MVP completion)
**Estimated Time**: 30-41 hours

## Prerequisites

Before starting Cloud Offload implementation:
- ✅ MVP features complete and tested
- ✅ Privacy API implemented
- ✅ Data cleanup task working
- ✅ All core reports functional
- ✅ Scheduled reports working
- ✅ Export engine operational

## Architecture Overview

```
[Moodle Plugin]
    ↓
[Cloud Job Manager] → Create job records
    ↓
[Cloud Connector] → Push to API Gateway or SQS
    ↓
[Cloud Worker Pool] → Process jobs (Lambda/ECS or Cloudflare Workers)
    ↓
[Email Provider] → Send emails (SES/SendGrid/Mailgun)
    ↓
[Storage] → Store certificates (S3/R2)
    ↓
[Callback Handler] → Update Moodle job status
```

## Implementation Phases

### Phase 4.1: Database Schema (2 hours)

**Task 4.1.1: Create cloud job tables**

Files to create/modify:
- `db/install.xml` - Add manireports_cloud_jobs table
- `db/install.xml` - Add manireports_cloud_job_recipients table
- `db/upgrade.php` - Add upgrade step for new tables

**Task 4.1.2: Test database installation**
```bash
# SSH to EC2
sudo -u www-data php admin/cli/upgrade.php --non-interactive
sudo -u www-data php admin/cli/purge_caches.php

# Verify tables created
mysql -u moodle_user -p -e "SHOW TABLES LIKE 'mdl_manireports_cloud%';"
```

### Phase 4.2: Cloud Job Manager API (6-8 hours)

**Task 4.2.1: Create cloud_job_manager.php**

Files to create:
- `classes/api/cloud_job_manager.php`

Key methods:
- `create_bulk_email_job()` - Create job with recipients
- `batch_recipients()` - Split into chunks (200 per batch)
- `submit_job_to_cloud()` - Send to cloud connector
- `update_job_status()` - Update from callback
- `get_job_progress()` - Get completion percentage
- `retry_failed_recipients()` - Requeue failed items

**Task 4.2.2: Implement job batching logic**
- Split large recipient lists into configurable chunks
- Create parent job and child batch records
- Track progress across batches

**Task 4.2.3: Add language strings**

File: `lang/en/local_manireports.php`
- Add cloud offload related strings
- Job status strings (pending, processing, completed, failed)
- Error messages

### Phase 4.3: Cloud Connector (4-6 hours)

**Task 4.3.1: Create abstract cloud_connector.php**

Files to create:
- `classes/api/cloud_connector.php` (abstract base)
- `classes/api/aws_connector.php` (AWS implementation)
- `classes/api/cloudflare_connector.php` (Cloudflare implementation)

**Task 4.3.2: Implement AWS connector**
- API Gateway HTTP POST implementation
- Direct SQS message sending (using AWS SDK)
- HMAC signature generation
- Error handling and retries

**Task 4.3.3: Implement Cloudflare connector**
- Queue API integration
- R2 storage integration
- Email routing API
- Worker invocation

**Task 4.3.4: Add connector factory**
- Auto-select connector based on settings
- Fallback to local processing if cloud unavailable

### Phase 4.4: Callback Handler (3-4 hours)

**Task 4.4.1: Create cloud_callback.php endpoint**

File: `ui/ajax/cloud_callback.php`

Features:
- HMAC signature validation
- Timestamp validation (prevent replay)
- Update job status
- Update recipient status
- Log to audit_logs
- Return JSON response

**Task 4.4.2: Implement security validation**

```php
// HMAC validation
$signature = hash_hmac('sha256', $request_body, $callback_secret);
if (!hash_equals($signature, $_SERVER['HTTP_X_SIGNATURE'])) {
    http_response_code(401);
    die('Invalid signature');
}

// Timestamp validation
$timestamp = $payload['timestamp'] ?? 0;
if (abs(time() - $timestamp) > 300) {
    http_response_code(401);
    die('Request expired');
}
```

**Task 4.4.3: Test callback endpoint**
```bash
# Test with curl
curl -X POST https://yourmoodle.com/local/manireports/ui/ajax/cloud_callback.php \
  -H "Content-Type: application/json" \
  -H "X-Signature: test_signature" \
  -d '{"job_id":"test","status":"completed"}'
```

### Phase 4.5: Certificate Generator (4-6 hours)

**Task 4.5.1: Create certificate_generator.php**

File: `classes/api/certificate_generator.php`

Features:
- Local PDF generation using mPDF/TCPDF
- Template management
- User/course data merging
- Integration with Moodle custom certificate plugin
- Prepare data for cloud generation

**Task 4.5.2: Create default certificate template**
- HTML/CSS template for certificates
- Placeholder variables (username, course, date, etc.)
- Configurable template system

**Task 4.5.3: Implement certificate data provider**
- Extract user data (name, email, etc.)
- Extract course data (name, completion date, grade)
- Format data for template merging

### Phase 4.6: Settings and Configuration (2-3 hours)

**Task 4.6.1: Add cloud settings to settings.php**

Settings to add:
- `cloud_offload_enabled` (checkbox)
- `cloud_mode` (api_gateway or sqs)
- `cloud_endpoint` (URL)
- `cloud_auth_token` (password)
- `aws_region` (text)
- `sqs_queue_url` (text)
- `job_batch_size` (int, default 200)
- `email_provider` (ses, sendgrid, mailgun, custom)
- `cloud_callback_secret` (password)
- `presigned_url_ttl` (int, default 3600)
- `certificate_generation_mode` (cloud, local, cloud_if_enabled)

**Task 4.6.2: Add test connection button**
- AJAX endpoint to test cloud connectivity
- Validate credentials
- Display success/error message

### Phase 4.7: Job Monitoring UI (4-5 hours)

**Task 4.7.1: Create cloud_jobs.php page**

File: `ui/cloud_jobs.php`

Features:
- List all cloud jobs with filters
- Job status (pending, processing, completed, failed)
- Progress bars
- Recipient breakdown
- Manual retry button
- Export job logs

**Task 4.7.2: Create cloud_job_view.php page**

File: `ui/cloud_job_view.php`

Features:
- Detailed job information
- Recipient list with individual status
- Error messages for failed recipients
- Retry individual recipients
- Download certificate URLs

**Task 4.7.3: Create Mustache templates**

Files:
- `templates/cloud_jobs_list.mustache`
- `templates/cloud_job_detail.mustache`
- `templates/cloud_job_status_widget.mustache`

**Task 4.7.4: Create AMD JavaScript module**

File: `amd/src/cloud_jobs.js`

Features:
- Auto-refresh job status every 5 seconds
- AJAX status updates
- Progress bar animations
- Retry button handlers

### Phase 4.8: Email Integration (3-4 hours)

**Task 4.8.1: Create email router**

Modify: `classes/api/scheduler.php`

Add method:
```php
public function send_via_cloud_or_local(array $recipients, object $message) {
    if ($this->is_cloud_enabled() && count($recipients) > $threshold) {
        return $this->send_via_cloud($recipients, $message);
    } else {
        return $this->send_via_local($recipients, $message);
    }
}
```

**Task 4.8.2: Create email templates**
- Course completion reminder template
- Engagement reminder template
- Custom reminder template
- Template variable system

**Task 4.8.3: Integrate with scheduled reports**
- Modify report_scheduler task to use cloud for large recipient lists
- Add cloud job option to schedule creation UI

### Phase 4.9: Cloud Worker Implementation (8-12 hours)

**Task 4.9.1: Create AWS Lambda worker**

File: `cloud_workers/aws/lambda_handler.py` (or Node.js)

Features:
- Pull job from SQS
- Parse job JSON
- Generate certificate PDF (if required)
- Upload to S3
- Generate presigned URL
- Send email via SES
- Callback to Moodle
- Handle retries
- Error logging

**Task 4.9.2: Create CloudFormation template**

File: `cloud_workers/aws/cloudformation.yaml`

Resources:
- SQS queue (main and DLQ)
- Lambda function
- IAM roles and policies
- S3 bucket for certificates
- CloudWatch alarms

**Task 4.9.3: Create Cloudflare Worker (alternative)**

File: `cloud_workers/cloudflare/worker.js`

Features:
- Queue consumer
- R2 storage integration
- Email API integration
- Callback to Moodle

**Task 4.9.4: Create deployment scripts**

Files:
- `cloud_workers/aws/deploy.sh` - AWS deployment script
- `cloud_workers/cloudflare/deploy.sh` - Cloudflare deployment script
- `cloud_workers/README.md` - Deployment instructions

### Phase 4.10: Testing and Documentation (3-4 hours)

**Task 4.10.1: Create PHPUnit tests**

Files:
- `tests/cloud_job_manager_test.php`
- `tests/cloud_connector_test.php`
- `tests/certificate_generator_test.php`

**Task 4.10.2: Create integration tests**
- End-to-end job submission test
- Callback processing test
- Fallback to local test

**Task 4.10.3: Create documentation**

Files:
- `docs/CLOUD_OFFLOAD_SETUP.md` - Setup guide
- `docs/CLOUD_OFFLOAD_AWS.md` - AWS-specific guide
- `docs/CLOUD_OFFLOAD_CLOUDFLARE.md` - Cloudflare-specific guide
- `docs/CLOUD_OFFLOAD_TROUBLESHOOTING.md` - Common issues

**Task 4.10.4: Update main README**
- Add cloud offload section
- Link to setup guides
- Add cost estimates

## File Structure

```
local/manireports/
├── classes/
│   └── api/
│       ├── cloud_job_manager.php          # NEW
│       ├── cloud_connector.php            # NEW (abstract)
│       ├── aws_connector.php              # NEW
│       ├── cloudflare_connector.php       # NEW
│       └── certificate_generator.php      # NEW
│
├── ui/
│   ├── cloud_jobs.php                     # NEW
│   ├── cloud_job_view.php                 # NEW
│   └── ajax/
│       └── cloud_callback.php             # NEW
│
├── templates/
│   ├── cloud_jobs_list.mustache           # NEW
│   ├── cloud_job_detail.mustache          # NEW
│   └── cloud_job_status_widget.mustache   # NEW
│
├── amd/src/
│   └── cloud_jobs.js                      # NEW
│
├── cloud_workers/                         # NEW DIRECTORY
│   ├── aws/
│   │   ├── lambda_handler.py
│   │   ├── cloudformation.yaml
│   │   ├── requirements.txt
│   │   └── deploy.sh
│   ├── cloudflare/
│   │   ├── worker.js
│   │   ├── wrangler.toml
│   │   └── deploy.sh
│   └── README.md
│
├── docs/                                  # NEW DIRECTORY
│   ├── CLOUD_OFFLOAD_SETUP.md
│   ├── CLOUD_OFFLOAD_AWS.md
│   ├── CLOUD_OFFLOAD_CLOUDFLARE.md
│   └── CLOUD_OFFLOAD_TROUBLESHOOTING.md
│
└── tests/
    ├── cloud_job_manager_test.php         # NEW
    ├── cloud_connector_test.php           # NEW
    └── certificate_generator_test.php     # NEW
```

## Database Schema Details

### manireports_cloud_jobs
```sql
CREATE TABLE mdl_manireports_cloud_jobs (
  id BIGINT(10) PRIMARY KEY AUTO_INCREMENT,
  jobid VARCHAR(36) NOT NULL UNIQUE,
  tenantid BIGINT(10) NULL,
  type VARCHAR(50) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  total_recipients INT(10) NOT NULL,
  completed_recipients INT(10) NOT NULL DEFAULT 0,
  failed_recipients INT(10) NOT NULL DEFAULT 0,
  payload_json LONGTEXT NOT NULL,
  createdby BIGINT(10) NOT NULL,
  timecreated BIGINT(10) NOT NULL,
  timestarted BIGINT(10) NULL,
  timecompleted BIGINT(10) NULL,
  INDEX idx_jobid (jobid),
  INDEX idx_status (status),
  INDEX idx_tenantid (tenantid)
);
```

### manireports_cloud_job_recipients
```sql
CREATE TABLE mdl_manireports_cloud_job_recipients (
  id BIGINT(10) PRIMARY KEY AUTO_INCREMENT,
  jobid VARCHAR(36) NOT NULL,
  userid BIGINT(10) NOT NULL,
  email VARCHAR(255) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  attempts INT(2) NOT NULL DEFAULT 0,
  certificate_url TEXT NULL,
  error_message TEXT NULL,
  timesent BIGINT(10) NULL,
  UNIQUE KEY idx_jobid_userid (jobid, userid),
  INDEX idx_status (status)
);
```

## Configuration Examples

### AWS Configuration
```php
// settings.php values
$config->cloud_offload_enabled = true;
$config->cloud_mode = 'sqs';
$config->aws_region = 'us-east-1';
$config->sqs_queue_url = 'https://sqs.us-east-1.amazonaws.com/123456789/manireports-jobs';
$config->cloud_auth_token = 'AWS_ACCESS_KEY:AWS_SECRET_KEY';
$config->email_provider = 'ses';
$config->job_batch_size = 200;
$config->cloud_callback_secret = 'your-secret-key-here';
```

### Cloudflare Configuration
```php
// settings.php values
$config->cloud_offload_enabled = true;
$config->cloud_mode = 'api_gateway';
$config->cloud_endpoint = 'https://manireports-worker.your-subdomain.workers.dev';
$config->cloud_auth_token = 'your-cloudflare-api-token';
$config->email_provider = 'sendgrid';
$config->job_batch_size = 200;
$config->cloud_callback_secret = 'your-secret-key-here';
```

## Job JSON Example

```json
{
  "job_id": "550e8400-e29b-41d4-a716-446655440000",
  "tenant_id": 123,
  "type": "send_reminder",
  "courseid": 101,
  "created_by": 50,
  "recipients": [
    {
      "userid": 1001,
      "email": "user1@example.com",
      "firstname": "John",
      "lastname": "Doe"
    },
    {
      "userid": 1002,
      "email": "user2@example.com",
      "firstname": "Jane",
      "lastname": "Smith"
    }
  ],
  "template_id": "course_completion_reminder",
  "template_data": {
    "course_name": "Introduction to PHP",
    "course_url": "https://yourmoodle.com/course/view.php?id=101",
    "due_date": "2025-12-31",
    "completion_percentage": 45
  },
  "generate_certificate": true,
  "certificate_template": "default",
  "callback_url": "https://yourmoodle.com/local/manireports/ui/ajax/cloud_callback.php",
  "callback_secret": "hmac_secret_key",
  "timestamp": "2025-11-18T12:00:00Z"
}
```

## Callback JSON Example

```json
{
  "job_id": "550e8400-e29b-41d4-a716-446655440000",
  "recipient_updates": [
    {
      "userid": 1001,
      "status": "completed",
      "certificate_url": "https://s3.amazonaws.com/bucket/cert-1001.pdf?presigned",
      "timesent": 1700308800
    },
    {
      "userid": 1002,
      "status": "failed",
      "error_message": "Email bounce: Invalid address",
      "attempts": 3
    }
  ],
  "timestamp": 1700308900
}
```

## Testing Checklist

- [ ] Database tables created successfully
- [ ] Cloud job manager creates jobs correctly
- [ ] Job batching works for large recipient lists
- [ ] AWS connector sends to SQS successfully
- [ ] Cloudflare connector posts to worker successfully
- [ ] Callback endpoint validates HMAC correctly
- [ ] Callback endpoint updates job status
- [ ] Certificate generator creates valid PDFs
- [ ] Email routing chooses cloud vs local correctly
- [ ] Job monitoring UI displays jobs
- [ ] Progress updates in real-time
- [ ] Manual retry works for failed recipients
- [ ] Fallback to local works when cloud disabled
- [ ] IOMAD filtering applies to cloud jobs
- [ ] Audit logs record cloud operations
- [ ] Worker processes jobs correctly
- [ ] Worker uploads certificates to S3/R2
- [ ] Worker sends emails via SES/SendGrid
- [ ] Worker callbacks to Moodle successfully
- [ ] DLQ receives failed jobs after max retries
- [ ] CloudWatch/monitoring shows metrics

## Deployment Steps

### Step 1: Deploy Moodle Plugin Updates
```bash
# SSH to EC2
cd /var/www/html/moodle/local/manireports

# Pull latest code with cloud offload
git pull origin main

# Run upgrade
sudo -u www-data php admin/cli/upgrade.php --non-interactive
sudo -u www-data php admin/cli/purge_caches.php
```

### Step 2: Deploy AWS Infrastructure
```bash
cd cloud_workers/aws

# Configure AWS CLI
aws configure

# Deploy CloudFormation stack
./deploy.sh production us-east-1

# Note the SQS queue URL from outputs
```

### Step 3: Configure Plugin Settings
```
1. Navigate to Site Administration → Plugins → Local plugins → ManiReports
2. Enable cloud offload
3. Select cloud mode (SQS)
4. Enter AWS region
5. Enter SQS queue URL
6. Enter AWS credentials
7. Select email provider (SES)
8. Enter callback secret
9. Save changes
10. Click "Test Connection" button
```

### Step 4: Test with Small Job
```bash
# Create test job via UI or CLI
sudo -u www-data php local/manireports/cli/test_cloud_job.php --recipients=5

# Monitor job status
tail -f /var/www/html/moodledata/error.log

# Check SQS queue
aws sqs get-queue-attributes --queue-url YOUR_QUEUE_URL --attribute-names All

# Check Lambda logs
aws logs tail /aws/lambda/manireports-worker --follow
```

## Cost Estimates

### AWS (50,000 emails/day)
- **SQS**: $0.40/month (1M requests free tier)
- **Lambda**: $5/month (400,000 GB-seconds free tier)
- **SES**: $5/month ($0.10 per 1,000 emails)
- **S3**: $1/month (5GB storage, 20,000 GET requests)
- **CloudWatch**: $1/month (basic monitoring)
- **Total**: ~$12-15/month

### Cloudflare (50,000 emails/day)
- **Workers**: $5/month (100,000 requests/day included)
- **R2 Storage**: $0.50/month (10GB storage)
- **Email Routing**: Free (or SendGrid $15/month for 40k emails)
- **Total**: ~$5-20/month

## Monitoring and Alerts

### CloudWatch Alarms (AWS)
```yaml
Alarms:
  - QueueDepthAlarm:
      Threshold: 1000 messages
      Action: SNS notification to admin
  
  - DLQNotEmptyAlarm:
      Threshold: 1 message
      Action: SNS notification to admin
  
  - LambdaErrorRateAlarm:
      Threshold: 5% error rate
      Action: SNS notification to admin
  
  - SESBounceRateAlarm:
      Threshold: 5% bounce rate
      Action: SNS notification to admin
```

### Moodle-Side Monitoring
- Scheduled task to check for stuck jobs (> 1 hour in processing)
- Daily report of failed jobs
- Email admin if DLQ has messages
- Dashboard widget showing cloud job statistics

## Troubleshooting Guide

### Issue: Jobs stuck in "pending" status
**Cause**: Cloud connector not sending to queue
**Solution**: 
- Check cloud_endpoint setting
- Verify AWS credentials
- Check network connectivity
- Review error logs

### Issue: Callback not updating status
**Cause**: HMAC validation failing
**Solution**:
- Verify callback_secret matches in worker and Moodle
- Check timestamp is within 5 minutes
- Review callback endpoint logs

### Issue: High bounce rate
**Cause**: Invalid email addresses
**Solution**:
- Validate email addresses before job creation
- Clean up user email data
- Configure SES bounce handling

### Issue: Certificates not generating
**Cause**: Template or data issues
**Solution**:
- Test certificate generation locally first
- Check template syntax
- Verify user/course data availability

## Security Considerations

1. **HMAC Validation**: Always validate callback signatures
2. **Timestamp Validation**: Prevent replay attacks
3. **IP Whitelist**: Restrict callback endpoint to cloud IPs
4. **Secrets Management**: Use environment variables, not hardcoded
5. **Presigned URLs**: Set appropriate TTL (1-24 hours)
6. **Data Minimization**: Only send necessary PII to cloud
7. **Encryption**: Use TLS 1.2+ for all communications
8. **IAM Roles**: Use least-privilege principle for AWS
9. **Audit Logging**: Log all cloud operations
10. **GDPR Compliance**: Provide data deletion mechanism

## Success Criteria

- [ ] Successfully send 50,000 emails via cloud
- [ ] Job completion time < 30 minutes for 50k emails
- [ ] Bounce rate < 2%
- [ ] Callback success rate > 99%
- [ ] Zero data loss (all statuses tracked)
- [ ] Fallback works when cloud unavailable
- [ ] Cost within budget ($15/month for AWS)
- [ ] No Moodle performance impact
- [ ] Admin can monitor jobs easily
- [ ] Documentation complete and clear

## Next Steps After Implementation

1. Monitor performance for 1 week
2. Optimize batch sizes based on metrics
3. Fine-tune retry policies
4. Add more email templates
5. Implement certificate template builder UI
6. Add webhook notifications for job completion
7. Create mobile app integration
8. Add analytics dashboard for cloud operations

---

**Status**: Ready for implementation after MVP completion
**Priority**: Phase 4 (Future Enhancement)
**Dependencies**: MVP features, Privacy API, Data Cleanup
**Estimated Completion**: 30-41 hours after starting Phase 4
