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
 * User engagement report.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_manireports\reports;

defined('MOODLE_INTERNAL') || die();

/**
 * User engagement report class.
 */
class user_engagement extends base_report {

    /**
     * Get report name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('userengagement', 'local_manireports');
    }

    /**
     * Get report description.
     *
     * @return string
     */
    public function get_description() {
        return get_string('userengagement_desc', 'local_manireports');
    }

    /**
     * Get SQL query for user engagement report.
     *
     * @return string SQL query
     */
    protected function get_sql() {
        $now = time();
        $last7days = $now - (7 * 24 * 3600);
        $last30days = $now - (30 * 24 * 3600);

        $sql = "SELECT u.id AS userid,
                       u.firstname,
                       u.lastname,
                       u.email,
                       c.id AS courseid,
                       c.fullname AS coursename,
                       u.lastaccess,
                       COALESCE(SUM(CASE WHEN utd.date >= :date7 THEN utd.duration ELSE 0 END), 0) AS time_7days,
                       COALESCE(SUM(CASE WHEN utd.date >= :date30 THEN utd.duration ELSE 0 END), 0) AS time_30days,
                       COALESCE(COUNT(DISTINCT CASE WHEN utd.date >= :date7b THEN utd.date END), 0) AS active_days_7,
                       COALESCE(COUNT(DISTINCT CASE WHEN utd.date >= :date30b THEN utd.date END), 0) AS active_days_30
                  FROM {user} u
                  JOIN {user_enrolments} ue ON ue.userid = u.id
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {course} c ON c.id = e.courseid
             LEFT JOIN {manireports_time_daily} utd ON utd.userid = u.id 
                   AND utd.courseid = c.id
                 WHERE u.deleted = 0
                   AND u.suspended = 0
                   AND ue.status = 0
                   AND c.id > 1";

        // Add course filter if provided.
        if (!empty($this->params['courseid'])) {
            $sql .= " AND c.id = :courseid";
        }

        // Add user search filter if provided (supports username or email search).
        if (!empty($this->params['usersearch'])) {
            $sql .= " AND (u.username LIKE :usersearch OR u.email LIKE :usersearch2)";
        }

        $sql .= " GROUP BY u.id, u.firstname, u.lastname, u.email, c.id, c.fullname, u.lastaccess
                  ORDER BY time_30days DESC, u.lastname ASC, u.firstname ASC";

        // Add date parameters.
        $this->params['date7'] = date('Y-m-d', $last7days);
        $this->params['date30'] = date('Y-m-d', $last30days);
        $this->params['date7b'] = date('Y-m-d', $last7days);
        $this->params['date30b'] = date('Y-m-d', $last30days);

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
            'time_7days' => get_string('timespent7days', 'local_manireports'),
            'time_30days' => get_string('timespent30days', 'local_manireports'),
            'active_days_7' => get_string('activedays7', 'local_manireports'),
            'active_days_30' => get_string('activedays30', 'local_manireports'),
            'lastaccess' => get_string('lastaccess', 'moodle')
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

        $filters['usersearch'] = array(
            'type' => 'text',
            'label' => get_string('usernameoremail', 'local_manireports')
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
        $sql = str_replace('GROUP BY', $companyfilter . ' GROUP BY', $sql);

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
        
        // Format time spent (seconds to hours:minutes).
        $row->time_7days = $this->format_duration($row->time_7days);
        $row->time_30days = $this->format_duration($row->time_30days);

        // Override lastaccess formatting with 'never' for empty values.
        if (empty($row->lastaccess) || $row->lastaccess === '-') {
            $row->lastaccess = get_string('never');
        }

        return $row;
    }

    /**
     * Format duration in seconds to human-readable format.
     *
     * @param int $seconds Duration in seconds
     * @return string Formatted duration
     */
    private function format_duration($seconds) {
        if ($seconds == 0) {
            return '0h 0m';
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        return sprintf('%dh %dm', $hours, $minutes);
    }

    /**
     * Get chart data for visualization.
     *
     * @param array $data Report data
     * @return array Chart data in Chart.js format
     */
    public function get_chart_data($data) {
        $labels = array();
        $timedata = array();
        $accessdata = array();
        
        // Limit to top 10 users for readability.
        $limiteddata = array_slice($data, 0, 10);

        foreach ($limiteddata as $row) {
            $username = $row->firstname . ' ' . $row->lastname;
            if (strlen($username) > 20) {
                $username = substr($username, 0, 17) . '...';
            }
            
            $labels[] = $username;
            $timedata[] = round($row->total_time_hours, 1);
            $accessdata[] = (int)$row->total_accesses;
        }

        return array(
            'labels' => $labels,
            'datasets' => array(
                array(
                    'label' => get_string('timespenthours', 'local_manireports'),
                    'data' => $timedata,
                    'backgroundColor' => 'rgba(99, 102, 241, 0.2)',
                    'borderColor' => 'rgba(99, 102, 241, 1)',
                    'borderWidth' => 3,
                    'fill' => true,
                    'tension' => 0.4,
                    'pointRadius' => 5,
                    'pointHoverRadius' => 7,
                    'pointBackgroundColor' => 'rgba(99, 102, 241, 1)',
                    'pointBorderColor' => '#fff',
                    'pointBorderWidth' => 2
                )
            ),
            'accessdata' => $accessdata,
            'chartType' => 'line'
        );
    }
}
