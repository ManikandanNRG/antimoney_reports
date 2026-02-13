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

// Return JSON response consistently
header('Content-Type: application/json');

try {
    // 1. Get Parameters first (needed for login check)
    $courseid = required_param('courseid', PARAM_INT);
    $timestamp = required_param('timestamp', PARAM_INT);

    // 2. Authenticate User & Load Course Context
    // Crucial: Passing $courseid loads the specific course capabilities/enrolment.
    // Without this, capabilities like 'moodle/course:view' are checked against System context only, causing failures.
    require_login($courseid); 

    // 3. Validate Session Key (Graceful Check)
    if (!confirm_sesskey()) {
        echo json_encode(['success' => false, 'error' => 'Invalid session key']);
        exit;
    }

    // 4. Check if time tracking is enabled
    $enabled = get_config('local_manireports', 'enabletimetracking');
    if (!$enabled) {
        // error_log('Heartbeat: Time tracking disabled'); // Optional: uncomment if needed
        echo json_encode(array(
            'success' => false,
            'error' => 'Time tracking is not enabled'
        ));
        exit;
    }

    // 5. Record heartbeat.
    $engine = new time_engine();
    $success = $engine->record_heartbeat($USER->id, $courseid, $timestamp);

    // Success Response
    echo json_encode(array(
        'success' => $success,
        'timestamp' => time(),
        'userid' => $USER->id,
        'courseid' => $courseid,
        'time_tracking_enabled' => (bool)$enabled
    ));

} catch (Exception $e) {
    // Log the actual error for debugging
    error_log('Heartbeat Exception: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
    
    // Return 200 OK with error details (to avoid console red/400 errors)
    echo json_encode(array(
        'success' => false,
        'error' => $e->getMessage()
    ));
}
