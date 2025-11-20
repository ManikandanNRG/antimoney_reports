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
 * xAPI Widget AJAX endpoint
 *
 * Returns xAPI engagement data for dashboard widgets.
 *
 * @package    local_manireports
 * @copyright  2024 ManiReports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');

require_login();

$userid = optional_param('userid', $USER->id, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);

$context = context_system::instance();

// Check capability.
if ($userid != $USER->id) {
    require_capability('local/manireports:viewadmindashboard', $context);
}

// Get xAPI data.
$xapi = new \local_manireports\api\xapi_integration();
$data = $xapi->get_xapi_widget_data($userid, $courseid);

// Return JSON.
header('Content-Type: application/json');
echo json_encode($data);
