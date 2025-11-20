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
 * Course progress report.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_manireports\reports;

defined('MOODLE_INTERNAL') || die();

/**
 * Course progress report class.
 */
class course_progress extends base_report {

    /**
     * Get report name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('courseprogress', 'local_manireports');
    }

    /**
     * Get report description.
     *
     * @return string
     */
    public function get_description() {
        return get_string('courseprogress_desc', 'local_manireports');
    }

    /**
     * Get SQL query for course progress report.
     *
     * @return string SQL query
     */
    protected function get_sql() {
        $sql = "SELECT CONCAT(u.id, '_', c.id) AS uniqueid,
                       u.id AS userid,
                       u.firstname,
                       u.lastname,
                       u.email,
                       c.id AS courseid,
                       c.fullname AS coursename,
                       COUNT(DISTINCT cm.id) AS total_activities,
                       COUNT(DISTINCT cmc.id) AS completed_activities,
                       CASE 
                           WHEN COUNT(DISTINCT cm.id) > 0 
                           THEN ROUND((COUNT(DISTINCT cmc.id) * 100.0 / COUNT(DISTINCT cm.id)), 2)
                           ELSE 0 
                       END AS progress_percentage,
                       cc.timecompleted
                  FROM {user} u
                  JOIN {user_enrolments} ue ON ue.userid = u.id
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {course} c ON c.id = e.courseid
             LEFT JOIN {course_modules} cm ON cm.course = c.id 
                   AND cm.completion > 0
                   AND cm.deletioninprogress = 0
             LEFT JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id 
                   AND cmc.userid = u.id
                   AND cmc.completionstate > 0
             LEFT JOIN {course_completions} cc ON cc.course = c.id 
                   AND cc.userid = u.id
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

        $sql .= " GROUP BY u.id, u.firstname, u.lastname, u.email, c.id, c.fullname, cc.timecompleted
                  ORDER BY c.fullname ASC, u.lastname ASC, u.firstname ASC";

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
            'total_activities' => get_string('totalactivities', 'local_manireports'),
            'completed_activities' => get_string('completedactivities', 'local_manireports'),
            'progress_percentage' => get_string('progresspercentage', 'local_manireports'),
            'timecompleted' => get_string('timecompleted', 'local_manireports')
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
        // Call parent formatting first (handles common timestamps).
        $row = parent::format_row($row);
        // No additional formatting needed - parent handles timecompleted.
        return $row;
    }

    /**
     * Get chart data for visualization.
     *
     * @param array $data Report data
     * @return array Chart data in Chart.js format
     */
    public function get_chart_data($data) {
        // Group users by progress ranges.
        $ranges = array(
            '0-25%' => 0,
            '26-50%' => 0,
            '51-75%' => 0,
            '76-100%' => 0
        );

        foreach ($data as $row) {
            $progress = $row->progress_percentage;
            if ($progress <= 25) {
                $ranges['0-25%']++;
            } else if ($progress <= 50) {
                $ranges['26-50%']++;
            } else if ($progress <= 75) {
                $ranges['51-75%']++;
            } else {
                $ranges['76-100%']++;
            }
        }

        return array(
            'labels' => array_keys($ranges),
            'datasets' => array(
                array(
                    'label' => get_string('numberofusers', 'local_manireports'),
                    'data' => array_values($ranges),
                    'backgroundColor' => array(
                        'rgba(239, 68, 68, 0.8)',    // Red for 0-25%
                        'rgba(245, 158, 11, 0.8)',   // Amber for 26-50%
                        'rgba(59, 130, 246, 0.8)',   // Blue for 51-75%
                        'rgba(16, 185, 129, 0.8)'    // Green for 76-100%
                    ),
                    'borderColor' => array(
                        'rgba(239, 68, 68, 1)',
                        'rgba(245, 158, 11, 1)',
                        'rgba(59, 130, 246, 1)',
                        'rgba(16, 185, 129, 1)'
                    ),
                    'borderWidth' => 2,
                    'borderRadius' => 8,
                    'borderSkipped' => false
                )
            ),
            'chartType' => 'bar'
        );
    }
}
