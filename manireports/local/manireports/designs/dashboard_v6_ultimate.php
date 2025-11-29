<?php
/**
 * Dashboard V6 - Ultimate Admin Dashboard with Tabs & Filters
 * Based on V7 Ultimate Design
 * 
 * Features:
 * - Collapsible Sidebar (hamburger menu)
 * - Horizontal Tab Menu (Overview, Courses, Companies, Users, Email, Certificates, Schedules)
 * - Global Filter Area (Date, Company, Course, User, Role, Export)
 * - Persistent KPI Cards (4 cards always visible)
 * - Tab-specific content areas
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../classes/output/dashboard_data_loader.php');
require_once(__DIR__ . '/../classes/output/cloud_offload_data_loader.php');
require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/manireports/designs/dashboard_v6_ultimate.php'));
$PAGE->set_heading('Dashboard V6 Ultimate');
$PAGE->set_pagelayout('embedded');

// --- Backend Connection Logic ---
$start_param = optional_param('start', '', PARAM_TEXT);
$end_param = optional_param('end', '', PARAM_TEXT);

$start_timestamp = 0;
$end_timestamp = 0;

if ($start_param) {
    $dt = DateTime::createFromFormat('d-m-Y', $start_param);
    if ($dt) {
        $dt->setTime(0, 0, 0);
        $start_timestamp = $dt->getTimestamp();
    }
}
if ($end_param) {
    $dt = DateTime::createFromFormat('d-m-Y', $end_param);
    if ($dt) {
        $dt->setTime(23, 59, 59);
        $end_timestamp = $dt->getTimestamp();
    }
}

// Instantiate Data Loader
$loader = new \local_manireports\output\dashboard_data_loader($USER->id, $start_timestamp, $end_timestamp);

// Fetch Data
// 1. KPIs
$kpi_data = $loader->get_admin_kpis();

// 2. System Health
$system_health = $loader->get_system_health();

// 3. User Roles
$role_data = $loader->get_user_roles_distribution();

// 4. Trend Data
$trend_data = $loader->get_completion_trends();

// 5. Company Analytics (New Method)
$company_data = $loader->get_company_analytics(5);

// 6. Top Courses Analytics (New Method)
$course_data = $loader->get_top_courses_analytics(10);

// 7. Live Statistics
try {
    $live_stats = $loader->get_live_statistics();
} catch (\Exception $e) {
    $live_stats = [
        'active_users' => 0,
        'peak_today' => 0,
        'active_courses_count' => 0,
        'top_courses' => [],
        'timeline_labels' => [],
        'timeline_data' => []
    ];
}

// 8. Courses Tab Data
$search_param = optional_param('q', '', PARAM_TEXT);
$category_param = optional_param('cat', 0, PARAM_INT);

try {
    $courses_metrics = $loader->get_courses_tab_metrics($search_param, $category_param);
} catch (\Exception $e) {
    $courses_metrics = ['active_courses' => 0, 'total_enrollments' => 0, 'avg_completion' => 0, 'certificates' => 0];
}

try {
    $courses_dist = $loader->get_course_category_distribution($search_param);
} catch (\Exception $e) {
    $courses_dist = [];
}

try {
    $courses_trends = $loader->get_course_enrollment_trends($search_param, $category_param);
} catch (\Exception $e) {
    $courses_trends = ['labels' => [], 'data' => []];
}

try {
    $comprehensive_courses = $loader->get_comprehensive_course_list(20, $search_param, $category_param);
} catch (\Exception $e) {
    $comprehensive_courses = [];
}

try {
    $course_categories = $loader->get_course_categories();
} catch (\Exception $e) {
    $course_categories = [];
}

// 9. Company Tab Data
$company_search_param = optional_param('company_q', '', PARAM_TEXT);

try {
    $company_metrics = $loader->get_company_tab_metrics($company_search_param);
} catch (\Exception $e) {
    $company_metrics = ['total_companies' => 0, 'total_users' => 0, 'avg_completion' => 0, 'assigned_courses' => 0];
}

try {
    $company_dist = $loader->get_company_distribution_chart();
} catch (\Exception $e) {
    $company_dist = [];
}

try {
    $company_perf = $loader->get_company_performance_chart();
} catch (\Exception $e) {
    $company_perf = [];
}

try {
    $company_list = $loader->get_company_analytics(20, $company_search_param);
} catch (\Exception $e) {
    $company_list = [];
}

// 10. Users Tab Data
$user_page = optional_param('user_page', 1, PARAM_INT);
$user_search = optional_param('user_search', '', PARAM_TEXT);
$user_role = optional_param('user_role', '', PARAM_TEXT);
$user_status = optional_param('user_status', '', PARAM_TEXT);
$user_per_page = 10;

try {
    $users_metrics = $loader->get_users_tab_metrics();
} catch (\Exception $e) {
    $users_metrics = ['total_users' => 0, 'active_today' => 0, 'suspended_users' => 0, 'new_users' => 0];
}

try {
    $users_list_data = $loader->get_comprehensive_user_list($user_page, $user_per_page, $user_search, $user_role, $user_status);
    $users_list = $users_list_data['data'];
    $users_pagination = $users_list_data['pagination'];
} catch (\Exception $e) {
    $users_list = [];
    $users_list = [];
    $users_pagination = ['total_records' => 0, 'total_pages' => 0, 'current_page' => 1, 'per_page' => 10];
}

// 11. Cloud Offload Data (Email & Certificates)
$cloud_loader = new \local_manireports\output\cloud_offload_data_loader($USER->id);

// Email Tab Data
$email_stats = $cloud_loader->get_job_stats('email');
$active_email_jobs = $cloud_loader->get_cloud_jobs('email', 'active', 5);
$email_history = $cloud_loader->get_cloud_jobs('email', 'history', 10);

// Certificate Tab Data
$cert_stats = $cloud_loader->get_job_stats('certificate');
$active_cert_jobs = $cloud_loader->get_cloud_jobs('certificate', 'active', 5);
$cert_history = $cloud_loader->get_cloud_jobs('certificate', 'history', 10);

// Settings Data (Company List)
$companies_list = $cloud_loader->get_companies();
$selected_company_id = optional_param('companyid', 0, PARAM_INT);
$company_settings = null;
if ($selected_company_id) {
    $company_settings = $cloud_loader->get_company_settings($selected_company_id);
} else if (!empty($companies_list)) {
    // Default to first company
    $first_company = reset($companies_list);
    $selected_company_id = $first_company->id;
    $company_settings = $cloud_loader->get_company_settings($selected_company_id);
}

// 12. Reminder Data
$reminder_data = $loader->get_reminder_data();

// Handle Settings Save (if posted)
if (optional_param('action', '', PARAM_ALPHA) === 'savesettings' && data_submitted() && confirm_sesskey()) {
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

    $existing = $DB->get_record('manireports_cloud_conf', ['company_id' => $settings->company_id]);
    if ($existing) {
        $settings->id = $existing->id;
        $DB->update_record('manireports_cloud_conf', $settings);
    } else {
        $DB->insert_record('manireports_cloud_conf', $settings);
    }
    // Redirect to avoid resubmission
    redirect(new moodle_url('/local/manireports/designs/dashboard_v6_ultimate.php', ['companyid' => $settings->company_id]), 'Settings saved', null, \core\output\notification::NOTIFY_SUCCESS);
}

// DEBUG: Check company data
// error_log("Company Metrics: " . print_r($company_metrics, true));
// error_log("Company List Count: " . count($company_list));


echo $OUTPUT->header();
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
:root {
    --bg-body: #0f172a;
    --glass-bg: rgba(30, 41, 59, 0.7);
    --glass-border: rgba(255, 255, 255, 0.08);
    --text-primary: #f8fafc;
    --text-secondary: #94a3b8;
    --card-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
    --sidebar-bg: rgba(15, 23, 42, 0.6);
    --accent-primary: #6366f1;
    --accent-secondary: #8b5cf6;
    --accent-success: #10b981;
    --accent-warning: #f59e0b;
    --accent-danger: #ef4444;
    --card-radius: 24px;
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
[data-theme="light"] {
    --bg-body: #f0f2f5;
    --glass-bg: rgba(255, 255, 255, 0.85);
    --glass-border: rgba(0, 0, 0, 0.05);
    --text-primary: #1e293b;
    --text-secondary: #64748b;
    --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
    --sidebar-bg: rgba(255, 255, 255, 0.8);
}
body {
    margin: 0; padding: 0;
    background-color: var(--bg-body);
    background-image: radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.15) 0px, transparent 50%),
                      radial-gradient(at 100% 0%, rgba(139, 92, 246, 0.15) 0px, transparent 50%);
    background-attachment: fixed;
    color: var(--text-primary);
    font-family: 'Outfit', sans-serif;
    height: 100vh; overflow: hidden;
    transition: background-color 0.3s ease;
}
.dashboard-container { display: block; height: 100vh; overflow: hidden; transition: var(--transition); }
.main-content { padding: 0; height: 100%; overflow-y: auto; scroll-behavior: smooth; }
.header { display: flex; justify-content: space-between; align-items: center; padding: 24px 40px; background: var(--glass-bg); border-bottom: 1px solid var(--glass-border); backdrop-filter: blur(10px); position: sticky; top: 0; z-index: 50; }
.header-left { display: flex; align-items: center; gap: 16px; }
.brand-logo {
    width: 40px; height: 40px; background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
    border-radius: 12px; display: flex; align-items: center; justify-content: center;
    font-size: 20px; color: white; box-shadow: 0 8px 16px rgba(99, 102, 241, 0.25); margin-right: 12px;
}
.tab-menu { display: flex; gap: 8px; padding: 16px 40px; background: var(--glass-bg); border-bottom: 1px solid var(--glass-border); backdrop-filter: blur(10px); overflow-x: auto; }
.tab-item {
    padding: 12px 24px; border-radius: 12px; background: transparent; border: 1px solid transparent;
    color: var(--text-secondary); cursor: pointer; transition: var(--transition); white-space: nowrap;
    font-weight: 500; display: flex; align-items: center; gap: 8px;
}
.tab-item:hover { background: rgba(99, 102, 241, 0.1); color: var(--text-primary); }
.tab-item.active { background: var(--accent-primary); color: white; border-color: var(--accent-primary); }
.filter-area {
    padding: 20px 40px; background: var(--glass-bg); border-bottom: 1px solid var(--glass-border);
    backdrop-filter: blur(10px); display: flex; gap: 12px; flex-wrap: wrap; align-items: center;
    position: relative; z-index: 1000; overflow: visible; /* Ensure it stacks above content and isn't clipped */
}
.filter-item { display: flex; align-items: center; gap: 8px; }
.filter-select, .filter-input {
    padding: 6px 12px; background: rgba(0, 0, 0, 0.1); border: 1px solid var(--glass-border);
    border-radius: 8px; color: var(--text-primary); font-family: inherit; outline: none;
    transition: var(--transition); min-width: 120px; font-size: 13px;
}
[data-theme="light"] .filter-select, [data-theme="light"] .filter-input { background: rgba(255, 255, 255, 0.5); }
.filter-select:focus, .filter-input:focus { border-color: var(--accent-primary); background: rgba(0, 0, 0, 0.15); }
.export-btn {
    padding: 8px 16px; background: var(--accent-primary); color: white; border: none;
    border-radius: 10px; cursor: pointer; transition: var(--transition); font-weight: 500;
}
.export-btn:hover { background: var(--accent-secondary); transform: translateY(-2px); }
.content-area { padding: 40px; max-width: 1600px; margin: 0 auto; }
.welcome-text h1 { font-size: 32px; margin: 0 0 8px 0; font-weight: 600; color: var(--text-primary); }
.welcome-text p { margin: 0; color: var(--text-secondary); }
.header-actions { display: flex; gap: 16px; align-items: center; }
.icon-btn {
    width: 48px; height: 48px; border-radius: 14px; border: 1px solid var(--glass-border);
    background: var(--glass-bg); color: var(--text-primary); display: flex;
    align-items: center; justify-content: center; cursor: pointer; transition: var(--transition);
}
.icon-btn:hover { background: rgba(99, 102, 241, 0.1); transform: translateY(-2px); }
.theme-toggle {
    position: relative; width: 60px; height: 30px; background: var(--glass-bg);
    border: 1px solid var(--glass-border); border-radius: 20px; cursor: pointer;
    display: flex; align-items: center; padding: 2px; transition: var(--transition);
}
.theme-toggle-thumb {
    width: 24px; height: 24px; background: var(--accent-primary); border-radius: 50%;
    position: absolute; left: 4px; transition: var(--transition); display: flex;
    align-items: center; justify-content: center; color: white; font-size: 12px;
}
[data-theme="light"] .theme-toggle-thumb { left: 32px; background: var(--accent-warning); }
.user-profile {
    display: flex; align-items: center; gap: 12px; padding: 8px 16px 8px 8px;
    background: var(--glass-bg); border: 1px solid var(--glass-border); border-radius: 14px;
    cursor: pointer; transition: var(--transition); color: var(--text-primary);
}
.user-profile:hover { background: rgba(99, 102, 241, 0.1); }
.avatar { width: 32px; height: 32px; border-radius: 10px; background: linear-gradient(135deg, #f59e0b, #ef4444); }
.kpi-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; margin-bottom: 32px; }
.bento-grid { display: grid; grid-template-columns: repeat(4, 1fr); grid-template-rows: repeat(auto-fit, auto); gap: 24px; }
.tab-content { display: none; }
.tab-content.active { display: block; }
.bento-card {
    background: var(--glass-bg); border: 1px solid var(--glass-border); border-radius: var(--card-radius);
    padding: 24px; backdrop-filter: blur(10px); transition: var(--transition);
    position: relative; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.02);
}
.bento-card:hover { transform: translateY(-4px); box-shadow: var(--card-shadow); border-color: var(--accent-primary); }
.card-span-1 { grid-column: span 1; }
.card-span-2 { grid-column: span 2; }
.card-span-3 { grid-column: span 3; }
.card-span-4 { grid-column: span 4; }
.card-row-2 { grid-row: span 2; }
.card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; position: relative; z-index: 2; }
.card-title { font-size: 16px; font-weight: 600; color: var(--text-secondary); display: flex; align-items: center; gap: 8px; }
.card-value { font-size: 36px; font-weight: 700; margin-bottom: 8px; color: var(--text-primary); position: relative; z-index: 2; }
.card-trend { font-size: 14px; display: flex; align-items: center; gap: 4px; }
.trend-up { color: var(--accent-success); }
.trend-down { color: var(--accent-danger); }
.card-illustration { position: absolute; top: 10px; right: 10px; width: 80px; height: 80px; object-fit: contain; opacity: 0.6; pointer-events: none; z-index: 1; }
.card-content-wrapper { position: relative; z-index: 2; }
.table-header { color: var(--text-secondary); font-weight: 500; font-size: 12px; text-transform: uppercase; padding: 12px; text-align: left; }
.table-row { border-bottom: 1px solid var(--glass-border); transition: var(--transition); }
.table-row:last-child { border-bottom: none; }
.table-row:hover { background: rgba(99, 102, 241, 0.05); }
.table-cell { padding: 16px 12px; color: var(--text-primary); }
.status-badge { padding: 6px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; display: inline-block; }
.status-active { background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); }
.status-inactive { background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
.status-warning { background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2); }
.status-upcoming { background: rgba(59, 130, 246, 0.15); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.2); }
.status-completed { background: rgba(139, 92, 246, 0.15); color: #8b5cf6; border: 1px solid rgba(139, 92, 246, 0.2); }
.status-retired { background: rgba(148, 163, 184, 0.15); color: #94a3b8; border: 1px solid rgba(148, 163, 184, 0.2); }

.progress-bar-slim { width: 100%; height: 6px; background: rgba(0,0,0,0.1); border-radius: 3px; overflow: hidden; position: relative; }
[data-theme="light"] .progress-bar-slim { background: rgba(0,0,0,0.05); }
.progress-fill { height: 100%; border-radius: 3px; transition: width 1s ease-in-out; }

.action-link { color: var(--accent-primary); text-decoration: none; font-weight: 500; font-size: 13px; transition: var(--transition); }
.action-link:hover { color: var(--accent-secondary); text-decoration: underline; }

.dot-success { background: var(--accent-success); box-shadow: 0 0 8px rgba(16, 185, 129, 0.4); }
.dot-warning { background: var(--accent-warning); box-shadow: 0 0 8px rgba(245, 158, 11, 0.4); }
.dot-danger { background: var(--accent-danger); box-shadow: 0 0 8px rgba(239, 68, 68, 0.4); }

.badge-student { background: rgba(99, 102, 241, 0.1); color: var(--accent-primary); border: 1px solid rgba(99, 102, 241, 0.2); }
.badge-teacher { background: rgba(16, 185, 129, 0.1); color: var(--accent-success); border: 1px solid rgba(16, 185, 129, 0.2); }
.badge-manager { background: rgba(139, 92, 246, 0.1); color: var(--accent-secondary); border: 1px solid rgba(139, 92, 246, 0.2); }
.badge-admin { background: rgba(239, 68, 68, 0.1); color: var(--accent-danger); border: 1px solid rgba(239, 68, 68, 0.2); }

@media (max-width: 1200px) {
    .bento-grid { grid-template-columns: repeat(2, 1fr); }
    .card-span-3, .card-span-4 { grid-column: span 2; }
}
@media (max-width: 768px) {
    .bento-grid { grid-template-columns: 1fr; }
    .kpi-cards { grid-template-columns: repeat(2, 1fr); }
    .card-span-1, .card-span-2, .card-span-3, .card-span-4 { grid-column: span 1; }
    .header { padding: 16px 20px; flex-direction: column; gap: 16px; }
    .header-left { width: 100%; justify-content: space-between; }
    .filter-area { padding: 16px 20px; }
    .tab-menu { padding: 12px 20px; }
}
.dropdown-menu {
    display: none; position: absolute; top: 100%; right: 0; margin-top: 8px;
    background: var(--glass-bg); border: 1px solid var(--glass-border);
    border-radius: 12px; padding: 8px; z-index: 2000; min-width: 180px;
    backdrop-filter: blur(10px); box-shadow: var(--card-shadow);
    overflow: hidden; /* Prevent scrollbars */
}
.dropdown-item {
    padding: 10px 16px; border-radius: 8px; cursor: pointer;
    color: var(--text-primary); font-size: 13px; display: flex; align-items: center; gap: 10px;
    transition: var(--transition); white-space: nowrap; /* Prevent text wrapping */
}
.dropdown-item:hover { background: rgba(99, 102, 241, 0.1); color: var(--accent-primary); }
</style>

<!-- Dashboard Container -->
<div class="dashboard-container">
    <!-- Main Content -->
    <main class="main-content">
        <!-- Header -->
        <header class="header">
            <div class="header-left">
                <div class="brand-logo"><i class="fa-solid fa-chart-pie"></i></div>
                <div class="welcome-text">
                    <h1>ManiReports</h1>
                    <p>Welcome back, <?php echo $USER->firstname; ?></p>
                </div>
            </div>
            <div class="header-actions">
                <div class="theme-toggle" onclick="toggleTheme()">
                    <div class="theme-toggle-thumb"><i class="fa-solid fa-sun"></i></div>
                </div>
                <div class="icon-btn"><i class="fa-regular fa-bell"></i></div>
                <div class="user-profile">
                    <div class="avatar"></div>
                    <span><?php echo $USER->firstname; ?></span>
                    <i class="fa-solid fa-chevron-down" style="font-size: 12px;"></i>
                </div>
            </div>
        </header>

        <!-- Tab Menu -->
        <div class="tab-menu">
            <div class="tab-item active" onclick="switchTab('overview')"><i class="fa-solid fa-grid-2"></i> Overview</div>
            <div class="tab-item" onclick="switchTab('courses')"><i class="fa-solid fa-book-open"></i> Courses</div>
            <div class="tab-item" onclick="switchTab('companies')"><i class="fa-solid fa-building"></i> Companies</div>
            <div class="tab-item" onclick="switchTab('users')"><i class="fa-solid fa-users"></i> Users</div>
            <div class="tab-item" onclick="switchTab('email')"><i class="fa-solid fa-envelope"></i> Email Offload</div>
            <div class="tab-item" onclick="switchTab('certificates')"><i class="fa-solid fa-certificate"></i> Cert Offload</div>
            <div class="tab-item" onclick="switchTab('reports')"><i class="fa-solid fa-file-lines"></i> Reports</div>
            <div class="tab-item" onclick="switchTab('reminders')"><i class="fa-solid fa-bell"></i> Reminders</div>
        </div>

        <!-- Filter Area -->
        <div class="filter-area" style="z-index: 3000;">
            <div class="filter-item">
                <i class="fa-regular fa-calendar" style="color: var(--accent-primary); cursor: pointer;" onclick="document.getElementById('dateStart').showPicker()"></i>
                <input type="date" id="dateStart" class="filter-input" placeholder="Start Date" style="width: 140px;" value="<?php echo $start_param; ?>">
                <span style="color: var(--text-secondary);">-</span>
                <input type="date" id="dateEnd" class="filter-input" placeholder="End Date" style="width: 140px;" value="<?php echo $end_param; ?>">
            </div>

            <div class="filter-item">
                <button class="filter-select quick-filter-btn" onclick="setDateFilter('1W')">1W</button>
                <button class="filter-select quick-filter-btn" onclick="setDateFilter('1M')">1M</button>
                <button class="filter-select quick-filter-btn" onclick="setDateFilter('3M')">3M</button>
                <button class="filter-select quick-filter-btn" onclick="setDateFilter('YTD')">YTD</button>
                <button class="filter-select quick-filter-btn active" onclick="setDateFilter('ALL')">ALL</button>
            </div>

            <div class="filter-item">
                <button class="export-btn" onclick="applyDateFilter()" style="padding: 6px 14px; font-size: 13px;">
                    <i class="fa-solid fa-filter"></i> Apply
                </button>
                <button class="export-btn" onclick="clearAllFilters()" style="padding: 6px 14px; font-size: 13px; background: var(--accent-danger);">
                    <i class="fa-solid fa-xmark"></i> Clear Filters
                </button>
            </div>

            <div style="flex: 1;"></div>

            <!-- Export Dropdown -->
            <div style="position: relative;">
                <button class="export-btn" onclick="toggleExportMenu()">
                    <i class="fa-solid fa-download"></i> Export Report
                    <i class="fa-solid fa-chevron-down" style="font-size: 10px; margin-left: 4px;"></i>
                </button>
                <div class="dropdown-menu" id="exportDropdown">
                    <div class="dropdown-item" onclick="triggerExport('course_completion', 'csv')">
                        <i class="fa-solid fa-file-csv"></i> Export as CSV
                    </div>
                    <div class="dropdown-item" onclick="triggerExport('course_completion', 'xlsx')">
                        <i class="fa-solid fa-file-excel"></i> Export as Excel
                    </div>
                    <div class="dropdown-item" onclick="triggerExport('course_completion', 'pdf')">
                        <i class="fa-solid fa-file-pdf"></i> Export as PDF
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content-area">
            <div id="tab-overview" class="tab-content active">
            <!-- KPI Cards (Always Visible) -->
            <div class="kpi-cards">

            <!-- KPI 1: Total Companies -->
            <div class="bento-card card-span-1">
                <img src="https://avatar.iran.liara.run/public/boy?username=Company" class="card-illustration" alt="Companies">
                <div class="card-content-wrapper">
                    <div class="card-header">
                        <div class="card-title"><i class="fa-solid fa-building" style="color: var(--accent-primary);"></i> Total Companies</div>
                    </div>
                    <div class="card-value"><?php echo $kpi_data['companies']; ?></div>
                    <div style="height: 60px;"><canvas id="chartCompanies"></canvas></div>
                </div>
            </div>

            <!-- KPI 2: Total Courses -->
            <div class="bento-card card-span-1">
                <img src="https://avatar.iran.liara.run/public/girl?username=Course" class="card-illustration" alt="Courses">
                <div class="card-content-wrapper">
                    <div class="card-header">
                        <div class="card-title"><i class="fa-solid fa-book" style="color: var(--accent-success);"></i> Total Courses</div>
                    </div>
                    <div class="card-value"><?php echo $kpi_data['courses']; ?></div>
                    <div style="height: 60px;"><canvas id="chartCourses"></canvas></div>
                </div>
            </div>
            <div class="bento-card card-span-1">
                <img src="https://avatar.iran.liara.run/public/boy?username=Users" class="card-illustration" alt="Users">
                <div class="card-content-wrapper">
                    <div class="card-header">
                        <div class="card-title"><i class="fa-solid fa-users" style="color: var(--accent-warning);"></i> Total Users</div>
                    </div>
                    <div class="card-value"><?php echo number_format($kpi_data['users']); ?></div>
                    <div style="height: 60px;"><canvas id="chartUsers"></canvas></div>
                </div>
            </div>

            <!-- KPI 4: Overall Completion % -->
            <div class="bento-card card-span-1">
                <img src="https://avatar.iran.liara.run/public/girl?username=Complete" class="card-illustration" alt="Completion">
                <div class="card-content-wrapper">
                    <div class="card-header">
                        <div class="card-title"><i class="fa-solid fa-trophy" style="color: var(--accent-secondary);"></i> Completion %</div>
                    </div>
                    <div class="card-value"><?php echo $kpi_data['completion_rate']; ?>%</div>
                    <div style="height: 6px; background: rgba(255,255,255,0.1); border-radius: 3px; margin-top: 20px;">
                        <div style="width: <?php echo $kpi_data['completion_rate']; ?>%; height: 100%; background: var(--accent-secondary); border-radius: 3px;"></div>
                    </div>
                </div>
            </div>
            </div>
            <!-- End KPI Cards -->

            <div class="bento-grid">

            <!-- System Health Widget -->
            <div class="bento-card card-span-1">
                <div class="card-header">
                    <div class="card-title">System Health</div>
                </div>
                <div>
                    <div class="health-item">
                        <div class="health-info">
                            <div class="health-icon"><i class="fa-solid fa-database"></i></div>
                            <div class="health-name">Database Size</div>
                        </div>
                        <div class="health-status">
                            <div class="status-dot dot-success"></div> <?php echo $system_health['db_size']; ?>
                        </div>
                    </div>
                    <div class="health-item">
                        <div class="health-info">
                            <div class="health-icon"><i class="fa-solid fa-bolt"></i></div>
                            <div class="health-name">Cache Hit Rate</div>
                        </div>
                        <div class="health-status">
                            <div class="status-dot dot-success"></div> <?php echo $system_health['cache_hit_rate']; ?>
                        </div>
                    </div>
                    <div class="health-item">
                        <div class="health-info">
                            <div class="health-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                            <div class="health-name">Error Rate</div>
                        </div>
                        <div class="health-status">
                            <div class="status-dot dot-success"></div> <?php echo $system_health['error_rate']; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Users 24h Time Chart -->
            <div class="bento-card card-span-2">
                <div class="card-header">
                    <div class="card-title">Active Users (24h)</div>
                </div>
                <div style="height: 250px; width: 100%;">
                    <canvas id="activeUsersChart"></canvas>
                </div>
            </div>

            <!-- Avg Time Spent per User Chart -->
            <div class="bento-card card-span-1">
                <div class="card-header">
                    <div class="card-title">Avg Time/User</div>
                </div>
                <div style="height: 250px; width: 100%;">
                    <canvas id="timeSpentChart"></canvas>
                </div>
            </div>

            <!-- User Role Distribution (Donut) -->
            <div class="bento-card card-span-2">
                <div class="card-header">
                    <div class="card-title">User Roles</div>
                </div>
                <div style="height: 250px; width: 100%; position: relative;">
                    <canvas id="chartUserRoles"></canvas>
                    <!-- Center Text Overlay -->
                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; pointer-events: none;">
                        <div style="font-size: 12px; color: var(--text-secondary);">Total</div>
                        <div style="font-size: 24px; font-weight: 700; color: var(--text-primary);"><?php echo array_sum($role_data); ?></div>
                    </div>
                </div>
                <div style="display: flex; justify-content: center; gap: 16px; margin-top: 16px;">
                    <div style="display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--text-secondary);">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: #10b981;"></span> Admin
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--text-secondary);">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: #f59e0b;"></span> Teacher
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--text-secondary);">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: #ef4444;"></span> Student
                    </div>
                </div>
            </div>

            <!-- Course Completion Trend Chart -->
            <!-- Course Completion Trend Chart -->
            <div class="bento-card card-span-2">
                <div class="card-header">
                    <div class="card-title">Course Completion Trend</div>
                </div>
                <div style="height: 300px; width: 100%;">
                    <canvas id="completionTrendChart"></canvas>
                </div>
            </div>

            <!-- Live Analytics Row (Full Width) -->
            <div class="bento-card card-span-4" style="min-height: 320px;">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <div class="card-title" style="display: flex; align-items: center; gap: 10px;">
                        <i class="fa-solid fa-bolt" style="color: #10b981;"></i> Real-time Active Users
                        <span style="font-size: 12px; color: #10b981; display: flex; align-items: center; gap: 5px;">
                            <span style="width: 8px; height: 8px; background: #10b981; border-radius: 50%; animation: pulse 1.5s infinite;"></span> Live
                        </span>
                    </div>
                    <div style="font-size: 12px; color: var(--text-secondary);">
                        Updated <span id="live-update-timer">0</span> sec ago
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1.5fr 1.5fr; gap: 24px; margin-top: 16px;">
                    <!-- Col 1: Metrics -->
                    <div style="display: flex; flex-direction: column; gap: 20px;">
                        <div style="background: rgba(16, 185, 129, 0.1); padding: 16px; border-radius: 12px; border: 1px solid rgba(16, 185, 129, 0.2);">
                            <div style="font-size: 13px; color: var(--text-secondary); margin-bottom: 4px;">Currently Active</div>
                            <div style="font-size: 32px; font-weight: 700; color: #10b981; display: flex; align-items: center; gap: 10px;">
                                <?php echo $live_stats['active_users']; ?>
                                <i class="fa-solid fa-users" style="font-size: 20px; opacity: 0.5;"></i>
                            </div>
                        </div>
                        <div style="background: rgba(139, 92, 246, 0.1); padding: 16px; border-radius: 12px; border: 1px solid rgba(139, 92, 246, 0.2);">
                            <div style="font-size: 13px; color: var(--text-secondary); margin-bottom: 4px;">Peak Today</div>
                            <div style="font-size: 24px; font-weight: 700; color: #8b5cf6; display: flex; align-items: center; gap: 10px;">
                                <?php echo $live_stats['peak_today']; ?>
                                <i class="fa-solid fa-chart-line" style="font-size: 18px; opacity: 0.5;"></i>
                            </div>
                        </div>
                        <div style="background: rgba(59, 130, 246, 0.1); padding: 16px; border-radius: 12px; border: 1px solid rgba(59, 130, 246, 0.2);">
                            <div style="font-size: 13px; color: var(--text-secondary); margin-bottom: 4px;">Active Courses</div>
                            <div style="font-size: 24px; font-weight: 700; color: #3b82f6; display: flex; align-items: center; gap: 10px;">
                                <?php echo $live_stats['active_courses_count']; ?>
                                <i class="fa-solid fa-book-open" style="font-size: 18px; opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>


                    <!-- Col 2: Live Courses -->
                    <div>
                        <div style="font-size: 14px; font-weight: 600; color: var(--text-primary); margin-bottom: 16px;">Users by Course (Live)</div>
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <?php foreach ($live_stats['top_courses'] as $course): ?>
                                <?php 
                                    $percent = ($live_stats['active_users'] > 0) ? ($course->active_count / $live_stats['active_users']) * 100 : 0;
                                    $color = '#3b82f6'; // Default Blue
                                    if ($percent > 50) $color = '#8b5cf6'; // Purple for high activity
                                    if ($percent < 20) $color = '#ef4444'; // Red for low
                                ?>
                                <div>
                                    <div style="display: flex; justify-content: space-between; font-size: 12px; color: var(--text-secondary); margin-bottom: 4px;">
                                        <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 180px;"><?php echo $course->fullname; ?></span>
                                        <span style="font-weight: 600; color: var(--text-primary);"><?php echo $course->active_count; ?></span>
                                    </div>
                                    <div style="width: 100%; height: 6px; background: var(--border-color); border-radius: 3px; overflow: hidden;">
                                        <div style="width: <?php echo $percent; ?>%; height: 100%; background: <?php echo $color; ?>; border-radius: 3px;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($live_stats['top_courses'])): ?>
                                <div style="font-size: 12px; color: var(--text-secondary); text-align: center; padding: 20px;">No active courses right now.</div>
                            <?php endif; ?>
                        </div>
                    </div>


                    <!-- Col 3: 24h Timeline -->
                    <div>
                        <div style="font-size: 14px; font-weight: 600; color: var(--text-primary); margin-bottom: 16px;">Activity Timeline (24h)</div>
                        <div style="height: 200px; width: 100%;">
                            <canvas id="timelineChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Company-wise Analytics Table -->
            <div class="bento-card card-span-4">
                <div class="card-header">
                    <div class="card-title">Company-wise Analytics</div>
                </div>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th class="table-header">Company Name</th>
                            <th class="table-header">Courses</th>
                            <th class="table-header">Users</th>
                            <th class="table-header">Enrolled</th>
                            <th class="table-header">Completed</th>
                            <th class="table-header" style="width: 200px;">Completion %</th>
                            <th class="table-header">Avg Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (!empty($company_data)) {
                            foreach ($company_data as $company) {
                                $pct = $company['completion_rate'];
                                $color = '#3b82f6'; // Blue
                                if ($pct > 70) $color = '#10b981'; // Green
                                if ($pct < 40) $color = '#f59e0b'; // Amber

                                echo '<tr class="table-row">
                                        <td class="table-cell" style="font-weight: 600;">' . $company['name'] . '</td>
                                        <td class="table-cell">' . $company['courses'] . '</td>
                                        <td class="table-cell">' . $company['users'] . '</td>
                                        <td class="table-cell">' . $company['enrolled'] . '</td>
                                        <td class="table-cell">' . $company['completed'] . '</td>
                                        <td class="table-cell">
                                            <div style="display: flex; align-items: center; gap: 12px;">
                                                <div class="progress-bar-slim">
                                                    <div class="progress-fill" style="width: ' . $pct . '%; background: ' . $color . ';"></div>
                                                </div>
                                                <span style="font-weight: 600; font-size: 12px; color: var(--text-primary);">' . $pct . '%</span>
                                            </div>
                                        </td>
                                        <td class="table-cell">' . $company['time'] . '</td>
                                      </tr>';
                            }
                        } else {
                            echo '<tr><td colspan="7" class="table-cell" style="text-align: center; padding: 24px; color: var(--text-secondary);">No company data available.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- Course Analytics Table (Top 10 Courses) -->
            <div class="bento-card card-span-4">
                <div class="card-header">
                    <div class="card-title">Course Analytics (Top 10 Courses)</div>
                    <a href="#" class="action-link">View All</a>
                </div>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th class="table-header">Course Name</th>
                            <th class="table-header">Enrolled</th>
                            <th class="table-header">Completed</th>
                            <th class="table-header" style="width: 200px;">Progress</th>
                            <th class="table-header">Status</th>
                            <th class="table-header" style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (!empty($course_data)) {
                            foreach ($course_data as $course) {
                                $pct = $course['progress'];
                                $color = '#3b82f6'; // Blue
                                if ($pct > 70) $color = '#10b981'; // Green
                                if ($pct < 40) $color = '#f59e0b'; // Amber

                                echo '<tr class="table-row">
                                        <td class="table-cell">
                                            <div style="font-weight: 600;">' . $course['fullname'] . '</div>
                                            <div style="font-size: 11px; color: var(--text-secondary);">' . $course['shortname'] . '</div>
                                        </td>
                                        <td class="table-cell">' . $course['enrolled'] . '</td>
                                        <td class="table-cell">' . $course['completed'] . '</td>
                                        <td class="table-cell">
                                            <div style="display: flex; align-items: center; gap: 12px;">
                                                <div class="progress-bar-slim">
                                                    <div class="progress-fill" style="width: ' . $pct . '%; background: ' . $color . ';"></div>
                                                </div>
                                                <span style="font-weight: 600; font-size: 12px; color: var(--text-primary);">' . $pct . '%</span>
                                            </div>
                                        </td>
                                        <td class="table-cell">
                                            <span class="status-badge ' . $course['status_class'] . '">' . $course['status'] . '</span>
                                        </td>
                                        <td class="table-cell" style="text-align: right;">
                                            <a href="#" class="action-link">View</a>
                                        </td>
                                      </tr>';
                            }
                        } else {
                            echo '<tr><td colspan="6" class="table-cell" style="text-align: center; padding: 24px; color: var(--text-secondary);">No course data available.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            </div>
        </div>

        <!-- COURSES TAB -->
        <div id="tab-courses" class="tab-content">
            <!-- Filter Bar -->
            <div class="filter-area" style="margin-bottom: 32px; background: var(--glass-bg); padding: 20px; border-radius: 16px; border: 1px solid var(--glass-border);">
                <div class="filter-item" style="flex-grow: 1;">
                    <i class="fa-solid fa-search" style="color: var(--text-secondary);"></i>
                    <input type="text" class="filter-input" placeholder="Search Companies or Courses..." style="width: 100%; font-size: 16px; padding: 12px 16px; background: rgba(0,0,0,0.2);">
                </div>
                <div class="filter-item">
                    <i class="fa-regular fa-calendar" style="color: var(--accent-primary);"></i>
                    <input type="text" class="filter-input" placeholder="Start Date" style="width: 110px;">
                    <span style="color: var(--text-secondary);">-</span>
                    <input type="text" class="filter-input" placeholder="End Date" style="width: 110px;">
                </div>
                <div class="filter-item">
                    <select class="filter-select">
                        <option value="0">All Categories</option>
                        <?php foreach ($course_categories as $id => $name) { echo "<option value='$id'>$name</option>"; } ?>
                    </select>
                </div>
            </div>

            <div class="bento-grid">
                <!-- Row 1: KPIs -->
                <div class="bento-card card-span-1">
                    <div class="card-title"><i class="fa-solid fa-book-open" style="color: var(--accent-primary);"></i> Active Courses</div>
                    <div class="card-value"><?php echo $courses_metrics['active_courses']; ?></div>
                </div>
                <div class="bento-card card-span-1">
                    <div class="card-title"><i class="fa-solid fa-chart-pie" style="color: var(--accent-success);"></i> Avg Completion</div>
                    <div class="card-value"><?php echo $courses_metrics['avg_completion']; ?>%</div>
                </div>
                <div class="bento-card card-span-1">
                    <div class="card-title"><i class="fa-solid fa-users" style="color: var(--accent-warning);"></i> Total Enrollments</div>
                    <div class="card-value"><?php echo number_format($courses_metrics['total_enrollments']); ?></div>
                </div>
                <div class="bento-card card-span-1">
                    <div class="card-title"><i class="fa-solid fa-certificate" style="color: var(--accent-secondary);"></i> Certificates</div>
                    <div class="card-value"><?php echo number_format($courses_metrics['certificates']); ?></div>
                </div>

                <!-- Row 2: Charts -->
                <div class="bento-card card-span-3">
                    <div class="card-header">
                        <div class="card-title">Enrollment Trends</div>
                    </div>
                    <div style="height: 300px;">
                        <canvas id="enrollmentTrendChart"></canvas>
                    </div>
                </div>
                <div class="bento-card card-span-1">
                    <div class="card-header">
                        <div class="card-title">Categories</div>
                    </div>
                    <div style="height: 300px;">
                        <canvas id="categoryDistChart"></canvas>
                    </div>
                </div>

                <!-- Row 3: Advanced Table -->
                <div class="bento-card card-span-4">
                    <div class="card-header">
                        <div class="card-title">Comprehensive Course List</div>
                        <button class="export-btn" style="padding: 6px 12px; font-size: 12px;" onclick="triggerExport('course_completion', 'csv')">Export CSV</button>
                    </div>
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th class="table-header">Course Name</th>
                                <th class="table-header">Category</th>
                                <th class="table-header">Enrolled</th>
                                <th class="table-header">Completed</th>
                                <th class="table-header" style="width: 150px;">Progress</th>
                                <th class="table-header">Avg Time</th>
                                <th class="table-header">Status</th>
                                <th class="table-header" style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($comprehensive_courses)): ?>
                                <?php foreach ($comprehensive_courses as $course): ?>
                                <tr class="table-row">
                                    <td class="table-cell" style="font-weight: 600;"><?php echo $course['fullname']; ?></td>
                                    <td class="table-cell" style="color: var(--text-secondary); font-size: 13px;"><?php echo $course['category']; ?></td>
                                    <td class="table-cell"><?php echo $course['enrolled']; ?></td>
                                    <td class="table-cell"><?php echo $course['completed']; ?></td>
                                    <td class="table-cell">
                                        <div class="progress-bar-slim">
                                            <div class="progress-fill" style="width: <?php echo $course['progress']; ?>%; background: var(--accent-primary);"></div>
                                        </div>
                                    </td>
                                    <td class="table-cell"><?php echo $course['avg_time']; ?></td>
                                    <td class="table-cell"><span class="status-badge <?php echo $course['status_class']; ?>"><?php echo $course['status']; ?></span></td>
                                    <td class="table-cell" style="text-align: right;">
                                        <a href="#" class="action-link">View Report</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="8" class="table-cell" style="text-align: center;">No courses found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- EMAIL OFFLOAD TAB -->
        <div id="tab-email" class="tab-content">
            <!-- KPIs -->
            <div class="kpi-cards">
                <div class="bento-card card-span-1">
                    <div class="card-header"><div class="card-title">Active Jobs</div></div>
                    <div class="card-value"><?php echo $email_stats['active_jobs']; ?></div>
                </div>
                <div class="bento-card card-span-1">
                    <div class="card-header"><div class="card-title">Sent Today</div></div>
                    <div class="card-value"><?php echo $email_stats['sent_today']; ?></div>
                </div>
                <div class="bento-card card-span-1">
                    <div class="card-header"><div class="card-title">Failed Today</div></div>
                    <div class="card-value"><?php echo $email_stats['failed_today']; ?></div>
                </div>
            </div>

            <div class="bento-grid">
                <!-- Active Jobs Table -->
                <div class="bento-card card-span-2">
                    <div class="card-header"><div class="card-title">Active Email Jobs</div></div>
                    <?php if ($active_email_jobs): ?>
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead><tr><th class="table-header">ID</th><th class="table-header">Type</th><th class="table-header">Status</th><th class="table-header">Progress</th></tr></thead>
                            <tbody>
                                <?php foreach ($active_email_jobs as $job): 
                                    $progress = $job->email_count > 0 ? round(($job->emails_sent / $job->email_count) * 100) : 0;
                                ?>
                                <tr class="table-row">
                                    <td class="table-cell"><?php echo $job->id; ?></td>
                                    <td class="table-cell"><?php echo $job->type; ?></td>
                                    <td class="table-cell"><span class="status-badge status-active"><?php echo $job->status; ?></span></td>
                                    <td class="table-cell">
                                        <div class="progress-bar-slim"><div class="progress-fill" style="width: <?php echo $progress; ?>%; background: var(--accent-primary);"></div></div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="padding: 20px; text-align: center; color: var(--text-secondary);">No active jobs.</div>
                    <?php endif; ?>
                </div>

                <!-- Job History Table -->
                <div class="bento-card card-span-2">
                    <div class="card-header"><div class="card-title">Job History</div></div>
                    <?php if ($email_history): ?>
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead><tr><th class="table-header">ID</th><th class="table-header">Type</th><th class="table-header">Status</th><th class="table-header">Completed</th><th class="table-header" style="text-align: right;">Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($email_history as $job): 
                                    $status_class = $job->status === 'completed' ? 'status-completed' : 'status-inactive';
                                ?>
                                <tr class="table-row">
                                    <td class="table-cell"><?php echo $job->id; ?></td>
                                    <td class="table-cell"><?php echo $job->type; ?></td>
                                    <td class="table-cell"><span class="status-badge <?php echo $status_class; ?>"><?php echo $job->status; ?></span></td>
                                    <td class="table-cell"><?php echo userdate($job->completed_at); ?></td>
                                    <td class="table-cell" style="text-align: right;">
                                        <button class="action-link" style="background:none; border:none; cursor:pointer;" onclick="viewJobDetails(<?php echo $job->id; ?>)">View Details</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="padding: 20px; text-align: center; color: var(--text-secondary);">No job history.</div>
                    <?php endif; ?>
                </div>

                <!-- Settings Form -->
                <div class="bento-card card-span-4">
                    <div class="card-header"><div class="card-title">Email Offload Configuration</div></div>
                    <form method="get" class="form-inline mb-3">
                        <label class="mr-2">Select Company:</label>
                        <select name="companyid" class="filter-select" onchange="this.form.submit()">
                            <?php foreach ($companies_list as $comp): 
                                $selected = ($comp->id == $selected_company_id) ? 'selected' : '';
                            ?>
                                <option value="<?php echo $comp->id; ?>" <?php echo $selected; ?>><?php echo $comp->name; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>

                    <?php if ($selected_company_id): ?>
                        <form method="post" action="">
                            <input type="hidden" name="action" value="savesettings">
                            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                            <input type="hidden" name="company_id" value="<?php echo $selected_company_id; ?>">
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div>
                                    <div class="form-group" style="margin-bottom: 15px;">
                                        <label style="display: block; margin-bottom: 5px; color: var(--text-secondary);">Enable Cloud Offload</label>
                                        <input type="checkbox" name="enabled" value="1" <?php echo ($company_settings && $company_settings->enabled) ? 'checked' : ''; ?>>
                                    </div>
                                    <div class="form-group" style="margin-bottom: 15px;">
                                        <label style="display: block; margin-bottom: 5px; color: var(--text-secondary);">Provider</label>
                                        <select name="provider" class="filter-select" style="width: 100%;">
                                            <option value="aws" <?php echo ($company_settings && $company_settings->provider == 'aws') ? 'selected' : ''; ?>>AWS (SQS + SES)</option>
                                            <option value="cloudflare" <?php echo ($company_settings && $company_settings->provider == 'cloudflare') ? 'selected' : ''; ?>>Cloudflare (Workers)</option>
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <h6 style="color: var(--text-primary); margin-bottom: 10px;">AWS Credentials</h6>
                                    <input type="text" name="aws_access_key" class="filter-input" style="width: 100%; margin-bottom: 10px;" placeholder="Access Key" value="<?php echo $company_settings->aws_access_key ?? ''; ?>">
                                    <input type="password" name="aws_secret_key" class="filter-input" style="width: 100%; margin-bottom: 10px;" placeholder="Secret Key" value="<?php echo $company_settings->aws_secret_key ?? ''; ?>">
                                    <input type="text" name="aws_region" class="filter-input" style="width: 100%; margin-bottom: 10px;" placeholder="Region (e.g. us-east-1)" value="<?php echo $company_settings->aws_region ?? 'us-east-1'; ?>">
                                    <input type="text" name="sqs_queue_url" class="filter-input" style="width: 100%; margin-bottom: 10px;" placeholder="SQS Queue URL" value="<?php echo $company_settings->sqs_queue_url ?? ''; ?>">
                                    <input type="text" name="ses_sender_email" class="filter-input" style="width: 100%; margin-bottom: 10px;" placeholder="SES Sender Email" value="<?php echo $company_settings->ses_sender_email ?? ''; ?>">
                                </div>
                            </div>
                            <button type="submit" class="export-btn" style="margin-top: 20px;">Save Configuration</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- CERTIFICATES TAB (Similar structure, using cert data) -->
        <div id="tab-certificates" class="tab-content">
             <!-- KPIs -->
             <div class="kpi-cards">
                <div class="bento-card card-span-1">
                    <div class="card-header"><div class="card-title">Active Cert Jobs</div></div>
                    <div class="card-value"><?php echo $cert_stats['active_jobs']; ?></div>
                </div>
                <div class="bento-card card-span-1">
                    <div class="card-header"><div class="card-title">Generated Today</div></div>
                    <div class="card-value"><?php echo $cert_stats['completed_today']; ?></div>
                </div>
            </div>
            
            <div class="bento-grid">
                 <!-- Active Cert Jobs Table -->
                 <div class="bento-card card-span-2">
                    <div class="card-header"><div class="card-title">Active Certificate Jobs</div></div>
                    <?php if ($active_cert_jobs): ?>
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead><tr><th class="table-header">ID</th><th class="table-header">Status</th><th class="table-header">Progress</th></tr></thead>
                            <tbody>
                                <?php foreach ($active_cert_jobs as $job): 
                                    $progress = $job->email_count > 0 ? round(($job->emails_sent / $job->email_count) * 100) : 0;
                                ?>
                                <tr class="table-row">
                                    <td class="table-cell"><?php echo $job->id; ?></td>
                                    <td class="table-cell"><span class="status-badge status-active"><?php echo $job->status; ?></span></td>
                                    <td class="table-cell">
                                        <div class="progress-bar-slim"><div class="progress-fill" style="width: <?php echo $progress; ?>%; background: var(--accent-secondary);"></div></div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="padding: 20px; text-align: center; color: var(--text-secondary);">No active certificate jobs.</div>
                    <?php endif; ?>
                </div>
            </div>

        </div>


<!-- COMPANY TAB -->
<div id="tab-companies" class="tab-content">
    <!-- Company Filter Bar -->
        <div class="bento-card card-span-4" style="margin-bottom: 24px; padding: 16px 24px;">
            <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                <div class="filter-item" style="flex: 1; min-width: 300px;">
                    <i class="fa-solid fa-magnifying-glass" style="color: var(--accent-primary);"></i>
                    <input type="text" class="filter-input" placeholder="Search Companies..." style="width: 100%; min-width: 250px;" id="companySearchInput">
                </div>
            </div>
        </div>

        <!-- Company KPIs -->
        <div class="kpi-cards">
            <div class="bento-card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-building" style="color: var(--accent-primary);"></i> Total Companies</div>
                </div>
                <div class="card-value"><?php echo $company_metrics['total_companies']; ?></div>
                <div class="card-trend trend-up"><i class="fa-solid fa-arrow-up"></i> Active</div>
            </div>

            <div class="bento-card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-users" style="color: var(--accent-success);"></i> Total Company Users</div>
                </div>
                <div class="card-value"><?php echo $company_metrics['total_users']; ?></div>
                <div class="card-trend trend-up"><i class="fa-solid fa-arrow-up"></i> Enrolled</div>
            </div>

            <div class="bento-card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-chart-line" style="color: var(--accent-warning);"></i> Avg Completion</div>
                </div>
                <div class="card-value"><?php echo $company_metrics['avg_completion']; ?>%</div>
                <div class="card-trend trend-up"><i class="fa-solid fa-arrow-up"></i> Global</div>
            </div>

            <div class="bento-card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-book-open" style="color: var(--accent-secondary);"></i> Assigned Courses</div>
                </div>
                <div class="card-value"><?php echo $company_metrics['assigned_courses']; ?></div>
                <div class="card-trend trend-up"><i class="fa-solid fa-arrow-up"></i> Total</div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="bento-grid">
            <!-- User Distribution Chart -->
            <div class="bento-card card-span-2">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-chart-pie"></i> User Distribution</div>
                </div>
                <div style="height: 300px; position: relative;">
                    <canvas id="companyUserDistChart"></canvas>
                </div>
            </div>

            <!-- Performance Leaderboard Chart -->
            <div class="bento-card card-span-2">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-trophy"></i> Performance Leaderboard</div>
                </div>
                <div style="height: 300px; position: relative;">
                    <canvas id="companyPerfChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Company Performance Matrix Table -->
        <div class="bento-grid">
            <div class="bento-card card-span-4">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-table"></i> Company Performance Matrix</div>
                    <button class="export-btn" onclick="triggerExport('course_completion', 'csv')">
                        <i class="fa-solid fa-download"></i> Export CSV
                    </button>
                </div>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th class="table-header">Company Name</th>
                                <th class="table-header">Users</th>
                                <th class="table-header">Courses</th>
                                <th class="table-header">Enrollments</th>
                                <th class="table-header">Completions</th>
                                <th class="table-header">Progress</th>
                                <th class="table-header" style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($company_list)): ?>
                                <?php foreach ($company_list as $company): ?>
                                <tr class="table-row">
                                    <td class="table-cell" style="font-weight: 600;"><?php echo $company['name']; ?></td>
                                    <td class="table-cell"><?php echo $company['users']; ?></td>
                                    <td class="table-cell"><?php echo $company['courses']; ?></td>
                                    <td class="table-cell"><?php echo $company['enrolled']; ?></td>
                                    <td class="table-cell"><?php echo $company['completed']; ?></td>
                                    <td class="table-cell">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <div class="progress-bar-slim" style="flex: 1;">
                                                <div class="progress-fill" style="width: <?php echo $company['completion_rate']; ?>%; background: var(--accent-success);"></div>
                                            </div>
                                            <span style="font-size: 12px; color: var(--text-secondary); min-width: 40px;"><?php echo $company['completion_rate']; ?>%</span>
                                        </div>
                                    </td>
                                    <td class="table-cell" style="text-align: right;">
                                        <a href="#" class="action-link">View Report</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="table-cell" style="text-align: center;">No companies found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
            </div>
        </div>
    </div>


    </div>

            <!-- USERS TAB -->
            <div id="tab-users" class="tab-content">
                <!-- KPI Cards -->
                <div class="kpi-cards">
                    <!-- Total Users -->
                    <div class="bento-card card-span-1">
                        <div class="card-content-wrapper">
                            <div class="card-header"><div class="card-title"><i class="fa-solid fa-users" style="color: var(--accent-primary);"></i> Total Users</div></div>
                            <div class="card-value"><?php echo number_format($users_metrics['total_users']); ?></div>
                            <div class="card-subtext">All time registered</div>
                        </div>
                    </div>
                    <!-- Active Today -->
                    <div class="bento-card card-span-1">
                        <div class="card-content-wrapper">
                            <div class="card-header"><div class="card-title"><i class="fa-solid fa-user-clock" style="color: var(--accent-success);"></i> Active Today</div></div>
                            <div class="card-value"><?php echo number_format($users_metrics['active_today']); ?></div>
                            <div class="card-subtext">Users logged in today</div>
                        </div>
                    </div>
                    <!-- Suspended -->
                    <div class="bento-card card-span-1">
                        <div class="card-content-wrapper">
                            <div class="card-header"><div class="card-title"><i class="fa-solid fa-user-slash" style="color: var(--accent-danger);"></i> Suspended</div></div>
                            <div class="card-value"><?php echo number_format($users_metrics['suspended_users']); ?></div>
                            <div class="card-subtext">Inactive accounts</div>
                        </div>
                    </div>
                    <!-- New Users -->
                    <div class="bento-card card-span-1">
                        <div class="card-content-wrapper">
                            <div class="card-header"><div class="card-title"><i class="fa-solid fa-user-plus" style="color: var(--accent-warning);"></i> New Users</div></div>
                            <div class="card-value"><?php echo number_format($users_metrics['new_users']); ?></div>
                            <div class="card-subtext">Last 30 days</div>
                        </div>
                    </div>
                </div>

                <!-- Filter Bar -->
                <div class="filter-area" style="margin-top: 20px; margin-bottom: 20px;">
                    <div class="filter-item">
                        <i class="fa-solid fa-search" style="color: var(--text-secondary);"></i>
                        <input type="text" id="userSearchInput" class="filter-input" placeholder="Search users..." value="<?php echo s($user_search); ?>">
                    </div>
                    <div class="filter-item">
                        <select id="userRoleSelect" class="filter-select" onchange="applyUserFilters()">
                            <option value="">All Roles</option>
                            <option value="student" <?php echo $user_role === 'student' ? 'selected' : ''; ?>>Student</option>
                            <option value="teacher" <?php echo $user_role === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                            <option value="manager" <?php echo $user_role === 'manager' ? 'selected' : ''; ?>>Manager</option>
                        </select>
                    </div>
                    <div class="filter-item">
                        <select id="userStatusSelect" class="filter-select" onchange="applyUserFilters()">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $user_status === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="suspended" <?php echo $user_status === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                        </select>
                    </div>
                    <button class="export-btn" onclick="applyUserFilters()">Apply</button>
                </div>

                <!-- Users Table -->
                <div class="bento-card" style="padding: 0; overflow: hidden;">
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="border-bottom: 1px solid var(--glass-border);">
                                    <th class="table-header" style="padding: 16px 24px;">USER</th>
                                    <th class="table-header">ROLE</th>
                                    <th class="table-header">STATUS</th>
                                    <th class="table-header" style="text-align: center;">ENROLLED</th>
                                    <th class="table-header" style="text-align: center;">IN PROGRESS</th>
                                    <th class="table-header" style="text-align: center;">COMPLETED</th>
                                    <th class="table-header">COMPLETION %</th>
                                    <th class="table-header" style="text-align: center;">AVG SCORE</th>
                                    <th class="table-header">LAST ACTIVE</th>
                                    <th class="table-header" style="text-align: right; padding-right: 24px;">ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($users_list)): ?>
                                    <?php foreach ($users_list as $user): ?>
                                    <tr class="table-row" style="border-bottom: 1px solid var(--glass-border);">
                                        <td class="table-cell" style="padding: 16px 24px;">
                                            <div style="display: flex; align-items: center; gap: 12px;">
                                                <div class="avatar-circle" style="width: 40px; height: 40px; border-radius: 50%; background: var(--accent-primary); display: flex; align-items: center; justify-content: center; font-weight: 600; color: white;">
                                                    <?php echo strtoupper(substr($user['name'], 0, 2)); ?>
                                                </div>
                                                <div>
                                                    <div style="font-weight: 600; color: var(--text-primary);"><?php echo $user['name']; ?></div>
                                                    <div style="font-size: 12px; color: var(--text-secondary);"><?php echo $user['email']; ?></div>
                                                    <?php if ($user['company']): ?>
                                                        <div style="font-size: 11px; color: var(--accent-secondary);"><?php echo $user['company']; ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="table-cell">
                                            <span class="badge <?php echo $user['role_class']; ?>" style="padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 500; background: rgba(99, 102, 241, 0.1); color: var(--accent-primary);">
                                                <?php echo $user['role']; ?>
                                            </span>
                                        </td>
                                        <td class="table-cell">
                                            <span class="status-dot <?php echo $user['status'] === 'Active' ? 'dot-success' : 'dot-danger'; ?>" style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 6px;"></span>
                                            <span style="color: <?php echo $user['status'] === 'Active' ? 'var(--accent-success)' : 'var(--accent-danger)'; ?>; font-size: 13px;"><?php echo $user['status']; ?></span>
                                        </td>
                                        <td class="table-cell" style="text-align: center; font-weight: 600;"><?php echo $user['enrolled']; ?></td>
                                        <td class="table-cell" style="text-align: center; color: var(--accent-warning);"><?php echo $user['in_progress']; ?></td>
                                        <td class="table-cell" style="text-align: center; color: var(--accent-success);"><?php echo $user['completed']; ?></td>
                                        <td class="table-cell">
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <span style="font-weight: 600; color: <?php echo $user['completion_rate'] >= 50 ? 'var(--accent-success)' : 'var(--accent-warning)'; ?>"><?php echo $user['completion_rate']; ?>%</span>
                                                <div class="progress-bar-slim" style="width: 60px; height: 4px; background: rgba(255,255,255,0.1); border-radius: 2px;">
                                                    <div style="width: <?php echo $user['completion_rate']; ?>%; height: 100%; background: <?php echo $user['completion_rate'] >= 50 ? 'var(--accent-success)' : 'var(--accent-warning)'; ?>; border-radius: 2px;"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="table-cell" style="text-align: center; font-weight: 600; color: var(--accent-secondary);"><?php echo $user['avg_score']; ?></td>
                                        <td class="table-cell" style="color: var(--text-secondary); font-size: 13px;"><?php echo $user['last_active']; ?></td>
                                        <td class="table-cell" style="text-align: right; padding-right: 24px;">
                                            <div style="display: flex; align-items: center; justify-content: flex-end; gap: 12px;">
                                                <i class="fa-regular fa-eye action-icon" style="cursor: pointer; color: var(--text-secondary);" title="View Details"></i>
                                                <i class="fa-regular fa-envelope action-icon" style="cursor: pointer; color: var(--text-secondary);" title="Email User"></i>
                                                <i class="fa-solid fa-ellipsis-vertical action-icon" style="cursor: pointer; color: var(--text-secondary);"></i>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="10" class="table-cell" style="text-align: center; padding: 40px;">No users found matching your criteria.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($users_pagination['total_pages'] > 1): ?>
                    <div style="padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--glass-border);">
                        <div style="color: var(--text-secondary); font-size: 13px;">
                            Showing <?php echo (($users_pagination['current_page'] - 1) * $users_pagination['per_page']) + 1; ?> to <?php echo min($users_pagination['current_page'] * $users_pagination['per_page'], $users_pagination['total_records']); ?> of <?php echo $users_pagination['total_records']; ?> users
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button class="export-btn" <?php echo $users_pagination['current_page'] <= 1 ? 'disabled' : ''; ?> onclick="changeUserPage(<?php echo $users_pagination['current_page'] - 1; ?>)" style="padding: 6px 12px; font-size: 12px;">Previous</button>
                            <span style="display: flex; align-items: center; padding: 0 8px; color: var(--text-primary); font-size: 13px;">Page <?php echo $users_pagination['current_page']; ?> of <?php echo $users_pagination['total_pages']; ?></span>
                            <button class="export-btn" <?php echo $users_pagination['current_page'] >= $users_pagination['total_pages'] ? 'disabled' : ''; ?> onclick="changeUserPage(<?php echo $users_pagination['current_page'] + 1; ?>)" style="padding: 6px 12px; font-size: 12px;">Next</button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- REPORTS TAB -->
            <div id="tab-reports" class="tab-content">
                <div class="alert alert-info">Reports content coming soon...</div>
            </div>
        </div>
    </div>

<script>


// Toggle Theme
function toggleTheme() {
    const body = document.body;
    const icon = document.querySelector('.theme-toggle-thumb i');
    const isLight = body.getAttribute('data-theme') === 'light';
    
    if (isLight) {
        body.removeAttribute('data-theme');
        icon.className = 'fa-solid fa-moon';
    } else {
        body.setAttribute('data-theme', 'light');
        icon.className = 'fa-solid fa-sun';
    }

    // Update Chart Gaps if chart exists
    if (typeof window.userRolesChart !== 'undefined') {
        const newGapColor = isLight ? '#1e293b' : '#ffffff'; 
        window.userRolesChart.data.datasets.forEach(dataset => {
            dataset.borderColor = newGapColor;
        });
        window.userRolesChart.update();
    }
}

// Switch Tabs (Robust Version)
function switchTab(tabName) {
    // Remove active class from all tabs
    document.querySelectorAll('.tab-item').forEach(tab => tab.classList.remove('active'));
    
    // Add active class to clicked tab
    const tabBtn = document.querySelector(`.tab-item[onclick*="'${tabName}'"]`);
    if (tabBtn) tabBtn.classList.add('active');

    // Switch content
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    const targetContent = document.getElementById('tab-' + tabName);
    if (targetContent) targetContent.classList.add('active');
    
    // Persist selection
    localStorage.setItem('activeTab', tabName);
}

// Date Filter Logic
function setDateFilter(range) {
    const event = window.event;
    if (event) {
        const buttons = document.querySelectorAll('.quick-filter-btn');
        buttons.forEach(btn => btn.classList.remove('active'));
        if (event.target) event.target.classList.add('active');
    }

    const today = new Date();
    let startDate = new Date();

    switch(range) {
        case '1W': startDate.setDate(today.getDate() - 7); break;
        case '1M': startDate.setMonth(today.getMonth() - 1); break;
        case '3M': startDate.setMonth(today.getMonth() - 3); break;
        case 'YTD': startDate = new Date(today.getFullYear(), 0, 1); break;
        case 'ALL': startDate = new Date(2000, 0, 1); break;
    }

    const formatDate = (date) => {
        const d = date.getDate().toString().padStart(2, '0');
        const m = (date.getMonth() + 1).toString().padStart(2, '0');
        const y = date.getFullYear();
        return `${d}-${m}-${y}`;
    };

    const startElem = document.getElementById('dateStart');
    const endElem = document.getElementById('dateEnd');
    if (startElem) startElem.value = formatDate(startDate);
    if (endElem) endElem.value = formatDate(today);

    console.log(`Filter applied: ${range}`);
}

// Apply Date Filter from date inputs
function applyDateFilter() {
    const startInput = document.getElementById('dateStart');
    const endInput = document.getElementById('endEnd');
    
    if (startInput && endInput) {
        const url = new URL(window.location.href);
        if (startInput.value) {
            url.searchParams.set('start', startInput.value);
        }
        if (endInput.value) {
            url.searchParams.set('end', endInput.value);
        }
        window.location.href = url.toString();
    }
}

// Clear All Filters
function clearAllFilters() {
    const baseUrl = window.location.origin + window.location.pathname;
    localStorage.removeItem('activeTab');
    window.location.href = baseUrl;
}

// Export Logic
function toggleExportMenu() {
    const menu = document.getElementById('exportDropdown');
    if (menu) {
        menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
    }
}

function triggerExport(reportType, format) {
    const baseUrl = '/local/manireports/ui/export.php';
    const dateStart = document.getElementById('dateStart').value;
    const dateEnd = document.getElementById('dateEnd').value;
    
    // Construct URL
    let url = `${baseUrl}?report=${reportType}&format=${format}`;
    if (dateStart) url += `&datefrom=${dateStart}`;
    if (dateEnd) url += `&dateto=${dateEnd}`;
    
    // Trigger download in same tab
    window.location.href = url;
}

// Close menu when clicking outside
document.addEventListener('click', function(event) {
    const menu = document.getElementById('export-menu');
    const btn = document.querySelector('.export-btn[onclick="toggleExportMenu()"]');
    if (menu && btn && !menu.contains(event.target) && !btn.contains(event.target)) {
        menu.style.display = 'none';
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { x: { display: false }, y: { display: false } },
        elements: { point: { radius: 0 }, line: { tension: 0.4 } }
    };

    // Helper to safely init chart
    const initChart = (id, config) => {
        const el = document.getElementById(id);
        if (el) new Chart(el, config);
    };

    // KPI Mini Charts
    initChart('chartCompanies', {
        type: 'line',
        data: { labels: [1,2,3,4,5,6,7], datasets: [{ data: [18, 19, 20, 21, 22, 23, 24], borderColor: '#6366f1', borderWidth: 2, fill: true, backgroundColor: (ctx) => {
            const gradient = ctx.chart.ctx.createLinearGradient(0, 0, 0, 60);
            gradient.addColorStop(0, 'rgba(99, 102, 241, 0.2)');
            gradient.addColorStop(1, 'rgba(99, 102, 241, 0)');
            return gradient;
        }}]},
        options: commonOptions
    });

    initChart('chartCourses', {
        type: 'line',
        data: { labels: [1,2,3,4,5,6,7], datasets: [{ data: [140, 145, 148, 150, 152, 154, 156], borderColor: '#10b981', borderWidth: 2, fill: true, backgroundColor: (ctx) => {
            const gradient = ctx.chart.ctx.createLinearGradient(0, 0, 0, 60);
            gradient.addColorStop(0, 'rgba(16, 185, 129, 0.2)');
            gradient.addColorStop(1, 'rgba(16, 185, 129, 0)');
            return gradient;
        }}]},
        options: commonOptions
    });

    initChart('chartUsers', {
        type: 'line',
        data: { labels: [1,2,3,4,5,6,7], datasets: [{ data: [8500, 8510, 8520, 8530, 8535, 8540, 8542], borderColor: '#f59e0b', borderWidth: 2, fill: true, backgroundColor: (ctx) => {
            const gradient = ctx.chart.ctx.createLinearGradient(0, 0, 0, 60);
            gradient.addColorStop(0, 'rgba(245, 158, 11, 0.2)');
            gradient.addColorStop(1, 'rgba(245, 158, 11, 0)');
            return gradient;
        }}]},
        options: commonOptions
    });

    // Active Users Chart
    initChart('activeUsersChart', {
        type: 'bar',
        data: {
            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            datasets: [{
                label: 'Active Users',
                data: [120, 150, 180, 170, 160, 90, 100],
                backgroundColor: '#6366f1',
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { grid: { color: 'rgba(255, 255, 255, 0.05)' }, ticks: { color: '#94a3b8' } },
                x: { grid: { display: false }, ticks: { color: '#94a3b8' } }
            }
        }
    });

    // Time Spent Chart
    initChart('timeSpentChart', {
        type: 'line',
        data: {
            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            datasets: [{
                label: 'Avg Minutes',
                data: [45, 50, 60, 55, 40, 30, 35],
                borderColor: '#8b5cf6',
                backgroundColor: 'rgba(139, 92, 246, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { grid: { color: 'rgba(255, 255, 255, 0.05)' }, ticks: { color: '#94a3b8' } },
                x: { grid: { display: false }, ticks: { color: '#94a3b8' } }
            }
        }
    });

    // Completion Trend Chart
    initChart('completionTrendChart', {
        type: 'line',
        data: {
            labels: <?php echo json_encode($trend_data['labels']); ?>,
            datasets: [
                {
                    label: 'Enrolled',
                    data: <?php echo json_encode($trend_data['enrollments']); ?>,
                    borderColor: '#10b981',
                    backgroundColor: (ctx) => {
                        const gradient = ctx.chart.ctx.createLinearGradient(0, 0, 0, 300);
                        gradient.addColorStop(0, 'rgba(16, 185, 129, 0.2)');
                        gradient.addColorStop(1, 'rgba(16, 185, 129, 0)');
                        return gradient;
                    },
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'Completed',
                    data: <?php echo json_encode($trend_data['completions']); ?>,
                    borderColor: '#f59e0b',
                    backgroundColor: (ctx) => {
                        const gradient = ctx.chart.ctx.createLinearGradient(0, 0, 0, 300);
                        gradient.addColorStop(0, 'rgba(245, 158, 11, 0.2)');
                        gradient.addColorStop(1, 'rgba(245, 158, 11, 0)');
                        return gradient;
                    },
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    labels: { color: '#94a3b8', font: { family: 'Outfit' }, usePointStyle: true, boxWidth: 6 },
                    position: 'top',
                    align: 'end'
                }
            },
            scales: {
                y: { grid: { color: 'rgba(255, 255, 255, 0.05)' }, ticks: { color: '#94a3b8', font: { family: 'Outfit' } } },
                x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { family: 'Outfit' } } }
            }
        }
    });

    // User Roles Donut Chart
    const roleData = {
        admin: <?php echo $role_data['admin']; ?>,
        teacher: <?php echo $role_data['teacher']; ?>,
        student: <?php echo $role_data['student']; ?>
    };
    const maxVal = Math.max(roleData.student, roleData.teacher, roleData.admin, 1);
    const logMax = Math.log10(maxVal + 10);
    const getScaledValue = (val) => {
        if (val === 0) return 0;
        const logVal = Math.log10(val + 1);
        const ratio = logVal / logMax;
        return Math.max(ratio * 100, 15); 
    };
    const adminPct = getScaledValue(roleData.admin);
    const teacherPct = getScaledValue(roleData.teacher);
    const studentPct = getScaledValue(roleData.student);
    const trackColor = 'rgba(148, 163, 184, 0.15)'; 
    const isLightMode = document.body.getAttribute('data-theme') === 'light';
    const gapColor = isLightMode ? '#ffffff' : '#1e293b';
    const gapWidth = 4; 
    
    const roleChartEl = document.getElementById('chartUserRoles');
    if (roleChartEl) {
        window.userRolesChart = new Chart(roleChartEl, {
            type: 'doughnut',
            data: {
                labels: ['Student', 'Teacher', 'Admin'], 
                datasets: [
                    {
                        data: [studentPct, 100 - studentPct],
                        backgroundColor: ['#ef4444', trackColor],
                        borderWidth: gapWidth,
                        borderColor: gapColor,
                        borderRadius: [20, 0],
                        cutout: '50%'
                    },
                    {
                        data: [teacherPct, 100 - teacherPct],
                        backgroundColor: ['#f59e0b', trackColor],
                        borderWidth: gapWidth,
                        borderColor: gapColor,
                        borderRadius: [20, 0],
                        cutout: '50%'
                    },
                    {
                        data: [adminPct, 100 - adminPct],
                        backgroundColor: ['#10b981', trackColor],
                        borderWidth: gapWidth,
                        borderColor: gapColor,
                        borderRadius: [20, 0],
                        cutout: '50%'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { 
                        enabled: true,
                        callbacks: {
                            label: function(context) {
                                const label = context.chart.data.labels[context.datasetIndex];
                                const realValue = roleData[label.toLowerCase()];
                                return `${label}: ${realValue}`;
                            }
                        }
                    }
                },
                animation: { animateScale: true, animateRotate: true }
            }
        });
    }

    // Live Update Timer
    let seconds = 0;
    setInterval(function() {
        seconds++;
        const timerEl = document.getElementById('live-update-timer');
        if (timerEl) timerEl.innerText = seconds;
    }, 1000);

    // 24h Timeline Chart
    const timelineEl = document.getElementById('timelineChart');
    if (timelineEl) {
        const timelineCtx = timelineEl.getContext('2d');
        const timelineGradient = timelineCtx.createLinearGradient(0, 0, 0, 200);
        timelineGradient.addColorStop(0, 'rgba(16, 185, 129, 0.2)');
        timelineGradient.addColorStop(1, 'rgba(16, 185, 129, 0)');

        new Chart(timelineCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($live_stats['timeline_labels']); ?>,
                datasets: [{
                    label: 'Active Users',
                    data: <?php echo json_encode($live_stats['timeline_data']); ?>,
                    borderColor: '#10b981',
                    backgroundColor: timelineGradient,
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        titleColor: '#94a3b8',
                        bodyColor: '#f8fafc',
                        borderColor: 'rgba(148, 163, 184, 0.1)',
                        borderWidth: 1
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(148, 163, 184, 0.1)' },
                        ticks: { color: '#94a3b8', font: { size: 10 } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#94a3b8', font: { size: 10 }, maxTicksLimit: 6 }
                    }
                }
            }
        });
    }

    // --- COURSES TAB CHARTS ---
    
    // Enrollment Trends
    const enrollEl = document.getElementById('enrollmentTrendChart');
    if (enrollEl) {
        const enrollCtx = enrollEl.getContext('2d');
        const enrollGradient = enrollCtx.createLinearGradient(0, 0, 0, 300);
        enrollGradient.addColorStop(0, 'rgba(99, 102, 241, 0.3)');
        enrollGradient.addColorStop(1, 'rgba(99, 102, 241, 0)');

        new Chart(enrollCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($courses_trends['labels']); ?>,
                datasets: [{
                    label: 'Enrollments',
                    data: <?php echo json_encode($courses_trends['data']); ?>,
                    borderColor: '#6366f1',
                    backgroundColor: enrollGradient,
                    borderWidth: 3,
                    pointBackgroundColor: '#6366f1',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: '#6366f1',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        titleColor: '#94a3b8',
                        bodyColor: '#f8fafc',
                        borderColor: 'rgba(148, 163, 184, 0.1)',
                        borderWidth: 1
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(148, 163, 184, 0.1)' },
                        ticks: { color: '#94a3b8' }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#94a3b8' }
                    }
                }
            }
        });
    }

    // Category Distribution
    const catEl = document.getElementById('categoryDistChart');
    if (catEl) {
        const catCtx = catEl.getContext('2d');
        const rawCatData = <?php echo json_encode($courses_dist); ?>;
        const processedCatLabels = [];
        const processedCatCounts = [];
        
        if (Array.isArray(rawCatData)) {
            rawCatData.forEach(item => {
                processedCatLabels.push(item.name);
                processedCatCounts.push(item.count);
            });
        } else if (typeof rawCatData === 'object' && rawCatData !== null) {
             Object.values(rawCatData).forEach(item => {
                processedCatLabels.push(item.name);
                processedCatCounts.push(item.count);
             });
        }

        new Chart(catCtx, {
            type: 'doughnut',
            data: {
                labels: processedCatLabels,
                datasets: [{
                    data: processedCatCounts,
                    backgroundColor: ['#6366f1', '#8b5cf6', '#10b981', '#f59e0b', '#ef4444'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: { color: '#94a3b8', font: { size: 11 }, boxWidth: 12 }
                    }
                },
                cutout: '70%'
            }
        });
    }

    // --- COMPANY TAB CHARTS ---
    
    // Company User Distribution (Doughnut)
    const companyDistEl = document.getElementById('companyUserDistChart');
    if (companyDistEl) {
        const companyDistCtx = companyDistEl.getContext('2d');
        const rawCompanyDist = <?php echo json_encode($company_dist); ?>;
        const companyLabels = [];
        const companyCounts = [];
        
        if (Array.isArray(rawCompanyDist)) {
            rawCompanyDist.forEach(item => {
                companyLabels.push(item.name);
                companyCounts.push(item.count);
            });
        }

        new Chart(companyDistCtx, {
            type: 'doughnut',
            data: {
                labels: companyLabels,
                datasets: [{
                    data: companyCounts,
                    backgroundColor: ['#6366f1', '#8b5cf6', '#10b981', '#f59e0b', '#ef4444'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: { color: '#94a3b8', font: { size: 11 }, boxWidth: 12 }
                    }
                },
                cutout: '70%'
            }
        });
    }

    // Company Performance (Horizontal Bar)
    const companyPerfEl = document.getElementById('companyPerfChart');
    if (companyPerfEl) {
        const companyPerfCtx = companyPerfEl.getContext('2d');
        const rawCompanyPerf = <?php echo json_encode($company_perf); ?>;
        const perfLabels = [];
        const perfRates = [];
        
        if (Array.isArray(rawCompanyPerf)) {
            rawCompanyPerf.forEach(item => {
                perfLabels.push(item.name);
                perfRates.push(item.rate);
            });
        }

        new Chart(companyPerfCtx, {
            type: 'bar',
            data: {
                labels: perfLabels,
                datasets: [{
                    label: 'Completion Rate (%)',
                    data: perfRates,
                    backgroundColor: '#10b981',
                    borderRadius: 8
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        titleColor: '#94a3b8',
                        bodyColor: '#f8fafc',
                        borderColor: 'rgba(148, 163, 184, 0.1)',
                        borderWidth: 1
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        max: 100,
                        grid: { color: 'rgba(148, 163, 184, 0.1)' },
                        ticks: { color: '#94a3b8' }
                    },
                    y: {
                        grid: { display: false },
                        ticks: { color: '#94a3b8' }
                    }
                }
            }
        });
    }

    // --- FILTER LOGIC ---
    const searchInput = document.querySelector('#tab-courses .filter-input[placeholder*="Search"]');
    const catSelect = document.querySelector('#tab-courses .filter-select');
    // Company Tab Filter
    const companySearchInput = document.getElementById('companySearchInput');
    
    function applyCompanyFilters() {
        const search = companySearchInput.value;
        const url = new URL(window.location.href);
        url.searchParams.set('company_q', search);
        localStorage.setItem('activeTab', 'companies');
        window.location.href = url.toString();
    }

    if (companySearchInput) companySearchInput.addEventListener('change', applyCompanyFilters);

    // Check for persisted tab
    const persistedTab = localStorage.getItem('activeTab');
    if (persistedTab) {
        // Use the global switchTab function
        switchTab(persistedTab);
    }
    // --- USERS TAB LOGIC ---
    function applyUserFilters() {
        const search = document.getElementById('userSearchInput').value;
        const role = document.getElementById('userRoleSelect').value;
        const status = document.getElementById('userStatusSelect').value;
        
        const url = new URL(window.location.href);
        url.searchParams.set('user_search', search);
        url.searchParams.set('user_role', role);
        url.searchParams.set('user_status', status);
        url.searchParams.set('user_page', 1); // Reset to page 1 on filter change
        
        localStorage.setItem('activeTab', 'users');
        window.location.href = url.toString();
    }

    window.applyUserFilters = applyUserFilters; // Expose to global scope

    window.changeUserPage = function(page) {
        const url = new URL(window.location.href);
        url.searchParams.set('user_page', page);
        localStorage.setItem('activeTab', 'users');
        window.location.href = url.toString();
    };

    // Enter key support for search
    const userSearchInput = document.getElementById('userSearchInput');
    if (userSearchInput) {
        userSearchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') applyUserFilters();
        });
    }

});
</script>
<style>
@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
    70% { box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
    100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}
</style>

<!-- Job Details Modal -->
<div id="jobDetailsModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 5000; align-items: center; justify-content: center;">
    <div class="bento-card" style="width: 600px; max-height: 80vh; display: flex; flex-direction: column; padding: 0; overflow: hidden;">
        <div class="card-header" style="padding: 24px; border-bottom: 1px solid var(--glass-border);">
            <div class="card-title">Job Details</div>
            <!-- Reminders Tab Content -->
            <div id="tab-reminders" class="tab-content">
                <!-- Reminder KPIs -->
                <div class="kpi-cards">
                    <div class="bento-card card-span-1">
                        <div class="card-header"><div class="card-title"><i class="fa-solid fa-bell" style="color: var(--accent-primary);"></i> Active Rules</div></div>
                        <div class="card-value"><?php echo $reminder_data['kpis']['active_rules']; ?> <span style="font-size: 14px; color: var(--text-secondary);">/ <?php echo $reminder_data['kpis']['total_rules']; ?></span></div>
                    </div>
                    <div class="bento-card card-span-1">
                        <div class="card-header"><div class="card-title"><i class="fa-solid fa-file-code" style="color: var(--accent-success);"></i> Templates</div></div>
                        <div class="card-value"><?php echo $reminder_data['kpis']['total_templates']; ?></div>
                    </div>
                    <div class="bento-card card-span-1">
                        <div class="card-header"><div class="card-title"><i class="fa-solid fa-paper-plane" style="color: var(--accent-warning);"></i> Sent Today</div></div>
                        <div class="card-value"><?php echo $reminder_data['kpis']['activity_today']; ?></div>
                    </div>
                    <div class="bento-card card-span-1">
                        <div class="card-header"><div class="card-title"><i class="fa-solid fa-clock" style="color: var(--accent-secondary);"></i> Status</div></div>
                        <div class="card-value" style="font-size: 18px;">Active</div>
                    </div>
                </div>

                <div class="bento-grid">
                    <!-- Rules List -->
                    <div class="bento-card card-span-2">
                        <div class="card-header">
                            <div class="card-title">Reminder Rules</div>
                            <a href="<?php echo new moodle_url('/local/manireports/ui/reminders.php'); ?>" class="action-link">Manage Rules</a>
                        </div>
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr>
                                    <th class="table-header">Name</th>
                                    <th class="table-header">Trigger</th>
                                    <th class="table-header">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reminder_data['rules'] as $rule): ?>
                                <tr class="table-row">
                                    <td class="table-cell" style="font-weight: 600;"><?php echo format_string($rule->name); ?></td>
                                    <td class="table-cell"><?php echo $rule->trigger_type; ?></td>
                                    <td class="table-cell">
                                        <?php if ($rule->enabled): ?>
                                            <span class="status-badge status-active">Enabled</span>
                                        <?php else: ?>
                                            <span class="status-badge status-inactive">Disabled</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($reminder_data['rules'])): ?>
                                    <tr><td colspan="3" class="table-cell" style="text-align: center;">No rules found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Templates List -->
                    <div class="bento-card card-span-2">
                        <div class="card-header">
                            <div class="card-title">Email Templates</div>
                            <a href="<?php echo new moodle_url('/local/manireports/ui/templates.php'); ?>" class="action-link">Manage Templates</a>
                        </div>
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr>
                                    <th class="table-header">Name</th>
                                    <th class="table-header">Subject</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reminder_data['templates'] as $tmpl): ?>
                                <tr class="table-row">
                                    <td class="table-cell" style="font-weight: 600;"><?php echo format_string($tmpl->name); ?></td>
                                    <td class="table-cell"><?php echo format_string($tmpl->subject); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($reminder_data['templates'])): ?>
                                    <tr><td colspan="2" class="table-cell" style="text-align: center;">No templates found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Recent Activity -->
                    <div class="bento-card card-span-4">
                        <div class="card-header">
                            <div class="card-title">Recent Reminder Activity</div>
                            <a href="<?php echo new moodle_url('/local/manireports/ui/reminder_dashboard.php'); ?>" class="action-link">View Full Log</a>
                        </div>
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr>
                                    <th class="table-header">Recipient</th>
                                    <th class="table-header">Status</th>
                                    <th class="table-header">Time</th>
                                    <th class="table-header">Message ID</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reminder_data['logs'] as $log): ?>
                                <tr class="table-row">
                                    <td class="table-cell"><?php echo $log['recipient']; ?></td>
                                    <td class="table-cell">
                                        <?php 
                                            $status_class = 'status-warning';
                                            if ($log['status'] == 'delivered' || $log['status'] == 'local_sent') $status_class = 'status-active';
                                            if ($log['status'] == 'failed') $status_class = 'status-inactive';
                                        ?>
                                        <span class="status-badge <?php echo $status_class; ?>"><?php echo $log['status']; ?></span>
                                    </td>
                                    <td class="table-cell"><?php echo $log['time']; ?></td>
                                    <td class="table-cell" style="font-family: monospace; font-size: 12px;"><?php echo $log['message_id']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($reminder_data['logs'])): ?>
                                    <tr><td colspan="4" class="table-cell" style="text-align: center;">No recent activity.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Job Details Modal -->
            <div id="jobDetailsModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 5000; align-items: center; justify-content: center; backdrop-filter: blur(5px);">
                <div style="background: var(--glass-bg); border: 1px solid var(--glass-border); width: 600px; max-height: 80vh; border-radius: 24px; display: flex; flex-direction: column; box-shadow: var(--card-shadow);">
                    <div style="padding: 24px; border-bottom: 1px solid var(--glass-border); display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="margin: 0; font-size: 18px;">Job Details</h3>
                        <button onclick="closeJobModal()" style="background: none; border: none; color: var(--text-secondary); cursor: pointer; font-size: 18px;"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                    <div style="padding: 24px; overflow-y: auto; flex: 1;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr>
                                    <th class="table-header">Email</th>
                                    <th class="table-header">Status</th>
                                    <th class="table-header">Sent At</th>
                                    <th class="table-header">Error</th>
                                </tr>
                            </thead>
                            <tbody id="jobDetailsBody">
                                <!-- Content loaded via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

<script>
function viewJobDetails(jobId) {
    const modal = document.getElementById('jobDetailsModal');
    const tbody = document.getElementById('jobDetailsBody');
    modal.style.display = 'flex';
    tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 20px;">Loading...</td></tr>';

    fetch('<?php echo $CFG->wwwroot; ?>/local/manireports/ajax_job_details.php?job_id=' + jobId + '&sesskey=<?php echo sesskey(); ?>')
        .then(response => response.json())
        .then(data => {
            tbody.innerHTML = '';
            if (data.recipients && data.recipients.length > 0) {
                data.recipients.forEach(recip => {
                    let statusColor = recip.status === 'sent' ? 'var(--accent-success)' : 'var(--accent-danger)';
                    let row = `
                        <tr class="table-row">
                            <td class="table-cell">${recip.email}</td>
                            <td class="table-cell"><span style="color: ${statusColor}; font-weight: 600;">${recip.status}</span></td>
                            <td class="table-cell">${recip.sent_at}</td>
                            <td class="table-cell" style="color: var(--accent-danger); font-size: 12px;">${recip.error_message || '-'}</td>
                        </tr>
                    `;
                    tbody.innerHTML += row;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 20px;">No details available.</td></tr>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 20px; color: var(--accent-danger);">Error loading details.</td></tr>';
        });
}

function closeJobModal() {
    document.getElementById('jobDetailsModal').style.display = 'none';
}
</script>

<?php
echo $OUTPUT->footer();
?>
