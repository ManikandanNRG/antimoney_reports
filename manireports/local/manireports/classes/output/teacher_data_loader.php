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
 * Teacher Data Loader - Course-scoped data filtering
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_manireports\output;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/dashboard_data_loader.php');

/**
 * Teacher Data Loader class.
 * 
 * Extends dashboard_data_loader to filter all data by teacher's courses.
 * Teachers only see data for courses they teach.
 */
class teacher_data_loader extends dashboard_data_loader {

    /** @var array Course IDs for filtering */
    protected $course_ids;

    /**
     * Constructor.
     *
     * @param int $userid User ID
     * @param array $course_ids Array of course IDs teacher teaches
     * @param int $startdate Optional start timestamp
     * @param int $enddate Optional end timestamp
     */
    public function __construct($userid, $course_ids, $startdate = 0, $enddate = 0) {
        parent::__construct($userid, $startdate, $enddate);
        $this->course_ids = $course_ids ?: [];
    }

    /**
     * Get Teacher KPIs (Course-scoped).
     *
     * @return array KPI data
     */
    public function get_admin_kpis() {
        global $DB;

        if (empty($this->course_ids)) {
            return ['users' => 0, 'courses' => 0, 'companies' => 0, 'completion_rate' => 0];
        }

        list($insql, $params) = $DB->get_in_or_equal($this->course_ids, SQL_PARAMS_NAMED);

        // Total Students in teacher's courses
        $total_students = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT ue.userid)
             FROM {user_enrolments} ue
             JOIN {enrol} e ON e.id = ue.enrolid
             WHERE e.courseid $insql AND ue.status = 0",
            $params
        );

        // Active Students (accessed in last 7 days)
        $params['lastweek'] = time() - (7 * 24 * 3600);
        $active_students = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT u.id)
             FROM {user} u
             JOIN {user_enrolments} ue ON ue.userid = u.id
             JOIN {enrol} e ON e.id = ue.enrolid
             WHERE e.courseid $insql AND ue.status = 0 AND u.lastaccess > :lastweek",
            $params
        );

        // Course Completions in teacher's courses
        $params2 = $params;
        unset($params2['lastweek']);
        $total_completions = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT cc.id)
             FROM {course_completions} cc
             WHERE cc.course $insql AND cc.timecompleted > 0",
            $params2
        );

        // Total Enrollments
        $total_enrollments = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT ue.id)
             FROM {user_enrolments} ue
             JOIN {enrol} e ON e.id = ue.enrolid
             WHERE e.courseid $insql AND ue.status = 0",
            $params2
        );

        $completion_rate = 0;
        if ($total_enrollments > 0) {
            $completion_rate = round(($total_completions / $total_enrollments) * 100, 1);
        }

        return [
            'users' => $total_students,
            'courses' => count($this->course_ids),
            'companies' => 0, // Teachers don't see companies
            'completion_rate' => $completion_rate,
            'active_users' => $active_students
        ];
    }

    /**
     * Get Top Courses Analytics (teacher's courses only).
     *
     * @param int $limit Number of records
     * @return array Course data
     */
    public function get_top_courses_analytics($limit = 10) {
        global $DB;

        if (empty($this->course_ids)) {
            return [];
        }

        list($insql, $params) = $DB->get_in_or_equal($this->course_ids, SQL_PARAMS_NAMED);

        $sql = "SELECT c.id, c.fullname, c.shortname, c.startdate, c.visible,
                       COUNT(DISTINCT ue.userid) as enrolled,
                       COUNT(DISTINCT cc.userid) as completed
                  FROM {course} c
                  JOIN {enrol} e ON e.courseid = c.id
                  JOIN {user_enrolments} ue ON ue.enrolid = e.id
             LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = ue.userid AND cc.timecompleted > 0
                 WHERE c.id $insql
              GROUP BY c.id, c.fullname, c.shortname, c.startdate, c.visible
              ORDER BY enrolled DESC";

        try {
            $courses = $DB->get_records_sql($sql, $params, 0, $limit);
        } catch (\Exception $e) {
            return [];
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
     * Get students in teacher's courses (new method for teacher dashboard).
     *
     * @return array Student data grouped by course
     */
    public function get_my_students() {
        global $DB;

        if (empty($this->course_ids)) {
            return [];
        }

        $result = [];

        foreach ($this->course_ids as $courseid) {
            $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname');
            if (!$course) {
                continue;
            }

            // Get students enrolled in this course
            $sql = "SELECT u.id, u.firstname, u.lastname, u.email, u.lastaccess,
                           cc.timecompleted,
                           (SELECT COUNT(*) FROM {course_modules_completion} cmc
                            JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                            WHERE cm.course = :courseid AND cmc.userid = u.id AND cmc.completionstate > 0) as completed_activities,
                           (SELECT COUNT(*) FROM {course_modules} cm2
                            WHERE cm2.course = :courseid2 AND cm2.completion > 0) as total_activities
                      FROM {user} u
                      JOIN {user_enrolments} ue ON ue.userid = u.id
                      JOIN {enrol} e ON e.id = ue.enrolid
                 LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = :courseid3
                     WHERE e.courseid = :courseid4 AND ue.status = 0 AND u.deleted = 0
                  ORDER BY u.lastname, u.firstname";

            $params = [
                'courseid' => $courseid,
                'courseid2' => $courseid,
                'courseid3' => $courseid,
                'courseid4' => $courseid
            ];

            try {
                $students = $DB->get_records_sql($sql, $params, 0, 50); // Limit to 50 per course
            } catch (\Exception $e) {
                continue;
            }

            $student_list = [];
            foreach ($students as $student) {
                $progress = 0;
                if ($student->total_activities > 0) {
                    $progress = round(($student->completed_activities / $student->total_activities) * 100);
                }

                $student_list[] = [
                    'id' => $student->id,
                    'fullname' => fullname($student),
                    'email' => $student->email,
                    'lastaccess' => $student->lastaccess ? userdate($student->lastaccess, get_string('strftimedatetime')) : 'Never',
                    'progress' => $progress,
                    'completed' => $student->timecompleted ? true : false
                ];
            }

            $result[] = [
                'course_id' => $course->id,
                'course_name' => $course->fullname,
                'student_count' => count($student_list),
                'students' => $student_list
            ];
        }

        return $result;
    }

    /**
     * Get Courses Tab Metrics (KPIs) - Scoped for Teacher.
     * Overrides parent method to show course-scoped stats instead of system-wide.
     */
    public function get_courses_tab_metrics($search = '', $category = 0) {
        // Reuse logic from KPI calculation
        $kpis = $this->get_admin_kpis();
        
        return [
            'active_courses' => $kpis['courses'], // Teaching courses count
            'total_enrollments' => $kpis['users'], // Count of students
            'avg_completion' => $kpis['completion_rate'],
            'certificates' => 0 // Placeholder
        ];
    }
    /**
     * Get Comprehensive Course List - Scoped for Teacher.
     */
    public function get_comprehensive_course_list($limit = 20, $search = '', $category = 0) {
        global $DB;
        
        if (empty($this->course_ids)) {
            return [];
        }

        list($insql, $params) = $DB->get_in_or_equal($this->course_ids, SQL_PARAMS_NAMED);
        
        // Add search
        $sql_where = "c.id $insql";
        if (!empty($search)) {
            $sql_where .= " AND (c.fullname LIKE :search OR c.shortname LIKE :search2)";
            $params['search'] = '%' . $search . '%';
            $params['search2'] = '%' . $search . '%';
        }
        
        // Add category
        if ($category > 0) {
            $sql_where .= " AND c.category = :category";
            $params['category'] = $category;
        }

        $sql = "SELECT c.id, c.fullname, c.shortname, c.startdate, c.visible, cat.name as category_name,
                       COUNT(DISTINCT ue.userid) as enrolled,
                       COUNT(DISTINCT cc.userid) as completed,
                       AVG(CASE WHEN cc.timecompleted > 0 THEN (cc.timecompleted - cc.timeenrolled) ELSE NULL END) as avg_duration
                  FROM {course} c
                  JOIN {course_categories} cat ON cat.id = c.category
                  JOIN {enrol} e ON e.courseid = c.id
                  JOIN {user_enrolments} ue ON ue.enrolid = e.id
             LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = ue.userid AND cc.timecompleted > 0
                 WHERE $sql_where
              GROUP BY c.id, c.fullname, c.shortname, c.startdate, c.visible, cat.name
              ORDER BY enrolled DESC";

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
     * Get Course Enrollment Trends - Scoped for Teacher.
     */
    public function get_course_enrollment_trends($search = '', $category = 0) {
        global $DB;
        
        if (empty($this->course_ids)) {
            return ['labels' => [], 'data' => []];
        }
        
        $months = [];
        $data = [];
        
        list($insql, $ctx_params) = $DB->get_in_or_equal($this->course_ids, SQL_PARAMS_NAMED);
        
        for ($i = 5; $i >= 0; $i--) {
            $timestamp = strtotime("-$i months");
            $month_start = strtotime("first day of this month 00:00:00", $timestamp);
            $month_end = strtotime("last day of this month 23:59:59", $timestamp);
            
            $months[] = date('M', $timestamp);
            
            // Build Query
            $params = array_merge($ctx_params, ['start' => $month_start, 'end' => $month_end]);
            $sql_where = "c.id $insql AND ue.timecreated >= :start AND ue.timecreated <= :end";
            
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
                    WHERE $sql_where";

            $data[] = $DB->count_records_sql($sql, $params);
        }

        return [
            'labels' => $months,
            'data' => $data
        ];
    }
    
    /**
     * Get Category Distribution - Scoped for Teacher.
     */
    public function get_category_distribution() {
        global $DB;
        
        if (empty($this->course_ids)) {
            return ['labels' => [], 'data' => []];
        }

        list($insql, $params) = $DB->get_in_or_equal($this->course_ids, SQL_PARAMS_NAMED);
        
        $sql = "SELECT cat.name, COUNT(c.id) as count
                FROM {course} c
                JOIN {course_categories} cat ON cat.id = c.category
                WHERE c.id $insql
                GROUP BY cat.name";
                
        $records = $DB->get_records_sql($sql, $params);
        
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
}
