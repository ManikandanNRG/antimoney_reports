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
            
            // Company
            $html_rows .= '<td class="table-cell">';
            $html_rows .= '<div style="font-size: 13px; color: var(--text-primary);">' . htmlspecialchars($course['company']) . '</div>';
            $html_rows .= '</td>';

            // Enrolled
            $html_rows .= '<td class="table-cell">';
            $html_rows .= '<div style="font-weight: 600; font-size: 15px;">' . $course['enrolled'] . ' <span style="font-size: 11px; font-weight: 400; color: var(--text-secondary);">Recipients</span></div>';
            $html_rows .= '</td>';
            
            // Completed
            $html_rows .= '<td class="table-cell">';
            $html_rows .= '<div style="font-weight: 600; font-size: 15px; color: var(--accent-success);">' . $course['completed'] . '</div>';
            $html_rows .= '</td>';

            // In Progress (Active)
            $html_rows .= '<td class="table-cell">';
            $html_rows .= '<div style="font-weight: 600; font-size: 15px; color: var(--accent-warning);">' . $course['in_progress'] . '</div>';
            $html_rows .= '</td>';

            // License Status
            $is_licensed = ($course['licensed'] == 1);
            $lic_label = $is_licensed ? 'Licensed' : 'Non-Licensed';
            $lic_style = $is_licensed ? 'background: rgba(16, 185, 129, 0.2); color: #10b981;' : 'background: rgba(148, 163, 184, 0.2); color: var(--text-secondary);';
            
            $html_rows .= '<td class="table-cell">';
            $html_rows .= '<span style="padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; text-transform: uppercase; ' . $lic_style . '">' . $lic_label . '</span>';
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

    case 'get_course_distribution':
        $courseid = required_param('courseid', PARAM_INT);
        $distribution = $loader->get_course_company_distribution($courseid);
        echo json_encode($distribution);
        break;

    case 'export_course_distribution':
        $courseid = required_param('courseid', PARAM_INT);
        $companyid = required_param('companyid', PARAM_INT);
        
        // Prevent debug output from corrupting CSV
        error_reporting(0);
        while (ob_get_level()) ob_end_clean();
        
        $filename = 'Report_Company_' . $companyid . '_Course_' . $courseid . '_' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // BOM for Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Headers
        fputcsv($output, [
            'Username', 'Email', 'Enrollment Date', 'Last Access', 
            'Time Spent', 'Grade', 'Completion', 'Status'
        ]);
        
        // Data
        $rows = $loader->get_company_course_user_report($courseid, $companyid);
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit();
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
}
