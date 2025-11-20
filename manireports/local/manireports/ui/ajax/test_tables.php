<?php
require_once(__DIR__ . '/../../../../config.php');
require_login();

if (!is_siteadmin()) {
    die(json_encode(['success' => false, 'error' => 'Admin access required']));
}

global $DB;

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

$missing = [];
foreach ($tables as $table) {
    if (!$DB->get_manager()->table_exists($table)) {
        $missing[] = $table;
    }
}

header('Content-Type: application/json');
echo json_encode([
    'success' => empty($missing),
    'count' => count($tables) - count($missing),
    'missing' => $missing
]);
