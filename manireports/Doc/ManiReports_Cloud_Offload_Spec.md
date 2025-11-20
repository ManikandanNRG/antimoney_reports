# ManiReports — Cloud Offload Module Specification

## 1. Purpose
Allow ManiReports to **offload two heavy operations** from the Moodle host to an external, scalable worker system you control (e.g., AWS or Cloudflare + compute):

1. Bulk email sending (reminders/notifications)  
2. Certificate generation (PDFs) for completed users

Goals:
- Avoid overloading Moodle (CPU, memory, SMTP connections).  
- Keep control of data (use your cloud; opt-in per site/company).  
- Provide audited, idempotent, secure processing and status callbacks.  
- Support retries, DLQ, monitoring and fallbacks.

This feature is **opt-in** via plugin settings and per-job toggles; ManiReports must function fully without cloud offload.

## 2. High-level architecture (overview)

```
[Moodle (ManiReports plugin)]
    |
    |-- (1) Prepare Job JSON / chunk recipients
    |-- (2) Persist job locally -> manireports_cloud_jobs
    |-- (3) Enqueue job -> Cloud Ingest (API Gateway) OR Direct SQS
    |
[Cloud Layer (customer's AWS/Cloudflare)]
    |
    |-- Ingest (API Gateway) -> SQS / Queue
    |-- Worker Pool (Lambda or ECS/Fargate)
    |     - Generates certificate PDF (if requested)
    |     - Uploads PDF to S3/R2 and generates presigned URL
    |     - Sends mail via SES/SendGrid/Mailgun
    |     - Calls Moodle callback URL per recipient or per job
    |
    |-- Monitoring: CloudWatch/Logs/Alerts
```

## 3. Plugin Settings (Admin UI)

### Cloud Offload (Global)
- `cloud_offload_enabled` (boolean)
- `cloud_mode` (`api_gateway`, `sqs`)
- `cloud_endpoint` (string URL)
- `cloud_auth_token` (secret)
- `aws_region` (string)
- `sqs_queue_url` (string)
- `job_batch_size` (int, default 200)
- `certificate_generation_mode` (`cloud`, `local`, `cloud_if_enabled`)
- `presigned_url_ttl` (int)
- `email_provider` (`ses`, `sendgrid`, `mailgun`, `custom`)
- `cloud_callback_secret` (secret)
- `cloud_retry_policy` (json)
- `cloud_fallback_behavior` (`queue_local`, `fail`)

Supports per-tenant override (IOMAD).

## 4. Database Tables

### `manireports_cloud_jobs`
Tracks cloud jobs.

### `manireports_cloud_job_recipients`
Tracks per-recipient status.

(See full schema in conversation text.)

## 5. Job JSON Contract

```json
{
  "job_id": "UUIDv4",
  "tenant_id": 123,
  "type": "send_reminder",
  "courseid": 101,
  "created_by": 50,
  "recipients": [
    {"userid": 1001, "email": "u1@example.com"}
  ],
  "template_id": "reminder_v1",
  "generate_certificate": true,
  "callback_url": "https://yourmoodle.com/local/manireports/ui/ajax/cloud_callback.php",
  "timestamp": "2025-11-18T12:00:00Z"
}
```

## 6. Ingestion Methods

### A. API Gateway Mode
Plugin POSTs to `cloud_endpoint`.

### B. Direct SQS Mode
Plugin uses AWS SDK to send messages.

## 7. Callback Endpoint

`ui/ajax/cloud_callback.php`  
Accepts worker updates. Performs signature validation using HMAC.

## 8. Worker Responsibilities

- Pull job from queue  
- Generate certificate if required  
- Upload PDF to S3/R2  
- Generate presigned URL  
- Send email via SES provider  
- Callback to Moodle with status  
- Update job summary  

## 9. Security

- TLS 1.2+  
- HMAC for callback  
- IAM roles  
- KMS encryption  
- Minimal PII transfer  
- Presigned URLs expire  

## 10. Retry & DLQ

- SQS DLQ  
- Retry policies  
- Worker idempotency  
- Plugin requeue UI for failed recipients  

## 11. Monitoring

- SQS queue depth  
- Worker errors  
- SES bounce rate  
- Cloud callback logs  
- Moodle-side job progress UI  

## 12. Admin UI Flows

### A. Manual Reminders
- Select course  
- Choose cloud mode  
- Plugin creates jobs  
- Cloud executes  
- Callback updates statuses  

### B. Scheduled Jobs
- Plugin builds and pushes jobs  
- Cloud executes scheduled actions  

## 13. Acceptance Criteria

(See detailed list in conversation.)

## 14. Rollout Plan

- Dev → Staging → Pilot → Production  
- Fallback to local mode  
- Progressive scaling  

## 15. Example Code Snippets

Includes:
- HTTP push job  
- Callback signature validation  

## 16. Monitoring Checklist

- SQS alerts  
- DLQ alerts  
- SES bounce alerts  
- Local push errors  

## 17. Costs & Sizing

- S3 cheap  
- SES cheap  
- ECS needed for heavy PDF generation  

## 18. GDPR / Privacy

- Optional pseudonymization  
- Tenants can disable cloud  
- Provide data purge UI  

## 19. Deliverables Required from Developers

- DB migrations  
- Push job implementation  
- Worker sample code  
- Callback endpoint  
- Job monitoring UI  

## 20. Design Constraints

- Opt-in  
- No hard dependency  
- IOMAD-aware  
- Auditable  
- Reversible  

