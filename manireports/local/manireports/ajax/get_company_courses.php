<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * AJAX endpoint to get courses for a specific company
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');

require_login();
require_sesskey();

$companyid = required_param('companyid', PARAM_INT);

// Get courses for this company from IOMAD
$sql = "SELECT c.id, c.fullname
        FROM {course} c
        JOIN {company_course} cc ON cc.courseid = c.id
        WHERE cc.companyid = :companyid
        AND c.id != :siteid
        ORDER BY c.fullname";

$courses = $DB->get_records_sql_menu($sql, [
    'companyid' => $companyid,
    'siteid' => SITEID
]);

// Return as HTML options
$html = '<option value="">'.get_string('selectcourse', 'local_manireports').'</option>';
foreach ($courses as $id => $name) {
    $html .= '<option value="' . $id . '">' . s($name) . '</option>';
}

echo $html;
