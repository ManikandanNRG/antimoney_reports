# Phase 7: Reengagement Email Offload Strategy

## Executive Summary
This document outlines strategies for offloading `mod_reengagement` emails to AWS SES via the `local_manireports` Cloud system. It analyzes four approaches and recommends **Option D Enhanced**, which combines event-driven interception with existing batch processing infrastructure.

## The Goal
Offload high-volume reengagement reminder emails to AWS SES to improve deliverability and reduce server load, **without** breaking upgradability or introducing maintenance debt.

---

## Option A: Core Modification (The "Hacker" Approach)
Directly modify the third-party plugin code to intercept emails.

### Workflow
1. **Cron Trigger**: Standard `mod_reengagement` cron runs.
2. **Logic Processing**: Plugin calculates which users need reminders.
3. **Interception Point**: Inside `lib.php`, function `reengagement_send_notification()`:
   - **Modified Code**: We inject an `if` block.
   - *If Cloud Enabled*: Call `CloudJobManager` → AWS SES.
   - *If Cloud Disabled*: Proceed with standard Moodle email.

### Analysis
*   **Pros**: Simplest to implement (10 lines of code).
*   **Cons**:
    *   **Not Update Safe**: Any plugin update will **overwrite** our changes, breaking the offload.
    *   **Maintenance Nightmare**: Requires manual patching after every update.
*   **Verdict**: ❌ **Rejected** (Too fragile for production).

---

## Option B: The "Shadow Task" (The "Replica" Approach)
Disable the original task and run a custom copy.

### Workflow
1. **Disable Original**: Admin disables `\mod_reengagement\task\cron_task`.
2. **Enable Shadow**: Admin enables `\local_manireports\task\reengagement_offload_task`.
3. **Replicated Logic**: Our task runs:
   - Queries DB for eligible users (Replicating plugin logic).
   - Checks enrolment, activity completion, and timing.
4. **Routing**:
   - *If Cloud Enabled*: Send via AWS SES.
   - *If Cloud Disabled*: Call original `reengagement_send_notification`.

### Analysis
*   **Pros**: No core code modification.
*   **Cons**:
    *   **High Risk**: We must perfectly replicate complex logic (timing, expiry, enrolment).
    *   **Fragile**: If the plugin logic changes in a new version, our "Shadow" becomes outdated/buggy.
    *   **Liability**: We own any bugs related to missed or duplicate reminders.
*   **Verdict**: ❌ **Rejected** (Too high maintenance).

---

## Option C: Message Output Processor (The "Standard Plugin" Approach)
Create a dedicated Moodle plugin to handle messages.

### Workflow
1. **Cron Trigger**: Standard `mod_reengagement` cron runs.
2. **Message Generation**: Plugin calls `message_send()`.
3. **Moodle Routing**: Moodle sees we have a custom processor enabled for "Reengagement".
4. **Interception**: Moodle passes the message to our new plugin `message_manireports`.
5. **Routing**:
   - *If Cloud Enabled*: Send via AWS SES → Return `true` (Handled).
   - *If Cloud Disabled*: Return `false` (Let Moodle use Email).

### Analysis
*   **Pros**: Architecturally pure (The "Moodle Way"). 100% safe from updates.
*   **Cons**:
    *   **New Plugin Required**: Requires creating `message/output/manireports`.
    *   **Violation**: Violates the "No new plugins" constraint.
*   **Verdict**: ⚠️ **Valid, but rejected due to constraints.**

---

## Option D Enhanced: Event Listener + Batch Processor 🎯 ⭐
**The Recommended Solution**

Intercept messages via events and leverage existing batch processing infrastructure.

### Workflow
1. **Configuration**: Admin disables "Email" output for Reengagement (One-time setup).
2. **Cron Trigger**: Standard `mod_reengagement` cron runs.
3. **Message Generation**: Plugin calls `message_send()`.
4. **Event Fired**: Moodle fires `\core\event\message_sent`.
5. **Listener**: `local_manireports` catches this event.
6. **Batch Processing**: Event handler calls existing `reengagement_observer::send_emails_via_cloud_or_local()`.
7. **Smart Routing**:
   - Checks `cloud_job_manager::should_use_cloud_offload()`.
   - *If Cloud Enabled*: Creates cloud job → AWS SES.
   - *If Cloud Disabled*: Sends via local mail.
   - *If Cloud Fails*: **Automatic fallback** to local mail.

### Key Features

#### ✅ **Automatic Fallback**
Built-in error handling ensures emails are always delivered:
```php
try {
    $result = cloud_job_manager::submit_to_cloud($job_id);
    if (!$result) {
        // Fallback to local
        return self::send_emails_local(...);
    }
} catch (\Exception $e) {
    // Fallback on exception
    return self::send_emails_local(...);
}
```

#### ✅ **Batch Processing**
Handles multiple recipients efficiently in a single job, reducing overhead.

#### ✅ **Campaign Tracking**
Supports campaign types (`reminder_7days`, `reminder_14days`) for analytics.

#### ✅ **Company-Aware**
Respects IOMAD multi-tenant configuration per company.

### Implementation Steps

#### 1. Register Event
**File**: `local/manireports/db/events.php`
```php
$observers = array(
    // ... existing observers ...
    array(
        'eventname'   => '\core\event\message_sent',
        'callback'    => '\local_manireports\api\EmailOffloadHandler::handle_reengagement_message',
    ),
);
```

#### 2. Implement Event Handler
**File**: `local/manireports/classes/api/EmailOffloadHandler.php`
```php
public static function handle_reengagement_message(\core\event\message_sent $event) {
    global $DB;

    // 1. Filter: Only handle Reengagement notifications
    if ($event->component !== 'mod_reengagement') {
        return;
    }

    // 2. Extract message data
    $data = $event->get_data();
    $userid = $data['useridto'];
    $subject = $data['other']['subject'] ?? 'Reminder';
    $messagetext = $data['other']['smallmessage'] ?? '';
    $messagehtml = $data['other']['fullmessagehtml'] ?? '';

    // 3. Get user & company
    $user = $DB->get_record('user', ['id' => $userid]);
    if (!$user) return;

    $company_id = self::get_user_company($userid);

    // 4. Prepare recipient array
    $recipients = [
        (object)[
            'id' => $user->id,
            'email' => $user->email,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
        ]
    ];

    // 5. Call existing batch processor
    // This handles Cloud/Local routing + automatic fallback
    \local_manireports\observers\reengagement_observer::send_emails_via_cloud_or_local(
        $recipients,
        $subject,
        $messagetext,
        $messagehtml,
        'reengagement_reminder',
        $company_id
    );
}
```

#### 3. Admin Configuration (One Time)
1. Go to **Site administration > Plugins > Message outputs > Default message outputs**.
2. Find **Reengagement**.
3. **Uncheck** "Email" for both "Online" and "Offline".
4. Save changes.

### Analysis

**Pros**:
- ✅ **Zero Plugin Modifications**: Uses standard event system.
- ✅ **Update Safe**: Reengagement plugin can be updated freely.
- ✅ **Reuses Existing Code**: Leverages `reengagement_observer.php` (already developed).
- ✅ **Robust Fallback**: Automatic degradation to local mail on cloud failure.
- ✅ **Batch Efficient**: Processes multiple recipients per job.
- ✅ **Company-Aware**: Respects IOMAD multi-tenant settings.

**Cons**:
- Requires one-time Admin configuration.

**Verdict**: ✅ **Recommended** (Best balance of safety, efficiency, and maintainability).

---

## Comparison Matrix

| Feature | Option A (Core Mod) | Option B (Shadow Task) | Option C (New Plugin) | **Option D Enhanced** |
| :--- | :---: | :---: | :---: | :---: |
| **Update Safe** | ❌ No | ⚠️ Partial | ✅ Yes | ✅ **Yes** |
| **Maintenance** | 🔴 High | 🔴 High | 🟢 Low | 🟢 **Low** |
| **New Plugin?** | No | No | Yes (`message/output`) | **No** (`local`) |
| **Logic Risk** | Low | High (Replication) | Low | **Low** |
| **Automatic Fallback** | Manual | Manual | Manual | ✅ **Built-in** |
| **Batch Processing** | No | Possible | No | ✅ **Yes** |
| **Reuses Existing Code** | No | No | No | ✅ **Yes** |
| **Architecture** | Hack | Workaround | Standard | **Event-Driven** |

## Final Recommendation

**Option D Enhanced** is the optimal solution because it:

1. ✅ **Respects the "No New Plugin" constraint** (lives in `local_manireports`).
2. ✅ **Avoids the "Update Hell"** of Option A (no core modifications).
3. ✅ **Avoids the "Logic Replication"** risks of Option B (uses plugin's own logic).
4. ✅ **Leverages existing infrastructure** (`reengagement_observer.php` already developed).
5. ✅ **Provides robust fallback** (automatic degradation to local mail).
6. ✅ **Handles batch processing** (efficient for high-volume scenarios).

### Next Steps
1. ✅ Approve Option D Enhanced.
2. Register `message_sent` event in `local/manireports/db/events.php`.
3. Implement `EmailOffloadHandler::handle_reengagement_message()`.
4. Configure Admin settings to disable standard Email for Reengagement.
5. Test with a small batch (10 users).
6. Monitor cloud job dashboard for delivery status.
