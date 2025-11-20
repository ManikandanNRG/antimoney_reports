<?php
require_once(__DIR__ . '/../../../../config.php');
require_login();

if (!is_siteadmin()) {
    die(json_encode(['success' => false, 'error' => 'Admin access required']));
}

global $DB;

// Check if time tracking is enabled
$enabled = get_config('local_manireports', 'enabletimetracking');
$heartbeat_interval = get_config('local_manireports', 'heartbeatinterval');
$session_timeout = get_config('local_manireports', 'sessiontimeout');

// Check if there are any sessions in the database
$session_count = $DB->count_records('manireports_time_sessions');
$daily_count = $DB->count_records('manireports_time_daily');

// Get recent sessions
$recent_sessions = $DB->get_records('manireports_time_sessions', [], 'lastupdated DESC', '*', 0, 5);

// Convert to array for JSON encoding
$sample_sessions = [];
foreach (array_slice($recent_sessions, 0, 3) as $session) {
    $sample_sessions[] = [
        'id' => $session->id,
        'userid' => $session->userid,
        'courseid' => $session->courseid,
        'sessionstart' => $session->sessionstart,
        'lastupdated' => $session->lastupdated,
        'duration' => $session->duration
    ];
}

header('Content-Type: application/json');
echo json_encode([
    'time_tracking_enabled' => (bool)$enabled,
    'heartbeat_interval' => $heartbeat_interval ?: 'NOT SET',
    'session_timeout' => $session_timeout ?: 'NOT SET',
    'total_sessions' => $session_count,
    'total_daily_records' => $daily_count,
    'recent_sessions' => count($recent_sessions),
    'sample_sessions' => $sample_sessions
]);
