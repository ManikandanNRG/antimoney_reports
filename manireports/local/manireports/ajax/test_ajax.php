<?php
// Test AJAX endpoint directly
require_once(__DIR__ . '/../../../config.php');

require_login();

echo "Testing AJAX endpoint...<br>";
echo "Session key: " . sesskey() . "<br>";

// Test with company ID 1 (adjust as needed)
$companyid = 1;

$sql = "SELECT c.id, c.fullname
        FROM {course} c
        JOIN {company_course} cc ON cc.courseid = c.id
        WHERE cc.companyid = :companyid
        AND c.id != :siteid
        ORDER BY c.fullname";

$courses = $DB->get_records_sql_menu($sql, [
    'companyid' => $companyid,
    'siteid' => SITEID
]);

echo "<br>Courses for company $companyid:<br>";
if (empty($courses)) {
    echo "No courses found!<br>";
} else {
    foreach ($courses as $id => $name) {
        echo "- $id: $name<br>";
    }
}

// Check if company_course table exists
$tables = $DB->get_tables();
echo "<br>Does company_course table exist? " . (in_array('company_course', $tables) ? 'YES' : 'NO') . "<br>";

// Check company table
$companies = $DB->get_records_menu('company', null, 'name ASC', 'id, name');
echo "<br>Companies:<br>";
foreach ($companies as $id => $name) {
    echo "- $id: $name<br>";
}
