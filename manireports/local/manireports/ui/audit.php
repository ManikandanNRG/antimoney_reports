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
 * Audit log viewer for ManiReports.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_manireports\api\audit_logger;

require_login();

$context = context_system::instance();
$PAGE->set_context($context);

// Check capability - only admins can view audit logs.
if (!has_capability('local/manireports:viewadmindashboard', $context)) {
    throw new moodle_exception('error:nopermission', 'local_manireports');
}

// Get parameters.
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 50, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$objecttype = optional_param('objecttype', '', PARAM_ALPHA);
$datefrom = optional_param('datefrom', 0, PARAM_INT);
$dateto = optional_param('dateto', 0, PARAM_INT);

// Build filters.
$filters = array();
if ($userid) {
    $filters['userid'] = $userid;
}
if ($action) {
    $filters['action'] = $action;
}
if ($objecttype) {
    $filters['objecttype'] = $objecttype;
}
if ($datefrom) {
    $filters['datefrom'] = $datefrom;
}
if ($dateto) {
    $filters['dateto'] = $dateto;
}

// Set up the page.
$PAGE->set_url(new moodle_url('/local/manireports/ui/audit.php', array_merge(array('page' => $page), $filters)));
$PAGE->set_title(get_string('auditlog', 'local_manireports'));
$PAGE->set_heading(get_string('auditlog', 'local_manireports'));
$PAGE->set_pagelayout('standard');

// Get audit logs.
$result = audit_logger::get_logs($filters, $page, $perpage);

// Output header.
echo $OUTPUT->header();

// Display page heading.
echo html_writer::tag('h2', get_string('auditlogs', 'local_manireports'));

// Display filters.
echo html_writer::start_div('manireports-filters card mb-3');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', get_string('filters', 'local_manireports'), array('class' => 'card-title'));

echo html_writer::start_tag('form', array('method' => 'get', 'action' => $PAGE->url->out(false)));

echo html_writer::start_div('row');

// Action filter.
echo html_writer::start_div('col-md-2 mb-2');
echo html_writer::tag('label', get_string('action', 'local_manireports'));
$actions = array(
    '' => get_string('all'),
    'create' => 'Create',
    'update' => 'Update',
    'delete' => 'Delete',
    'execute' => 'Execute',
    'export' => 'Export',
    'auth_failure' => 'Auth Failure',
    'config_change' => 'Config Change'
);
echo html_writer::select($actions, 'action', $action, false, array('class' => 'form-control'));
echo html_writer::end_div();

// Object type filter.
echo html_writer::start_div('col-md-2 mb-2');
echo html_writer::tag('label', get_string('objecttype', 'local_manireports'));
$objecttypes = array(
    '' => get_string('all'),
    'report' => 'Report',
    'schedule' => 'Schedule',
    'dashboard' => 'Dashboard',
    'security' => 'Security',
    'settings' => 'Settings'
);
echo html_writer::select($objecttypes, 'objecttype', $objecttype, false, array('class' => 'form-control'));
echo html_writer::end_div();

// Date from filter.
echo html_writer::start_div('col-md-2 mb-2');
echo html_writer::tag('label', get_string('datefrom', 'local_manireports'));
$value = $datefrom ? userdate($datefrom, '%Y-%m-%d') : '';
echo html_writer::empty_tag('input', array('type' => 'date', 'name' => 'datefrom', 'value' => $value, 'class' => 'form-control'));
echo html_writer::end_div();

// Date to filter.
echo html_writer::start_div('col-md-2 mb-2');
echo html_writer::tag('label', get_string('dateto', 'local_manireports'));
$value = $dateto ? userdate($dateto, '%Y-%m-%d') : '';
echo html_writer::empty_tag('input', array('type' => 'date', 'name' => 'dateto', 'value' => $value, 'class' => 'form-control'));
echo html_writer::end_div();

echo html_writer::end_div();

echo html_writer::tag('button', get_string('apply', 'moodle'), array('type' => 'submit', 'class' => 'btn btn-primary mt-2'));
echo html_writer::end_tag('form');

echo html_writer::end_div();
echo html_writer::end_div();

// Display results.
if (empty($result['data'])) {
    echo $OUTPUT->notification(get_string('noauditlogs', 'local_manireports'), 'info');
} else {
    // Build table.
    $table = new html_table();
    $table->attributes['class'] = 'generaltable table table-striped table-sm';
    
    // Table headers.
    $table->head = array(
        get_string('timecreated', 'moodle'),
        get_string('user'),
        get_string('action', 'local_manireports'),
        get_string('objecttype', 'local_manireports'),
        get_string('objectid', 'local_manireports'),
        get_string('details', 'local_manireports'),
        get_string('ipaddress', 'local_manireports')
    );
    
    // Table rows.
    foreach ($result['data'] as $log) {
        $row = array();
        
        // Time.
        $row[] = userdate($log->timecreated, get_string('strftimedatetime', 'langconfig'));
        
        // User.
        $user = $DB->get_record('user', array('id' => $log->userid), 'id, firstname, lastname');
        $row[] = $user ? fullname($user) : 'Unknown';
        
        // Action.
        $row[] = $log->action;
        
        // Object type.
        $row[] = $log->objecttype;
        
        // Object ID.
        $row[] = $log->objectid;
        
        // Details (truncate if too long).
        $details = $log->details;
        if (strlen($details) > 100) {
            $details = substr($details, 0, 100) . '...';
        }
        $row[] = html_writer::tag('small', s($details));
        
        // IP address.
        $row[] = $log->ipaddress;
        
        $table->data[] = $row;
    }
    
    echo html_writer::table($table);
    
    // Pagination.
    if ($result['total'] > $perpage) {
        $baseurl = new moodle_url('/local/manireports/ui/audit.php', $filters);
        echo $OUTPUT->paging_bar($result['total'], $page, $perpage, $baseurl);
    }
    
    // Display total count.
    echo html_writer::tag('p', get_string('totalrecords', 'local_manireports', $result['total']), array('class' => 'text-muted mt-2'));
}

// Output footer.
echo $OUTPUT->footer();
