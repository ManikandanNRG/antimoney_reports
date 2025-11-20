<?php
require_once(__DIR__ . '/../../../../config.php');
require_login();

if (!is_siteadmin()) {
    die(json_encode(['success' => false, 'error' => 'Admin access required']));
}

$builder = new \local_manireports\api\report_builder();

$results = [
    'valid_select' => false,
    'invalid_drop' => false,
    'invalid_insert' => false,
    'invalid_update' => false,
    'invalid_delete' => false
];

// Test 1: Valid SELECT
$sql = "SELECT id, fullname FROM {course} WHERE category = ?";
$results['valid_select'] = $builder->validate_sql($sql);

// Test 2: Invalid DROP
$sql = "DROP TABLE {course}";
$results['invalid_drop'] = !$builder->validate_sql($sql);

// Test 3: Invalid INSERT
$sql = "INSERT INTO {course} (fullname) VALUES ('Test')";
$results['invalid_insert'] = !$builder->validate_sql($sql);

// Test 4: Invalid UPDATE
$sql = "UPDATE {course} SET fullname = 'Test' WHERE id = 1";
$results['invalid_update'] = !$builder->validate_sql($sql);

// Test 5: Invalid DELETE
$sql = "DELETE FROM {course} WHERE id = 1";
$results['invalid_delete'] = !$builder->validate_sql($sql);

header('Content-Type: application/json');
echo json_encode($results);
