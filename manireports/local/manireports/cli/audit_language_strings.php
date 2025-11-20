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
 * CLI script to audit language strings in ManiReports.
 *
 * @package    local_manireports
 * @copyright  2024 ManiReports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

cli_heading('ManiReports - Language String Audit');

// Load language strings.
$langfile = $CFG->dirroot . '/local/manireports/lang/en/local_manireports.php';
$string = [];
include($langfile);

cli_writeln('Total language strings defined: ' . count($string));
cli_writeln('');

// Categorize strings.
$categories = [
    'Plugin' => [],
    'Capabilities' => [],
    'Settings' => [],
    'Dashboard' => [],
    'Reports' => [],
    'Schedules' => [],
    'Audit' => [],
    'At-Risk' => [],
    'xAPI' => [],
    'Performance' => [],
    'Security' => [],
    'Errors' => [],
    'Other' => [],
];

foreach ($string as $key => $value) {
    if (strpos($key, 'manireports:') === 0) {
        $categories['Capabilities'][] = $key;
    } else if (strpos($key, 'settings') !== false || strpos($key, 'ttl') !== false || 
               strpos($key, 'timeout') !== false || strpos($key, 'retention') !== false) {
        $categories['Settings'][] = $key;
    } else if (strpos($key, 'dashboard') !== false) {
        $categories['Dashboard'][] = $key;
    } else if (strpos($key, 'report') !== false || strpos($key, 'query') !== false) {
        $categories['Reports'][] = $key;
    } else if (strpos($key, 'schedule') !== false) {
        $categories['Schedules'][] = $key;
    } else if (strpos($key, 'audit') !== false) {
        $categories['Audit'][] = $key;
    } else if (strpos($key, 'atrisk') !== false || strpos($key, 'risk') !== false) {
        $categories['At-Risk'][] = $key;
    } else if (strpos($key, 'xapi') !== false) {
        $categories['xAPI'][] = $key;
    } else if (strpos($key, 'performance') !== false || strpos($key, 'cache') !== false) {
        $categories['Performance'][] = $key;
    } else if (strpos($key, 'security') !== false || strpos($key, 'rate') !== false) {
        $categories['Security'][] = $key;
    } else if (strpos($key, 'error') !== false || strpos($key, 'failed') !== false) {
        $categories['Errors'][] = $key;
    } else if ($key === 'pluginname') {
        $categories['Plugin'][] = $key;
    } else {
        $categories['Other'][] = $key;
    }
}

// Display categories.
foreach ($categories as $category => $strings) {
    if (!empty($strings)) {
        cli_writeln($category . ': ' . count($strings) . ' strings');
    }
}

cli_writeln('');
cli_writeln('=== Missing String Recommendations ===');
cli_writeln('');

// Check for common missing strings.
$recommended = [
    'Common UI' => [
        'save', 'cancel', 'delete', 'edit', 'view', 'back', 'next', 'previous',
        'search', 'filter', 'export', 'import', 'refresh', 'loading',
    ],
    'Time/Date' => [
        'today', 'yesterday', 'thisweek', 'lastweek', 'thismonth', 'lastmonth',
        'daterange', 'from', 'to',
    ],
    'Status' => [
        'active', 'inactive', 'enabled', 'disabled', 'success', 'warning',
        'pending', 'completed', 'running',
    ],
];

foreach ($recommended as $category => $keys) {
    $missing = [];
    foreach ($keys as $key) {
        if (!isset($string[$key])) {
            $missing[] = $key;
        }
    }
    
    if (!empty($missing)) {
        cli_writeln($category . ' (missing):');
        foreach ($missing as $key) {
            cli_writeln('  - ' . $key);
        }
        cli_writeln('');
    }
}

cli_writeln('Done!');
exit(0);
