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
        $result = $loader->get_courses_page($page, $limit, $search, $category);
        
        // Return HTML rows for the table
        $html_rows = '';
        foreach ($result['rows'] as $course) {
            $html_rows .= '<tr class="table-row">';
            // Course & Category
            $html_rows .= '<td class="table-cell">';
            $html_rows .= '<div style="font-weight: 600; color: var(--text-primary);">' . htmlspecialchars($course['fullname']) . '</div>';
            $html_rows .= '<div style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">' . htmlspecialchars($course['category']) . '</div>';
            $html_rows .= '</td>';
            
            // Enrolled
            $html_rows .= '<td class="table-cell">';
            $html_rows .= '<div style="font-weight: 600;">' . $course['enrolled'] . ' Users</div>';
            // Placeholder for active users if we had it
            // $html_rows .= '<div style="font-size: 11px; color: var(--accent-success);">12 Active</div>';
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
            $html_rows .= '<td class="table-cell">';
            $html_rows .= '<span class="status-badge ' . $course['status_class'] . '">' . $course['status'] . '</span>';
            $html_rows .= '</td>';
            
            // Action
            $html_rows .= '<td class="table-cell" style="text-align: right;">';
            $html_rows .= '<a href="' . $course['view_url'] . '" class="action-link" target="_blank">View</a>';
            $html_rows .= '</td>';
            
            $html_rows .= '</tr>';
        }
        
        if (empty($result['rows'])) {
             $html_rows = '<tr><td colspan="5" style="text-align: center; padding: 30px; color: var(--text-secondary);">No courses found.</td></tr>';
        }

        echo json_encode(['html' => $html_rows, 'pagination' => $result['pagination']]);
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
}
