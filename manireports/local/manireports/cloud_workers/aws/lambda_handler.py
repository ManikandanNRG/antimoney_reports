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
            
            print(f"Processing Job ID: {job_id}, Type: {job_type}, Recipients: {len(recipients)}")
            
            # Process emails
            results = process_emails(recipients, job_type)
            
            # Send callback to Moodle
            send_callback(job_id, results)
            
            # Delete message from SQS (Lambda does this automatically if no error raised, 
            # but explicit deletion is safer if we want partial success)
            # sqs_client.delete_message(...) 
            
        except Exception as e:
            print(f"Error processing record: {str(e)}")
            # If we raise exception, Lambda will retry the whole batch. 
            # For partial failures, we should handle them internally and not raise.
            
    return {
        'statusCode': 200,
        'body': json.dumps('Job processed successfully')
    }

def process_emails(recipients, job_type):
    sent_count = 0
    failed_count = 0
    errors = []
    
    # SES_SENDER_EMAIL is required for production. 
    # If not set, it defaults to a dummy, which will likely fail in SES unless verified.
    sender_email = os.environ.get('SES_SENDER_EMAIL')
    if not sender_email:
        print("WARNING: SES_SENDER_EMAIL env var not set. Using default 'noreply@example.com'")
        sender_email = 'noreply@example.com'
    
    for recipient in recipients:
        try:
            email_address = recipient['email']
            recipient_data = json.loads(recipient['recipient_data'])
            
            # Compose email based on job type
            subject, body = compose_email(job_type, recipient_data)
            
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
            
            print(f"Email sent to {email_address}: {response['MessageId']}")
            sent_count += 1
            
        except ClientError as e:
            error_msg = e.response['Error']['Message']
            print(f"Failed to send to {recipient.get('email')}: {error_msg}")
            failed_count += 1
            errors.append(f"{recipient.get('email')}: {error_msg}")
        except Exception as e:
            print(f"General error for {recipient.get('email')}: {str(e)}")
            failed_count += 1
            errors.append(f"{recipient.get('email')}: {str(e)}")
            
    return {
        'status': 'completed' if failed_count == 0 else 'partial_failure',
        'emails_sent': sent_count,
        'emails_failed': failed_count,
        'errors': errors
    }

def compose_email(job_type, data):
    """
    Composes email subject and body based on job type and data.
    """
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
    Sends a callback to Moodle with the results using urllib (standard lib).
    """
    moodle_url = os.environ.get('MOODLE_CALLBACK_URL')
    moodle_token = os.environ.get('MOODLE_TOKEN')
    
    if not moodle_url:
        print("Skipping callback: MOODLE_CALLBACK_URL not set")
        return

    payload = {
        'job_id': job_id,
        'status': results['status'],
        'emails_sent': results['emails_sent'],
        'emails_failed': results['emails_failed'],
        'errors': results['errors']
    }
    
    try:
        data = json.dumps(payload).encode('utf-8')
        req = urllib.request.Request(moodle_url, data=data, headers={
            'Content-Type': 'application/json',
            'Authorization': f'Bearer {moodle_token}'
        })
        
        with urllib.request.urlopen(req) as response:
            print(f"Callback sent: {response.getcode()}")
            
    except Exception as e:
        print(f"Failed to send callback: {str(e)}")
