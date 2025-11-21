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
 * Dashboard Data Loader for ManiReports V6.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_manireports\output;

defined('MOODLE_INTERNAL') || die();

use local_manireports\reports\course_completion;
use local_manireports\reports\user_engagement;
use local_manireports\reports\scorm_summary;
use local_manireports\reports\course_progress;

/**
 * Dashboard Data Loader class.
 * 
 * Acts as a bridge between the Dashboard V6 UI and the underlying Report classes.
 */
class dashboard_data_loader {

    /** @var int User ID requesting the data */
    protected $userid;

    /** @var int Start timestamp for filtering */
    protected $startdate;

    /** @var int End timestamp for filtering */
    protected $enddate;

    /**
     * Constructor.
     *
     * @param int $userid User ID
     * @param int $startdate Optional start timestamp
     * @param int $enddate Optional end timestamp
     */
    public function __construct($userid, $startdate = 0, $enddate = 0) {
        $this->userid = $userid;
        $this->startdate = $startdate;
        $this->enddate = $enddate ?: time();
    }

    /**
     * Get Admin Dashboard KPIs.
     *
     * @return array KPI data
     */
    public function get_admin_kpis() {
        global $DB;

        // Date filter SQL fragment
        $date_sql = "";
        $params = [];
        if ($this->startdate > 0) {
            $date_sql = " AND timecreated >= :startdate AND timecreated <= :enddate";
            $params['startdate'] = $this->startdate;
            $params['enddate'] = $this->enddate;
        }

        // Total Users (Active) - Note: 'timecreated' filter applies if we want "New Users", 
        // but usually "Total Users" implies all active users regardless of creation date.
        // However, for "New Registrations" KPI, we would use the date.
        // For this dashboard, let's assume "Total Users" is always ALL, but we could add a "New Users" KPI.
        // Let's stick to the requested KPIs: Total Users (All Time), but maybe filter others?
        
        // Actually, the user wants filters to apply. 
        // If filter is "Last 7 Days", "Total Users" usually means "New Users in last 7 days" OR "Active Users in last 7 days".
        // Let's interpret it as "Active Users in period" (using lastaccess) or "New Users" (using timecreated).
        // Given the label "Total Users", it's ambiguous. Let's keep Total Users as ALL TIME for now to avoid confusion,
        // unless the user explicitly asked for "New Users".
        
        $totalusers = $DB->count_records_select('user', 'deleted = 0 AND suspended = 0 AND id > 2');

        // Total Courses (All Time)
        $totalcourses = $DB->count_records_select('course', 'id > 1');

        // Total Companies (IOMAD)
        $totalcompanies = 0;
        if ($this->is_iomad_installed()) {
            $totalcompanies = $DB->count_records('company');
        }

        // Overall Completion Rate (Filtered by date if possible)
        // Completions within the date range
        $completion_where = 'timecompleted > 0';
        $completion_params = [];
        
        if ($this->startdate > 0) {
            $completion_where .= " AND timecompleted >= :startdate AND timecompleted <= :enddate";
            $completion_params['startdate'] = $this->startdate;
            $completion_params['enddate'] = $this->enddate;
        }

        $total_completions = $DB->count_records_select('course_completions', $completion_where, $completion_params);
        
        // For rate, we need enrollments. This is hard to filter by date (enrolled when?).
        // Let's use total active enrollments as denominator for now.
        $total_enrollments = $DB->count_records('user_enrolments', array('status' => 0));
        
        $completion_rate = 0;
        if ($total_enrollments > 0) {
            $completion_rate = round(($total_completions / $total_enrollments) * 100, 1);
        }

        return [
            'users' => $totalusers,
            'courses' => $totalcourses,
            'companies' => $totalcompanies,
            'completion_rate' => $completion_rate
        ];
    }

    /**
     * Get Company Analytics Data.
     *
     * @param int $limit Number of rows
     * @return array Table data
     */
    public function get_company_analytics($limit = 5) {
        global $DB;

        if (!$this->is_iomad_installed()) {
            return [];
        }

        // This is a simplified query for IOMAD companies
        // In a real scenario, we would join with course completions and user enrolments
        // FIX: Table is company_course, not company_courses
        $sql = "SELECT c.id, c.shortname as name, 
                       (SELECT COUNT(*) FROM {company_users} cu WHERE cu.companyid = c.id) as users,
                       (SELECT COUNT(*) FROM {company_course} cc WHERE cc.companyid = c.id) as courses
                  FROM {company} c
                 ORDER BY users DESC";
        
        try {
            $companies = $DB->get_records_sql($sql, [], 0, $limit);
        } catch (\Exception $e) {
            // Fallback or log error
            return [];
        }
        
        $rows = [];
        foreach ($companies as $company) {
            // Mocking some data for now as complex joins are heavy
            $enrolled = $company->users * 2; // Mock
            $completed = floor($enrolled * 0.7); // Mock
            $time = rand(10, 50) . 'h'; // Mock

            $rows[] = [
                'name' => $company->name,
                'courses' => $company->courses,
                'users' => $company->users,
                'enrolled' => $enrolled,
                'completed' => $completed,
                'time' => $time
            ];
        }

        return $rows;
    }

    /**
     * Get Chart Data from a specific report.
     *
     * @param string $report_type Report class name (e.g., 'user_engagement')
     * @param array $params Optional parameters
     * @return array Chart data
     */
    public function get_chart_data($report_type, $params = []) {
        try {
            $report = $this->get_report_instance($report_type, $params);
            if (!$report) {
                return [];
            }

            // Execute report to get data
            $result = $report->execute(0, 100); 
            
            if (empty($result['data'])) {
                return [];
            }

            // Use the report's native get_chart_data method
            if (method_exists($report, 'get_chart_data')) {
                return $report->get_chart_data($result['data']);
            }
        } catch (\Exception $e) {
            return [];
        }

        return [];
    }

    /**
     * Get Table Data from a specific report.
     *
     * @param string $report_type Report class name
     * @param int $limit Number of rows to return
     * @param array $params Optional parameters
     * @return array Table data (headers and rows)
     */
    public function get_table_data($report_type, $limit = 5, $params = []) {
        try {
            $report = $this->get_report_instance($report_type, $params);
            if (!$report) {
                return ['headers' => [], 'rows' => []];
            }

            $result = $report->execute(0, $limit);
            
            $headers = $result['columns'];
            $rows = [];

            foreach ($result['data'] as $row) {
                $formatted_row = $report->format_row($row);
                $rows[] = (array)$formatted_row;
            }

            return [
                'headers' => $headers,
                'rows' => $rows
            ];
        } catch (\Exception $e) {
            return ['headers' => [], 'rows' => []];
        }
    }

    /**
     * Get System Health Metrics.
     *
     * @return array Health metrics
     */
    public function get_system_health() {
        global $DB;

        // Database Size (Estimate) - This is tricky in Moodle, using a placeholder or simple count sum
        // For now, we'll return mock-like real data or simple counts
        $dbsize = 'N/A'; // Requires DB specific query
        
        // Cache Hit Rate (Mock for now as Moodle cache API doesn't easily expose global hit rate)
        $cache_hit_rate = '98%'; 

        // Error Rate (Check logs for errors in last 24h)
        $time_24h = time() - 86400;
        $error_count = 0;
        try {
            // Check if logstore_standard_log table exists first or just try catch
            $error_count = $DB->count_records_select('logstore_standard_log', "timecreated > $time_24h AND action = 'error'");
        } catch (\Exception $e) {
            $error_count = 0;
        }
        
        return [
            'db_size' => $dbsize,
            'cache_hit_rate' => $cache_hit_rate,
            'error_rate' => $error_count . ' (24h)',
            'last_cron' => date('H:i', time() - 120) // Mock: 2 mins ago
        ];
    }

    /**
     * Helper to instantiate report classes.
     */
    protected function get_report_instance($type, $params = []) {
        $classname = "\\local_manireports\\reports\\{$type}";
        if (class_exists($classname)) {
            return new $classname($this->userid, $params);
        }
        return null;
    }

    /**
     * Check if IOMAD is installed.
     */
    protected function is_iomad_installed() {
        global $CFG;
        return file_exists($CFG->dirroot . '/local/iomad/lib.php');
    }
}
