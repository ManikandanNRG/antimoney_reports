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
     * Queue of user IDs to process on shutdown (to handle race conditions).
     * @var array
     */
    private static $user_queue = [];

    /**
     * Flag to ensure shutdown function is registered only once.
     * @var bool
     */
    private static $shutdown_registered = false;

    /**
     * Handles the user_created event (CSV Import).
     *
     * @param \core\event\user_created $event
     */
    public static function handle_user_created(\core\event\user_created $event) {
        global $DB;

        $user_id = $event->objectid;
        error_log("CloudOffload: Event triggered for user $user_id");

        $user = $DB->get_record('user', ['id' => $user_id]);

        if (!$user) {
            error_log("CloudOffload: User $user_id not found in DB");
            return;
        }

        // Determine Company ID (IOMAD specific)
        $company_id = self::get_user_company($user_id);
        if (!$company_id) {
            error_log("CloudOffload: No company found for user $user_id");
            return; // Not an IOMAD user or no company
        }
        error_log("CloudOffload: User $user_id belongs to company $company_id");

        // Check if offload is enabled for this company
        if (!self::is_offload_enabled($company_id)) {
            error_log("CloudOffload: Offload NOT enabled for company $company_id");
            return;
        }

        // 1. Retrieve Temporary Password (IOMAD Specific)
        // IOMAD stores it in user_preferences with key 'iomad_temporary' (Encrypted)
        $temp_password_enc = get_user_preferences('iomad_temporary', null, $user_id);
        $temp_password = null;

        if (!empty($temp_password_enc)) {
             error_log("CloudOffload: Found encrypted 'iomad_temporary' preference for user $user_id.");
             
             // Decrypt using IOMAD's native method
             if (class_exists('\company_user')) {
                 $temp_password = \company_user::rc4decrypt($temp_password_enc);
                 if ($temp_password) {
                     error_log("CloudOffload: Successfully decrypted temporary password.");
                 } else {
                     error_log("CloudOffload: Decryption returned empty result.");
                 }
             } else {
                 error_log("CloudOffload: CRITICAL - \company_user class not found. Cannot decrypt password.");
             }
        }

        // If no temp password from preference, check request params (GUI fallback)
        if (empty($temp_password)) {
            error_log("CloudOffload: No 'iomad_temporary' preference (or decryption failed). Checking request params for password.");
            
            // Fallback: Check Request Params (for GUI creation)
            // Debug: Log all request keys to see what we have
            // error_log("CloudOffload: Request Keys: " . implode(', ', array_keys($_REQUEST)));
            
            // Moodle/IOMAD forms often use 'newpassword' or 'password'
            $raw_password = optional_param('newpassword', '', PARAM_RAW);
            if (empty($raw_password)) {
                $raw_password = optional_param('password', '', PARAM_RAW);
            }

            if (!empty($raw_password)) {
                $temp_password = $raw_password;
                error_log("CloudOffload: Found password in request params.");
            } else {
                // [RACE CONDITION FIX]
                // For CSV uploads, the 'iomad_temporary' preference is set AFTER this event fires.
                // We must queue this user and check again at the end of the script execution.
                error_log("CloudOffload: No password found immediately. Queuing user $user_id for shutdown processing (CSV race condition handling).");
                
                self::$user_queue[] = $user_id;
                
                if (!self::$shutdown_registered) {
                    register_shutdown_function(['\local_manireports\api\EmailOffloadHandler', 'process_queue']);
                    self::$shutdown_registered = true;
                    error_log("CloudOffload: Registered shutdown function for queue processing.");
                }
                return;
            }
        }
        
        // If we found the password immediately (GUI or pre-set), process now.
        self::process_single_user_offload($user, $company_id, $temp_password);
    }

    /**
     * Processes the queue of users at shutdown.
     */
    public static function process_queue() {
        global $DB;
        
        if (empty(self::$user_queue)) {
            return;
        }

        error_log("CloudOffload: Processing shutdown queue for " . count(self::$user_queue) . " users.");

        foreach (self::$user_queue as $user_id) {
            try {
                error_log("CloudOffload: Processing queued user $user_id");
                
                $user = $DB->get_record('user', ['id' => $user_id]);
                if (!$user) {
                    error_log("CloudOffload: [Queue] User $user_id not found in DB. Skipping.");
                    continue;
                }

                $company_id = self::get_user_company($user_id);
                if (!$company_id) {
                    error_log("CloudOffload: [Queue] No company found for user $user_id. Skipping.");
                    continue;
                }

                // Try to get password again (should be saved now)
                $temp_password_enc = get_user_preferences('iomad_temporary', null, $user_id);
                $temp_password = null;

                if (!empty($temp_password_enc)) {
                     if (class_exists('\company_user')) {
                         $temp_password = \company_user::rc4decrypt($temp_password_enc);
                         error_log("CloudOffload: [Queue] Successfully decrypted password for user $user_id.");
                     }
                }

                if ($temp_password) {
                    self::process_single_user_offload($user, $company_id, $temp_password);
                } else {
                    error_log("CloudOffload: [Queue] Still no password found for user $user_id. Skipping offload.");
                }
            } catch (\Exception $e) {
                $debug_info = (isset($e->debuginfo)) ? " Debug: " . $e->debuginfo : "";
                error_log("CloudOffload: [Queue] Exception processing user $user_id: " . $e->getMessage() . $debug_info);
            }
        }
    }

    /**
     * Helper to process the offload for a single user.
     * 
     * @param \stdClass $user
     * @param int $company_id
     * @param string $password
     */
    private static function process_single_user_offload($user, $company_id, $password) {
        global $DB;
        
        error_log("CloudOffload: Creating job for user {$user->id} with password.");

        // 2. Create Cloud Job
        $manager = new CloudJobManager();
        $recipient = [
            'email' => $user->email,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'username' => $user->username,
            'password' => $password, // Sending the temp password
            'loginurl' => new \moodle_url('/login/index.php')
        ];

        $job_id = $manager->create_job('user_created', [$recipient], $company_id);
        error_log("CloudOffload: Job created with ID $job_id");
        $manager->submit_job($job_id);

        // 3. Suppress Default Email (IOMAD Specific)
        // IOMAD queues emails in 'mdl_email'. We need to delete the pending email for this user.
        // We look for emails created very recently for this user.
        try {
            $DB->delete_records_select('email', "userid = ? AND timecreated > ?", [$user->id, time() - 120]);
            error_log("CloudOffload: Suppressed default Moodle email for user {$user->id}");
        } catch (\Exception $e) {
            // If suppression fails, it's not critical. The user might get a duplicate email, but that's better than a crash.
            error_log("CloudOffload: Warning - Failed to suppress default email for user {$user->id}: " . $e->getMessage());
        }
    }

    /**
     * Handles the license_allocated event.
     *
     * @param \block_iomad_company_admin\event\user_license_assigned $event
     */
    public static function handle_license_allocated(\block_iomad_company_admin\event\user_license_assigned $event) {
        global $DB;

        $data = $event->get_data();
        $assignment_id = $data['objectid'];
        
        error_log("CloudOffload: License Event triggered. Assignment ID: $assignment_id");

        // 1. Get Assignment Record (Links User to License)
        // Table: mdl_companylicense_users
        $assignment = $DB->get_record('companylicense_users', ['id' => $assignment_id]);
        if (!$assignment) {
            error_log("CloudOffload: Assignment $assignment_id not found in companylicense_users");
            return;
        }
        
        $userid = $assignment->userid;
        $licenseid = $assignment->licenseid;
        error_log("CloudOffload: Found User $userid and License $licenseid from assignment");

        // 2. Get License Details (Links to Company and Course)
        // Table: mdl_companylicense
        $license = $DB->get_record('companylicense', ['id' => $licenseid]);
        if (!$license) {
            error_log("CloudOffload: License $licenseid not found in companylicense");
            return;
        }
        
        $companyid = $license->companyid;
        error_log("CloudOffload: License belongs to Company $companyid");

        // 3. Check Offload Status
        if (!self::is_offload_enabled($companyid)) {
            error_log("CloudOffload: Offload NOT enabled for company $companyid");
            return;
        }

        // 4. Get User and Course Details
        $user = $DB->get_record('user', ['id' => $userid]);
        
        // [NEW LOGIC] Check if user is brand new (created in last 2 minutes)
        // This handles CSV Create and GUI Create scenarios where we don't want a double email.
        if ((time() - $user->timecreated) < 120) {
            error_log("CloudOffload: User $userid is new (created < 2 mins ago). Skipping License Email to avoid spam.");
            return;
        }
        
        // Determine Course ID
        $courseid = 0;
        if (!empty($assignment->licensecourseid)) {
             $courseid = $assignment->licensecourseid;
             error_log("CloudOffload: Found Course ID $courseid from assignment");
        } else {
             // Fallback: Get first course linked to this license
             $lic_course = $DB->get_record('companylicense_courses', ['licenseid' => $licenseid]);
             if ($lic_course) {
                 $courseid = $lic_course->courseid;
                 error_log("CloudOffload: Found Course ID $courseid from companylicense_courses");
             }
        }

        if (!$courseid) {
             error_log("CloudOffload: No course found for license $licenseid");
             return;
        }

        $course = $DB->get_record('course', ['id' => $courseid]);
        
        if (!$user || !$course) {
             error_log("CloudOffload: User or Course record not found (User: " . ($user ? 'OK' : 'MISSING') . ", Course: " . ($course ? 'OK' : 'MISSING') . ")");
             return;
        }

        // 5. Create Cloud Job
        $manager = new CloudJobManager();
        $recipient = [
            'email' => $user->email,
            'firstname' => $user->firstname,
            'course_name' => $course->fullname,
            'license_name' => $license->name
        ];

        $job_id = $manager->create_job('license_allocation', [$recipient], $companyid);
        error_log("CloudOffload: License Job created with ID $job_id");
        $manager->submit_job($job_id);

        // 6. Suppress Default Email
        $DB->delete_records_select('email', "userid = ? AND timecreated > ?", [$userid, time() - 60]);
    }

    /**
     * Handles the license_allocated event.
     *
     * @param int $company_id
     * @return bool
     */
    private static function is_offload_enabled(int $company_id): bool {
        global $DB;
        $settings = $DB->get_record('manireports_cloud_conf', ['company_id' => $company_id]);
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
        // 1. Try DB lookup (works for existing users or if IOMAD inserted already)
        $record = $DB->get_record('company_users', ['userid' => $user_id]);
        if ($record) {
            return $record->companyid;
        }

        // 2. Fallback: Check Request Params (for GUI creation where DB might not be ready yet)
        // IOMAD forms usually pass 'companyid' or 'company'
        $company_id = optional_param('companyid', 0, PARAM_INT);
        if ($company_id) {
            error_log("CloudOffload: Found company ID $company_id from request params");
            return $company_id;
        }

        return false;
    }
}
