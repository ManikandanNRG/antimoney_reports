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
 * Dashboard builder UI for creating and editing custom dashboards.
 *
 * @package    local_manireports
 * @copyright  2025 ManiReports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();

$dashboardid = optional_param('id', 0, PARAM_INT);
$action = optional_param('action', 'edit', PARAM_ALPHA);

$context = context_system::instance();
require_capability('local/manireports:managereports', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/manireports/ui/dashboard_builder.php', ['id' => $dashboardid]));
$PAGE->set_pagelayout('admin');

$dashboardmanager = new \local_manireports\api\dashboard_manager();
$widgetmanager = new \local_manireports\api\widget_manager();

// Handle form submissions.
if ($action === 'save' && confirm_sesskey()) {
    $name = required_param('name', PARAM_TEXT);
    $description = optional_param('description', '', PARAM_TEXT);
    $scope = required_param('scope', PARAM_ALPHA);
    $companyid = optional_param('companyid', 0, PARAM_INT);
    $layoutjson = required_param('layout', PARAM_RAW);

    try {
        if ($dashboardid) {
            // Update existing dashboard.
            $data = new stdClass();
            $data->name = $name;
            $data->description = $description;
            $data->scope = $scope;
            $data->companyid = $companyid;
            $dashboardmanager->update_dashboard($dashboardid, $data);
            $dashboardmanager->save_dashboard_layout($dashboardid, $layoutjson);
            
            redirect(new moodle_url('/local/manireports/ui/dashboard_builder.php', ['id' => $dashboardid]),
                get_string('dashboardupdated', 'local_manireports'), null, \core\output\notification::NOTIFY_SUCCESS);
        } else {
            // Create new dashboard.
            $dashboard = new stdClass();
            $dashboard->name = $name;
            $dashboard->description = $description;
            $dashboard->scope = $scope;
            $dashboard->companyid = $companyid;
            $dashboard->layoutjson = $layoutjson;
            $newid = $dashboardmanager->create_dashboard($dashboard);
            
            redirect(new moodle_url('/local/manireports/ui/dashboard_builder.php', ['id' => $newid]),
                get_string('dashboardcreated', 'local_manireports'), null, \core\output\notification::NOTIFY_SUCCESS);
        }
    } catch (Exception $e) {
        \core\notification::error($e->getMessage());
    }
}

// Load dashboard if editing.
$dashboard = null;
$widgets = [];
if ($dashboardid) {
    try {
        $dashboard = $dashboardmanager->get_dashboard($dashboardid);
        $widgets = $widgetmanager->get_dashboard_widgets($dashboardid);
        $PAGE->set_title(get_string('editdashboard', 'local_manireports') . ': ' . $dashboard->name);
        $PAGE->set_heading(get_string('editdashboard', 'local_manireports'));
    } catch (Exception $e) {
        print_error('dashboardnotfound', 'local_manireports');
    }
} else {
    $PAGE->set_title(get_string('createdashboard', 'local_manireports'));
    $PAGE->set_heading(get_string('createdashboard', 'local_manireports'));
}

// Get widget types for palette.
$widgettypes = $widgetmanager->get_widget_types();

// Get IOMAD companies if available.
$companies = [];
$iomadfilter = new \local_manireports\api\iomad_filter();
if ($iomadfilter->is_iomad_installed()) {
    $companies = $iomadfilter->get_company_selector_options($USER->id);
}

echo $OUTPUT->header();

// Dashboard metadata form.
?>
<div class="dashboard-builder-container">
    <form id="dashboard-metadata-form" method="post" action="<?php echo $PAGE->url; ?>">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="layout" id="layout-json" value="">
        
        <div class="card mb-3">
            <div class="card-body">
                <h3><?php echo get_string('dashboardsettings', 'local_manireports'); ?></h3>
                
                <div class="form-group">
                    <label for="dashboard-name"><?php echo get_string('name'); ?></label>
                    <input type="text" class="form-control" id="dashboard-name" name="name" 
                           value="<?php echo $dashboard ? s($dashboard->name) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="dashboard-description"><?php echo get_string('description'); ?></label>
                    <textarea class="form-control" id="dashboard-description" name="description" rows="3"><?php 
                        echo $dashboard ? s($dashboard->description) : ''; 
                    ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="dashboard-scope"><?php echo get_string('scope', 'local_manireports'); ?></label>
                    <select class="form-control" id="dashboard-scope" name="scope" required>
                        <option value="personal" <?php echo ($dashboard && $dashboard->scope === 'personal') ? 'selected' : ''; ?>>
                            <?php echo get_string('personal', 'local_manireports'); ?>
                        </option>
                        <option value="global" <?php echo ($dashboard && $dashboard->scope === 'global') ? 'selected' : ''; ?>>
                            <?php echo get_string('global', 'local_manireports'); ?>
                        </option>
                        <?php if (!empty($companies)): ?>
                        <option value="company" <?php echo ($dashboard && $dashboard->scope === 'company') ? 'selected' : ''; ?>>
                            <?php echo get_string('company', 'local_manireports'); ?>
                        </option>
                        <?php endif; ?>
                    </select>
                </div>
                
                <?php if (!empty($companies)): ?>
                <div class="form-group" id="company-selector" style="display: <?php echo ($dashboard && $dashboard->scope === 'company') ? 'block' : 'none'; ?>;">
                    <label for="dashboard-company"><?php echo get_string('company', 'local_manireports'); ?></label>
                    <select class="form-control" id="dashboard-company" name="companyid">
                        <option value="0"><?php echo get_string('selectcompany', 'local_manireports'); ?></option>
                        <?php foreach ($companies as $id => $name): ?>
                        <option value="<?php echo $id; ?>" <?php echo ($dashboard && $dashboard->companyid == $id) ? 'selected' : ''; ?>>
                            <?php echo s($name); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <?php echo get_string('savedashboard', 'local_manireports'); ?>
                    </button>
                    <a href="<?php echo new moodle_url('/local/manireports/ui/dashboard.php'); ?>" class="btn btn-secondary">
                        <?php echo get_string('cancel'); ?>
                    </a>
                </div>
            </div>
        </div>
    </form>
    
    <div class="row">
        <div class="col-md-3">
            <div class="card">
                <div class="card-header">
                    <h4><?php echo get_string('widgetpalette', 'local_manireports'); ?></h4>
                </div>
                <div class="card-body">
                    <div id="widget-palette">
                        <?php foreach ($widgettypes as $type => $typedef): ?>
                        <div class="widget-palette-item" data-widget-type="<?php echo $type; ?>">
                            <div class="widget-icon">
                                <?php
                                $icons = [
                                    'kpi' => 'i/dashboard',
                                    'line' => 'i/chart',
                                    'bar' => 'i/chart',
                                    'pie' => 'i/chart',
                                    'table' => 'i/report'
                                ];
                                echo $OUTPUT->pix_icon($icons[$type] ?? 'i/dashboard', '');
                                ?>
                            </div>
                            <div class="widget-info">
                                <strong><?php echo $typedef['name']; ?></strong>
                                <small class="text-muted d-block"><?php echo $typedef['description']; ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-9">
            <div class="card">
                <div class="card-header">
                    <h4><?php echo get_string('dashboardlayout', 'local_manireports'); ?></h4>
                </div>
                <div class="card-body">
                    <div id="dashboard-grid" class="dashboard-grid">
                        <?php if ($dashboard && !empty($widgets)): ?>
                            <?php foreach ($widgets as $widget): ?>
                            <div class="grid-stack-item" data-widget-id="<?php echo $widget->id; ?>" 
                                 data-widget-type="<?php echo $widget->widgettype; ?>">
                                <div class="grid-stack-item-content">
                                    <div class="widget-header">
                                        <span class="widget-title"><?php echo s($widget->title); ?></span>
                                        <div class="widget-actions">
                                            <button class="btn btn-sm btn-link widget-edit" title="<?php echo get_string('edit'); ?>">
                                                <?php echo $OUTPUT->pix_icon('t/edit', get_string('edit')); ?>
                                            </button>
                                            <button class="btn btn-sm btn-link widget-delete" title="<?php echo get_string('delete'); ?>">
                                                <?php echo $OUTPUT->pix_icon('t/delete', get_string('delete')); ?>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="widget-body">
                                        <p class="text-muted"><?php echo $widget->widgettype; ?> widget</p>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                        <div class="empty-state">
                            <p class="text-muted"><?php echo get_string('dragwidgetshere', 'local_manireports'); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Widget Configuration Modal -->
<div class="modal fade" id="widget-config-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo get_string('configurewidget', 'local_manireports'); ?></h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="widget-config-form">
                    <input type="hidden" id="widget-id" name="widgetid">
                    <input type="hidden" id="widget-type" name="widgettype">
                    
                    <div class="form-group">
                        <label for="widget-title"><?php echo get_string('title', 'local_manireports'); ?></label>
                        <input type="text" class="form-control" id="widget-title" name="title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="widget-metric"><?php echo get_string('metric', 'local_manireports'); ?></label>
                        <select class="form-control" id="widget-metric" name="metric">
                            <option value=""><?php echo get_string('selectmetric', 'local_manireports'); ?></option>
                        </select>
                    </div>
                    
                    <div id="widget-config-fields">
                        <!-- Dynamic fields based on widget type -->
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <?php echo get_string('cancel'); ?>
                </button>
                <button type="button" class="btn btn-primary" id="save-widget-config">
                    <?php echo get_string('save'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.dashboard-builder-container {
    padding: 20px;
}

.widget-palette-item {
    display: flex;
    align-items: center;
    padding: 10px;
    margin-bottom: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    cursor: move;
    background: #fff;
}

.widget-palette-item:hover {
    background: #f5f5f5;
    border-color: #0f6cbf;
}

.widget-icon {
    margin-right: 10px;
}

.widget-info {
    flex: 1;
}

.dashboard-grid {
    min-height: 400px;
    border: 2px dashed #ddd;
    border-radius: 4px;
    padding: 20px;
    background: #fafafa;
}

.grid-stack-item {
    border: 1px solid #ddd;
    background: #fff;
    border-radius: 4px;
}

.grid-stack-item-content {
    padding: 10px;
}

.widget-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.widget-title {
    font-weight: bold;
}

.widget-actions button {
    padding: 2px 5px;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
}
</style>

<?php
// Initialize JavaScript module.
$PAGE->requires->js_call_amd('local_manireports/dashboard_builder', 'init', [
    'dashboardid' => $dashboardid,
    'widgets' => array_values($widgets),
    'widgettypes' => $widgettypes
]);

echo $OUTPUT->footer();
