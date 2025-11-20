<?php
require_once(__DIR__ . '/../../../../config.php');
require_login();

if (!is_siteadmin()) {
    die(json_encode(['success' => false, 'error' => 'Admin access required']));
}

$builder = new \local_manireports\api\report_builder();

$results = [
    'simple_select' => false,
    'param_query' => false,
    'join_query' => false,
    'row_count' => 0
];

try {
    // Test 1: Simple SELECT
    $sql = "SELECT id, fullname FROM {course} LIMIT 5";
    $data = $builder->execute_report($sql, []);
    $results['simple_select'] = true;
    $results['row_count'] = count($data);

    // Test 2: Parameterized query
    $sql = "SELECT id, fullname FROM {course} WHERE category = ?";
    $data = $builder->execute_report($sql, [1]);
    $results['param_query'] = true;

    // Test 3: JOIN query
    $sql = "SELECT c.id, c.fullname FROM {course} c LIMIT 5";
    $data = $builder->execute_report($sql, []);
    $results['join_query'] = true;

} catch (Exception $e) {
    $results['error'] = $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($results);
