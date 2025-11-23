# Phase 4: Cloud Offload - Configuration & Testing Guide

This document outlines the steps to configure and verify the "Selective Cloud Offload" features implemented in Phase 4.

## 1. Prerequisites

Before testing, ensure the database schema has been updated to include the new cloud offload tables.

1.  **Run Database Upgrade**:
    Open your terminal and run the Moodle upgrade script:
    ```bash
    php admin/cli/upgrade.php
    ```
    *Verify*: Check that tables `mdl_manireports_cloud_recip`, `mdl_manireports_cloud_conf`, and `mdl_manireports_cloud_jobs` exist in your database.

2.  **Purge Caches (Critical)**:
    Since we added new event observers (`db/events.php`), you **MUST** purge Moodle caches for them to be registered.
    ```bash
    php admin/cli/purge_caches.php
    ```

## 2. Configuration

You need to enable cloud offload for a specific company and provide the necessary credentials.

1.  **Access the Dashboard**:
    Navigate to the ManiReports Dashboard V6 (`/local/manireports/designs/dashboard_v6_ultimate.php`).

2.  **Go to "Email Offload" Tab**:
    Click on the **"Email Offload"** tab in the dashboard navigation.

3.  **Select a Company**:
    Use the "Select Company" dropdown to choose the company you want to configure (e.g., "Test Company").

4.  **Enter Provider Settings**:
    *   **Enable Cloud Offload**: Check the box.
    *   **Provider**: Select **AWS (SQS + SES)** (or Cloudflare if applicable).
    *   **AWS Credentials**:
        *   **Access Key**: Your AWS Access Key ID.
        *   **Secret Key**: Your AWS Secret Access Key.
        *   **Region**: e.g., `us-east-1`.
        *   **SQS Queue URL**: The URL of your SQS queue (e.g., `https://sqs.us-east-1.amazonaws.com/123456789012/my-queue`).
        *   **SES Sender Email**: A verified email address in AWS SES (e.g., `noreply@yourdomain.com`).

5.  **Save Configuration**:
    Click **"Save Configuration"**.
    *Verify*: The page should reload, and your settings should remain populated.

## 3. Testing Scenario A: License Allocation (IOMAD)

This tests if the system intercepts the IOMAD license allocation email and creates a cloud job instead.

1.  **Navigate to IOMAD Licenses**:
    Go to **Site Administration > IOMAD Dashboard > Company > Licenses**.

2.  **Allocate a License**:
    *   Select the company you configured in Step 2.
    *   Assign a license to a user (preferably a test user).

3.  **Verify Results**:
    *   **Check Database (Cloud Job)**:
        Run this SQL query:
        ```sql
        SELECT * FROM mdl_manireports_cloud_jobs ORDER BY id DESC LIMIT 1;
        ```
        *Expected*: You should see a new record with `type = 'license_allocation'` and `status = 'pending'` (or `queued`).
    *   **Check Database (Recipients)**:
        ```sql
        SELECT * FROM mdl_manireports_cloud_recip WHERE job_id = [JOB_ID_FROM_ABOVE];
        ```
        *Expected*: One record containing the user's email.
    *   **Check Database (Suppression)**:
        ```sql
        SELECT * FROM mdl_email WHERE userid = [USER_ID];
        ```
        *Expected*: **No record** should exist for this specific email event (or it should be marked as sent if your cron ran, but the key is that *Moodle* didn't send it directly if offload is working). *Note: The suppression logic deletes the email from the `mdl_email` table immediately.*

## 4. Testing Scenario B: CSV User Upload

This tests if the system intercepts the "New User" email during bulk uploads.

1.  **Prepare CSV**:
    Create a simple CSV file (`users.csv`) with 2-3 test users:
    ```csv
    username,firstname,lastname,email,password,company
    testuser1,Test,User1,test1@example.com,Password123!,[YOUR_COMPANY_SHORTNAME]
    testuser2,Test,User2,test2@example.com,Password123!,[YOUR_COMPANY_SHORTNAME]
    ```

2.  **Upload Users**:
    *   Go to **Site Administration > Users > Accounts > Upload users**.
    *   Upload your CSV file.
    *   **Settings**:
        *   **Institution**: Select your configured company.
        *   **New password**: "Field required in file" (or generate if testing that flow).
        *   **Prevent email address duplicates**: Yes.

3.  **Verify Results**:
    *   **Check Database (Cloud Job)**:
        ```sql
        SELECT * FROM mdl_manireports_cloud_jobs WHERE type = 'user_created' ORDER BY id DESC LIMIT 1;
        ```
        *Expected*: A new job record.
    *   **Check Database (Recipients)**:
        ```sql
        SELECT * FROM mdl_manireports_cloud_recip WHERE job_id = [JOB_ID];
        ```
        *Expected*: Records for `test1@example.com` and `test2@example.com`.
        *   **Crucial**: Check the `recipient_data` column. It should contain a JSON object with the `password` (if it was a temp password flow) or relevant user details.

## 5. Testing Scenario C: Cloud Worker Processing (Optional/Advanced)

If you have deployed the Python Lambda script:

1.  **Trigger the Worker**:
    Manually invoke your Lambda function (or wait for the SQS trigger).

2.  **Verify Email Receipt**:
    Check the inbox of the test users (`test1@example.com`, etc.).
    *Expected*: They should receive the welcome email formatted by your cloud template.

3.  **Verify Job Completion**:
    *   Check `mdl_manireports_cloud_jobs` again.
    *   *Expected*: `status` should change to `completed` and `emails_sent` should match the number of users.

## Troubleshooting

*   **Job not created?**
    *   Check if the company ID in `mdl_manireports_cloud_conf` matches the company you are testing with.
    *   Ensure `enabled = 1` in the settings.
*   **Emails still sent by Moodle?**
    *   Verify the `EmailOffloadHandler` is correctly hooked into the events.
    *   Check Moodle logs for any errors during the event trigger.

## 6. AWS Configuration Guide (Detailed)

This section details the steps to configure the required AWS services (SQS, SES, Lambda, IAM) to work with the ManiReports Cloud Offload system.

### 6.1. IAM Configuration (Permissions)

You need two IAM roles/users: one for Moodle to access SQS/SES, and one for the Lambda function to execute.

#### A. Create Moodle IAM User
1.  Go to **IAM > Users > Create user**.
2.  Name: `moodle-cloud-offload-user`.
3.  **Permissions**: Attach policies directly:
    *   `AmazonSQSFullAccess` (or restrict to your specific queue).
    *   `AmazonSESFullAccess` (or restrict to sending emails).
4.  **Create Access Keys**:
    *   Go to the user's **Security credentials** tab.
    *   Click **Create access key**.
    *   **Save these keys!** You will need the **Access Key ID** and **Secret Access Key** for the Moodle Dashboard configuration.

#### B. Create Lambda Execution Role
1.  Go to **IAM > Roles > Create role**.
2.  Trusted entity type: **AWS Service**.
3.  Service or use case: **Lambda**.
4.  **Permissions**: Attach:
    *   `AWSLambdaBasicExecutionRole` (for logging to CloudWatch).
    *   `AmazonSQSFullAccess` (to read messages from the queue).
    *   `AmazonSESFullAccess` (to send emails).
5.  Name: `moodle-lambda-offload-role`.

### 6.2. SQS Configuration (Queue)

1.  Go to **Simple Queue Service (SQS)**.
2.  Click **Create queue**.
3.  **Type**: **Standard** (FIFO is not required for this implementation and has lower throughput limits).
4.  **Name**: `manireports-email-queue` (or similar).
5.  **Configuration**:
    *   **Visibility timeout**: Set to **1 minute** (or longer than your Lambda execution time).
    *   Keep other defaults.
6.  Click **Create queue**.
7.  **Copy URL**: Copy the **URL** (e.g., `https://sqs.us-east-1.amazonaws.com/...`) - you need this for Moodle.

### 6.3. SES Configuration (Email Service)

1.  Go to **Amazon Simple Email Service (SES)**.
2.  **Identities**: Click **Create identity**.
3.  **Identity type**:
    *   **Domain**: Recommended for production (verifies `yourdomain.com`).
    *   **Email address**: Easier for testing (verifies `noreply@yourdomain.com`).
4.  Follow the verification steps (DNS records for domain, or clicking a link for email).
5.  **Sandbox Mode**: If your account is new, you are in the SES Sandbox. You can ONLY send emails to **verified addresses**.
    *   *For testing*: Verify the recipient email addresses (e.g., `test1@example.com`) in SES as well.
    *   *For production*: Request production access to send to any email.

### 6.4. Lambda Configuration (The Worker)

1.  Go to **Lambda > Create function**.
2.  **Author from scratch**.
3.  **Function name**: `manireports-email-worker`.
4.  **Runtime**: **Python 3.9** (or newer).
5.  **Permissions**:
    *   Change default execution role > **Use an existing role**.
    *   Select `moodle-lambda-offload-role` (created in step 6.1.B).
6.  Click **Create function**.

#### Deploy Code
1.  Copy the content of `D:\antigravity\manireports\local\manireports\cloud_workers\aws\lambda_handler.py`.
2.  Paste it into the **Code source** editor in the Lambda console (replace existing code).
3.  Click **Deploy**.

#### Add Trigger (SQS)
1.  Click **+ Add trigger**.
2.  Select **SQS**.
3.  **SQS queue**: Select `manireports-email-queue`.
4.  **Batch size**: 10 (default is fine).
5.  Click **Add**.

#### Environment Variables (Configuration)
In the Lambda function, go to **Configuration > Environment variables** and add the following:

*   **Required**:
    *   `SES_SENDER_EMAIL`: The email address you verified in SES (e.g., `noreply@yourdomain.com`). If you skip this, the script defaults to `noreply@example.com` which will likely fail.

*   **Optional (For Phase 5 / Future Use)**:
    *   `MOODLE_CALLBACK_URL`: The URL to your Moodle callback script (e.g., `https://yoursite.com/local/manireports/cloud_callback.php`).
    *   `MOODLE_TOKEN`: A secure token to authenticate the callback.

**Note**: For Phase 4 testing, you only strictly need `SES_SENDER_EMAIL`. You can leave the Moodle callback variables empty for now; the script will just skip the callback step.

### 6.5. Final Connection

1.  Go back to the **Moodle Dashboard > Email Offload Tab**.
2.  Enter the **Access Key** and **Secret Key** from Step 6.1.A.
3.  Enter the **SQS Queue URL** from Step 6.2.
4.  Enter the **SES Sender Email** from Step 6.3.
5.  Save and test!
