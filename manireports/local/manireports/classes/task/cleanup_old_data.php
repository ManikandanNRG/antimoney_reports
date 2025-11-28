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
 * Cleanup old data scheduled task.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_manireports\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Cleanup old data task class.
 */
class cleanup_old_data extends \core\task\scheduled_task {

    /**
     * Get task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_cleanupolddata', 'local_manireports');
    }

    /**
     * Execute task.
     */
    public function execute() {
        global $DB;

        mtrace('Starting data cleanup task...');

        $totalcleaned = 0;

        // Clean up old audit logs.
        $auditcleaned = $this->cleanup_audit_logs();
        mtrace("Cleaned up {$auditcleaned} old audit log entries");
        $totalcleaned += $auditcleaned;

        // Clean up old report runs.
        $runscleaned = $this->cleanup_report_runs();
        mtrace("Cleaned up {$runscleaned} old report run records");
        $totalcleaned += $runscleaned;

        // Clean up expired cache.
        $cachecleaned = $this->cleanup_expired_cache();
        mtrace("Cleaned up {$cachecleaned} expired cache entries");
        $totalcleaned += $cachecleaned;

        // Clean up old session data.
        $sessionscleaned = $this->cleanup_old_sessions();
        mtrace("Cleaned up {$sessionscleaned} old session records");
        $totalcleaned += $sessionscleaned;

        // Clean up orphaned data.
        $orphanscleaned = $this->cleanup_orphaned_data();
        mtrace("Cleaned up {$orphanscleaned} orphaned records");
        $totalcleaned += $orphanscleaned;

        // Clean up old reminder jobs.
        $remindercleaned = $this->cleanup_reminder_jobs();
        mtrace("Cleaned up {$remindercleaned} old reminder jobs");
        $totalcleaned += $remindercleaned;

        mtrace("Data cleanup task completed. Total records cleaned: {$totalcleaned}");
    }

    /**
     * Clean up old audit log entries.
     *
     * @return int Number of records deleted
     */
    protected function cleanup_audit_logs() {
        global $DB;

        // Get retention period from config (default 365 days).
        $retention = get_config('local_manireports', 'auditlogretention') ?: 365;
        $cutoff = time() - ($retention * 24 * 60 * 60);

        // Delete old audit logs.
        $sql = "DELETE FROM {manireports_audit_logs}
                 WHERE timecreated < :cutoff";

        try {
            $DB->execute($sql, array('cutoff' => $cutoff));
            $count = $DB->get_field_sql(
                "SELECT COUNT(*) FROM {manireports_audit_logs} WHERE timecreated < :cutoff",
                array('cutoff' => $cutoff)
            );
            return $count ?: 0;
        } catch (\dml_exception $e) {
            mtrace('Error cleaning audit logs: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Clean up old report run records.
     *
     * @return int Number of records deleted
     */
    protected function cleanup_report_runs() {
        global $DB;

        // Get retention period from config (default 90 days).
        $retention = get_config('local_manireports', 'reportrunretention') ?: 90;
        $cutoff = time() - ($retention * 24 * 60 * 60);

        // Count records to be deleted.
        $count = $DB->count_records_select('manireports_report_runs', 'timestarted < :cutoff', array('cutoff' => $cutoff));

        // Delete old report runs.
        $DB->delete_records_select('manireports_report_runs', 'timestarted < :cutoff', array('cutoff' => $cutoff));

        return $count;
    }

    /**
     * Clean up expired cache entries.
     *
     * @return int Number of records deleted
     */
    protected function cleanup_expired_cache() {
        global $DB;

        $count = 0;
        $now = time();

        // Get all cache entries.
        $caches = $DB->get_records('manireports_cache_summary', null, '', 'id, lastgenerated, ttl');

        foreach ($caches as $cache) {
            $age = $now - $cache->lastgenerated;
            if ($age > $cache->ttl) {
                if ($DB->delete_records('manireports_cache_summary', array('id' => $cache->id))) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Clean up old session data.
     *
     * @return int Number of records deleted
     */
    protected function cleanup_old_sessions() {
        global $DB;

        // Clean up sessions older than 7 days.
        $cutoff = time() - (7 * 24 * 60 * 60);

        // Count records to be deleted.
        $count = $DB->count_records_select('manireports_usertime_sessions', 'lastupdated < :cutoff', array('cutoff' => $cutoff));

        // Delete old sessions.
        $DB->delete_records_select('manireports_usertime_sessions', 'lastupdated < :cutoff', array('cutoff' => $cutoff));

        return $count;
    }

    /**
     * Clean up orphaned data.
     *
     * @return int Number of records deleted
     */
    protected function cleanup_orphaned_data() {
        global $DB;

        $count = 0;

        // Clean up schedule recipients for deleted schedules.
        $sql = "DELETE FROM {manireports_schedule_recipients}
                 WHERE scheduleid NOT IN (SELECT id FROM {manireports_schedules})";
        
        try {
            $DB->execute($sql);
            $orphancount = $DB->count_records_sql(
                "SELECT COUNT(*) FROM {manireports_schedule_recipients} sr
                  WHERE NOT EXISTS (SELECT 1 FROM {manireports_schedules} s WHERE s.id = sr.scheduleid)"
            );
            $count += $orphancount ?: 0;
        } catch (\dml_exception $e) {
            mtrace('Error cleaning orphaned schedule recipients: ' . $e->getMessage());
        }

        // Clean up report runs for deleted reports.
        $sql = "DELETE FROM {manireports_report_runs}
                 WHERE reportid NOT IN (SELECT id FROM {manireports_customreports})
                   AND reportid > 0";
        
        try {
            $DB->execute($sql);
            $orphancount = $DB->count_records_sql(
                "SELECT COUNT(*) FROM {manireports_report_runs} rr
                  WHERE rr.reportid > 0 
                    AND NOT EXISTS (SELECT 1 FROM {manireports_customreports} cr WHERE cr.id = rr.reportid)"
            );
            $count += $orphancount ?: 0;
        } catch (\dml_exception $e) {
            mtrace('Error cleaning orphaned report runs: ' . $e->getMessage());
        }

        // Clean up time tracking data for deleted users.
        $sql = "DELETE FROM {manireports_usertime_sessions}
                 WHERE userid NOT IN (SELECT id FROM {user})";
        
        try {
            $DB->execute($sql);
            $orphancount = $DB->count_records_sql(
                "SELECT COUNT(*) FROM {manireports_usertime_sessions} ts
                  WHERE NOT EXISTS (SELECT 1 FROM {user} u WHERE u.id = ts.userid)"
            );
            $count += $orphancount ?: 0;
        } catch (\dml_exception $e) {
            mtrace('Error cleaning orphaned time sessions: ' . $e->getMessage());
        }

        $sql = "DELETE FROM {manireports_usertime_daily}
                 WHERE userid NOT IN (SELECT id FROM {user})";
        
        try {
            $DB->execute($sql);
            $orphancount = $DB->count_records_sql(
                "SELECT COUNT(*) FROM {manireports_usertime_daily} td
                  WHERE NOT EXISTS (SELECT 1 FROM {user} u WHERE u.id = td.userid)"
            );
            $count += $orphancount ?: 0;
        } catch (\dml_exception $e) {
            mtrace('Error cleaning orphaned time daily records: ' . $e->getMessage());
        }

        // Clean up orphaned dashboard widgets.
        $sql = "DELETE FROM {manireports_dashboard_widgets}
                 WHERE dashboardid NOT IN (SELECT id FROM {manireports_dashboards})";
        
        try {
            $DB->execute($sql);
            $orphancount = $DB->count_records_sql(
                "SELECT COUNT(*) FROM {manireports_dashboard_widgets} dw
                  WHERE NOT EXISTS (SELECT 1 FROM {manireports_dashboards} d WHERE d.id = dw.dashboardid)"
            );
            $count += $orphancount ?: 0;
        } catch (\dml_exception $e) {
            mtrace('Error cleaning orphaned dashboard widgets: ' . $e->getMessage());
        }

        // Clean up orphaned at-risk acknowledgments.
        $sql = "DELETE FROM {manireports_atrisk_ack}
                 WHERE userid NOT IN (SELECT id FROM {user})
                    OR acknowledgedby NOT IN (SELECT id FROM {user})";
        
        try {
            $DB->execute($sql);
            $orphancount = $DB->count_records_sql(
                "SELECT COUNT(*) FROM {manireports_atrisk_ack} aa
                  WHERE NOT EXISTS (SELECT 1 FROM {user} u WHERE u.id = aa.userid)
                     OR NOT EXISTS (SELECT 1 FROM {user} u WHERE u.id = aa.acknowledgedby)"
            );
            $count += $orphancount ?: 0;
        } catch (\dml_exception $e) {
            mtrace('Error cleaning orphaned at-risk acknowledgments: ' . $e->getMessage());
        }

        return $count;
    }

    /**
     * Clean up old reminder jobs.
     *
     * @return int Number of records deleted
     */
    protected function cleanup_reminder_jobs() {
        global $DB;

        // Retention: 90 days
        $cutoff = time() - (90 * 24 * 60 * 60);
        
        $count = $DB->count_records_select('manireports_reminder_job', 'last_attempt_ts < ?', [$cutoff]);
        $DB->delete_records_select('manireports_reminder_job', 'last_attempt_ts < ?', [$cutoff]);

        return $count;
    }
}
