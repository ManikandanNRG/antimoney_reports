<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * AJAX endpoint to get activities for a specific course
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');

require_login();
require_sesskey();

$courseid = required_param('courseid', PARAM_INT);

// Get activities with completion tracking enabled
$modinfo = get_fast_modinfo($courseid);
$activities = [];

foreach ($modinfo->get_cms() as $cm) {
    // Only include activities with completion tracking
    if ($cm->completion != COMPLETION_TRACKING_NONE && !$cm->deletioninprogress) {
        $modname = get_string('modulename', $cm->modname);
        $activities[$cm->id] = format_string($cm->name) . ' (' . $modname . ')';
    }
}

// Return as HTML options (Course Completion will be added by JavaScript)
$html = '';
foreach ($activities as $id => $name) {
    $html .= '<option value="' . $id . '">' . s($name) . '</option>';
}

echo $html;
