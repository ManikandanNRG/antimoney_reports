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
 * SCORM summary report.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_manireports\reports;

defined('MOODLE_INTERNAL') || die();

/**
 * SCORM summary report class.
 */
class scorm_summary extends base_report {

    /**
     * Get report name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('scormsummary', 'local_manireports');
    }

    /**
     * Get report description.
     *
     * @return string
     */
    public function get_description() {
        return get_string('scormsummary_desc', 'local_manireports');
    }

    /**
     * Get SQL query for SCORM summary report.
     *
     * @return string SQL query
     */
    protected function get_sql() {
        $sql = "SELECT CONCAT(ss.scormid, '_', ss.userid) AS uniqueid,
                       u.id AS userid,
                       u.firstname,
                       u.lastname,
                       u.email,
                       s.name AS scormname,
                       c.fullname AS coursename,
                       ss.attempts,
                       ss.completed,
                       ss.totaltime,
                       ss.score,
                       ss.lastaccess
                  FROM {manireports_scorm_summary} ss
                  JOIN {scorm} s ON s.id = ss.scormid
                  JOIN {course} c ON c.id = s.course
                  JOIN {user} u ON u.id = ss.userid
                 WHERE u.deleted = 0";

        // Add course filter if provided.
        if (!empty($this->params['courseid'])) {
            $sql .= " AND c.id = :courseid";
        }

        // Add SCORM filter if provided.
        if (!empty($this->params['scormid'])) {
            $sql .= " AND s.id = :scormid";
        }

        // Add user search filter if provided (supports username or email search).
        if (!empty($this->params['usersearch'])) {
            $sql .= " AND (u.username LIKE :usersearch OR u.email LIKE :usersearch2)";
        }

        // Add completion filter if provided.
        if (isset($this->params['completed']) && $this->params['completed'] !== '') {
            $sql .= " AND ss.completed = :completed";
        }

        $sql .= " ORDER BY c.fullname ASC, s.name ASC, u.lastname ASC, u.firstname ASC";

        return $sql;
    }

    /**
     * Get column definitions.
     *
     * @return array
     */
    protected function get_columns() {
        return array(
            'firstname' => get_string('firstname', 'moodle'),
            'lastname' => get_string('lastname', 'moodle'),
            'email' => get_string('email', 'moodle'),
            'coursename' => get_string('course', 'local_manireports'),
            'scormname' => get_string('scormname', 'local_manireports'),
            'attempts' => get_string('attempts', 'local_manireports'),
            'completed' => get_string('completed', 'moodle'),
            'totaltime' => get_string('totaltime', 'local_manireports'),
            'score' => get_string('score', 'local_manireports'),
            'lastaccess' => get_string('lastaccess', 'local_manireports')
        );
    }

    /**
     * Get filter definitions.
     *
     * @return array
     */
    public function get_filters() {
        $filters = parent::get_filters();

        $filters['courseid'] = array(
            'type' => 'course',
            'label' => get_string('course', 'local_manireports')
        );

        $filters['scormid'] = array(
            'type' => 'scorm',
            'label' => get_string('scormactivity', 'local_manireports')
        );

        $filters['usersearch'] = array(
            'type' => 'text',
            'label' => get_string('usernameoremail', 'local_manireports')
        );

        $filters['completed'] = array(
            'type' => 'select',
            'label' => get_string('completed', 'moodle'),
            'options' => array(
                '' => get_string('all'),
                '1' => get_string('yes'),
                '0' => get_string('no')
            )
        );

        return $filters;
    }

    /**
     * Apply IOMAD company filter to SQL query.
     *
     * Override parent method to handle company filtering for this specific query.
     *
     * @param string $sql Original SQL query
     * @return string Modified SQL query with company filter
     */
    protected function apply_iomad_filter($sql) {
        global $DB;

        if (!\local_manireports\api\iomad_filter::is_iomad_installed()) {
            return $sql;
        }

        $companyid = isset($this->params['companyid']) ? $this->params['companyid'] : null;

        if (is_siteadmin($this->userid) && $companyid === null) {
            return $sql;
        }

        if ($companyid !== null) {
            $companies = array($companyid);
        } else {
            $companies = \local_manireports\api\iomad_filter::get_user_companies($this->userid);
        }

        if (empty($companies)) {
            return $sql . " AND 1=0";
        }

        list($insql, $inparams) = $DB->get_in_or_equal($companies, SQL_PARAMS_NAMED, 'company');
        
        $companyfilter = " AND u.id IN (
            SELECT cu.userid 
            FROM {company_users} cu 
            WHERE cu.companyid $insql
        )";

        $this->params = array_merge($this->params, $inparams);
        $sql = str_replace('ORDER BY', $companyfilter . ' ORDER BY', $sql);

        return $sql;
    }

    /**
     * Format row for display.
     *
     * @param object $row
     * @return object
     */
    public function format_row($row) {
        // Call parent formatting first (handles timestamps).
        $row = parent::format_row($row);
        
        // Format completed as Yes/No.
        $row->completed = $row->completed ? get_string('yes') : get_string('no');

        // Format total time (seconds to hours:minutes:seconds).
        if ($row->totaltime > 0) {
            $hours = floor((float)$row->totaltime / 3600);
            $minutes = floor(((float)$row->totaltime % 3600) / 60);
            $seconds = (float)$row->totaltime % 60;
            $row->totaltime = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        } else {
            $row->totaltime = '00:00:00';
        }

        // Format score.
        if ($row->score !== null && $row->score !== '') {
            $row->score = round((float)$row->score, 2) . '%';
        } else {
            $row->score = '-';
        }

        return $row;
    }

    /**
     * Get chart data for visualization.
     *
     * @param array $data Report data
     * @return array Chart data in Chart.js format
     */
    public function get_chart_data($data) {
        // SCORM summary data doesn't have completed/incomplete/notattempted counts per activity
        // This report shows individual user attempts, not aggregated data
        // So we'll return null to skip chart rendering for this report
        return null;
    }
}
