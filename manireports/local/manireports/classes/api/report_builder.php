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
 * Core report builder API for generating reports from SQL queries.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_manireports\api;

defined('MOODLE_INTERNAL') || die();

/**
 * Report builder class for executing and managing reports.
 */
class report_builder {

    /**
     * Whitelist of allowed Moodle tables for custom SQL reports.
     *
     * @var array
     */
    private static $allowed_tables = array(
        'user', 'course', 'course_categories', 'course_completions',
        'course_modules', 'course_modules_completion', 'enrol', 'user_enrolments',
        'role', 'role_assignments', 'context', 'grade_grades', 'grade_items',
        'quiz', 'quiz_attempts', 'quiz_grades', 'scorm', 'scorm_scoes_track',
        'assign', 'assign_submission', 'assign_grades', 'forum', 'forum_posts',
        'forum_discussions', 'logstore_standard_log', 'company', 'company_users',
        'company_course', 'manireports_time_daily', 'manireports_scorm_summary',
        'manireports_cache_summary'
    );

    /**
     * Blocked SQL keywords that are not allowed in custom reports.
     *
     * @var array
     */
    private static $blocked_keywords = array(
        'DROP', 'CREATE', 'ALTER', 'TRUNCATE', 'INSERT', 'UPDATE', 'DELETE',
        'GRANT', 'REVOKE', 'EXEC', 'EXECUTE', 'CALL', 'DECLARE', 'SET'
    );

    /**
     * Execute a report by ID with parameters.
     *
     * @param int $reportid Report ID from manireports_customreports table
     * @param array $params Parameters to bind to the query
     * @param int $userid User ID executing the report (for IOMAD filtering)
     * @param int $page Page number for pagination (0-based)
     * @param int $perpage Records per page
     * @param bool $usecache Whether to use cache (default true)
     * @return array Array containing 'data' (result rows), 'columns' (column names), 'total' (total count), 'cached'
     * @throws \moodle_exception
     */
    public function execute_report($reportid, $params = array(), $userid = 0, $page = 0, $perpage = 25, $usecache = true) {
        global $DB, $USER;

        if ($userid === 0) {
            $userid = $USER->id;
        }

        // Check concurrent execution limit.
        $optimizer = new performance_optimizer();
        if (!$optimizer->can_execute_report()) {
            throw new \moodle_exception('toomanyreports', 'local_manireports');
        }

        $starttime = microtime(true);
        $cached = false;

        // Get report definition.
        $report = $DB->get_record('manireports_customreports', array('id' => $reportid), '*', MUST_EXIST);

        // Check cache first if enabled.
        if ($usecache) {
            $cachemanager = new cache_manager();
            $cacheparams = array_merge($params, array(
                'reportid' => $reportid,
                'page' => $page,
                'perpage' => $perpage
            ));
            $cachekey = $cachemanager->generate_cache_key('custom_report_' . $reportid, $cacheparams);
            
            $cacheddata = $cachemanager->get_cached_data($cachekey);
            
            if ($cacheddata !== null) {
                // Cache hit.
                $executiontime = microtime(true) - $starttime;
                return array(
                    'data' => $cacheddata->data,
                    'columns' => $cacheddata->columns,
                    'total' => $cacheddata->total,
                    'page' => $page,
                    'perpage' => $perpage,
                    'cached' => true,
                    'executiontime' => round($executiontime, 3)
                );
            }
        }

        // Cache miss - execute query.
        // Generate SQL based on report type.
        if ($report->type === 'gui' && !empty($report->configjson)) {
            // GUI report - generate SQL from configuration.
            $config = json_decode($report->configjson);
            if (!$config) {
                throw new \moodle_exception('invalidconfig', 'local_manireports');
            }
            
            $querybuilder = new query_builder();
            $sqldata = $querybuilder->build_sql_from_config($config);
            $sql = $sqldata['sql'];
            
            // Merge GUI-generated params with runtime params.
            $params = array_merge($sqldata['params'], $params);
        } else {
            // SQL report - use stored SQL.
            $sql = $report->sqlquery;
            
            // Validate SQL.
            if (!$this->validate_sql($sql)) {
                throw new \moodle_exception('invalidsql', 'local_manireports');
            }
            
            // Validate parameters match query.
            if (!$this->validate_parameter_match($sql, $params)) {
                throw new \moodle_exception('invalidparameters', 'local_manireports');
            }
        }

        // Apply IOMAD company filter if applicable (only if query has user table with alias 'u').
        if (iomad_filter::is_iomad_installed() && preg_match('/\bFROM\s+\{user\}\s+u\b/i', $sql)) {
            $companyid = isset($params['companyid']) ? $params['companyid'] : null;
            $sql = iomad_filter::apply_company_filter($sql, $userid, 'u', $companyid);
        }

        // Apply dynamic filters.
        $sql = $this->apply_filters($sql, $params);

        // Remove any existing LIMIT clause from user's SQL (Moodle will add its own for pagination).
        // Use case-insensitive match and trim whitespace.
        $sql = trim($sql);
        if (preg_match('/\bLIMIT\b/i', $sql)) {
            $sql = preg_replace('/\bLIMIT\s+\d+(\s*,\s*\d+)?(\s+OFFSET\s+\d+)?\s*$/i', '', $sql);
        }

        // Get query timeout setting.
        $timeout = get_config('local_manireports', 'querytimeout') ?: 60;
        
        // Set maximum execution time for this query.
        $oldmaxexectime = ini_get('max_execution_time');
        if ($oldmaxexectime < $timeout + 30) {
            @set_time_limit($timeout + 30);
        }

        try {
            // Get total count for pagination.
            $countsql = $this->get_count_sql($sql);
            $total = $DB->count_records_sql($countsql, $params);

            // Check if count query took too long.
            $counttime = microtime(true) - $starttime;
            if ($counttime > $timeout) {
                throw new \moodle_exception('querytimeout', 'local_manireports', '', $timeout);
            }

            // Execute query with pagination.
            $DB->set_debug(false); // Disable debug for performance.
            $results = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);

            // Check total execution time.
            $totaltime = microtime(true) - $starttime;
            if ($totaltime > $timeout) {
                throw new \moodle_exception('querytimeout', 'local_manireports', '', $timeout);
            }

        } catch (\dml_exception $e) {
            debugging('Report execution error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            throw new \moodle_exception('databaseerror', 'local_manireports', '', $e->getMessage());
        } finally {
            // Restore original max execution time.
            @set_time_limit($oldmaxexectime);
        }

        // Extract column names from first result.
        $columns = array();
        if (!empty($results)) {
            $firstrow = reset($results);
            $columns = array_keys((array)$firstrow);
        }

        $executiontime = microtime(true) - $starttime;

        $result = array(
            'data' => array_values($results),
            'columns' => $columns,
            'total' => $total,
            'page' => $page,
            'perpage' => $perpage,
            'cached' => false,
            'executiontime' => round($executiontime, 3)
        );

        // Cache the result if caching is enabled.
        if ($usecache) {
            $cachemanager = new cache_manager();
            $cacheparams = array_merge($params, array(
                'reportid' => $reportid,
                'page' => $page,
                'perpage' => $perpage
            ));
            $cachekey = $cachemanager->generate_cache_key('custom_report_' . $reportid, $cacheparams);
            
            // Get TTL from config.
            $ttl = get_config('local_manireports', 'cachettl_dashboard') ?: 3600;
            
            // Store data, columns, and total.
            $cachedata = (object)array(
                'data' => $result['data'],
                'columns' => $result['columns'],
                'total' => $result['total']
            );
            
            $cachemanager->set_cached_data($cachekey, $cachedata, 'custom_report', $reportid, $ttl);
        }

        return $result;
    }

    /**
     * Validate SQL query for security.
     *
     * @param string $sql SQL query to validate
     * @return bool True if valid, false otherwise
     */
    public function validate_sql($sql) {
        if (empty($sql)) {
            return false;
        }

        // Remove comments to prevent comment-based injection.
        $sql = $this->remove_sql_comments($sql);

        // Convert to uppercase for checking.
        $sqlup = strtoupper($sql);

        // Check for blocked keywords (strict word boundary matching).
        foreach (self::$blocked_keywords as $keyword) {
            if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/i', $sql)) {
                debugging('Blocked keyword found: ' . $keyword, DEBUG_DEVELOPER);
                return false;
            }
        }

        // Must start with SELECT (after trimming whitespace).
        if (!preg_match('/^\s*SELECT\b/i', $sql)) {
            debugging('Query must start with SELECT', DEBUG_DEVELOPER);
            return false;
        }

        // Block multiple statements (semicolon not at end).
        if (preg_match('/;\s*\w+/i', $sql)) {
            debugging('Multiple statements not allowed', DEBUG_DEVELOPER);
            return false;
        }

        // Block UNION with different table access (potential data leakage).
        if (preg_match('/UNION/i', $sql)) {
            // UNION is allowed but we need to validate all parts.
            if (!$this->validate_union_query($sql)) {
                debugging('Invalid UNION query structure', DEBUG_DEVELOPER);
                return false;
            }
        }

        // Validate table names against whitelist.
        if (!$this->validate_tables($sql)) {
            debugging('Query contains non-whitelisted tables', DEBUG_DEVELOPER);
            return false;
        }

        // Check for balanced parameter placeholders.
        if (!$this->validate_parameters($sql)) {
            debugging('Invalid parameter placeholders', DEBUG_DEVELOPER);
            return false;
        }

        // Block dangerous functions.
        if (!$this->validate_functions($sql)) {
            debugging('Query contains dangerous functions', DEBUG_DEVELOPER);
            return false;
        }

        // Validate query timeout setting.
        $timeout = get_config('local_manireports', 'querytimeout') ?: 60;
        if ($timeout < 1 || $timeout > 300) {
            set_config('querytimeout', 60, 'local_manireports');
        }

        return true;
    }

    /**
     * Remove SQL comments from query.
     *
     * @param string $sql SQL query
     * @return string SQL without comments
     */
    private function remove_sql_comments($sql) {
        // Remove single-line comments (--).
        $sql = preg_replace('/--[^\n]*\n/', "\n", $sql);
        
        // Remove multi-line comments (/* */).
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        
        return $sql;
    }

    /**
     * Validate UNION queries.
     *
     * @param string $sql SQL query with UNION
     * @return bool True if valid
     */
    private function validate_union_query($sql) {
        // Split by UNION and validate each part.
        $parts = preg_split('/\bUNION\s+(ALL\s+)?/i', $sql);
        
        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }
            
            // Each part must be a valid SELECT.
            if (!preg_match('/^\s*SELECT\b/i', $part)) {
                return false;
            }
            
            // Validate tables in this part.
            if (!$this->validate_tables($part)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Validate that query doesn't use dangerous functions.
     *
     * @param string $sql SQL query
     * @return bool True if no dangerous functions found
     */
    private function validate_functions($sql) {
        $dangerous_functions = array(
            'LOAD_FILE', 'INTO OUTFILE', 'INTO DUMPFILE', 'BENCHMARK',
            'SLEEP', 'GET_LOCK', 'RELEASE_LOCK', 'LOAD DATA',
            'SYSTEM', 'SHELL', 'EXEC'
        );
        
        foreach ($dangerous_functions as $func) {
            if (preg_match('/\b' . preg_quote($func, '/') . '\b/i', $sql)) {
                debugging('Dangerous function found: ' . $func, DEBUG_DEVELOPER);
                return false;
            }
        }
        
        return true;
    }

    /**
     * Validate that all tables in query are whitelisted.
     *
     * @param string $sql SQL query
     * @return bool True if all tables are whitelisted
     */
    private function validate_tables($sql) {
        global $CFG;

        // Extract table names from FROM and JOIN clauses.
        // Match both {tablename} and mdl_tablename formats.
        $pattern = '/(?:FROM|JOIN)\s+(?:\{([a-z0-9_]+)\}|' . preg_quote($CFG->prefix, '/') . '([a-z0-9_]+))/i';
        preg_match_all($pattern, $sql, $matches);

        // Combine both match groups.
        $tables = array_merge(
            array_filter($matches[1]),
            array_filter($matches[2])
        );

        if (empty($tables)) {
            debugging('No tables found in query', DEBUG_DEVELOPER);
            return false;
        }

        $tables = array_unique($tables);

        // Check each table against whitelist.
        foreach ($tables as $table) {
            if (!in_array($table, self::$allowed_tables)) {
                debugging('Table not whitelisted: ' . $table, DEBUG_DEVELOPER);
                return false;
            }
        }

        // Ensure query uses Moodle table prefix notation {tablename}.
        if (preg_match('/' . preg_quote($CFG->prefix, '/') . '[a-z0-9_]+/i', $sql)) {
            debugging('Use {tablename} notation instead of ' . $CFG->prefix . 'tablename', DEBUG_DEVELOPER);
            // Allow it but warn - Moodle will handle it.
        }

        return true;
    }

    /**
     * Get list of allowed tables for custom reports.
     *
     * @return array Array of allowed table names
     */
    public static function get_allowed_tables() {
        return self::$allowed_tables;
    }

    /**
     * Add a table to the whitelist (for extensions).
     *
     * @param string $tablename Table name to add
     * @return bool True on success
     */
    public static function add_allowed_table($tablename) {
        if (!in_array($tablename, self::$allowed_tables)) {
            self::$allowed_tables[] = $tablename;
            return true;
        }
        return false;
    }

    /**
     * Validate parameter placeholders in SQL.
     *
     * @param string $sql SQL query
     * @return bool True if parameters are valid
     */
    private function validate_parameters($sql) {
        // Check for named parameters (:paramname).
        preg_match_all('/:([a-z0-9_]+)/i', $sql, $matches);
        
        // Validate parameter naming convention.
        if (!empty($matches[1])) {
            foreach ($matches[1] as $param) {
                // Must be alphanumeric and underscore only.
                if (!preg_match('/^[a-z0-9_]+$/i', $param)) {
                    debugging('Invalid parameter name: ' . $param, DEBUG_DEVELOPER);
                    return false;
                }
                
                // Parameter name should not be too long.
                if (strlen($param) > 50) {
                    debugging('Parameter name too long: ' . $param, DEBUG_DEVELOPER);
                    return false;
                }
            }
        }

        // Check for question mark placeholders (not recommended in Moodle).
        if (strpos($sql, '?') !== false) {
            debugging('Use named parameters (:param) instead of ? placeholders', DEBUG_DEVELOPER);
            return false;
        }

        // Check for potential SQL injection patterns in string literals.
        if (preg_match('/[\'"].*(?:--|\/\*|\*\/|;).*[\'"]/', $sql)) {
            debugging('Suspicious patterns in string literals', DEBUG_DEVELOPER);
            // Don't fail, but warn - might be legitimate.
        }

        return true;
    }

    /**
     * Validate parameters match query placeholders.
     *
     * @param string $sql SQL query
     * @param array $params Parameters array
     * @return bool True if parameters match
     */
    public function validate_parameter_match($sql, $params) {
        // Extract parameter names from SQL.
        preg_match_all('/:([a-z0-9_]+)/i', $sql, $matches);
        $sqlparams = array_unique($matches[1]);
        
        // Check that all SQL parameters have values.
        foreach ($sqlparams as $param) {
            if (!array_key_exists($param, $params)) {
                debugging('Missing parameter value: ' . $param, DEBUG_DEVELOPER);
                return false;
            }
        }
        
        // Warn about unused parameters (not an error).
        foreach (array_keys($params) as $param) {
            if (!in_array($param, $sqlparams)) {
                debugging('Unused parameter: ' . $param, DEBUG_DEVELOPER);
            }
        }
        
        return true;
    }

    /**
     * Apply dynamic filters to SQL query.
     *
     * @param string $sql Original SQL query
     * @param array $filters Filter parameters
     * @return string Modified SQL query
     */
    public function apply_filters($sql, $filters) {
        // Filters are applied via parameter binding, not SQL modification.
        // This method is a placeholder for future filter logic.
        return $sql;
    }

    /**
     * Generate count SQL from original query.
     *
     * @param string $sql Original SQL query
     * @return string Count SQL query
     */
    private function get_count_sql($sql) {
        // Remove ORDER BY clause for count query (improves performance).
        // Use a more robust regex that handles multiline ORDER BY.
        $countsql = preg_replace('/\s+ORDER\s+BY\s+[^)]+$/is', '', $sql);
        
        // Remove LIMIT clause.
        $countsql = preg_replace('/\s+LIMIT\s+\d+(\s+OFFSET\s+\d+)?$/is', '', $countsql);
        
        // Wrap in COUNT query - use AS alias for better compatibility.
        return "SELECT COUNT(*) FROM ({$countsql}) AS countquery";
    }

    /**
     * Save a new custom report.
     *
     * @param object $report Report object with name, description, type, sqlquery, configjson
     * @param int $userid User ID creating the report
     * @return int New report ID
     * @throws \moodle_exception
     */
    public function save_report($report, $userid) {
        global $DB;

        // Validate required fields.
        if (empty($report->name)) {
            throw new \moodle_exception('reportnamerequired', 'local_manireports');
        }

        if ($report->type === 'sql' && empty($report->sqlquery)) {
            throw new \moodle_exception('sqlqueryrequired', 'local_manireports');
        }

        if ($report->type === 'gui' && empty($report->configjson)) {
            throw new \moodle_exception('configjsonrequired', 'local_manireports');
        }

        // Validate SQL if type is sql.
        if ($report->type === 'sql' && !$this->validate_sql($report->sqlquery)) {
            throw new \moodle_exception('invalidsql', 'local_manireports');
        }

        // Validate GUI configuration if type is gui.
        if ($report->type === 'gui') {
            $this->validate_gui_config($report->configjson);
        }

        $now = time();
        $record = new \stdClass();
        $record->name = $report->name;
        $record->description = $report->description ?? '';
        $record->type = $report->type ?? 'sql';
        $record->sqlquery = $report->sqlquery ?? null;
        $record->configjson = $report->configjson ?? null;
        $record->createdby = $userid;
        $record->timecreated = $now;
        $record->timemodified = $now;

        $reportid = $DB->insert_record('manireports_customreports', $record);

        // Log audit trail.
        $this->log_audit($userid, 'create', 'report', $reportid, $report->name);

        return $reportid;
    }

    /**
     * Update an existing custom report.
     *
     * @param int $reportid Report ID
     * @param object $report Report object with fields to update
     * @param int $userid User ID updating the report
     * @return bool True on success
     * @throws \moodle_exception
     */
    public function update_report($reportid, $report, $userid) {
        global $DB;

        $existing = $DB->get_record('manireports_customreports', array('id' => $reportid), '*', MUST_EXIST);

        // Validate SQL if being updated.
        if (isset($report->sqlquery) && !empty($report->sqlquery)) {
            if (!$this->validate_sql($report->sqlquery)) {
                throw new \moodle_exception('invalidsql', 'local_manireports');
            }
        }

        // Validate GUI configuration if being updated.
        if (isset($report->configjson) && !empty($report->configjson)) {
            $this->validate_gui_config($report->configjson);
        }

        $record = new \stdClass();
        $record->id = $reportid;
        $record->name = $report->name ?? $existing->name;
        $record->description = $report->description ?? $existing->description;
        $record->type = $report->type ?? $existing->type;
        $record->sqlquery = $report->sqlquery ?? $existing->sqlquery;
        $record->configjson = $report->configjson ?? $existing->configjson;
        $record->timemodified = time();

        $DB->update_record('manireports_customreports', $record);

        // Invalidate cache for this report.
        $this->invalidate_report_cache($reportid);

        // Log audit trail.
        $this->log_audit($userid, 'update', 'report', $reportid, $record->name);

        return true;
    }

    /**
     * Invalidate cache for a specific report.
     *
     * @param int $reportid Report ID
     * @return bool True on success
     */
    protected function invalidate_report_cache($reportid) {
        $cachemanager = new cache_manager();
        
        // Invalidate all cache entries for this custom report.
        global $DB;
        $cachekey = 'custom_report_' . $reportid;
        
        // Delete all cache entries that start with this report's key.
        $sql = "SELECT id FROM {manireports_cache_summary} 
                WHERE " . $DB->sql_like('cachekey', ':cachekey');
        $params = array('cachekey' => $cachekey . '%');
        
        $cacheids = $DB->get_fieldset_sql($sql, $params);
        
        foreach ($cacheids as $cacheid) {
            $DB->delete_records('manireports_cache_summary', array('id' => $cacheid));
        }
        
        return true;
    }

    /**
     * Delete a custom report.
     *
     * @param int $reportid Report ID
     * @param int $userid User ID deleting the report
     * @return bool True on success
     * @throws \moodle_exception
     */
    public function delete_report($reportid, $userid) {
        global $DB;

        $report = $DB->get_record('manireports_customreports', array('id' => $reportid), '*', MUST_EXIST);

        // Delete associated schedules.
        $DB->delete_records('manireports_schedules', array('reportid' => $reportid));

        // Delete the report.
        $DB->delete_records('manireports_customreports', array('id' => $reportid));

        // Log audit trail.
        $this->log_audit($userid, 'delete', 'report', $reportid, $report->name);

        return true;
    }

    /**
     * Get list of custom reports with filtering.
     *
     * @param int $userid User ID (for permission filtering)
     * @param string $type Filter by type (sql, gui, or null for all)
     * @return array Array of report records
     */
    public function get_reports($userid, $type = null) {
        global $DB;

        $params = array();
        $where = '1=1';

        if ($type !== null) {
            $where .= ' AND type = :type';
            $params['type'] = $type;
        }

        // Site admins see all reports, others see only their own.
        if (!is_siteadmin($userid)) {
            $where .= ' AND createdby = :userid';
            $params['userid'] = $userid;
        }

        return $DB->get_records_select('manireports_customreports', $where, $params, 'name ASC');
    }

    /**
     * Validate GUI report configuration.
     *
     * @param string $configjson JSON configuration string
     * @return bool True if valid
     * @throws \moodle_exception If configuration is invalid
     */
    private function validate_gui_config($configjson) {
        $config = json_decode($configjson);
        
        if (!$config) {
            throw new \moodle_exception('invalidconfig', 'local_manireports', '', 'Invalid JSON format');
        }

        // Validate using query_builder.
        try {
            $querybuilder = new query_builder();
            $sqldata = $querybuilder->build_sql_from_config($config);
            
            // Validate the generated SQL.
            if (!$this->validate_sql($sqldata['sql'])) {
                throw new \moodle_exception('invalidsql', 'local_manireports', '', 'Generated SQL is invalid');
            }
        } catch (\Exception $e) {
            throw new \moodle_exception('invalidconfig', 'local_manireports', '', $e->getMessage());
        }

        return true;
    }

    /**
     * Log audit trail for report operations.
     *
     * @param int $userid User ID
     * @param string $action Action performed
     * @param string $objecttype Object type
     * @param int $objectid Object ID
     * @param string $details Additional details
     */
    private function log_audit($userid, $action, $objecttype, $objectid, $details) {
        global $DB;

        $record = new \stdClass();
        $record->userid = $userid;
        $record->action = $action;
        $record->objecttype = $objecttype;
        $record->objectid = $objectid;
        $record->details = $details;
        $record->timecreated = time();

        $DB->insert_record('manireports_audit_logs', $record);
    }
}
