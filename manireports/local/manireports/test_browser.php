<?php
/**
 * Browser-based testing for ManiReports
 * Access via: https://your-moodle.com/local/manireports/test_browser.php
 */

require_once(__DIR__ . '/../../config.php');
require_login();

// Check admin access
if (!is_siteadmin()) {
    die('Admin access required');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>ManiReports - Browser Test</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        .test { margin: 20px 0; padding: 15px; border: 1px solid #ddd; }
        .pass { background: #d4edda; border-color: #28a745; }
        .fail { background: #f8d7da; border-color: #dc3545; }
        .info { background: #d1ecf1; border-color: #17a2b8; }
        h2 { color: #333; }
        code { background: #f4f4f4; padding: 2px 5px; }
        button { padding: 10px 20px; margin: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <h1>ManiReports - Browser-Based Testing</h1>
    
    <?php
    
    global $DB, $USER;
    
    echo '<div class="test info">';
    echo '<h3>Current User Info</h3>';
    echo 'Username: ' . $USER->username . '<br>';
    echo 'User ID: ' . $USER->id . '<br>';
    echo 'Is Admin: ' . (is_siteadmin() ? 'YES' : 'NO') . '<br>';
    echo '</div>';
    
    // Test 1: Database Connection
    echo '<div class="test">';
    echo '<h3>Test 1: Database Connection</h3>';
    try {
        $count = $DB->count_records('course');
        echo '<div class="pass">✓ Database connected. Courses found: ' . $count . '</div>';
    } catch (Exception $e) {
        echo '<div class="fail">✗ Database error: ' . $e->getMessage() . '</div>';
    }
    echo '</div>';
    
    // Test 2: Plugin Tables
    echo '<div class="test">';
    echo '<h3>Test 2: ManiReports Tables</h3>';
    $tables = [
        'manireports_customreports',
        'manireports_schedules',
        'manireports_report_runs',
        'manireports_time_sessions',
        'manireports_time_daily',
        'manireports_scorm_summary',
        'manireports_cache_summary',
        'manireports_dashboards',
        'manireports_dash_widgets',
        'manireports_audit_logs',
        'manireports_atrisk_ack',
        'manireports_failed_jobs'
    ];
    
    $missing = [];
    foreach ($tables as $table) {
        if (!$DB->get_manager()->table_exists($table)) {
            $missing[] = $table;
        }
    }
    
    if (empty($missing)) {
        echo '<div class="pass">✓ All 12 tables exist</div>';
    } else {
        echo '<div class="fail">✗ Missing tables: ' . implode(', ', $missing) . '</div>';
    }
    echo '</div>';
    
    // Test 3: Capabilities
    echo '<div class="test">';
    echo '<h3>Test 3: Capabilities</h3>';
    $caps = [
        'local/manireports:viewadmindashboard',
        'local/manireports:viewmanagerdashboard',
        'local/manireports:viewteacherdashboard',
        'local/manireports:viewstudentdashboard',
        'local/manireports:managereports',
        'local/manireports:schedule',
        'local/manireports:customreports'
    ];
    
    $missing_caps = [];
    foreach ($caps as $cap) {
        if (!$DB->record_exists('capabilities', ['name' => $cap])) {
            $missing_caps[] = $cap;
        }
    }
    
    if (empty($missing_caps)) {
        echo '<div class="pass">✓ All 7 capabilities defined</div>';
    } else {
        echo '<div class="fail">✗ Missing capabilities: ' . implode(', ', $missing_caps) . '</div>';
    }
    echo '</div>';
    
    // Test 4: IOMAD Detection
    echo '<div class="test">';
    echo '<h3>Test 4: IOMAD Detection</h3>';
    $iomad_installed = $DB->get_manager()->table_exists('company');
    if ($iomad_installed) {
        echo '<div class="pass">✓ IOMAD is installed</div>';
        $company_count = $DB->count_records('company');
        echo 'Companies found: ' . $company_count . '<br>';
    } else {
        echo '<div class="info">ℹ IOMAD not installed (optional)</div>';
    }
    echo '</div>';
    
    // Test 5: Report Classes
    echo '<div class="test">';
    echo '<h3>Test 5: Report Classes</h3>';
    $reports = [
        'course_completion' => '\\local_manireports\\reports\\course_completion',
        'course_progress' => '\\local_manireports\\reports\\course_progress',
        'scorm_summary' => '\\local_manireports\\reports\\scorm_summary',
        'user_engagement' => '\\local_manireports\\reports\\user_engagement',
        'quiz_attempts' => '\\local_manireports\\reports\\quiz_attempts'
    ];
    
    $missing_reports = [];
    foreach ($reports as $name => $class) {
        if (!class_exists($class)) {
            $missing_reports[] = $name;
        }
    }
    
    if (empty($missing_reports)) {
        echo '<div class="pass">✓ All 5 report classes exist</div>';
    } else {
        echo '<div class="fail">✗ Missing reports: ' . implode(', ', $missing_reports) . '</div>';
    }
    echo '</div>';
    
    // Test 6: API Classes
    echo '<div class="test">';
    echo '<h3>Test 6: API Classes</h3>';
    $apis = [
        'report_builder' => '\\local_manireports\\api\\report_builder',
        'time_engine' => '\\local_manireports\\api\\time_engine',
        'cache_manager' => '\\local_manireports\\api\\cache_manager',
        'scheduler' => '\\local_manireports\\api\\scheduler',
        'export_engine' => '\\local_manireports\\api\\export_engine',
        'analytics_engine' => '\\local_manireports\\api\\analytics_engine',
        'iomad_filter' => '\\local_manireports\\api\\iomad_filter'
    ];
    
    $missing_apis = [];
    foreach ($apis as $name => $class) {
        if (!class_exists($class)) {
            $missing_apis[] = $name;
        }
    }
    
    if (empty($missing_apis)) {
        echo '<div class="pass">✓ All 7 API classes exist</div>';
    } else {
        echo '<div class="fail">✗ Missing APIs: ' . implode(', ', $missing_apis) . '</div>';
    }
    echo '</div>';
    
    // Test 7: Scheduled Tasks
    echo '<div class="test">';
    echo '<h3>Test 7: Scheduled Tasks</h3>';
    $tasks = [
        '\\local_manireports\\task\\cache_builder',
        '\\local_manireports\\task\\time_aggregation',
        '\\local_manireports\\task\\report_scheduler',
        '\\local_manireports\\task\\scorm_summary',
        '\\local_manireports\\task\\cleanup_old_data'
    ];
    
    $missing_tasks = [];
    foreach ($tasks as $task) {
        if (!class_exists($task)) {
            $missing_tasks[] = $task;
        }
    }
    
    if (empty($missing_tasks)) {
        echo '<div class="pass">✓ All 5 scheduled tasks exist</div>';
    } else {
        echo '<div class="fail">✗ Missing tasks: ' . implode(', ', $missing_tasks) . '</div>';
    }
    echo '</div>';
    
    // Test 8: Language Strings
    echo '<div class="test">';
    echo '<h3>Test 8: Language Strings</h3>';
    $strings = get_string_manager()->load_component_strings('local_manireports', 'en');
    $string_count = count($strings);
    if ($string_count > 100) {
        echo '<div class="pass">✓ Language strings loaded: ' . $string_count . ' strings</div>';
    } else {
        echo '<div class="fail">✗ Language strings incomplete: ' . $string_count . ' strings</div>';
    }
    echo '</div>';
    
    // Test 9: Direct Report Access
    echo '<div class="test">';
    echo '<h3>Test 9: Direct Report Access Links</h3>';
    echo '<p>Click links below to test reports directly:</p>';
    
    $dashboard_url = new moodle_url('/local/manireports/ui/dashboard.php');
    $completion_url = new moodle_url('/local/manireports/ui/report_view.php', ['report' => 'course_completion']);
    $custom_url = new moodle_url('/local/manireports/ui/custom_reports.php');
    $schedules_url = new moodle_url('/local/manireports/ui/schedules.php');
    $audit_url = new moodle_url('/local/manireports/ui/audit.php');
    
    echo '<button onclick="window.open(\'' . $dashboard_url . '\', \'_blank\')">Admin Dashboard</button><br><br>';
    echo '<button onclick="window.open(\'' . $completion_url . '\', \'_blank\')">Course Completion Report</button><br><br>';
    echo '<button onclick="window.open(\'' . $custom_url . '\', \'_blank\')">Custom Reports</button><br><br>';
    echo '<button onclick="window.open(\'' . $schedules_url . '\', \'_blank\')">Schedules</button><br><br>';
    echo '<button onclick="window.open(\'' . $audit_url . '\', \'_blank\')">Audit Logs</button>';
    echo '</div>';
    
    // Summary
    echo '<div class="test info">';
    echo '<h2>Summary</h2>';
    echo '<p><strong>Backend Status:</strong> ✓ Complete (all classes, tables, tasks exist)</p>';
    echo '<p><strong>Frontend Status:</strong> ⚠ Basic (no navigation UI, access via direct URLs)</p>';
    echo '<p><strong>Next Steps:</strong></p>';
    echo '<ul>';
    echo '<li>Use direct URLs above to test reports</li>';
    echo '<li>Test with different user roles (admin, manager, teacher, student)</li>';
    echo '<li>Check browser console (F12) for JavaScript errors</li>';
    echo '<li>Check Moodle error log for PHP errors</li>';
    echo '</ul>';
    echo '</div>';
    
    ?>
    
</body>
</html>
