<?php
namespace local_manireports\task;

defined('MOODLE_INTERNAL') || die();

use core\task\scheduled_task;
use local_manireports\api\ReminderManager;
use local_manireports\api\TemplateEngine;
use local_manireports\api\CloudJobManager;

/**
 * Scheduled task to process and send reminders.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_reminders extends scheduled_task {

    /**
     * Get task name.
     */
    public function get_name() {
        return get_string('task_process_reminders', 'local_manireports');
    }

    /**
     * Execute task.
     */
    public function execute() {
        global $DB;

        mtrace('Starting reminder processing...');

        // 1. Create new instances for eligible users
        $manager = new ReminderManager();
        $rules = $DB->get_records('manireports_rem_rule', ['enabled' => 1]);
        foreach ($rules as $rule) {
            $count = $manager->create_instances($rule->id);
            if ($count > 0) {
                mtrace("Created {$count} instances for rule '{$rule->name}'");
            }
        }

        // 2. Process due instances
        $now = time();
        $sql = "SELECT i.*, r.remindercount, r.emaildelay, r.templateid, r.send_to_user, r.send_to_managers, r.companyid, r.name as rulename
                FROM {manireports_rem_inst} i
                JOIN {manireports_rem_rule} r ON r.id = i.ruleid
                WHERE i.next_send <= :now 
                  AND i.completed = 0 
                  AND i.emailsent < r.remindercount
                  AND r.enabled = 1";
        
        $instances = $DB->get_records_sql($sql, ['now' => $now]);
        mtrace("Found " . count($instances) . " due instances.");

        $template_engine = new TemplateEngine();

        foreach ($instances as $instance) {
            // Atomic Claiming - Lock by updating next_send
            $lock_time = $now + 300; // Lock for 5 minutes
            $claimed = $DB->execute("UPDATE {manireports_rem_inst} 
                                     SET next_send = ? 
                                     WHERE id = ? AND next_send = ?", 
                                     [$lock_time, $instance->id, $instance->next_send]);

            if (!$claimed) {
                continue; // Someone else claimed it
            }

            mtrace("Processing instance {$instance->id} for user {$instance->userid}");

            try {
                // Check completion
                $completion = new \completion_info($DB->get_record('course', ['id' => $instance->courseid]));
                if ($completion->is_course_complete($instance->userid)) {
                    $DB->set_field('manireports_rem_inst', 'completed', 1, ['id' => $instance->id]);
                    mtrace("User {$instance->userid} completed course, skipping.");
                    continue;
                }

                // Get user and course data
                $user = $DB->get_record('user', ['id' => $instance->userid]);
                $course = $DB->get_record('course', ['id' => $instance->courseid]);
                
                // Render Template
                $rendered = $template_engine->render($instance->templateid, $user, $course);

                // Check if cloud offload is enabled
                $cloud_enabled = get_config('local_manireports', 'cloud_offload_enabled');
                
                if ($cloud_enabled && class_exists('\local_manireports\api\CloudJobManager')) {
                    // Use CloudJobManager
                    $cloud_manager = new CloudJobManager();
                    
                    // Prepare recipients
                    $recipients = [];
                    
                    if ($instance->send_to_user) {
                        $recipients[] = [
                            'email' => $user->email,
                            'firstname' => $user->firstname,
                            'lastname' => $user->lastname,
                            'username' => $user->username,
                            'password' => '',
                            'loginurl' => new \moodle_url('/login/index.php')
                        ];
                    }
                    
                    if ($instance->send_to_managers) {
                        $managers = $manager->get_managers($instance->userid, $instance->companyid);
                        foreach ($managers as $mgr) {
                            $recipients[] = [
                                'email' => $mgr->email,
                                'firstname' => $mgr->firstname,
                                'lastname' => $mgr->lastname,
                                'username' => $mgr->username,
                                'password' => '',
                                'loginurl' => new \moodle_url('/login/index.php')
                            ];
                        }
                    }
                    
                    try {
                        // Create and submit cloud job
                        $job_id = $cloud_manager->create_job(
                            'reminder',
                            $recipients,
                            $instance->companyid,
                            $rendered['subject'],
                            $rendered['body_html']
                        );
                        
                        $cloud_manager->submit_job($job_id);
                        
                        // Log each recipient
                        foreach ($recipients as $recipient) {
                            $audit = new \stdClass();
                            $audit->instanceid = $instance->id;
                            $audit->message_id = \core\uuid::generate();
                            $audit->job_id = $job_id;
                            $audit->recipient_email = $recipient['email'];
                            $audit->status = 'submitted';
                            $audit->attempts = 1;
                            $audit->last_attempt_ts = time();
                            $audit->payload = json_encode(['subject' => $rendered['subject'], 'type' => 'reminder']);
                            $DB->insert_record('manireports_rem_job', $audit);
                        }
                        
                        mtrace("Offloaded to cloud (Job ID: $job_id) for " . count($recipients) . " recipients");
                        
                    } catch (\Exception $e) {
                        mtrace("Cloud offload failed: " . $e->getMessage() . ". Falling back to local send.");
                        // Fallback to local
                        email_to_user($user, \core_user::get_noreply_user(), $rendered['subject'], $rendered['body_text'], $rendered['body_html']);
                        
                        $audit = new \stdClass();
                        $audit->instanceid = $instance->id;
                        $audit->message_id = \core\uuid::generate();
                        $audit->recipient_email = $user->email;
                        $audit->status = 'local_sent';
                        $audit->attempts = 1;
                        $audit->last_attempt_ts = time();
                        $DB->insert_record('manireports_rem_job', $audit);
                    }

                } else {
                    // Local send
                    email_to_user($user, \core_user::get_noreply_user(), $rendered['subject'], $rendered['body_text'], $rendered['body_html']);
                    
                    $audit = new \stdClass();
                    $audit->instanceid = $instance->id;
                    $audit->message_id = \core\uuid::generate();
                    $audit->recipient_email = $user->email;
                    $audit->status = 'local_sent';
                    $audit->attempts = 1;
                    $audit->last_attempt_ts = time();
                    $DB->insert_record('manireports_rem_job', $audit);
                    
                    mtrace("Sent locally to {$user->email}");
                }

                // Update instance state
                $next_run = time() + $instance->emaildelay;
                $DB->execute("UPDATE {manireports_rem_inst} 
                              SET emailsent = emailsent + 1, next_send = ? 
                              WHERE id = ?", [$next_run, $instance->id]);

            } catch (\Exception $e) {
                mtrace("Error processing instance {$instance->id}: " . $e->getMessage());
                $DB->set_field('manireports_rem_inst', 'next_send', time() + 3600, ['id' => $instance->id]);
            }
        }
        
        mtrace('Reminder processing completed.');
    }
}
