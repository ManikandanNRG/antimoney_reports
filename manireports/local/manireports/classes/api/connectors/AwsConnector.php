<?php
namespace local_manireports\api\connectors;

defined('MOODLE_INTERNAL') || die();

use Aws\Sqs\SqsClient;
use Aws\Exception\AwsException;

/**
 * Class AwsConnector
 *
 * Handles communication with AWS services (SQS).
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 */
class AwsConnector {

    /** @var object Company settings */
    private $settings;

    /** @var SqsClient|null AWS SQS Client */
    private $client;

    /**
     * Constructor.
     *
     * @param object $settings Row from manireports_cloud_company_settings
     */
    public function __construct($settings) {
        $this->settings = $settings;
        $this->initialize_client();
    }

    /**
     * Initializes the AWS SQS Client.
     *
     * @return void
     */
    private function initialize_client() {
        // Check if AWS SDK is available
        if (!class_exists('Aws\Sqs\SqsClient')) {
            global $CFG;
            
            // 1. Try Local Plugin Vendor
            $local_autoload = $CFG->dirroot . '/local/manireports/vendor/autoload.php';
            if (file_exists($local_autoload)) {
                require_once($local_autoload);
            }
            
            // 2. Try Global Moodle Vendor (if not found locally)
            if (!class_exists('Aws\Sqs\SqsClient')) {
                $global_autoload = $CFG->dirroot . '/vendor/autoload.php';
                if (file_exists($global_autoload)) {
                    require_once($global_autoload);
                }
            }

            // Check again after trying to load
            if (!class_exists('Aws\Sqs\SqsClient')) {
                // Throw a more helpful error message
                throw new \moodle_exception(
                    'awssdkmissing', 
                    'local_manireports', 
                    '', 
                    "AWS SDK not found. Please run 'composer require aws/aws-sdk-php' in " . $CFG->dirroot
                );
            }
        }

        try {
            $this->client = new SqsClient([
                'version' => 'latest',
                'region'  => $this->settings->aws_region,
                'credentials' => [
                    'key'    => $this->settings->aws_access_key,
                    'secret' => $this->settings->aws_secret_key,
                ]
            ]);
        } catch (\Exception $e) {
            throw new \moodle_exception('awsclienterror', 'local_manireports', '', $e->getMessage());
        }
    }

    /**
     * Submits a job payload to the SQS queue.
     *
     * @param array $payload Job data
     * @return string|false Message ID on success, false on failure
     */
    public function submit_job(array $payload) {
        if (!$this->client) {
            return false;
        }

        try {
            $result = $this->client->sendMessage([
                'QueueUrl'    => $this->settings->sqs_queue_url,
                'MessageBody' => json_encode($payload),
                'MessageAttributes' => [
                    'JobType' => [
                        'DataType' => 'String',
                        'StringValue' => $payload['type']
                    ],
                    'CompanyId' => [
                        'DataType' => 'Number',
                        'StringValue' => (string)$this->settings->company_id
                    ]
                ]
            ]);

            return $result['MessageId'];

        } catch (AwsException $e) {
            // Log AWS specific error
            debugging('AWS SQS Error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        } catch (\Exception $e) {
            debugging('General Error sending to SQS: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }
}
