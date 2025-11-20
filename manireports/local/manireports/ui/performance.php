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
 * Performance monitoring dashboard.
 *
 * @package    local_manireports
 * @copyright  2024 ManiReports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();

$action = optional_param('action', '', PARAM_ALPHA);

$context = context_system::instance();
require_capability('local/manireports:viewadmindashboard', $context);

$PAGE->set_context($context);
$PAGE->set_url('/local/manireports/ui/performance.php');
$PAGE->set_title(get_string('performance', 'local_manireports'));
$PAGE->set_heading(get_string('performance', 'local_manireports'));
$PAGE->set_pagelayout('admin');

// Handle actions.
if ($action === 'ensure_indexes' && confirm_sesskey()) {
    $optimizer = new \local_manireports\api\performance_optimizer();
    $results = $optimizer->ensure_indexes();
    
    $message = get_string('indexesensured', 'local_manireports', $results);
    redirect($PAGE->url, $message, null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();

// Get performance stats.
$optimizer = new \local_manireports\api\performance_optimizer();
$stats = $optimizer->get_performance_stats();
$recommendations = $optimizer->get_task_recommendations();

// Display performance overview.
echo html_writer::tag('h3', get_string('performanceoverview', 'local_manireports'));

echo html_writer::start_div('row mb-4');

// Table sizes card.
echo html_writer::start_div('col-md-6');
echo html_writer::start_div('card');
echo html_writer::start_div('card-header');
echo html_writer::tag('h5', get_string('tablesizes', 'local_manireports'), ['class' => 'card-title']);
echo html_writer::end_div();
echo html_writer::start_div('card-body');

if (!empty($stats['tables'])) {
    echo html_writer::start_tag('table', ['class' => 'table table-sm']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', get_string('table', 'local_manireports'));
    echo html_writer::tag('th', get_string('records', 'local_manireports'), ['class' => 'text-right']);
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');
    
    foreach ($stats['tables'] as $table => $count) {
        $tablename = str_replace('manireports_', '', $table);
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', $tablename);
        echo html_writer::tag('td', number_format($count), ['class' => 'text-right']);
        echo html_writer::end_tag('tr');
    }
    
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
}

echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Concurrent reports card.
echo html_writer::start_div('col-md-6');
echo html_writer::start_div('card');
echo html_writer::start_div('card-header');
echo html_writer::tag('h5', get_string('concurrentreports', 'local_manireports'), ['class' => 'card-title']);
echo html_writer::end_div();
echo html_writer::start_div('card-body');

$concurrent = $stats['concurrent_reports'];
$max = $stats['max_concurrent_reports'];
$percentage = $max > 0 ? round(($concurrent / $max) * 100) : 0;

echo html_writer::start_tag('table', ['class' => 'table table-sm']);
echo html_writer::start_tag('tr');
echo html_writer::tag('td', get_string('currentlyrunning', 'local_manireports'));
echo html_writer::tag('td', $concurrent, ['class' => 'text-right']);
echo html_writer::end_tag('tr');
echo html_writer::start_tag('tr');
echo html_writer::tag('td', get_string('maximumallowed', 'local_manireports'));
echo html_writer::tag('td', $max, ['class' => 'text-right']);
echo html_writer::end_tag('tr');
echo html_writer::start_tag('tr');
echo html_writer::tag('td', get_string('utilization', 'local_manireports'));
echo html_writer::tag('td', $percentage . '%', ['class' => 'text-right']);
echo html_writer::end_tag('tr');
echo html_writer::end_tag('table');

// Progress bar.
$progressclass = 'bg-success';
if ($percentage > 80) {
    $progressclass = 'bg-danger';
} else if ($percentage > 60) {
    $progressclass = 'bg-warning';
}

echo html_writer::start_div('progress');
echo html_writer::div('', 'progress-bar ' . $progressclass, [
    'role' => 'progressbar',
    'style' => 'width: ' . $percentage . '%',
    'aria-valuenow' => $percentage,
    'aria-valuemin' => '0',
    'aria-valuemax' => '100',
]);
echo html_writer::end_div();

echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div();

// Cache statistics.
if (!isset($stats['cache']['error'])) {
    echo html_writer::tag('h3', get_string('cachestatistics', 'local_manireports'));
    
    echo html_writer::start_div('card mb-4');
    echo html_writer::start_div('card-body');
    
    $hitrate = $stats['cache']['hit_rate'];
    $hitrateclass = 'text-success';
    if ($hitrate < 50) {
        $hitrateclass = 'text-danger';
    } else if ($hitrate < 70) {
        $hitrateclass = 'text-warning';
    }
    
    echo html_writer::start_tag('table', ['class' => 'table table-sm']);
    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', get_string('totalcacheentries', 'local_manireports'));
    echo html_writer::tag('td', number_format($stats['cache']['total']), ['class' => 'text-right']);
    echo html_writer::end_tag('tr');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', get_string('validcacheentries', 'local_manireports'));
    echo html_writer::tag('td', number_format($stats['cache']['valid']), ['class' => 'text-right']);
    echo html_writer::end_tag('tr');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', get_string('cachehitrate', 'local_manireports'));
    echo html_writer::tag('td', $hitrate . '%', ['class' => 'text-right ' . $hitrateclass]);
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('table');
    
    echo html_writer::end_div();
    echo html_writer::end_div();
}

// Task recommendations.
if (!empty($recommendations)) {
    echo html_writer::tag('h3', get_string('taskrecommendations', 'local_manireports'));
    
    echo html_writer::start_div('card mb-4');
    echo html_writer::start_div('card-body');
    
    echo html_writer::start_tag('table', ['class' => 'table table-striped']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', get_string('task', 'local_manireports'));
    echo html_writer::tag('th', get_string('recommendation', 'local_manireports'));
    echo html_writer::tag('th', get_string('reason', 'local_manireports'));
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');
    
    foreach ($recommendations as $rec) {
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', str_replace('\\local_manireports\\task\\', '', $rec['task']));
        echo html_writer::tag('td', $rec['recommendation']);
        echo html_writer::tag('td', $rec['reason']);
        echo html_writer::end_tag('tr');
    }
    
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    
    echo html_writer::end_div();
    echo html_writer::end_div();
}

// Actions.
echo html_writer::tag('h3', get_string('actions', 'local_manireports'));

echo html_writer::start_div('card');
echo html_writer::start_div('card-body');

$ensureurl = new moodle_url($PAGE->url, ['action' => 'ensure_indexes', 'sesskey' => sesskey()]);
echo html_writer::link(
    $ensureurl,
    get_string('ensureindexes', 'local_manireports'),
    ['class' => 'btn btn-primary mr-2']
);

$settingsurl = new moodle_url('/admin/settings.php', ['section' => 'local_manireports_settings']);
echo html_writer::link(
    $settingsurl,
    get_string('settings', 'local_manireports'),
    ['class' => 'btn btn-secondary']
);

echo html_writer::end_div();
echo html_writer::end_div();

echo $OUTPUT->footer();
