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
 * Cache manager for pre-aggregation and caching.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_manireports\api;

defined('MOODLE_INTERNAL') || die();

/**
 * Cache manager class.
 */
class cache_manager {

    /**
     * Get cached data by key.
     *
     * @param string $cachekey Cache key
     * @return object|null Cached data or null if not found/expired
     */
    public function get_cached_data($cachekey) {
        global $DB;

        $cache = $DB->get_record('manireports_cache_summary', array('cachekey' => $cachekey));

        if (!$cache) {
            return null;
        }

        // Check if cache is expired.
        $age = time() - $cache->lastgenerated;
        if ($age > $cache->ttl) {
            // Cache expired, delete it.
            $DB->delete_records('manireports_cache_summary', array('id' => $cache->id));
            return null;
        }

        // Decode JSON data.
        return json_decode($cache->datajson);
    }

    /**
     * Set cached data.
     *
     * @param string $cachekey Cache key
     * @param mixed $data Data to cache
     * @param string $reporttype Report type
     * @param int|null $referenceid Reference ID (course, user, etc)
     * @param int $ttl Time to live in seconds
     * @return bool True on success
     */
    public function set_cached_data($cachekey, $data, $reporttype, $referenceid = null, $ttl = 3600) {
        global $DB;

        // Check if cache record exists.
        $cache = $DB->get_record('manireports_cache_summary', array('cachekey' => $cachekey));

        $record = new \stdClass();
        $record->cachekey = $cachekey;
        $record->reporttype = $reporttype;
        $record->referenceid = $referenceid;
        $record->datajson = json_encode($data);
        $record->lastgenerated = time();
        $record->ttl = $ttl;

        if ($cache) {
            $record->id = $cache->id;
            $DB->update_record('manireports_cache_summary', $record);
        } else {
            $DB->insert_record('manireports_cache_summary', $record);
        }

        return true;
    }

    /**
     * Invalidate cache by key.
     *
     * @param string $cachekey Cache key
     * @return bool True on success
     */
    public function invalidate_cache($cachekey) {
        global $DB;
        return $DB->delete_records('manireports_cache_summary', array('cachekey' => $cachekey));
    }

    /**
     * Invalidate all cache for a report type.
     *
     * @param string $reporttype Report type
     * @return bool True on success
     */
    public function invalidate_report_cache($reporttype) {
        global $DB;
        return $DB->delete_records('manireports_cache_summary', array('reporttype' => $reporttype));
    }

    /**
     * Generate cache key from parameters.
     *
     * @param string $reporttype Report type
     * @param array $params Parameters
     * @return string Cache key
     */
    public function generate_cache_key($reporttype, $params = array()) {
        ksort($params);
        $paramstring = http_build_query($params);
        return $reporttype . '_' . md5($paramstring);
    }

    /**
     * Run pre-aggregations for heavy reports.
     *
     * @return int Number of aggregations run
     */
    public function run_aggregations() {
        $count = 0;

        // Aggregate 12-month enrollment trends.
        if ($this->aggregate_enrollment_trends()) {
            $count++;
        }

        // Aggregate 12-month completion trends.
        if ($this->aggregate_completion_trends()) {
            $count++;
        }

        // Aggregate company-wide statistics (if IOMAD).
        $iomadfilter = new iomad_filter();
        if ($iomadfilter::is_iomad_installed()) {
            $count += $this->aggregate_company_stats();
        }

        return $count;
    }

    /**
     * Aggregate enrollment trends for last 12 months.
     *
     * @return bool True on success
     */
    protected function aggregate_enrollment_trends() {
        global $DB;

        $sql = "SELECT DATE_FORMAT(FROM_UNIXTIME(ue.timecreated), '%Y-%m') as month,
                       COUNT(DISTINCT ue.id) as enrollments
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {course} c ON c.id = e.courseid
                 WHERE ue.timecreated >= :since
                   AND c.id > 1
                 GROUP BY month
                 ORDER BY month ASC";

        $since = strtotime('-12 months');
        $data = $DB->get_records_sql($sql, array('since' => $since));

        $ttl = get_config('local_manireports', 'cachettl_historical') ?: 86400;
        $cachekey = $this->generate_cache_key('enrollment_trends_12m');

        return $this->set_cached_data($cachekey, $data, 'enrollment_trends', null, $ttl);
    }

    /**
     * Aggregate completion trends for last 12 months.
     *
     * @return bool True on success
     */
    protected function aggregate_completion_trends() {
        global $DB;

        $sql = "SELECT DATE_FORMAT(FROM_UNIXTIME(cc.timecompleted), '%Y-%m') as month,
                       COUNT(DISTINCT cc.id) as completions
                  FROM {course_completions} cc
                  JOIN {course} c ON c.id = cc.course
                 WHERE cc.timecompleted IS NOT NULL
                   AND cc.timecompleted >= :since
                   AND c.id > 1
                 GROUP BY month
                 ORDER BY month ASC";

        $since = strtotime('-12 months');
        $data = $DB->get_records_sql($sql, array('since' => $since));

        $ttl = get_config('local_manireports', 'cachettl_historical') ?: 86400;
        $cachekey = $this->generate_cache_key('completion_trends_12m');

        return $this->set_cached_data($cachekey, $data, 'completion_trends', null, $ttl);
    }

    /**
     * Aggregate company-wide statistics.
     *
     * @return int Number of companies aggregated
     */
    protected function aggregate_company_stats() {
        global $DB;

        $companies = $DB->get_records('company', null, '', 'id, name');
        $count = 0;

        foreach ($companies as $company) {
            // Get company user count.
            $usercount = $DB->count_records('company_users', array('companyid' => $company->id));

            // Get company course count.
            $coursecount = $DB->count_records('company_course', array('companyid' => $company->id));

            // Get company enrollment count.
            $sql = "SELECT COUNT(DISTINCT ue.id)
                      FROM {user_enrolments} ue
                      JOIN {enrol} e ON e.id = ue.enrolid
                      JOIN {company_course} cc ON cc.courseid = e.courseid
                     WHERE cc.companyid = :companyid
                       AND ue.status = 0";
            $enrollcount = $DB->count_records_sql($sql, array('companyid' => $company->id));

            $data = array(
                'usercount' => $usercount,
                'coursecount' => $coursecount,
                'enrollcount' => $enrollcount
            );

            $ttl = get_config('local_manireports', 'cachettl_dashboard') ?: 3600;
            $cachekey = $this->generate_cache_key('company_stats', array('companyid' => $company->id));

            if ($this->set_cached_data($cachekey, $data, 'company_stats', $company->id, $ttl)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Clean up expired cache entries.
     *
     * @return int Number of entries deleted
     */
    public function cleanup_expired_cache() {
        global $DB;

        $sql = "SELECT id, lastgenerated, ttl
                  FROM {manireports_cache_summary}";

        $caches = $DB->get_records_sql($sql);
        $count = 0;
        $now = time();

        foreach ($caches as $cache) {
            $age = $now - $cache->lastgenerated;
            if ($age > $cache->ttl) {
                if ($DB->delete_records('manireports_cache_summary', array('id' => $cache->id))) {
                    $count++;
                }
            }
        }

        return $count;
    }
}
