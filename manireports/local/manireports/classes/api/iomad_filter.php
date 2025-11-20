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
 * IOMAD filter for multi-tenant company isolation.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_manireports\api;

defined('MOODLE_INTERNAL') || die();

/**
 * IOMAD filter class for applying company-based filtering to queries.
 */
class iomad_filter {

    /**
     * Check if IOMAD is installed and available.
     *
     * @return bool True if IOMAD is installed, false otherwise
     */
    public static function is_iomad_installed() {
        global $CFG, $DB;

        // Check if IOMAD company table exists.
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('company');
        
        if (!$dbman->table_exists($table)) {
            return false;
        }

        // Check if IOMAD company library exists.
        if (!file_exists($CFG->dirroot . '/local/iomad/lib/company.php')) {
            return false;
        }

        return true;
    }

    /**
     * Get companies assigned to a user.
     *
     * @param int $userid User ID
     * @return array Array of company IDs the user belongs to
     */
    public static function get_user_companies($userid) {
        global $DB;

        if (!self::is_iomad_installed()) {
            return array();
        }

        // Check if user is site admin.
        if (is_siteadmin($userid)) {
            // Site admins can see all companies.
            return $DB->get_fieldset_select('company', 'id', '', array());
        }

        // Get companies where user is assigned.
        $sql = "SELECT DISTINCT c.id
                  FROM {company} c
                  JOIN {company_users} cu ON cu.companyid = c.id
                 WHERE cu.userid = :userid";
        
        return $DB->get_fieldset_sql($sql, array('userid' => $userid));
    }

    /**
     * Apply company filter to SQL query.
     *
     * This method modifies the SQL query to include company filtering based on user's company assignments.
     *
     * @param string $sql Original SQL query
     * @param int $userid User ID to filter for
     * @param string $useralias Alias used for user table in the query (default: 'u')
     * @param int|null $companyid Optional specific company ID to filter by
     * @return string Modified SQL query with company filter applied
     */
    public static function apply_company_filter($sql, $userid, $useralias = 'u', $companyid = null) {
        global $DB;

        if (!self::is_iomad_installed()) {
            return $sql;
        }

        // Site admins can see all data unless specific company is requested.
        if (is_siteadmin($userid) && $companyid === null) {
            return $sql;
        }

        // Get user's companies or use specific company.
        if ($companyid !== null) {
            $companies = array($companyid);
        } else {
            $companies = self::get_user_companies($userid);
        }

        // If no companies found, return query that returns no results.
        if (empty($companies)) {
            return $sql . " AND 1=0";
        }

        // Build company filter clause.
        list($insql, $params) = $DB->get_in_or_equal($companies, SQL_PARAMS_NAMED, 'company');
        
        $companyfilter = " AND {$useralias}.id IN (
            SELECT cu.userid 
            FROM {company_users} cu 
            WHERE cu.companyid $insql
        )";

        // Add company filter to WHERE clause.
        // Look for WHERE keyword and append the filter.
        if (stripos($sql, 'WHERE') !== false) {
            // WHERE clause exists, append to it.
            $sql = preg_replace('/WHERE/i', 'WHERE 1=1 ' . $companyfilter . ' AND', $sql, 1);
        } else {
            // No WHERE clause, add one before ORDER BY, GROUP BY, or LIMIT.
            $keywords = array('ORDER BY', 'GROUP BY', 'LIMIT', 'HAVING');
            $position = false;
            
            foreach ($keywords as $keyword) {
                $pos = stripos($sql, $keyword);
                if ($pos !== false && ($position === false || $pos < $position)) {
                    $position = $pos;
                }
            }
            
            if ($position !== false) {
                $sql = substr($sql, 0, $position) . ' WHERE 1=1 ' . $companyfilter . ' ' . substr($sql, $position);
            } else {
                $sql .= ' WHERE 1=1 ' . $companyfilter;
            }
        }

        return $sql;
    }

    /**
     * Get company selector options for UI dropdown.
     *
     * @param int $userid User ID
     * @return array Array of company options [id => name]
     */
    public static function get_company_selector_options($userid) {
        global $DB;

        if (!self::is_iomad_installed()) {
            return array();
        }

        // Site admins can see all companies.
        if (is_siteadmin($userid)) {
            return $DB->get_records_menu('company', null, 'name ASC', 'id, name');
        }

        // Get user's companies.
        $companies = self::get_user_companies($userid);
        
        if (empty($companies)) {
            return array();
        }

        list($insql, $params) = $DB->get_in_or_equal($companies, SQL_PARAMS_NAMED);
        
        // Get records and convert to menu format (id => name).
        $records = $DB->get_records_select('company', "id $insql", $params, 'name ASC', 'id, name');
        
        $options = array();
        foreach ($records as $record) {
            $options[$record->id] = $record->name;
        }
        
        return $options;
    }

    /**
     * Check if user has access to a specific company.
     *
     * @param int $userid User ID
     * @param int $companyid Company ID
     * @return bool True if user has access, false otherwise
     */
    public static function has_company_access($userid, $companyid) {
        if (!self::is_iomad_installed()) {
            return true;
        }

        // Site admins have access to all companies.
        if (is_siteadmin($userid)) {
            return true;
        }

        $companies = self::get_user_companies($userid);
        return in_array($companyid, $companies);
    }

    /**
     * Get company name by ID.
     *
     * @param int $companyid Company ID
     * @return string|null Company name or null if not found
     */
    public static function get_company_name($companyid) {
        global $DB;

        if (!self::is_iomad_installed()) {
            return null;
        }

        return $DB->get_field('company', 'name', array('id' => $companyid));
    }

    /**
     * Get default company for user.
     *
     * Returns the first company the user is assigned to, or null if none.
     *
     * @param int $userid User ID
     * @return int|null Company ID or null
     */
    public static function get_default_company($userid) {
        $companies = self::get_user_companies($userid);
        return !empty($companies) ? reset($companies) : null;
    }

    /**
     * Filter course list by company.
     *
     * @param int $userid User ID
     * @param int|null $companyid Optional company ID to filter by
     * @return array Array of course IDs accessible to the user
     */
    public static function get_company_courses($userid, $companyid = null) {
        global $DB;

        if (!self::is_iomad_installed()) {
            // Return all courses user has access to.
            return array_keys(enrol_get_all_users_courses($userid, true));
        }

        // Get user's companies or use specific company.
        if ($companyid !== null) {
            $companies = array($companyid);
        } else {
            $companies = self::get_user_companies($userid);
        }

        if (empty($companies)) {
            return array();
        }

        list($insql, $params) = $DB->get_in_or_equal($companies, SQL_PARAMS_NAMED);
        
        $sql = "SELECT DISTINCT cc.courseid
                  FROM {company_course} cc
                 WHERE cc.companyid $insql";
        
        return $DB->get_fieldset_sql($sql, $params);
    }

    /**
     * Filter user list by company.
     *
     * @param int $requestinguserid User ID making the request
     * @param int|null $companyid Optional company ID to filter by
     * @return array Array of user IDs accessible to the requesting user
     */
    public static function get_company_users($requestinguserid, $companyid = null) {
        global $DB;

        if (!self::is_iomad_installed()) {
            return array();
        }

        // Get companies to filter by.
        if ($companyid !== null) {
            $companies = array($companyid);
        } else {
            $companies = self::get_user_companies($requestinguserid);
        }

        if (empty($companies)) {
            return array();
        }

        list($insql, $params) = $DB->get_in_or_equal($companies, SQL_PARAMS_NAMED);
        
        $sql = "SELECT DISTINCT cu.userid
                  FROM {company_users} cu
                 WHERE cu.companyid $insql";
        
        return $DB->get_fieldset_sql($sql, $params);
    }
}
