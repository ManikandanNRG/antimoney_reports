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
 * Course completion report.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_manireports\reports;

defined('MOODLE_INTERNAL') || die();

/**
 * Course completion report class.
 */
class course_completion extends base_report {

    /**
     * Get report name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('coursecompletion', 'local_manireports');
    }

    /**
     * Get report description.
     *
     * @return string
     */
    public function get_description() {
        return get_string('coursecompletion_desc', 'local_manireports');
    }

    /**
     * Get SQL query for course completion report.
     *
     * @return string SQL query
     */
    protected function get_sql() {
        $sql = "SELECT c.id,
                       c.fullname AS coursename,
                       c.shortname,
                       COUNT(DISTINCT ue.userid) AS enrolled,
                       COUNT(DISTINCT cc.userid) AS completed,
                       CASE 
                           WHEN COUNT(DISTINCT ue.userid) > 0 
                           THEN ROUND((COUNT(DISTINCT cc.userid) * 100.0 / COUNT(DISTINCT ue.userid)), 2)
                           ELSE 0 
                       END AS completion_percentage
                  FROM {course} c
                  JOIN {enrol} e ON e.courseid = c.id
                  JOIN {user_enrolments} ue ON ue.enrolid = e.id
                  JOIN {user} u ON u.id = ue.userid
             LEFT JOIN {course_completions} cc ON cc.course = c.id 
                   AND cc.userid = ue.userid 
                   AND cc.timecompleted IS NOT NULL
                 WHERE c.id > 1
                   AND u.deleted = 0
                   AND ue.status = 0";

        // Add date range filter if provided.
        if (!empty($this->params['datefrom'])) {
            $sql .= " AND ue.timecreated >= :datefrom";
        }
        if (!empty($this->params['dateto'])) {
            $sql .= " AND ue.timecreated <= :dateto";
        }

        // Add course filter if provided.
        if (!empty($this->params['courseid'])) {
            $sql .= " AND c.id = :courseid";
        }

        $sql .= " GROUP BY c.id, c.fullname, c.shortname
                  ORDER BY c.fullname ASC";

        return $sql;
    }

    /**
     * Get column definitions.
     *
     * @return array
     */
    protected function get_columns() {
        return array(
            'coursename' => get_string('course', 'local_manireports'),
            'shortname' => get_string('shortname', 'local_manireports'),
            'enrolled' => get_string('enrolled', 'local_manireports'),
            'completed' => get_string('completed', 'moodle'),
            'completion_percentage' => get_string('completionpercentage', 'local_manireports')
        );
    }

    /**
     * Get default parameters.
     *
     * @return array
     */
    public function get_default_params() {
        return array(
            'datefrom' => strtotime('-30 days'),
            'dateto' => time()
        );
    }

    /**
     * Get filter definitions.
     *
     * @return array
     */
    public function get_filters() {
        $filters = parent::get_filters();

        $filters['datefrom'] = array(
            'type' => 'date',
            'label' => get_string('datefrom', 'local_manireports')
        );

        $filters['dateto'] = array(
            'type' => 'date',
            'label' => get_string('dateto', 'local_manireports')
        );

        $filters['courseid'] = array(
            'type' => 'course',
            'label' => get_string('course', 'local_manireports')
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

        // Get company ID from parameters if specified.
        $companyid = isset($this->params['companyid']) ? $this->params['companyid'] : null;

        // Site admins can see all data unless specific company is requested.
        if (is_siteadmin($this->userid) && $companyid === null) {
            return $sql;
        }

        // Get user's companies or use specific company.
        if ($companyid !== null) {
            $companies = array($companyid);
        } else {
            $companies = \local_manireports\api\iomad_filter::get_user_companies($this->userid);
        }

        // If no companies found, return query that returns no results.
        if (empty($companies)) {
            return $sql . " AND 1=0";
        }

        // Build company filter clause for user enrolments.
        list($insql, $inparams) = $DB->get_in_or_equal($companies, SQL_PARAMS_NAMED, 'company');
        
        // Add the company filter to the WHERE clause.
        // Filter users by company membership.
        $companyfilter = " AND u.id IN (
            SELECT cu.userid 
            FROM {company_users} cu 
            WHERE cu.companyid $insql
        )";

        // Merge the IN parameters into the main params array.
        $this->params = array_merge($this->params, $inparams);

        // Add the filter before GROUP BY.
        $sql = str_replace('GROUP BY', $companyfilter . ' GROUP BY', $sql);

        return $sql;
    }

    /**
     * Get chart configuration.
     *
     * @return array
     */
    public function get_chart_config() {
        return array(
            'type' => 'bar',
            'title' => get_string('coursecompletion', 'local_manireports'),
            'xaxis' => 'coursename',
            'yaxis' => 'completion_percentage',
            'label' => get_string('completionpercentage', 'local_manireports')
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
        $completiondata = array();
        $enrolleddata = array();
        $completeddata = array();
        $backgroundcolors = array();
        $bordercolors = array();

        // Limit to top 10 courses for readability.
        $limiteddata = array_slice($data, 0, 10);

        // Premium gradient color palette.
        $colorpalette = array(
            array('bg' => 'rgba(99, 102, 241, 0.8)', 'border' => 'rgba(99, 102, 241, 1)'),      // Indigo
            array('bg' => 'rgba(59, 130, 246, 0.8)', 'border' => 'rgba(59, 130, 246, 1)'),      // Blue
            array('bg' => 'rgba(16, 185, 129, 0.8)', 'border' => 'rgba(16, 185, 129, 1)'),      // Green
            array('bg' => 'rgba(245, 158, 11, 0.8)', 'border' => 'rgba(245, 158, 11, 1)'),      // Amber
            array('bg' => 'rgba(239, 68, 68, 0.8)', 'border' => 'rgba(239, 68, 68, 1)'),        // Red
            array('bg' => 'rgba(168, 85, 247, 0.8)', 'border' => 'rgba(168, 85, 247, 1)'),      // Purple
            array('bg' => 'rgba(236, 72, 153, 0.8)', 'border' => 'rgba(236, 72, 153, 1)'),      // Pink
            array('bg' => 'rgba(20, 184, 166, 0.8)', 'border' => 'rgba(20, 184, 166, 1)'),      // Teal
            array('bg' => 'rgba(251, 146, 60, 0.8)', 'border' => 'rgba(251, 146, 60, 1)'),      // Orange
            array('bg' => 'rgba(14, 165, 233, 0.8)', 'border' => 'rgba(14, 165, 233, 1)')       // Sky
        );

        $colorindex = 0;
        foreach ($limiteddata as $row) {
            // Truncate long course names for labels.
            $coursename = strlen($row->coursename) > 25 
                ? substr($row->coursename, 0, 22) . '...' 
                : $row->coursename;
            
            $labels[] = $coursename;
            $completiondata[] = round($row->completion_percentage, 1);
            $enrolleddata[] = (int)$row->enrolled;
            $completeddata[] = (int)$row->completed;
            
            // Assign colors from palette.
            $color = $colorpalette[$colorindex % count($colorpalette)];
            $backgroundcolors[] = $color['bg'];
            $bordercolors[] = $color['border'];
            $colorindex++;
        }

        return array(
            'labels' => $labels,
            'datasets' => array(
                array(
                    'label' => get_string('completionpercentage', 'local_manireports'),
                    'data' => $completiondata,
                    'backgroundColor' => $backgroundcolors,
                    'borderColor' => $bordercolors,
                    'borderWidth' => 2,
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                    'hoverBackgroundColor' => $bordercolors,
                    'hoverBorderWidth' => 3
                )
            ),
            'enrolleddata' => $enrolleddata,
            'completeddata' => $completeddata
        );
    }
}
