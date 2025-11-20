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
 * Quiz attempts report.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_manireports\reports;

defined('MOODLE_INTERNAL') || die();

/**
 * Quiz attempts report class.
 */
class quiz_attempts extends base_report {

    /**
     * Get report name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('quizattempts', 'local_manireports');
    }

    /**
     * Get report description.
     *
     * @return string
     */
    public function get_description() {
        return get_string('quizattempts_desc', 'local_manireports');
    }

    /**
     * Get SQL query for quiz attempts report.
     *
     * @return string SQL query
     */
    protected function get_sql() {
        $sql = "SELECT CONCAT(u.id, '_', q.id) AS uniqueid,
                       u.id AS userid,
                       u.firstname,
                       u.lastname,
                       u.email,
                       c.fullname AS coursename,
                       q.name AS quizname,
                       COUNT(DISTINCT qa.id) AS total_attempts,
                       COUNT(DISTINCT CASE WHEN qa.state = 'finished' THEN qa.id END) AS finished_attempts,
                       ROUND(AVG(CASE WHEN qa.state = 'finished' THEN (qa.sumgrades / q.sumgrades * 100) END), 2) AS avg_score,
                       MAX(CASE WHEN qa.state = 'finished' THEN (qa.sumgrades / q.sumgrades * 100) END) AS best_score,
                       MAX(qa.timefinish) AS last_attempt
                  FROM {user} u
                  JOIN {user_enrolments} ue ON ue.userid = u.id
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {course} c ON c.id = e.courseid
                  JOIN {quiz} q ON q.course = c.id
             LEFT JOIN {quiz_attempts} qa ON qa.quiz = q.id 
                   AND qa.userid = u.id
                 WHERE u.deleted = 0
                   AND u.suspended = 0
                   AND ue.status = 0
                   AND c.id > 1";

        // Add course filter if provided.
        if (!empty($this->params['courseid'])) {
            $sql .= " AND c.id = :courseid";
        }

        // Add quiz filter if provided.
        if (!empty($this->params['quizid'])) {
            $sql .= " AND q.id = :quizid";
        }

        // Add user search filter if provided (supports username or email search).
        if (!empty($this->params['usersearch'])) {
            $sql .= " AND (u.username LIKE :usersearch OR u.email LIKE :usersearch2)";
        }

        // Add date range filter if provided.
        if (!empty($this->params['datefrom'])) {
            $sql .= " AND qa.timefinish >= :datefrom";
        }
        if (!empty($this->params['dateto'])) {
            $sql .= " AND qa.timefinish <= :dateto";
        }

        $sql .= " GROUP BY u.id, u.firstname, u.lastname, u.email, c.fullname, q.name, q.id
                  HAVING COUNT(DISTINCT qa.id) > 0
                  ORDER BY c.fullname ASC, q.name ASC, u.lastname ASC, u.firstname ASC";

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
            'quizname' => get_string('quizname', 'local_manireports'),
            'total_attempts' => get_string('totalattempts', 'local_manireports'),
            'finished_attempts' => get_string('finishedattempts', 'local_manireports'),
            'avg_score' => get_string('averagescore', 'local_manireports'),
            'best_score' => get_string('bestscore', 'local_manireports'),
            'last_attempt' => get_string('lastattempt', 'local_manireports')
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

        $filters['quizid'] = array(
            'type' => 'quiz',
            'label' => get_string('quiz', 'local_manireports')
        );

        $filters['usersearch'] = array(
            'type' => 'text',
            'label' => get_string('usernameoremail', 'local_manireports')
        );

        $filters['datefrom'] = array(
            'type' => 'date',
            'label' => get_string('datefrom', 'local_manireports')
        );

        $filters['dateto'] = array(
            'type' => 'date',
            'label' => get_string('dateto', 'local_manireports')
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
        
        // Format scores.
        if ($row->avg_score !== null && $row->avg_score !== '') {
            $row->avg_score = round((float)$row->avg_score, 2) . '%';
        } else {
            $row->avg_score = '-';
        }

        if ($row->best_score !== null && $row->best_score !== '') {
            $row->best_score = round((float)$row->best_score, 2) . '%';
        } else {
            $row->best_score = '-';
        }

        return $row;
    }

    /**
     * Get chart configuration.
     *
     * @return array
     */
    public function get_chart_config() {
        return array(
            'type' => 'bar',
            'title' => get_string('quizattempts', 'local_manireports'),
            'xaxis' => 'quizname',
            'yaxis' => 'avg_score',
            'label' => get_string('averagescore', 'local_manireports')
        );
    }

    /**
     * Get chart data for visualization.
     *
     * @param array $data Report data
     * @return array Chart data in Chart.js format
     */
    public function get_chart_data($data) {
        $labels = array();
        $attemptsdata = array();
        $avgscoredata = array();
        
        // Limit to top 10 quizzes.
        $limiteddata = array_slice($data, 0, 10);

        foreach ($limiteddata as $row) {
            $quizname = strlen($row->quizname) > 25 
                ? substr($row->quizname, 0, 22) . '...' 
                : $row->quizname;
            
            $labels[] = $quizname;
            $attemptsdata[] = (int)$row->total_attempts;
            $avgscoredata[] = round($row->average_score, 1);
        }

        return array(
            'labels' => $labels,
            'datasets' => array(
                array(
                    'label' => get_string('attempts', 'local_manireports'),
                    'data' => $attemptsdata,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.8)',
                    'borderColor' => 'rgba(59, 130, 246, 1)',
                    'borderWidth' => 2,
                    'borderRadius' => 8,
                    'type' => 'bar',
                    'yAxisID' => 'y'
                ),
                array(
                    'label' => get_string('averagescore', 'local_manireports'),
                    'data' => $avgscoredata,
                    'backgroundColor' => 'rgba(16, 185, 129, 0.2)',
                    'borderColor' => 'rgba(16, 185, 129, 1)',
                    'borderWidth' => 3,
                    'fill' => false,
                    'tension' => 0.4,
                    'type' => 'line',
                    'yAxisID' => 'y1',
                    'pointRadius' => 5,
                    'pointHoverRadius' => 7,
                    'pointBackgroundColor' => 'rgba(16, 185, 129, 1)',
                    'pointBorderColor' => '#fff',
                    'pointBorderWidth' => 2
                )
            ),
            'chartType' => 'mixed'
        );
    }
}
