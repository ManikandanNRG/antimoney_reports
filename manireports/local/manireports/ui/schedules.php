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
 * Schedule management page for ManiReports.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_manireports\api\scheduler;

require_login();

$context = context_system::instance();
$PAGE->set_context($context);

// Check capability.
if (!has_capability('local/manireports:schedule', $context)) {
    throw new moodle_exception('error:nopermission', 'local_manireports');
}

// Get parameters.
$action = optional_param('action', 'list', PARAM_ALPHA);
$scheduleid = optional_param('id', 0, PARAM_INT);

// Set up the page.
$PAGE->set_url(new moodle_url('/local/manireports/ui/schedules.php'));
$PAGE->set_title(get_string('schedules', 'local_manireports'));
$PAGE->set_heading(get_string('schedules', 'local_manireports'));
$PAGE->set_pagelayout('standard');

$scheduler = new scheduler();

// Handle actions.
if ($action === 'delete' && $scheduleid && confirm_sesskey()) {
    $scheduler->delete_schedule($scheduleid);
    redirect(new moodle_url('/local/manireports/ui/schedules.php'), 
             get_string('scheduledeleted', 'local_manireports'), 
             null, 
             \core\output\notification::NOTIFY_SUCCESS);
}

// Output header.
echo $OUTPUT->header();

// Display page heading.
echo html_writer::tag('h2', get_string('schedules', 'local_manireports'));

// Create schedule button.
$create_url = new moodle_url('/local/manireports/ui/schedule_edit.php');
echo html_writer::link($create_url, get_string('createschedule', 'local_manireports'), 
                       array('class' => 'btn btn-primary mb-3'));

// Get user's schedules.
$schedules = $scheduler->get_user_schedules($USER->id);

if (empty($schedules)) {
    echo $OUTPUT->notification(get_string('noschedules', 'local_manireports'), 'info');
} else {
    // Build table.
    $table = new html_table();
    $table->attributes['class'] = 'generaltable table table-striped';
    
    // Table headers.
    $table->head = array(
        get_string('schedulename', 'local_manireports'),
        get_string('reports', 'local_manireports'),
        get_string('frequency', 'local_manireports'),
        get_string('format', 'moodle'),
        get_string('status', 'local_manireports'),
        get_string('lastrun', 'local_manireports'),
        get_string('nextrun', 'local_manireports'),
        get_string('actions', 'local_manireports')
    );
    
    // Table rows.
    foreach ($schedules as $schedule) {
        $row = array();
        
        // Name.
        $row[] = $schedule->name;
        
        // Report type.
        $row[] = $schedule->reporttype;
        
        // Frequency.
        $row[] = get_string($schedule->frequency, 'local_manireports');
        
        // Format.
        $row[] = strtoupper($schedule->format);
        
        // Status.
        $status = $schedule->enabled ? 
            get_string('enabled', 'local_manireports') : 
            get_string('disabled', 'local_manireports');
        $row[] = $status;
        
        // Last run.
        $row[] = $schedule->lastrun > 0 ? 
            userdate($schedule->lastrun, get_string('strftimedatetime', 'langconfig')) : 
            '-';
        
        // Next run.
        $row[] = $schedule->nextrun > 0 ? 
            userdate($schedule->nextrun, get_string('strftimedatetime', 'langconfig')) : 
            '-';
        
        // Actions.
        $actions = array();
        
        $edit_url = new moodle_url('/local/manireports/ui/schedule_edit.php', array('id' => $schedule->id));
        $actions[] = html_writer::link($edit_url, get_string('edit'), array('class' => 'btn btn-sm btn-secondary'));
        
        $delete_url = new moodle_url('/local/manireports/ui/schedules.php', array(
            'action' => 'delete',
            'id' => $schedule->id,
            'sesskey' => sesskey()
        ));
        $actions[] = html_writer::link($delete_url, get_string('delete'), 
                                      array('class' => 'btn btn-sm btn-danger',
                                            'onclick' => 'return confirm("Are you sure?");'));
        
        $row[] = implode(' ', $actions);
        
        $table->data[] = $row;
    }
    
    echo html_writer::table($table);
}

// Output footer.
echo $OUTPUT->footer();
