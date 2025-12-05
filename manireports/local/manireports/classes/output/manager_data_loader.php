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
 * Manager Data Loader - Company-scoped data filtering
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_manireports\output;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/dashboard_data_loader.php');

/**
 * Manager Data Loader class.
 * 
 * Extends dashboard_data_loader to filter all data by company ID.
 * Managers only see data for users in their company.
 */
class manager_data_loader extends dashboard_data_loader {

    /** @var int Company ID for filtering */
    protected $companyid;

    /**
     * Constructor.
     *
     * @param int $userid User ID
     * @param int $companyid Company ID for filtering
     * @param int $startdate Optional start timestamp
     * @param int $enddate Optional end timestamp
     */
    public function __construct($userid, $companyid, $startdate = 0, $enddate = 0) {
        parent::__construct($userid, $startdate, $enddate);
        $this->companyid = $companyid;
    }

    /**
     * Get Manager KPIs (Company-scoped).
     *
     * @return array KPI data
     */
    public function get_admin_kpis() {
        global $DB;

        if (!$this->companyid) {
            return ['users' => 0, 'courses' => 0, 'companies' => 0, 'completion_rate' => 0];
        }

        // Company Users (active)
        $company_users = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT cu.userid)
             FROM {company_users} cu
             JOIN {user} u ON u.id = cu.userid
             WHERE cu.companyid = :companyid AND u.deleted = 0 AND u.suspended = 0",
            ['companyid' => $this->companyid]
        );

        // Active Users (logged in recently)
        $active_users = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT cu.userid)
             FROM {company_users} cu
             JOIN {user} u ON u.id = cu.userid
             WHERE cu.companyid = :companyid 
               AND u.deleted = 0 
               AND u.suspended = 0
               AND u.lastaccess > :lastweek",
            ['companyid' => $this->companyid, 'lastweek' => time() - (7 * 24 * 3600)]
        );

        // Company Courses
        $company_courses = $DB->count_records('company_course', ['companyid' => $this->companyid]);

        // Completion Rate (company users only)
        $total_enrollments = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT ue.id)
             FROM {company_users} cu
             JOIN {user_enrolments} ue ON ue.userid = cu.userid
             JOIN {enrol} e ON e.id = ue.enrolid
             WHERE cu.companyid = :companyid AND ue.status = 0",
            ['companyid' => $this->companyid]
        );

        $total_completions = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT cc.id)
             FROM {company_users} cu
             JOIN {course_completions} cc ON cc.userid = cu.userid
             WHERE cu.companyid = :companyid AND cc.timecompleted > 0",
            ['companyid' => $this->companyid]
        );

        $completion_rate = 0;
        if ($total_enrollments > 0) {
            $completion_rate = round(($total_completions / $total_enrollments) * 100, 1);
        }

        return [
            'users' => $company_users,
            'courses' => $company_courses,
            'companies' => 1, // Manager sees only their company
            'completion_rate' => $completion_rate,
            'active_users' => $active_users
        ];
    }

    /**
     * Get Company Analytics (filtered to show only manager's company).
     *
     * @param int $limit Number of records
     * @param string $search Search term
     * @return array Company data
     */
    public function get_company_analytics($limit = 5, $search = '') {
        global $DB;

        if (!$this->companyid) {
            return [];
        }

        // Manager only sees their own company
        $sql = "SELECT c.id, c.name, c.shortname,
                       (SELECT COUNT(*) FROM {company_users} cu WHERE cu.companyid = c.id) as users,
                       (SELECT COUNT(*) FROM {company_course} cc WHERE cc.companyid = c.id) as courses,
                       (SELECT COUNT(DISTINCT ue.id) 
                        FROM {company_users} cu2
                        JOIN {user_enrolments} ue ON ue.userid = cu2.userid
                        WHERE cu2.companyid = c.id AND ue.status = 0) as enrolled,
                       (SELECT COUNT(DISTINCT cc2.userid)
                        FROM {company_users} cu3
                        JOIN {course_completions} cc2 ON cc2.userid = cu3.userid
                        WHERE cu3.companyid = c.id AND cc2.timecompleted > 0) as completed
                  FROM {company} c
                 WHERE c.id = :companyid";

        try {
            $companies = $DB->get_records_sql($sql, ['companyid' => $this->companyid]);
        } catch (\Exception $e) {
            return [];
        }

        $rows = [];
        foreach ($companies as $company) {
            $completion_rate = ($company->enrolled > 0) ? round(($company->completed / $company->enrolled) * 100) : 0;

            $rows[] = [
                'id' => $company->id,
                'name' => $company->name,
                'courses' => $company->courses,
                'users' => $company->users,
                'enrolled' => $company->enrolled,
                'completed' => $company->completed,
                'completion_rate' => $completion_rate
            ];
        }

        return $rows;
    }

    /**
     * Get Top Courses Analytics (company-filtered).
     *
     * @param int $limit Number of records
     * @return array Course data
     */
    public function get_top_courses_analytics($limit = 10) {
        global $DB;

        if (!$this->companyid) {
            return [];
        }

        // Only courses assigned to this company
        $sql = "SELECT c.id, c.fullname, c.shortname, c.startdate, c.visible,
                       COUNT(DISTINCT ue.userid) as enrolled,
                       COUNT(DISTINCT cc.userid) as completed
                  FROM {course} c
                  JOIN {company_course} compc ON compc.courseid = c.id
                  JOIN {enrol} e ON e.courseid = c.id
                  JOIN {user_enrolments} ue ON ue.enrolid = e.id
                  JOIN {company_users} cu ON cu.userid = ue.userid
             LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = ue.userid AND cc.timecompleted > 0
                 WHERE compc.companyid = :companyid AND cu.companyid = :companyid2 AND c.id > 1
              GROUP BY c.id, c.fullname, c.shortname, c.startdate, c.visible
              ORDER BY enrolled DESC";

        try {
            $courses = $DB->get_records_sql($sql, ['companyid' => $this->companyid, 'companyid2' => $this->companyid], 0, $limit);
        } catch (\Exception $e) {
            return parent::get_top_courses_analytics($limit); // Fallback to parent
        }

        $rows = [];
        foreach ($courses as $course) {
            $progress = ($course->enrolled > 0) ? round(($course->completed / $course->enrolled) * 100) : 0;

            $status = 'Active';
            $status_class = 'status-active';

            if ($course->visible == 0) {
                $status = 'Retired';
                $status_class = 'status-retired';
            } elseif ($course->startdate > time()) {
                $status = 'Upcoming';
                $status_class = 'status-upcoming';
            }

            $rows[] = [
                'id' => $course->id,
                'fullname' => $course->fullname,
                'shortname' => $course->shortname,
                'enrolled' => $course->enrolled,
                'completed' => $course->completed,
                'progress' => $progress,
                'status' => $status,
                'status_class' => $status_class
            ];
        }

        return $rows;
    }

    /**
     * Get company users (new method for manager dashboard).
     *
     * @param int $limit Number of records
     * @return array User data
     */
    public function get_company_users($limit = 20) {
        global $DB;

        if (!$this->companyid) {
            return [];
        }

        $sql = "SELECT u.id, u.firstname, u.lastname, u.email, u.lastaccess,
                       COUNT(DISTINCT ue.id) as enrollments,
                       COUNT(DISTINCT cc.id) as completions
                  FROM {user} u
                  JOIN {company_users} cu ON cu.userid = u.id
             LEFT JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
             LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.timecompleted > 0
                 WHERE cu.companyid = :companyid AND u.deleted = 0 AND u.suspended = 0
              GROUP BY u.id, u.firstname, u.lastname, u.email, u.lastaccess
              ORDER BY u.lastname, u.firstname";

        try {
            $users = $DB->get_records_sql($sql, ['companyid' => $this->companyid], 0, $limit);
        } catch (\Exception $e) {
            return [];
        }

        $rows = [];
        foreach ($users as $user) {
            $rows[] = [
                'id' => $user->id,
                'fullname' => fullname($user),
                'email' => $user->email,
                'lastaccess' => $user->lastaccess ? userdate($user->lastaccess) : 'Never',
                'enrollments' => $user->enrollments,
                'completions' => $user->completions
            ];
        }

        return $rows;
    }
}
