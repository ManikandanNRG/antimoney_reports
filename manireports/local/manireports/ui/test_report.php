<?php
// Simple test to see the actual error
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once(__DIR__ . '/../../../config.php');

echo "<h1>Testing Report Loading</h1>";

$reporttype = 'course_completion';
echo "<p>Report type: $reporttype</p>";

$reportclass = "\\local_manireports\\reports\\{$reporttype}";
echo "<p>Report class: $reportclass</p>";

$filepath = __DIR__ . "/../classes/reports/{$reporttype}.php";
echo "<p>File path: $filepath</p>";
echo "<p>File exists: " . (file_exists($filepath) ? 'YES' : 'NO') . "</p>";

if (file_exists($filepath)) {
    require_once($filepath);
    echo "<p>File included successfully</p>";
}

echo "<p>Class exists: " . (class_exists($reportclass) ? 'YES' : 'NO') . "</p>";

if (class_exists($reportclass)) {
    echo "<p>SUCCESS! Class can be loaded.</p>";
    
    // Try to instantiate
    try {
        $report = new $reportclass($USER->id, array());
        echo "<p>Report instantiated successfully!</p>";
    } catch (Exception $e) {
        echo "<p style='color:red'>Error instantiating: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color:red'>ERROR: Class does not exist after include</p>";
    
    // List what's in the directory
    echo "<h3>Files in reports directory:</h3>";
    $files = scandir(__DIR__ . "/../classes/reports/");
    echo "<pre>" . print_r($files, true) . "</pre>";
}
