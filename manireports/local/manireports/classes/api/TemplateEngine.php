<?php
namespace local_manireports\api;

defined('MOODLE_INTERNAL') || die();

/**
 * Template Engine for Reminder Feature.
 * Handles placeholder replacement and template rendering.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class TemplateEngine {

    /**
     * Render a template with the given context.
     *
     * @param int $templateid Template ID
     * @param object $user User object
     * @param object $course Course object
     * @param object|null $activity Activity object (optional)
     * @return array ['subject' => string, 'body' => string, 'body_text' => string]
     */
    public function render($templateid, $user, $course, $activity = null) {
        global $DB;

        $template = $DB->get_record('manireports_rem_tmpl', ['id' => $templateid, 'enabled' => 1]);
        if (!$template) {
            throw new \moodle_exception('Template not found or disabled');
        }

        $context = $this->build_context($user, $course, $activity);
        
        $subject = $this->replace_placeholders($template->subject, $context);
        $body_html = $this->replace_placeholders($template->body_html, $context);
        $body_text = $this->replace_placeholders($template->body_text, $context);

        return [
            'subject' => $subject,
            'body_html' => $body_html,
            'body_text' => $body_text
        ];
    }

    /**
     * Build context array for placeholder replacement.
     */
    private function build_context($user, $course, $activity = null) {
        $context = [
            '{firstname}' => $user->firstname,
            '{lastname}' => $user->lastname,
            '{email}' => $user->email,
            '{coursename}' => $course->fullname,
            '{courselink}' => new \moodle_url('/course/view.php', ['id' => $course->id]),
            '{siteurl}' => new \moodle_url('/'),
        ];

        if ($activity) {
            $context['{activityname}'] = $activity->name;
            $context['{activitylink}'] = new \moodle_url('/mod/' . $activity->modname . '/view.php', ['id' => $activity->id]);
        } else {
            $context['{activityname}'] = '';
            $context['{activitylink}'] = '';
        }

        // Add profile fields
        // Note: This is a basic implementation. For full profile field support, 
        // we would need to query mdl_user_info_data.
        
        return $context;
    }

    /**
     * Replace placeholders in text.
     */
    private function replace_placeholders($text, $context) {
        return str_replace(array_keys($context), array_values($context), $text);
    }

    /**
     * Convert HTML to plain text.
     */
    public function html_to_text($html) {
        return html_to_text($html, 0);
    }

    /**
     * Get list of supported placeholders.
     */
    public function get_supported_placeholders() {
        return [
            '{firstname}', '{lastname}', '{email}',
            '{coursename}', '{courselink}',
            '{activityname}', '{activitylink}',
            '{siteurl}'
        ];
    }
}
