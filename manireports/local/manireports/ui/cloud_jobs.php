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
 * Cloud Jobs Dashboard and Configuration.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);

// Check capability.
if (!has_capability('local/manireports:managecloud', $context)) {
    // Fallback if capability not defined yet, use site config
    require_capability('moodle/site:config', $context);
}

$action = optional_param('action', 'dashboard', PARAM_ALPHA);
$companyid = optional_param('companyid', 0, PARAM_INT);

// Set up the page.
$PAGE->set_url(new moodle_url('/local/manireports/ui/cloud_jobs.php'));
$PAGE->set_title(get_string('cloudjobs', 'local_manireports'));
$PAGE->set_heading(get_string('cloudjobs', 'local_manireports'));
$PAGE->set_pagelayout('admin');

// Handle Settings Form Submission
if ($action === 'savesettings' && data_submitted() && confirm_sesskey()) {
    $settings = new stdClass();
    $settings->company_id = required_param('company_id', PARAM_INT);
    $settings->provider = required_param('provider', PARAM_ALPHA);
    $settings->enabled = optional_param('enabled', 0, PARAM_INT);
    
    if ($settings->provider === 'aws') {
        $settings->aws_access_key = required_param('aws_access_key', PARAM_TEXT);
        $settings->aws_secret_key = required_param('aws_secret_key', PARAM_TEXT);
        $settings->aws_region = required_param('aws_region', PARAM_TEXT);
        $settings->sqs_queue_url = required_param('sqs_queue_url', PARAM_URL);
        $settings->ses_sender_email = required_param('ses_sender_email', PARAM_EMAIL);
    } elseif ($settings->provider === 'cloudflare') {
        $settings->cloudflare_api_token = required_param('cloudflare_api_token', PARAM_TEXT);
        $settings->cloudflare_account_id = required_param('cloudflare_account_id', PARAM_TEXT);
    }

    // Check if settings exist
    $existing = $DB->get_record('manireports_cloud_company_settings', ['company_id' => $settings->company_id]);
    if ($existing) {
        $settings->id = $existing->id;
        $DB->update_record('manireports_cloud_company_settings', $settings);
    } else {
        $DB->insert_record('manireports_cloud_company_settings', $settings);
    }
    
    redirect(new moodle_url('/local/manireports/ui/cloud_jobs.php', ['action' => 'settings']), get_string('settingssaved', 'local_manireports'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();

// Tabs
$tabs = [
    'dashboard' => get_string('dashboard', 'local_manireports'),
    'history' => get_string('history', 'local_manireports'),
    'settings' => get_string('settings', 'local_manireports')
];

echo '<ul class="nav nav-tabs mb-3">';
foreach ($tabs as $key => $name) {
    $active = ($action === $key) ? 'active' : '';
    $url = new moodle_url('/local/manireports/ui/cloud_jobs.php', ['action' => $key]);
    echo '<li class="nav-item">';
    echo '<a class="nav-link ' . $active . '" href="' . $url . '">' . $name . '</a>';
    echo '</li>';
}
echo '</ul>';

if ($action === 'dashboard') {
    // Active Jobs
    echo '<h3>' . get_string('activejobs', 'local_manireports') . '</h3>';
    
    $jobs = $DB->get_records_select('manireports_cloud_jobs', "status IN ('pending', 'queued', 'processing')", null, 'created_at DESC');
    
    if ($jobs) {
        echo '<table class="table table-striped">';
        echo '<thead><tr><th>ID</th><th>Type</th><th>Status</th><th>Progress</th><th>Created</th></tr></thead>';
        echo '<tbody>';
        foreach ($jobs as $job) {
            $progress = $job->email_count > 0 ? round(($job->emails_sent / $job->email_count) * 100) : 0;
            echo '<tr>';
            echo '<td>' . $job->id . '</td>';
            echo '<td>' . $job->type . '</td>';
            echo '<td><span class="badge badge-info">' . $job->status . '</span></td>';
            echo '<td><div class="progress"><div class="progress-bar" style="width: ' . $progress . '%">' . $progress . '%</div></div></td>';
            echo '<td>' . userdate($job->created_at) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<div class="alert alert-info">' . get_string('noactivejobs', 'local_manireports') . '</div>';
    }

} elseif ($action === 'history') {
    // Completed Jobs
    echo '<h3>' . get_string('jobhistory', 'local_manireports') . '</h3>';
    
    $jobs = $DB->get_records_select('manireports_cloud_jobs', "status IN ('completed', 'failed', 'partial_failure')", null, 'created_at DESC', '*', 0, 50);
    
    if ($jobs) {
        echo '<table class="table table-striped">';
        echo '<thead><tr><th>ID</th><th>Type</th><th>Status</th><th>Sent/Total</th><th>Completed</th><th>Actions</th></tr></thead>';
        echo '<tbody>';
        foreach ($jobs as $job) {
            $status_class = $job->status === 'completed' ? 'success' : 'danger';
            echo '<tr>';
            echo '<td>' . $job->id . '</td>';
            echo '<td>' . $job->type . '</td>';
            echo '<td><span class="badge badge-' . $status_class . '">' . $job->status . '</span></td>';
            echo '<td>' . $job->emails_sent . ' / ' . $job->email_count . '</td>';
            echo '<td>' . ($job->completed_at ? userdate($job->completed_at) : '-') . '</td>';
            echo '<td><a href="#" class="btn btn-sm btn-secondary">Details</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<div class="alert alert-info">' . get_string('nohistory', 'local_manireports') . '</div>';
    }

} elseif ($action === 'settings') {
    // Company Settings
    echo '<h3>' . get_string('companysettings', 'local_manireports') . '</h3>';
    
    // Get all companies (assuming IOMAD)
    $companies = $DB->get_records('company', null, 'name ASC');
    
    if (!$companies) {
        echo '<div class="alert alert-warning">No companies found. Is IOMAD installed?</div>';
    } else {
        // Select Company Form
        $selected_company = $companyid ? $companies[$companyid] : reset($companies);
        $companyid = $selected_company->id;
        
        echo '<form method="get" class="form-inline mb-3">';
        echo '<input type="hidden" name="action" value="settings">';
        echo '<label class="mr-2">Select Company:</label>';
        echo '<select name="companyid" class="form-control mr-2" onchange="this.form.submit()">';
        foreach ($companies as $comp) {
            $selected = ($comp->id == $companyid) ? 'selected' : '';
            echo '<option value="' . $comp->id . '" ' . $selected . '>' . $comp->name . '</option>';
        }
        echo '</select>';
        echo '</form>';
        
        // Settings Form for Selected Company
        $settings = $DB->get_record('manireports_cloud_company_settings', ['company_id' => $companyid]);
        
        echo '<div class="card">';
        echo '<div class="card-body">';
        echo '<h5 class="card-title">Settings for ' . $selected_company->name . '</h5>';
        
        echo '<form method="post" action="">';
        echo '<input type="hidden" name="action" value="savesettings">';
        echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
        echo '<input type="hidden" name="company_id" value="' . $companyid . '">';
        
        // Enabled
        $enabled = $settings ? $settings->enabled : 0;
        echo '<div class="form-group form-check">';
        echo '<input type="checkbox" class="form-check-input" name="enabled" value="1" ' . ($enabled ? 'checked' : '') . '>';
        echo '<label class="form-check-label">Enable Cloud Offload</label>';
        echo '</div>';
        
        // Provider
        $provider = $settings ? $settings->provider : 'aws';
        echo '<div class="form-group">';
        echo '<label>Provider</label>';
        echo '<select name="provider" class="form-control">';
        echo '<option value="aws" ' . ($provider == 'aws' ? 'selected' : '') . '>AWS (SQS + SES)</option>';
        echo '<option value="cloudflare" ' . ($provider == 'cloudflare' ? 'selected' : '') . '>Cloudflare (Workers)</option>';
        echo '</select>';
        echo '</div>';
        
        // AWS Settings
        echo '<div id="aws-settings">';
        echo '<h6>AWS Configuration</h6>';
        echo '<div class="form-group"><label>Access Key</label><input type="text" name="aws_access_key" class="form-control" value="' . ($settings->aws_access_key ?? '') . '"></div>';
        echo '<div class="form-group"><label>Secret Key</label><input type="password" name="aws_secret_key" class="form-control" value="' . ($settings->aws_secret_key ?? '') . '"></div>';
        echo '<div class="form-group"><label>Region</label><input type="text" name="aws_region" class="form-control" value="' . ($settings->aws_region ?? 'us-east-1') . '"></div>';
        echo '<div class="form-group"><label>SQS Queue URL</label><input type="text" name="sqs_queue_url" class="form-control" value="' . ($settings->sqs_queue_url ?? '') . '"></div>';
        echo '<div class="form-group"><label>SES Sender Email</label><input type="text" name="ses_sender_email" class="form-control" value="' . ($settings->ses_sender_email ?? '') . '"></div>';
        echo '</div>';
        
        echo '<button type="submit" class="btn btn-primary">Save Settings</button>';
        echo '</form>';
        echo '</div>';
        echo '</div>';
    }
}

echo $OUTPUT->footer();
