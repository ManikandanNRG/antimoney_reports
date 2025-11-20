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
 * SCORM summary aggregation scheduled task.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_manireports\task;

defined('MOODLE_INTERNAL') || die();

/**
 * SCORM summary aggregation task class.
 */
class scorm_summary extends \core\task\scheduled_task {

    /**
     * Get task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_scormsummary', 'local_manireports');
    }

    /**
     * Execute task.
     */
    public function execute() {
        global $DB;

        mtrace('Starting SCORM summary aggregation task...');

        // Check if SCORM module exists.
        if (!$DB->get_manager()->table_exists('scorm')) {
            mtrace('SCORM module not installed, skipping aggregation');
            return;
        }

        // Get timestamp of last aggregation.
        $lastrun = get_config('local_manireports', 'scorm_last_aggregation');
        if (!$lastrun) {
            $lastrun = 0;
        }

        $now = time();
        $processed = 0;

        // Get all SCORM activities.
        $scorms = $DB->get_records('scorm', null, '', 'id, course');

        foreach ($scorms as $scorm) {
            $count = $this->aggregate_scorm($scorm->id, $lastrun);
            $processed += $count;
        }

        // Update last run timestamp.
        set_config('scorm_last_aggregation', $now, 'local_manireports');

        mtrace("SCORM summary aggregation completed. Processed {$processed} user-SCORM combinations");
    }

    /**
     * Aggregate SCORM data for a specific SCORM activity.
     *
     * @param int $scormid SCORM ID
     * @param int $since Timestamp to aggregate from
     * @return int Number of records processed
     */
    protected function aggregate_scorm($scormid, $since) {
        global $DB;

        // Get all users who have attempted this SCORM since last run.
        $sql = "SELECT DISTINCT userid
                  FROM {scorm_scoes_track}
                 WHERE scormid = :scormid
                   AND timemodified > :since";

        $users = $DB->get_fieldset_sql($sql, array('scormid' => $scormid, 'since' => $since));

        $count = 0;
        foreach ($users as $userid) {
            if ($this->aggregate_user_scorm($scormid, $userid)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Aggregate SCORM data for a specific user and SCORM.
     *
     * @param int $scormid SCORM ID
     * @param int $userid User ID
     * @return bool True on success
     */
    protected function aggregate_user_scorm($scormid, $userid) {
        global $DB;

        // Get all attempts for this user/SCORM.
        $sql = "SELECT attempt, 
                       MAX(CASE WHEN element = 'cmi.core.lesson_status' THEN value END) as status,
                       MAX(CASE WHEN element = 'cmi.core.score.raw' THEN value END) as score,
                       MAX(CASE WHEN element = 'cmi.core.total_time' THEN value END) as totaltime,
                       MAX(timemodified) as lastaccess
                  FROM {scorm_scoes_track}
                 WHERE scormid = :scormid
                   AND userid = :userid
                 GROUP BY attempt";

        $attempts = $DB->get_records_sql($sql, array('scormid' => $scormid, 'userid' => $userid));

        if (empty($attempts)) {
            return false;
        }

        // Calculate summary statistics.
        $totalattempts = count($attempts);
        $completed = 0;
        $totalscore = 0;
        $scorecount = 0;
        $totaltime = 0;
        $lastaccess = 0;

        foreach ($attempts as $attempt) {
            // Check completion.
            if (in_array($attempt->status, array('completed', 'passed'))) {
                $completed = 1;
            }

            // Sum scores.
            if ($attempt->score !== null && is_numeric($attempt->score)) {
                $totalscore += floatval($attempt->score);
                $scorecount++;
            }

            // Sum time (convert SCORM time format to seconds).
            if ($attempt->totaltime) {
                $totaltime += $this->scorm_time_to_seconds($attempt->totaltime);
            }

            // Track last access.
            if ($attempt->lastaccess > $lastaccess) {
                $lastaccess = $attempt->lastaccess;
            }
        }

        // Calculate average score.
        $avgscore = $scorecount > 0 ? ($totalscore / $scorecount) : null;

        // Check if summary record exists.
        $summary = $DB->get_record('manireports_scorm_summary', array(
            'scormid' => $scormid,
            'userid' => $userid
        ));

        if ($summary) {
            // Update existing record.
            $summary->attempts = $totalattempts;
            $summary->completed = $completed;
            $summary->totaltime = $totaltime;
            $summary->score = $avgscore;
            $summary->lastaccess = $lastaccess;
            $summary->lastupdated = time();
            $DB->update_record('manireports_scorm_summary', $summary);
        } else {
            // Create new record.
            $record = new \stdClass();
            $record->scormid = $scormid;
            $record->userid = $userid;
            $record->attempts = $totalattempts;
            $record->completed = $completed;
            $record->totaltime = $totaltime;
            $record->score = $avgscore;
            $record->lastaccess = $lastaccess;
            $record->lastupdated = time();
            $DB->insert_record('manireports_scorm_summary', $record);
        }

        return true;
    }

    /**
     * Convert SCORM time format to seconds.
     *
     * SCORM time format: HH:MM:SS or HH:MM:SS.SS
     *
     * @param string $scormtime SCORM time string
     * @return int Time in seconds
     */
    protected function scorm_time_to_seconds($scormtime) {
        if (empty($scormtime)) {
            return 0;
        }

        // Handle different SCORM time formats.
        if (preg_match('/^(\d+):(\d+):(\d+)/', $scormtime, $matches)) {
            $hours = intval($matches[1]);
            $minutes = intval($matches[2]);
            $seconds = intval($matches[3]);
            return ($hours * 3600) + ($minutes * 60) + $seconds;
        }

        // If it's already a number, return it.
        if (is_numeric($scormtime)) {
            return intval($scormtime);
        }

        return 0;
    }
}
