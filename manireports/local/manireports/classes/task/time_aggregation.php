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
 * Time aggregation scheduled task.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_manireports\task;

use local_manireports\api\time_engine;

defined('MOODLE_INTERNAL') || die();

/**
 * Time aggregation task class.
 */
class time_aggregation extends \core\task\scheduled_task {

    /**
     * Get task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_timeaggregation', 'local_manireports');
    }

    /**
     * Execute task.
     */
    public function execute() {
        $engine = new time_engine();

        mtrace('Starting time aggregation task...');

        // Close inactive sessions (older than 10 minutes).
        $sessiontimeout = get_config('local_manireports', 'sessiontimeout') ?: 10;
        $timeoutseconds = $sessiontimeout * 60;
        
        $closed = $engine->close_inactive_sessions($timeoutseconds);
        mtrace("Closed {$closed} inactive sessions");

        // Aggregate yesterday's data.
        $yesterday = date('Y-m-d', strtotime('yesterday'));
        $aggregated = $engine->aggregate_daily_time($yesterday);
        mtrace("Aggregated {$aggregated} sessions for {$yesterday}");

        mtrace('Time aggregation task completed');
    }
}
