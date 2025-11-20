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
 * Audit logger for tracking user actions and system events.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_manireports\api;

defined('MOODLE_INTERNAL') || die();

/**
 * Audit logger class.
 */
class audit_logger {

    /**
     * Log an action to the audit trail.
     *
     * @param string $action Action performed (e.g., 'create', 'update', 'delete', 'view', 'export')
     * @param string $objecttype Type of object (e.g., 'report', 'schedule', 'dashboard')
     * @param int $objectid ID of the object
     * @param string $details Additional details (JSON or text)
     * @param int $userid User ID (defaults to current user)
     * @return int Audit log ID
     */
    public static function log_action($action, $objecttype, $objectid, $details = '', $userid = null) {
        global $DB, $USER;

        if ($userid === null) {
            $userid = $USER->id;
        }

        $log = new \stdClass();
        $log->userid = $userid;
        $log->action = $action;
        $log->objecttype = $objecttype;
        $log->objectid = $objectid;
        $log->details = $details;
        $log->ipaddress = self::get_user_ip();
        $log->timecreated = time();

        $logid = $DB->insert_record('manireports_audit_logs', $log);

        return $logid;
    }

    /**
     * Log report creation.
     *
     * @param int $reportid Report ID
     * @param string $reporttype Report type
     * @param int $userid User ID
     */
    public static function log_report_create($reportid, $reporttype, $userid = null) {
        $details = json_encode(array('reporttype' => $reporttype));
        self::log_action('create', 'report', $reportid, $details, $userid);
    }

    /**
     * Log report update.
     *
     * @param int $reportid Report ID
     * @param array $changes Changes made
     * @param int $userid User ID
     */
    public static function log_report_update($reportid, $changes = array(), $userid = null) {
        $details = json_encode($changes);
        self::log_action('update', 'report', $reportid, $details, $userid);
    }

    /**
     * Log report deletion.
     *
     * @param int $reportid Report ID
     * @param int $userid User ID
     */
    public static function log_report_delete($reportid, $userid = null) {
        self::log_action('delete', 'report', $reportid, '', $userid);
    }

    /**
     * Log report execution.
     *
     * @param int $reportid Report ID or 0 for prebuilt reports
     * @param string $reporttype Report type
     * @param int $recordcount Number of records returned
     * @param int $userid User ID
     */
    public static function log_report_execute($reportid, $reporttype, $recordcount, $userid = null) {
        $details = json_encode(array(
            'reporttype' => $reporttype,
            'recordcount' => $recordcount
        ));
        self::log_action('execute', 'report', $reportid, $details, $userid);
    }

    /**
     * Log report export.
     *
     * @param string $reporttype Report type
     * @param string $format Export format
     * @param int $recordcount Number of records exported
     * @param int $userid User ID
     */
    public static function log_report_export($reporttype, $format, $recordcount, $userid = null) {
        $details = json_encode(array(
            'reporttype' => $reporttype,
            'format' => $format,
            'recordcount' => $recordcount
        ));
        self::log_action('export', 'report', 0, $details, $userid);
    }

    /**
     * Log schedule creation.
     *
     * @param int $scheduleid Schedule ID
     * @param string $schedulename Schedule name
     * @param int $userid User ID
     */
    public static function log_schedule_create($scheduleid, $schedulename, $userid = null) {
        $details = json_encode(array('name' => $schedulename));
        self::log_action('create', 'schedule', $scheduleid, $details, $userid);
    }

    /**
     * Log schedule update.
     *
     * @param int $scheduleid Schedule ID
     * @param array $changes Changes made
     * @param int $userid User ID
     */
    public static function log_schedule_update($scheduleid, $changes = array(), $userid = null) {
        $details = json_encode($changes);
        self::log_action('update', 'schedule', $scheduleid, $details, $userid);
    }

    /**
     * Log schedule deletion.
     *
     * @param int $scheduleid Schedule ID
     * @param int $userid User ID
     */
    public static function log_schedule_delete($scheduleid, $userid = null) {
        self::log_action('delete', 'schedule', $scheduleid, '', $userid);
    }

    /**
     * Log dashboard creation.
     *
     * @param int $dashboardid Dashboard ID
     * @param string $dashboardname Dashboard name
     * @param int $userid User ID
     */
    public static function log_dashboard_create($dashboardid, $dashboardname, $userid = null) {
        $details = json_encode(array('name' => $dashboardname));
        self::log_action('create', 'dashboard', $dashboardid, $details, $userid);
    }

    /**
     * Log dashboard update.
     *
     * @param int $dashboardid Dashboard ID
     * @param array $changes Changes made
     * @param int $userid User ID
     */
    public static function log_dashboard_update($dashboardid, $changes = array(), $userid = null) {
        $details = json_encode($changes);
        self::log_action('update', 'dashboard', $dashboardid, $details, $userid);
    }

    /**
     * Log dashboard deletion.
     *
     * @param int $dashboardid Dashboard ID
     * @param int $userid User ID
     */
    public static function log_dashboard_delete($dashboardid, $userid = null) {
        self::log_action('delete', 'dashboard', $dashboardid, '', $userid);
    }

    /**
     * Log failed authorization attempt.
     *
     * @param string $capability Capability that was checked
     * @param string $context Context where check failed
     * @param int $userid User ID
     */
    public static function log_auth_failure($capability, $context, $userid = null) {
        $details = json_encode(array(
            'capability' => $capability,
            'context' => $context
        ));
        self::log_action('auth_failure', 'security', 0, $details, $userid);
    }

    /**
     * Log configuration change.
     *
     * @param string $setting Setting name
     * @param string $oldvalue Old value
     * @param string $newvalue New value
     * @param int $userid User ID
     */
    public static function log_config_change($setting, $oldvalue, $newvalue, $userid = null) {
        $details = json_encode(array(
            'setting' => $setting,
            'old_value' => $oldvalue,
            'new_value' => $newvalue
        ));
        self::log_action('config_change', 'settings', 0, $details, $userid);
    }

    /**
     * Get audit logs with filtering.
     *
     * @param array $filters Filters (userid, action, objecttype, datefrom, dateto)
     * @param int $page Page number (0-based)
     * @param int $perpage Records per page
     * @return array Array with 'data', 'total', 'page', 'perpage'
     */
    public static function get_logs($filters = array(), $page = 0, $perpage = 50) {
        global $DB;

        $where = array('1=1');
        $params = array();

        if (!empty($filters['userid'])) {
            $where[] = 'userid = :userid';
            $params['userid'] = $filters['userid'];
        }

        if (!empty($filters['action'])) {
            $where[] = 'action = :action';
            $params['action'] = $filters['action'];
        }

        if (!empty($filters['objecttype'])) {
            $where[] = 'objecttype = :objecttype';
            $params['objecttype'] = $filters['objecttype'];
        }

        if (!empty($filters['datefrom'])) {
            $where[] = 'timecreated >= :datefrom';
            $params['datefrom'] = $filters['datefrom'];
        }

        if (!empty($filters['dateto'])) {
            $where[] = 'timecreated <= :dateto';
            $params['dateto'] = $filters['dateto'];
        }

        $where_sql = implode(' AND ', $where);

        // Get total count.
        $total = $DB->count_records_sql("SELECT COUNT(*) FROM {manireports_audit_logs} WHERE {$where_sql}", $params);

        // Get records.
        $sql = "SELECT * FROM {manireports_audit_logs} WHERE {$where_sql} ORDER BY timecreated DESC";
        $logs = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);

        return array(
            'data' => array_values($logs),
            'total' => $total,
            'page' => $page,
            'perpage' => $perpage
        );
    }

    /**
     * Get user's IP address.
     *
     * @return string IP address
     */
    private static function get_user_ip() {
        return getremoteaddr();
    }

    /**
     * Clean up old audit logs based on retention policy.
     *
     * @param int $retention_days Number of days to retain logs
     * @return int Number of records deleted
     */
    public static function cleanup_old_logs($retention_days) {
        global $DB;

        $cutoff = time() - ($retention_days * 86400);

        return $DB->delete_records_select('manireports_audit_logs', 'timecreated < :cutoff', array('cutoff' => $cutoff));
    }
}
