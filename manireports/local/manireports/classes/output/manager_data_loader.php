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
                       (SELECT COUNT(DISTINCT cu_act.userid) 
                        FROM {company_users} cu_act
                        JOIN {user} u_act ON u_act.id = cu_act.userid
                        WHERE cu_act.companyid = c.id AND u_act.lastaccess > :lastweek) as active_users,
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
            $companies = $DB->get_records_sql($sql, ['companyid' => $this->companyid, 'lastweek' => time() - (7 * 24 * 3600)]);
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
                'active_users' => $company->active_users,
                'enrolled' => $company->enrolled,
                'completed' => $company->completed,
                'completion_rate' => $completion_rate,
                'time' => '0h 0m'
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
        // Only courses assigned to this company
        $sql = "SELECT c.id, c.fullname, c.shortname, c.startdate, c.visible,
                       (SELECT name FROM {course_categories} WHERE id = c.category) as category_name,
                       COUNT(DISTINCT ue.userid) as enrolled,
                       COUNT(DISTINCT cc.userid) as completed,
                       AVG(CASE WHEN cc.timecompleted > 0 THEN (cc.timecompleted - cc.timeenrolled) ELSE NULL END) as avg_duration
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
                'category' => $course->category_name,
                'enrolled' => $course->enrolled,
                'completed' => $course->completed,
                'progress' => $progress,
                'avg_time' => ($course->avg_duration > 0) ? round($course->avg_duration / 3600, 1) . 'h' : '-',
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
    /**
     * Get Comprehensive Course List - Scoped for Manager.
     */
    public function get_comprehensive_course_list($limit = 20, $search = '', $category = 0) {
        global $DB;
        
        if (!$this->companyid) {
            return [];
        }

        $params = ['companyid' => $this->companyid];
        $sql_where = "compc.companyid = :companyid"; // Filter by company course

        if (!empty($search)) {
            $sql_where .= " AND (c.fullname LIKE :search OR c.shortname LIKE :search2)";
            $params['search'] = '%' . $search . '%';
            $params['search2'] = '%' . $search . '%';
        }
        
        if ($category > 0) {
            $sql_where .= " AND c.category = :category";
            $params['category'] = $category;
        }

        $sql = "SELECT c.id, c.fullname, c.shortname, c.startdate, c.visible, cat.name as category_name,
                       COUNT(DISTINCT ue.userid) as enrolled,
                       COUNT(DISTINCT cc.userid) as completed,
                       AVG(CASE WHEN cc.timecompleted > 0 THEN (cc.timecompleted - cc.timeenrolled) ELSE NULL END) as avg_duration
                  FROM {course} c
                  JOIN {company_course} compc ON compc.courseid = c.id
                  JOIN {course_categories} cat ON cat.id = c.category
                  JOIN {enrol} e ON e.courseid = c.id
                  JOIN {user_enrolments} ue ON ue.enrolid = e.id
                  JOIN {company_users} cu ON cu.userid = ue.userid AND cu.companyid = :companyid2
             LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = ue.userid AND cc.timecompleted > 0
                 WHERE $sql_where
              GROUP BY c.id, c.fullname, c.shortname, c.startdate, c.visible, cat.name
              ORDER BY enrolled DESC";

        $params['companyid2'] = $this->companyid; // For company_users join

        try {
            $courses = $DB->get_records_sql($sql, $params, 0, $limit);
        } catch (\Exception $e) {
            return [];
        }

        $rows = [];
        foreach ($courses as $course) {
            $progress = ($course->enrolled > 0) ? round(($course->completed / $course->enrolled) * 100) : 0;
            $status = ($course->visible == 1) ? 'Active' : 'Retired';
            $status_class = ($course->visible == 1) ? 'status-active' : 'status-retired';

            if ($course->startdate > time()) {
                $status = 'Upcoming';
                $status_class = 'status-upcoming';
            }

            $rows[] = [
                'id' => $course->id,
                'fullname' => $course->fullname,
                'category' => $course->category_name,
                'enrolled' => $course->enrolled,
                'completed' => $course->completed,
                'progress' => $progress,
                'avg_time' => ($course->avg_duration > 0) ? round($course->avg_duration / 3600, 1) . 'h' : '-',
                'status' => $status,
                'status_class' => $status_class
            ];
        }

        return $rows;
    }

    /**
     * Get Course Enrollment Trends - Scoped for Manager.
     */
    public function get_course_enrollment_trends($search = '', $category = 0) {
        global $DB;
        
        if (!$this->companyid) {
            return ['labels' => [], 'data' => []];
        }
        
        $months = [];
        $data = [];
        
        for ($i = 5; $i >= 0; $i--) {
            $timestamp = strtotime("-$i months");
            $month_start = strtotime("first day of this month 00:00:00", $timestamp);
            $month_end = strtotime("last day of this month 23:59:59", $timestamp);
            
            $months[] = date('M', $timestamp);
            
            // Build Query
            $params = ['companyid' => $this->companyid, 'companyid2' => $this->companyid, 'start' => $month_start, 'end' => $month_end];
            $sql_where = "compc.companyid = :companyid AND cu.companyid = :companyid2 AND ue.timecreated >= :start AND ue.timecreated <= :end";
            
            if (!empty($search)) {
                $sql_where .= " AND (c.fullname LIKE :search OR c.shortname LIKE :search2)";
                $params['search'] = '%' . $search . '%';
                $params['search2'] = '%' . $search . '%';
            }

            if ($category > 0) {
                $sql_where .= " AND c.category = :category";
                $params['category'] = $category;
            }

            $sql = "SELECT COUNT(ue.id) 
                    FROM {user_enrolments} ue
                    JOIN {enrol} e ON e.id = ue.enrolid
                    JOIN {course} c ON c.id = e.courseid
                    JOIN {company_course} compc ON compc.courseid = c.id
                    JOIN {company_users} cu ON cu.userid = ue.userid
                    WHERE $sql_where";

            $data[] = $DB->count_records_sql($sql, $params);
        }

        return [
            'labels' => $months,
            'data' => $data
        ];
    }
    
    /**
     * Get Category Distribution - Scoped for Manager.
     */
    public function get_category_distribution() {
        global $DB;
        
        if (!$this->companyid) {
            return ['labels' => [], 'data' => []];
        }

        $sql = "SELECT cat.name, COUNT(c.id) as count
                FROM {course} c
                JOIN {company_course} compc ON compc.courseid = c.id
                JOIN {course_categories} cat ON cat.id = c.category
                WHERE compc.companyid = :companyid
                GROUP BY cat.name";
                
        $records = $DB->get_records_sql($sql, ['companyid' => $this->companyid]);
        
        $labels = [];
        $data = [];
        
        foreach ($records as $rec) {
            $labels[] = $rec->name;
            $data[] = $rec->count;
        }
        
        return [
            'labels' => $labels,
            'data' => $data
        ];
    }
    /**
     * Get Company Tab Metrics - Scoped for Manager.
     */
    public function get_company_tab_metrics($search = '') {
        global $DB;
        
        if (!$this->companyid) {
            return ['total_companies' => 0, 'total_users' => 0, 'avg_completion' => 0, 'assigned_courses' => 0];
        }

        // 1. Total Companies (Always 1 for manager view)
        $total_companies = 1;

        // 2. Total Company Users
        $total_users = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT userid) FROM {company_users} WHERE companyid = :companyid",
            ['companyid' => $this->companyid]
        );

        // 3. Assigned Courses
        $assigned_courses = $DB->count_records('company_course', ['companyid' => $this->companyid]);

        // 4. Avg Completion
        $completion_rate = 0;
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

        if ($total_enrollments > 0) {
            $completion_rate = round(($total_completions / $total_enrollments) * 100, 1);
        }

        return [
            'total_companies' => $total_companies,
            'total_users' => $total_users,
            'avg_completion' => $completion_rate,
            'assigned_courses' => $assigned_courses
        ];
    }

    /**
     * Get Company Distribution Chart - Scoped for Manager.
     * Shows Department distribution instead of Company distribution.
     */
    public function get_company_distribution_chart() {
        global $DB;

        if (!$this->companyid) {
            return ['labels' => [], 'data' => []];
        }

        // Show departments within the company
        if ($DB->get_manager()->table_exists('company_departments')) {
            $sql = "SELECT d.name, COUNT(cu.id) as count
                    FROM {company_departments} d
                    LEFT JOIN {company_users} cu ON cu.departmentid = d.id AND cu.companyid = :companyid
                    WHERE d.companyid = :companyid2
                    GROUP BY d.name";
            $records = $DB->get_records_sql($sql, ['companyid' => $this->companyid, 'companyid2' => $this->companyid]);
        } else {
            return ['labels' => ['My Company'], 'data' => [1]];
        }

        $labels = [];
        $data = [];
        foreach ($records as $rec) {
            $labels[] = $rec->name;
            $data[] = $rec->count;
        }

        return [
            'labels' => $labels,
            'data' => $data
        ];
    }

    /**
     * Get Company Performance Chart - Scoped for Manager.
     * Shows just their company performance (flat bar) or maybe top courses?
     * Let's show Top 5 Courses completion rates for the company.
     */
    public function get_company_performance_chart() {
        global $DB;

        if (!$this->companyid) {
            return ['labels' => [], 'data' => []];
        }

        $sql = "SELECT c.shortname, 
                       COUNT(DISTINCT ue.userid) as enrolled,
                       COUNT(DISTINCT cc.userid) as completed
                  FROM {course} c
                  JOIN {company_course} compc ON compc.courseid = c.id
                  JOIN {enrol} e ON e.courseid = c.id
                  JOIN {user_enrolments} ue ON ue.enrolid = e.id
                  JOIN {company_users} cu ON cu.userid = ue.userid AND cu.companyid = :companyid
             LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = ue.userid AND cc.timecompleted > 0
                 WHERE compc.companyid = :companyid2
              GROUP BY c.shortname
              ORDER BY enrolled DESC";
        
        $records = $DB->get_records_sql($sql, ['companyid' => $this->companyid, 'companyid2' => $this->companyid], 0, 5);

        $labels = [];
        $data = [];

        foreach ($records as $rec) {
            $labels[] = $rec->shortname;
            $data[] = ($rec->enrolled > 0) ? round(($rec->completed / $rec->enrolled) * 100, 1) : 0;
        }

        return [
            'labels' => $labels,
            'data' => $data
        ];
    }

    /**
     * Get Users Tab Metrics - Scoped for Manager.
     */
    public function get_users_tab_metrics() {
        global $DB;

        if (!$this->companyid) {
            return ['total_users' => 0, 'active_today' => 0, 'suspended_users' => 0, 'new_users' => 0];
        }

        // 1. Total Users (Company Scope)
        $total_users = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT userid) FROM {company_users} WHERE companyid = :companyid", 
            ['companyid' => $this->companyid]
        );

        // 2. Active Today (Company Scope)
        $today_start = strtotime("today midnight");
        $active_today = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT cu.userid) 
             FROM {company_users} cu
             JOIN {user} u ON u.id = cu.userid
             WHERE cu.companyid = :companyid AND u.lastaccess >= :today",
            ['companyid' => $this->companyid, 'today' => $today_start]
        );

        // 3. Suspended Users (Company Scope)
        $suspended_users = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT cu.userid) 
             FROM {company_users} cu
             JOIN {user} u ON u.id = cu.userid
             WHERE cu.companyid = :companyid AND (u.suspended = 1 OR cu.suspended = 1)", 
            ['companyid' => $this->companyid]
        );

        // 4. New Users (Last 30 Days) (Company Scope)
        $thirty_days_ago = time() - (30 * 24 * 3600);
        $new_users = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT cu.userid) 
             FROM {company_users} cu
             JOIN {user} u ON u.id = cu.userid
             WHERE cu.companyid = :companyid AND u.timecreated >= :window",
            ['companyid' => $this->companyid, 'window' => $thirty_days_ago]
        );

        return [
            'total_users' => $total_users,
            'active_today' => $active_today,
            'suspended_users' => $suspended_users,
            'new_users' => $new_users
        ];
    }

    /**
     * Get Courses Tab Metrics - Scoped for Manager.
     */
    public function get_courses_tab_metrics($search = '', $category = 0) {
        global $DB;

        if (!$this->companyid) {
            return ['active_courses' => 0, 'total_enrollments' => 0, 'avg_completion' => 0, 'certificates' => 0];
        }

        $params = ['companyid' => $this->companyid];
        $sql_where = "compc.companyid = :companyid";

        if (!empty($search)) {
            $sql_where .= " AND (c.fullname LIKE :search OR c.shortname LIKE :search2)";
            $params['search'] = '%' . $search . '%';
            $params['search2'] = '%' . $search . '%';
        }

        if ($category > 0) {
            $sql_where .= " AND c.category = :category";
            $params['category'] = $category;
        }

        // 1. Active Courses (assigned to company)
        $active_courses = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT c.id) 
             FROM {course} c 
             JOIN {company_course} compc ON compc.courseid = c.id 
             WHERE $sql_where AND c.visible = 1", 
            $params
        );

        // 2. Total Enrollments (Company Users in Company Courses)
        // We only care about enrollments of OUR users in ANY course (or limited to company courses?)
        // Standard IOMAD logic: Manager sees their users' progress.
        $sql_enrol = "SELECT COUNT(ue.id) 
                      FROM {user_enrolments} ue
                      JOIN {enrol} e ON e.id = ue.enrolid
                      JOIN {course} c ON c.id = e.courseid
                      JOIN {company_users} cu ON cu.userid = ue.userid
                      JOIN {company_course} compc ON compc.courseid = c.id AND compc.companyid = cu.companyid
                      WHERE $sql_where AND cu.companyid = :companyid2 AND ue.status = 0";
        
        $params['companyid2'] = $this->companyid;
        $total_enrollments = $DB->count_records_sql($sql_enrol, $params);

        // 3. Avg Completion
        $sql_compl = "SELECT COUNT(cc.id) 
                      FROM {course_completions} cc 
                      JOIN {course} c ON c.id = cc.course
                      JOIN {company_users} cu ON cu.userid = cc.userid
                      JOIN {company_course} compc ON compc.courseid = c.id AND compc.companyid = cu.companyid
                      WHERE $sql_where AND cu.companyid = :companyid3 AND cc.timecompleted > 0";

        $params['companyid3'] = $this->companyid;
        $total_completions = $DB->count_records_sql($sql_compl, $params);

        $avg_completion = ($total_enrollments > 0) ? round(($total_completions / $total_enrollments) * 100, 1) : 0;

        // 4. Certificates (Scoped to company users)
        $certificates = 0;
        if ($DB->get_manager()->table_exists('certificate_issues')) {
             $sql_cert = "SELECT COUNT(ci.id) 
                          FROM {certificate_issues} ci
                          JOIN {company_users} cu ON cu.userid = ci.userid
                          WHERE cu.companyid = :companyid4";
             $certificates = $DB->count_records_sql($sql_cert, ['companyid4' => $this->companyid]);
        } elseif ($DB->get_manager()->table_exists('simplecertificate_issues')) {
             $sql_cert = "SELECT COUNT(ci.id) 
                          FROM {simplecertificate_issues} ci
                          JOIN {company_users} cu ON cu.userid = ci.userid
                          WHERE cu.companyid = :companyid4";
             $certificates = $DB->count_records_sql($sql_cert, ['companyid4' => $this->companyid]);
        }

        return [
            'active_courses' => $active_courses,
            'total_enrollments' => $total_enrollments,
            'avg_completion' => $avg_completion,
            'certificates' => $certificates
        ];
    }

    /**
     * Get Comprehensive User List with Pagination - Scoped for Manager.
     */
    public function get_comprehensive_user_list($page = 1, $per_page = 10, $search = '', $role_filter = '', $status_filter = '') {
        global $DB, $CFG;

        if (!$this->companyid) {
            return ['data' => [], 'pagination' => ['total_records' => 0, 'total_pages' => 0, 'current_page' => 1, 'per_page' => $per_page]];
        }

        $offset = ($page - 1) * $per_page;
        $params = ['companyid' => $this->companyid];
        
        // Base Query joining company_users
        $sql_from = "FROM {user} u
                     JOIN {company_users} cu ON cu.userid = u.id
                     LEFT JOIN {role_assignments} ra ON ra.userid = u.id
                     LEFT JOIN {role} r ON r.id = ra.roleid";
        
        $where_clauses = ["cu.companyid = :companyid", "u.deleted = 0", "u.id > 2"];

        // Search Filter
        if (!empty($search)) {
            $where_clauses[] = "(u.firstname LIKE :search OR u.lastname LIKE :search2 OR u.email LIKE :search3)";
            $params['search'] = '%' . $search . '%';
            $params['search2'] = '%' . $search . '%';
            $params['search3'] = '%' . $search . '%';
        }

        // Status Filter
        if ($status_filter !== '') {
            if ($status_filter === 'active') {
                $where_clauses[] = "u.suspended = 0";
            } elseif ($status_filter === 'suspended') {
                $where_clauses[] = "u.suspended = 1";
            }
        }

        // Role Filter
        if (!empty($role_filter)) {
            $where_clauses[] = "r.shortname = :role";
            $params['role'] = $role_filter;
        }

        $where_sql = implode(" AND ", $where_clauses);

        // Count Total
        $count_sql = "SELECT COUNT(DISTINCT u.id) $sql_from WHERE $where_sql";
        $total_records = $DB->count_records_sql($count_sql, $params);
        $total_pages = ceil($total_records / $per_page);

        // Get Data
        $data_sql = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email, u.lastaccess, u.suspended, u.timecreated,
                            (SELECT shortname FROM {role} r2 
                             JOIN {role_assignments} ra2 ON ra2.roleid = r2.id 
                             WHERE ra2.userid = u.id ORDER BY r2.sortorder LIMIT 1) as role_shortname
                       $sql_from 
                       WHERE $where_sql 
                       ORDER BY u.id DESC";
        
        $users = $DB->get_records_sql($data_sql, $params, $offset, $per_page);

        $rows = [];
        foreach ($users as $user) {
            $status_class = ($user->suspended == 0) ? 'status-active' : 'status-inactive';
            $status_label = ($user->suspended == 0) ? 'Active' : 'Suspended';
            
            // Format Last Access
            $last_access = $user->lastaccess ? userdate($user->lastaccess) : 'Never';
            if ($user->lastaccess > time() - 300) {
                 $last_access = 'Just now';
            }

            $rows[] = [
                'id' => $user->id,
                'fullname' => fullname($user),
                'email' => $user->email,
                'role' => $user->role_shortname ?? 'student',
                'status' => $status_label,
                'status_class' => $status_class,
                'last_active' => $last_access,
                'enrolled_courses' => 0, // Simplified to avoid N+1 query performance hit
                'completed_courses' => 0,
                'avg_score' => '-',
                'completion' => 0
            ];
        }

        return [
            'data' => $rows,
            'pagination' => [
                'total_records' => $total_records,
                'total_pages' => $total_pages,
                'current_page' => $page,
                'per_page' => $per_page
            ]
        ];
    }
}
