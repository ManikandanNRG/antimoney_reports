<?php
/**
 * CLI script to assign capabilities to roles.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');

global $DB;

echo "=== Assigning ManiReports Capabilities to Roles ===\n\n";

// Get all roles
$roles = $DB->get_records('role');

$capabilities = [
    'local/manireports:viewstudentdashboard' => ['student'],
    'local/manireports:viewteacherdashboard' => ['editingteacher', 'teacher'],
    'local/manireports:viewmanagerdashboard' => ['manager'],
    'local/manireports:viewadmindashboard' => ['manager'],
    'local/manireports:managereports' => ['manager'],
    'local/manireports:schedule' => ['manager'],
];

$context = context_system::instance();

foreach ($capabilities as $capability => $archetypes) {
    echo "Capability: {$capability}\n";
    
    foreach ($archetypes as $archetype) {
        // Find role by archetype
        $role = $DB->get_record('role', ['shortname' => $archetype]);
        
        if ($role) {
            // Assign capability to role
            role_change_permission($role->id, $context, $capability, CAP_ALLOW);
            echo "  ✓ Assigned to {$archetype} role\n";
        } else {
            echo "  ✗ Role '{$archetype}' not found\n";
        }
    }
    echo "\n";
}

echo "✓ Capability assignment complete\n";
