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
 * Plugin lifecycle hooks and callbacks.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Hook to extend navigation menu.
 *
 * @param global_navigation $navigation Navigation object
 */
function local_manireports_extend_navigation(global_navigation $navigation) {
    global $PAGE, $USER;

    // Only add navigation items if user is logged in.
    if (!isloggedin() || isguestuser()) {
        return;
    }

    // Check if user has any manireports capability.
    $context = context_system::instance();
    $hascapability = has_capability('local/manireports:viewadmindashboard', $context) ||
                     has_capability('local/manireports:viewmanagerdashboard', $context) ||
                     has_capability('local/manireports:viewteacherdashboard', $context) ||
                     has_capability('local/manireports:viewstudentdashboard', $context);

    if (!$hascapability) {
        return;
    }

    // Add ManiReports node to navigation.
    $node = $navigation->add(
        get_string('pluginname', 'local_manireports'),
        new moodle_url('/local/manireports/ui/dashboard.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'manireports',
        new pix_icon('i/report', '')
    );
    $node->showinflatnavigation = true;
}

/**
 * Serve the files from the manireports file areas.
 *
 * @param stdClass $course Course object
 * @param stdClass $cm Course module object
 * @param context $context Context object
 * @param string $filearea File area
 * @param array $args Extra arguments
 * @param bool $forcedownload Whether to force download
 * @param array $options Additional options
 * @return bool False if file not found, does not return if found
 */
function local_manireports_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    // Check the contextlevel is as expected.
    if ($context->contextlevel != CONTEXT_SYSTEM) {
        return false;
    }

    // Make sure the filearea is one of those used by the plugin.
    if ($filearea !== 'manireports_exports') {
        return false;
    }

    // Check capability.
    require_capability('local/manireports:viewadmindashboard', $context);

    // Extract the filename / filepath from the $args array.
    $filename = array_pop($args);
    if (!$args) {
        $filepath = '/';
    } else {
        $filepath = '/' . implode('/', $args) . '/';
    }

    // Retrieve the file from the Files API.
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_manireports', $filearea, 0, $filepath, $filename);
    if (!$file) {
        return false;
    }

    // Send the file.
    send_stored_file($file, 86400, 0, $forcedownload, $options);
}

/**
 * Hook to add CSS to all pages.
 *
 * @return array Array of CSS files to include
 */
function local_manireports_before_standard_html_head() {
    global $PAGE;
    
    // Add plugin CSS to all pages.
    $PAGE->requires->css('/local/manireports/styles.css');
}

/**
 * Hook to inject JavaScript into course pages for time tracking.
 *
 * @return void
 */
function local_manireports_before_footer() {
    global $PAGE, $USER, $COURSE;

    // Only inject on course pages.
    if ($PAGE->pagelayout !== 'course' && $PAGE->pagelayout !== 'incourse') {
        return;
    }

    // Don't track on site home.
    if ($COURSE->id == SITEID) {
        return;
    }

    // Only track logged in users.
    if (!isloggedin() || isguestuser()) {
        return;
    }

    // Check if time tracking is enabled.
    if (!get_config('local_manireports', 'enabletimetracking')) {
        return;
    }

    // Get heartbeat interval from settings (default: 25 seconds).
    $interval = get_config('local_manireports', 'heartbeatinterval') ?: 25;

    // Inject heartbeat JavaScript.
    $PAGE->requires->js_call_amd('local_manireports/heartbeat', 'init', array(
        $COURSE->id,
        $USER->id,
        $interval
    ));
}
