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
 * Export handler for ManiReports.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_manireports\api\export_engine;
use local_manireports\api\audit_logger;

require_login();

$context = context_system::instance();

// Get parameters.
$reporttype = required_param('report', PARAM_ALPHANUMEXT);
$format = required_param('format', PARAM_ALPHA);

// Get filter parameters.
$courseid = optional_param('courseid', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$datefrom = optional_param('datefrom', '', PARAM_TEXT); // Date string from HTML input
$dateto = optional_param('dateto', '', PARAM_TEXT); // Date string from HTML input
$companyid = optional_param('companyid', 0, PARAM_INT);

// Convert date strings to timestamps if provided.
if (!empty($datefrom)) {
    $datefrom = strtotime($datefrom . ' 00:00:00');
}
if (!empty($dateto)) {
    $dateto = strtotime($dateto . ' 23:59:59');
}

// Build parameters array.
$params = array();
if ($courseid) {
    $params['courseid'] = $courseid;
}
if ($userid) {
    $params['userid'] = $userid;
}
if ($datefrom) {
    $params['datefrom'] = $datefrom;
}
if ($dateto) {
    $params['dateto'] = $dateto;
}
if ($companyid) {
    $params['companyid'] = $companyid;
}

// Check capability.
$hascapability = has_capability('local/manireports:viewadmindashboard', $context) ||
                 has_capability('local/manireports:viewmanagerdashboard', $context) ||
                 has_capability('local/manireports:viewteacherdashboard', $context) ||
                 has_capability('local/manireports:viewstudentdashboard', $context);

if (!$hascapability) {
    throw new moodle_exception('error:nopermission', 'local_manireports');
}

// Create report instance.
$reportclass = "\\local_manireports\\reports\\{$reporttype}";

if (!class_exists($reportclass)) {
    throw new moodle_exception('error:reportnotfound', 'local_manireports');
}

$report = new $reportclass($USER->id, $params);

// Check permission for this specific report.
if (!$report->has_permission($USER->id)) {
    throw new moodle_exception('error:nopermission', 'local_manireports');
}

// Execute report (get all data, no pagination for export).
try {
    $result = $report->execute(0, 999999);
} catch (Exception $e) {
    throw new moodle_exception('error:unexpectederror', 'local_manireports', '', $e->getMessage());
}

// Format all rows.
$formatted_data = array();
foreach ($result['data'] as $row) {
    $formatted_data[] = $report->format_row($row);
}

// Generate filename.
$filename = $report->get_export_filename($format);

// Export data.
$export_engine = new export_engine();

try {
    $file = $export_engine->export($formatted_data, $result['columns'], $format, pathinfo($filename, PATHINFO_FILENAME));
    
    // Log audit trail.
    audit_logger::log_report_export($reporttype, $format, count($formatted_data));
    
    // Send file to browser.
    send_stored_file($file, 0, 0, true);
    
} catch (Exception $e) {
    throw new moodle_exception('error:unexpectederror', 'local_manireports', '', $e->getMessage());
}
