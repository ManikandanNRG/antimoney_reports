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
 * Error handling and resilience utilities for ManiReports
 *
 * Provides error logging, retry logic, and failure management.
 *
 * @package    local_manireports
 * @copyright  2024 ManiReports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_manireports\api;

defined('MOODLE_INTERNAL') || die();

/**
 * Error handling and resilience utilities
 */
class error_handler {

    /** @var int Maximum retry attempts */
    const MAX_RETRIES = 3;

    /** @var int Base delay for exponential backoff (seconds) */
    const BASE_DELAY = 2;

    /**
     * Execute operation with retry logic
     *
     * @param callable $operation Operation to execute
     * @param int $maxretries Maximum retry attempts
     * @param string $context Context for logging
     * @return mixed Operation result
     * @throws \Exception If all retries fail
     */
    public static function execute_with_retry($operation, $maxretries = self::MAX_RETRIES, $context = '') {
        $attempt = 0;
        $lasterror = null;

        while ($attempt < $maxretries) {
            try {
                return $operation();
            } catch (\Exception $e) {
                $attempt++;
                $lasterror = $e;

                self::log_error($e, $context, [
                    'attempt' => $attempt,
                    'max_retries' => $maxretries,
                ]);

                if ($attempt < $maxretries) {
                    // Exponential backoff.
                    $delay = self::BASE_DELAY * pow(2, $attempt - 1);
                    sleep($delay);
                }
            }
        }

        // All retries failed.
        throw new \Exception(
            "Operation failed after $maxretries attempts: " . $lasterror->getMessage(),
            0,
            $lasterror
        );
    }

    /**
     * Log error with full context
     *
     * @param \Exception $exception Exception to log
     * @param string $context Context description
     * @param array $additionaldata Additional data to log
     * @return void
     */
    public static function log_error($exception, $context = '', $additionaldata = []) {
        global $CFG;

        $errordata = [
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'context' => $context,
            'additional' => $additionaldata,
            'time' => time(),
            'user' => self::get_current_user_info(),
        ];

        // Log to Moodle error log.
        $message = sprintf(
            "[ManiReports] %s: %s in %s:%d\nContext: %s\nTrace:\n%s",
            $context,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            json_encode($additionaldata),
            $exception->getTraceAsString()
        );

        debugging($message, DEBUG_DEVELOPER);

        // Log to audit system.
        try {
            $logger = new audit_logger();
            $logger->log_action('error', 'system', 0, $errordata);
        } catch (\Exception $e) {
            // Prevent infinite loop if audit logging fails.
            debugging('Failed to log error to audit system: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Get current user information for logging
     *
     * @return array User information
     */
    private static function get_current_user_info() {
        global $USER;

        if (isset($USER->id) && $USER->id > 0) {
            return [
                'id' => $USER->id,
                'username' => $USER->username,
                'email' => $USER->email,
            ];
        }

        return ['id' => 0, 'username' => 'guest', 'email' => ''];
    }

    /**
     * Handle failed scheduled task
     *
     * @param string $taskname Task class name
     * @param \Exception $exception Exception that occurred
     * @param array $context Task context
     * @return void
     */
    public static function handle_task_failure($taskname, $exception, $context = []) {
        global $DB;

        // Log the failure.
        self::log_error($exception, "Task failure: $taskname", $context);

        // Record in failed jobs table.
        $record = new \stdClass();
        $record->taskname = $taskname;
        $record->error = $exception->getMessage();
        $record->stacktrace = $exception->getTraceAsString();
        $record->context = json_encode($context);
        $record->timefailed = time();
        $record->retrycount = 0;

        try {
            $DB->insert_record('manireports_failed_jobs', $record);
        } catch (\Exception $e) {
            debugging('Failed to record failed job: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        // Check if we should send alert.
        self::check_and_send_alert($taskname);
    }

    /**
     * Check if alert should be sent for repeated failures
     *
     * @param string $taskname Task name
     * @return void
     */
    private static function check_and_send_alert($taskname) {
        global $DB;

        // Count recent failures (last 24 hours).
        $since = time() - (24 * 3600);
        $count = $DB->count_records_select(
            'manireports_failed_jobs',
            'taskname = ? AND timefailed > ?',
            [$taskname, $since]
        );

        // Send alert if 3 or more failures.
        if ($count >= 3) {
            self::send_failure_alert($taskname, $count);
        }
    }

    /**
     * Send failure alert to administrators
     *
     * @param string $taskname Task name
     * @param int $failurecount Number of failures
     * @return void
     */
    private static function send_failure_alert($taskname, $failurecount) {
        global $CFG;

        $admins = get_admins();

        if (empty($admins)) {
            return;
        }

        $subject = get_string('taskfailurealert', 'local_manireports', $taskname);
        $message = get_string('taskfailurealertbody', 'local_manireports', [
            'taskname' => $taskname,
            'count' => $failurecount,
            'url' => $CFG->wwwroot . '/local/manireports/ui/failed_jobs.php',
        ]);

        foreach ($admins as $admin) {
            email_to_user($admin, \core_user::get_noreply_user(), $subject, $message);
        }
    }

    /**
     * Retry failed job
     *
     * @param int $jobid Job ID
     * @return bool True if retry succeeded
     */
    public static function retry_failed_job($jobid) {
        global $DB;

        $job = $DB->get_record('manireports_failed_jobs', ['id' => $jobid], '*', MUST_EXIST);

        // Increment retry count.
        $job->retrycount++;
        $job->lastretry = time();
        $DB->update_record('manireports_failed_jobs', $job);

        try {
            // Attempt to re-execute the task.
            $task = \core\task\manager::get_scheduled_task($job->taskname);
            
            if ($task) {
                $task->execute();
                
                // Success - delete the failed job record.
                $DB->delete_records('manireports_failed_jobs', ['id' => $jobid]);
                
                return true;
            }
        } catch (\Exception $e) {
            // Retry failed - log it.
            self::log_error($e, "Failed job retry: {$job->taskname}", [
                'jobid' => $jobid,
                'retry_count' => $job->retrycount,
            ]);
            
            // Update error message.
            $job->error = $e->getMessage();
            $job->stacktrace = $e->getTraceAsString();
            $DB->update_record('manireports_failed_jobs', $job);
        }

        return false;
    }

    /**
     * Get failed jobs
     *
     * @param int $limit Maximum number of jobs to return
     * @return array Failed jobs
     */
    public static function get_failed_jobs($limit = 100) {
        global $DB;

        return $DB->get_records('manireports_failed_jobs', null, 'timefailed DESC', '*', 0, $limit);
    }

    /**
     * Clear old failed jobs
     *
     * @param int $days Days to keep
     * @return int Number of jobs deleted
     */
    public static function clear_old_failed_jobs($days = 30) {
        global $DB;

        $cutoff = time() - ($days * 24 * 3600);
        
        return $DB->delete_records_select(
            'manireports_failed_jobs',
            'timefailed < ?',
            [$cutoff]
        );
    }

    /**
     * Execute operation with timeout
     *
     * @param callable $operation Operation to execute
     * @param int $timeout Timeout in seconds
     * @param string $context Context for logging
     * @return mixed Operation result
     * @throws \Exception If timeout exceeded
     */
    public static function execute_with_timeout($operation, $timeout, $context = '') {
        $starttime = time();
        
        // Set time limit.
        $oldlimit = ini_get('max_execution_time');
        set_time_limit($timeout + 30);

        try {
            $result = $operation();
            
            // Check if we exceeded timeout.
            $duration = time() - $starttime;
            if ($duration > $timeout) {
                throw new \Exception("Operation exceeded timeout of {$timeout}s (took {$duration}s)");
            }
            
            return $result;
        } catch (\Exception $e) {
            self::log_error($e, "Timeout: $context", [
                'timeout' => $timeout,
                'duration' => time() - $starttime,
            ]);
            throw $e;
        } finally {
            // Restore time limit.
            set_time_limit($oldlimit);
        }
    }

    /**
     * Wrap operation in try-catch with logging
     *
     * @param callable $operation Operation to execute
     * @param string $context Context for logging
     * @param mixed $defaultreturn Default return value on error
     * @return mixed Operation result or default
     */
    public static function safe_execute($operation, $context = '', $defaultreturn = null) {
        try {
            return $operation();
        } catch (\Exception $e) {
            self::log_error($e, $context);
            return $defaultreturn;
        }
    }

    /**
     * Check system health
     *
     * @return array Health check results
     */
    public static function check_system_health() {
        global $DB;

        $health = [
            'status' => 'healthy',
            'checks' => [],
            'warnings' => [],
            'errors' => [],
        ];

        // Check database connection.
        try {
            $DB->get_record('user', ['id' => 2]);
            $health['checks'][] = 'Database connection: OK';
        } catch (\Exception $e) {
            $health['errors'][] = 'Database connection failed';
            $health['status'] = 'critical';
        }

        // Check failed jobs.
        try {
            $failedjobs = $DB->count_records('manireports_failed_jobs');
            if ($failedjobs > 10) {
                $health['warnings'][] = "High number of failed jobs: $failedjobs";
                $health['status'] = 'warning';
            } else {
                $health['checks'][] = "Failed jobs: $failedjobs";
            }
        } catch (\Exception $e) {
            $health['warnings'][] = 'Could not check failed jobs';
        }

        // Check disk space.
        $free = disk_free_space('/');
        $total = disk_total_space('/');
        $percent = ($free / $total) * 100;
        
        if ($percent < 10) {
            $health['errors'][] = 'Low disk space: ' . round($percent, 1) . '%';
            $health['status'] = 'critical';
        } else if ($percent < 20) {
            $health['warnings'][] = 'Disk space low: ' . round($percent, 1) . '%';
            if ($health['status'] === 'healthy') {
                $health['status'] = 'warning';
            }
        } else {
            $health['checks'][] = 'Disk space: ' . round($percent, 1) . '% free';
        }

        return $health;
    }
}
