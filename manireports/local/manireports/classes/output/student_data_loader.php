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
 * Student Data Loader - Personal data filtering
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_manireports\output;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/dashboard_data_loader.php');

/**
 * Student Data Loader class.
 * 
 * Extends dashboard_data_loader to filter all data by student's user ID.
 * Students only see their own personal data.
 */
class student_data_loader extends dashboard_data_loader {

    /**
     * Get Student KPIs (Personal data).
     *
     * @return array KPI data
     */
    public function get_admin_kpis() {
        global $DB;

        // Enrolled Courses
        $enrolled_courses = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT e.courseid)
             FROM {user_enrolments} ue
             JOIN {enrol} e ON e.id = ue.enrolid
             WHERE ue.userid = :userid AND ue.status = 0 AND e.courseid > 1",
            ['userid' => $this->userid]
        );

        // In Progress Courses (enrolled but not completed)
        $in_progress = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT e.courseid)
             FROM {user_enrolments} ue
             JOIN {enrol} e ON e.id = ue.enrolid
             LEFT JOIN {course_completions} cc ON cc.course = e.courseid AND cc.userid = ue.userid
             WHERE ue.userid = :userid AND ue.status = 0 AND e.courseid > 1
               AND (cc.timecompleted IS NULL OR cc.timecompleted = 0)",
            ['userid' => $this->userid]
        );

        // Completed Courses
        $completed_courses = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT cc.course)
             FROM {course_completions} cc
             WHERE cc.userid = :userid AND cc.timecompleted > 0",
            ['userid' => $this->userid]
        );

        // Average Progress across all courses
        $sql = "SELECT AVG(progress) as avg_progress
                FROM (
                    SELECT 
                        CASE 
                            WHEN total_activities > 0 
                            THEN (completed_activities * 100.0 / total_activities)
                            ELSE 0
                        END as progress
                    FROM (
                        SELECT e.courseid,
                               (SELECT COUNT(*) FROM {course_modules_completion} cmc
                                JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                                WHERE cm.course = e.courseid AND cmc.userid = :userid AND cmc.completionstate > 0) as completed_activities,
                               (SELECT COUNT(*) FROM {course_modules} cm2
                                WHERE cm2.course = e.courseid AND cm2.completion > 0) as total_activities
                        FROM {user_enrolments} ue
                        JOIN {enrol} e ON e.id = ue.enrolid
                        WHERE ue.userid = :userid2 AND ue.status = 0 AND e.courseid > 1
                    ) as course_progress
                ) as all_progress";

        try {
            $avg_progress = $DB->get_field_sql($sql, ['userid' => $this->userid, 'userid2' => $this->userid]);
            $avg_progress = $avg_progress ? round($avg_progress, 1) : 0;
        } catch (\Exception $e) {
            $avg_progress = 0;
        }

        return [
            'users' => $enrolled_courses, // Reusing 'users' field for enrolled courses
            'courses' => $in_progress,
            'companies' => $completed_courses, // Reusing 'companies' field for completed
            'completion_rate' => $avg_progress
        ];
    }

    /**
     * Get student's enrolled courses with progress (new method for student dashboard).
     *
     * @return array Course data with progress
     */
    public function get_my_courses() {
        global $DB;

        $sql = "SELECT c.id, c.fullname, c.shortname, c.summary,
                       ue.timecreated as enrolled_date,
                       cc.timecompleted,
                       (SELECT COUNT(*) FROM {course_modules_completion} cmc
                        JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                        WHERE cm.course = c.id AND cmc.userid = :userid AND cmc.completionstate > 0) as completed_activities,
                       (SELECT COUNT(*) FROM {course_modules} cm2
                        WHERE cm2.course = c.id AND cm2.completion > 0) as total_activities,
                       (SELECT MAX(timeaccess) FROM {user_lastaccess} ula
                        WHERE ula.userid = :userid2 AND ula.courseid = c.id) as last_access
                  FROM {course} c
                  JOIN {enrol} e ON e.courseid = c.id
                  JOIN {user_enrolments} ue ON ue.enrolid = e.id
             LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = ue.userid
                 WHERE ue.userid = :userid3 AND ue.status = 0 AND c.id > 1
              ORDER BY cc.timecompleted IS NULL DESC, c.fullname";

        $params = [
            'userid' => $this->userid,
            'userid2' => $this->userid,
            'userid3' => $this->userid
        ];

        try {
            $courses = $DB->get_records_sql($sql, $params);
        } catch (\Exception $e) {
            return [];
        }

        $rows = [];
        foreach ($courses as $course) {
            $progress = 0;
            if ($course->total_activities > 0) {
                $progress = round(($course->completed_activities / $course->total_activities) * 100);
            }

            $status = 'In Progress';
            $status_class = 'status-active';

            if ($course->timecompleted) {
                $status = 'Completed';
                $status_class = 'status-completed';
            } elseif ($progress == 0) {
                $status = 'Not Started';
                $status_class = 'status-upcoming';
            }

            $rows[] = [
                'id' => $course->id,
                'fullname' => $course->fullname,
                'shortname' => $course->shortname,
                'summary' => strip_tags($course->summary),
                'progress' => $progress,
                'completed_activities' => $course->completed_activities,
                'total_activities' => $course->total_activities,
                'status' => $status,
                'status_class' => $status_class,
                'last_access' => $course->last_access ? userdate($course->last_access, get_string('strftimedatetime')) : 'Never',
                'enrolled_date' => userdate($course->enrolled_date, get_string('strftimedate')),
                'completed_date' => $course->timecompleted ? userdate($course->timecompleted, get_string('strftimedate')) : null,
                'course_url' => new \moodle_url('/course/view.php', ['id' => $course->id])
            ];
        }

        return $rows;
    }

    /**
     * Get student's grades (new method for student dashboard).
     *
     * @return array Grade data
     */
    public function get_my_grades() {
        global $DB;

        $sql = "SELECT c.id, c.fullname, gi.itemname, gg.finalgrade, gi.grademax
                  FROM {course} c
                  JOIN {enrol} e ON e.courseid = c.id
                  JOIN {user_enrolments} ue ON ue.enrolid = e.id
                  JOIN {grade_items} gi ON gi.courseid = c.id
                  JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = ue.userid
                 WHERE ue.userid = :userid AND ue.status = 0 AND c.id > 1
                   AND gi.itemtype = 'course' AND gg.finalgrade IS NOT NULL
              ORDER BY c.fullname";

        try {
            $grades = $DB->get_records_sql($sql, ['userid' => $this->userid]);
        } catch (\Exception $e) {
            return [];
        }

        $rows = [];
        foreach ($grades as $grade) {
            $percentage = 0;
            if ($grade->grademax > 0) {
                $percentage = round(($grade->finalgrade / $grade->grademax) * 100, 1);
            }

            $rows[] = [
                'course_id' => $grade->id,
                'course_name' => $grade->fullname,
                'grade' => round($grade->finalgrade, 2),
                'max_grade' => round($grade->grademax, 2),
                'percentage' => $percentage
            ];
        }

        return $rows;
    }

    /**
     * Get Courses Tab Metrics (KPIs) - Scoped for Student.
     * Overrides parent method to show personal stats instead of system-wide.
     */
    public function get_courses_tab_metrics($search = '', $category = 0) {
        global $DB;

        // Reuse logic from KPI calculation
        $kpis = $this->get_admin_kpis();
        
        // 1. My Active Courses (In Progress)
        $active_courses = $kpis['courses']; 
        
        // 2. My Total Enrollments
        $total_enrollments = $kpis['users']; 
        
        // 3. My Avg Completion
        $avg_completion = $kpis['completion_rate'];
        
        // 4. My Certificates
        $certificates = 0;
        if ($DB->get_manager()->table_exists('certificate_issues')) {
             $certificates += $DB->count_records('certificate_issues', ['userid' => $this->userid]);
        }
        if ($DB->get_manager()->table_exists('customcert_issues')) {
             $certificates += $DB->count_records('customcert_issues', ['userid' => $this->userid]);
        }
        if ($DB->get_manager()->table_exists('simplecertificate_issues')) {
             $certificates += $DB->count_records('simplecertificate_issues', ['userid' => $this->userid]);
        }
        
        return [
            'active_courses' => $active_courses,
            'total_enrollments' => $total_enrollments,
            'avg_completion' => $avg_completion,
            'certificates' => $certificates
        ];
    }
    /**
     * Get Comprehensive Course List (Advanced Table) - Scoped for Student.
     * Shows only courses the student is enrolled in.
     */
    public function get_comprehensive_course_list($limit = 20, $search = '', $category = 0) {
        // Reuse get_my_courses but format for the table
        $my_courses = $this->get_my_courses();
        
        $rows = [];
        foreach ($my_courses as $course) {
            // Apply search filter if needed
            if (!empty($search)) {
                if (stripos($course['fullname'], $search) === false && stripos($course['shortname'], $search) === false) {
                    continue; // Skip if doesn't match search
                }
            }
            
            // Note: Category filter ignored as we don't fetch categories for students to keep it simple/fast
            
            $rows[] = [
                'id' => $course['id'],
                'fullname' => $course['fullname'],
                'category' => '-', // Hide/Skip category lookup
                'enrolled' => 1, // Just the student
                'completed' => ($course['status'] === 'Completed') ? 1 : 0,
                'progress' => $course['progress'],
                'progress' => $course['progress'], // Key repeated in original, kept for safety
                'avg_time' => '-', // Hide avg time
                'status' => $course['status'],
                'status_class' => $course['status_class']
            ];
            
            if (count($rows) >= $limit) break;
        }
        
        return $rows;
    }

    /**
     * Get Course Enrollment Trends - Scoped for Student.
     * Return empty or personal activity to prevent leakage.
     */
    public function get_course_enrollment_trends($search = '', $category = 0) {
        // Return flat line or empty to hide system trends
        return [
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            'data' => [0, 0, 0, 0, 0, 0] 
        ];
    }
    
    /**
     * Get Category Distribution - Scoped for Student.
     * Return empty to prevent leakage.
     */
    public function get_category_distribution() {
        return [
            'labels' => [],
            'data' => []
        ];
    }
}
