import json
import boto3
import os
import urllib.request
from botocore.exceptions import ClientError

# Initialize AWS clients
ses_client = boto3.client('ses')
sqs_client = boto3.client('sqs')

def lambda_handler(event, context):
    """
    AWS Lambda handler for processing email jobs from SQS.
    """
    print("Processing event:", json.dumps(event))
    
    for record in event['Records']:
        try:
            # Parse SQS message
            payload = json.loads(record['body'])
            job_id = payload.get('job_id')
            job_type = payload.get('type')
            recipients = payload.get('recipients', [])
            
            # Extract custom content from payload if available
            custom_subject = payload.get('custom_subject')
            custom_html = payload.get('custom_html')
            
            print(f"Processing Job ID: {job_id}, Type: {job_type}, Recipients: {len(recipients)}")
            
            # Process emails
            results = process_emails(recipients, job_type, custom_subject, custom_html)
            
            # Send callback to Moodle
            send_callback(job_id, results)
            
        except Exception as e:
            print(f"Error processing record: {str(e)}")
            
    return {
        'statusCode': 200,
        'body': json.dumps('Job processed successfully')
    }

def process_emails(recipients, job_type, custom_subject=None, custom_html=None):
    sent_count = 0
    failed_count = 0
    errors = []
    recipient_results = []
    
    sender_email = os.environ.get('SES_SENDER_EMAIL')
    if not sender_email:
        print("WARNING: SES_SENDER_EMAIL env var not set. Using default 'noreply@example.com'")
        sender_email = 'noreply@example.com'
    
    for recipient in recipients:
        email_address = recipient.get('email', 'unknown')
        result = {'email': email_address, 'status': 'pending'}
        
        try:
            # Parse recipient data
            recipient_data = json.loads(recipient['recipient_data'])
            
            # Validate email address format before sending
            if not email_address or '@' not in email_address:
                raise ValueError(f"Invalid email format: {email_address}")
            
            # Compose email
            subject, body = compose_email(job_type, recipient_data, custom_subject, custom_html)
            
            print(f"Attempting to send email to: {email_address}")
            
            # Send via SES
            response = ses_client.send_email(
                Source=sender_email,
                Destination={
                    'ToAddresses': [email_address]
                },
                Message={
                    'Subject': {
                        'Data': subject,
                        'Charset': 'UTF-8'
                    },
                    'Body': {
                        'Html': {
                            'Data': body,
                            'Charset': 'UTF-8'
                        }
                    }
                }
            )
            
            # Check SES response status
            http_status = response.get('ResponseMetadata', {}).get('HTTPStatusCode', 0)
            message_id = response.get('MessageId', 'unknown')
            
            if http_status == 200 and message_id:
                print(f"✅ Email sent successfully to {email_address}: MessageId={message_id}")
                sent_count += 1
                result['status'] = 'sent'
                result['message_id'] = message_id
            else:
                # SES returned non-200 status
                error_msg = f"SES returned status {http_status}"
                print(f"❌ SES error for {email_address}: {error_msg}")
                failed_count += 1
                errors.append(f"{email_address}: {error_msg}")
                result['status'] = 'failed'
                result['error'] = error_msg
            
        except ClientError as e:
            # AWS SES specific errors (bounces, invalid email, etc.)
            error_code = e.response['Error']['Code']
            error_msg = e.response['Error']['Message']
            
            # Categorize the error
            if error_code == 'MessageRejected':
                error_type = "Bounce: Email rejected by SES"
            elif error_code == 'MailFromDomainNotVerified':
                error_type = "Configuration: Sender domain not verified"
            elif error_code == 'ConfigurationSetDoesNotExist':
                error_type = "Configuration: Invalid configuration set"
            else:
                error_type = f"SES Error: {error_code}"
            
            full_error = f"{error_type} - {error_msg}"
            print(f"❌ ClientError for {email_address}: {full_error}")
            
            failed_count += 1
            errors.append(f"{email_address}: {full_error}")
            result['status'] = 'failed'
            result['error'] = full_error
            
        except ValueError as e:
            # Invalid email format or data parsing errors
            error_msg = str(e)
            print(f"❌ Validation error for {email_address}: {error_msg}")
            failed_count += 1
            errors.append(f"{email_address}: {error_msg}")
            result['status'] = 'failed'
            result['error'] = error_msg
            
        except Exception as e:
            # Catch-all for unexpected errors
            error_msg = f"Unexpected error: {str(e)}"
            print(f"❌ General error for {email_address}: {error_msg}")
            failed_count += 1
            errors.append(f"{email_address}: {error_msg}")
            result['status'] = 'failed'
            result['error'] = error_msg
            
        recipient_results.append(result)
            
    return {
        'status': 'completed' if failed_count == 0 else 'partial_failure',
        'emails_sent': sent_count,
        'emails_failed': failed_count,
        'errors': errors,
        'recipients': recipient_results
    }

def compose_email(job_type, data, custom_subject=None, custom_html=None):
    """
    Composes email subject and body.
    Prioritizes custom content if provided.
    """
    if custom_subject and custom_html:
        return custom_subject, custom_html

    # Fallback to hardcoded templates
    if job_type == 'user_created':
        subject = "Welcome to Our Platform"
        body = f"""
        <h1>Welcome, {data.get('firstname')}!</h1>
        <p>Your account has been created.</p>
        <p><strong>Username:</strong> {data.get('username')}</p>
        <p><strong>Password:</strong> {data.get('password')}</p>
        <p><a href="{data.get('loginurl')}">Login Here</a></p>
        """
    elif job_type == 'license_allocation':
        subject = "Course License Assigned"
        body = f"""
        <h1>Hello {data.get('firstname')}</h1>
        <p>You have been assigned a license for the course: <strong>{data.get('course_name')}</strong></p>
        <p>License: {data.get('license_name')}</p>
        """
    else:
        subject = "Notification"
        body = "<p>You have a new notification.</p>"
        
    return subject, body

def send_callback(job_id, results):
    """
    Sends a callback to Moodle with the results.
    """
    moodle_url = os.environ.get('MOODLE_CALLBACK_URL')
    moodle_token = os.environ.get('MOODLE_CALLBACK_TOKEN')
    
    if not moodle_url:
        print("Skipping callback: MOODLE_CALLBACK_URL not set")
        return

    payload = {
        'job_id': job_id,
        'status': results['status'],
        'token': moodle_token,
        'emails_sent': results['emails_sent'],
        'emails_failed': results['emails_failed'],
        'errors': results['errors'],
        'recipients': results['recipients']
    }
    
    try:
        data = json.dumps(payload).encode('utf-8')
        req = urllib.request.Request(moodle_url, data=data, headers={
            'Content-Type': 'application/json'
        })
        
        with urllib.request.urlopen(req) as response:
            print(f"Callback sent: {response.getcode()}")
            
    except Exception as e:
        print(f"Failed to send callback: {str(e)}")
