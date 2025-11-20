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
 * CLI script to perform security audit on ManiReports.
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
        'fix' => false,
    ),
    array(
        'h' => 'help',
        'f' => 'fix',
    )
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help = "Perform security audit on ManiReports plugin.

Options:
-h, --help            Print out this help
-f, --fix             Attempt to fix issues automatically

Example:
\$ sudo -u www-data /usr/bin/php local/manireports/cli/security_audit.php
\$ sudo -u www-data /usr/bin/php local/manireports/cli/security_audit.php --fix
";

    echo $help;
    exit(0);
}

cli_heading('ManiReports - Security Audit');

$issues = [];
$fixed = [];

// Check 1: Verify all UI files have capability checks.
cli_writeln('Checking capability enforcement...');

$uifiles = glob($CFG->dirroot . '/local/manireports/ui/*.php');
foreach ($uifiles as $file) {
    $content = file_get_contents($file);
    
    if (strpos($content, 'require_capability') === false && 
        strpos($content, 'has_capability') === false) {
        $issues[] = basename($file) . ': Missing capability check';
    }
}

// Check 2: Verify CSRF protection on forms.
cli_writeln('Checking CSRF protection...');

foreach ($uifiles as $file) {
    $content = file_get_contents($file);
    
    if (strpos($content, '<form') !== false && 
        strpos($content, 'sesskey') === false) {
        $issues[] = basename($file) . ': Form missing sesskey';
    }
}

// Check 3: Check for direct SQL queries without parameters.
cli_writeln('Checking SQL query safety...');

$phpfiles = glob($CFG->dirroot . '/local/manireports/classes/**/*.php');
foreach ($phpfiles as $file) {
    $content = file_get_contents($file);
    
    // Look for $DB->execute or $DB->get_records with string concatenation.
    if (preg_match('/\$DB->(execute|get_records|get_record|count_records)\s*\([^)]*\$[^)]*\./', $content)) {
        $issues[] = basename($file) . ': Possible SQL injection (string concatenation)';
    }
}

// Check 4: Verify input validation.
cli_writeln('Checking input validation...');

foreach ($uifiles as $file) {
    $content = file_get_contents($file);
    
    // Check for $_GET, $_POST, $_REQUEST usage.
    if (preg_match('/\$_(GET|POST|REQUEST)\[/', $content)) {
        $issues[] = basename($file) . ': Direct superglobal access (use required_param/optional_param)';
    }
}

// Check 5: Verify output sanitization.
cli_writeln('Checking output sanitization...');

foreach ($uifiles as $file) {
    $content = file_get_contents($file);
    
    // Look for echo without s() or format_string().
    if (preg_match('/echo\s+\$[a-zA-Z_][a-zA-Z0-9_]*;/', $content)) {
        $issues[] = basename($file) . ': Possible XSS (unsanitized output)';
    }
}

// Check 6: Verify file permissions.
cli_writeln('Checking file permissions...');

$allfiles = array_merge($phpfiles, $uifiles);
foreach ($allfiles as $file) {
    $perms = fileperms($file);
    
    // Check if file is world-writable.
    if ($perms & 0x0002) {
        $issues[] = basename($file) . ': World-writable file';
        
        if ($options['fix']) {
            chmod($file, 0644);
            $fixed[] = basename($file) . ': Fixed permissions';
        }
    }
}

// Check 7: Verify custom reports use whitelist.
cli_writeln('Checking custom report security...');

$reportbuilder = $CFG->dirroot . '/local/manireports/classes/api/report_builder.php';
if (file_exists($reportbuilder)) {
    $content = file_get_contents($reportbuilder);
    
    if (strpos($content, 'validate_sql') === false) {
        $issues[] = 'report_builder.php: Missing SQL validation';
    }
    
    if (strpos($content, 'allowed_tables') === false) {
        $issues[] = 'report_builder.php: Missing table whitelist';
    }
}

// Display results.
cli_writeln('');
cli_writeln('=== Audit Results ===');
cli_writeln('');

if (empty($issues)) {
    cli_writeln('✓ No security issues found!', 'green');
} else {
    cli_writeln('✗ Found ' . count($issues) . ' potential security issues:', 'red');
    cli_writeln('');
    
    foreach ($issues as $issue) {
        cli_writeln('  - ' . $issue);
    }
}

if (!empty($fixed)) {
    cli_writeln('');
    cli_writeln('Fixed ' . count($fixed) . ' issues:');
    foreach ($fixed as $fix) {
        cli_writeln('  ✓ ' . $fix, 'green');
    }
}

cli_writeln('');
cli_writeln('Recommendations:');
cli_writeln('  1. Review all capability checks');
cli_writeln('  2. Ensure all forms use sesskey');
cli_writeln('  3. Use prepared statements for all queries');
cli_writeln('  4. Validate all user input with PARAM_* types');
cli_writeln('  5. Sanitize all output with s() or format_string()');
cli_writeln('  6. Review file permissions (should be 644 for files, 755 for directories)');
cli_writeln('');

exit(empty($issues) ? 0 : 1);
