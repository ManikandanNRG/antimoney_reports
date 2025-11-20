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
 * Plugin administration pages are defined here.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_manireports_settings', get_string('settings'));

    // General settings heading.
    $settings->add(new admin_setting_heading(
        'local_manireports/generalsettings',
        get_string('generalsettings', 'local_manireports'),
        ''
    ));

    // Enable/disable time tracking.
    $settings->add(new admin_setting_configcheckbox(
        'local_manireports/enabletimetracking',
        get_string('enabletimetracking', 'local_manireports'),
        get_string('enabletimetracking_desc', 'local_manireports'),
        1
    ));

    // Time tracking heartbeat interval (seconds).
    $settings->add(new admin_setting_configtext(
        'local_manireports/heartbeatinterval',
        get_string('heartbeatinterval', 'local_manireports'),
        get_string('heartbeatinterval_desc', 'local_manireports'),
        25,
        PARAM_INT
    ));

    // Session timeout (minutes).
    $settings->add(new admin_setting_configtext(
        'local_manireports/sessiontimeout',
        get_string('sessiontimeout', 'local_manireports'),
        get_string('sessiontimeout_desc', 'local_manireports'),
        10,
        PARAM_INT
    ));

    // Cache settings heading.
    $settings->add(new admin_setting_heading(
        'local_manireports/cachesettings',
        get_string('cachesettings', 'local_manireports'),
        ''
    ));

    // Cache TTL for dashboard widgets (seconds).
    $settings->add(new admin_setting_configtext(
        'local_manireports/cachettl_dashboard',
        get_string('cachettl_dashboard', 'local_manireports'),
        get_string('cachettl_dashboard_desc', 'local_manireports'),
        3600,
        PARAM_INT
    ));

    // Cache TTL for trend reports (seconds).
    $settings->add(new admin_setting_configtext(
        'local_manireports/cachettl_trends',
        get_string('cachettl_trends', 'local_manireports'),
        get_string('cachettl_trends_desc', 'local_manireports'),
        21600,
        PARAM_INT
    ));

    // Cache TTL for historical reports (seconds).
    $settings->add(new admin_setting_configtext(
        'local_manireports/cachettl_historical',
        get_string('cachettl_historical', 'local_manireports'),
        get_string('cachettl_historical_desc', 'local_manireports'),
        86400,
        PARAM_INT
    ));

    // Report execution settings heading.
    $settings->add(new admin_setting_heading(
        'local_manireports/reportsettings',
        get_string('reportsettings', 'local_manireports'),
        ''
    ));

    // Query timeout (seconds).
    $settings->add(new admin_setting_configtext(
        'local_manireports/querytimeout',
        get_string('querytimeout', 'local_manireports'),
        get_string('querytimeout_desc', 'local_manireports'),
        60,
        PARAM_INT
    ));

    // Maximum concurrent report executions.
    $settings->add(new admin_setting_configtext(
        'local_manireports/maxconcurrentreports',
        get_string('maxconcurrentreports', 'local_manireports'),
        get_string('maxconcurrentreports_desc', 'local_manireports'),
        5,
        PARAM_INT
    ));

    // Data retention settings heading.
    $settings->add(new admin_setting_heading(
        'local_manireports/retentionsettings',
        get_string('retentionsettings', 'local_manireports'),
        ''
    ));

    // Audit log retention (days).
    $settings->add(new admin_setting_configtext(
        'local_manireports/auditlogretention',
        get_string('auditlogretention', 'local_manireports'),
        get_string('auditlogretention_desc', 'local_manireports'),
        365,
        PARAM_INT
    ));

    // Report run history retention (days).
    $settings->add(new admin_setting_configtext(
        'local_manireports/reportrunretention',
        get_string('reportrunretention', 'local_manireports'),
        get_string('reportrunretention_desc', 'local_manireports'),
        90,
        PARAM_INT
    ));

    // At-risk learner settings heading.
    $settings->add(new admin_setting_heading(
        'local_manireports/atrisksettings',
        get_string('atrisksettings', 'local_manireports'),
        ''
    ));

    // Minimum time spent threshold (hours).
    $settings->add(new admin_setting_configtext(
        'local_manireports/atrisk_mintime',
        get_string('atrisk_mintime', 'local_manireports'),
        get_string('atrisk_mintime_desc', 'local_manireports'),
        2,
        PARAM_INT
    ));

    // Maximum days since login threshold.
    $settings->add(new admin_setting_configtext(
        'local_manireports/atrisk_maxdays',
        get_string('atrisk_maxdays', 'local_manireports'),
        get_string('atrisk_maxdays_desc', 'local_manireports'),
        7,
        PARAM_INT
    ));

    // Minimum completion percentage threshold.
    $settings->add(new admin_setting_configtext(
        'local_manireports/atrisk_mincompletion',
        get_string('atrisk_mincompletion', 'local_manireports'),
        get_string('atrisk_mincompletion_desc', 'local_manireports'),
        30,
        PARAM_INT
    ));

    // Performance optimization settings heading.
    $settings->add(new admin_setting_heading(
        'local_manireports/performancesettings',
        get_string('performancesettings', 'local_manireports'),
        ''
    ));

    // Maximum concurrent report executions.
    $settings->add(new admin_setting_configtext(
        'local_manireports/max_concurrent_reports',
        get_string('max_concurrent_reports', 'local_manireports'),
        get_string('max_concurrent_reports_desc', 'local_manireports'),
        5,
        PARAM_INT
    ));

    // Default page size for reports.
    $settings->add(new admin_setting_configtext(
        'local_manireports/default_page_size',
        get_string('default_page_size', 'local_manireports'),
        get_string('default_page_size_desc', 'local_manireports'),
        100,
        PARAM_INT
    ));

    // Query timeout in seconds.
    $settings->add(new admin_setting_configtext(
        'local_manireports/query_timeout',
        get_string('query_timeout', 'local_manireports'),
        get_string('query_timeout_desc', 'local_manireports'),
        30,
        PARAM_INT
    ));

    // xAPI Integration settings heading.
    $settings->add(new admin_setting_heading(
        'local_manireports/xapisettings',
        get_string('xapisettings', 'local_manireports'),
        ''
    ));

    // Enable/disable xAPI integration.
    $settings->add(new admin_setting_configcheckbox(
        'local_manireports/enable_xapi_integration',
        get_string('enable_xapi_integration', 'local_manireports'),
        get_string('enable_xapi_integration_desc', 'local_manireports'),
        0
    ));

    // xAPI score weight in engagement calculation.
    $settings->add(new admin_setting_configtext(
        'local_manireports/xapi_score_weight',
        get_string('xapi_score_weight', 'local_manireports'),
        get_string('xapi_score_weight_desc', 'local_manireports'),
        0.3,
        PARAM_FLOAT
    ));

    // Add external pages for ManiReports management.
    $ADMIN->add('localplugins', new admin_category('manireportsmanage', get_string('pluginname', 'local_manireports')));
    
    // Add settings page to the ManiReports category.
    $ADMIN->add('manireportsmanage', $settings);

    // Dashboard link.
    $ADMIN->add('manireportsmanage', new admin_externalpage(
        'manireportsdashboard',
        get_string('dashboard', 'local_manireports'),
        new moodle_url('/local/manireports/ui/dashboard.php'),
        'local/manireports:viewadmindashboard'
    ));

    // Custom reports link.
    $ADMIN->add('manireportsmanage', new admin_externalpage(
        'manireportscustom',
        get_string('customreports', 'local_manireports'),
        new moodle_url('/local/manireports/ui/custom_reports.php'),
        'local/manireports:customreports'
    ));

    // Schedules link.
    $ADMIN->add('manireportsmanage', new admin_externalpage(
        'manireportsschedules',
        get_string('schedules', 'local_manireports'),
        new moodle_url('/local/manireports/ui/schedules.php'),
        'local/manireports:schedule'
    ));

    // Audit logs link.
    $ADMIN->add('manireportsmanage', new admin_externalpage(
        'manireportsaudit',
        get_string('auditlog', 'local_manireports'),
        new moodle_url('/local/manireports/ui/audit.php'),
        'local/manireports:viewadmindashboard'
    ));

    // At-risk learners link.
    $ADMIN->add('manireportsmanage', new admin_externalpage(
        'manireportsatrisk',
        get_string('atriskdashboard', 'local_manireports'),
        new moodle_url('/local/manireports/ui/at_risk.php'),
        'local/manireports:viewmanagerdashboard'
    ));

    // Performance monitoring link.
    $ADMIN->add('manireportsmanage', new admin_externalpage(
        'manireportsperformance',
        get_string('performance', 'local_manireports'),
        new moodle_url('/local/manireports/ui/performance.php'),
        'local/manireports:viewadmindashboard'
    ));

    // Failed jobs link.
    $ADMIN->add('manireportsmanage', new admin_externalpage(
        'manireportsfailedjobs',
        get_string('failedjobs', 'local_manireports'),
        new moodle_url('/local/manireports/ui/failed_jobs.php'),
        'local/manireports:viewadmindashboard'
    ));
}
