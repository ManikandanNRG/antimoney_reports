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
 * Custom reports management page.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_manireports\api\report_builder;

require_login();

$context = context_system::instance();
require_capability('local/manireports:customreports', $context);

$action = optional_param('action', 'list', PARAM_ALPHA);
$reportid = optional_param('id', 0, PARAM_INT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/manireports/ui/custom_reports.php'));
$PAGE->set_title(get_string('customreports', 'local_manireports'));
$PAGE->set_heading(get_string('customreports', 'local_manireports'));
$PAGE->set_pagelayout('admin');

$builder = new report_builder();

// Handle actions.
if ($action === 'delete' && $reportid > 0) {
    require_sesskey();
    
    try {
        $builder->delete_report($reportid, $USER->id);
        redirect(
            new moodle_url('/local/manireports/ui/custom_reports.php'),
            get_string('reportdeleted', 'local_manireports'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (Exception $e) {
        redirect(
            new moodle_url('/local/manireports/ui/custom_reports.php'),
            $e->getMessage(),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

echo $OUTPUT->header();

// Display action buttons.
if ($action === 'list') {
    echo html_writer::start_div('mb-3');
    echo html_writer::link(
        new moodle_url('/local/manireports/ui/custom_report_edit.php'),
        get_string('createcustomreport', 'local_manireports'),
        array('class' => 'btn btn-primary')
    );
    echo html_writer::end_div();
}

// Get reports.
$reports = $builder->get_reports($USER->id);

if (empty($reports)) {
    echo $OUTPUT->notification(get_string('nocustomreports', 'local_manireports'), 'info');
} else {
    // Display reports table.
    $table = new html_table();
    $table->head = array(
        get_string('name', 'moodle'),
        get_string('description', 'moodle'),
        get_string('type', 'moodle'),
        get_string('timecreated', 'moodle'),
        get_string('actions', 'local_manireports')
    );
    $table->attributes['class'] = 'generaltable';

    foreach ($reports as $report) {
        $viewurl = new moodle_url('/local/manireports/ui/report_view.php', array('id' => $report->id));
        $editurl = new moodle_url('/local/manireports/ui/custom_report_edit.php', array('id' => $report->id));
        $deleteurl = new moodle_url('/local/manireports/ui/custom_reports.php', array(
            'action' => 'delete',
            'id' => $report->id,
            'sesskey' => sesskey()
        ));

        $actions = array();
        $actions[] = html_writer::link($viewurl, get_string('view'), array('class' => 'btn btn-sm btn-secondary'));
        $actions[] = html_writer::link($editurl, get_string('edit'), array('class' => 'btn btn-sm btn-primary'));
        $actions[] = html_writer::link(
            $deleteurl,
            get_string('delete'),
            array(
                'class' => 'btn btn-sm btn-danger',
                'onclick' => 'return confirm("' . get_string('confirmdelete', 'local_manireports') . '");'
            )
        );

        $row = array(
            format_string($report->name),
            format_text($report->description, FORMAT_PLAIN),
            strtoupper($report->type),
            userdate($report->timecreated),
            implode(' ', $actions)
        );

        $table->data[] = $row;
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
