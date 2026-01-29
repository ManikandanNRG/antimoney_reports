<?php
// Simple syntax check for dashboard files
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once(__DIR__ . '/../../config.php');
    echo "Config loaded\n";
    
    require_once(__DIR__ . '/classes/output/dashboard_data_loader.php');
    echo "dashboard_data_loader.php loaded successfully\n";
    
    // We can't easily include dashboard.php because it executes code, but we can try linting it via shell_exec if this script runs
    $output = shell_exec('php -l ' . __DIR__ . '/ui/dashboard.php');
    echo "dashboard.php syntax check: " . $output . "\n";
    
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
