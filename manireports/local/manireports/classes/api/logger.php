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
 * Simple logger for ManiReports debugging.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_manireports\api;

defined('MOODLE_INTERNAL') || die();

/**
 * Logger class for debugging.
 */
class logger {

    /**
     * Log a message to the plugin log file.
     *
     * @param string $message Message to log
     * @param string $level Log level (INFO, ERROR, DEBUG)
     */
    public static function log($message, $level = 'INFO') {
        global $CFG;

        $logfile = $CFG->dataroot . '/manireports_debug.log';
        $timestamp = date('Y-m-d H:i:s');
        $logmessage = "[{$timestamp}] [{$level}] {$message}\n";

        // Also log to Moodle's error_log for convenience.
        error_log("ManiReports [{$level}]: {$message}");

        // Write to custom log file.
        file_put_contents($logfile, $logmessage, FILE_APPEND);
    }

    /**
     * Log an error.
     *
     * @param string $message Error message
     */
    public static function error($message) {
        self::log($message, 'ERROR');
    }

    /**
     * Log debug information.
     *
     * @param string $message Debug message
     */
    public static function debug($message) {
        self::log($message, 'DEBUG');
    }

    /**
     * Log info message.
     *
     * @param string $message Info message
     */
    public static function info($message) {
        self::log($message, 'INFO');
    }
}
