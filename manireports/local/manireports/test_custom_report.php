<?php
/**
 * Test custom report execution to find the exact error.
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
$CFG->debug = (E_ALL | E_STRICT);
$CFG->debugdisplay = 1;

require_login();

echo "Testing Custom Report Execution\n";
echo "================================\n\n";

// Test 1: Check if report exists in database
echo "1. Checking if report ID 1 exists...\n";
$report = $DB->get_record('manireports_customreports', array('id' => 1));
if (!$report) {
    die("ERROR: Report ID 1 not found in database!\n");
}
echo "   ✓ Report found: " . $report->name . "\n";
echo "   Type: " . $report->type . "\n";
echo "   SQL: " . substr($report->sqlquery, 0, 100) . "...\n\n";

// Test 2: Load report_builder class
echo "2. Loading report_builder class...\n";
try {
    $builder = new \local_manireports\api\report_builder();
    echo "   ✓ report_builder loaded\n\n";
} catch (Exception $e) {
    die("ERROR loading report_builder: " . $e->getMessage() . "\n");
}

// Test 3: Validate the SQL
echo "3. Validating SQL...\n";
try {
    $valid = $builder->validate_sql($report->sqlquery);
    if ($valid) {
        echo "   ✓ SQL is valid\n\n";
    } else {
        die("ERROR: SQL validation failed!\n");
    }
} catch (Exception $e) {
    die("ERROR validating SQL: " . $e->getMessage() . "\n");
}

// Test 4: Execute the report
echo "4. Executing report...\n";
try {
    $result = $builder->execute_report(1, array(), $USER->id, 0, 25, false);
    echo "   ✓ Report executed successfully!\n";
    echo "   Total records: " . $result['total'] . "\n";
    echo "   Columns: " . implode(', ', $result['columns']) . "\n";
    echo "   Data rows: " . count($result['data']) . "\n";
    echo "   Execution time: " . $result['executiontime'] . "s\n\n";
    
    // Show first row
    if (!empty($result['data'])) {
        echo "First row:\n";
        print_r($result['data'][0]);
    }
    
    echo "\n✓✓✓ SUCCESS! Custom reports are working! ✓✓✓\n";
    
} catch (Exception $e) {
    echo "\n✗✗✗ ERROR EXECUTING REPORT ✗✗✗\n";
    echo "Error message: " . $e->getMessage() . "\n";
    echo "Error code: " . $e->getCode() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
    
    // Write to debug log
    $logfile = '/opt/moodledata/manireports_debug.log';
    $logmsg = date('Y-m-d H:i:s') . " - Custom Report Error:\n";
    $logmsg .= "Message: " . $e->getMessage() . "\n";
    $logmsg .= "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    $logmsg .= "Trace: " . $e->getTraceAsString() . "\n\n";
    file_put_contents($logfile, $logmsg, FILE_APPEND);
    
    echo "\nError also written to: $logfile\n";
}
