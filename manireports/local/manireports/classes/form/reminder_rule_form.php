<?php
namespace local_manireports\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class reminder_rule_form extends \moodleform {

    public function definition() {
        global $DB;

        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('rulename', 'local_manireports'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        // Company (if IOMAD)
        // For now, simple text or select if we can fetch companies
        // $mform->addElement('select', 'companyid', ...);
        $mform->addElement('hidden', 'companyid', 0); // Default to global for now
        $mform->setType('companyid', PARAM_INT);

        // Course
        // Loading all courses might be heavy. Ideally use an autocomplete.
        // For simplicity, let's use a text input for ID or a small select if few courses.
        // Let's use a text input for Course ID for now, or 0 for all.
        $mform->addElement('text', 'courseid', get_string('courseid', 'local_manireports'));
        $mform->setType('courseid', PARAM_INT);
        $mform->setDefault('courseid', 0);
        $mform->addHelpButton('courseid', 'courseid', 'local_manireports');

        $mform->addElement('header', 'trigger_settings', get_string('triggersettings', 'local_manireports'));

        $triggers = [
            'enrol' => get_string('trigger_enrol', 'local_manireports'),
            'start_date' => get_string('trigger_startdate', 'local_manireports'),
            'incomplete_after' => get_string('trigger_incomplete', 'local_manireports'),
        ];
        $mform->addElement('select', 'trigger_type', get_string('triggertype', 'local_manireports'), $triggers);

        // Trigger Value (Days)
        $mform->addElement('text', 'trigger_days', get_string('days', 'local_manireports'));
        $mform->setType('trigger_days', PARAM_INT);
        $mform->setDefault('trigger_days', 7);

        $mform->addElement('header', 'schedule_settings', get_string('schedulesettings', 'local_manireports'));

        $mform->addElement('duration', 'emaildelay', get_string('emaildelay', 'local_manireports'));
        $mform->setDefault('emaildelay', 86400); // 1 day

        $mform->addElement('text', 'remindercount', get_string('remindercount', 'local_manireports'));
        $mform->setType('remindercount', PARAM_INT);
        $mform->setDefault('remindercount', 1);

        $mform->addElement('header', 'recipient_settings', get_string('recipientsettings', 'local_manireports'));

        $mform->addElement('checkbox', 'send_to_user', get_string('sendtouser', 'local_manireports'));
        $mform->setDefault('send_to_user', 1);

        $mform->addElement('checkbox', 'send_to_managers', get_string('sendtomanagers', 'local_manireports'));
        $mform->setDefault('send_to_managers', 0);

        $mform->addElement('textarea', 'thirdparty_emails', get_string('thirdpartyemails', 'local_manireports'), 'rows="3" cols="50"');
        $mform->setType('thirdparty_emails', PARAM_TEXT);

        $mform->addElement('header', 'content_settings', get_string('contentsettings', 'local_manireports'));

        // Template Select
        $templates = $DB->get_records_menu('manireports_rem_tmpl', ['enabled' => 1], 'name ASC', 'id, name');
        $mform->addElement('select', 'templateid', get_string('template', 'local_manireports'), $templates);
        $mform->addRule('templateid', null, 'required', null, 'client');

        $mform->addElement('selectyesno', 'enabled', get_string('enabled', 'local_manireports'));
        $mform->setDefault('enabled', 1);

        $this->add_action_buttons();
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        // Add custom validation if needed
        return $errors;
    }
}
