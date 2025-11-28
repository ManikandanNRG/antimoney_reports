<?php
namespace local_manireports\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class template_form extends \moodleform {

    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('templatename', 'local_manireports'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('text', 'subject', get_string('subject', 'local_manireports'));
        $mform->setType('subject', PARAM_TEXT);
        $mform->addRule('subject', null, 'required', null, 'client');

        $mform->addElement('editor', 'body_html', get_string('body', 'local_manireports'));
        $mform->setType('body_html', PARAM_RAW);
        $mform->addRule('body_html', null, 'required', null, 'client');

        // Placeholders help
        $mform->addElement('static', 'placeholders_help', get_string('placeholders', 'local_manireports'), 
            '{firstname}, {lastname}, {coursename}, {courselink}, {activityname}, {activitylink}, {siteurl}');

        $mform->addElement('selectyesno', 'enabled', get_string('enabled', 'local_manireports'));
        $mform->setDefault('enabled', 1);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
