# Phase 7: Reengagement Email Offload Strategy

## Executive Summary
This document outlines strategies for offloading `mod_reengagement` emails to AWS SES via the `local_manireports` Cloud system. It analyzes five approaches, recommending **Option D Enhanced** for standard use cases and **Option F** for strict "No Core Hack" requirements with CC support.

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
*   **Pros**: Simplest to implement (10 lines of code). Captures ALL emails (Student + CCs).
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

## Option D Enhanced: Event Listener + Batch Processor 🎯
**The Recommended Solution (for Student Emails)**

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

### Analysis
*   **Pros**:
    *   **Zero Plugin Modifications**: Uses standard event system.
    *   **Update Safe**: Reengagement plugin can be updated freely.
    *   **Reuses Existing Code**: Leverages `reengagement_observer.php`.
    *   **Robust Fallback**: Automatic degradation to local mail on cloud failure.
*   **Cons**:
    *   **CC Email Limitation**: Does NOT capture CC emails sent via `email_to_user()` (they go via local SMTP).
*   **Verdict**: ✅ **Recommended** (Best balance of safety and efficiency, if CC offload is not critical).

---

## Option F: Atomic Pre-Claim (The "Database" Fix) 🛡️
**The "True Cloud Offload" Solution (Captures CCs)**

Intercept the *data* in the database *before* `mod_reengagement` even sees it.

### Workflow
1. **Our Task Runs First** (every 4 minutes):
   - Queries `mdl_reengagement_inprogress` table for due emails.
   - **Atomic Update**: `UPDATE reengagement_inprogress SET emailsent = emailsent + 1 WHERE id = ? AND emailsent = ?`
   - **The Lock**: If update returns 0 rows, someone else grabbed it. Skip.
   - **The Claim**: If update returns 1 row, we have exclusive rights.
2. **Processing**:
   - We generate the email (Student + CCs) ourselves.
   - We send via Cloud.
3. **Reengagement Cron Runs After** (every 5 minutes):
   - Sees `emailsent` count already incremented.
   - Skips sending.

### Analysis
*   **Pros**:
    *   **Captures EVERYTHING**: Student + CC emails (since we generate them).
    *   **True Async**: Queues to AWS before Moodle tries to send.
    *   **No Admin Config**: No need to disable email processor.
    *   **No Core Hacks**: Respects the "No Core Hacks" constraint.
*   **Cons**:
    *   **Code Complexity**: We must replicate the template logic (fetching user, course, replacing placeholders).
*   **Verdict**: ✅ **Recommended** (If CC offload is critical and "No Core Hacks" is mandatory).

---

## Comparison Matrix

| Feature | Option A (Core Mod) | Option B (Shadow Task) | Option C (New Plugin) | Option D Enhanced | **Option F (Atomic)** |
| :--- | :---: | :---: | :---: | :---: | :---: |
| **Update Safe** | ❌ No | ⚠️ Partial | ✅ Yes | ✅ **Yes** | ✅ **Yes** |
| **Maintenance** | 🔴 High | 🔴 High | 🟢 Low | 🟢 **Low** | 🟡 Medium |
| **New Plugin?** | No | No | Yes (`message/output`) | **No** (`local`) | **No** (`local`) |
| **Captures CCs?** | ✅ Yes | ✅ Yes | ❌ No | ❌ No | ✅ **Yes** |
| **Logic Risk** | Low | High | Low | **Low** | Medium |
| **Architecture** | Hack | Workaround | Standard | **Event-Driven** | **DB Locking** |

## Final Recommendation

1.  **If Student Emails are 99% of volume and CCs can go local**:
    *   Choose **Option D Enhanced**.
    *   It is simpler, safer, and requires less code.

2.  **If CC Emails MUST go to Cloud and "No Core Hacks" is strict**:
    *   Choose **Option F (Atomic Pre-Claim)**.
    *   It is the *only* robust way to capture CCs without hacking core.

### Next Steps
1.  Decide between Option D (Simple) and Option F (Complete).
2.  If Option D: Register event listener.
3.  If Option F: Implement `reengagement_interceptor` task with atomic locking.
