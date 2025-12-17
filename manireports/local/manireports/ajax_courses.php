<?php
define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/output/dashboard_data_loader.php');
require_once(__DIR__ . '/classes/output/manager_data_loader.php');

require_login();
require_sesskey();

$action = required_param('action', PARAM_ALPHANUMEXT);
$page = optional_param('page', 1, PARAM_INT);
$limit = optional_param('limit', 20, PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT);
$category = optional_param('category', 0, PARAM_INT);
$start_str = optional_param('start_date', '', PARAM_TEXT);
$end_str = optional_param('end_date', '', PARAM_TEXT);

// Convert Date Strings to Timestamps
$start_date = !empty($start_str) ? strtotime($start_str) : 0;
$end_date = !empty($end_str) ? strtotime($end_str . ' 23:59:59') : 0;

// Initialize Loader based on Role
$user_context = \context_user::instance($USER->id);
$is_admin = is_siteadmin();
$is_manager = has_capability('moodle/site:config', $user_context) || \local_manireports\output\dashboard_data_loader::is_company_manager($USER->id);

$loader = null;
if ($is_admin) {
    $loader = new \local_manireports\output\dashboard_data_loader($USER->id);
} elseif ($is_manager) {
    $loader = new \local_manireports\output\manager_data_loader($USER->id);
} else {
    // Other roles not supported for this specific admin view
    echo json_encode(['error' => 'Unauthorized']);
    die();
}

switch ($action) {
    case 'get_courses':
        $result = $loader->get_courses_page($page, $limit, $search, $category, $start_date, $end_date);
        
        // Return HTML rows for the table
        $html_rows = '';
        foreach ($result['rows'] as $course) {
            $html_rows .= '<tr class="table-row">';
            // Course & Category
            $html_rows .= '<td class="table-cell" style="white-space: normal; word-wrap: break-word; max-width: 300px;">';
            $html_rows .= '<div style="font-weight: 600; color: var(--text-primary); line-height: 1.4;">' . htmlspecialchars($course['fullname']) . '</div>';
            $html_rows .= '<div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">' . htmlspecialchars($course['category']) . '</div>';
            $html_rows .= '</td>';
            
            // Enrolled with Sparkline
            $html_rows .= '<td class="table-cell">';
            $html_rows .= '<div style="display: flex; flex-direction: column; gap: 4px;">';
            $html_rows .= '<div style="font-weight: 600; font-size: 15px; white-space: nowrap;">' . $course['enrolled'] . ' <span style="font-size: 11px; font-weight: 400; color: var(--text-secondary);">Students</span></div>';
            
            // Generate Random Sparkline Path
            $pts = [];
            $prev = 10;
            for($i=0; $i<7; $i++) {
                $val = rand(2, 18);
                $pts[] = ($i * 10) . ',' . $val;
            }
            $path_d = implode(' ', $pts);
            
            $html_rows .= '<svg width="60" height="20" viewBox="0 0 60 20" fill="none" xmlns="http://www.w3.org/2000/svg" style="opacity: 0.7;">
                <polyline points="' . $path_d . '" stroke="#8b5cf6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>';
            $html_rows .= '</div>';
            $html_rows .= '</td>';
            
            // Progress & Time
            $html_rows .= '<td class="table-cell">';
            $html_rows .= '<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">';
            $html_rows .= '<div class="progress-bar-slim" style="width: 80px;">';
            $html_rows .= '<div class="progress-fill" style="width: ' . $course['progress'] . '%; background: var(--accent-primary);"></div>';
            $html_rows .= '</div>';
            $html_rows .= '<span style="font-size: 12px; font-weight: 600;">' . $course['progress'] . '%</span>';
            $html_rows .= '</div>';
            $html_rows .= '<div style="font-size: 11px; color: var(--text-secondary);">Avg: ' . $course['avg_time'] . '</div>';
            $html_rows .= '</td>';
            
            // Status
            $html_rows .= '<td class="table-cell" style="text-align: right;">';
            $html_rows .= '<span class="status-badge ' . $course['status_class'] . '">' . $course['status'] . '</span>';
            $html_rows .= '</td>';
            
            // Action
            $html_rows .= '<td class="table-cell" style="text-align: right;">';
            $html_rows .= '<button onclick="openCourseDrawer(' . $course['id'] . ')" class="action-link" style="background: none; border: none; cursor: pointer; padding: 0;">Quick View <i class="fa-solid fa-chevron-right" style="font-size: 10px; margin-left: 4px;"></i></button>';
            $html_rows .= '</td>';
            
            $html_rows .= '</tr>';
        }
        
        if (empty($result['rows'])) {
             $html_rows = '<tr><td colspan="5" style="text-align: center; padding: 30px; color: var(--text-secondary);">No courses found.</td></tr>';
        }

        // Fetch Metrics for KPI Cards
        $metrics = $loader->get_courses_tab_metrics($search, $category, $start_date, $end_date);

        echo json_encode([
            'html' => $html_rows, 
            'pagination' => $result['pagination'],
            'metrics' => $metrics
        ]);
        break;

    case 'get_course_details':
        $courseid = required_param('courseid', PARAM_INT);
        $details = $loader->get_course_details($courseid);
        echo json_encode($details);
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
}
