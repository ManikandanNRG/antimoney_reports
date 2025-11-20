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
 * Web service definitions for ManiReports
 *
 * @package    local_manireports
 * @copyright  2024 ManiReports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_manireports_get_table_columns' => [
        'classname'   => 'local_manireports\external\gui_builder',
        'methodname'  => 'get_table_columns',
        'classpath'   => '',
        'description' => 'Get columns for a table in GUI report builder',
        'type'        => 'read',
        'ajax'        => true,
        'loginrequired' => true,
    ],
    'local_manireports_build_sql_preview' => [
        'classname'   => 'local_manireports\external\gui_builder',
        'methodname'  => 'build_sql_preview',
        'classpath'   => '',
        'description' => 'Build SQL preview from GUI configuration',
        'type'        => 'read',
        'ajax'        => true,
        'loginrequired' => true,
    ],
    // External API endpoints for integration (Phase 3)
    'local_manireports_get_dashboard_data' => [
        'classname'   => 'local_manireports\external\api',
        'methodname'  => 'get_dashboard_data',
        'classpath'   => '',
        'description' => 'Get dashboard data for external integration',
        'type'        => 'read',
        'ajax'        => false,
        'capabilities' => 'local/manireports:viewadmindashboard,local/manireports:viewmanagerdashboard,local/manireports:viewteacherdashboard,local/manireports:viewstudentdashboard',
        'loginrequired' => true,
    ],
    'local_manireports_get_report_data' => [
        'classname'   => 'local_manireports\external\api',
        'methodname'  => 'get_report_data',
        'classpath'   => '',
        'description' => 'Execute a report and return data for external integration',
        'type'        => 'read',
        'ajax'        => false,
        'capabilities' => 'local/manireports:managereports',
        'loginrequired' => true,
    ],
    'local_manireports_get_report_metadata' => [
        'classname'   => 'local_manireports\external\api',
        'methodname'  => 'get_report_metadata',
        'classpath'   => '',
        'description' => 'Get metadata about available reports',
        'type'        => 'read',
        'ajax'        => false,
        'capabilities' => 'local/manireports:managereports',
        'loginrequired' => true,
    ],
    'local_manireports_get_available_reports' => [
        'classname'   => 'local_manireports\external\api',
        'methodname'  => 'get_available_reports',
        'classpath'   => '',
        'description' => 'Get list of available reports for the current user',
        'type'        => 'read',
        'ajax'        => false,
        'loginrequired' => true,
    ],
];
