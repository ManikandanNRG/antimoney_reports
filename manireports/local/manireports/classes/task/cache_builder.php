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
 * Cache builder scheduled task.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_manireports\task;

use local_manireports\api\cache_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Cache builder task class.
 */
class cache_builder extends \core\task\scheduled_task {

    /**
     * Get task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_cachebuilder', 'local_manireports');
    }

    /**
     * Execute task.
     */
    public function execute() {
        $manager = new cache_manager();

        mtrace('Starting cache builder task...');

        // Run pre-aggregations.
        $count = $manager->run_aggregations();
        mtrace("Completed {$count} pre-aggregations");

        // Clean up expired cache.
        $cleaned = $manager->cleanup_expired_cache();
        mtrace("Cleaned up {$cleaned} expired cache entries");

        mtrace('Cache builder task completed');
    }
}
