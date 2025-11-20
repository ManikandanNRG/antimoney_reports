<?php
/**
 * Debug dashboard access issues.
 */

require_once(__DIR__ . '/../../../config.php');
require_login();

global $DB, $USER;

?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard Debug</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        .info { background: #d1ecf1; padding: 15px; margin: 15px 0; border-left: 4px solid #17a2b8; }
        .pass { color: #28a745; }
        .fail { color: #dc3545; }
    </style>
</head>
<body>
    <h1>Dashboard Access Debug</h1>

    <div class="info">
        <h3>User Information</h3>
        <p>User ID: <?php echo $USER->id; ?></p>
        <p>Username: <?php echo $USER->username; ?></p>
        <p>Email: <?php echo $USER->email; ?></p>
    </div>

    <div class="info">
        <h3>System-Level Capabilities</h3>
        <?php
        $context = context_system::instance();
        $caps = [
            'local/manireports:viewadmindashboard',
            'local/manireports:viewmanagerdashboard',
            'local/manireports:viewteacherdashboard',
            'local/manireports:viewstudentdashboard'
        ];
        
        foreach ($caps as $cap) {
            $has = has_capability($cap, $context);
            echo "<p>" . $cap . ": <span class='" . ($has ? 'pass' : 'fail') . "'>" . ($has ? 'YES ✓' : 'NO ✗') . "</span></p>";
        }
        ?>
    </div>

    <div class="info">
        <h3>User Roles at System Level</h3>
        <?php
        $roles = get_user_roles($context, $USER->id);
        if (empty($roles)) {
            echo "<p class='fail'>No roles assigned at system level</p>";
        } else {
            foreach ($roles as $role) {
                echo "<p>" . $role->name . " (" . $role->shortname . ")</p>";
            }
        }
        ?>
    </div>

    <div class="info">
        <h3>Course Enrollments</h3>
        <?php
        $enrolled = $DB->record_exists_sql(
            "SELECT 1 FROM {user_enrolments} ue
             JOIN {enrol} e ON ue.enrolid = e.id
             WHERE ue.userid = ? AND ue.status = 0 AND e.status = 0",
            [$USER->id]
        );
        
        echo "<p>Enrolled in courses: <span class='" . ($enrolled ? 'pass' : 'fail') . "'>" . ($enrolled ? 'YES ✓' : 'NO ✗') . "</span></p>";
        
        if ($enrolled) {
            $courses = $DB->get_records_sql(
                "SELECT DISTINCT c.id, c.fullname FROM {course} c
                 JOIN {enrol} e ON c.id = e.courseid
                 JOIN {user_enrolments} ue ON e.id = ue.enrolid
                 WHERE ue.userid = ? AND ue.status = 0 AND e.status = 0",
                [$USER->id]
            );
            
            echo "<p>Courses:</p><ul>";
            foreach ($courses as $course) {
                echo "<li>" . $course->fullname . "</li>";
            }
            echo "</ul>";
        }
        ?>
    </div>

    <div class="info">
        <h3>Course-Level Roles</h3>
        <?php
        $courses = $DB->get_records_sql(
            "SELECT DISTINCT c.id, c.fullname FROM {course} c
             JOIN {enrol} e ON c.id = e.courseid
             JOIN {user_enrolments} ue ON e.id = ue.enrolid
             WHERE ue.userid = ? AND ue.status = 0 AND e.status = 0",
            [$USER->id]
        );
        
        if (empty($courses)) {
            echo "<p class='fail'>Not enrolled in any courses</p>";
        } else {
            foreach ($courses as $course) {
                $course_context = context_course::instance($course->id);
                $roles = get_user_roles($course_context, $USER->id);
                echo "<p><strong>" . $course->fullname . ":</strong> ";
                if (empty($roles)) {
                    echo "<span class='fail'>No roles</span>";
                } else {
                    $role_names = array_map(function($r) { return $r->shortname; }, $roles);
                    echo "<span class='pass'>" . implode(', ', $role_names) . "</span>";
                }
                echo "</p>";
            }
        }
        ?>
    </div>

</body>
</html>
