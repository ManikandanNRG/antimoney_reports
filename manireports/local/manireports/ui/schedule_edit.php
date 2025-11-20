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
 * Schedule edit page for ManiReports.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/formslib.php');

use local_manireports\api\scheduler;

require_login();

$context = context_system::instance();
$PAGE->set_context($context);

// Check capability.
if (!has_capability('local/manireports:schedule', $context)) {
    throw new moodle_exception('error:nopermission', 'local_manireports');
}

// Get parameters.
$scheduleid = optional_param('id', 0, PARAM_INT);

// Set up the page.
$PAGE->set_url(new moodle_url('/local/manireports/ui/schedule_edit.php', array('id' => $scheduleid)));
$PAGE->set_title($scheduleid ? get_string('editschedule', 'local_manireports') : get_string('createschedule', 'local_manireports'));
$PAGE->set_heading($scheduleid ? get_string('editschedule', 'local_manireports') : get_string('createschedule', 'local_manireports'));
$PAGE->set_pagelayout('standard');

$scheduler_api = new scheduler();

// Get existing schedule if editing.
$schedule = null;
if ($scheduleid) {
    $schedule = $scheduler_api->get_schedule($scheduleid);
    if (!$schedule) {
        throw new moodle_exception('error:schedulenotfound', 'local_manireports');
    }
}

// Define form class.
class schedule_form extends moodleform {
    public function definition() {
        global $DB;
        
        $mform = $this->_form;
        
        // Schedule name.
        $mform->addElement('text', 'name', get_string('schedulename', 'local_manireports'), array('size' => 50));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        
        // Report type - prebuilt or custom.
        $reporttypes = array(
            'prebuilt' => get_string('prebuiltreports', 'local_manireports'),
            'custom' => get_string('customreports', 'local_manireports')
        );
        $mform->addElement('select', 'reportcategory', get_string('reportcategory', 'local_manireports'), $reporttypes);
        $mform->setDefault('reportcategory', 'prebuilt');
        
        // Prebuilt reports.
        $prebuilt_reports = array(
            'course_completion' => get_string('coursecompletion', 'local_manireports'),
            'course_progress' => get_string('courseprogress', 'local_manireports'),
            'scorm_summary' => get_string('scormsummary', 'local_manireports'),
            'user_engagement' => get_string('userengagement', 'local_manireports'),
            'quiz_attempts' => get_string('quizattempts', 'local_manireports')
        );
        $mform->addElement('select', 'reporttype', get_string('prebuiltreport', 'local_manireports'), $prebuilt_reports);
        $mform->hideIf('reporttype', 'reportcategory', 'eq', 'custom');
        
        // Custom reports.
        $custom_reports = array('' => get_string('selectreport', 'local_manireports'));
        $reports = $DB->get_records('manireports_customreports', null, 'name ASC');
        foreach ($reports as $report) {
            $custom_reports[$report->id] = $report->name . ' (' . strtoupper($report->type) . ')';
        }
        $mform->addElement('select', 'reportid', get_string('customreport', 'local_manireports'), $custom_reports);
        $mform->hideIf('reportid', 'reportcategory', 'eq', 'prebuilt');
        $mform->addRule('reportid', null, 'required', null, 'client');
        
        // Format.
        $formats = array(
            'csv' => 'CSV',
            'xlsx' => 'Excel (XLSX)',
            'pdf' => 'PDF'
        );
        $mform->addElement('select', 'format', get_string('format', 'moodle'), $formats);
        $mform->addRule('format', null, 'required', null, 'client');
        
        // Frequency.
        $frequencies = array(
            'daily' => get_string('daily', 'local_manireports'),
            'weekly' => get_string('weekly', 'local_manireports'),
            'monthly' => get_string('monthly', 'local_manireports')
        );
        $mform->addElement('select', 'frequency', get_string('frequency', 'local_manireports'), $frequencies);
        $mform->addRule('frequency', null, 'required', null, 'client');
        
        // Recipients (textarea for multiple emails).
        $mform->addElement('textarea', 'recipients', get_string('recipients', 'local_manireports'), 
                          array('rows' => 5, 'cols' => 50));
        $mform->setType('recipients', PARAM_TEXT);
        $mform->addHelpButton('recipients', 'recipients', 'local_manireports');
        $mform->addRule('recipients', null, 'required', null, 'client');
        
        // Enabled.
        $mform->addElement('advcheckbox', 'enabled', get_string('enabled', 'local_manireports'));
        $mform->setDefault('enabled', 1);
        
        // Hidden fields.
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        
        // Buttons.
        $this->add_action_buttons(true, get_string('save', 'moodle'));
    }
}

// Create form.
$mform = new schedule_form();

// Set form data if editing.
if ($schedule) {
    $formdata = clone $schedule;
    
    // Set report category based on reporttype.
    if ($schedule->reporttype === 'custom') {
        $formdata->reportcategory = 'custom';
    } else {
        $formdata->reportcategory = 'prebuilt';
    }
    
    // Get recipients as newline-separated list.
    $recipients = $scheduler_api->get_recipients($scheduleid);
    $emails = array();
    foreach ($recipients as $recipient) {
        $emails[] = $recipient->email;
    }
    $formdata->recipients = implode("\n", $emails);
    $mform->set_data($formdata);
}

// Handle form submission.
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/manireports/ui/schedules.php'));
} else if ($data = $mform->get_data()) {
    
    $schedule_data = new stdClass();
    $schedule_data->name = $data->name;
    
    // Set reporttype and reportid based on category.
    if ($data->reportcategory === 'custom') {
        $schedule_data->reporttype = 'custom';
        $schedule_data->reportid = $data->reportid;
    } else {
        $schedule_data->reporttype = $data->reporttype;
        $schedule_data->reportid = null;
    }
    
    $schedule_data->format = $data->format;
    $schedule_data->frequency = $data->frequency;
    $schedule_data->enabled = $data->enabled;
    $schedule_data->parameters = '{}'; // Empty JSON for now.
    
    if ($data->id) {
        // Update existing schedule.
        $scheduler_api->update_schedule($data->id, $schedule_data);
        $scheduleid = $data->id;
        $message = get_string('scheduleupdated', 'local_manireports');
    } else {
        // Create new schedule.
        $scheduleid = $scheduler_api->create_schedule($schedule_data);
        $message = get_string('schedulecreated', 'local_manireports');
    }
    
    // Update recipients.
    // First, remove all existing recipients.
    $existing_recipients = $scheduler_api->get_recipients($scheduleid);
    foreach ($existing_recipients as $recipient) {
        $scheduler_api->remove_recipient($scheduleid, $recipient->email);
    }
    
    // Add new recipients.
    $recipient_emails = explode("\n", $data->recipients);
    foreach ($recipient_emails as $email) {
        $email = trim($email);
        if (!empty($email) && validate_email($email)) {
            $scheduler_api->add_recipient($scheduleid, $email);
        }
    }
    
    redirect(new moodle_url('/local/manireports/ui/schedules.php'), 
             $message, 
             null, 
             \core\output\notification::NOTIFY_SUCCESS);
}

// Output header.
echo $OUTPUT->header();

// Display form.
$mform->display();

// Output footer.
echo $OUTPUT->footer();
