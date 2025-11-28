# Phase 7: Option D Technical Limitation
## Why "Event Listener" Misses CC Emails

## Executive Summary
**Option D (Event Listener)** successfully offloads student emails to the Cloud but **fails to capture CC emails** (HR/Admin notifications). These CC emails continue to be sent via the local Moodle SMTP server.

This document explains the technical root cause found in the `mod_reengagement` source code.

---

## The Root Cause: Two Different Sending Methods

The `mod_reengagement` plugin uses **two completely different PHP functions** to send emails, depending on the recipient type.

### 1. Student Emails (Captured ✅)
For the main student reminder, the plugin uses:
```php
// lib.php
message_send($eventdata);
```
*   **What it does**: Pushes the message into Moodle's "Message Processor" pipeline.
*   **Result**: Fires the `\core\event\message_sent` event.
*   **Option D**: Listens for this event -> **CAPTURES IT**.

### 2. CC Emails (Missed ❌)
For "Third Party" or "Company CC" emails, the plugin uses:
```php
// lib.php (inside reengagement_send_notification)
email_to_user($user, $from, $subject, $messagetext, $messagehtml);
```
*   **What it does**: Connects **directly** to the SMTP server to send the email immediately.
*   **Result**: Bypasses the Message Processor pipeline entirely. **Does NOT fire** the `message_sent` event.
*   **Option D**: Never hears about this email -> **MISSES IT**.

---

## Visual Flow Comparison

### Path A: Student Email (Option D Works)
```
Reengagement Cron
       ↓
message_send()  <-- Moodle API
       ↓
[Moodle Message Processor]
       ↓
Event Fired: \core\event\message_sent  <-- WE CATCH THIS!
       ↓
Option D Listener
       ↓
Cloud Offload (AWS SES) ✅
```

### Path B: CC Email (Option D Fails)
```
Reengagement Cron
       ↓
email_to_user()  <-- Direct SMTP Call
       ↓
[Direct SMTP Connection]
       ↓
Sent via Local Mail Server ❌
       ↓
(No Event Fired)
       ↓
Option D is never notified.
```

---

## Why We Can't Fix This in Option D

To capture `email_to_user()` calls, we would need to:
1.  **Hack Core**: Modify `lib/moodlelib.php` to intercept every email (Violates "No Core Hacks").
2.  **Hack Plugin**: Modify `mod/reengagement/lib.php` to change `email_to_user` to `message_send` (Violates "No Plugin Hacks").

Since Option D is strictly defined as "No Code Modifications", it is **technically impossible** for it to see these CC emails.

---

## The Solution: Option F

**Option F (Atomic Pre-Claim)** solves this by:
1.  Ignoring the plugin's sending logic entirely.
2.  Reading the raw data from the database.
3.  Constructing the email (Student + CCs) ourselves.
4.  Sending everything via Cloud.

This is why **Option F** is the only "No Hack" solution that supports CC offload.
