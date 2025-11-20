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
 * Scheduled tasks for ManiReports plugin.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = array(

    // Time tracking aggregation task - runs hourly to aggregate session data into daily summaries.
    array(
        'classname' => 'local_manireports\task\time_aggregation',
        'blocking' => 0,
        'minute' => '5',
        'hour' => '*',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*'
    ),

    // Cache builder task - runs every 6 hours to pre-aggregate heavy metrics.
    array(
        'classname' => 'local_manireports\task\cache_builder',
        'blocking' => 0,
        'minute' => '15',
        'hour' => '*/6',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*'
    ),

    // Report scheduler task - runs every 15 minutes to execute scheduled reports.
    array(
        'classname' => 'local_manireports\task\report_scheduler',
        'blocking' => 0,
        'minute' => '*/15',
        'hour' => '*',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*'
    ),

    // SCORM summary task - runs hourly to aggregate SCORM tracking data.
    array(
        'classname' => 'local_manireports\task\scorm_summary',
        'blocking' => 0,
        'minute' => '10',
        'hour' => '*',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*'
    ),

    // Cleanup old data task - runs daily at 2:00 AM to remove expired data.
    array(
        'classname' => 'local_manireports\task\cleanup_old_data',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '2',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*'
    ),

);
