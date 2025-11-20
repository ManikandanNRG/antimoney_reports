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
 * Failed jobs management interface.
 *
 * @package    local_manireports
 * @copyright  2024 ManiReports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();

$action = optional_param('action', '', PARAM_ALPHA);
$jobid = optional_param('jobid', 0, PARAM_INT);

$context = context_system::instance();
require_capability('local/manireports:viewadmindashboard', $context);

$PAGE->set_context($context);
$PAGE->set_url('/local/manireports/ui/failed_jobs.php');
$PAGE->set_title(get_string('failedjobs', 'local_manireports'));
$PAGE->set_heading(get_string('failedjobs', 'local_manireports'));
$PAGE->set_pagelayout('admin');

// Handle actions.
if ($action === 'retry' && $jobid && confirm_sesskey()) {
    $handler = new \local_manireports\api\error_handler();
    
    if ($handler->retry_failed_job($jobid)) {
        redirect($PAGE->url, get_string('jobretried', 'local_manireports'), null, \core\output\notification::NOTIFY_SUCCESS);
    } else {
        redirect($PAGE->url, get_string('jobretryfailed', 'local_manireports'), null, \core\output\notification::NOTIFY_ERROR);
    }
}

if ($action === 'delete' && $jobid && confirm_sesskey()) {
    $DB->delete_records('manireports_failed_jobs', ['id' => $jobid]);
    redirect($PAGE->url, get_string('jobdeleted', 'local_manireports'), null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'clearold' && confirm_sesskey()) {
    $handler = new \local_manireports\api\error_handler();
    $count = $handler->clear_old_failed_jobs(30);
    redirect($PAGE->url, get_string('jobscleared', 'local_manireports', $count), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();

// System health check.
$handler = new \local_manireports\api\error_handler();
$health = $handler->check_system_health();

echo html_writer::tag('h3', get_string('systemhealth', 'local_manireports'));

$healthclass = 'alert-success';
if ($health['status'] === 'warning') {
    $healthclass = 'alert-warning';
} else if ($health['status'] === 'critical') {
    $healthclass = 'alert-danger';
}

echo html_writer::start_div('alert ' . $healthclass);
echo html_writer::tag('h5', get_string('status', 'local_manireports') . ': ' . ucfirst($health['status']));

if (!empty($health['checks'])) {
    echo html_writer::start_tag('ul');
    foreach ($health['checks'] as $check) {
        echo html_writer::tag('li', '✓ ' . $check);
    }
    echo html_writer::end_tag('ul');
}

if (!empty($health['warnings'])) {
    echo html_writer::start_tag('ul');
    foreach ($health['warnings'] as $warning) {
        echo html_writer::tag('li', '⚠ ' . $warning, ['class' => 'text-warning']);
    }
    echo html_writer::end_tag('ul');
}

if (!empty($health['errors'])) {
    echo html_writer::start_tag('ul');
    foreach ($health['errors'] as $error) {
        echo html_writer::tag('li', '✗ ' . $error, ['class' => 'text-danger']);
    }
    echo html_writer::end_tag('ul');
}

echo html_writer::end_div();

// Failed jobs list.
echo html_writer::tag('h3', get_string('failedjobs', 'local_manireports'));

$jobs = $handler->get_failed_jobs();

if (empty($jobs)) {
    echo html_writer::div(
        get_string('nofailedjobs', 'local_manireports'),
        'alert alert-success'
    );
} else {
    // Actions.
    $clearurl = new moodle_url($PAGE->url, ['action' => 'clearold', 'sesskey' => sesskey()]);
    echo html_writer::link($clearurl, get_string('clearoldjobs', 'local_manireports'), ['class' => 'btn btn-secondary mb-3']);

    echo html_writer::start_tag('table', ['class' => 'table table-striped']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', get_string('task', 'local_manireports'));
    echo html_writer::tag('th', get_string('error', 'local_manireports'));
    echo html_writer::tag('th', get_string('timefailed', 'local_manireports'));
    echo html_writer::tag('th', get_string('retrycount', 'local_manireports'));
    echo html_writer::tag('th', get_string('actions', 'local_manireports'));
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');

    foreach ($jobs as $job) {
        echo html_writer::start_tag('tr');
        
        // Task name.
        $taskname = str_replace('\\local_manireports\\task\\', '', $job->taskname);
        echo html_writer::tag('td', $taskname);
        
        // Error (truncated).
        $error = strlen($job->error) > 100 ? substr($job->error, 0, 100) . '...' : $job->error;
        echo html_writer::tag('td', html_writer::tag('small', s($error)), ['title' => s($job->error)]);
        
        // Time failed.
        echo html_writer::tag('td', userdate($job->timefailed));
        
        // Retry count.
        echo html_writer::tag('td', $job->retrycount);
        
        // Actions.
        $retryurl = new moodle_url($PAGE->url, ['action' => 'retry', 'jobid' => $job->id, 'sesskey' => sesskey()]);
        $deleteurl = new moodle_url($PAGE->url, ['action' => 'delete', 'jobid' => $job->id, 'sesskey' => sesskey()]);
        
        $actions = html_writer::link($retryurl, get_string('retry', 'local_manireports'), ['class' => 'btn btn-sm btn-primary mr-1']);
        $actions .= html_writer::link($deleteurl, get_string('delete'), ['class' => 'btn btn-sm btn-danger']);
        
        echo html_writer::tag('td', $actions);
        
        echo html_writer::end_tag('tr');
        
        // Expandable details row.
        if (!empty($job->stacktrace)) {
            echo html_writer::start_tag('tr', ['class' => 'collapse', 'id' => 'details-' . $job->id]);
            echo html_writer::start_tag('td', ['colspan' => '5']);
            echo html_writer::tag('strong', get_string('stacktrace', 'local_manireports'));
            echo html_writer::tag('pre', s($job->stacktrace), ['class' => 'small']);
            echo html_writer::end_tag('td');
            echo html_writer::end_tag('tr');
        }
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
}

echo $OUTPUT->footer();
