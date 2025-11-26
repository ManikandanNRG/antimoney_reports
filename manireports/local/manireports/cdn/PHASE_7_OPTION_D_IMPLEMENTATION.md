# Phase 7: Option D Implementation Plan
## Event Listener with Automatic Fallback

## Overview
Intercept reengagement emails via Moodle's event system and route them to AWS SES, with automatic fallback to local mail on failure.

---

## Architecture

### Flow Diagram
```
Reengagement Cron
    ↓
message_send()
    ↓
Event: \core\event\message_sent
    ↓
EmailOffloadHandler::handle_message_sent()
    ↓
Check Company Config
    ↓
    ├─→ Cloud Enabled?
    │       ↓
    │   Try: CloudJobManager::create_job()
    │       ↓
    │   Success? → AWS SES
    │       ↓
    │   Failure? → Fallback to email_to_user()
    │
    └─→ Cloud Disabled?
            ↓
        email_to_user() (Standard Moodle)
```

---

## Implementation Steps

### Step 1: Register Event Listener
**File**: `local/manireports/db/events.php`

Add the following observer:
```php
array(
    'eventname'   => '\core\event\message_sent',
    'callback'    => '\local_manireports\api\EmailOffloadHandler::handle_message_sent',
),
```

### Step 2: Implement Event Handler
**File**: `local/manireports/classes/api/EmailOffloadHandler.php`

Add this new method:

```php
/**
 * Handle reengagement message_sent events
 * 
 * @param \core\event\message_sent $event
 */
public static function handle_message_sent(\core\event\message_sent $event) {
    global $DB;

    // 1. Filter: Only handle Reengagement notifications
    $data = $event->get_data();
    if ($data['component'] !== 'mod_reengagement') {
        return; // Not a reengagement email, ignore
    }

    // 2. Extract message data
    $userid = $data['useridto'];
    $subject = $data['other']['subject'] ?? 'Course Reminder';
    $messagetext = $data['other']['smallmessage'] ?? '';
    $messagehtml = $data['other']['fullmessagehtml'] ?? $data['other']['fullmessage'] ?? '';

    // 3. Get user record
    $user = $DB->get_record('user', ['id' => $userid]);
    if (!$user) {
        error_log("CloudOffload: User $userid not found for reengagement email");
        return;
    }

    // 4. Get company ID
    $company_id = self::get_user_company($userid);

    // 5. Route based on company config
    if (self::is_offload_enabled($company_id)) {
        // --- CLOUD OFFLOAD PATH ---
        self::send_via_cloud_with_fallback($user, $company_id, $subject, $messagetext, $messagehtml);
    } else {
        // --- STANDARD PATH ---
        self::send_via_local($user, $subject, $messagetext, $messagehtml);
    }
}

/**
 * Send email via cloud with automatic fallback
 * 
 * @param object $user User object
 * @param int $company_id Company ID
 * @param string $subject Email subject
 * @param string $messagetext Plain text message
 * @param string $messagehtml HTML message
 */
private static function send_via_cloud_with_fallback($user, $company_id, $subject, $messagetext, $messagehtml) {
    try {
        // Attempt cloud offload
        $manager = new CloudJobManager();
        $recipient = [
            'email' => $user->email,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'username' => $user->username,
            'loginurl' => new \moodle_url('/login/index.php')
        ];

        $job_id = $manager->create_job('reengagement', [$recipient], $company_id, $subject, $messagehtml);
        
        if ($manager->submit_job($job_id)) {
            error_log("CloudOffload: Reengagement email queued for user {$user->id} (Job: $job_id)");
            return; // Success!
        } else {
            error_log("CloudOffload: Cloud submission failed for user {$user->id}. Falling back to local.");
            // Fall through to fallback
        }
    } catch (\Exception $e) {
        error_log("CloudOffload: Exception during cloud offload: " . $e->getMessage() . ". Falling back to local.");
        // Fall through to fallback
    }

    // FALLBACK: Send via local mail
    self::send_via_local($user, $subject, $messagetext, $messagehtml);
}

/**
 * Send email via local Moodle mail
 * 
 * @param object $user User object
 * @param string $subject Email subject
 * @param string $messagetext Plain text message
 * @param string $messagehtml HTML message
 */
private static function send_via_local($user, $subject, $messagetext, $messagehtml) {
    $from = core_user::get_support_user();
    $result = email_to_user($user, $from, $subject, $messagetext, $messagehtml);
    
    if ($result) {
        error_log("CloudOffload: Reengagement email sent locally to {$user->email}");
    } else {
        error_log("CloudOffload: CRITICAL - Failed to send reengagement email to {$user->email}");
    }
}
```

### Step 3: Admin Configuration
**One-time setup** in Moodle Admin:

1. Navigate to: **Site administration → Plugins → Message outputs → Default message outputs**
2. Find the row for **"Reengagement"**
3. **Uncheck** "Email" for both "Online" and "Offline" columns
4. Click **Save changes**

**Why?** This prevents Moodle from sending the email via standard SMTP, allowing our event handler to take over.

---

## Key Features

### ✅ Automatic Fallback
If cloud submission fails (network issue, AWS error, etc.), the system automatically sends via local mail. **Zero email loss.**

### ✅ Company-Aware Routing
- **Cloud Enabled Companies**: Emails go to AWS SES
- **Standard Companies**: Emails go via local Moodle mail
- **Mixed Environment Support**: Both can coexist

### ✅ No Plugin Modification
- Uses Moodle's standard event system
- `mod_reengagement` plugin remains untouched
- **Update safe**: Plugin can be upgraded freely

### ✅ Robust Error Handling
- Try/catch blocks prevent crashes
- Detailed error logging for debugging
- Graceful degradation on any failure

---

## Testing Plan

### Test 1: Cloud Offload (Enabled Company)
1. Create a test user in a company with Cloud Offload enabled
2. Enroll user in a course with a reengagement activity (5-minute duration)
3. Wait for cron to run
4. **Expected**:
   - Cloud job created in `mdl_manireports_cloud_jobs`
   - Email sent via AWS SES
   - No local email sent

### Test 2: Local Fallback (Disabled Company)
1. Create a test user in a company with Cloud Offload disabled
2. Enroll user in a course with a reengagement activity
3. Wait for cron to run
4. **Expected**:
   - No cloud job created
   - Email sent via local Moodle mail
   - User receives email

### Test 3: Automatic Fallback (Simulated Failure)
1. Temporarily break AWS connectivity (e.g., invalid credentials)
2. Create a test user in a cloud-enabled company
3. Enroll and wait for cron
4. **Expected**:
   - Cloud submission fails
   - System automatically falls back to local mail
   - User still receives email
   - Error logged in Moodle logs

---

## Rollback Plan

If issues arise, rollback is simple:

1. **Re-enable Standard Email**:
   - Go to **Message outputs → Default message outputs**
   - **Check** "Email" for Reengagement
   - Save changes

2. **Remove Event Listener** (optional):
   - Comment out the event observer in `db/events.php`
   - Run: `php admin/cli/purge_caches.php`

System reverts to standard Moodle email delivery.

---

## Advantages Over Other Options

| Feature | Option A | Option B | Option C | **Option D** |
|:---|:---:|:---:|:---:|:---:|
| Update Safe | ❌ | ⚠️ | ✅ | ✅ |
| No New Plugin | ✅ | ✅ | ❌ | ✅ |
| Automatic Fallback | ❌ | ❌ | ❌ | ✅ |
| Error Handling | Basic | Basic | Basic | **Robust** |
| Rollback | Hard | Medium | Easy | **Easy** |

---

## Next Steps

1. ✅ Get manager approval
2. Register event listener in `db/events.php`
3. Implement `handle_message_sent()` in `EmailOffloadHandler.php`
4. Configure admin settings (disable standard email)
5. Test with small batch (5-10 users)
6. Monitor logs and cloud job dashboard
7. Roll out to production
