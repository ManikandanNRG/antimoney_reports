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
 * CLI script to ensure all required database indexes exist.
 *
 * @package    local_manireports
 * @copyright  2024 ManiReports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

// Get cli options.
list($options, $unrecognized) = cli_get_params(
    array(
        'help' => false,
    ),
    array(
        'h' => 'help',
    )
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help = "Ensure all required database indexes exist for ManiReports.

Options:
-h, --help            Print out this help

Example:
\$ sudo -u www-data /usr/bin/php local/manireports/cli/ensure_indexes.php
";

    echo $help;
    exit(0);
}

cli_heading('ManiReports - Ensure Database Indexes');

$optimizer = new \local_manireports\api\performance_optimizer();

cli_writeln('Checking and creating required indexes...');

$results = $optimizer->ensure_indexes();

cli_writeln('');
cli_writeln('Results:');
cli_writeln('  Indexes checked: ' . $results['checked']);
cli_writeln('  Indexes created: ' . $results['created']);

if (!empty($results['errors'])) {
    cli_writeln('');
    cli_writeln('Errors:');
    foreach ($results['errors'] as $error) {
        cli_writeln('  - ' . $error);
    }
    exit(1);
}

cli_writeln('');
cli_writeln('Done!');
exit(0);
