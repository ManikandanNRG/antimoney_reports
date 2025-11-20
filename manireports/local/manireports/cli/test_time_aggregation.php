<?php
/**
 * CLI script to test time aggregation for today's data.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');

use local_manireports\api\time_engine;

global $DB;

echo "=== Time Aggregation Test ===\n\n";

// Get active sessions
$sessions = $DB->get_records('manireports_time_sessions');
echo "Active sessions: " . count($sessions) . "\n";

if (count($sessions) > 0) {
    echo "\nSample sessions:\n";
    foreach (array_slice($sessions, 0, 3) as $session) {
        echo "  User {$session->userid}, Course {$session->courseid}: " . 
             date('Y-m-d H:i:s', $session->sessionstart) . " - " . 
             date('Y-m-d H:i:s', $session->lastupdated) . "\n";
    }
}

// Aggregate today's data
echo "\n--- Aggregating today's data ---\n";
$engine = new time_engine();
$today = date('Y-m-d');
$aggregated = $engine->aggregate_daily_time($today);
echo "Aggregated {$aggregated} sessions for {$today}\n";

// Check daily summaries
echo "\n--- Daily Summaries ---\n";
$daily = $DB->get_records('manireports_time_daily', ['date' => $today]);
echo "Daily records for {$today}: " . count($daily) . "\n";

if (count($daily) > 0) {
    echo "\nSample daily summaries:\n";
    foreach (array_slice($daily, 0, 3) as $record) {
        $hours = floor($record->duration / 3600);
        $minutes = floor(($record->duration % 3600) / 60);
        echo "  User {$record->userid}, Course {$record->courseid}: " . 
             "{$hours}h {$minutes}m ({$record->duration}s), {$record->sessioncount} sessions\n";
    }
}

echo "\n✓ Time aggregation test complete\n";
