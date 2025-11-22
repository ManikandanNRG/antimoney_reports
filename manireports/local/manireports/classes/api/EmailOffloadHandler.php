<?php
namespace local_manireports\api;

defined('MOODLE_INTERNAL') || die();

/**
 * Class EmailOffloadHandler
 *
 * Handles the interception of Moodle/IOMAD events and offloads emails to the cloud.
 * Contains critical IOMAD-specific logic for password retrieval and email suppression.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 */
class EmailOffloadHandler {

    /**
     * Handles the user_created event (CSV Import).
     *
     * @param \core\event\user_created $event
     */
    public static function handle_user_created(\core\event\user_created $event) {
        global $DB;

        $user_id = $event->objectid;
        $user = $DB->get_record('user', ['id' => $user_id]);

        if (!$user) {
            return;
        }

        // Check if this is part of a bulk operation (simple heuristic: check recent creations)
        // For now, we assume if cloud offload is enabled for the company, we offload ALL new user emails
        // to ensure consistency, or we can implement a "bulk detection" logic.
        
        // Determine Company ID (IOMAD specific)
        $company_id = self::get_user_company($user_id);
        if (!$company_id) {
            return; // Not an IOMAD user or no company
        }

        // Check if offload is enabled for this company
        if (!self::is_offload_enabled($company_id)) {
            return;
        }

        // 1. Retrieve Temporary Password (IOMAD Specific)
        // IOMAD stores it in user_preferences with key 'iomad_temporary'
        $temp_password = get_user_preferences('iomad_temporary', null, $user_id);

        // If no temp password, it might be a normal registration or manual creation without temp pass.
        // But for CSV upload, it should be there.
        if (empty($temp_password)) {
            // Fallback: Check if we can get it from the event data or other means?
            // If we can't get the password, we can't send the "Welcome" email with credentials.
            // We might skip offload in this case to be safe.
            return;
        }

        // 2. Create Cloud Job
        $manager = new CloudJobManager();
        $recipient = [
            'email' => $user->email,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'username' => $user->username,
            'password' => $temp_password, // Sending the temp password
            'loginurl' => new \moodle_url('/login/index.php')
        ];

        $job_id = $manager->create_job('user_created', [$recipient], $company_id);
        $manager->submit_job($job_id);

        // 3. Suppress Default Email (IOMAD Specific)
        // IOMAD queues emails in 'mdl_email'. We need to delete the pending email for this user.
        // We look for emails created very recently for this user.
        $DB->delete_records_select('email', "userid = ? AND timecreated > ?", [$user_id, time() - 60]);
    }

    /**
     * Handles the license_allocated event.
     *
     * @param \block_iomad_company_admin\event\user_license_assigned $event
     */
    public static function handle_license_allocated(\block_iomad_company_admin\event\user_license_assigned $event) {
        global $DB;

        $data = $event->get_data();
        $userid = $data['relateduserid'];
        $licenseid = $data['objectid'];
        $companyid = $data['courseid']; // Note: IOMAD events sometimes map courseid to companyid or vice versa, verify event structure.
        // Assuming standard IOMAD event structure where courseid might be used for company context or we derive it.
        
        // Better to get company from license
        $license = $DB->get_record('company_licenses', ['id' => $licenseid]);
        if ($license) {
            $companyid = $license->companyid;
        }

        if (!self::is_offload_enabled($companyid)) {
            return;
        }

        $user = $DB->get_record('user', ['id' => $userid]);
        $course = $DB->get_record('course', ['id' => $license->courseid]);

        // Create Cloud Job
        $manager = new CloudJobManager();
        $recipient = [
            'email' => $user->email,
            'firstname' => $user->firstname,
            'course_name' => $course->fullname,
            'license_name' => $license->name
        ];

        $job_id = $manager->create_job('license_allocation', [$recipient], $companyid);
        $manager->submit_job($job_id);

        // Suppress Default Email
        $DB->delete_records_select('email', "userid = ? AND timecreated > ?", [$userid, time() - 60]);
    }

    /**
     * Checks if cloud offload is enabled for a company.
     *
     * @param int $company_id
     * @return bool
     */
    private static function is_offload_enabled(int $company_id): bool {
        global $DB;
        $settings = $DB->get_record('manireports_cloud_company_settings', ['company_id' => $company_id]);
        return $settings && $settings->enabled;
    }

    /**
     * Helper to get user's company ID.
     *
     * @param int $user_id
     * @return int|false
     */
    private static function get_user_company(int $user_id) {
        global $DB;
        // IOMAD stores user-company relation in mdl_company_users
        $record = $DB->get_record('company_users', ['userid' => $user_id]);
        return $record ? $record->companyid : false;
    }
}
