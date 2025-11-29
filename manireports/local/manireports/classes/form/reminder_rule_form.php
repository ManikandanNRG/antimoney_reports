<?php
namespace local_manireports\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class reminder_rule_form extends \moodleform {

    public function definition() {
        global $DB;

        $mform = $this->_form;

        // General Section
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Rule Name
        $mform->addElement('text', 'name', get_string('rulename', 'local_manireports'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addHelpButton('name', 'rulename', 'local_manireports');

        // Company (hidden for now)
        $mform->addElement('hidden', 'companyid', 0);
        $mform->setType('companyid', PARAM_INT);

        // Course Dropdown (User-Friendly)
        $courses = $DB->get_records_menu('course', null, 'fullname ASC', 'id, fullname');
        $course_options = [0 => get_string('allcourses', 'local_manireports')] + $courses;
        $mform->addElement('select', 'courseid', get_string('courseid', 'local_manireports'), $course_options);
        $mform->setDefault('courseid', 0);
        $mform->addHelpButton('courseid', 'courseid', 'local_manireports');

        // Trigger Settings
        $mform->addElement('header', 'trigger_settings', 'Trigger Settings');

        // Trigger Type
        $triggers = [
            'enrol' => get_string('triggertype_enrol', 'local_manireports'),
            'incomplete_after' => get_string('triggertype_incomplete', 'local_manireports'),
        ];
        $mform->addElement('select', 'trigger_type', get_string('triggertype', 'local_manireports'), $triggers);
        $mform->addHelpButton('trigger_type', 'triggertype', 'local_manireports');

        // Trigger Days
        $mform->addElement('text', 'trigger_days', get_string('triggerdays', 'local_manireports'));
        $mform->setType('trigger_days', PARAM_INT);
        $mform->setDefault('trigger_days', 7);
        $mform->addHelpButton('trigger_days', 'triggerdays', 'local_manireports');

        // Schedule Settings
        $mform->addElement('header', 'schedule_settings', 'Schedule Settings');

        // Email Delay
        $mform->addElement('duration', 'emaildelay', get_string('emaildelay', 'local_manireports'));
        $mform->setDefault('emaildelay', 86400); // 1 day
        $mform->addHelpButton('emaildelay', 'emaildelay', 'local_manireports');

        // Reminder Count
        $mform->addElement('text', 'remindercount', get_string('remindercount', 'local_manireports'));
        $mform->setType('remindercount', PARAM_INT);
        $mform->setDefault('remindercount', 1);
        $mform->addHelpButton('remindercount', 'remindercount', 'local_manireports');

        // Recipient Settings
        $mform->addElement('header', 'recipient_settings', 'Recipient Settings');

        // Send to User
        $mform->addElement('checkbox', 'send_to_user', get_string('sendtousers', 'local_manireports'));
        $mform->setDefault('send_to_user', 1);
        $mform->addHelpButton('send_to_user', 'sendtousers', 'local_manireports');

        // Send to Managers
        $mform->addElement('checkbox', 'send_to_managers', get_string('sendtomanagers', 'local_manireports'));
        $mform->setDefault('send_to_managers', 0);
        $mform->addHelpButton('send_to_managers', 'sendtomanagers', 'local_manireports');

        // Third Party Emails
        $mform->addElement('textarea', 'thirdparty_emails', get_string('thirdpartyemails', 'local_manireports'), 'rows="3" cols="50"');
        $mform->setType('thirdparty_emails', PARAM_TEXT);
        $mform->addHelpButton('thirdparty_emails', 'thirdpartyemails', 'local_manireports');

        // Content Settings
        $mform->addElement('header', 'content_settings', 'Content Settings');

        // Template Dropdown (User-Friendly)
        $templates = $DB->get_records_menu('manireports_rem_tmpl', ['enabled' => 1], 'name ASC', 'id, name');
        if (empty($templates)) {
            $templates = [0 => get_string('notemplates', 'local_manireports')];
        }
        $mform->addElement('select', 'templateid', get_string('templateid', 'local_manireports'), $templates);
        $mform->addRule('templateid', null, 'required', null, 'client');
        $mform->addHelpButton('templateid', 'templateid', 'local_manireports');

        // Enabled
        $mform->addElement('selectyesno', 'enabled', get_string('enabled', 'local_manireports'));
        $mform->setDefault('enabled', 1);
        $mform->addHelpButton('enabled', 'enabled', 'local_manireports');

        $this->add_action_buttons();
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Validate rule name
        if (empty(trim($data['name']))) {
            $errors['name'] = get_string('required');
        }

        // Validate reminder count (1-5)
        if (isset($data['remindercount'])) {
            if ($data['remindercount'] < 1 || $data['remindercount'] > 5) {
                $errors['remindercount'] = 'Number of reminders must be between 1 and 5';
            }
        }

        // Validate trigger days
        if (isset($data['trigger_days']) && $data['trigger_days'] < 0) {
            $errors['trigger_days'] = 'Trigger days must be a positive number';
        }

        // Validate third party emails format
        if (!empty($data['thirdparty_emails'])) {
            $emails = explode(',', $data['thirdparty_emails']);
            foreach ($emails as $email) {
                $email = trim($email);
                if (!empty($email) && !validate_email($email)) {
                    $errors['thirdparty_emails'] = 'Invalid email format. Use comma-separated email addresses.';
                    break;
                }
            }
        }

        // Validate template selection
        if (empty($data['templateid']) || $data['templateid'] == 0) {
            $errors['templateid'] = 'Please select an email template';
        }

        return $errors;
    }
}
