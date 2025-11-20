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
 * Time tracking engine for recording user activity time.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_manireports\api;

defined('MOODLE_INTERNAL') || die();

/**
 * Time tracking engine class.
 */
class time_engine {

    /**
     * Record a heartbeat signal from user.
     *
     * @param int $userid User ID
     * @param int $courseid Course ID
     * @param int $timestamp Current timestamp
     * @return bool True on success
     */
    public function record_heartbeat($userid, $courseid, $timestamp) {
        global $DB;

        // Check if time tracking is enabled.
        if (!get_config('local_manireports', 'enabletimetracking')) {
            return false;
        }

        $now = time();
        $sessiontimeout = get_config('local_manireports', 'sessiontimeout') ?: 10;
        $timeoutthreshold = $now - ($sessiontimeout * 60);

        // Check for existing active session.
        $session = $DB->get_record('manireports_time_sessions', array(
            'userid' => $userid,
            'courseid' => $courseid
        ));

        if ($session) {
            // Check if session is still active (last update within timeout).
            if ($session->lastupdated >= $timeoutthreshold) {
                // Update existing session.
                $session->lastupdated = $now;
                $session->duration = $now - $session->sessionstart;
                $DB->update_record('manireports_time_sessions', $session);
            } else {
                // Session expired, close it and create new one.
                $this->close_session($session->id);
                $this->create_session($userid, $courseid, $now);
            }
        } else {
            // Create new session.
            $this->create_session($userid, $courseid, $now);
        }

        return true;
    }

    /**
     * Create a new time tracking session.
     *
     * @param int $userid User ID
     * @param int $courseid Course ID
     * @param int $timestamp Start timestamp
     * @return int Session ID
     */
    protected function create_session($userid, $courseid, $timestamp) {
        global $DB;

        $record = new \stdClass();
        $record->userid = $userid;
        $record->courseid = $courseid;
        $record->sessionstart = $timestamp;
        $record->lastupdated = $timestamp;
        $record->duration = 0;

        return $DB->insert_record('manireports_time_sessions', $record);
    }

    /**
     * Close a time tracking session.
     *
     * @param int $sessionid Session ID
     * @return bool True on success
     */
    protected function close_session($sessionid) {
        global $DB;

        $session = $DB->get_record('manireports_time_sessions', array('id' => $sessionid));
        if (!$session) {
            return false;
        }

        // Calculate final duration.
        $duration = $session->lastupdated - $session->sessionstart;

        // Add to daily summary.
        $this->add_to_daily_summary($session->userid, $session->courseid, $session->sessionstart, $duration);

        // Delete session.
        $DB->delete_records('manireports_time_sessions', array('id' => $sessionid));

        return true;
    }

    /**
     * Close inactive sessions.
     *
     * @param int $timeoutseconds Timeout in seconds (default: 600 = 10 minutes)
     * @return int Number of sessions closed
     */
    public function close_inactive_sessions($timeoutseconds = 600) {
        global $DB;

        $threshold = time() - $timeoutseconds;

        // Get inactive sessions.
        $sessions = $DB->get_records_select('manireports_time_sessions', 
            'lastupdated < :threshold', 
            array('threshold' => $threshold)
        );

        $count = 0;
        foreach ($sessions as $session) {
            if ($this->close_session($session->id)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Add session duration to daily summary.
     *
     * @param int $userid User ID
     * @param int $courseid Course ID
     * @param int $timestamp Session timestamp
     * @param int $duration Duration in seconds
     * @return bool True on success
     */
    protected function add_to_daily_summary($userid, $courseid, $timestamp, $duration) {
        global $DB;

        $date = date('Y-m-d', $timestamp);

        // Check if daily record exists.
        $daily = $DB->get_record('manireports_time_daily', array(
            'userid' => $userid,
            'courseid' => $courseid,
            'date' => $date
        ));

        if ($daily) {
            // Update existing record.
            $daily->duration += $duration;
            $daily->sessioncount++;
            $daily->lastupdated = time();
            $DB->update_record('manireports_time_daily', $daily);
        } else {
            // Create new record.
            $record = new \stdClass();
            $record->userid = $userid;
            $record->courseid = $courseid;
            $record->date = $date;
            $record->duration = $duration;
            $record->sessioncount = 1;
            $record->lastupdated = time();
            $DB->insert_record('manireports_time_daily', $record);
        }

        return true;
    }

    /**
     * Get user time spent in a date range.
     *
     * @param int $userid User ID
     * @param string $startdate Start date (Y-m-d)
     * @param string $enddate End date (Y-m-d)
     * @param int|null $courseid Optional course ID filter
     * @return array Array of daily time records
     */
    public function get_user_time($userid, $startdate, $enddate, $courseid = null) {
        global $DB;

        $params = array(
            'userid' => $userid,
            'startdate' => $startdate,
            'enddate' => $enddate
        );

        $sql = "SELECT *
                  FROM {manireports_time_daily}
                 WHERE userid = :userid
                   AND date >= :startdate
                   AND date <= :enddate";

        if ($courseid !== null) {
            $sql .= " AND courseid = :courseid";
            $params['courseid'] = $courseid;
        }

        $sql .= " ORDER BY date ASC";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Get total time spent by user.
     *
     * @param int $userid User ID
     * @param int|null $courseid Optional course ID filter
     * @param int|null $days Optional number of days to look back
     * @return int Total duration in seconds
     */
    public function get_total_time($userid, $courseid = null, $days = null) {
        global $DB;

        $params = array('userid' => $userid);
        $sql = "SELECT SUM(duration) as total
                  FROM {manireports_time_daily}
                 WHERE userid = :userid";

        if ($courseid !== null) {
            $sql .= " AND courseid = :courseid";
            $params['courseid'] = $courseid;
        }

        if ($days !== null) {
            $startdate = date('Y-m-d', strtotime("-{$days} days"));
            $sql .= " AND date >= :startdate";
            $params['startdate'] = $startdate;
        }

        $result = $DB->get_record_sql($sql, $params);
        return $result && $result->total ? (int)$result->total : 0;
    }

    /**
     * Aggregate session data into daily summaries.
     *
     * This is called by the scheduled task.
     *
     * @param string|null $date Optional specific date to aggregate (Y-m-d)
     * @return int Number of sessions aggregated
     */
    public function aggregate_daily_time($date = null) {
        global $DB;

        if ($date === null) {
            $date = date('Y-m-d', strtotime('yesterday'));
        }

        // Get all sessions for the date.
        $starttime = strtotime($date . ' 00:00:00');
        $endtime = strtotime($date . ' 23:59:59');

        $sessions = $DB->get_records_select('manireports_time_sessions',
            'sessionstart >= :start AND sessionstart <= :end',
            array('start' => $starttime, 'end' => $endtime)
        );

        $count = 0;
        foreach ($sessions as $session) {
            $duration = $session->lastupdated - $session->sessionstart;
            if ($duration > 0) {
                $this->add_to_daily_summary($session->userid, $session->courseid, $session->sessionstart, $duration);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get active users count.
     *
     * @param int $days Number of days to look back
     * @param int|null $courseid Optional course ID filter
     * @return int Number of active users
     */
    public function get_active_users_count($days = 7, $courseid = null) {
        global $DB;

        $startdate = date('Y-m-d', strtotime("-{$days} days"));
        $params = array('startdate' => $startdate);

        $sql = "SELECT COUNT(DISTINCT userid) as count
                  FROM {manireports_time_daily}
                 WHERE date >= :startdate";

        if ($courseid !== null) {
            $sql .= " AND courseid = :courseid";
            $params['courseid'] = $courseid;
        }

        $result = $DB->get_record_sql($sql, $params);
        return $result && $result->count ? (int)$result->count : 0;
    }
}
