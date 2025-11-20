<?php
require_once(__DIR__ . '/../../../../config.php');
require_login();

if (!is_siteadmin()) {
    die(json_encode(['success' => false, 'error' => 'Admin access required']));
}

$tasks = [
    '\\local_manireports\\task\\cache_builder',
    '\\local_manireports\\task\\cleanup_old_data',
    '\\local_manireports\\task\\report_scheduler',
    '\\local_manireports\\task\\scorm_summary',
    '\\local_manireports\\task\\time_aggregation'
];

$missing = [];
foreach ($tasks as $task) {
    if (!class_exists($task)) {
        $missing[] = $task;
    }
}

header('Content-Type: application/json');
echo json_encode([
    'success' => empty($missing),
    'count' => count($tasks) - count($missing),
    'missing' => $missing
]);
