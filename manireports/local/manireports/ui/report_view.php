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
 * Report view page for ManiReports.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_manireports\api\logger;

require_login();

$context = context_system::instance();
$PAGE->set_context($context);

// Get parameters - support both custom report ID and prebuilt report type.
$reportid = optional_param('id', 0, PARAM_INT);
$reporttype = optional_param('report', '', PARAM_ALPHANUMEXT);
$type = optional_param('type', '', PARAM_ALPHANUMEXT); // Alternative parameter name for drill-down.
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 25, PARAM_INT);
$drilldown = optional_param('drilldown', 0, PARAM_INT); // Drill-down mode flag.

// Support 'type' parameter as alias for 'report'.
if (empty($reporttype) && !empty($type)) {
    $reporttype = $type;
}

// Must have either report ID or report type.
if ($reportid == 0 && empty($reporttype)) {
    throw new moodle_exception('error:reportnotfound', 'local_manireports');
}

// Get filter parameters.
$courseid = optional_param('courseid', 0, PARAM_INT);
$usersearch = optional_param('usersearch', '', PARAM_TEXT);
$datefrom_str = optional_param('datefrom', '', PARAM_TEXT); // Date string from HTML input
$dateto_str = optional_param('dateto', '', PARAM_TEXT); // Date string from HTML input
$companyid = optional_param('companyid', 0, PARAM_INT);

// Convert date strings to timestamps for query parameters.
$datefrom = 0;
$dateto = 0;
if (!empty($datefrom_str)) {
    $datefrom = strtotime($datefrom_str . ' 00:00:00');
}
if (!empty($dateto_str)) {
    $dateto = strtotime($dateto_str . ' 23:59:59');
}

// Build parameters array.
$params = array();
if ($courseid) {
    $params['courseid'] = $courseid;
}
if (!empty($usersearch)) {
    $params['usersearch'] = $usersearch;
}
if ($datefrom) {
    $params['datefrom'] = $datefrom;
}
if ($dateto) {
    $params['dateto'] = $dateto;
}
if ($companyid) {
    $params['companyid'] = $companyid;
}

// Get drill-down filter parameters (filter_*).
foreach ($_GET as $key => $value) {
    if (strpos($key, 'filter_') === 0) {
        $filterkey = substr($key, 7); // Remove 'filter_' prefix.
        $params[$filterkey] = clean_param($value, PARAM_TEXT);
    }
}

// Check capability.
$hascapability = has_capability('local/manireports:viewadmindashboard', $context) ||
                 has_capability('local/manireports:viewmanagerdashboard', $context) ||
                 has_capability('local/manireports:viewteacherdashboard', $context) ||
                 has_capability('local/manireports:viewstudentdashboard', $context);

if (!$hascapability) {
    throw new moodle_exception('error:nopermission', 'local_manireports');
}

// Handle custom reports (by ID).
if ($reportid > 0) {
    require_capability('local/manireports:customreports', $context);
    
    $builder = new \local_manireports\api\report_builder();
    
    try {
        $result = $builder->execute_report($reportid, $params, $USER->id, $page, $perpage);
        
        // Get report details for display.
        $customreport = $DB->get_record('manireports_customreports', array('id' => $reportid), '*', MUST_EXIST);
        
        // Set up the page.
        $PAGE->set_url(new moodle_url('/local/manireports/ui/report_view.php', array('id' => $reportid)));
        $PAGE->set_title($customreport->name);
        $PAGE->set_heading($customreport->name);
        $PAGE->set_pagelayout('standard');
        
        // Output header.
        echo $OUTPUT->header();
        
        // Display report title and description.
        echo html_writer::tag('h2', format_string($customreport->name));
        if (!empty($customreport->description)) {
            echo html_writer::tag('p', format_text($customreport->description, FORMAT_PLAIN), array('class' => 'lead'));
        }
        
        // Display execution time.
        if (isset($result['executiontime'])) {
            echo html_writer::tag('p', 
                get_string('executiontime', 'local_manireports') . ': ' . $result['executiontime'] . 's',
                array('class' => 'text-muted small')
            );
        }
        
        // Display results table.
        if (empty($result['data'])) {
            echo $OUTPUT->notification(get_string('nodata', 'local_manireports'), 'info');
        } else {
            $table = new html_table();
            $table->head = $result['columns'];
            $table->attributes['class'] = 'generaltable';
            
            foreach ($result['data'] as $row) {
                $table->data[] = array_values((array)$row);
            }
            
            echo html_writer::table($table);
            
            // Display pagination.
            if ($result['total'] > $perpage) {
                echo $OUTPUT->paging_bar($result['total'], $page, $perpage, $PAGE->url);
            }
            
            // Display total records.
            echo html_writer::tag('p', get_string('totalrecords', 'local_manireports', $result['total']), array('class' => 'text-muted'));
        }
        
        // Display export buttons.
        echo html_writer::start_div('mt-3');
        $exporturl = new moodle_url('/local/manireports/ui/export.php', array('id' => $reportid));
        echo html_writer::link($exporturl, get_string('export', 'local_manireports'), array('class' => 'btn btn-secondary'));
        echo html_writer::end_div();
        
        echo $OUTPUT->footer();
        die();
        
    } catch (Exception $e) {
        echo $OUTPUT->header();
        echo $OUTPUT->notification($e->getMessage(), 'error');
        echo $OUTPUT->footer();
        die();
    }
}

// Handle prebuilt reports (by type).
// Create report instance.
$reportclass = "\\local_manireports\\reports\\{$reporttype}";

// Enable debugging to see what's happening.
$debuginfo = array(
    'reporttype' => $reporttype,
    'reportclass' => $reportclass,
    'file_exists' => file_exists(__DIR__ . "/../classes/reports/{$reporttype}.php"),
    'class_exists' => class_exists($reportclass)
);

if (!class_exists($reportclass)) {
    // Log detailed debug info.
    logger::debug('Report class not found: ' . print_r($debuginfo, true));
    
    // Try to manually include the file.
    // First, ensure base_report is loaded (dependency).
    $basereportpath = __DIR__ . "/../classes/reports/base_report.php";
    if (file_exists($basereportpath) && !class_exists('\\local_manireports\\reports\\base_report')) {
        require_once($basereportpath);
        logger::info("Manually included base_report.php");
    }
    
    // Now include the specific report class.
    $filepath = __DIR__ . "/../classes/reports/{$reporttype}.php";
    if (file_exists($filepath)) {
        require_once($filepath);
        logger::info("Manually included {$filepath}");
    }
    
    // Check again after manual include.
    if (!class_exists($reportclass)) {
        logger::error("Class still not found after manual include: {$reportclass}");
        throw new moodle_exception('error:reportnotfound', 'local_manireports', '', 
            "Class: {$reportclass}, File exists: " . ($debuginfo['file_exists'] ? 'yes' : 'no'));
    }
}

$report = new $reportclass($USER->id, $params);

// Check permission for this specific report.
if (!$report->has_permission($USER->id)) {
    throw new moodle_exception('error:nopermission', 'local_manireports');
}

// Execute report.
try {
    $result = $report->execute($page, $perpage);
    
    // Log audit trail (only on first page to avoid excessive logging).
    if ($page == 0) {
        \local_manireports\api\audit_logger::log_report_execute(0, $reporttype, $result['total']);
    }
} catch (Exception $e) {
    throw new moodle_exception('error:unexpectederror', 'local_manireports', '', $e->getMessage());
}

// Set up the page.
$PAGE->set_url(new moodle_url('/local/manireports/ui/report_view.php', array('report' => $reporttype)));
$PAGE->set_title($report->get_name());
$PAGE->set_heading($report->get_name());
$PAGE->set_pagelayout('standard');

// Output header.
echo $OUTPUT->header();

// Add navigation buttons at the top.
echo html_writer::start_div('mb-3');
$dashboardurl = new moodle_url('/local/manireports/ui/dashboard.php');
echo html_writer::link($dashboardurl, '← ' . get_string('backtodashboard', 'local_manireports'), array('class' => 'btn btn-secondary'));
echo html_writer::end_div();

// Display report title and description.
echo html_writer::tag('h2', $report->get_name());
echo html_writer::tag('p', $report->get_description(), array('class' => 'lead'));

// Display drill-down filters if in drill-down mode.
if ($drilldown && !empty($params)) {
    echo html_writer::start_div('alert alert-info manireports-drilldown-filters');
    echo html_writer::tag('strong', get_string('appliedfilters', 'local_manireports') . ': ');
    
    foreach ($params as $key => $value) {
        if (in_array($key, array('courseid', 'userid', 'datefrom', 'dateto', 'companyid'))) {
            continue; // Skip standard filters.
        }
        
        $badge = html_writer::tag('span', ucfirst($key) . ': ' . s($value), array('class' => 'badge badge-primary mr-2'));
        echo $badge;
    }
    
    // Add clear filters button.
    $clearurl = new moodle_url('/local/manireports/ui/report_view.php', array('report' => $reporttype));
    echo html_writer::link($clearurl, get_string('clearfilters', 'local_manireports'), array('class' => 'btn btn-sm btn-secondary ml-2'));
    
    echo html_writer::end_div();
}

// Display filters if available.
$filters = $report->get_filters();
if (!empty($filters)) {
    echo html_writer::start_div('manireports-filters card mb-3');
    echo html_writer::start_div('card-body');
    echo html_writer::tag('h5', get_string('filters', 'local_manireports'), array('class' => 'card-title'));
    
    echo html_writer::start_tag('form', array('method' => 'get', 'action' => $PAGE->url->out(false)));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'report', 'value' => $reporttype));
    
    echo html_writer::start_div('row');
    foreach ($filters as $name => $filter) {
        echo html_writer::start_div('col-md-3 mb-2');
        echo html_writer::tag('label', $filter['label'], array('for' => $name));
        
        if ($filter['type'] === 'select' && isset($filter['options'])) {
            echo html_writer::select($filter['options'], $name, $params[$name] ?? '', array('' => get_string('all')), array('class' => 'form-control'));
        } else if ($filter['type'] === 'date') {
            // Use original date string for display
            $value = '';
            if ($name === 'datefrom' && !empty($datefrom_str)) {
                $value = $datefrom_str;
            } else if ($name === 'dateto' && !empty($dateto_str)) {
                $value = $dateto_str;
            }
            echo html_writer::empty_tag('input', array('type' => 'date', 'name' => $name, 'value' => $value, 'class' => 'form-control'));
        } else if ($filter['type'] === 'course') {
            // Course selector
            $courses = array('' => get_string('all'));
            $allcourses = $DB->get_records_menu('course', array('visible' => 1), 'fullname ASC', 'id, fullname');
            $courses = $courses + $allcourses;
            echo html_writer::select($courses, $name, $params[$name] ?? '', false, array('class' => 'form-control'));
        } else if ($filter['type'] === 'user') {
            // User search field - accepts username or email
            $value = $params[$name] ?? '';
            echo html_writer::empty_tag('input', array(
                'type' => 'text', 
                'name' => $name, 
                'value' => $value, 
                'placeholder' => get_string('usernameoremail', 'local_manireports'),
                'class' => 'form-control'
            ));
        } else if ($filter['type'] === 'quiz' || $filter['type'] === 'scorm') {
            // Quiz/SCORM selector (simplified - shows all)
            $table = $filter['type'];
            $items = array('' => get_string('all'));
            $allitems = $DB->get_records_menu($table, null, 'name ASC', 'id, name', 0, 100);
            $items = $items + $allitems;
            echo html_writer::select($items, $name, $params[$name] ?? '', false, array('class' => 'form-control'));
        } else {
            $value = $params[$name] ?? '';
            echo html_writer::empty_tag('input', array('type' => 'text', 'name' => $name, 'value' => $value, 'class' => 'form-control'));
        }
        
        echo html_writer::end_div();
    }
    echo html_writer::end_div();
    
    echo html_writer::start_div('mt-2');
    echo html_writer::tag('button', get_string('apply', 'moodle'), array('type' => 'submit', 'class' => 'btn btn-primary mr-2', 'id' => 'manireports-filter-submit'));
    
    // Add clear filters button.
    $clearurl = new moodle_url('/local/manireports/ui/report_view.php', array('report' => $reporttype));
    echo html_writer::link($clearurl, get_string('clearfilters', 'local_manireports'), array('class' => 'btn btn-secondary'));
    echo html_writer::end_div();
    
    echo html_writer::end_tag('form');
    
    // Add JavaScript to remove empty form fields before submission
    echo html_writer::start_tag('script');
    echo "
    document.addEventListener('DOMContentLoaded', function() {
        var form = document.querySelector('.manireports-filters form');
        if (form) {
            form.addEventListener('submit', function(e) {
                // Remove empty input fields
                var inputs = form.querySelectorAll('input[type=\"text\"], input[type=\"number\"], input[type=\"date\"]');
                inputs.forEach(function(input) {
                    if (input.value === '' || input.value === null) {
                        input.removeAttribute('name');
                    }
                });
                
                // Remove empty select fields
                var selects = form.querySelectorAll('select');
                selects.forEach(function(select) {
                    if (select.value === '' || select.value === null || select.value === '0') {
                        select.removeAttribute('name');
                    }
                });
            });
        }
    });
    ";
    echo html_writer::end_tag('script');
    
    echo html_writer::end_div();
    echo html_writer::end_div();
}

// Display results.
if (empty($result['data'])) {
    echo $OUTPUT->notification(get_string('nodata', 'local_manireports'), 'info');
} else {
    // Export buttons.
    echo html_writer::start_div('mb-3');
    echo html_writer::tag('strong', get_string('export', 'local_manireports') . ': ');
    
    $export_url = new moodle_url('/local/manireports/ui/export.php', array_merge(array('report' => $reporttype), $params));
    
    $csv_url = new moodle_url($export_url, array('format' => 'csv'));
    echo html_writer::link($csv_url, get_string('exportcsv', 'local_manireports'), array('class' => 'btn btn-sm btn-secondary mr-1'));
    
    $xlsx_url = new moodle_url($export_url, array('format' => 'xlsx'));
    echo html_writer::link($xlsx_url, get_string('exportxlsx', 'local_manireports'), array('class' => 'btn btn-sm btn-secondary mr-1'));
    
    $pdf_url = new moodle_url($export_url, array('format' => 'pdf'));
    echo html_writer::link($pdf_url, get_string('exportpdf', 'local_manireports'), array('class' => 'btn btn-sm btn-secondary'));
    
    echo html_writer::end_div();
    
    // Display chart if available.
    $chartdata = $report->get_chart_data($result['data']);
    if ($chartdata !== null && !empty($chartdata['labels'])) {
        echo html_writer::start_div('card mb-4 shadow-sm');
        echo html_writer::start_div('card-body');
        echo html_writer::start_div('d-flex justify-content-between align-items-center mb-3');
        echo html_writer::tag('h5', get_string('visualization', 'local_manireports'), array('class' => 'card-title mb-0'));
        echo html_writer::tag('span', get_string('top10courses', 'local_manireports'), array('class' => 'badge badge-info'));
        echo html_writer::end_div();
        echo html_writer::start_div('', array('style' => 'position: relative; height:500px;'));
        echo html_writer::tag('canvas', '', array('id' => 'manireports-chart'));
        echo html_writer::end_div();
        echo html_writer::end_div();
        echo html_writer::end_div();
        
        // Add Chart.js library and initialization script.
        echo html_writer::start_tag('script', array('src' => 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js'));
        echo html_writer::end_tag('script');
        
        echo html_writer::start_tag('script');
        $charttype = isset($chartdata['chartType']) ? $chartdata['chartType'] : 'bar';
        if ($charttype === 'horizontalBar') {
            $charttype = 'bar';
            $indexaxis = "'y'";
        } else if ($charttype === 'mixed') {
            $charttype = 'bar';
            $indexaxis = "'x'";
        } else {
            $indexaxis = "'x'";
        }
        
        echo "
        document.addEventListener('DOMContentLoaded', function() {
            var ctx = document.getElementById('manireports-chart');
            if (ctx) {
                var chartData = " . json_encode($chartdata) . ";
                var chartType = '" . $charttype . "';
                var indexAxis = " . $indexaxis . ";
                
                new Chart(ctx, {
                    type: chartType,
                    data: {
                        labels: chartData.labels,
                        datasets: chartData.datasets
                    },
                    options: {
                        indexAxis: indexAxis,
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: {
                            duration: 1500,
                            easing: 'easeInOutQuart'
                        },
                        layout: {
                            padding: {
                                top: 20,
                                right: 20,
                                bottom: 10,
                                left: 10
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                stacked: chartData.chartType === 'horizontalBar',
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.05)',
                                    drawBorder: false
                                },
                                ticks: {
                                    font: {
                                        size: 12,
                                        family: \"'Segoe UI', 'Helvetica Neue', Arial, sans-serif\"
                                    },
                                    color: '#6b7280',
                                    padding: 10
                                }
                            },
                            y1: chartData.chartType === 'mixed' ? {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                beginAtZero: true,
                                max: 100,
                                grid: {
                                    drawOnChartArea: false
                                },
                                ticks: {
                                    font: {
                                        size: 12
                                    },
                                    color: '#6b7280',
                                    callback: function(value) {
                                        return value + '%';
                                    }
                                }
                            } : undefined,
                            x: {
                                stacked: chartData.chartType === 'horizontalBar',
                                grid: {
                                    display: false,
                                    drawBorder: false
                                },
                                ticks: {
                                    font: {
                                        size: 11,
                                        family: \"'Segoe UI', 'Helvetica Neue', Arial, sans-serif\"
                                    },
                                    color: '#6b7280',
                                    maxRotation: 45,
                                    minRotation: 45,
                                    padding: 5
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                enabled: true,
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleFont: {
                                    size: 14,
                                    weight: 'bold'
                                },
                                bodyFont: {
                                    size: 13
                                },
                                padding: 12,
                                cornerRadius: 8,
                                displayColors: true,
                                callbacks: {
                                    title: function(context) {
                                        return context[0].label;
                                    },
                                    label: function(context) {
                                        var index = context.dataIndex;
                                        var completion = context.parsed.y;
                                        var enrolled = chartData.enrolleddata[index];
                                        var completed = chartData.completeddata[index];
                                        
                                        return [
                                            'Completion: ' + completion + '%',
                                            'Enrolled: ' + enrolled,
                                            'Completed: ' + completed
                                        ];
                                    }
                                }
                            },
                            datalabels: false
                        },
                        interaction: {
                            intersect: false,
                            mode: 'index'
                        }
                    }
                });
            }
        });
        ";
        echo html_writer::end_tag('script');
    }
    
    // Build table.
    $table = new html_table();
    $table->attributes['class'] = 'generaltable table table-striped';
    
    // Table headers.
    $table->head = array();
    $columns = (array)$result['columns']; // Ensure it's an array.
    foreach ($columns as $key => $label) {
        $table->head[] = $label;
    }
    
    // Table rows.
    foreach ($result['data'] as $row) {
        // Apply formatting to the row.
        $formatted_row = $report->format_row($row);
        $cells = array();
        foreach (array_keys($columns) as $key) {
            $cells[] = isset($formatted_row->$key) ? $formatted_row->$key : '-';
        }
        $table->data[] = $cells;
    }
    
    echo html_writer::table($table);
    
    // Pagination.
    if ($result['total'] > $perpage) {
        $baseurl = new moodle_url('/local/manireports/ui/report_view.php', array_merge(array('report' => $reporttype), $params));
        echo $OUTPUT->paging_bar($result['total'], $page, $perpage, $baseurl);
    }
    
    // Display total count.
    echo html_writer::tag('p', get_string('totalrecords', 'local_manireports', $result['total']), array('class' => 'text-muted mt-2'));
}

// Output footer.
echo $OUTPUT->footer();
