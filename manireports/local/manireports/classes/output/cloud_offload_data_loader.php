<?php
namespace local_manireports\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Class cloud_offload_data_loader
 *
 * Handles data fetching for Cloud Offload features (Email & Certificates).
 * Separated from dashboard_data_loader to maintain clean separation of concerns.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 */
class cloud_offload_data_loader {

    /** @var int User ID requesting the data */
    protected $userid;

    /**
     * Constructor.
     *
     * @param int $userid User ID
     */
    public function __construct($userid) {
        $this->userid = $userid;
    }

    /**
     * Get Cloud Jobs filtered by type.
     *
     * @param string $type 'email' or 'certificate' (or 'all')
     * @param string $status_filter 'active' (pending/processing) or 'history' (completed/failed)
     * @param int $limit Number of records
     * @return array Job records
     */
    public function get_cloud_jobs($type = 'all', $status_filter = 'active', $limit = 10) {
        global $DB;

        $params = [];
        $where = "1=1";

        // Filter by Type
        if ($type === 'email') {
            // Assuming 'user_created', 'license_allocation' are email types
            // If we have specific certificate types, we filter them out or in.
            // For now, let's assume all current types are email-related unless specified otherwise.
            // If we add certificate generation later, we'll have a 'certificate_generation' type.
            $where .= " AND type IN ('user_created', 'license_allocation', 'csv_import')"; 
        } elseif ($type === 'certificate') {
            $where .= " AND type = 'certificate_generation'";
        }

        // Filter by Status
        if ($status_filter === 'active') {
            $where .= " AND status IN ('pending', 'queued', 'processing')";
            $sort = "created_at DESC";
        } else {
            $where .= " AND status IN ('completed', 'failed', 'partial_failure')";
            $sort = "completed_at DESC";
        }

        return $DB->get_records_select('manireports_cloud_jobs', $where, $params, $sort, '*', 0, $limit);
    }

    /**
     * Get Job Statistics (KPIs).
     *
     * @param string $type 'email' or 'certificate'
     * @return array KPI data
     */
    public function get_job_stats($type = 'all') {
        global $DB;

        $params = [];
        $where = "1=1";

        if ($type === 'email') {
            $where .= " AND type IN ('user_created', 'license_allocation', 'csv_import')";
        } elseif ($type === 'certificate') {
            $where .= " AND type = 'certificate_generation'";
        }

        // Active Jobs
        $active = $DB->count_records_select('manireports_cloud_jobs', "$where AND status IN ('pending', 'queued', 'processing')", $params);

        // Completed (Today)
        $today_start = strtotime("today 00:00:00");
        $completed_today = $DB->count_records_select('manireports_cloud_jobs', "$where AND status = 'completed' AND completed_at >= $today_start", $params);

        // Failed (Today)
        $failed_today = $DB->count_records_select('manireports_cloud_jobs', "$where AND status IN ('failed', 'partial_failure') AND completed_at >= $today_start", $params);

        // Total Emails Sent (Today) - Sum of emails_sent column
        // Total Emails Sent (Today) - Sum of emails_sent column
        $emails_sent_today = 0;
        // Use COALESCE to ensure we get 0 instead of NULL if no records match
        $sql = "SELECT COALESCE(SUM(emails_sent), 0) FROM {manireports_cloud_jobs} WHERE $where AND completed_at >= :today";
        $emails_sent_today = $DB->get_field_sql($sql, array_merge($params, ['today' => $today_start]));

        return [
            'active_jobs' => $active,
            'completed_today' => $completed_today,
            'failed_today' => $failed_today,
            'sent_today' => $emails_sent_today ?: 0
        ];
    }

    /**
     * Get Company Settings.
     *
     * @param int $companyid
     * @return object|false Settings record
     */
    public function get_company_settings($companyid) {
        global $DB;
        return $DB->get_record('manireports_cloud_conf', ['company_id' => $companyid]);
    }

    /**
     * Get all companies (for the settings dropdown).
     *
     * @return array Companies
     */
    public function get_companies() {
        global $DB;
        if ($DB->get_manager()->table_exists('company')) {
            return $DB->get_records('company', null, 'name ASC');
        }
        return [];
    }

    /**
     * Get recipients for a specific job.
     *
     * @param int $job_id
     * @return array Recipient records
     */
    public function get_job_recipients($job_id) {
        global $DB;
        return $DB->get_records('manireports_cloud_recip', ['job_id' => $job_id]);
    }
}
