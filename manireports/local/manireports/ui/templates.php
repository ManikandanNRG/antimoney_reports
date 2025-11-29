<?php
require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/../classes/form/template_form.php');

admin_externalpage_setup('manireports_reminders'); // Reuse same permission check

$action = optional_param('action', 'list', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

$PAGE->set_url(new moodle_url('/local/manireports/ui/templates.php'));
$PAGE->set_title(get_string('templates', 'local_manireports'));
$PAGE->set_heading(get_string('templates', 'local_manireports'));

$form = new \local_manireports\form\template_form(new moodle_url($PAGE->url, ['action' => 'edit', 'id' => $id]));

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/manireports/ui/templates.php'));
} else if ($data = $form->get_data()) {
    $data->timemodified = time();
    $data->body_text = html_to_text($data->body_html['text']);
    $data->body_html = $data->body_html['text']; // Extract text from editor array

    if ($data->id) {
        $DB->update_record('manireports_rem_tmpl', $data);
        $msg = get_string('templateupdated', 'local_manireports');
    } else {
        $DB->insert_record('manireports_rem_tmpl', $data);
        $msg = get_string('templatecreated', 'local_manireports');
    }
    redirect(new moodle_url('/local/manireports/ui/templates.php'), $msg, null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();

// Print tabs.
local_manireports_print_tabs('templates');

if ($action === 'edit' || $action === 'add') {
    if ($id && $action === 'edit') {
        $template = $DB->get_record('manireports_rem_tmpl', ['id' => $id], '*', MUST_EXIST);
        $template->body_html = ['text' => $template->body_html, 'format' => FORMAT_HTML];
        $form->set_data($template);
    }
    echo $OUTPUT->heading($id ? get_string('edittemplate', 'local_manireports') : get_string('addtemplate', 'local_manireports'));
    $form->display();
} else {
    // List View
    echo $OUTPUT->single_button(new moodle_url($PAGE->url, ['action' => 'add']), get_string('addtemplate', 'local_manireports'), 'get', ['class' => 'btn-primary mb-3']);

    $templates = $DB->get_records('manireports_rem_tmpl', null, 'name ASC');
    if ($templates) {
        $table = new html_table();
        $table->head = ['Name', 'Subject', 'Enabled', 'Actions'];
        $table->data = [];
        foreach ($templates as $tmpl) {
            $editurl = new moodle_url($PAGE->url, ['action' => 'edit', 'id' => $tmpl->id]);
            $deleteurl = new moodle_url($PAGE->url, ['action' => 'delete', 'id' => $tmpl->id, 'sesskey' => sesskey()]);
            
            $actions = [
                html_writer::link($editurl, 'Edit', ['class' => 'btn btn-sm btn-secondary']),
                // html_writer::link($deleteurl, 'Delete', ['class' => 'btn btn-sm btn-danger']) // Soft delete logic needed
            ];

            $table->data[] = [
                format_string($tmpl->name),
                format_string($tmpl->subject),
                $tmpl->enabled ? 'Yes' : 'No',
                implode(' ', $actions)
            ];
        }
        echo html_writer::table($table);
    } else {
        echo $OUTPUT->notification(get_string('notemplates', 'local_manireports'), 'info');
    }
}

echo $OUTPUT->footer();
