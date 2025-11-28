<?php
require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/../classes/form/reminder_rule_form.php');

admin_externalpage_setup('manireports_reminders');

$id = optional_param('id', 0, PARAM_INT);

$PAGE->set_url(new moodle_url('/local/manireports/ui/reminder_edit.php', ['id' => $id]));
$PAGE->set_title(get_string('editrule', 'local_manireports'));
$PAGE->set_heading(get_string('editrule', 'local_manireports'));

$form = new \local_manireports\form\reminder_rule_form(null, ['id' => $id]);

if ($id) {
    $rule = $DB->get_record('manireports_reminder_rule', ['id' => $id], '*', MUST_EXIST);
    // Unpack trigger value
    $trigger_value = json_decode($rule->trigger_value, true);
    $rule->trigger_days = isset($trigger_value['days']) ? $trigger_value['days'] : 0;
    $form->set_data($rule);
}

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/manireports/ui/reminders.php'));
} else if ($data = $form->get_data()) {
    $manager = new \local_manireports\api\ReminderManager();
    
    // Pack trigger value
    $data->trigger_value = json_encode(['days' => $data->trigger_days]);
    unset($data->trigger_days);

    if ($id) {
        $manager->update_rule($id, $data);
        $msg = get_string('ruleupdated', 'local_manireports');
    } else {
        $manager->create_rule($data);
        $msg = get_string('rulecreated', 'local_manireports');
    }

    redirect(new moodle_url('/local/manireports/ui/reminders.php'), $msg, null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
