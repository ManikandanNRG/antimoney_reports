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
 * Performance Optimizer for ManiReports
 *
 * Provides database query optimization, index management, and performance monitoring.
 *
 * @package    local_manireports
 * @copyright  2024 ManiReports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_manireports\api;

defined('MOODLE_INTERNAL') || die();

/**
 * Performance optimization utilities
 *
 * Handles query optimization, index management, and performance monitoring.
 */
class performance_optimizer {

    /** @var int Maximum concurrent report executions */
    const MAX_CONCURRENT_REPORTS = 5;

    /** @var int Query timeout in seconds */
    const QUERY_TIMEOUT = 30;

    /** @var int Default page size for pagination */
    const DEFAULT_PAGE_SIZE = 100;

    /**
     * Ensure required database indexes exist
     *
     * @return array Results of index creation
     */
    public function ensure_indexes() {
        global $DB;

        $results = [
            'checked' => 0,
            'created' => 0,
            'errors' => [],
        ];

        $indexes = $this->get_required_indexes();

        foreach ($indexes as $table => $tableindexes) {
            foreach ($tableindexes as $indexdef) {
                $results['checked']++;
                
                try {
                    if ($this->create_index_if_missing($table, $indexdef)) {
                        $results['created']++;
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = "Error on {$table}.{$indexdef['name']}: " . $e->getMessage();
                }
            }
        }

        return $results;
    }

    /**
     * Get required database indexes
     *
     * @return array Array of table => indexes
     */
    private function get_required_indexes() {
        return [
            'manireports_usertime_sessions' => [
                [
                    'name' => 'userid_courseid_idx',
                    'columns' => ['userid', 'courseid'],
                ],
                [
                    'name' => 'lastupdated_idx',
                    'columns' => ['lastupdated'],
                ],
            ],
            'manireports_usertime_daily' => [
                [
                    'name' => 'userid_courseid_date_idx',
                    'columns' => ['userid', 'courseid', 'date'],
                ],
                [
                    'name' => 'date_idx',
                    'columns' => ['date'],
                ],
            ],
            'manireports_audit_logs' => [
                [
                    'name' => 'userid_timecreated_idx',
                    'columns' => ['userid', 'timecreated'],
                ],
                [
                    'name' => 'action_idx',
                    'columns' => ['action'],
                ],
            ],
            'manireports_report_runs' => [
                [
                    'name' => 'reportid_timestarted_idx',
                    'columns' => ['reportid', 'timestarted'],
                ],
                [
                    'name' => 'userid_timestarted_idx',
                    'columns' => ['userid', 'timestarted'],
                ],
            ],
        ];
    }

    /**
     * Create index if it doesn't exist
     *
     * @param string $table Table name
     * @param array $indexdef Index definition
     * @return bool True if index was created
     */
    private function create_index_if_missing($table, $indexdef) {
        global $DB;

        $dbman = $DB->get_manager();
        $xmldbtable = new \xmldb_table($table);

        if (!$dbman->table_exists($xmldbtable)) {
            return false;
        }

        $index = new \xmldb_index(
            $indexdef['name'],
            XMLDB_INDEX_NOTUNIQUE,
            $indexdef['columns']
        );

        if (!$dbman->index_exists($xmldbtable, $index)) {
            $dbman->add_index($xmldbtable, $index);
            return true;
        }

        return false;
    }

    /**
     * Get current concurrent report count
     *
     * @return int Number of currently running reports
     */
    public function get_concurrent_report_count() {
        global $DB;

        return $DB->count_records('manireports_report_runs', [
            'status' => 'running',
        ]);
    }

    /**
     * Check if new report execution is allowed
     *
     * @return bool True if execution is allowed
     */
    public function can_execute_report() {
        $current = $this->get_concurrent_report_count();
        $max = get_config('local_manireports', 'max_concurrent_reports') ?: self::MAX_CONCURRENT_REPORTS;
        
        return $current < $max;
    }

    /**
     * Apply pagination to query results
     *
     * @param array $data Full result set
     * @param int $page Page number (0-indexed)
     * @param int $pagesize Items per page
     * @return array Paginated results with metadata
     */
    public function paginate_results($data, $page = 0, $pagesize = null) {
        if ($pagesize === null) {
            $pagesize = get_config('local_manireports', 'default_page_size') ?: self::DEFAULT_PAGE_SIZE;
        }

        $total = count($data);
        $totalpages = ceil($total / $pagesize);
        $page = max(0, min($page, $totalpages - 1));
        
        $offset = $page * $pagesize;
        $paginated = array_slice($data, $offset, $pagesize);

        return [
            'data' => $paginated,
            'page' => $page,
            'pagesize' => $pagesize,
            'total' => $total,
            'totalpages' => $totalpages,
            'hasmore' => ($page < $totalpages - 1),
        ];
    }

    /**
     * Get performance statistics
     *
     * @return array Performance metrics
     */
    public function get_performance_stats() {
        global $DB;

        $stats = [];

        // Table sizes.
        $tables = [
            'manireports_usertime_sessions',
            'manireports_usertime_daily',
            'manireports_audit_logs',
            'manireports_report_runs',
            'manireports_cache_summary',
        ];

        foreach ($tables as $table) {
            try {
                $stats['tables'][$table] = $DB->count_records($table);
            } catch (\Exception $e) {
                $stats['tables'][$table] = 0;
            }
        }

        // Concurrent reports.
        $stats['concurrent_reports'] = $this->get_concurrent_report_count();
        $stats['max_concurrent_reports'] = get_config('local_manireports', 'max_concurrent_reports') ?: self::MAX_CONCURRENT_REPORTS;

        // Cache stats.
        try {
            $cache_total = $DB->count_records('manireports_cache_summary');
            $cache_valid = $DB->count_records_sql(
                "SELECT COUNT(*) FROM {manireports_cache_summary}
                  WHERE (? - lastgenerated) <= ttl",
                [time()]
            );
            
            $stats['cache'] = [
                'total' => $cache_total,
                'valid' => $cache_valid,
                'hit_rate' => $cache_total > 0 ? round(($cache_valid / $cache_total) * 100, 2) : 0,
            ];
        } catch (\Exception $e) {
            $stats['cache'] = ['error' => $e->getMessage()];
        }

        return $stats;
    }

    /**
     * Optimize scheduled task timing
     *
     * @return array Recommendations for task scheduling
     */
    public function get_task_recommendations() {
        $recommendations = [];

        // Recommend off-peak hours for heavy tasks.
        $heavy_tasks = [
            '\\local_manireports\\task\\cache_builder',
            '\\local_manireports\\task\\scorm_summary',
            '\\local_manireports\\task\\time_aggregation',
        ];

        foreach ($heavy_tasks as $task) {
            $recommendations[] = [
                'task' => $task,
                'recommendation' => 'Schedule during off-peak hours (e.g., 2:00 AM - 4:00 AM)',
                'reason' => 'Heavy database operations',
            ];
        }

        return $recommendations;
    }
}
