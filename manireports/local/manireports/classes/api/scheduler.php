<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Scheduler API for managing scheduled reports.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_manireports\api;

use local_manireports\api\audit_logger;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduler class for report scheduling.
 */
class scheduler {

    /**
     * Create a new report schedule.
     *
     * @param object $schedule Schedule data
     * @return int Schedule ID
     */
    public function create_schedule($schedule) {
        global $DB, $USER;

        // Validate required fields.
        $required = array('reporttype', 'format', 'frequency', 'enabled');
        foreach ($required as $field) {
            if (!isset($schedule->$field)) {
                throw new \moodle_exception('error:invalidparameters', 'local_manireports');
            }
        }

        // Set defaults.
        $schedule->userid = isset($schedule->userid) ? $schedule->userid : $USER->id;
        $schedule->timecreated = time();
        $schedule->timemodified = time();
        $schedule->lastrun = 0;
        $schedule->nextrun = $this->calculate_next_run($schedule->frequency, time());

        // Insert schedule.
        $scheduleid = $DB->insert_record('manireports_schedules', $schedule);

        // Log audit trail.
        audit_logger::log_schedule_create($scheduleid, $schedule->name, $schedule->userid);

        return $scheduleid;
    }

    /**
     * Update an existing schedule.
     *
     * @param int $scheduleid Schedule ID
     * @param object $schedule Schedule data
     * @return bool Success
     */
    public function update_schedule($scheduleid, $schedule) {
        global $DB;

        $existing = $DB->get_record('manireports_schedules', array('id' => $scheduleid));
        if (!$existing) {
            throw new \moodle_exception('error:schedulenotfound', 'local_manireports');
        }

        $schedule->id = $scheduleid;
        $schedule->timemodified = time();

        // Recalculate next run if frequency changed.
        if (isset($schedule->frequency) && $schedule->frequency != $existing->frequency) {
            $schedule->nextrun = $this->calculate_next_run($schedule->frequency, time());
        }

        $result = $DB->update_record('manireports_schedules', $schedule);

        // Log audit trail.
        $changes = array();
        foreach ((array)$schedule as $key => $value) {
            if (isset($existing->$key) && $existing->$key != $value) {
                $changes[$key] = array('old' => $existing->$key, 'new' => $value);
            }
        }
        if (!empty($changes)) {
            audit_logger::log_schedule_update($scheduleid, $changes);
        }

        return $result;
    }

    /**
     * Delete a schedule.
     *
     * @param int $scheduleid Schedule ID
     * @return bool Success
     */
    public function delete_schedule($scheduleid) {
        global $DB;

        // Delete recipients first.
        $DB->delete_records('manireports_schedule_recipients', array('scheduleid' => $scheduleid));

        // Delete schedule.
        $result = $DB->delete_records('manireports_schedules', array('id' => $scheduleid));

        // Log audit trail.
        audit_logger::log_schedule_delete($scheduleid);

        return $result;
    }

    /**
     * Get a schedule by ID.
     *
     * @param int $scheduleid Schedule ID
     * @return object|false Schedule object or false
     */
    public function get_schedule($scheduleid) {
        global $DB;
        return $DB->get_record('manireports_schedules', array('id' => $scheduleid));
    }

    /**
     * Get all schedules for a user.
     *
     * @param int $userid User ID
     * @return array Array of schedule objects
     */
    public function get_user_schedules($userid) {
        global $DB;
        return $DB->get_records('manireports_schedules', array('userid' => $userid), 'timecreated DESC');
    }

    /**
     * Get schedules that are due to run.
     *
     * @return array Array of schedule objects
     */
    public function get_due_schedules() {
        global $DB;

        $now = time();

        $sql = "SELECT *
                  FROM {manireports_schedules}
                 WHERE enabled = 1
                   AND nextrun <= :now
              ORDER BY nextrun ASC";

        return $DB->get_records_sql($sql, array('now' => $now));
    }

    /**
     * Calculate next run time based on frequency.
     *
     * @param string $frequency Frequency (daily, weekly, monthly)
     * @param int $from_time Calculate from this time (default: now)
     * @return int Next run timestamp
     */
    public function calculate_next_run($frequency, $from_time = null) {
        if ($from_time === null) {
            $from_time = time();
        }

        switch ($frequency) {
            case 'daily':
                // Next day at 2 AM.
                $next = strtotime('tomorrow 2:00', $from_time);
                break;

            case 'weekly':
                // Next Monday at 2 AM.
                $next = strtotime('next Monday 2:00', $from_time);
                break;

            case 'monthly':
                // First day of next month at 2 AM.
                $next = strtotime('first day of next month 2:00', $from_time);
                break;

            default:
                throw new \moodle_exception('error:invalidfrequency', 'local_manireports', '', $frequency);
        }

        return $next;
    }

    /**
     * Add a recipient to a schedule.
     *
     * @param int $scheduleid Schedule ID
     * @param string $email Recipient email
     * @return int Recipient ID
     */
    public function add_recipient($scheduleid, $email) {
        global $DB;

        // Check if recipient already exists.
        $existing = $DB->get_record('manireports_schedule_recipients', array(
            'scheduleid' => $scheduleid,
            'email' => $email
        ));

        if ($existing) {
            return $existing->id;
        }

        $recipient = new \stdClass();
        $recipient->scheduleid = $scheduleid;
        $recipient->email = $email;
        $recipient->timecreated = time();

        return $DB->insert_record('manireports_schedule_recipients', $recipient);
    }

    /**
     * Remove a recipient from a schedule.
     *
     * @param int $scheduleid Schedule ID
     * @param string $email Recipient email
     * @return bool Success
     */
    public function remove_recipient($scheduleid, $email) {
        global $DB;

        return $DB->delete_records('manireports_schedule_recipients', array(
            'scheduleid' => $scheduleid,
            'email' => $email
        ));
    }

    /**
     * Get all recipients for a schedule.
     *
     * @param int $scheduleid Schedule ID
     * @return array Array of recipient objects
     */
    public function get_recipients($scheduleid) {
        global $DB;
        return $DB->get_records('manireports_schedule_recipients', array('scheduleid' => $scheduleid));
    }

    /**
     * Update schedule after execution.
     *
     * @param int $scheduleid Schedule ID
     * @param bool $success Whether execution was successful
     */
    public function update_after_execution($scheduleid, $success) {
        global $DB;

        $schedule = $this->get_schedule($scheduleid);
        if (!$schedule) {
            return;
        }

        $schedule->lastrun = time();
        $schedule->nextrun = $this->calculate_next_run($schedule->frequency, time());

        if (!$success) {
            $schedule->failcount = isset($schedule->failcount) ? $schedule->failcount + 1 : 1;
        } else {
            $schedule->failcount = 0;
        }

        $DB->update_record('manireports_schedules', $schedule);
    }

    /**
     * Check if user has permission to manage schedules.
     *
     * @param int $userid User ID
     * @return bool True if user has permission
     */
    public function can_manage_schedules($userid) {
        $context = \context_system::instance();
        return has_capability('local/manireports:schedule', $context, $userid);
    }
}
