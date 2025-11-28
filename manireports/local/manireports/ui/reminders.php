<?php
require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('manireports_reminders');

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

$PAGE->set_url(new moodle_url('/local/manireports/ui/reminders.php'));
$PAGE->set_title(get_string('reminders', 'local_manireports'));
$PAGE->set_heading(get_string('reminders', 'local_manireports'));

// Handle Actions
if ($action === 'delete' && $id && confirm_sesskey()) {
    $DB->set_field('manireports_reminder_rule', 'enabled', 0, ['id' => $id]);
    redirect($PAGE->url, get_string('ruledeleted', 'local_manireports'), null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'run' && $id && confirm_sesskey()) {
    // Manually trigger instance creation for this rule
    $manager = new \local_manireports\api\ReminderManager();
    $count = $manager->create_instances($id);
    redirect($PAGE->url, get_string('instancescreated', 'local_manireports', $count), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();

// Print tabs.
local_manireports_print_tabs('reminders');

// Add "New Rule" button
echo $OUTPUT->single_button(new moodle_url('/local/manireports/ui/reminder_edit.php'), get_string('addnewrule', 'local_manireports'), 'get', ['class' => 'btn-primary mb-3']);

// List Rules
$rules = $DB->get_records('manireports_reminder_rule', ['enabled' => 1]);

if ($rules) {
    $table = new html_table();
    $table->head = ['Name', 'Trigger', 'Delay', 'Count', 'Actions'];
    $table->data = [];

    foreach ($rules as $rule) {
        $editurl = new moodle_url('/local/manireports/ui/reminder_edit.php', ['id' => $rule->id]);
        $deleteurl = new moodle_url($PAGE->url, ['action' => 'delete', 'id' => $rule->id, 'sesskey' => sesskey()]);
        $runurl = new moodle_url($PAGE->url, ['action' => 'run', 'id' => $rule->id, 'sesskey' => sesskey()]);

        $actions = [
            html_writer::link($editurl, 'Edit', ['class' => 'btn btn-sm btn-secondary']),
            html_writer::link($runurl, 'Run Now', ['class' => 'btn btn-sm btn-info']),
            html_writer::link($deleteurl, 'Delete', ['class' => 'btn btn-sm btn-danger', 'onclick' => 'return confirm("Are you sure?")'])
        ];

        $table->data[] = [
            format_string($rule->name),
            $rule->trigger_type,
            format_time($rule->emaildelay),
            $rule->remindercount,
            implode(' ', $actions)
        ];
    }

    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('norulesfound', 'local_manireports'), 'info');
}

echo $OUTPUT->footer();
