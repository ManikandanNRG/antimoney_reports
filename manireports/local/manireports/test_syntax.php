<?php
// Simple syntax check for report_builder.php
require_once(__DIR__ . '/../../config.php');

try {
    require_once(__DIR__ . '/classes/api/report_builder.php');
    echo "report_builder.php loaded successfully\n";
    
    $builder = new \local_manireports\api\report_builder();
    echo "report_builder instantiated successfully\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
