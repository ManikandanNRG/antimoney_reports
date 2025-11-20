<?php
/**
 * ManiReports - Browser-Based Test Scenarios (Part 1)
 * Access via: https://dev.aktrea.net/local/manireports/test_scenarios.php
 */

require_once(__DIR__ . '/../../config.php');
require_login();

if (!is_siteadmin()) {
    die('Admin access required');
}

global $DB, $USER;
?>
<!DOCTYPE html>
<html>
<head>
    <title>ManiReports - Test Scenarios Part 1</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        .task { background: white; margin: 20px 0; padding: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .task h2 { color: #333; border-bottom: 3px solid #007bff; padding-bottom: 10px; }
        .scenario { background: #f9f9f9; margin: 15px 0; padding: 15px; border-left: 4px solid #007bff; }
        .scenario h3 { margin-top: 0; color: #0056b3; }
        .pass { color: #28a745; font-weight: bold; }
        .fail { color: #dc3545; font-weight: bold; }
        .info { color: #17a2b8; font-weight: bold; }
        .button-group { margin: 15px 0; }
        button { padding: 10px 20px; margin: 5px; cursor: pointer; background: #007bff; color: white; border: none; border-radius: 3px; }
        button:hover { background: #0056b3; }
        .code { background: #f4f4f4; padding: 10px; border-radius: 3px; font-family: monospace; overflow-x: auto; }
        .result { margin-top: 10px; padding: 10px; border-radius: 3px; }
        .result.pass { background: #d4edda; border: 1px solid #28a745; }
        .result.fail { background: #f8d7da; border: 1px solid #dc3545; }
        .result.info { background: #d1ecf1; border: 1px solid #17a2b8; }
    </style>
</head>
<body>
    <div class="container">
        <h1>ManiReports - Test Scenarios Part 1 (Tasks 1-10)</h1>
        <p>This page provides browser-based testing for all Part 1 scenarios.</p>

        <?php
        // Task 1: Plugin Foundation
        echo '<div class="task">';
        echo '<h2>Task 1: Plugin Foundation & Structure</h2>';
        echo '<div class="scenario">';
        echo '<h3>Test 1.1: Plugin Installation</h3>';
        echo '<p><strong>Steps:</strong></p>';
        echo '<ol>';
        echo '<li>SSH to EC2 server</li>';
        echo '<li>Navigate to /var/www/html/moodle/local/</li>';
        echo '<li>Deploy manireports folder</li>';
        echo '<li>Run: <code>sudo -u www-data php admin/cli/upgrade.php --non-interactive</code></li>';
        echo '<li>Login to Moodle as admin</li>';
        echo '<li>Navigate to: Site administration → Plugins → Local plugins</li>';
        echo '<li>Verify ManiReports appears in list with version 2024111704</li>';
        echo '</ol>';
        echo '<p><strong>Expected Result:</strong> Plugin installed successfully, version shows correctly</p>';
        echo '<div class="button-group">';
        echo '<button onclick="testPluginInstallation()">Test Plugin Installation</button>';
        echo '</div>';
        echo '<div id="result-1-1"></div>';
        echo '</div>';
        echo '</div>';

        // Task 2: Database Schema
        echo '<div class="task">';
        echo '<h2>Task 2: Database Schema & Installation</h2>';
        echo '<div class="scenario">';
        echo '<h3>Test 2.1: Database Tables Creation</h3>';
        echo '<p><strong>Expected Tables (13 total):</strong></p>';
        echo '<ul>';
        $tables = [
            'manireports_audit_logs',
            'manireports_atrisk_ack',
            'manireports_cache_summary',
            'manireports_customreports',
            'manireports_dash_widgets',
            'manireports_dashboards',
            'manireports_failed_jobs',
            'manireports_report_runs',
            'manireports_sched_recip',
            'manireports_schedules',
            'manireports_scorm_summary',
            'manireports_time_daily',
            'manireports_time_sessions'
        ];
        foreach ($tables as $table) {
            echo '<li>' . $table . '</li>';
        }
        echo '</ul>';
        echo '<div class="button-group">';
        echo '<button onclick="testDatabaseTables()">Test Database Tables</button>';
        echo '</div>';
        echo '<div id="result-2-1"></div>';
        echo '</div>';

        echo '<div class="scenario">';
        echo '<h3>Test 2.2: Capabilities Definition</h3>';
        echo '<p><strong>Expected Capabilities (7 total):</strong></p>';
        echo '<ul>';
        $caps = [
            'local/manireports:viewadmindashboard',
            'local/manireports:viewmanagerdashboard',
            'local/manireports:viewteacherdashboard',
            'local/manireports:viewstudentdashboard',
            'local/manireports:managereports',
            'local/manireports:schedule',
            'local/manireports:customreports'
        ];
        foreach ($caps as $cap) {
            echo '<li>' . $cap . '</li>';
        }
        echo '</ul>';
        echo '<div class="button-group">';
        echo '<button onclick="testCapabilities()">Test Capabilities</button>';
        echo '</div>';
        echo '<div id="result-2-2"></div>';
        echo '</div>';

        echo '<div class="scenario">';
        echo '<h3>Test 2.3: Scheduled Tasks Registration</h3>';
        echo '<p><strong>Expected Tasks (5 total):</strong></p>';
        echo '<ul>';
        $tasks = [
            '\\local_manireports\\task\\cache_builder',
            '\\local_manireports\\task\\cleanup_old_data',
            '\\local_manireports\\task\\report_scheduler',
            '\\local_manireports\\task\\scorm_summary',
            '\\local_manireports\\task\\time_aggregation'
        ];
        foreach ($tasks as $task) {
            echo '<li>' . $task . '</li>';
        }
        echo '</ul>';
        echo '<div class="button-group">';
        echo '<button onclick="testScheduledTasks()">Test Scheduled Tasks</button>';
        echo '</div>';
        echo '<div id="result-2-3"></div>';
        echo '</div>';
        echo '</div>';

        // Task 3: IOMAD Filter
        echo '<div class="task">';
        echo '<h2>Task 3: IOMAD Filter & Multi-Tenancy</h2>';
        echo '<div class="scenario">';
        echo '<h3>Test 3.1: IOMAD Detection</h3>';
        echo '<div class="button-group">';
        echo '<button onclick="testIOMADDetection()">Test IOMAD Detection</button>';
        echo '</div>';
        echo '<div id="result-3-1"></div>';
        echo '</div>';
        echo '</div>';

        // Task 4: Report Builder API
        echo '<div class="task">';
        echo '<h2>Task 4: Core Report Builder API</h2>';
        echo '<div class="scenario">';
        echo '<h3>Test 4.1: SQL Validation</h3>';
        echo '<div class="button-group">';
        echo '<button onclick="testSQLValidation()">Test SQL Validation</button>';
        echo '</div>';
        echo '<div id="result-4-1"></div>';
        echo '</div>';

        echo '<div class="scenario">';
        echo '<h3>Test 4.2: Report Execution</h3>';
        echo '<div class="button-group">';
        echo '<button onclick="testReportExecution()">Test Report Execution</button>';
        echo '</div>';
        echo '<div id="result-4-2"></div>';
        echo '</div>';
        echo '</div>';

        // Task 5: Prebuilt Reports
        echo '<div class="task">';
        echo '<h2>Task 5: Prebuilt Core Reports</h2>';
        echo '<div class="scenario">';
        echo '<h3>Test 5.1-5.5: Report Access Links</h3>';
        echo '<p>Click buttons below to test each report directly:</p>';
        echo '<div class="button-group">';
        
        $dashboard_url = new moodle_url('/local/manireports/ui/dashboard.php');
        $completion_url = new moodle_url('/local/manireports/ui/report_view.php', ['report' => 'course_completion']);
        $progress_url = new moodle_url('/local/manireports/ui/report_view.php', ['report' => 'course_progress']);
        $scorm_url = new moodle_url('/local/manireports/ui/report_view.php', ['report' => 'scorm_summary']);
        $engagement_url = new moodle_url('/local/manireports/ui/report_view.php', ['report' => 'user_engagement']);
        $quiz_url = new moodle_url('/local/manireports/ui/report_view.php', ['report' => 'quiz_attempts']);
        
        echo '<button onclick="window.open(\'' . $dashboard_url . '\', \'_blank\')">Dashboard</button>';
        echo '<button onclick="window.open(\'' . $completion_url . '\', \'_blank\')">Course Completion</button>';
        echo '<button onclick="window.open(\'' . $progress_url . '\', \'_blank\')">Course Progress</button>';
        echo '<button onclick="window.open(\'' . $scorm_url . '\', \'_blank\')">SCORM Summary</button>';
        echo '<button onclick="window.open(\'' . $engagement_url . '\', \'_blank\')">User Engagement</button>';
        echo '<button onclick="window.open(\'' . $quiz_url . '\', \'_blank\')">Quiz Attempts</button>';
        
        echo '</div>';
        echo '</div>';
        echo '</div>';

        // Task 6-10: Other Tests
        echo '<div class="task">';
        echo '<h2>Tasks 6-10: Advanced Features</h2>';
        echo '<div class="scenario">';
        echo '<p><strong>These tests require CLI commands on the server:</strong></p>';
        echo '<ul>';
        echo '<li><strong>Task 6:</strong> Time Tracking Engine - Monitor heartbeat requests in browser DevTools</li>';
        echo '<li><strong>Task 7:</strong> SCORM Analytics - Run aggregation task via CLI</li>';
        echo '<li><strong>Task 8:</strong> Caching & Pre-Aggregation - Run cache builder task</li>';
        echo '<li><strong>Task 9:</strong> Analytics Engine - Run engagement calculations</li>';
        echo '<li><strong>Task 10:</strong> Export Engine - Test CSV/XLSX/PDF exports from reports</li>';
        echo '</ul>';
        echo '<p>See TESTING_SCENARIOS_PART1.md for detailed CLI commands.</p>';
        echo '</div>';
        echo '</div>';

        ?>

    </div>

    <script>
        function testPluginInstallation() {
            const resultDiv = document.getElementById('result-1-1');
            resultDiv.innerHTML = '<div class="result info">✓ Plugin installation requires manual SSH deployment. See steps above.</div>';
        }

        function testDatabaseTables() {
            const resultDiv = document.getElementById('result-2-1');
            fetch('<?php echo new moodle_url('/local/manireports/ui/ajax/test_tables.php'); ?>')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        resultDiv.innerHTML = '<div class="result pass">✓ All ' + data.count + ' tables exist</div>';
                    } else {
                        resultDiv.innerHTML = '<div class="result fail">✗ Missing tables: ' + data.missing.join(', ') + '</div>';
                    }
                })
                .catch(error => {
                    resultDiv.innerHTML = '<div class="result fail">✗ Error: ' + error.message + '</div>';
                });
        }

        function testCapabilities() {
            const resultDiv = document.getElementById('result-2-2');
            fetch('<?php echo new moodle_url('/local/manireports/ui/ajax/test_capabilities.php'); ?>')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        resultDiv.innerHTML = '<div class="result pass">✓ All ' + data.count + ' capabilities defined</div>';
                    } else {
                        resultDiv.innerHTML = '<div class="result fail">✗ Missing capabilities: ' + data.missing.join(', ') + '</div>';
                    }
                })
                .catch(error => {
                    resultDiv.innerHTML = '<div class="result fail">✗ Error: ' + error.message + '</div>';
                });
        }

        function testScheduledTasks() {
            const resultDiv = document.getElementById('result-2-3');
            fetch('<?php echo new moodle_url('/local/manireports/ui/ajax/test_tasks.php'); ?>')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        resultDiv.innerHTML = '<div class="result pass">✓ All ' + data.count + ' scheduled tasks exist</div>';
                    } else {
                        resultDiv.innerHTML = '<div class="result fail">✗ Missing tasks: ' + data.missing.join(', ') + '</div>';
                    }
                })
                .catch(error => {
                    resultDiv.innerHTML = '<div class="result fail">✗ Error: ' + error.message + '</div>';
                });
        }

        function testIOMADDetection() {
            const resultDiv = document.getElementById('result-3-1');
            fetch('<?php echo new moodle_url('/local/manireports/ui/ajax/test_iomad.php'); ?>')
                .then(response => response.json())
                .then(data => {
                    if (data.installed) {
                        resultDiv.innerHTML = '<div class="result pass">✓ IOMAD is installed (' + data.companies + ' companies)</div>';
                    } else {
                        resultDiv.innerHTML = '<div class="result info">ℹ IOMAD not installed (optional)</div>';
                    }
                })
                .catch(error => {
                    resultDiv.innerHTML = '<div class="result fail">✗ Error: ' + error.message + '</div>';
                });
        }

        function testSQLValidation() {
            const resultDiv = document.getElementById('result-4-1');
            fetch('<?php echo new moodle_url('/local/manireports/ui/ajax/test_sql.php'); ?>')
                .then(response => response.json())
                .then(data => {
                    let html = '<div class="result pass">';
                    html += '<strong>SQL Validation Results:</strong><br>';
                    html += 'Valid SELECT: ' + (data.valid_select ? '✓' : '✗') + '<br>';
                    html += 'Invalid DROP blocked: ' + (data.invalid_drop ? '✓' : '✗') + '<br>';
                    html += 'Invalid INSERT blocked: ' + (data.invalid_insert ? '✓' : '✗') + '<br>';
                    html += 'Invalid UPDATE blocked: ' + (data.invalid_update ? '✓' : '✗') + '<br>';
                    html += 'Invalid DELETE blocked: ' + (data.invalid_delete ? '✓' : '✗') + '<br>';
                    html += '</div>';
                    resultDiv.innerHTML = html;
                })
                .catch(error => {
                    resultDiv.innerHTML = '<div class="result fail">✗ Error: ' + error.message + '</div>';
                });
        }

        function testReportExecution() {
            const resultDiv = document.getElementById('result-4-2');
            fetch('<?php echo new moodle_url('/local/manireports/ui/ajax/test_reports.php'); ?>')
                .then(response => response.json())
                .then(data => {
                    let html = '<div class="result pass">';
                    html += '<strong>Report Execution Results:</strong><br>';
                    html += 'Simple SELECT: ' + (data.simple_select ? '✓' : '✗') + '<br>';
                    html += 'Parameterized query: ' + (data.param_query ? '✓' : '✗') + '<br>';
                    html += 'JOIN query: ' + (data.join_query ? '✓' : '✗') + '<br>';
                    html += 'Rows returned: ' + data.row_count + '<br>';
                    html += '</div>';
                    resultDiv.innerHTML = html;
                })
                .catch(error => {
                    resultDiv.innerHTML = '<div class="result fail">✗ Error: ' + error.message + '</div>';
                });
        }
    </script>

</body>
</html>
