<?php
namespace local_manireports\api;

defined('MOODLE_INTERNAL') || die();

use local_manireports\api\TemplateEngine;

/**
 * Reminder Manager for Reminder Feature.
 * Handles rule management, user eligibility, and instance creation.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ReminderManager {

    /**
     * Create a new reminder rule.
     *
     * @param \stdClass $data Rule data
     * @return int New rule ID
     */
    public function create_rule($data) {
        global $DB;

        $data->timecreated = time();
        $data->timemodified = time();

        // Validate trigger value JSON
        if (!is_string($data->trigger_value)) {
            $data->trigger_value = json_encode($data->trigger_value);
        }

        return $DB->insert_record('manireports_rem_rule', $data);
    }

    /**
     * Update an existing reminder rule.
     *
     * @param int $id Rule ID
     * @param \stdClass $data Rule data
     * @return bool
     */
    public function update_rule($id, $data) {
        global $DB;

        $data->id = $id;
        $data->timemodified = time();

        if (isset($data->trigger_value) && !is_string($data->trigger_value)) {
            $data->trigger_value = json_encode($data->trigger_value);
        }

        return $DB->update_record('manireports_rem_rule', $data);
    }

    /**
     * Delete a reminder rule and all related data.
     *
     * @param int $id Rule ID
     * @return bool
     */
    public function delete_rule($id) {
        global $DB;
        
        // Hard delete - remove rule and all related data
        try {
            // Start transaction
            $transaction = $DB->start_delegated_transaction();
            
            // 1. Get all instances for this rule
            $instances = $DB->get_records('manireports_rem_inst', ['ruleid' => $id]);
            
            // 2. Delete all jobs for these instances
            foreach ($instances as $instance) {
                $DB->delete_records('manireports_rem_job', ['instanceid' => $instance->id]);
            }
            
            // 3. Delete all instances
            $DB->delete_records('manireports_rem_inst', ['ruleid' => $id]);
            
            // 4. Delete the rule itself
            $DB->delete_records('manireports_rem_rule', ['id' => $id]);
            
            // Commit transaction
            $transaction->allow_commit();
            
            return true;
        } catch (\Exception $e) {
            error_log('Error deleting rule: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get eligible users for a specific rule.
     *
     * @param int $ruleid Rule ID
     * @return array List of eligible users (objects with id, courseid, etc.)
     */
    public function get_eligible_users($ruleid) {
        global $DB;

        $rule = $DB->get_record('manireports_rem_rule', ['id' => $ruleid]);
        if (!$rule || !$rule->enabled) {
            return [];
        }

        $users = [];
        $trigger_value = json_decode($rule->trigger_value, true);
        $days = isset($trigger_value['days']) ? (int)$trigger_value['days'] : 0;
        $hours = isset($trigger_value['hours']) ? (int)$trigger_value['hours'] : 0;
        $offset_seconds = ($days * 86400) + ($hours * 3600);

        // Base query for enrolled users with company filtering
        // Added JOIN to {course} to get startdate for start_date trigger
        // Added JOIN to {company_users} for company-scoped reminders
        $sql = "SELECT ue.userid, e.courseid, ue.timecreated as enroltime, c.startdate, cu.companyid
                FROM {user_enrolments} ue
                JOIN {enrol} e ON e.id = ue.enrolid
                JOIN {user} u ON u.id = ue.userid
                JOIN {course} c ON c.id = e.courseid
                JOIN {company_users} cu ON cu.userid = u.id
                WHERE u.deleted = 0 AND u.suspended = 0 AND ue.status = 0
                  AND cu.companyid = :companyid";

        if ($rule->courseid > 0) {
            $sql .= " AND e.courseid = :courseid";
        }
        
        // Filter by company if applicable (requires IOMAD or custom logic)
        // For now, assuming standard Moodle or IOMAD handling via other means if needed.

        $params = ['courseid' => $rule->courseid, 'companyid' => $rule->companyid];

        // Apply Trigger Logic
        switch ($rule->trigger_type) {
            case 'enrol':
                // User enrolled X time ago
                $cutoff = time() - $offset_seconds;
                $sql .= " AND ue.timecreated <= :cutoff";
                $params['cutoff'] = $cutoff;
                break;

            case 'incomplete_after':
                // Same as enrol, but implies checking completion status later
                $cutoff = time() - $offset_seconds;
                $sql .= " AND ue.timecreated <= :cutoff";
                $params['cutoff'] = $cutoff;
                break;

            case 'start_date':
                // Course start date + offset <= now
                $cutoff = time() - $offset_seconds;
                $sql .= " AND c.startdate <= :cutoff";
                $params['cutoff'] = $cutoff;
                break;

            case 'license_expiry':
                // IOMAD License Expiry
                // Check for licenses expiring within the next X days
                // We use activityid to store licenseid for uniqueness
                $days = isset($trigger_value['days']) ? (int)$trigger_value['days'] : 30;
                $now = time();
                $future_time = $now + ($days * 86400);

                // Query IOMAD licenses - get courseid from companylicense_courses
                // If rule has specific course, use it; otherwise get first course or 0
                if ($rule->courseid > 0) {
                    // Specific course selected
                    $sql = "SELECT 2 as userid, :rulecourse as courseid, l.id as activityid, l.expirydate
                            FROM {companylicense} l
                            WHERE l.expirydate >= :now AND l.expirydate <= :future 
                              AND l.companyid = :companyid
                              AND EXISTS (
                                SELECT 1 FROM {companylicense_courses} clc 
                                WHERE clc.licenseid = l.id AND clc.courseid = :courseid
                              )";
                    $params = [
                        'now' => $now,
                        'future' => $future_time,
                        'companyid' => $rule->companyid,
                        'courseid' => $rule->courseid,
                        'rulecourse' => $rule->courseid
                    ];
                } else {
                    // All courses - get first course from companylicense_courses or 0
                    $sql = "SELECT 2 as userid, 
                                   COALESCE((SELECT MIN(clc.courseid) FROM {companylicense_courses} clc WHERE clc.licenseid = l.id), 0) as courseid,
                                   l.id as activityid, l.expirydate
                            FROM {companylicense} l
                            WHERE l.expirydate >= :now AND l.expirydate <= :future 
                              AND l.companyid = :companyid";
                    $params = [
                        'now' => $now,
                        'future' => $future_time,
                        'companyid' => $rule->companyid
                    ];
                }
                
                // Override candidates fetch
                $candidates = $DB->get_records_sql($sql, $params);
                break;

            case 'license_utilization':
                // IOMAD License Utilization
                // Check if usage >= X%
                $percent = isset($trigger_value['percent']) ? (int)$trigger_value['percent'] : 80;
                
                // Get courseid from companylicense_courses
                if ($rule->courseid > 0) {
                    // Specific course selected
                    $sql = "SELECT 2 as userid, :rulecourse as courseid, l.id as activityid, l.allocation, l.used
                            FROM {companylicense} l
                            WHERE l.allocation > 0 
                              AND ((l.used / l.allocation) * 100) >= :percent 
                              AND l.companyid = :companyid
                              AND EXISTS (
                                SELECT 1 FROM {companylicense_courses} clc 
                                WHERE clc.licenseid = l.id AND clc.courseid = :courseid
                              )";
                    $params = [
                        'percent' => $percent,
                        'companyid' => $rule->companyid,
                        'courseid' => $rule->courseid,
                        'rulecourse' => $rule->courseid
                    ];
                } else {
                    // All courses - get first course from companylicense_courses or 0
                    $sql = "SELECT 2 as userid, 
                                   COALESCE((SELECT MIN(clc.courseid) FROM {companylicense_courses} clc WHERE clc.licenseid = l.id), 0) as courseid,
                                   l.id as activityid, l.allocation, l.used
                            FROM {companylicense} l
                            WHERE l.allocation > 0 
                              AND ((l.used / l.allocation) * 100) >= :percent 
                              AND l.companyid = :companyid";
                    $params = [
                        'percent' => $percent,
                        'companyid' => $rule->companyid
                    ];
                }
                
                // Override candidates fetch
                $candidates = $DB->get_records_sql($sql, $params);
                break;

            case 'custom':
                // Custom logic placeholder - currently no-op or requires specific implementation
                // For now, we return empty to avoid sending incorrectly
                return [];
        }

        if (!isset($candidates)) {
            $candidates = $DB->get_records_sql($sql, $params);
        }

        // Filter out those who already have an instance for this rule
        $eligible = [];
        foreach ($candidates as $candidate) {
            $check_params = [
                'ruleid' => $rule->id,
                'userid' => $candidate->userid,
                'courseid' => $candidate->courseid,
                'companyid' => $rule->companyid  // Company-scoped uniqueness
            ];
            
            // If activityid (licenseid) is present, include it in uniqueness check
            if (isset($candidate->activityid)) {
                $check_params['activityid'] = $candidate->activityid;
            }

            if (!$DB->record_exists('manireports_rem_inst', $check_params)) {
                $eligible[] = $candidate;
            }
        }

        return $eligible;
    }

    /**
     * Create reminder instances for eligible users.
     *
     * @param int $ruleid Rule ID
     * @return int Count of created instances
     */
    public function create_instances($ruleid) {
        global $DB;

        $rule = $DB->get_record('manireports_rem_rule', ['id' => $ruleid]);
        if (!$rule) {
            return 0;
        }

        $eligible_users = $this->get_eligible_users($ruleid);
        $count = 0;

        foreach ($eligible_users as $user) {
            // Check completion if required
            if ($rule->trigger_type === 'incomplete_after') {
                $completion = new \completion_info($DB->get_record('course', ['id' => $user->courseid]));
                if ($completion->is_course_complete($user->userid)) {
                    continue; // Skip if already completed
                }
            }

            $instance = new \stdClass();
            $instance->ruleid = $rule->id;
            $instance->userid = $user->userid;
            $instance->courseid = $user->courseid;
            $instance->companyid = $rule->companyid;  // Company-scoped instance
            // Use activityid from candidate (for licenses) or from rule
            $instance->activityid = isset($user->activityid) ? $user->activityid : $rule->activityid;
            $instance->emailsent = 0;
            $instance->next_send = time() + $rule->emaildelay;
            $instance->timecreated = time();
            $instance->timemodified = time();
            $instance->completed = 0; // Initial state

            $DB->insert_record('manireports_rem_inst', $instance);
            $count++;
        }

        return $count;
    }
    /**
     * Get managers for a user (IOMAD support).
     *
     * @param int $userid User ID
     * @param int $companyid Company ID
     * @return array List of manager user objects
     */
    public function get_managers($userid, $companyid) {
        global $DB;
        
        // Check if IOMAD tables exist
        if (!$DB->get_manager()->table_exists('block_iomad_company_users')) {
            return [];
        }

        // Logic to find department managers or company admins
        // This is a simplified query; adjust based on specific IOMAD roles/structure
        $sql = "SELECT u.*
                FROM {user} u
                JOIN {block_iomad_company_users} cu ON cu.userid = u.id
                WHERE cu.companyid = :companyid AND cu.managertype = 1 
                AND u.deleted = 0 AND u.suspended = 0";
        
        return $DB->get_records_sql($sql, ['companyid' => $companyid]);
    }
}
