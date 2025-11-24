<?php
namespace local_manireports\api;

defined('MOODLE_INTERNAL') || die();

/**
 * Class CloudJobManager
 *
 * Manages the lifecycle of cloud offload jobs: creation, submission, and status updates.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 */
class CloudJobManager {

    /**
     * Creates a new cloud job in the database.
     *
     * @param string $type The type of job (e.g., 'csv_import', 'license_allocation').
     * @param array $recipients Array of recipients data. Each item must have 'email'.
     * @param int $company_id The IOMAD company ID.
     * @return int The ID of the created job.
     * @throws \dml_exception
     */
    public function create_job(string $type, array $recipients, int $company_id): int {
        global $DB;

        $job = new \stdClass();
        $job->type = $type;
        $job->status = 'pending';
        $job->email_count = count($recipients);
        $job->company_id = $company_id;
        $job->created_at = time();
        
        $job_id = $DB->insert_record('manireports_cloud_jobs', $job);

        // Insert recipients
        foreach ($recipients as $recipient) {
            $recip = new \stdClass();
            $recip->job_id = $job_id;
            $recip->email = $recipient['email'];
            // Store other data as JSON
            unset($recipient['email']);
            $recip->recipient_data = json_encode($recipient);
            $recip->status = 'pending';
            
            $DB->insert_record('manireports_cloud_recip', $recip);
        }

        return $job_id;
    }

    /**
     * Submits a job to the cloud provider.
     *
     * @param int $job_id
     * @return bool
     */
    public function submit_job(int $job_id): bool {
        global $DB;

        try {
            $job = $DB->get_record('manireports_cloud_jobs', ['id' => $job_id], '*', MUST_EXIST);
            
            // Get Company Settings
            $settings = $DB->get_record('manireports_cloud_conf', ['company_id' => $job->company_id], '*', MUST_EXIST);
            
            $connector = new connectors\AwsConnector($settings);
            
            // Get recipients
            $recipients = $DB->get_records('manireports_cloud_recip', ['job_id' => $job_id]);
            
            // Prepare payload
            $payload = [
                'job_id' => $job_id,
                'type' => $job->type,
                'recipients' => array_values($recipients) // Ensure array is indexed
            ];

            $message_id = $connector->submit_job($payload);

            if ($message_id) {
                $this->update_job_status($job_id, 'queued');
                return true;
            } else {
                $this->update_job_status($job_id, 'failed');
                return false;
            }

        } catch (\Exception $e) {
            // Try to log to DB, but fallback to system log if DB fails
            try {
                $this->log_error($job_id, "Submission failed: " . $e->getMessage());
                $this->update_job_status($job_id, 'failed');
            } catch (\Exception $db_e) {
                error_log("CloudOffload: CRITICAL - Failed to log error to DB for Job $job_id. Original Error: " . $e->getMessage() . ". DB Error: " . $db_e->getMessage());
            }
            return false;
        }
    }

    /**
     * Updates the status of a job.
     *
     * @param int $job_id
     * @param string $status
     * @return void
     */
    public function update_job_status(int $job_id, string $status): void {
        global $DB;
        $update = new \stdClass();
        $update->id = $job_id;
        $update->status = $status;
        
        if ($status === 'processing') {
            $update->started_at = time();
        } elseif (in_array($status, ['completed', 'failed', 'partial_failure'])) {
            $update->completed_at = time();
        }

        $DB->update_record('manireports_cloud_jobs', $update);
    }

    /**
     * Handles the callback from the cloud worker.
     *
     * @param int $job_id
     * @param array $data Callback data (status, sent_count, failed_count, errors)
     * @return void
     */
    public function handle_callback(int $job_id, array $data): void {
        global $DB;

        $job = $DB->get_record('manireports_cloud_jobs', ['id' => $job_id], '*', MUST_EXIST);

        $update = new \stdClass();
        $update->id = $job_id;
        $update->status = $data['status'];
        $update->emails_sent = $data['emails_sent'] ?? 0;
        $update->emails_failed = $data['emails_failed'] ?? 0;
        $update->completed_at = time();
        
        if (!empty($data['errors'])) {
            $update->error_log = json_encode($data['errors']);
        }

        $DB->update_record('manireports_cloud_jobs', $update);

        // Update individual recipients if detailed status provided
        if (!empty($data['recipients'])) {
            foreach ($data['recipients'] as $recip_data) {
                $recip_record = $DB->get_record('manireports_cloud_recip', ['job_id' => $job_id, 'email' => $recip_data['email']]);
                if ($recip_record) {
                    $recip_update = new \stdClass();
                    $recip_update->id = $recip_record->id;
                    $recip_update->status = $recip_data['status']; // 'sent' or 'failed'
                    
                    if ($recip_data['status'] === 'sent') {
                        $recip_update->sent_at = time();
                    }
                    
                    if (!empty($recip_data['error'])) {
                        $recip_update->error_message = $recip_data['error'];
                    }
                    
                    $DB->update_record('manireports_cloud_recip', $recip_update);
                }
            }
        }
    }

    /**
     * Logs an error for a job.
     *
     * @param int $job_id
     * @param string $message
     * @return void
     */
    private function log_error(int $job_id, string $message): void {
        global $DB;
        $job = $DB->get_record('manireports_cloud_jobs', ['id' => $job_id]);
        if ($job) {
            $current_log = $job->error_log ? $job->error_log . "\n" : "";
            $new_log = $current_log . "[" . date('Y-m-d H:i:s') . "] " . $message;
            $DB->set_field('manireports_cloud_jobs', 'error_log', $new_log, ['id' => $job_id]);
        }
    }
}
