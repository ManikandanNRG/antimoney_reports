<?php
require_once(__DIR__ . '/../../../../config.php');
require_login();

if (!is_siteadmin()) {
    die(json_encode(['success' => false, 'error' => 'Admin access required']));
}

global $DB;

$caps = [
    'local/manireports:viewadmindashboard',
    'local/manireports:viewmanagerdashboard',
    'local/manireports:viewteacherdashboard',
    'local/manireports:viewstudentdashboard',
    'local/manireports:managereports',
    'local/manireports:schedule',
    'local/manireports:customreports'
];

$missing = [];
foreach ($caps as $cap) {
    if (!$DB->record_exists('capabilities', ['name' => $cap])) {
        $missing[] = $cap;
    }
}

header('Content-Type: application/json');
echo json_encode([
    'success' => empty($missing),
    'count' => count($caps) - count($missing),
    'missing' => $missing
]);
