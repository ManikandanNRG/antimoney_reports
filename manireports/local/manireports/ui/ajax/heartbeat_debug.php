<?php
/**
 * Debug endpoint to test heartbeat functionality.
 * Access via: https://dev.aktrea.net/local/manireports/ui/ajax/heartbeat_debug.php
 */

require_once(__DIR__ . '/../../../../config.php');
require_login();

if (!is_siteadmin()) {
    die('Admin access required');
}

global $DB, $USER;

?>
<!DOCTYPE html>
<html>
<head>
    <title>Heartbeat Debug</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        .test { background: #f9f9f9; padding: 15px; margin: 15px 0; border-left: 4px solid #007bff; }
        .pass { color: #28a745; }
        .fail { color: #dc3545; }
        button { padding: 10px 20px; margin: 5px; cursor: pointer; background: #007bff; color: white; border: none; }
        pre { background: #f4f4f4; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Heartbeat Debug Tool</h1>

    <div class="test">
        <h3>1. Check Settings</h3>
        <?php
        $enabled = get_config('local_manireports', 'enabletimetracking');
        $interval = get_config('local_manireports', 'heartbeatinterval');
        $timeout = get_config('local_manireports', 'sessiontimeout');
        
        echo "Time Tracking Enabled: <span class='" . ($enabled ? 'pass' : 'fail') . "'>" . ($enabled ? 'YES ✓' : 'NO ✗') . "</span><br>";
        echo "Heartbeat Interval: " . ($interval ?: 'NOT SET') . " seconds<br>";
        echo "Session Timeout: " . ($timeout ?: 'NOT SET') . " minutes<br>";
        ?>
    </div>

    <div class="test">
        <h3>2. Test Heartbeat Endpoint</h3>
        <button onclick="testHeartbeat()">Send Test Heartbeat</button>
        <pre id="heartbeat-result"></pre>
    </div>

    <div class="test">
        <h3>3. Check Database</h3>
        <button onclick="checkDatabase()">Check Sessions in Database</button>
        <pre id="database-result"></pre>
    </div>

    <div class="test">
        <h3>4. Check AMD Module</h3>
        <button onclick="checkAMD()">Check Heartbeat Module</button>
        <pre id="amd-result"></pre>
    </div>

    <script>
        function testHeartbeat() {
            const courseid = 2; // Usually first course
            const timestamp = Math.floor(Date.now() / 1000);
            
            fetch('<?php echo new moodle_url('/local/manireports/ui/ajax/heartbeat.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'courseid=' + courseid + '&timestamp=' + timestamp + '&sesskey=<?php echo sesskey(); ?>'
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('heartbeat-result').textContent = JSON.stringify(data, null, 2);
            })
            .catch(error => {
                document.getElementById('heartbeat-result').textContent = 'Error: ' + error.message;
            });
        }

        function checkDatabase() {
            fetch('<?php echo new moodle_url('/local/manireports/ui/ajax/test_timetracking.php'); ?>')
            .then(response => response.json())
            .then(data => {
                document.getElementById('database-result').textContent = JSON.stringify(data, null, 2);
            })
            .catch(error => {
                document.getElementById('database-result').textContent = 'Error: ' + error.message;
            });
        }

        function checkAMD() {
            // Check if heartbeat module is loaded
            if (typeof M !== 'undefined' && M.cfg) {
                document.getElementById('amd-result').textContent = 'Moodle loaded: YES\nWWWRoot: ' + M.cfg.wwwroot + '\nSesskey: ' + M.cfg.sesskey;
            } else {
                document.getElementById('amd-result').textContent = 'Moodle not loaded';
            }
        }
    </script>

</body>
</html>
