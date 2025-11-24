# Email Offload Workflow: "The Race to the Mailbox"

This document explains the internal mechanics of how the Cloud Offload system intercepts Moodle's default email process.

## The Scenario

Imagine Moodle's email system is like a corporate mailroom.

### 1. The Trigger (User Created)
When you create a user in the GUI, Moodle does two things immediately:
1.  **Writes to Database:** It saves the new user in `mdl_user`.
2.  **Queues an Email:** It writes a "letter" (the email data) into its outbox table (`mdl_email`). It intends for a background worker (Cron) to pick this up later and send it via SMTP.

### 2. The Interception (Our Plugin)
Our plugin is listening for that exact moment the user is created. It acts **faster** than Moodle's background worker.

Here is the step-by-step flow of how we "bypass" Moodle:

1.  **Event Fires:** Moodle shouts, *"I just created User 15093!"*
2.  **We React:** Our plugin (`EmailOffloadHandler`) hears this and wakes up.
3.  **We Steal the Data:** We look at User 15093, grab their email, name, and that temporary password you just set.
4.  **We Send to Cloud:** We package this data and ship it off to AWS immediately. **The email is now effectively "sent" by us.**
5.  **The "Heist" (Suppression):** This is the critical part. We know Moodle *also* put a letter in the `mdl_email` box.
    *   We run a command: `DELETE FROM mdl_email WHERE userid = 15093`.
    *   **Result:** We destroy the letter Moodle intended to send.

### 3. How Moodle Reacts
Moodle's background worker (the "Postman") arrives a few seconds or minutes later to check the `mdl_email` box.

*   **Moodle's Expectation:** "I should find a letter here for User 15093."
*   **The Reality:** The box is empty.
*   **Moodle's Reaction:** It assumes there is nothing to do. It does **not** throw an error. It does **not** panic. It simply moves on to the next task.

## Does Moodle know the mail is completed?
**No.**

*   **Moodle's Perspective:** As far as Moodle is concerned, that email never happened. It won't show up in Moodle's internal mail logs (if you have standard logging enabled). Moodle thinks, *"I guess I didn't have to send anything after all."*
*   **The Consequence:** You cannot use Moodle's standard "Mail Log" reports to check these emails anymore.
*   **The Solution:** That is exactly why we built the **"Email Offload" tab** in your Dashboard. **WE** are now the source of truth. If you want to know if User 15093 got their email, you must check *our* dashboard, not Moodle's logs.

## Summary
We are performing a "man-in-the-middle" operation. We let Moodle do the heavy lifting of creating the user, but we **intercept and destroy** the email task before Moodle can execute it, replacing it with our own superior Cloud delivery.

## Frequently Asked Questions (Technical Deep Dive)

### 1. The "Cron Race" Concern
**Q: If Moodle's Cron runs at the exact same second (e.g., 10:00:00) that I create a user, will both systems send the email?**

**A: It is extremely unlikely, bordering on impossible.**
*   **Synchronous Execution:** When you click "Create User", our plugin code runs *inside* that same web request. It happens immediately, millisecond-by-millisecond.
*   **Transaction Isolation:** In most database setups, the "New Email" record in `mdl_email` is not even visible to other processes (like the Cron) until the entire "Create User" transaction is finished.
*   **We are "Inside the Room":** Since we are part of the transaction, we delete the email *before* the transaction commits. By the time the Cron (the "Postman") looks at the database, the record was created and destroyed within a split second, effectively never existing for the outside world.

### 2. Why Delete? Why not "Mark as Sent"?
**Q: Instead of deleting the record from `mdl_email`, can we just mark it as "sent" so we keep the log in Moodle?**

**A: No, because `mdl_email` is a Queue, not a Log.**
*   **Moodle's Behavior:** When Moodle successfully sends an email via Cron, **it deletes the record from `mdl_email`**. It does not keep it.
*   **The "To-Do" List:** Think of `mdl_email` as a "To-Do List", not a "History Book". If an item is on the list, Moodle *will* try to do it. If we leave it there, Moodle will send it.
### 3. Bulk Uploads & Fallbacks (The 2000 User Scenario)
**Q: What happens if I upload a CSV with 2000 users? What if AWS is slow or the script times out?**

**A: The system is designed to "Fail Safe" to ensure no user is left behind.**

1.  **The Batch Process:**
    *   When you upload a CSV, our plugin queues the users in memory instead of processing them instantly.
    *   Once the CSV is fully read, we process the queue and send jobs to AWS one by one.

2.  **Handling Delays:**
    *   **AWS Delay:** We only wait for AWS to accept the message (~50ms), not for the email to be delivered. So 2000 users takes roughly 100 seconds.
    *   **Timeout Risk:** If the script times out (e.g., after 300s) halfway through, the remaining users are simply left in Moodle's `mdl_email` queue.

3.  **The Fallback Safety Net:**
    *   **AWS Down?** If `submit_job` returns false, we **skip** the suppression step. Moodle sends the email.
    *   **Script Crash/Timeout?** The suppression step is never reached for the remaining users. Moodle sends the email.
    *   **Success?** Only when AWS confirms receipt do we delete the Moodle email.

**Result:** In a worst-case scenario, Moodle's default mailer acts as the backup, ensuring every user gets their credentials.
