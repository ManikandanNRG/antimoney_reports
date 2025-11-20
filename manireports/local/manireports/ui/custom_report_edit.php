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
 * Custom report edit/create page.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/formslib.php');

use local_manireports\api\report_builder;

require_login();

$context = context_system::instance();
require_capability('local/manireports:customreports', $context);

$reportid = optional_param('id', 0, PARAM_INT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/manireports/ui/custom_report_edit.php', array('id' => $reportid)));
$PAGE->set_title(get_string('customreport', 'local_manireports'));
$PAGE->set_heading(get_string('customreport', 'local_manireports'));
$PAGE->set_pagelayout('admin');

$builder = new report_builder();

// Get existing report if editing.
$report = null;
if ($reportid > 0) {
    $report = $DB->get_record('manireports_customreports', array('id' => $reportid), '*', MUST_EXIST);
}

/**
 * Custom report form.
 */
class custom_report_form extends moodleform {
    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;

        // Report ID (hidden).
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // Report name.
        $mform->addElement('text', 'name', get_string('reportname', 'local_manireports'), array('size' => 60));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');

        // Description.
        $mform->addElement('textarea', 'description', get_string('description'), array('rows' => 3, 'cols' => 60));
        $mform->setType('description', PARAM_TEXT);

        // Report type.
        $types = array(
            'sql' => get_string('sqlreport', 'local_manireports'),
        );
        $mform->addElement('select', 'type', get_string('reporttype', 'local_manireports'), $types);
        $mform->setDefault('type', 'sql');

        // SQL query.
        $mform->addElement('textarea', 'sqlquery', get_string('sqlquery', 'local_manireports'), array('rows' => 15, 'cols' => 80));
        $mform->setType('sqlquery', PARAM_RAW);
        $mform->addRule('sqlquery', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('sqlquery', 'sqlquery', 'local_manireports');

        // Show allowed tables.
        $allowedtables = report_builder::get_allowed_tables();
        $tableslist = html_writer::tag('div', 
            html_writer::tag('strong', get_string('allowedtables', 'local_manireports') . ': ') .
            html_writer::tag('code', implode(', ', $allowedtables)),
            array('class' => 'alert alert-info')
        );
        $mform->addElement('html', $tableslist);

        // Action buttons.
        $this->add_action_buttons(true, get_string('savereport', 'local_manireports'));
    }

    /**
     * Form validation.
     *
     * @param array $data Form data
     * @param array $files Form files
     * @return array Errors
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Validate SQL if provided.
        if (!empty($data['sqlquery'])) {
            $builder = new report_builder();
            if (!$builder->validate_sql($data['sqlquery'])) {
                $errors['sqlquery'] = get_string('error:invalidsql', 'local_manireports');
            }
        }

        return $errors;
    }
}

$mform = new custom_report_form();

// Set form data if editing.
if ($report) {
    $mform->set_data($report);
}

// Handle form submission.
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/manireports/ui/custom_reports.php'));
} else if ($data = $mform->get_data()) {
    try {
        if ($data->id > 0) {
            // Update existing report.
            $builder->update_report($data->id, $data, $USER->id);
            $message = get_string('reportupdated', 'local_manireports');
        } else {
            // Create new report.
            $reportid = $builder->save_report($data, $USER->id);
            $message = get_string('reportcreated', 'local_manireports');
        }

        redirect(
            new moodle_url('/local/manireports/ui/custom_reports.php'),
            $message,
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (Exception $e) {
        echo $OUTPUT->header();
        echo $OUTPUT->notification($e->getMessage(), 'error');
        $mform->display();
        echo $OUTPUT->footer();
        die();
    }
}

echo $OUTPUT->header();

// Display form.
$mform->display();

echo $OUTPUT->footer();
