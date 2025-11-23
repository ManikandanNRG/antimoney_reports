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
 * Plugin upgrade steps are defined here.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute local_manireports upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_manireports_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2024111701) {
        // Fix schedules table structure to match code expectations.
        $table = new xmldb_table('manireports_schedules');

        // Add userid field if it doesn't exist.
        $field = new xmldb_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '2', 'id');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add reporttype field if it doesn't exist.
        $field = new xmldb_field('reporttype', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, 'course_completion', 'name');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add parameters field if it doesn't exist.
        $field = new xmldb_field('parameters', XMLDB_TYPE_TEXT, null, null, null, null, null, 'format');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add enabled field if it doesn't exist.
        $field = new xmldb_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'parameters');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add lastrun field if it doesn't exist.
        $field = new xmldb_field('lastrun', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'enabled');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add nextrun field if it doesn't exist.
        $field = new xmldb_field('nextrun', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'lastrun');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add failcount field if it doesn't exist.
        $field = new xmldb_field('failcount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'nextrun');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2024111701, 'local', 'manireports');
    }

    if ($oldversion < 2024111702) {
        // Add reportid field to schedules table to support custom reports.
        $table = new xmldb_table('manireports_schedules');
        
        $field = new xmldb_field('reportid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'reporttype');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add foreign key for reportid.
        $key = new xmldb_key('reportid', XMLDB_KEY_FOREIGN, array('reportid'), 'manireports_customreports', array('id'));
        $dbman->add_key($table, $key);

        // Add index for reportid.
        $index = new xmldb_index('reportid', XMLDB_INDEX_NOTUNIQUE, array('reportid'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2024111702, 'local', 'manireports');
    }

    // Add at-risk acknowledgment table.
    if ($oldversion < 2024111703) {
        $table = new xmldb_table('manireports_atrisk_ack');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('acknowledgedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('note', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timeacknowledged', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('userid', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, array('courseid'), 'course', array('id'));
        $table->add_key('acknowledgedby', XMLDB_KEY_FOREIGN, array('acknowledgedby'), 'user', array('id'));

        $table->add_index('userid_courseid', XMLDB_INDEX_NOTUNIQUE, array('userid', 'courseid'));
        $table->add_index('timeacknowledged', XMLDB_INDEX_NOTUNIQUE, array('timeacknowledged'));

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2024111703, 'local', 'manireports');
    }

    // Add failed jobs table for error handling and retry management.
    if ($oldversion < 2024111704) {
        $table = new xmldb_table('manireports_failed_jobs');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('taskname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('error', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('stacktrace', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('context', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timefailed', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('retrycount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('lastretry', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        $table->add_index('taskname', XMLDB_INDEX_NOTUNIQUE, array('taskname'));
        $table->add_index('timefailed', XMLDB_INDEX_NOTUNIQUE, array('timefailed'));

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2024111704, 'local', 'manireports');
    }

    return true;
}

/**
 * Execute local_manireports upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_manireports_upgrade_cloud_offload($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2024111705) {
        // Add cloud offload fields to schedules table.
        $table = new xmldb_table('manireports_schedules');
        
        $fields = [
            new xmldb_field('cloud_preference', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'auto', 'timemodified'),
            new xmldb_field('action_type', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'email', 'cloud_preference'),
            new xmldb_field('custom_interval', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'action_type'),
            new xmldb_field('suppresstarget', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'custom_interval'),
            new xmldb_field('suppress_course_completion', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'suppresstarget')
        ];

        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // Define table manireports_cloud_jobs.
        $table = new xmldb_table('manireports_cloud_jobs');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('type', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('email_count', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('emails_sent', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('emails_failed', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('company_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('created_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('started_at', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('completed_at', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('error_log', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('company_id', XMLDB_KEY_FOREIGN, array('company_id'), 'company', array('id'));
    }
