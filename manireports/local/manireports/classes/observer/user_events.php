<?php
namespace local_manireports\observer;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer for user lifecycle events.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_events {

    /**
     * Handle user deletion event.
     * Removes all reminder instances and jobs for the deleted user.
     *
     * @param \core\event\user_deleted $event
     */
    public static function user_deleted(\core\event\user_deleted $event) {
        global $DB;
        
        $userid = $event->objectid;
        
        mtrace("Reminder cleanup: User {$userid} deleted, removing instances...");
        
        // Get all instances for this user
        $instances = $DB->get_records('manireports_rem_inst', ['userid' => $userid]);
        
        $job_count = 0;
        $instance_count = 0;
        
        // Delete jobs for each instance
        foreach ($instances as $instance) {
            $deleted = $DB->delete_records('manireports_rem_job', ['instanceid' => $instance->id]);
            $job_count += $deleted;
        }
        
        // Delete all instances for this user
        $instance_count = $DB->delete_records('manireports_rem_inst', ['userid' => $userid]);
        
        mtrace("Reminder cleanup: Deleted {$instance_count} instances and {$job_count} jobs for user {$userid}");
    }

    /**
     * Handle user unenrollment event.
     * Removes reminder instances and jobs for the unenrolled course.
     *
     * @param \core\event\user_enrolment_deleted $event
     */
    public static function user_unenrolled(\core\event\user_enrolment_deleted $event) {
        global $DB;
        
        $userid = $event->relateduserid;
        $courseid = $event->courseid;
        
        mtrace("Reminder cleanup: User {$userid} unenrolled from course {$courseid}, removing instances...");
        
        // Get instances for this user + course combination
        $instances = $DB->get_records('manireports_rem_inst', [
            'userid' => $userid,
            'courseid' => $courseid
        ]);
        
        $job_count = 0;
        $instance_count = 0;
        
        // Delete jobs for each instance
        foreach ($instances as $instance) {
            $deleted = $DB->delete_records('manireports_rem_job', ['instanceid' => $instance->id]);
            $job_count += $deleted;
        }
        
        // Delete instances for this user + course
        $instance_count = $DB->delete_records('manireports_rem_inst', [
            'userid' => $userid,
            'courseid' => $courseid
        ]);
        
        mtrace("Reminder cleanup: Deleted {$instance_count} instances and {$job_count} jobs for user {$userid} in course {$courseid}");
    }
}
