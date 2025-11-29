<?php
namespace local_manireports\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class template_form extends \moodleform {

    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Template Name
        $mform->addElement('text', 'name', get_string('templatename', 'local_manireports'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');


        // Email Subject
        $mform->addElement('text', 'subject', get_string('subject', 'local_manireports'));
        $mform->setType('subject', PARAM_TEXT);
        $mform->addRule('subject', null, 'required', null, 'client');


        // Email Body (HTML Editor)
        $mform->addElement('editor', 'body_html', get_string('bodyhtml', 'local_manireports'));
        $mform->setType('body_html', PARAM_RAW);
        $mform->addRule('body_html', null, 'required', null, 'client');


        // Placeholders Information (Static Help Text)
        $placeholders_html = '<div style="background: rgba(99, 102, 241, 0.1); border: 1px solid rgba(99, 102, 241, 0.3); border-radius: 8px; padding: 12px; margin-top: 8px;">
            <strong style="color: #6366f1;">Available Placeholders:</strong><br>
            <code>{firstname}</code> - User\'s first name<br>
            <code>{lastname}</code> - User\'s last name<br>
            <code>{email}</code> - User\'s email address<br>
            <code>{coursename}</code> - Course name<br>
            <code>{courseurl}</code> - Link to the course<br>
            <code>{completiondate}</code> - Expected completion date
        </div>';
        $mform->addElement('static', 'placeholders_help', 'Placeholders', $placeholders_html);

        // Enabled
        $mform->addElement('selectyesno', 'enabled', get_string('enabled', 'local_manireports'));
        $mform->setDefault('enabled', 1);


        // Hidden ID field
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Validate template name
        if (empty(trim($data['name']))) {
            $errors['name'] = get_string('required');
        }

        // Validate subject
        if (empty(trim($data['subject']))) {
            $errors['subject'] = get_string('required');
        }

        // Validate body HTML
        if (isset($data['body_html']) && is_array($data['body_html'])) {
            $body_text = trim($data['body_html']['text']);
            if (empty($body_text)) {
                $errors['body_html'] = 'Email body cannot be empty';
            }
        }

        return $errors;
    }
}
