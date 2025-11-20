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
 * AJAX endpoint for time tracking heartbeat.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');

use local_manireports\api\time_engine;

require_login();
require_sesskey();

$courseid = required_param('courseid', PARAM_INT);
$timestamp = required_param('timestamp', PARAM_INT);

try {
    // Verify user is enrolled in course.
    $context = context_course::instance($courseid);
    require_capability('moodle/course:view', $context);

    // Check if time tracking is enabled
    $enabled = get_config('local_manireports', 'enabletimetracking');
    if (!$enabled) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(array(
            'success' => false,
            'error' => 'Time tracking is not enabled'
        ));
        exit;
    }

    // Record heartbeat.
    $engine = new time_engine();
    $success = $engine->record_heartbeat($USER->id, $courseid, $timestamp);

    // Return JSON response.
    header('Content-Type: application/json');
    echo json_encode(array(
        'success' => $success,
        'timestamp' => time(),
        'userid' => $USER->id,
        'courseid' => $courseid,
        'time_tracking_enabled' => (bool)$enabled
    ));
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(array(
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ));
}
