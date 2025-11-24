<?php
/**
 * Cloud Offload Callback Endpoint
 *
 * Receives POST requests from the Cloud Worker (Lambda) to update job status.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 */

// Define MOODLE_INTERNAL to allow including config.php
define('MOODLE_INTERNAL', true);

// Load Moodle Configuration
require_once('../../config.php');

// Set response header to JSON
header('Content-Type: application/json');

// 1. Get POST Data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (empty($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload']);
    exit;
}

// 2. Validate Required Fields
if (empty($data['job_id']) || empty($data['status']) || empty($data['token'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields (job_id, status, token)']);
    exit;
}

$job_id = (int)$data['job_id'];
$status = clean_param($data['status'], PARAM_ALPHA);
$token = clean_param($data['token'], PARAM_RAW);

// 3. Authenticate Request
global $DB;

// Get Job to find Company ID
$job = $DB->get_record('manireports_cloud_jobs', ['id' => $job_id]);
if (!$job) {
    http_response_code(404);
    echo json_encode(['error' => 'Job not found']);
    exit;
}

// Get Company Settings to verify Token (using aws_secret_key as token)
$settings = $DB->get_record('manireports_cloud_conf', ['company_id' => $job->company_id]);
if (!$settings) {
    http_response_code(500);
    echo json_encode(['error' => 'Company settings not found']);
    exit;
}

// Verify Token
// We use the AWS Secret Key as the shared secret for the callback
if ($token !== $settings->aws_secret_key) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid token']);
    exit;
}

// 4. Process Callback
try {
    $manager = new \local_manireports\api\CloudJobManager();
    $manager->handle_callback($job_id, $data);

    echo json_encode(['success' => true, 'message' => 'Job updated']);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal error: ' . $e->getMessage()]);
    error_log("CloudCallback Error: " . $e->getMessage());
}
