# Cloud Offload Module - Quick Summary

## What It Does

Offloads two heavy operations from your Moodle server to external cloud infrastructure:
1. **Bulk Email Sending** (50,000 emails) - Course reminders, notifications
2. **Certificate Generation** - PDF certificates for completions

## Why You Need It

- **Performance**: Avoid overloading Moodle server with heavy operations
- **Scalability**: Handle 50,000 emails without impacting site performance
- **Reliability**: Retry failed emails automatically with DLQ
- **Cost-Effective**: ~$12-15/month on AWS or ~$5-20/month on Cloudflare

## When to Implement

**Phase 4** - After completing:
- ✅ All MVP features (dashboards, reports, scheduling)
- ✅ Privacy API implementation
- ✅ Data cleanup task
- ✅ Full testing of core features

## Implementation Time

**30-41 hours** broken down as:
- Database schema: 2 hours
- Cloud job manager: 6-8 hours
- Cloud connector: 4-6 hours
- Callback handler: 3-4 hours
- Certificate generator: 4-6 hours
- Settings UI: 2-3 hours
- Job monitoring UI: 4-5 hours
- Email integration: 3-4 hours
- Cloud worker: 8-12 hours
- Testing & docs: 3-4 hours

## Cloud Provider Options

### AWS (Recommended)
- **Services**: Lambda + SQS + SES + S3
- **Cost**: ~$12-15/month for 50k emails/day
- **Pros**: Mature, reliable, well-documented
- **Cons**: Slightly more expensive

### Cloudflare (Cost-Effective)
- **Services**: Workers + Queue + Email + R2
- **Cost**: ~$5-20/month for 50k emails/day
- **Pros**: Cheaper, simpler setup
- **Cons**: Newer platform, less mature

## Key Features

1. **Job Batching**: Split 50k recipients into 200-recipient chunks
2. **Status Tracking**: Real-time progress updates via callbacks
3. **Retry Logic**: Automatic retry with exponential backoff
4. **Fallback**: Local processing if cloud unavailable
5. **Monitoring UI**: Dashboard to view job status and retry failures
6. **Security**: HMAC signature validation on callbacks
7. **IOMAD Support**: Company isolation in cloud jobs
8. **Audit Logging**: All operations logged for compliance

## Files to Create

**PHP Classes** (7 files):
- `classes/api/cloud_job_manager.php`
- `classes/api/cloud_connector.php`
- `classes/api/aws_connector.php`
- `classes/api/cloudflare_connector.php`
- `classes/api/certificate_generator.php`

**UI Pages** (3 files):
- `ui/cloud_jobs.php`
- `ui/cloud_job_view.php`
- `ui/ajax/cloud_callback.php`

**Templates** (3 files):
- `templates/cloud_jobs_list.mustache`
- `templates/cloud_job_detail.mustache`
- `templates/cloud_job_status_widget.mustache`

**JavaScript** (1 file):
- `amd/src/cloud_jobs.js`

**Cloud Workers** (2 implementations):
- `cloud_workers/aws/lambda_handler.py`
- `cloud_workers/cloudflare/worker.js`

**Database Tables** (2 tables):
- `manireports_cloud_jobs`
- `manireports_cloud_job_recipients`

## Configuration Settings

Add to `settings.php`:
- Cloud offload enabled (yes/no)
- Cloud mode (API Gateway or SQS)
- Cloud endpoint URL
- Authentication token
- AWS region
- SQS queue URL
- Job batch size (default: 200)
- Email provider (SES, SendGrid, Mailgun)
- Callback secret
- Certificate generation mode

## Testing Checklist

- [ ] Create test job with 5 recipients
- [ ] Verify job appears in monitoring UI
- [ ] Check SQS queue receives message
- [ ] Verify Lambda/Worker processes job
- [ ] Confirm emails sent via SES/SendGrid
- [ ] Verify callback updates Moodle status
- [ ] Test certificate generation and S3 upload
- [ ] Test retry for failed recipients
- [ ] Test fallback to local processing
- [ ] Verify IOMAD company filtering

## Deployment Steps

1. **Update Moodle plugin** with cloud offload code
2. **Run database upgrade** to create new tables
3. **Deploy cloud infrastructure** (CloudFormation or Wrangler)
4. **Configure plugin settings** in Moodle admin
5. **Test connection** using test button
6. **Create test job** with small recipient list
7. **Monitor logs** and verify success
8. **Scale up** to production volumes

## Success Metrics

- ✅ 50,000 emails sent in < 30 minutes
- ✅ Bounce rate < 2%
- ✅ Callback success rate > 99%
- ✅ Zero Moodle performance impact
- ✅ Cost within budget ($15/month)
- ✅ Admin can monitor and retry easily

## Documentation

Full implementation details in:
- `CLOUD_OFFLOAD_IMPLEMENTATION_PLAN.md` - Complete step-by-step guide
- `docs/CLOUD_OFFLOAD_SETUP.md` - Setup instructions (to be created)
- `docs/CLOUD_OFFLOAD_AWS.md` - AWS-specific guide (to be created)
- `docs/CLOUD_OFFLOAD_CLOUDFLARE.md` - Cloudflare guide (to be created)

## Current Status

**Phase**: Phase 4 (Future Enhancement)
**Priority**: After MVP completion
**Dependencies**: MVP, Privacy API, Data Cleanup
**Decision**: Implement after current tasks complete

---

**Ready to implement when you complete current MVP tasks!**
