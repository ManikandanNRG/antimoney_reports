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
     * Delete (soft delete) a reminder rule.
     *
     * @param int $id Rule ID
     * @return bool
     */
    public function delete_rule($id) {
        global $DB;
        // Soft delete by disabling
        return $DB->set_field('manireports_rem_rule', 'enabled', 0, ['id' => $id]);
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

        // Base query for enrolled users
        $sql = "SELECT ue.userid, e.courseid, ue.timecreated as enroltime
                FROM {user_enrolments} ue
                JOIN {enrol} e ON e.id = ue.enrolid
                JOIN {user} u ON u.id = ue.userid
                WHERE u.deleted = 0 AND u.suspended = 0 AND ue.status = 0";

        if ($rule->courseid > 0) {
            $sql .= " AND e.courseid = :courseid";
        }
        
        // Filter by company if applicable (requires IOMAD or custom logic)
        // For now, assuming standard Moodle or IOMAD handling via other means if needed.
        // If IOMAD, we might need to join block_iomad_company_users.

        $params = ['courseid' => $rule->courseid];

        // Apply Trigger Logic
        switch ($rule->trigger_type) {
            case 'enrol':
                // User enrolled X time ago
                // We want users where (enroltime + offset) <= now
                // AND who haven't completed the course (optional check here, but handled in instance creation too)
                $cutoff = time() - $offset_seconds;
                // To avoid processing very old enrolments, maybe add a lower bound? 
                // For now, let's just check if they hit the mark.
                // Actually, we should check if they are *past* the mark but not *too* far past?
                // Or just process everyone who matches and let the instance logic handle duplicates.
                
                // Better approach: Find users who enrolled before cutoff
                $sql .= " AND ue.timecreated <= :cutoff";
                $params['cutoff'] = $cutoff;
                break;

            case 'incomplete_after':
                // Same as enrol, but implies checking completion status later
                $cutoff = time() - $offset_seconds;
                $sql .= " AND ue.timecreated <= :cutoff";
                $params['cutoff'] = $cutoff;
                break;

            // Add other triggers as needed
        }

        $candidates = $DB->get_records_sql($sql, $params);

        // Filter out those who already have an instance for this rule
        $eligible = [];
        foreach ($candidates as $candidate) {
            if (!$DB->record_exists('manireports_rem_inst', [
                'ruleid' => $rule->id,
                'userid' => $candidate->userid,
                'courseid' => $candidate->courseid
            ])) {
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
