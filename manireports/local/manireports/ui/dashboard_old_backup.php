<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Dashboard page for ManiReports.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

require_login();

global $DB, $USER;

$context = context_system::instance();

// Check if user has any dashboard capability at system level.
$hascapability = has_capability('local/manireports:viewadmindashboard', $context) ||
                 has_capability('local/manireports:viewmanagerdashboard', $context) ||
                 has_capability('local/manireports:viewteacherdashboard', $context) ||
                 has_capability('local/manireports:viewstudentdashboard', $context);

// If no system-level capability, check if user has student capability in any course context
if (!$hascapability) {
    $courses = $DB->get_records_sql(
        "SELECT DISTINCT c.id FROM {course} c
         JOIN {enrol} e ON c.id = e.courseid
         JOIN {user_enrolments} ue ON e.id = ue.enrolid
         WHERE ue.userid = ? AND ue.status = 0 AND e.status = 0",
        [$USER->id]
    );
    
    foreach ($courses as $course) {
        $course_context = context_course::instance($course->id);
        if (has_capability('local/manireports:viewstudentdashboard', $course_context)) {
            $hascapability = true;
            break;
        }
    }
}

if (!$hascapability) {
    throw new moodle_exception('error:nopermission', 'local_manireports');
}

// Set up the page.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/manireports/ui/dashboard.php'));
$PAGE->set_title(get_string('dashboard', 'local_manireports'));
$PAGE->set_heading(get_string('dashboard', 'local_manireports'));
$PAGE->set_pagelayout('standard');

// Output header.
echo $OUTPUT->header();

// Print tabs.
local_manireports_print_tabs('dashboard');

// Render dashboard.
$renderer = $PAGE->get_renderer('local_manireports', 'dashboard');
echo $renderer->render_dashboard($USER->id);

// Output footer.
echo $OUTPUT->footer();
