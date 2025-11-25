<?php
define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/output/cloud_offload_data_loader.php');

require_login();
require_sesskey();

$job_id = required_param('job_id', PARAM_INT);

$loader = new \local_manireports\output\cloud_offload_data_loader($USER->id);
$recipients = $loader->get_job_recipients($job_id);

$data = [];
foreach ($recipients as $recip) {
    $data[] = [
        'email' => $recip->email,
        'status' => $recip->status,
        'sent_at' => $recip->sent_at ? userdate($recip->sent_at) : '-',
        'error_message' => $recip->error_message ?? ''
    ];
}

echo json_encode(['recipients' => $data]);
