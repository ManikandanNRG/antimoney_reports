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
 * Privacy Subsystem implementation for local_manireports
 *
 * Implements GDPR compliance by providing data export and deletion capabilities.
 *
 * @package    local_manireports
 * @copyright  2024 ManiReports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_manireports\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider for ManiReports plugin
 *
 * Implements the privacy API to support GDPR compliance.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Returns meta data about this system.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        
        // Time tracking sessions.
        $collection->add_database_table(
            'manireports_usertime_sessions',
            [
                'userid' => 'privacy:metadata:usertime_sessions:userid',
                'courseid' => 'privacy:metadata:usertime_sessions:courseid',
                'sessionstart' => 'privacy:metadata:usertime_sessions:sessionstart',
                'lastupdated' => 'privacy:metadata:usertime_sessions:lastupdated',
                'duration' => 'privacy:metadata:usertime_sessions:duration',
            ],
            'privacy:metadata:usertime_sessions'
        );

        // Daily time tracking aggregates.
        $collection->add_database_table(
            'manireports_usertime_daily',
            [
                'userid' => 'privacy:metadata:usertime_daily:userid',
                'courseid' => 'privacy:metadata:usertime_daily:courseid',
                'date' => 'privacy:metadata:usertime_daily:date',
                'duration' => 'privacy:metadata:usertime_daily:duration',
                'sessioncount' => 'privacy:metadata:usertime_daily:sessioncount',
            ],
            'privacy:metadata:usertime_daily'
        );

        // Custom reports created by users.
        $collection->add_database_table(
            'manireports_customreports',
            [
                'name' => 'privacy:metadata:customreports:name',
                'description' => 'privacy:metadata:customreports:description',
                'sqlquery' => 'privacy:metadata:customreports:sqlquery',
                'configjson' => 'privacy:metadata:customreports:configjson',
                'createdby' => 'privacy:metadata:customreports:createdby',
                'timecreated' => 'privacy:metadata:customreports:timecreated',
                'timemodified' => 'privacy:metadata:customreports:timemodified',
            ],
            'privacy:metadata:customreports'
        );

        // Report execution history.
        $collection->add_database_table(
            'manireports_report_runs',
            [
                'userid' => 'privacy:metadata:report_runs:userid',
                'reportid' => 'privacy:metadata:report_runs:reportid',
                'status' => 'privacy:metadata:report_runs:status',
                'timestarted' => 'privacy:metadata:report_runs:timestarted',
                'timefinished' => 'privacy:metadata:report_runs:timefinished',
            ],
            'privacy:metadata:report_runs'
        );

        // Audit logs.
        $collection->add_database_table(
            'manireports_audit_logs',
            [
                'userid' => 'privacy:metadata:audit_logs:userid',
                'action' => 'privacy:metadata:audit_logs:action',
                'objecttype' => 'privacy:metadata:audit_logs:objecttype',
                'objectid' => 'privacy:metadata:audit_logs:objectid',
                'details' => 'privacy:metadata:audit_logs:details',
                'timecreated' => 'privacy:metadata:audit_logs:timecreated',
            ],
            'privacy:metadata:audit_logs'
        );

        // Schedule recipients.
        $collection->add_database_table(
            'manireports_schedule_recipients',
            [
                'userid' => 'privacy:metadata:schedule_recipients:userid',
                'scheduleid' => 'privacy:metadata:schedule_recipients:scheduleid',
            ],
            'privacy:metadata:schedule_recipients'
        );

        // Dashboards created by users.
        $collection->add_database_table(
            'manireports_dashboards',
            [
                'name' => 'privacy:metadata:dashboards:name',
                'description' => 'privacy:metadata:dashboards:description',
                'layoutjson' => 'privacy:metadata:dashboards:layoutjson',
                'createdby' => 'privacy:metadata:dashboards:createdby',
                'timecreated' => 'privacy:metadata:dashboards:timecreated',
                'timemodified' => 'privacy:metadata:dashboards:timemodified',
            ],
            'privacy:metadata:dashboards'
        );

        // At-risk acknowledgments.
        $collection->add_database_table(
            'manireports_atrisk_ack',
            [
                'userid' => 'privacy:metadata:atrisk_ack:userid',
                'courseid' => 'privacy:metadata:atrisk_ack:courseid',
                'acknowledgedby' => 'privacy:metadata:atrisk_ack:acknowledgedby',
                'note' => 'privacy:metadata:atrisk_ack:note',
                'timeacknowledged' => 'privacy:metadata:atrisk_ack:timeacknowledged',
            ],
            'privacy:metadata:atrisk_ack'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        // ManiReports data is stored at system context level.
        $contextlist->add_system_context();

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_system) {
            return;
        }

        // Get users from time tracking.
        $sql = "SELECT DISTINCT userid FROM {manireports_usertime_sessions}";
        $userlist->add_from_sql('userid', $sql, []);

        $sql = "SELECT DISTINCT userid FROM {manireports_usertime_daily}";
        $userlist->add_from_sql('userid', $sql, []);

        // Get users who created custom reports.
        $sql = "SELECT DISTINCT createdby as userid FROM {manireports_customreports}";
        $userlist->add_from_sql('userid', $sql, []);

        // Get users from report runs.
        $sql = "SELECT DISTINCT userid FROM {manireports_report_runs}";
        $userlist->add_from_sql('userid', $sql, []);

        // Get users from audit logs.
        $sql = "SELECT DISTINCT userid FROM {manireports_audit_logs}";
        $userlist->add_from_sql('userid', $sql, []);

        // Get users from schedule recipients.
        $sql = "SELECT DISTINCT userid FROM {manireports_schedule_recipients}";
        $userlist->add_from_sql('userid', $sql, []);

        // Get users who created dashboards.
        $sql = "SELECT DISTINCT createdby as userid FROM {manireports_dashboards}";
        $userlist->add_from_sql('userid', $sql, []);

        // Get users from at-risk acknowledgments.
        $sql = "SELECT DISTINCT userid FROM {manireports_atrisk_ack}";
        $userlist->add_from_sql('userid', $sql, []);

        $sql = "SELECT DISTINCT acknowledgedby as userid FROM {manireports_atrisk_ack}";
        $userlist->add_from_sql('userid', $sql, []);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        $context = \context_system::instance();

        // Export time tracking sessions.
        $sessions = $DB->get_records('manireports_usertime_sessions', ['userid' => $userid]);
        if (!empty($sessions)) {
            $data = [];
            foreach ($sessions as $session) {
                $data[] = (object) [
                    'courseid' => $session->courseid,
                    'sessionstart' => \core_privacy\local\request\transform::datetime($session->sessionstart),
                    'lastupdated' => \core_privacy\local\request\transform::datetime($session->lastupdated),
                    'duration' => $session->duration,
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('privacy:path:timetracking', 'local_manireports'), get_string('privacy:path:sessions', 'local_manireports')],
                (object) ['sessions' => $data]
            );
        }

        // Export daily time tracking.
        $daily = $DB->get_records('manireports_usertime_daily', ['userid' => $userid]);
        if (!empty($daily)) {
            $data = [];
            foreach ($daily as $record) {
                $data[] = (object) [
                    'courseid' => $record->courseid,
                    'date' => $record->date,
                    'duration' => $record->duration,
                    'sessioncount' => $record->sessioncount,
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('privacy:path:timetracking', 'local_manireports'), get_string('privacy:path:daily', 'local_manireports')],
                (object) ['daily_time' => $data]
            );
        }

        // Export custom reports created by user.
        $reports = $DB->get_records('manireports_customreports', ['createdby' => $userid]);
        if (!empty($reports)) {
            $data = [];
            foreach ($reports as $report) {
                $data[] = (object) [
                    'name' => $report->name,
                    'description' => $report->description,
                    'type' => $report->type,
                    'timecreated' => \core_privacy\local\request\transform::datetime($report->timecreated),
                    'timemodified' => \core_privacy\local\request\transform::datetime($report->timemodified),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('privacy:path:customreports', 'local_manireports')],
                (object) ['reports' => $data]
            );
        }

        // Export report runs.
        $runs = $DB->get_records('manireports_report_runs', ['userid' => $userid]);
        if (!empty($runs)) {
            $data = [];
            foreach ($runs as $run) {
                $data[] = (object) [
                    'reportid' => $run->reportid,
                    'status' => $run->status,
                    'timestarted' => \core_privacy\local\request\transform::datetime($run->timestarted),
                    'timefinished' => $run->timefinished ? \core_privacy\local\request\transform::datetime($run->timefinished) : null,
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('privacy:path:reportruns', 'local_manireports')],
                (object) ['runs' => $data]
            );
        }

        // Export audit logs.
        $logs = $DB->get_records('manireports_audit_logs', ['userid' => $userid]);
        if (!empty($logs)) {
            $data = [];
            foreach ($logs as $log) {
                $data[] = (object) [
                    'action' => $log->action,
                    'objecttype' => $log->objecttype,
                    'objectid' => $log->objectid,
                    'timecreated' => \core_privacy\local\request\transform::datetime($log->timecreated),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('privacy:path:auditlogs', 'local_manireports')],
                (object) ['logs' => $data]
            );
        }

        // Export schedule recipients.
        $recipients = $DB->get_records('manireports_schedule_recipients', ['userid' => $userid]);
        if (!empty($recipients)) {
            $data = [];
            foreach ($recipients as $recipient) {
                $data[] = (object) [
                    'scheduleid' => $recipient->scheduleid,
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('privacy:path:schedules', 'local_manireports')],
                (object) ['recipients' => $data]
            );
        }

        // Export dashboards created by user.
        $dashboards = $DB->get_records('manireports_dashboards', ['createdby' => $userid]);
        if (!empty($dashboards)) {
            $data = [];
            foreach ($dashboards as $dashboard) {
                $data[] = (object) [
                    'name' => $dashboard->name,
                    'description' => $dashboard->description,
                    'scope' => $dashboard->scope,
                    'timecreated' => \core_privacy\local\request\transform::datetime($dashboard->timecreated),
                    'timemodified' => \core_privacy\local\request\transform::datetime($dashboard->timemodified),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('privacy:path:dashboards', 'local_manireports')],
                (object) ['dashboards' => $data]
            );
        }

        // Export at-risk acknowledgments (as subject).
        $acks = $DB->get_records('manireports_atrisk_ack', ['userid' => $userid]);
        if (!empty($acks)) {
            $data = [];
            foreach ($acks as $ack) {
                $data[] = (object) [
                    'courseid' => $ack->courseid,
                    'acknowledgedby' => $ack->acknowledgedby,
                    'note' => $ack->note,
                    'timeacknowledged' => \core_privacy\local\request\transform::datetime($ack->timeacknowledged),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('privacy:path:atrisk', 'local_manireports'), get_string('privacy:path:atrisk_subject', 'local_manireports')],
                (object) ['acknowledgments' => $data]
            );
        }

        // Export at-risk acknowledgments (as acknowledger).
        $acks = $DB->get_records('manireports_atrisk_ack', ['acknowledgedby' => $userid]);
        if (!empty($acks)) {
            $data = [];
            foreach ($acks as $ack) {
                $data[] = (object) [
                    'userid' => $ack->userid,
                    'courseid' => $ack->courseid,
                    'note' => $ack->note,
                    'timeacknowledged' => \core_privacy\local\request\transform::datetime($ack->timeacknowledged),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('privacy:path:atrisk', 'local_manireports'), get_string('privacy:path:atrisk_acknowledger', 'local_manireports')],
                (object) ['acknowledgments' => $data]
            );
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel != CONTEXT_SYSTEM) {
            return;
        }

        // Delete all time tracking data.
        $DB->delete_records('manireports_usertime_sessions');
        $DB->delete_records('manireports_usertime_daily');

        // Delete all custom reports.
        $DB->delete_records('manireports_customreports');

        // Delete all report runs.
        $DB->delete_records('manireports_report_runs');

        // Delete all audit logs.
        $DB->delete_records('manireports_audit_logs');

        // Delete all schedule recipients.
        $DB->delete_records('manireports_schedule_recipients');

        // Delete all dashboards.
        $DB->delete_records('manireports_dashboards');
        $DB->delete_records('manireports_dashboard_widgets');

        // Delete all at-risk acknowledgments.
        $DB->delete_records('manireports_atrisk_ack');
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        // Delete time tracking data.
        $DB->delete_records('manireports_usertime_sessions', ['userid' => $userid]);
        $DB->delete_records('manireports_usertime_daily', ['userid' => $userid]);

        // Delete custom reports created by user.
        $reports = $DB->get_records('manireports_customreports', ['createdby' => $userid], '', 'id');
        foreach ($reports as $report) {
            // Delete associated schedules.
            $schedules = $DB->get_records('manireports_schedules', ['reportid' => $report->id], '', 'id');
            foreach ($schedules as $schedule) {
                $DB->delete_records('manireports_schedule_recipients', ['scheduleid' => $schedule->id]);
            }
            $DB->delete_records('manireports_schedules', ['reportid' => $report->id]);
            
            // Delete report runs.
            $DB->delete_records('manireports_report_runs', ['reportid' => $report->id]);
        }
        $DB->delete_records('manireports_customreports', ['createdby' => $userid]);

        // Delete report runs by user.
        $DB->delete_records('manireports_report_runs', ['userid' => $userid]);

        // Delete audit logs.
        $DB->delete_records('manireports_audit_logs', ['userid' => $userid]);

        // Delete schedule recipients.
        $DB->delete_records('manireports_schedule_recipients', ['userid' => $userid]);

        // Delete dashboards created by user.
        $dashboards = $DB->get_records('manireports_dashboards', ['createdby' => $userid], '', 'id');
        foreach ($dashboards as $dashboard) {
            $DB->delete_records('manireports_dashboard_widgets', ['dashboardid' => $dashboard->id]);
        }
        $DB->delete_records('manireports_dashboards', ['createdby' => $userid]);

        // Delete at-risk acknowledgments.
        $DB->delete_records('manireports_atrisk_ack', ['userid' => $userid]);
        $DB->delete_records('manireports_atrisk_ack', ['acknowledgedby' => $userid]);
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();

        if ($context->contextlevel != CONTEXT_SYSTEM) {
            return;
        }

        $userids = $userlist->get_userids();

        if (empty($userids)) {
            return;
        }

        list($insql, $params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        // Delete time tracking data.
        $DB->delete_records_select('manireports_usertime_sessions', "userid $insql", $params);
        $DB->delete_records_select('manireports_usertime_daily', "userid $insql", $params);

        // Delete custom reports and associated data.
        $reports = $DB->get_records_select('manireports_customreports', "createdby $insql", $params, '', 'id');
        foreach ($reports as $report) {
            $schedules = $DB->get_records('manireports_schedules', ['reportid' => $report->id], '', 'id');
            foreach ($schedules as $schedule) {
                $DB->delete_records('manireports_schedule_recipients', ['scheduleid' => $schedule->id]);
            }
            $DB->delete_records('manireports_schedules', ['reportid' => $report->id]);
            $DB->delete_records('manireports_report_runs', ['reportid' => $report->id]);
        }
        $DB->delete_records_select('manireports_customreports', "createdby $insql", $params);

        // Delete report runs.
        $DB->delete_records_select('manireports_report_runs', "userid $insql", $params);

        // Delete audit logs.
        $DB->delete_records_select('manireports_audit_logs', "userid $insql", $params);

        // Delete schedule recipients.
        $DB->delete_records_select('manireports_schedule_recipients', "userid $insql", $params);

        // Delete dashboards and widgets.
        $dashboards = $DB->get_records_select('manireports_dashboards', "createdby $insql", $params, '', 'id');
        foreach ($dashboards as $dashboard) {
            $DB->delete_records('manireports_dashboard_widgets', ['dashboardid' => $dashboard->id]);
        }
        $DB->delete_records_select('manireports_dashboards', "createdby $insql", $params);

        // Delete at-risk acknowledgments.
        $DB->delete_records_select('manireports_atrisk_ack', "userid $insql", $params);
        $DB->delete_records_select('manireports_atrisk_ack', "acknowledgedby $insql", $params);
    }
}
