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
 * Base report class for all prebuilt reports.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_manireports\reports;

use local_manireports\api\iomad_filter;

defined('MOODLE_INTERNAL') || die();

/**
 * Abstract base class for all reports.
 */
abstract class base_report {

    /**
     * @var int User ID executing the report
     */
    protected $userid;

    /**
     * @var array Report parameters
     */
    protected $params;

    /**
     * Constructor.
     *
     * @param int $userid User ID executing the report
     * @param array $params Report parameters
     */
    public function __construct($userid, $params = array()) {
        $this->userid = $userid;
        $this->params = $params;
    }

    /**
     * Get the SQL query for this report.
     *
     * Must be implemented by child classes.
     *
     * @return string SQL query
     */
    abstract protected function get_sql();

    /**
     * Get column definitions for this report.
     *
     * Must be implemented by child classes.
     *
     * @return array Array of column definitions [name => label]
     */
    abstract protected function get_columns();

    /**
     * Get report name.
     *
     * Must be implemented by child classes.
     *
     * @return string Report name
     */
    abstract public function get_name();

    /**
     * Get report description.
     *
     * Must be implemented by child classes.
     *
     * @return string Report description
     */
    abstract public function get_description();

    /**
     * Execute the report and return results.
     *
     * @param int $page Page number (0-based)
     * @param int $perpage Records per page
     * @param bool $usecache Whether to use cache (default true)
     * @return array Array containing 'data', 'columns', 'total', 'page', 'perpage', 'cached'
     */
    public function execute($page = 0, $perpage = 25, $usecache = true) {
        global $DB;

        $starttime = microtime(true);
        $cached = false;

        // Check if caching is enabled for this report.
        if ($usecache && $this->is_cacheable()) {
            $cachemanager = new \local_manireports\api\cache_manager();
            
            // Generate cache key including pagination.
            $reporttype = $this->get_report_type();
            $cacheparams = array_merge($this->params, array(
                'page' => $page,
                'perpage' => $perpage
            ));
            $cachekey = $cachemanager->generate_cache_key($reporttype, $cacheparams);
            
            // Try to get cached data.
            $cacheddata = $cachemanager->get_cached_data($cachekey);
            
            if ($cacheddata !== null) {
                // Cache hit - return cached data.
                $cached = true;
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

        // Cache miss or caching disabled - execute query.
        // Get SQL query.
        $sql = $this->get_sql();

        // Apply IOMAD company filter.
        $sql = $this->apply_iomad_filter($sql);

        // Prepare SQL parameters (add wildcards for LIKE queries).
        $sqlparams = $this->prepare_sql_params();

        // Get total count for pagination.
        $countsql = $this->get_count_sql($sql);
        $total = $DB->count_records_sql($countsql, $sqlparams);

        // Execute query with pagination.
        try {
            $results = $DB->get_records_sql($sql, $sqlparams, $page * $perpage, $perpage);
        } catch (\dml_exception $e) {
            debugging('Report execution error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            throw new \moodle_exception('databaseerror', 'local_manireports');
        }

        // Format results.
        $formattedresults = array();
        foreach ($results as $row) {
            $formattedresults[] = $this->format_row($row);
        }

        $executiontime = microtime(true) - $starttime;

        $result = array(
            'data' => $formattedresults,
            'columns' => $this->get_columns(),
            'total' => $total,
            'page' => $page,
            'perpage' => $perpage,
            'cached' => false,
            'executiontime' => round($executiontime, 3)
        );

        // Cache the result if caching is enabled.
        if ($usecache && $this->is_cacheable()) {
            $cachemanager = new \local_manireports\api\cache_manager();
            $reporttype = $this->get_report_type();
            $cacheparams = array_merge($this->params, array(
                'page' => $page,
                'perpage' => $perpage
            ));
            $cachekey = $cachemanager->generate_cache_key($reporttype, $cacheparams);
            $ttl = $this->get_cache_ttl();
            
            // Store only data, columns, and total (not execution time).
            $cachedata = (object)array(
                'data' => $result['data'],
                'columns' => $result['columns'],
                'total' => $result['total']
            );
            
            $cachemanager->set_cached_data($cachekey, $cachedata, $reporttype, null, $ttl);
        }

        return $result;
    }

    /**
     * Check if this report is cacheable.
     *
     * Can be overridden by child classes.
     *
     * @return bool True if cacheable
     */
    protected function is_cacheable() {
        // By default, reports are cacheable.
        // Override this method to disable caching for specific reports.
        return true;
    }

    /**
     * Get cache TTL for this report.
     *
     * Can be overridden by child classes.
     *
     * @return int TTL in seconds
     */
    protected function get_cache_ttl() {
        // Default to dashboard cache TTL.
        return get_config('local_manireports', 'cachettl_dashboard') ?: 3600;
    }

    /**
     * Get report type identifier.
     *
     * @return string Report type
     */
    protected function get_report_type() {
        // Get class name without namespace.
        $classname = get_class($this);
        $parts = explode('\\', $classname);
        return end($parts);
    }



    /**
     * Prepare SQL parameters for execution.
     * 
     * This method adds wildcards for LIKE queries and handles special parameter formatting.
     *
     * @return array Prepared SQL parameters
     */
    protected function prepare_sql_params() {
        $sqlparams = $this->params;
        
        // Add wildcard parameters for user search (username or email).
        if (!empty($this->params['usersearch'])) {
            $sqlparams['usersearch'] = '%' . $this->params['usersearch'] . '%';
            $sqlparams['usersearch2'] = '%' . $this->params['usersearch'] . '%';
        }
        
        return $sqlparams;
    }

    /**
     * Apply IOMAD company filter to SQL query.
     *
     * @param string $sql Original SQL query
     * @return string Modified SQL query with company filter
     */
    protected function apply_iomad_filter($sql) {
        if (!iomad_filter::is_iomad_installed()) {
            return $sql;
        }

        // Get company ID from parameters if specified.
        $companyid = isset($this->params['companyid']) ? $this->params['companyid'] : null;

        // Apply company filter.
        return iomad_filter::apply_company_filter($sql, $this->userid, 'u', $companyid);
    }

    /**
     * Generate count SQL from original query.
     *
     * @param string $sql Original SQL query
     * @return string Count SQL query
     */
    protected function get_count_sql($sql) {
        // Remove ORDER BY clause for count query.
        $countsql = preg_replace('/ORDER BY .+$/i', '', $sql);
        return "SELECT COUNT(*) FROM ($countsql) countquery";
    }

    /**
     * Get default parameters for this report.
     *
     * Can be overridden by child classes.
     *
     * @return array Default parameters
     */
    public function get_default_params() {
        return array();
    }

    /**
     * Get filter definitions for this report.
     *
     * Can be overridden by child classes.
     *
     * @return array Array of filter definitions
     */
    public function get_filters() {
        $filters = array();

        // Add company filter if IOMAD is installed.
        if (iomad_filter::is_iomad_installed()) {
            $filters['companyid'] = array(
                'type' => 'select',
                'label' => get_string('company', 'local_manireports'),
                'options' => iomad_filter::get_company_selector_options($this->userid)
            );
        }

        return $filters;
    }

    /**
     * Validate report parameters.
     *
     * Can be overridden by child classes.
     *
     * @param array $params Parameters to validate
     * @return bool True if valid
     */
    public function validate_params($params) {
        return true;
    }

    /**
     * Format a result row for display.
     *
     * Can be overridden by child classes to format specific columns.
     *
     * @param object $row Result row
     * @return object Formatted row
     */
    public function format_row($row) {
        // Format common timestamp fields.
        $timestamp_fields = ['timecreated', 'timemodified', 'timecompleted', 'lastaccess', 'last_attempt'];
        foreach ($timestamp_fields as $field) {
            if (isset($row->$field) && is_numeric($row->$field) && $row->$field > 0) {
                $row->$field = userdate($row->$field, get_string('strftimedatetime', 'langconfig'));
            } else if (isset($row->$field)) {
                $row->$field = '-';
            }
        }
        return $row;
    }

    /**
     * Get export filename for this report.
     *
     * @param string $format Export format (csv, xlsx, pdf)
     * @return string Filename
     */
    public function get_export_filename($format) {
        $name = strtolower(str_replace(' ', '_', $this->get_name()));
        $date = date('Y-m-d');
        return "{$name}_{$date}.{$format}";
    }

    /**
     * Check if user has permission to view this report.
     *
     * @param int $userid User ID
     * @return bool True if user has permission
     */
    public function has_permission($userid) {
        $context = \context_system::instance();
        
        // Check if user has any dashboard capability.
        return has_capability('local/manireports:viewadmindashboard', $context, $userid) ||
               has_capability('local/manireports:viewmanagerdashboard', $context, $userid) ||
               has_capability('local/manireports:viewteacherdashboard', $context, $userid) ||
               has_capability('local/manireports:viewstudentdashboard', $context, $userid);
    }

    /**
     * Get chart configuration for this report.
     *
     * Can be overridden by child classes to provide chart data.
     *
     * @return array|null Chart configuration or null if no chart
     */
    public function get_chart_config() {
        return null;
    }

    /**
     * Get chart data for visualization.
     *
     * Can be overridden by child classes to provide custom chart data.
     *
     * @param array $data Report data
     * @return array|null Chart data in Chart.js format or null if no chart
     */
    public function get_chart_data($data) {
        return null;
    }
}
