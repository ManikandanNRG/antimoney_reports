<?php
/**
 * CLI script to enable time tracking and verify settings.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');

global $DB;

echo "=== ManiReports Time Tracking Configuration ===\n\n";

// Enable time tracking
set_config('enabletimetracking', 1, 'local_manireports');
echo "✓ Time tracking enabled\n";

// Set heartbeat interval (25 seconds)
set_config('heartbeatinterval', 25, 'local_manireports');
echo "✓ Heartbeat interval set to 25 seconds\n";

// Set session timeout (10 minutes)
set_config('sessiontimeout', 10, 'local_manireports');
echo "✓ Session timeout set to 10 minutes\n";

// Verify settings
echo "\n=== Verification ===\n";
$enabled = get_config('local_manireports', 'enabletimetracking');
$heartbeat = get_config('local_manireports', 'heartbeatinterval');
$timeout = get_config('local_manireports', 'sessiontimeout');

echo "Time Tracking Enabled: " . ($enabled ? "YES ✓" : "NO ✗") . "\n";
echo "Heartbeat Interval: " . ($heartbeat ?: "NOT SET") . " seconds\n";
echo "Session Timeout: " . ($timeout ?: "NOT SET") . " minutes\n";

// Check database tables
echo "\n=== Database Tables ===\n";
$tables_exist = [
    'manireports_time_sessions' => $DB->get_manager()->table_exists('manireports_time_sessions'),
    'manireports_time_daily' => $DB->get_manager()->table_exists('manireports_time_daily')
];

foreach ($tables_exist as $table => $exists) {
    echo $table . ": " . ($exists ? "EXISTS ✓" : "MISSING ✗") . "\n";
}

// Check for existing sessions
$session_count = $DB->count_records('manireports_time_sessions');
$daily_count = $DB->count_records('manireports_time_daily');

echo "\n=== Current Data ===\n";
echo "Active Sessions: " . $session_count . "\n";
echo "Daily Records: " . $daily_count . "\n";

echo "\n✓ Time tracking configuration complete!\n";
echo "Heartbeat requests should now create session records.\n";
