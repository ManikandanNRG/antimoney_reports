<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Scheduled task for executing scheduled reports.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_manireports\task;

use local_manireports\api\scheduler;
use local_manireports\api\export_engine;

defined('MOODLE_INTERNAL') || die();

/**
 * Report scheduler task class.
 */
class report_scheduler extends \core\task\scheduled_task {

    /**
     * Get task name.
     *
     * @return string Task name
     */
    public function get_name() {
        return get_string('task_reportscheduler', 'local_manireports');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB;

        mtrace('Starting report scheduler task...');

        $scheduler = new scheduler();
        $export_engine = new export_engine();

        // Get due schedules.
        $schedules = $scheduler->get_due_schedules();

        if (empty($schedules)) {
            mtrace('No schedules due to run.');
            return;
        }

        mtrace('Found ' . count($schedules) . ' schedule(s) to execute.');

        foreach ($schedules as $schedule) {
            mtrace('Executing schedule: ' . $schedule->name . ' (ID: ' . $schedule->id . ')');

            try {
                // Create report run record.
                $run = new \stdClass();
                $run->scheduleid = $schedule->id;
                $run->status = 'running';
                $run->starttime = time();
                $run->userid = $schedule->userid;
                $runid = $DB->insert_record('manireports_report_runs', $run);

                // Execute report.
                $result = $this->execute_report($schedule);

                // Generate export file.
                $file = $this->generate_export($schedule, $result);

                // Send emails to recipients.
                $this->send_emails($schedule, $file);

                // Update run record as successful.
                $run->id = $runid;
                $run->status = 'completed';
                $run->endtime = time();
                $run->duration = $run->endtime - $run->starttime;
                $run->recordcount = count($result['data']);
                $DB->update_record('manireports_report_runs', $run);

                // Update schedule.
                $scheduler->update_after_execution($schedule->id, true);

                mtrace('Schedule executed successfully.');

            } catch (\Exception $e) {
                mtrace('Error executing schedule: ' . $e->getMessage());

                // Update run record as failed.
                if (isset($runid)) {
                    $run->id = $runid;
                    $run->status = 'failed';
                    $run->endtime = time();
                    $run->duration = $run->endtime - $run->starttime;
                    $run->error = $e->getMessage();
                    $DB->update_record('manireports_report_runs', $run);
                }

                // Update schedule.
                $scheduler->update_after_execution($schedule->id, false);
            }
        }

        mtrace('Report scheduler task completed.');
    }

    /**
     * Execute a report for a schedule.
     *
     * @param object $schedule Schedule object
     * @return array Report result
     */
    private function execute_report($schedule) {
        // Parse parameters.
        $params = json_decode($schedule->parameters, true);
        if (!is_array($params)) {
            $params = array();
        }

        // Check if this is a custom report or prebuilt report.
        if ($schedule->reporttype === 'custom' && !empty($schedule->reportid)) {
            // Custom report (SQL or GUI).
            $reportbuilder = new \local_manireports\api\report_builder();
            $result = $reportbuilder->execute_report(
                $schedule->reportid,
                $params,
                $schedule->userid,
                0,
                999999,
                false // Don't use cache for scheduled reports
            );
            
            return $result;
        } else {
            // Prebuilt report class.
            $reportclass = "\\local_manireports\\reports\\{$schedule->reporttype}";

            if (!class_exists($reportclass)) {
                throw new \moodle_exception('error:reportnotfound', 'local_manireports');
            }

            $report = new $reportclass($schedule->userid, $params);

            // Execute report (get all data).
            $result = $report->execute(0, 999999);

            // Format all rows.
            $formatted_data = array();
            foreach ($result['data'] as $row) {
                $formatted_data[] = $report->format_row($row);
            }

            $result['data'] = $formatted_data;

            return $result;
        }
    }

    /**
     * Generate export file for a schedule.
     *
     * @param object $schedule Schedule object
     * @param array $result Report result
     * @return stored_file Export file
     */
    private function generate_export($schedule, $result) {
        $export_engine = new export_engine();

        $filename = $schedule->reporttype . '_' . date('Y-m-d');

        $file = $export_engine->export(
            $result['data'],
            $result['columns'],
            $schedule->format,
            $filename
        );

        return $file;
    }

    /**
     * Send emails to schedule recipients.
     *
     * @param object $schedule Schedule object
     * @param stored_file $file Export file
     */
    private function send_emails($schedule, $file) {
        global $CFG;

        $scheduler = new scheduler();
        $recipients = $scheduler->get_recipients($schedule->id);

        if (empty($recipients)) {
            mtrace('No recipients configured for this schedule.');
            return;
        }

        // Prepare email.
        $subject = $schedule->name . ' - ' . userdate(time(), get_string('strftimedatetime', 'langconfig'));
        $message = "Your scheduled report '{$schedule->name}' is attached.\n\n";
        $message .= "Report: {$schedule->reporttype}\n";
        $message .= "Format: " . strtoupper($schedule->format) . "\n";
        $message .= "Generated: " . userdate(time(), get_string('strftimedatetime', 'langconfig')) . "\n";

        // Get file path for attachment.
        $fs = get_file_storage();
        $filepath = $file->copy_content_to_temp();

        foreach ($recipients as $recipient) {
            mtrace('Sending email to: ' . $recipient->email);

            // Create a fake user object for email_to_user.
            $user = new \stdClass();
            $user->email = $recipient->email;
            $user->firstname = '';
            $user->lastname = '';
            $user->maildisplay = true;
            $user->mailformat = 1;
            $user->id = -1;
            $user->firstnamephonetic = '';
            $user->lastnamephonetic = '';
            $user->middlename = '';
            $user->alternatename = '';

            // Send email with attachment.
            $from = \core_user::get_noreply_user();

            email_to_user($user, $from, $subject, $message, '', $filepath, $file->get_filename());
        }

        // Clean up temp file.
        @unlink($filepath);

        mtrace('Emails sent to ' . count($recipients) . ' recipient(s).');
    }
}
