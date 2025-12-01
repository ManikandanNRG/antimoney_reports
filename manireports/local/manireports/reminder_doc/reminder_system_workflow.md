# Reminder System - Complete Workflow Documentation

## Overview
The ManiReports Reminder System is an automated email notification system that sends reminders to users based on course enrollment, completion status, and license management triggers.

---

## Key Concepts

### 1. **Reminder Templates**
Email templates that define the content of reminder emails.

**Fields:**
- **Name**: Template identifier (e.g., "Course Completion Reminder")
- **Subject**: Email subject line (supports placeholders)
- **Body HTML**: Email content (supports placeholders like `{firstname}`, `{lastname}`, `{coursename}`, `{courseurl}`)
- **Enabled**: Yes/No toggle to activate/deactivate the template

**Purpose of "Enabled" field:**
- **Yes**: Template is active and can be used in reminder rules
- **No**: Template is hidden from selection and cannot be used (useful for drafts or temporarily disabling templates)

---

### 2. **Reminder Rules**
Rules that define WHEN and TO WHOM reminders should be sent.

**Fields:**

#### **General Settings**
- **Rule Name**: Descriptive name (e.g., "7-Day Course Completion Reminder")
- **Company**: Which IOMAD company this rule applies to (company-specific)
- **Course**: Which course triggers this reminder (or "All Courses")

#### **Trigger Settings**
- **Trigger Type**: When the reminder should start
  - `After Enrollment`: X time after user enrolls
  - `After Course Start Date`: X time after course officially starts
  - `Incomplete After Period`: If user hasn't completed after X time
  - `License Expiry (Days Before)`: Warn before license expires
  - `License Utilization (%)`: Alert when X% of licenses are used
  - `Custom Logic`: Developer-defined custom triggers

- **Trigger Value**: Number + Unit (Minutes/Hours/Days/Weeks/%)
  - Example: `7 Days` = Trigger 7 days after enrollment
  - Example: `90 %` = Trigger when 90% of licenses are used

- **Target Activity**: What completion to check
  - `Course Completion`: Stop reminders when entire course is completed
  - `Specific Activity`: Stop reminders when a specific activity (quiz, assignment, etc.) is completed

#### **Schedule Settings**
- **Email Delay**: Time between sending each reminder
  - Example: `1 Day` = Send reminder every 1 day
  - Supports Minutes/Hours/Days/Weeks for testing

- **Number of Reminders**: How many times to send (max 5)
  - Example: `3` = Send 3 reminders total

#### **Recipient Settings**
- **Send to Users**: Yes/No - Send to enrolled users
- **Send to Managers**: Yes/No - Also send to user's manager (IOMAD)
- **Recipients (To)**: Manual email addresses (comma-separated)
- **Recipients (CC)**: CC email addresses (for license triggers)

#### **Content Settings**
- **Email Template**: Which template to use for the email content

- **Enabled**: Yes/No toggle to activate/deactivate the rule

**Purpose of "Enabled" field:**
- **Yes**: Rule is active and will create reminder instances
- **No**: Rule is paused/disabled (useful for seasonal courses or testing)

---

## Complete Workflow

### **Step 1: Create Email Template**
1. Go to **Reminders → Templates**
2. Click **"Add Template"**
3. Fill in:
   - Name: "Course Completion Reminder"
   - Subject: "Complete your {coursename} course"
   - Body: "Hi {firstname}, please complete {coursename}. Click here: {courseurl}"
   - Enabled: **Yes**
4. Save

---

### **Step 2: Create Reminder Rule**
1. Go to **Reminders → Reminder Dashboard**
2. Click **"Add New Rule"**
3. Fill in:
   - **Rule Name**: "7-Day Incomplete Reminder"
   - **Company**: Select your company
   - **Course**: Select specific course (or "All Courses")
   - **Trigger Type**: "Incomplete After Period"
   - **Trigger Value**: `7 Days`
   - **Target Activity**: "Course Completion"
   - **Email Delay**: `1 Day`
   - **Number of Reminders**: `3`
   - **Send to Users**: ✓ Yes
   - **Send to Managers**: ✗ No
   - **Email Template**: "Course Completion Reminder"
   - **Enabled**: **Yes**
4. Save

---

### **Step 3: System Creates Reminder Instances (Automated)**

**When does this happen?**
- A scheduled task runs periodically (e.g., every hour)
- The task is: `local_manireports\task\process_reminders`

**What happens?**
1. System finds all **enabled** reminder rules
2. For each rule, it finds **eligible users**:
   - Users enrolled in the specified course
   - Users in the specified company
   - Users who match the trigger criteria
3. System creates **reminder instances** for each eligible user
   - Each instance tracks: `ruleid`, `userid`, `courseid`, `companyid`, `activityid`
   - Instance has a `status`: `pending`, `sent`, `completed`, `cancelled`

---

### **Step 4: System Sends Reminders (Automated)**

**Example Timeline** (using our rule above):

| Time | Event |
|------|-------|
| **Day 0** | User enrolls in course |
| **Day 7** | System checks: Course incomplete? → **Yes** → Create reminder instance |
| **Day 7** | **1st Reminder Sent** (status = `sent`, `sent_count` = 1) |
| **Day 8** | **2nd Reminder Sent** (status = `sent`, `sent_count` = 2) |
| **Day 9** | **3rd Reminder Sent** (status = `sent`, `sent_count` = 3) |
| **Day 10** | No more reminders (max 3 reached) |

**What if user completes the course on Day 8?**
- System checks completion status before each reminder
- If `Target Activity` is completed → Instance status = `completed`
- **No more reminders sent** ✓

---

### **Step 5: Completion Check Logic**

**For `activityid = -1` (Course Completion):**
```php
$completion = new completion_info($course);
if ($completion->is_course_complete($userid)) {
    // Stop reminders
}
```

**For `activityid > 0` (Specific Activity):**
```php
$modinfo = get_fast_modinfo($courseid, $userid);
$cm = $modinfo->get_cm($activityid);
$completion_data = $completion->get_data($cm, false, $userid);
if ($completion_data->completionstate != COMPLETION_INCOMPLETE) {
    // Stop reminders
}
```

---

## Trigger Type Examples

### **1. After Enrollment**
- **Use Case**: Welcome email series
- **Example**: Send reminder 1 day after enrollment
- **Trigger Value**: `1 Day`

### **2. After Course Start Date**
- **Use Case**: Cohort-based courses with fixed start dates
- **Example**: Remind users 2 days after course officially starts
- **Trigger Value**: `2 Days`

### **3. Incomplete After Period**
- **Use Case**: Encourage completion
- **Example**: If user hasn't completed after 7 days, send reminders
- **Trigger Value**: `7 Days`

### **4. License Expiry (Days Before)**
- **Use Case**: Warn admins before licenses expire
- **Example**: Alert 30 days before expiry
- **Trigger Value**: `30 Days`
- **Recipients**: Admin emails (manual entry)

### **5. License Utilization (%)**
- **Use Case**: Alert when running out of seats
- **Example**: Notify when 90% of licenses are used
- **Trigger Value**: `90 %`
- **Recipients**: Sales team emails

---

## Database Schema

### **Tables**

#### `mdl_manireports_rem_tmpl` (Templates)
| Field | Description |
|-------|-------------|
| `id` | Template ID |
| `name` | Template name |
| `subject` | Email subject |
| `bodyhtml` | Email HTML content |
| `enabled` | 1 = Active, 0 = Disabled |

#### `mdl_manireports_rem_rule` (Rules)
| Field | Description |
|-------|-------------|
| `id` | Rule ID |
| `name` | Rule name |
| `companyid` | Company ID (IOMAD) |
| `courseid` | Course ID (0 = All Courses) |
| `trigger_type` | Trigger type (enrol, start_date, etc.) |
| `trigger_value` | JSON: `{"days": 7, "hours": 0}` |
| `activityid` | Target activity (-1 = Course Completion) |
| `emaildelay` | Delay in seconds |
| `remindercount` | Max reminders to send |
| `templateid` | Template ID |
| `send_to_user` | 1 = Yes, 0 = No |
| `send_to_managers` | 1 = Yes, 0 = No |
| `thirdparty_emails` | Manual recipients |
| `cc_emails` | CC recipients |
| `enabled` | 1 = Active, 0 = Disabled |

#### `mdl_manireports_rem_inst` (Instances)
| Field | Description |
|-------|-------------|
| `id` | Instance ID |
| `ruleid` | Rule ID |
| `userid` | User ID |
| `courseid` | Course ID |
| `companyid` | Company ID |
| `activityid` | Target activity ID |
| `status` | pending/sent/completed/cancelled |
| `sent_count` | How many reminders sent |
| `last_sent` | Timestamp of last email |
| `timecreated` | When instance was created |

---

## Testing the System

### **Quick Test (5 Minutes)**
1. Create a template
2. Create a rule:
   - Trigger: "After Enrollment"
   - Trigger Value: `5 Minutes`
   - Email Delay: `2 Minutes`
   - Number of Reminders: `3`
   - Enabled: **Yes**
3. Enroll a test user in the course
4. Wait 5 minutes → 1st reminder sent
5. Wait 2 more minutes → 2nd reminder sent
6. Wait 2 more minutes → 3rd reminder sent
7. Complete the target activity → No more reminders

---

## Key Features

### **Company-Specific Rules**
- Each rule is tied to a specific IOMAD company
- Prevents conflicts when courses are shared across companies
- Users only receive reminders from their company's rules

### **Granular Completion Tracking**
- `activityid = -1`: Check overall course completion
- `activityid > 0`: Check specific activity completion
- Reminders stop when target is completed

### **Flexible Time Units**
- Minutes (for testing)
- Hours
- Days
- Weeks
- Percentage (for license triggers)

### **Enable/Disable Control**
- **Templates**: Draft templates can be disabled
- **Rules**: Seasonal rules can be paused without deletion

---

## Summary

The **"Enabled"** field is a simple on/off switch:
- **Templates**: Controls whether the template appears in the dropdown when creating rules
- **Rules**: Controls whether the rule actively creates reminder instances

This allows you to:
- Create draft templates/rules without activating them
- Temporarily pause reminders without deleting the configuration
- Test rules by enabling/disabling them quickly
