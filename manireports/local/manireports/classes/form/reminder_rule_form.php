<?php
namespace local_manireports\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class reminder_rule_form extends \moodleform {

    public function definition() {
        global $DB;

        $mform = $this->_form;

        // General Section
        // General Section
        $mform->addElement('html', '<h4 class="text-xl font-bold text-gray-800 dark:text-white mb-4 mt-2 border-b border-gray-200 dark:border-gray-700 pb-2">' . get_string('general', 'form') . '</h4>');

        // Rule Name
        $mform->addElement('text', 'name', get_string('rulename', 'local_manireports'));
        $mform->setType('name', PARAM_TEXT);

        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addElement('static', 'name_help', '', '<div class="text-sm text-gray-500 dark:text-gray-400 mt-1">' . get_string('rulename_help', 'local_manireports') . '</div>');


        // Company (hidden for now)
        $mform->addElement('hidden', 'companyid', 0);
        $mform->setType('companyid', PARAM_INT);

        // Course Dropdown (User-Friendly)
        $courses = $DB->get_records_menu('course', null, 'fullname ASC', 'id, fullname');
        $course_options = [0 => get_string('allcourses', 'local_manireports')] + $courses;
        $mform->addElement('select', 'courseid', get_string('courseid', 'local_manireports'), $course_options);

        $mform->setDefault('courseid', 0);
        $mform->addElement('static', 'courseid_help', '', '<div class="text-sm text-gray-500 dark:text-gray-400 mt-1">' . get_string('courseid_help', 'local_manireports') . '</div>');


        // Trigger Settings
        // Trigger Settings
        $mform->addElement('html', '<h4 class="text-xl font-bold text-gray-800 dark:text-white mb-4 mt-6 border-b border-gray-200 dark:border-gray-700 pb-2">Trigger Settings</h4>');

        // Trigger Type
        $triggers = [
            'enrol' => get_string('triggertype_enrol', 'local_manireports'),
            'incomplete_after' => get_string('triggertype_incomplete', 'local_manireports'),
        ];
        $mform->addElement('select', 'trigger_type', get_string('triggertype', 'local_manireports'), $triggers);
        $mform->addElement('static', 'trigger_type_help', '', '<div class="text-sm text-gray-500 dark:text-gray-400 mt-1">' . get_string('triggertype_help', 'local_manireports') . '</div>');


        // Trigger Days
        $mform->addElement('text', 'trigger_days', get_string('triggerdays', 'local_manireports'));
        $mform->setType('trigger_days', PARAM_INT);

        $mform->setDefault('trigger_days', 7);
        $mform->addElement('static', 'trigger_days_help', '', '<div class="text-sm text-gray-500 dark:text-gray-400 mt-1">' . get_string('triggerdays_help', 'local_manireports') . '</div>');


        // Schedule Settings
        // Schedule Settings
        $mform->addElement('html', '<h4 class="text-xl font-bold text-gray-800 dark:text-white mb-4 mt-6 border-b border-gray-200 dark:border-gray-700 pb-2">Schedule Settings</h4>');

        // Email Delay
        $mform->addElement('duration', 'emaildelay', get_string('emaildelay', 'local_manireports'));

        $mform->setDefault('emaildelay', 86400); // 1 day
        $mform->addElement('static', 'emaildelay_help', '', '<div class="text-sm text-gray-500 dark:text-gray-400 mt-1">' . get_string('emaildelay_help', 'local_manireports') . '</div>');


        // Reminder Count
        $mform->addElement('text', 'remindercount', get_string('remindercount', 'local_manireports'));
        $mform->setType('remindercount', PARAM_INT);

        $mform->setDefault('remindercount', 1);
        $mform->addElement('static', 'remindercount_help', '', '<div class="text-sm text-gray-500 dark:text-gray-400 mt-1">' . get_string('remindercount_help', 'local_manireports') . '</div>');


        // Recipient Settings
        // Recipient Settings
        $mform->addElement('html', '<h4 class="text-xl font-bold text-gray-800 dark:text-white mb-4 mt-6 border-b border-gray-200 dark:border-gray-700 pb-2">Recipient Settings</h4>');

        // Send to User
        $mform->addElement('checkbox', 'send_to_user', get_string('sendtousers', 'local_manireports'));

        $mform->setDefault('send_to_user', 1);
        $mform->addElement('static', 'send_to_user_help', '', '<div class="text-sm text-gray-500 dark:text-gray-400 mt-1">' . get_string('sendtousers_help', 'local_manireports') . '</div>');


        // Send to Managers
        $mform->addElement('checkbox', 'send_to_managers', get_string('sendtomanagers', 'local_manireports'));

        $mform->setDefault('send_to_managers', 0);
        $mform->addElement('static', 'send_to_managers_help', '', '<div class="text-sm text-gray-500 dark:text-gray-400 mt-1">' . get_string('sendtomanagers_help', 'local_manireports') . '</div>');


        // Third Party Emails
        $mform->addElement('textarea', 'thirdparty_emails', get_string('thirdpartyemails', 'local_manireports'), 'rows="3" cols="50"');

        $mform->setType('thirdparty_emails', PARAM_TEXT);
        $mform->addElement('static', 'thirdparty_emails_help', '', '<div class="text-sm text-gray-500 dark:text-gray-400 mt-1">' . get_string('thirdpartyemails_help', 'local_manireports') . '</div>');


        // Content Settings
        // Content Settings
        $mform->addElement('html', '<h4 class="text-xl font-bold text-gray-800 dark:text-white mb-4 mt-6 border-b border-gray-200 dark:border-gray-700 pb-2">Content Settings</h4>');

        // Template Dropdown (User-Friendly)
        $templates = $DB->get_records_menu('manireports_rem_tmpl', ['enabled' => 1], 'name ASC', 'id, name');
        if (empty($templates)) {
            $templates = [0 => get_string('notemplates', 'local_manireports')];
        }
        $mform->addElement('select', 'templateid', get_string('templateid', 'local_manireports'), $templates);

        $mform->addRule('templateid', null, 'required', null, 'client');
        $mform->addElement('static', 'templateid_help', '', '<div class="text-sm text-gray-500 dark:text-gray-400 mt-1">' . get_string('templateid_help', 'local_manireports') . '</div>');


        // Enabled
        $mform->addElement('selectyesno', 'enabled', get_string('enabled', 'local_manireports'));
        $mform->setDefault('enabled', 1);


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
