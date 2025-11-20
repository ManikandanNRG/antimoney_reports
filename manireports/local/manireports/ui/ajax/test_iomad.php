<?php
require_once(__DIR__ . '/../../../../config.php');
require_login();

if (!is_siteadmin()) {
    die(json_encode(['installed' => false, 'error' => 'Admin access required']));
}

global $DB;

$installed = $DB->get_manager()->table_exists('company');
$companies = 0;

if ($installed) {
    $companies = $DB->count_records('company');
}

header('Content-Type: application/json');
echo json_encode([
    'installed' => $installed,
    'companies' => $companies
]);
