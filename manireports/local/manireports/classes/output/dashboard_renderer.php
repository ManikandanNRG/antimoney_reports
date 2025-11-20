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
 * Dashboard renderer for ManiReports.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_manireports\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Dashboard renderer class.
 */
class dashboard_renderer extends \plugin_renderer_base {

    /**
     * Render dashboard based on user role.
     *
     * @param int $userid User ID
     * @return string HTML output
     */
    public function render_dashboard($userid) {
        global $OUTPUT, $DB;

        $context = \context_system::instance();

        // Determine which dashboard to show based on capabilities.
        if (has_capability('local/manireports:viewadmindashboard', $context, $userid)) {
            return $this->render_admin_dashboard($userid);
        } else if (has_capability('local/manireports:viewmanagerdashboard', $context, $userid)) {
            return $this->render_manager_dashboard($userid);
        } else if (has_capability('local/manireports:viewteacherdashboard', $context, $userid)) {
            return $this->render_teacher_dashboard($userid);
        } else if (has_capability('local/manireports:viewstudentdashboard', $context, $userid)) {
            return $this->render_student_dashboard($userid);
        } else {
            // Check if user has student capability in any course context (for IOMAD students)
            $courses = $DB->get_records_sql(
                "SELECT DISTINCT c.id FROM {course} c
                 JOIN {enrol} e ON c.id = e.courseid
                 JOIN {user_enrolments} ue ON e.id = ue.enrolid
                 WHERE ue.userid = ? AND ue.status = 0 AND e.status = 0",
                [$userid]
            );
            
            foreach ($courses as $course) {
                $course_context = \context_course::instance($course->id);
                if (has_capability('local/manireports:viewstudentdashboard', $course_context, $userid)) {
                    return $this->render_student_dashboard($userid);
                }
            }
        }

        return $OUTPUT->notification(get_string('error:nopermission', 'local_manireports'), 'error');
    }

    /**
     * Render admin dashboard.
     *
     * @param int $userid User ID
     * @return string HTML output
     */
    protected function render_admin_dashboard($userid) {
        $data = array(
            'title' => get_string('admindashboard', 'local_manireports'),
            'widgets' => $this->get_admin_widgets($userid),
            'companies' => $this->get_companies_data(),
            'courseusage' => $this->get_course_usage_data(),
            'inactiveusers' => $this->get_inactive_users_list(),
            'hasisomad' => $this->is_iomad_installed()
        );

        return $this->render_from_template('local_manireports/dashboard_admin', $data);
    }

    /**
     * Render manager dashboard.
     *
     * @param int $userid User ID
     * @return string HTML output
     */
    protected function render_manager_dashboard($userid) {
        global $DB;

        // Get manager's company.
        $companyid = $this->get_user_company($userid);
        $companyname = '';

        if ($companyid) {
            $company = $DB->get_record('company', array('id' => $companyid), 'name');
            if ($company) {
                $companyname = format_string($company->name);
            }
        }

        $data = array(
            'title' => get_string('managerdashboard', 'local_manireports'),
            'companyname' => $companyname,
            'hascompany' => !empty($companyname),
            'widgets' => $this->get_manager_widgets($userid),
            'companyusers' => $this->get_company_users_list($companyid),
            'companycourses' => $this->get_company_courses_list($companyid)
        );

        return $this->render_from_template('local_manireports/dashboard_manager', $data);
    }

    /**
     * Render teacher dashboard.
     *
     * @param int $userid User ID
     * @return string HTML output
     */
    protected function render_teacher_dashboard($userid) {
        $data = array(
            'title' => get_string('teacherdashboard', 'local_manireports'),
            'widgets' => $this->get_teacher_widgets($userid),
            'mycourses' => $this->get_teacher_courses_list($userid),
            'studentprogress' => $this->get_teacher_student_progress($userid),
            'recentactivity' => $this->get_teacher_recent_activity($userid)
        );

        return $this->render_from_template('local_manireports/dashboard_teacher', $data);
    }

    /**
     * Render student dashboard.
     *
     * @param int $userid User ID
     * @return string HTML output
     */
    protected function render_student_dashboard($userid) {
        $data = array(
            'title' => get_string('studentdashboard', 'local_manireports'),
            'widgets' => $this->get_student_widgets($userid)
        );

        return $this->render_from_template('local_manireports/dashboard_student', $data);
    }

    /**
     * Get widgets for admin dashboard.
     *
     * @param int $userid User ID
     * @return array Array of widget data
     */
    protected function get_admin_widgets($userid) {
        global $DB;

        // Check cache first.
        $cachemanager = new \local_manireports\api\cache_manager();
        $cachekey = $cachemanager->generate_cache_key('admin_widgets', array('userid' => $userid));
        $cacheddata = $cachemanager->get_cached_data($cachekey);
        
        if ($cacheddata !== null) {
            return $cacheddata;
        }

        $widgets = array();

        // Total users widget.
        $totalusers = $DB->count_records('user', array('deleted' => 0, 'suspended' => 0));
        $widgets[] = array(
            'type' => 'kpi',
            'title' => get_string('totalusers', 'local_manireports'),
            'value' => $totalusers,
            'icon' => 'users'
        );

        // Total courses widget.
        $totalcourses = $DB->count_records_select('course', 'id > 1');
        $widgets[] = array(
            'type' => 'kpi',
            'title' => get_string('totalcourses', 'local_manireports'),
            'value' => $totalcourses,
            'icon' => 'book'
        );

        // Total enrollments widget.
        $totalenrolments = $DB->count_records('user_enrolments', array('status' => 0));
        $widgets[] = array(
            'type' => 'kpi',
            'title' => get_string('totalenrolments', 'local_manireports'),
            'value' => $totalenrolments,
            'icon' => 'graduation-cap'
        );

        // Active users (last 30 days).
        $thirtydays = time() - (30 * 24 * 60 * 60);
        $activeusers = $DB->count_records_select('user', 
            'deleted = 0 AND suspended = 0 AND lastaccess > :lastaccess',
            array('lastaccess' => $thirtydays)
        );
        $widgets[] = array(
            'type' => 'kpi',
            'title' => get_string('activeusers30days', 'local_manireports'),
            'value' => $activeusers,
            'icon' => 'user-check'
        );

        // Inactive users (no login in 30 days).
        $inactiveusers = $DB->count_records_select('user',
            'deleted = 0 AND suspended = 0 AND (lastaccess < :lastaccess OR lastaccess = 0)',
            array('lastaccess' => $thirtydays)
        );
        $widgets[] = array(
            'type' => 'kpi',
            'title' => get_string('inactiveusers30days', 'local_manireports'),
            'value' => $inactiveusers,
            'icon' => 'user-times',
            'alert' => $inactiveusers > 0
        );

        // Course completions (last 30 days).
        $completions = $DB->count_records_select('course_completions',
            'timecompleted > :timecompleted',
            array('timecompleted' => $thirtydays)
        );
        $widgets[] = array(
            'type' => 'kpi',
            'title' => get_string('completions30days', 'local_manireports'),
            'value' => $completions,
            'icon' => 'trophy'
        );

        // Cache the widgets.
        $ttl = get_config('local_manireports', 'cachettl_dashboard') ?: 3600;
        $cachemanager->set_cached_data($cachekey, $widgets, 'admin_widgets', null, $ttl);

        return $widgets;
    }

    /**
     * Get widgets for manager dashboard.
     *
     * @param int $userid User ID
     * @return array Array of widget data
     */
    protected function get_manager_widgets($userid) {
        global $DB;

        // Get manager's company ID.
        $companyid = $this->get_user_company($userid);

        if (!$companyid) {
            // No company assigned, show error.
            return array();
        }

        // Check cache first.
        $cachemanager = new \local_manireports\api\cache_manager();
        $cachekey = $cachemanager->generate_cache_key('manager_widgets', array(
            'userid' => $userid,
            'companyid' => $companyid
        ));
        $cacheddata = $cachemanager->get_cached_data($cachekey);
        
        if ($cacheddata !== null) {
            return $cacheddata;
        }

        $widgets = array();

        // Total users in company.
        $totalusers = $DB->count_records('company_users', array('companyid' => $companyid));
        $widgets[] = array(
            'type' => 'kpi',
            'title' => get_string('companyusers', 'local_manireports'),
            'value' => $totalusers,
            'icon' => 'users'
        );

        // Total courses in company.
        $totalcourses = $DB->count_records('company_course', array('companyid' => $companyid));
        $widgets[] = array(
            'type' => 'kpi',
            'title' => get_string('companycourses', 'local_manireports'),
            'value' => $totalcourses,
            'icon' => 'book'
        );

        // Active users in company (last 30 days).
        $thirtydays = time() - (30 * 24 * 60 * 60);
        $sql = "SELECT COUNT(DISTINCT u.id)
                  FROM {user} u
                  JOIN {company_users} cu ON cu.userid = u.id
                 WHERE cu.companyid = :companyid
                   AND u.deleted = 0
                   AND u.suspended = 0
                   AND u.lastaccess > :lastaccess";
        
        $activeusers = $DB->count_records_sql($sql, array(
            'companyid' => $companyid,
            'lastaccess' => $thirtydays
        ));

        $widgets[] = array(
            'type' => 'kpi',
            'title' => get_string('activeusers30days', 'local_manireports'),
            'value' => $activeusers,
            'icon' => 'user-check'
        );

        // Course completions in company (last 30 days).
        $sql = "SELECT COUNT(cc.id)
                  FROM {course_completions} cc
                  JOIN {company_users} cu ON cu.userid = cc.userid
                 WHERE cu.companyid = :companyid
                   AND cc.timecompleted > :timecompleted";
        
        $completions = $DB->count_records_sql($sql, array(
            'companyid' => $companyid,
            'timecompleted' => $thirtydays
        ));

        $widgets[] = array(
            'type' => 'kpi',
            'title' => get_string('completions30days', 'local_manireports'),
            'value' => $completions,
            'icon' => 'trophy'
        );

        // Enrollments in company.
        $sql = "SELECT COUNT(DISTINCT ue.id)
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {company_course} cc ON cc.courseid = e.courseid
                  JOIN {company_users} cu ON cu.userid = ue.userid
                 WHERE cc.companyid = :companyid
                   AND cu.companyid = :companyid2
                   AND ue.status = 0";
        
        $enrollments = $DB->count_records_sql($sql, array(
            'companyid' => $companyid,
            'companyid2' => $companyid
        ));

        $widgets[] = array(
            'type' => 'kpi',
            'title' => get_string('totalenrolments', 'local_manireports'),
            'value' => $enrollments,
            'icon' => 'graduation-cap'
        );

        // Inactive users in company.
        $sql = "SELECT COUNT(DISTINCT u.id)
                  FROM {user} u
                  JOIN {company_users} cu ON cu.userid = u.id
                 WHERE cu.companyid = :companyid
                   AND u.deleted = 0
                   AND u.suspended = 0
                   AND (u.lastaccess < :lastaccess OR u.lastaccess = 0)";
        
        $inactiveusers = $DB->count_records_sql($sql, array(
            'companyid' => $companyid,
            'lastaccess' => $thirtydays
        ));

        $widgets[] = array(
            'type' => 'kpi',
            'title' => get_string('inactiveusers30days', 'local_manireports'),
            'value' => $inactiveusers,
            'icon' => 'user-times',
            'alert' => $inactiveusers > 0
        );

        // Cache the widgets.
        $ttl = get_config('local_manireports', 'cachettl_dashboard') ?: 3600;
        $cachemanager->set_cached_data($cachekey, $widgets, 'manager_widgets', $companyid, $ttl);

        return $widgets;
    }

    /**
     * Get widgets for teacher dashboard.
     *
     * @param int $userid User ID
     * @return array Array of widget data
     */
    protected function get_teacher_widgets($userid) {
        global $DB;

        // Check cache first.
        $cachemanager = new \local_manireports\api\cache_manager();
        $cachekey = $cachemanager->generate_cache_key('teacher_widgets', array('userid' => $userid));
        $cacheddata = $cachemanager->get_cached_data($cachekey);
        
        if ($cacheddata !== null) {
            return $cacheddata;
        }

        $widgets = array();

        // Get courses where user is teacher.
        $sql = "SELECT COUNT(DISTINCT c.id)
                  FROM {course} c
                  JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
                  JOIN {role_assignments} ra ON ra.contextid = ctx.id
                  JOIN {role} r ON r.id = ra.roleid
                 WHERE ra.userid = :userid
                   AND r.archetype IN ('editingteacher', 'teacher')";
        
        $mycourses = $DB->count_records_sql($sql, array('userid' => $userid));

        $widgets[] = array(
            'type' => 'kpi',
            'title' => get_string('mycourses', 'local_manireports'),
            'value' => $mycourses,
            'icon' => 'book'
        );

        // Get total students in teacher's courses.
        $sql = "SELECT COUNT(DISTINCT ue.userid)
                  FROM {course} c
                  JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
                  JOIN {role_assignments} ra ON ra.contextid = ctx.id
                  JOIN {role} r ON r.id = ra.roleid
                  JOIN {enrol} e ON e.courseid = c.id
                  JOIN {user_enrolments} ue ON ue.enrolid = e.id
                  JOIN {role_assignments} ra2 ON ra2.contextid = ctx.id AND ra2.userid = ue.userid
                  JOIN {role} r2 ON r2.id = ra2.roleid
                 WHERE ra.userid = :userid
                   AND r.archetype IN ('editingteacher', 'teacher')
                   AND r2.archetype = 'student'
                   AND ue.status = 0";
        
        $totalstudents = $DB->count_records_sql($sql, array('userid' => $userid));

        $widgets[] = array(
            'type' => 'kpi',
            'title' => get_string('mystudents', 'local_manireports'),
            'value' => $totalstudents,
            'icon' => 'users'
        );

        // Get active students (last 7 days).
        $sevendays = time() - (7 * 24 * 60 * 60);
        $sql = "SELECT COUNT(DISTINCT u.id)
                  FROM {course} c
                  JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
                  JOIN {role_assignments} ra ON ra.contextid = ctx.id
                  JOIN {role} r ON r.id = ra.roleid
                  JOIN {enrol} e ON e.courseid = c.id
                  JOIN {user_enrolments} ue ON ue.enrolid = e.id
                  JOIN {user} u ON u.id = ue.userid
                  JOIN {role_assignments} ra2 ON ra2.contextid = ctx.id AND ra2.userid = u.id
                  JOIN {role} r2 ON r2.id = ra2.roleid
                 WHERE ra.userid = :userid
                   AND r.archetype IN ('editingteacher', 'teacher')
                   AND r2.archetype = 'student'
                   AND ue.status = 0
                   AND u.lastaccess > :lastaccess";
        
        $activestudents = $DB->count_records_sql($sql, array(
            'userid' => $userid,
            'lastaccess' => $sevendays
        ));

        $widgets[] = array(
            'type' => 'kpi',
            'title' => get_string('activestudents7days', 'local_manireports'),
            'value' => $activestudents,
            'icon' => 'user-check'
        );

        // Get course completions in teacher's courses (last 30 days).
        $thirtydays = time() - (30 * 24 * 60 * 60);
        $sql = "SELECT COUNT(DISTINCT cc.id)
                  FROM {course} c
                  JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
                  JOIN {role_assignments} ra ON ra.contextid = ctx.id
                  JOIN {role} r ON r.id = ra.roleid
                  JOIN {course_completions} cc ON cc.course = c.id
                 WHERE ra.userid = :userid
                   AND r.archetype IN ('editingteacher', 'teacher')
                   AND cc.timecompleted > :timecompleted";
        
        $completions = $DB->count_records_sql($sql, array(
            'userid' => $userid,
            'timecompleted' => $thirtydays
        ));

        $widgets[] = array(
            'type' => 'kpi',
            'title' => get_string('completions30days', 'local_manireports'),
            'value' => $completions,
            'icon' => 'trophy'
        );

        // Get pending submissions/grading.
        $sql = "SELECT COUNT(DISTINCT s.id)
                  FROM {course} c
                  JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
                  JOIN {role_assignments} ra ON ra.contextid = ctx.id
                  JOIN {role} r ON r.id = ra.roleid
                  JOIN {assign} a ON a.course = c.id
                  JOIN {assign_submission} s ON s.assignment = a.id
                 WHERE ra.userid = :userid
                   AND r.archetype IN ('editingteacher', 'teacher')
                   AND s.status = 'submitted'
                   AND s.timemodified > s.timecreated
                   AND NOT EXISTS (
                       SELECT 1 FROM {assign_grades} ag 
                       WHERE ag.assignment = a.id 
                       AND ag.userid = s.userid 
                       AND ag.timemodified > s.timemodified
                   )";
        
        try {
            $pendinggrading = $DB->count_records_sql($sql, array('userid' => $userid));
        } catch (\dml_exception $e) {
            $pendinggrading = 0;
        }

        $widgets[] = array(
            'type' => 'kpi',
            'title' => get_string('pendinggrading', 'local_manireports'),
            'value' => $pendinggrading,
            'icon' => 'clipboard-check',
            'alert' => $pendinggrading > 0
        );

        // Get quiz attempts (last 7 days).
        $sql = "SELECT COUNT(DISTINCT qa.id)
                  FROM {course} c
                  JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
                  JOIN {role_assignments} ra ON ra.contextid = ctx.id
                  JOIN {role} r ON r.id = ra.roleid
                  JOIN {quiz} q ON q.course = c.id
                  JOIN {quiz_attempts} qa ON qa.quiz = q.id
                 WHERE ra.userid = :userid
                   AND r.archetype IN ('editingteacher', 'teacher')
                   AND qa.timefinish > :timefinish";
        
        try {
            $quizattempts = $DB->count_records_sql($sql, array(
                'userid' => $userid,
                'timefinish' => $sevendays
            ));
        } catch (\dml_exception $e) {
            $quizattempts = 0;
        }

        $widgets[] = array(
            'type' => 'kpi',
            'title' => get_string('quizattempts7days', 'local_manireports'),
            'value' => $quizattempts,
            'icon' => 'question-circle'
        );

        // Cache the widgets.
        $ttl = get_config('local_manireports', 'cachettl_dashboard') ?: 3600;
        $cachemanager->set_cached_data($cachekey, $widgets, 'teacher_widgets', null, $ttl);

        return $widgets;
    }

    /**
     * Get widgets for student dashboard.
     *
     * @param int $userid User ID
     * @return array Array of widget data
     */
    protected function get_student_widgets($userid) {
        global $DB;

        // Check cache first.
        $cachemanager = new \local_manireports\api\cache_manager();
        $cachekey = $cachemanager->generate_cache_key('student_widgets', array('userid' => $userid));
        $cacheddata = $cachemanager->get_cached_data($cachekey);
        
        if ($cacheddata !== null) {
            return $cacheddata;
        }

        $widgets = array();

        // Get enrolled courses.
        $enrolledcourses = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT c.id)
               FROM {course} c
               JOIN {enrol} e ON e.courseid = c.id
               JOIN {user_enrolments} ue ON ue.enrolid = e.id
              WHERE ue.userid = :userid
                AND ue.status = 0
                AND c.id > 1",
            array('userid' => $userid)
        );

        $widgets[] = array(
            'type' => 'kpi',
            'title' => get_string('enrolledcourses', 'local_manireports'),
            'value' => $enrolledcourses,
            'icon' => 'book'
        );

        // Get completed courses.
        $completedcourses = $DB->count_records_sql(
            "SELECT COUNT(*) FROM {course_completions}
             WHERE userid = :userid AND timecompleted > 0",
            array('userid' => $userid)
        );

        $widgets[] = array(
            'type' => 'kpi',
            'title' => get_string('completedcourses', 'local_manireports'),
            'value' => $completedcourses,
            'icon' => 'check-circle'
        );

        // Cache the widgets.
        $ttl = get_config('local_manireports', 'cachettl_dashboard') ?: 3600;
        $cachemanager->set_cached_data($cachekey, $widgets, 'student_widgets', null, $ttl);

        return $widgets;
    }

    /**
     * Render a single widget.
     *
     * @param array $widget Widget data
     * @return string HTML output
     */
    public function render_widget($widget) {
        $template = 'local_manireports/widget_' . $widget['type'];
        return $this->render_from_template($template, $widget);
    }

    /**
     * Check if IOMAD is installed.
     *
     * @return bool True if IOMAD is installed
     */
    protected function is_iomad_installed() {
        global $CFG;
        return file_exists($CFG->dirroot . '/local/iomad/lib.php');
    }

    /**
     * Get companies data for IOMAD installations.
     *
     * @return array Array of company data
     */
    protected function get_companies_data() {
        global $DB;

        if (!$this->is_iomad_installed()) {
            return array();
        }

        $companies = array();

        try {
            $companyrecords = $DB->get_records('company', null, 'name ASC');

            foreach ($companyrecords as $company) {
                // Count users in company.
                $usercount = $DB->count_records('company_users', array('companyid' => $company->id));

                // Count courses in company.
                $coursecount = $DB->count_records('company_course', array('companyid' => $company->id));

                $companies[] = array(
                    'id' => $company->id,
                    'name' => format_string($company->name),
                    'shortname' => format_string($company->shortname),
                    'usercount' => $usercount,
                    'coursecount' => $coursecount
                );
            }
        } catch (\dml_exception $e) {
            // IOMAD tables might not exist, return empty array.
            debugging('IOMAD tables not found: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return $companies;
    }

    /**
     * Get course usage data for heatmap.
     *
     * @return array Array of course usage data
     */
    protected function get_course_usage_data() {
        global $DB;

        $courses = array();

        // Get top 10 most accessed courses in last 30 days.
        $thirtydays = time() - (30 * 24 * 60 * 60);

        $sql = "SELECT c.id, c.fullname, c.shortname, COUNT(DISTINCT l.userid) as usercount,
                       COUNT(l.id) as accesscount
                  FROM {course} c
                  JOIN {logstore_standard_log} l ON l.courseid = c.id
                 WHERE c.id > 1
                   AND l.timecreated > :timecreated
              GROUP BY c.id, c.fullname, c.shortname
              ORDER BY accesscount DESC
                 LIMIT 10";

        try {
            $records = $DB->get_records_sql($sql, array('timecreated' => $thirtydays));

            foreach ($records as $record) {
                $courses[] = array(
                    'id' => $record->id,
                    'fullname' => format_string($record->fullname),
                    'shortname' => format_string($record->shortname),
                    'usercount' => $record->usercount,
                    'accesscount' => $record->accesscount
                );
            }
        } catch (\dml_exception $e) {
            debugging('Error fetching course usage: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return $courses;
    }

    /**
     * Get list of inactive users.
     *
     * @return array Array of inactive user data
     */
    protected function get_inactive_users_list() {
        global $DB;

        $users = array();

        // Get users who haven't logged in for 30 days.
        $thirtydays = time() - (30 * 24 * 60 * 60);

        $sql = "SELECT u.id, u.firstname, u.lastname, u.email, u.lastaccess
                  FROM {user} u
                 WHERE u.deleted = 0
                   AND u.suspended = 0
                   AND (u.lastaccess < :lastaccess OR u.lastaccess = 0)
                   AND u.id > 2
              ORDER BY u.lastaccess ASC
                 LIMIT 20";

        try {
            $records = $DB->get_records_sql($sql, array('lastaccess' => $thirtydays));

            foreach ($records as $record) {
                $users[] = array(
                    'id' => $record->id,
                    'fullname' => fullname($record),
                    'email' => $record->email,
                    'lastaccess' => $record->lastaccess > 0 ? 
                        userdate($record->lastaccess, get_string('strftimedatetime', 'langconfig')) : 
                        get_string('never'),
                    'daysinactive' => $record->lastaccess > 0 ? 
                        floor((time() - $record->lastaccess) / (24 * 60 * 60)) : 
                        get_string('never')
                );
            }
        } catch (\dml_exception $e) {
            debugging('Error fetching inactive users: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return $users;
    }

    /**
     * Get user's company ID.
     *
     * @param int $userid User ID
     * @return int|false Company ID or false if not found
     */
    protected function get_user_company($userid) {
        global $DB;

        if (!$this->is_iomad_installed()) {
            return false;
        }

        try {
            $companyuser = $DB->get_record('company_users', array('userid' => $userid), 'companyid', IGNORE_MULTIPLE);
            return $companyuser ? $companyuser->companyid : false;
        } catch (\dml_exception $e) {
            debugging('Error fetching user company: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Get list of users in company.
     *
     * @param int $companyid Company ID
     * @return array Array of user data
     */
    protected function get_company_users_list($companyid) {
        global $DB;

        if (!$companyid) {
            return array();
        }

        $users = array();

        $sql = "SELECT u.id, u.firstname, u.lastname, u.email, u.lastaccess
                  FROM {user} u
                  JOIN {company_users} cu ON cu.userid = u.id
                 WHERE cu.companyid = :companyid
                   AND u.deleted = 0
              ORDER BY u.lastname ASC, u.firstname ASC
                 LIMIT 10";

        try {
            $records = $DB->get_records_sql($sql, array('companyid' => $companyid));

            foreach ($records as $record) {
                $users[] = array(
                    'id' => $record->id,
                    'fullname' => fullname($record),
                    'email' => $record->email,
                    'lastaccess' => $record->lastaccess > 0 ? 
                        userdate($record->lastaccess, get_string('strftimedate', 'langconfig')) : 
                        get_string('never')
                );
            }
        } catch (\dml_exception $e) {
            debugging('Error fetching company users: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return $users;
    }

    /**
     * Get list of courses in company.
     *
     * @param int $companyid Company ID
     * @return array Array of course data
     */
    protected function get_company_courses_list($companyid) {
        global $DB;

        if (!$companyid) {
            return array();
        }

        $courses = array();

        $sql = "SELECT c.id, c.fullname, c.shortname, 
                       COUNT(DISTINCT ue.userid) as enrolledusers,
                       COUNT(DISTINCT cc.userid) as completedusers
                  FROM {course} c
                  JOIN {company_course} coc ON coc.courseid = c.id
                  LEFT JOIN {enrol} e ON e.courseid = c.id
                  LEFT JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.status = 0
                  LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.timecompleted > 0
                 WHERE coc.companyid = :companyid
              GROUP BY c.id, c.fullname, c.shortname
              ORDER BY c.fullname ASC
                 LIMIT 10";

        try {
            $records = $DB->get_records_sql($sql, array('companyid' => $companyid));

            foreach ($records as $record) {
                $completionrate = $record->enrolledusers > 0 ? 
                    round(($record->completedusers / $record->enrolledusers) * 100, 1) : 0;

                $courses[] = array(
                    'id' => $record->id,
                    'fullname' => format_string($record->fullname),
                    'shortname' => format_string($record->shortname),
                    'enrolledusers' => $record->enrolledusers,
                    'completedusers' => $record->completedusers,
                    'completionrate' => $completionrate
                );
            }
        } catch (\dml_exception $e) {
            debugging('Error fetching company courses: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return $courses;
    }

    /**
     * Get list of teacher's courses with statistics.
     *
     * @param int $userid Teacher user ID
     * @return array Array of course data
     */
    protected function get_teacher_courses_list($userid) {
        global $DB;

        $courses = array();

        $sql = "SELECT DISTINCT c.id, c.fullname, c.shortname,
                       COUNT(DISTINCT ue.userid) as enrolledstudents,
                       COUNT(DISTINCT cc.userid) as completedstudents
                  FROM {course} c
                  JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
                  JOIN {role_assignments} ra ON ra.contextid = ctx.id
                  JOIN {role} r ON r.id = ra.roleid
                  LEFT JOIN {enrol} e ON e.courseid = c.id
                  LEFT JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.status = 0
                  LEFT JOIN {role_assignments} ra2 ON ra2.contextid = ctx.id AND ra2.userid = ue.userid
                  LEFT JOIN {role} r2 ON r2.id = ra2.roleid AND r2.archetype = 'student'
                  LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = ue.userid AND cc.timecompleted > 0
                 WHERE ra.userid = :userid
                   AND r.archetype IN ('editingteacher', 'teacher')
              GROUP BY c.id, c.fullname, c.shortname
              ORDER BY c.fullname ASC
                 LIMIT 10";

        try {
            $records = $DB->get_records_sql($sql, array('userid' => $userid));

            foreach ($records as $record) {
                $completionrate = $record->enrolledstudents > 0 ? 
                    round(($record->completedstudents / $record->enrolledstudents) * 100, 1) : 0;

                $courses[] = array(
                    'id' => $record->id,
                    'fullname' => format_string($record->fullname),
                    'shortname' => format_string($record->shortname),
                    'enrolledstudents' => $record->enrolledstudents,
                    'completedstudents' => $record->completedstudents,
                    'completionrate' => $completionrate
                );
            }
        } catch (\dml_exception $e) {
            debugging('Error fetching teacher courses: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return $courses;
    }

    /**
     * Get student progress summary for teacher's courses.
     *
     * @param int $userid Teacher user ID
     * @return array Array of student progress data
     */
    protected function get_teacher_student_progress($userid) {
        global $DB;

        $students = array();

        $sql = "SELECT u.id, u.firstname, u.lastname, u.email,
                       COUNT(DISTINCT c.id) as enrolledcourses,
                       COUNT(DISTINCT cc.course) as completedcourses,
                       MAX(u.lastaccess) as lastaccess
                  FROM {user} u
                  JOIN {user_enrolments} ue ON ue.userid = u.id
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {course} c ON c.id = e.courseid
                  JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
                  JOIN {role_assignments} ra ON ra.contextid = ctx.id
                  JOIN {role} r ON r.id = ra.roleid
                  JOIN {role_assignments} ra2 ON ra2.contextid = ctx.id AND ra2.userid = u.id
                  JOIN {role} r2 ON r2.id = ra2.roleid
                  LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = u.id AND cc.timecompleted > 0
                 WHERE ra.userid = :userid
                   AND r.archetype IN ('editingteacher', 'teacher')
                   AND r2.archetype = 'student'
                   AND ue.status = 0
              GROUP BY u.id, u.firstname, u.lastname, u.email
              ORDER BY u.lastname ASC, u.firstname ASC
                 LIMIT 10";

        try {
            $records = $DB->get_records_sql($sql, array('userid' => $userid));

            foreach ($records as $record) {
                $progressrate = $record->enrolledcourses > 0 ? 
                    round(($record->completedcourses / $record->enrolledcourses) * 100, 1) : 0;

                $students[] = array(
                    'id' => $record->id,
                    'fullname' => fullname($record),
                    'email' => $record->email,
                    'enrolledcourses' => $record->enrolledcourses,
                    'completedcourses' => $record->completedcourses,
                    'progressrate' => $progressrate,
                    'lastaccess' => $record->lastaccess > 0 ? 
                        userdate($record->lastaccess, get_string('strftimedate', 'langconfig')) : 
                        get_string('never')
                );
            }
        } catch (\dml_exception $e) {
            debugging('Error fetching student progress: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return $students;
    }

    /**
     * Get recent activity in teacher's courses.
     *
     * @param int $userid Teacher user ID
     * @return array Array of recent activity data
     */
    protected function get_teacher_recent_activity($userid) {
        global $DB;

        $activities = array();

        // Get recent submissions.
        $sql = "SELECT s.id, u.firstname, u.lastname, c.fullname as coursename, 
                       a.name as activityname, s.timemodified, 'submission' as activitytype
                  FROM {assign_submission} s
                  JOIN {assign} a ON a.id = s.assignment
                  JOIN {course} c ON c.id = a.course
                  JOIN {user} u ON u.id = s.userid
                  JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
                  JOIN {role_assignments} ra ON ra.contextid = ctx.id
                  JOIN {role} r ON r.id = ra.roleid
                 WHERE ra.userid = :userid
                   AND r.archetype IN ('editingteacher', 'teacher')
                   AND s.status = 'submitted'
              ORDER BY s.timemodified DESC
                 LIMIT 10";

        try {
            $records = $DB->get_records_sql($sql, array('userid' => $userid));

            foreach ($records as $record) {
                $activities[] = array(
                    'id' => $record->id,
                    'studentname' => fullname($record),
                    'coursename' => format_string($record->coursename),
                    'activityname' => format_string($record->activityname),
                    'activitytype' => get_string('submission', 'local_manireports'),
                    'timemodified' => userdate($record->timemodified, get_string('strftimedatetime', 'langconfig'))
                );
            }
        } catch (\dml_exception $e) {
            debugging('Error fetching recent activity: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return $activities;
    }

    /**
     * Get dashboard data for API consumption
     *
     * Returns dashboard data in a format suitable for external API calls.
     *
     * @param string $dashboardtype Dashboard type (admin, manager, teacher, student)
     * @param int $userid User ID
     * @param array $filters Filter parameters
     * @param int $page Page number
     * @param int $pagesize Items per page
     * @return array Dashboard data
     */
    public function get_dashboard_data_for_api($dashboardtype, $userid, $filters = [], $page = 0, $pagesize = 25) {
        $data = [];
        
        switch ($dashboardtype) {
            case 'admin':
                $widgets = $this->get_admin_widgets($userid);
                $data['widgets'] = $this->format_widgets_for_api($widgets);
                $data['total'] = count($widgets);
                break;
                
            case 'manager':
                $widgets = $this->get_manager_widgets($userid);
                $data['widgets'] = $this->format_widgets_for_api($widgets);
                $data['total'] = count($widgets);
                break;
                
            case 'teacher':
                $widgets = $this->get_teacher_widgets($userid);
                $data['widgets'] = $this->format_widgets_for_api($widgets);
                $data['total'] = count($widgets);
                break;
                
            case 'student':
                $widgets = $this->get_student_widgets($userid);
                $data['widgets'] = $this->format_widgets_for_api($widgets);
                $data['total'] = count($widgets);
                break;
                
            default:
                throw new \moodle_exception('api:invaliddashboardtype', 'local_manireports');
        }
        
        return $data;
    }

    /**
     * Format widgets for API output
     *
     * @param array $widgets Widget data
     * @return array Formatted widgets
     */
    private function format_widgets_for_api($widgets) {
        $formatted = [];
        
        foreach ($widgets as $widget) {
            $formatted[] = [
                'type' => $widget['type'] ?? 'unknown',
                'title' => $widget['title'] ?? '',
                'data' => json_encode($widget['data'] ?? []),
            ];
        }
        
        return $formatted;
    }
}
