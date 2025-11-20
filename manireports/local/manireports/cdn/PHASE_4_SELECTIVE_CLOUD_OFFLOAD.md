# Phase 4: Selective Cloud Offload - Email & Certificate Generation

## Overview

**Purpose**: Offload ONLY email sending and certificate generation to cloud infrastructure to reduce Moodle server load for large deployments (100k+ users).

**What Goes to Cloud:**
- ✅ Bulk email sending (SES/SendGrid)
- ✅ Certificate PDF generation (Lambda/Workers)

**What Stays Self-Hosted:**
- ✅ Dashboards
- ✅ Reports
- ✅ Analytics
- ✅ Time tracking
- ✅ All data storage

**Target Volume**: 50,000+ emails per job
**Cloud Providers**: AWS (primary), Cloudflare (cost-effective)
**Estimated Time**: 18-22 hours
**Cost**: $5-15/month (AWS) or $5-10/month (Cloudflare)

---

## Architecture

```
┌──────────────────────────────────────┐
│      MOODLE (Self-Hosted)            │
├──────────────────────────────────────┤
│                                      │
│  ✅ Dashboards (local)               │
│  ✅ Reports (local)                  │
│  ✅ Analytics (local)                │
│  ✅ Time Tracking (local)            │
│  ✅ Data Storage (local)             │
│                                      │
│  ❌ Email Sending → Cloud (optional) │
│  ❌ Certificate Gen → Cloud (opt)    │
│                                      │
│  Per-Company Control:                │
│  ├─ Company A: Cloud enabled         │
│  ├─ Company B: Cloud disabled        │
│  ├─ Company C: Cloud for emails only │
│  └─ Company D: Cloud for certs only  │
│                                      │
└──────────────────────────────────────┘
         ↓ (only if enabled)
┌──────────────────────────────────────┐
│    CLOUD (AWS/Cloudflare)            │
├──────────────────────────────────────┤
│                                      │
│  ✅ Email Service (SES/SendGrid)     │
│  ✅ Certificate Generator (PDF)      │
│  ✅ Storage (S3/R2)                  │
│                                      │
│  Company-Isolated Processing:        │
│  ├─ Company A emails (50k users)     │
│  ├─ Company C certificates (30k)     │
│  └─ Fallback to local if disabled    │
│                                      │
└──────────────────────────────────────┘
```

---

## IOMAD Multi-Tenant Support

**Key Feature**: Each company can independently control cloud offload

**Per-Company Settings**:
- Enable/disable cloud offload entirely
- Enable/disable cloud for emails only
- Enable/disable cloud for certificates only
- Set user threshold (e.g., "use cloud if > 1000 users")

**Example Scenarios**:
- Company A (100k users): Cloud enabled for both emails & certificates
- Company B (500 users): Cloud disabled (local processing only)
- Company C (50k users): Cloud enabled for emails, local for certificates
- Company D (10k users): Cloud disabled (below threshold)

**Decision Logic**:
```
IF company_cloud_enabled AND
   (type == 'email' AND company_use_cloud_for_emails OR
    type == 'certificate' AND company_use_cloud_for_certificates) AND
   recipient_count >= company_user_threshold
THEN
   Use cloud offload
ELSE
   Use local processing
END
```

---

## Implementation Tasks

### Task 4.1: Database Schema (2 hours)

Create tables for tracking cloud jobs:

**Files to modify:**
- `db/install.xml` - Add cloud job tables
- `db/upgrade.php` - Add upgrade step

**Tables needed:**
- `manireports_cloud_jobs` - Job records
- `manireports_cloud_job_recipients` - Individual recipient status

### Task 4.2: Cloud Job Manager (4 hours)

**File**: `classes/api/cloud_job_manager.php`

Methods:
- `create_email_job()` - Create bulk email job
- `create_certificate_job()` - Create certificate generation job
- `batch_recipients()` - Split into chunks (200 per batch)
- `submit_to_cloud()` - Send to cloud queue
- `update_job_status()` - Update from callback
- `get_job_progress()` - Track completion

### Task 4.3: Cloud Connector (3 hours)

**Files**:
- `classes/api/cloud_connector.php` (abstract)
- `classes/api/aws_connector.php` (AWS SQS)
- `classes/api/cloudflare_connector.php` (Cloudflare Queue)

Features:
- Queue message formatting
- HMAC signature generation
- Error handling & retries

### Task 4.4: Callback Handler (2 hours)

**File**: `ui/ajax/cloud_callback.php`

Features:
- HMAC signature validation
- Timestamp validation (prevent replay)
- Update job status
- Update recipient status
- Log to audit_logs

### Task 4.5: Certificate Generator (4 hours)

**File**: `classes/api/certificate_generator.php`

Features:
- PDF template management
- User/course data merging
- Prepare data for cloud generation
- Local fallback option

### Task 4.6: Settings & Configuration (3 hours)

**File**: `settings.php`

Global Settings:
- `cloud_offload_enabled` (checkbox) - Enable/disable cloud offload globally
- `cloud_mode` (sqs or api_gateway)
- `cloud_endpoint` (URL)
- `cloud_auth_token` (password)
- `email_provider` (ses, sendgrid, mailgun)
- `job_batch_size` (int, default 200)
- `cloud_callback_secret` (password)

**NEW: Per-Company Settings (IOMAD)**

Add new table: `manireports_company_cloud_settings`
```sql
CREATE TABLE mdl_manireports_company_cloud_settings (
  id BIGINT(10) PRIMARY KEY AUTO_INCREMENT,
  companyid BIGINT(10) NOT NULL UNIQUE,
  cloud_enabled TINYINT(1) NOT NULL DEFAULT 0,
  use_cloud_for_emails TINYINT(1) NOT NULL DEFAULT 0,
  use_cloud_for_certificates TINYINT(1) NOT NULL DEFAULT 0,
  user_threshold INT(10) NOT NULL DEFAULT 1000,
  timecreated BIGINT(10) NOT NULL,
  timemodified BIGINT(10) NOT NULL,
  INDEX idx_companyid (companyid)
);
```

**Company Admin UI** (`ui/company_cloud_settings.php`):
- Enable/disable cloud offload per company
- Toggle email sending to cloud
- Toggle certificate generation to cloud
- Set user threshold (e.g., "use cloud if company has > 1000 users")
- View cloud job history for company

**Logic in scheduler.php**:
```php
public function should_use_cloud($companyid, $recipientcount, $type) {
    // Get company settings
    $settings = $this->get_company_cloud_settings($companyid);
    
    if (!$settings->cloud_enabled) {
        return false;  // Company disabled cloud offload
    }
    
    if ($type === 'email' && !$settings->use_cloud_for_emails) {
        return false;  // Company disabled cloud emails
    }
    
    if ($type === 'certificate' && !$settings->use_cloud_for_certificates) {
        return false;  // Company disabled cloud certificates
    }
    
    if ($recipientcount < $settings->user_threshold) {
        return false;  // Not enough users to justify cloud
    }
    
    return true;  // Use cloud offload
}
```

### Task 4.7: Job Monitoring UI (3 hours)

**Files**:
- `ui/cloud_jobs.php` - Job list page
- `templates/cloud_jobs_list.mustache` - Template
- `amd/src/cloud_jobs.js` - Auto-refresh logic

Features:
- List all jobs with status
- Progress tracking
- Manual retry button
- Error messages

### Task 4.8: Email Integration (2 hours)

**Modify**: `classes/api/scheduler.php`

Add method:
```php
public function send_via_cloud_or_local(array $recipients, object $message) {
    if ($this->is_cloud_enabled() && count($recipients) > 100) {
        return $this->send_via_cloud($recipients, $message);
    } else {
        return $this->send_via_local($recipients, $message);
    }
}
```

### Task 4.9: Cloud Worker Implementation (6 hours)

**AWS Lambda** (Python/Node.js):
- Pull job from SQS
- Send emails via SES
- Generate certificates (PDF)
- Upload to S3
- Callback to Moodle

**Cloudflare Worker** (JavaScript):
- Queue consumer
- Email via SendGrid
- Certificate generation
- R2 storage
- Callback to Moodle

### Task 4.10: Testing & Documentation (2 hours)

- PHPUnit tests
- Integration tests
- Setup documentation
- Troubleshooting guide

---

## File Structure

```
local/manireports/
├── classes/api/
│   ├── cloud_job_manager.php          # NEW
│   ├── cloud_connector.php            # NEW
│   ├── aws_connector.php              # NEW
│   ├── cloudflare_connector.php       # NEW
│   └── certificate_generator.php      # NEW
│
├── ui/
│   ├── cloud_jobs.php                 # NEW
│   └── ajax/
│       └── cloud_callback.php         # NEW
│
├── templates/
│   └── cloud_jobs_list.mustache       # NEW
│
├── amd/src/
│   └── cloud_jobs.js                  # NEW
│
└── cdn/                               # NEW DIRECTORY
    ├── PHASE_4_SELECTIVE_CLOUD_OFFLOAD.md
    ├── AWS_SETUP.md
    ├── CLOUDFLARE_SETUP.md
    └── TROUBLESHOOTING.md
```

---

## Database Schema

### manireports_cloud_jobs
```sql
CREATE TABLE mdl_manireports_cloud_jobs (
  id BIGINT(10) PRIMARY KEY AUTO_INCREMENT,
  jobid VARCHAR(36) NOT NULL UNIQUE,
  type VARCHAR(20) NOT NULL,  -- 'email' or 'certificate'
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
  INDEX idx_status (status)
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

---

## Configuration Examples

### AWS Configuration
```php
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
$config->cloud_offload_enabled = true;
$config->cloud_mode = 'api_gateway';
$config->cloud_endpoint = 'https://manireports-worker.your-subdomain.workers.dev';
$config->cloud_auth_token = 'your-cloudflare-api-token';
$config->email_provider = 'sendgrid';
$config->job_batch_size = 200;
$config->cloud_callback_secret = 'your-secret-key-here';
```

---

## Job JSON Format

```json
{
  "job_id": "550e8400-e29b-41d4-a716-446655440000",
  "type": "email",
  "recipients": [
    {
      "userid": 1001,
      "email": "user1@example.com",
      "firstname": "John",
      "lastname": "Doe"
    }
  ],
  "template_id": "course_completion_reminder",
  "template_data": {
    "course_name": "Introduction to PHP",
    "course_url": "https://yourmoodle.com/course/view.php?id=101"
  },
  "callback_url": "https://yourmoodle.com/local/manireports/ui/ajax/cloud_callback.php",
  "callback_secret": "hmac_secret_key",
  "timestamp": "2025-11-18T12:00:00Z"
}
```

---

## Cost Estimates

### AWS (50,000 emails/day)
- SQS: $0.40/month
- Lambda: $5/month
- SES: $5/month
- S3: $1/month
- **Total**: ~$11/month

### Cloudflare (50,000 emails/day)
- Workers: $5/month
- R2 Storage: $0.50/month
- SendGrid: $15/month (or free tier)
- **Total**: ~$5-20/month

---

## Testing Checklist

- [ ] Database tables created
- [ ] Cloud job manager creates jobs
- [ ] Job batching works correctly
- [ ] Cloud connector sends to queue
- [ ] Callback endpoint validates HMAC
- [ ] Job status updates correctly
- [ ] Certificate generator creates PDFs
- [ ] Email routing works (cloud vs local)
- [ ] Job monitoring UI displays jobs
- [ ] Manual retry works
- [ ] Fallback to local works
- [ ] Audit logs record operations

---

## Deployment Steps

1. Deploy Moodle plugin updates
2. Run database upgrade
3. Configure cloud settings
4. Deploy cloud worker
5. Test with small job (5 recipients)
6. Monitor logs
7. Scale to production

---

## Success Criteria

- [ ] Successfully send 50,000 emails via cloud
- [ ] Job completion time < 30 minutes
- [ ] Bounce rate < 2%
- [ ] Callback success rate > 99%
- [ ] Zero data loss
- [ ] Fallback works when cloud unavailable
- [ ] Cost within budget
- [ ] No Moodle performance impact
- [ ] Admin can monitor jobs easily

---

**Status**: Ready for implementation after MVP completion
**Priority**: Phase 4 (Future Enhancement)
**Estimated Completion**: 18-22 hours
