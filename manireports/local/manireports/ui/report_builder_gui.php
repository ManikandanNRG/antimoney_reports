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
 * GUI Report Builder interface
 *
 * @package    local_manireports
 * @copyright  2024 ManiReports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_manireports\api\query_builder;
use local_manireports\api\report_builder;

require_login();
require_capability('local/manireports:customreports', context_system::instance());

$reportid = optional_param('id', 0, PARAM_INT);
$action = optional_param('action', 'edit', PARAM_ALPHA);

$PAGE->set_url(new moodle_url('/local/manireports/ui/report_builder_gui.php', ['id' => $reportid]));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('guireportbuilder', 'local_manireports'));
$PAGE->set_heading(get_string('guireportbuilder', 'local_manireports'));
$PAGE->set_pagelayout('admin');

// Load existing report if editing
$report = null;
$config = null;
if ($reportid > 0) {
    $report = $DB->get_record('manireports_customreports', ['id' => $reportid], '*', MUST_EXIST);
    if ($report->type !== 'gui') {
        throw new moodle_exception('invalidreporttype', 'local_manireports');
    }
    $config = json_decode($report->configjson);
}

// Handle form submission
if ($action === 'save' && confirm_sesskey()) {
    $name = required_param('name', PARAM_TEXT);
    $description = optional_param('description', '', PARAM_TEXT);
    $configjson = required_param('config', PARAM_RAW);
    
    // Validate and parse config
    $config = json_decode($configjson);
    if (!$config) {
        throw new moodle_exception('invalidconfig', 'local_manireports');
    }
    
    // Build SQL to validate
    try {
        $result = query_builder::build_sql_from_config($config);
        $sql = $result['sql'];
    } catch (Exception $e) {
        throw new moodle_exception('invalidconfig', 'local_manireports', '', $e->getMessage());
    }
    
    // Save or update report
    $record = new stdClass();
    $record->name = $name;
    $record->description = $description;
    $record->type = 'gui';
    $record->sqlquery = $sql;
    $record->configjson = $configjson;
    $record->timemodified = time();
    
    if ($reportid > 0) {
        $record->id = $reportid;
        $DB->update_record('manireports_customreports', $record);
    } else {
        $record->createdby = $USER->id;
        $record->timecreated = time();
        $reportid = $DB->insert_record('manireports_customreports', $record);
    }
    
    redirect(new moodle_url('/local/manireports/ui/custom_reports.php'), 
             get_string('reportsaved', 'local_manireports'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Get available tables and metadata
$tables = query_builder::get_allowed_tables();
$aggregations = query_builder::get_aggregation_functions();
$jointypes = query_builder::get_join_types();

echo $OUTPUT->header();

// Include AMD module for GUI builder
$PAGE->requires->js_call_amd('local_manireports/report_builder_gui', 'init', [
    'reportid' => $reportid,
    'config' => $config ? json_encode($config) : '{}',
    'tables' => json_encode(array_values($tables)),
]);

?>

<div class="manireports-gui-builder">
    <form id="gui-builder-form" method="post" action="<?php echo $PAGE->url; ?>">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="config" id="config-json" value="">
        
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="report-name"><?php echo get_string('reportname', 'local_manireports'); ?></label>
                <input type="text" class="form-control" id="report-name" name="name" 
                       value="<?php echo $report ? s($report->name) : ''; ?>" required>
            </div>
            <div class="col-md-6">
                <label for="report-description"><?php echo get_string('description'); ?></label>
                <input type="text" class="form-control" id="report-description" name="description" 
                       value="<?php echo $report ? s($report->description) : ''; ?>">
            </div>
        </div>
        
        <div class="card mb-3">
            <div class="card-header">
                <h5><?php echo get_string('selecttables', 'local_manireports'); ?></h5>
            </div>
            <div class="card-body">
                <div id="table-selector">
                    <select id="add-table" class="form-control mb-2">
                        <option value=""><?php echo get_string('selecttable', 'local_manireports'); ?></option>
                        <?php foreach ($tables as $table): ?>
                            <option value="<?php echo $table['name']; ?>"><?php echo $table['label']; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" id="btn-add-table" class="btn btn-secondary">
                        <?php echo get_string('addtable', 'local_manireports'); ?>
                    </button>
                </div>
                <div id="selected-tables" class="mt-3"></div>
            </div>
        </div>
        
        <div class="card mb-3">
            <div class="card-header">
                <h5><?php echo get_string('selectcolumns', 'local_manireports'); ?></h5>
            </div>
            <div class="card-body">
                <div id="column-selector"></div>
                <div id="selected-columns" class="mt-3"></div>
            </div>
        </div>
        
        <div class="card mb-3">
            <div class="card-header">
                <h5><?php echo get_string('joins', 'local_manireports'); ?></h5>
            </div>
            <div class="card-body">
                <button type="button" id="btn-add-join" class="btn btn-secondary">
                    <?php echo get_string('addjoin', 'local_manireports'); ?>
                </button>
                <div id="joins-list" class="mt-3"></div>
            </div>
        </div>
        
        <div class="card mb-3">
            <div class="card-header">
                <h5><?php echo get_string('filters', 'local_manireports'); ?></h5>
            </div>
            <div class="card-body">
                <button type="button" id="btn-add-filter" class="btn btn-secondary">
                    <?php echo get_string('addfilter', 'local_manireports'); ?>
                </button>
                <div id="filters-list" class="mt-3"></div>
                <div class="mt-2">
                    <label><?php echo get_string('filterlogic', 'local_manireports'); ?></label>
                    <select id="filter-logic" class="form-control" style="width: auto;">
                        <option value="AND">AND</option>
                        <option value="OR">OR</option>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="card mb-3">
            <div class="card-header">
                <h5><?php echo get_string('grouping', 'local_manireports'); ?></h5>
            </div>
            <div class="card-body">
                <button type="button" id="btn-add-groupby" class="btn btn-secondary">
                    <?php echo get_string('addgroupby', 'local_manireports'); ?>
                </button>
                <div id="groupby-list" class="mt-3"></div>
            </div>
        </div>
        
        <div class="card mb-3">
            <div class="card-header">
                <h5><?php echo get_string('sorting', 'local_manireports'); ?></h5>
            </div>
            <div class="card-body">
                <button type="button" id="btn-add-orderby" class="btn btn-secondary">
                    <?php echo get_string('addorderby', 'local_manireports'); ?>
                </button>
                <div id="orderby-list" class="mt-3"></div>
            </div>
        </div>
        
        <div class="card mb-3">
            <div class="card-header">
                <h5><?php echo get_string('sqlpreview', 'local_manireports'); ?></h5>
            </div>
            <div class="card-body">
                <pre id="sql-preview" class="bg-light p-3" style="max-height: 300px; overflow-y: auto;"></pre>
            </div>
        </div>
        
        <div class="form-group">
            <button type="submit" class="btn btn-primary">
                <?php echo get_string('savereport', 'local_manireports'); ?>
            </button>
            <a href="<?php echo new moodle_url('/local/manireports/ui/custom_reports.php'); ?>" class="btn btn-secondary">
                <?php echo get_string('cancel'); ?>
            </a>
        </div>
    </form>
</div>

<?php

echo $OUTPUT->footer();
