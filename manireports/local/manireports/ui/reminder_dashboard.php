<?php
require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('manireports_reminders');

$PAGE->set_url(new moodle_url('/local/manireports/ui/reminder_dashboard.php'));
$PAGE->set_title(get_string('reminderdashboard', 'local_manireports'));
$PAGE->set_heading(get_string('reminderdashboard', 'local_manireports'));

echo $OUTPUT->header();

// Print tabs.
local_manireports_print_tabs('analytics');

// KPIs
$total_sent = $DB->count_records('manireports_rem_job', ['status' => 'delivered']); // or local_sent
$total_queued = $DB->count_records('manireports_rem_job', ['status' => 'enqueued']);
$total_failed = $DB->count_records('manireports_rem_job', ['status' => 'failed']);
$today_sent = $DB->count_records_select('manireports_rem_job', 'last_attempt_ts > ?', [strtotime('today')]);

echo '<div class="row mb-4">';
echo '<div class="col-md-3"><div class="card bg-primary text-white"><div class="card-body"><h3>' . $total_sent . '</h3><p>Total Sent</p></div></div></div>';
echo '<div class="col-md-3"><div class="card bg-warning text-dark"><div class="card-body"><h3>' . $total_queued . '</h3><p>Queued</p></div></div></div>';
echo '<div class="col-md-3"><div class="card bg-danger text-white"><div class="card-body"><h3>' . $total_failed . '</h3><p>Failed</p></div></div></div>';
echo '<div class="col-md-3"><div class="card bg-success text-white"><div class="card-body"><h3>' . $today_sent . '</h3><p>Sent Today</p></div></div></div>';
echo '</div>';

// Chart: Delivery Trends (Last 7 Days)
$chart = new \core\chart_line();
$series = new \core\chart_series('Sent Emails', []);
$labels = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $start = strtotime($date);
    $end = $start + 86400;
    $count = $DB->count_records_select('manireports_rem_job', 'last_attempt_ts >= ? AND last_attempt_ts < ?', [$start, $end]);
    $series->add_data($count);
    $labels[] = date('D', $start);
}

$chart->add_series($series);
$chart->set_labels($labels);
echo $OUTPUT->render($chart);

// Recent Jobs Table
echo '<h3>Recent Activity</h3>';
$jobs = $DB->get_records('manireports_rem_job', null, 'last_attempt_ts DESC', '*', 0, 20);

if ($jobs) {
    $table = new html_table();
    $table->head = ['Recipient', 'Status', 'Time', 'Message ID'];
    $table->data = [];
    foreach ($jobs as $job) {
        $table->data[] = [
            $job->recipient_email,
            $job->status,
            userdate($job->last_attempt_ts),
            $job->message_id
        ];
    }
    echo html_writer::table($table);
} else {
    echo '<p>No recent activity.</p>';
}

echo $OUTPUT->footer();
