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
 * External API for ManiReports integration
 *
 * Provides RESTful JSON API endpoints for external systems to retrieve
 * dashboard data, execute reports, and access metadata.
 *
 * @package    local_manireports
 * @copyright  2024 ManiReports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_manireports\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;
use context_system;
use moodle_exception;

/**
 * External API class for ManiReports
 *
 * Implements web service functions for external integration with BI tools
 * and mobile applications.
 */
class api extends external_api {

    /**
     * Returns description of get_dashboard_data parameters
     *
     * @return external_function_parameters
     */
    public static function get_dashboard_data_parameters() {
        return new external_function_parameters([
            'dashboardtype' => new external_value(PARAM_ALPHA, 'Dashboard type (admin, manager, teacher, student)'),
            'filters' => new external_single_structure([
                'companyid' => new external_value(PARAM_INT, 'Company ID for IOMAD filtering', VALUE_OPTIONAL),
                'courseid' => new external_value(PARAM_INT, 'Course ID filter', VALUE_OPTIONAL),
                'startdate' => new external_value(PARAM_INT, 'Start date timestamp', VALUE_OPTIONAL),
                'enddate' => new external_value(PARAM_INT, 'End date timestamp', VALUE_OPTIONAL),
            ], 'Filter parameters', VALUE_DEFAULT, []),
            'page' => new external_value(PARAM_INT, 'Page number for pagination', VALUE_DEFAULT, 0),
            'pagesize' => new external_value(PARAM_INT, 'Number of items per page', VALUE_DEFAULT, 25),
        ]);
    }

    /**
     * Get dashboard data for external integration
     *
     * @param string $dashboardtype Dashboard type
     * @param array $filters Filter parameters
     * @param int $page Page number
     * @param int $pagesize Items per page
     * @return array Dashboard data with widgets
     */
    public static function get_dashboard_data($dashboardtype, $filters = [], $page = 0, $pagesize = 25) {
        global $USER;

        // Validate parameters.
        $params = self::validate_parameters(self::get_dashboard_data_parameters(), [
            'dashboardtype' => $dashboardtype,
            'filters' => $filters,
            'page' => $page,
            'pagesize' => $pagesize,
        ]);

        // Validate context.
        $context = context_system::instance();
        self::validate_context($context);

        // Check capability based on dashboard type.
        $capability = self::get_dashboard_capability($params['dashboardtype']);
        require_capability($capability, $context);

        // Limit page size.
        $params['pagesize'] = min($params['pagesize'], 100);

        try {
            // Get dashboard renderer.
            $renderer = new \local_manireports\output\dashboard_renderer();
            
            // Get dashboard data based on type.
            $data = $renderer->get_dashboard_data_for_api(
                $params['dashboardtype'],
                $USER->id,
                $params['filters'],
                $params['page'],
                $params['pagesize']
            );

            return [
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'page' => $params['page'],
                    'pagesize' => $params['pagesize'],
                    'total' => $data['total'] ?? 0,
                    'totalpages' => ceil(($data['total'] ?? 0) / $params['pagesize']),
                ],
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'errorcode' => $e->getCode(),
            ];
        }
    }

    /**
     * Returns description of get_dashboard_data return value
     *
     * @return external_single_structure
     */
    public static function get_dashboard_data_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'data' => new external_single_structure([
                'widgets' => new external_multiple_structure(
                    new external_single_structure([
                        'type' => new external_value(PARAM_ALPHA, 'Widget type'),
                        'title' => new external_value(PARAM_TEXT, 'Widget title'),
                        'data' => new external_value(PARAM_RAW, 'Widget data as JSON'),
                    ]),
                    'Dashboard widgets',
                    VALUE_OPTIONAL
                ),
                'total' => new external_value(PARAM_INT, 'Total items', VALUE_OPTIONAL),
            ], 'Dashboard data', VALUE_OPTIONAL),
            'pagination' => new external_single_structure([
                'page' => new external_value(PARAM_INT, 'Current page'),
                'pagesize' => new external_value(PARAM_INT, 'Items per page'),
                'total' => new external_value(PARAM_INT, 'Total items'),
                'totalpages' => new external_value(PARAM_INT, 'Total pages'),
            ], 'Pagination metadata', VALUE_OPTIONAL),
            'error' => new external_value(PARAM_TEXT, 'Error message', VALUE_OPTIONAL),
            'errorcode' => new external_value(PARAM_INT, 'Error code', VALUE_OPTIONAL),
        ]);
    }

    /**
     * Returns description of get_report_data parameters
     *
     * @return external_function_parameters
     */
    public static function get_report_data_parameters() {
        return new external_function_parameters([
            'reportid' => new external_value(PARAM_INT, 'Report ID'),
            'parameters' => new external_single_structure([
                'companyid' => new external_value(PARAM_INT, 'Company ID', VALUE_OPTIONAL),
                'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_OPTIONAL),
                'userid' => new external_value(PARAM_INT, 'User ID', VALUE_OPTIONAL),
                'startdate' => new external_value(PARAM_INT, 'Start date timestamp', VALUE_OPTIONAL),
                'enddate' => new external_value(PARAM_INT, 'End date timestamp', VALUE_OPTIONAL),
            ], 'Report parameters', VALUE_DEFAULT, []),
            'page' => new external_value(PARAM_INT, 'Page number for pagination', VALUE_DEFAULT, 0),
            'pagesize' => new external_value(PARAM_INT, 'Number of items per page', VALUE_DEFAULT, 25),
        ]);
    }

    /**
     * Execute a report and return data
     *
     * @param int $reportid Report ID
     * @param array $parameters Report parameters
     * @param int $page Page number
     * @param int $pagesize Items per page
     * @return array Report data
     */
    public static function get_report_data($reportid, $parameters = [], $page = 0, $pagesize = 25) {
        global $DB;

        // Validate parameters.
        $params = self::validate_parameters(self::get_report_data_parameters(), [
            'reportid' => $reportid,
            'parameters' => $parameters,
            'page' => $page,
            'pagesize' => $pagesize,
        ]);

        // Validate context.
        $context = context_system::instance();
        self::validate_context($context);

        // Check capability.
        require_capability('local/manireports:managereports', $context);

        // Limit page size.
        $params['pagesize'] = min($params['pagesize'], 100);

        try {
            // Get report.
            $report = $DB->get_record('manireports_customreports', ['id' => $params['reportid']], '*', MUST_EXIST);

            // Execute report.
            $builder = new \local_manireports\api\report_builder();
            $result = $builder->execute_report($params['reportid'], $params['parameters']);

            // Apply pagination.
            $offset = $params['page'] * $params['pagesize'];
            $total = count($result['data']);
            $paginateddata = array_slice($result['data'], $offset, $params['pagesize']);

            return [
                'success' => true,
                'report' => [
                    'id' => $report->id,
                    'name' => $report->name,
                    'description' => $report->description,
                ],
                'columns' => $result['columns'],
                'data' => $paginateddata,
                'pagination' => [
                    'page' => $params['page'],
                    'pagesize' => $params['pagesize'],
                    'total' => $total,
                    'totalpages' => ceil($total / $params['pagesize']),
                ],
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'errorcode' => $e->getCode(),
            ];
        }
    }

    /**
     * Returns description of get_report_data return value
     *
     * @return external_single_structure
     */
    public static function get_report_data_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'report' => new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Report ID'),
                'name' => new external_value(PARAM_TEXT, 'Report name'),
                'description' => new external_value(PARAM_TEXT, 'Report description'),
            ], 'Report information', VALUE_OPTIONAL),
            'columns' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Column name'),
                'Column names',
                VALUE_OPTIONAL
            ),
            'data' => new external_multiple_structure(
                new external_value(PARAM_RAW, 'Row data as JSON'),
                'Report data rows',
                VALUE_OPTIONAL
            ),
            'pagination' => new external_single_structure([
                'page' => new external_value(PARAM_INT, 'Current page'),
                'pagesize' => new external_value(PARAM_INT, 'Items per page'),
                'total' => new external_value(PARAM_INT, 'Total items'),
                'totalpages' => new external_value(PARAM_INT, 'Total pages'),
            ], 'Pagination metadata', VALUE_OPTIONAL),
            'error' => new external_value(PARAM_TEXT, 'Error message', VALUE_OPTIONAL),
            'errorcode' => new external_value(PARAM_INT, 'Error code', VALUE_OPTIONAL),
        ]);
    }

    /**
     * Returns description of get_report_metadata parameters
     *
     * @return external_function_parameters
     */
    public static function get_report_metadata_parameters() {
        return new external_function_parameters([
            'reportid' => new external_value(PARAM_INT, 'Report ID', VALUE_OPTIONAL),
        ]);
    }

    /**
     * Get metadata about a report or all reports
     *
     * @param int|null $reportid Report ID (optional)
     * @return array Report metadata
     */
    public static function get_report_metadata($reportid = null) {
        global $DB;

        // Validate parameters.
        $params = self::validate_parameters(self::get_report_metadata_parameters(), [
            'reportid' => $reportid,
        ]);

        // Validate context.
        $context = context_system::instance();
        self::validate_context($context);

        // Check capability.
        require_capability('local/manireports:managereports', $context);

        try {
            if ($params['reportid']) {
                // Get single report metadata.
                $report = $DB->get_record('manireports_customreports', ['id' => $params['reportid']], '*', MUST_EXIST);
                
                $metadata = [
                    'id' => $report->id,
                    'name' => $report->name,
                    'description' => $report->description,
                    'type' => $report->type,
                    'timecreated' => $report->timecreated,
                    'timemodified' => $report->timemodified,
                ];

                // Get parameter info if available.
                if ($report->type === 'sql' && !empty($report->sqlquery)) {
                    preg_match_all('/:([a-zA-Z0-9_]+)/', $report->sqlquery, $matches);
                    $metadata['parameters'] = array_unique($matches[1]);
                }

                return [
                    'success' => true,
                    'report' => $metadata,
                ];

            } else {
                // Get all reports metadata.
                $reports = $DB->get_records('manireports_customreports', null, 'name ASC');
                
                $reportsdata = [];
                foreach ($reports as $report) {
                    $reportsdata[] = [
                        'id' => $report->id,
                        'name' => $report->name,
                        'description' => $report->description,
                        'type' => $report->type,
                        'timecreated' => $report->timecreated,
                        'timemodified' => $report->timemodified,
                    ];
                }

                return [
                    'success' => true,
                    'reports' => $reportsdata,
                    'total' => count($reportsdata),
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'errorcode' => $e->getCode(),
            ];
        }
    }

    /**
     * Returns description of get_report_metadata return value
     *
     * @return external_single_structure
     */
    public static function get_report_metadata_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'report' => new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Report ID'),
                'name' => new external_value(PARAM_TEXT, 'Report name'),
                'description' => new external_value(PARAM_TEXT, 'Report description'),
                'type' => new external_value(PARAM_ALPHA, 'Report type'),
                'timecreated' => new external_value(PARAM_INT, 'Creation timestamp'),
                'timemodified' => new external_value(PARAM_INT, 'Modification timestamp'),
                'parameters' => new external_multiple_structure(
                    new external_value(PARAM_TEXT, 'Parameter name'),
                    'Report parameters',
                    VALUE_OPTIONAL
                ),
            ], 'Single report metadata', VALUE_OPTIONAL),
            'reports' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Report ID'),
                    'name' => new external_value(PARAM_TEXT, 'Report name'),
                    'description' => new external_value(PARAM_TEXT, 'Report description'),
                    'type' => new external_value(PARAM_ALPHA, 'Report type'),
                    'timecreated' => new external_value(PARAM_INT, 'Creation timestamp'),
                    'timemodified' => new external_value(PARAM_INT, 'Modification timestamp'),
                ]),
                'All reports metadata',
                VALUE_OPTIONAL
            ),
            'total' => new external_value(PARAM_INT, 'Total reports', VALUE_OPTIONAL),
            'error' => new external_value(PARAM_TEXT, 'Error message', VALUE_OPTIONAL),
            'errorcode' => new external_value(PARAM_INT, 'Error code', VALUE_OPTIONAL),
        ]);
    }

    /**
     * Returns description of get_available_reports parameters
     *
     * @return external_function_parameters
     */
    public static function get_available_reports_parameters() {
        return new external_function_parameters([]);
    }

    /**
     * Get list of available reports for the current user
     *
     * @return array Available reports
     */
    public static function get_available_reports() {
        global $DB, $USER;

        // Validate parameters.
        $params = self::validate_parameters(self::get_available_reports_parameters(), []);

        // Validate context.
        $context = context_system::instance();
        self::validate_context($context);

        try {
            $reports = [];

            // Add prebuilt reports.
            $prebuilt = [
                'course_completion' => 'Course Completion Report',
                'course_progress' => 'Course Progress Report',
                'scorm_summary' => 'SCORM Summary Report',
                'user_engagement' => 'User Engagement Report',
                'quiz_attempts' => 'Quiz Attempts Report',
            ];

            foreach ($prebuilt as $key => $name) {
                $reports[] = [
                    'id' => 0,
                    'name' => $name,
                    'type' => 'prebuilt',
                    'key' => $key,
                ];
            }

            // Add custom reports if user has permission.
            if (has_capability('local/manireports:customreports', $context)) {
                $customreports = $DB->get_records('manireports_customreports', null, 'name ASC');
                
                foreach ($customreports as $report) {
                    $reports[] = [
                        'id' => $report->id,
                        'name' => $report->name,
                        'type' => 'custom',
                        'key' => 'custom_' . $report->id,
                    ];
                }
            }

            return [
                'success' => true,
                'reports' => $reports,
                'total' => count($reports),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'errorcode' => $e->getCode(),
            ];
        }
    }

    /**
     * Returns description of get_available_reports return value
     *
     * @return external_single_structure
     */
    public static function get_available_reports_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'reports' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Report ID'),
                    'name' => new external_value(PARAM_TEXT, 'Report name'),
                    'type' => new external_value(PARAM_ALPHA, 'Report type'),
                    'key' => new external_value(PARAM_TEXT, 'Report key'),
                ]),
                'Available reports',
                VALUE_OPTIONAL
            ),
            'total' => new external_value(PARAM_INT, 'Total reports', VALUE_OPTIONAL),
            'error' => new external_value(PARAM_TEXT, 'Error message', VALUE_OPTIONAL),
            'errorcode' => new external_value(PARAM_INT, 'Error code', VALUE_OPTIONAL),
        ]);
    }

    /**
     * Get dashboard capability based on type
     *
     * @param string $type Dashboard type
     * @return string Capability name
     */
    private static function get_dashboard_capability($type) {
        $capabilities = [
            'admin' => 'local/manireports:viewadmindashboard',
            'manager' => 'local/manireports:viewmanagerdashboard',
            'teacher' => 'local/manireports:viewteacherdashboard',
            'student' => 'local/manireports:viewstudentdashboard',
        ];

        return $capabilities[$type] ?? 'local/manireports:viewstudentdashboard';
    }
}
