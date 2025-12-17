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
$PAGE->set_url(new moodle_url('/local/manireports/ui/dashboard.php'));
$PAGE->set_heading(get_string('dashboard', 'local_manireports'));
$PAGE->set_pagelayout('embedded');

// ========================================================================
// PHASE 1: ROLE DETECTION & CONTEXT GATHERING
// ========================================================================
$user_role = 'student'; // Default role
$role_context = []; // Store role-specific context data

// Check capabilities in order of hierarchy: Admin > Manager > Teacher > Student
// Use is_siteadmin() for admin check (more reliable than custom capability)
if (is_siteadmin()) {
    $user_role = 'admin';
    // Admin has access to everything, no filtering needed
    
} elseif (has_capability('local/manireports:viewmanagerdashboard', $context) || 
          has_capability('block/iomad_company_admin:manage', $context) || 
          has_capability('block/iomad_company_admin:administer', $context) ||
          $DB->record_exists_sql("SELECT 1 FROM {role_assignments} ra JOIN {role} r ON r.id = ra.roleid WHERE ra.userid = ? AND (r.shortname = 'companyadmin' OR r.shortname = 'manager' OR r.shortname = 'departmentmanager' OR r.shortname = 'companydepartmentmanager')", [$USER->id])) {
    $user_role = 'manager';
    
    // Get manager's company ID from IOMAD company_users table
    if ($DB->get_manager()->table_exists('company_users')) {
        $company_user = $DB->get_record('company_users', ['userid' => $USER->id]);
        if ($company_user) {
            $role_context['companyid'] = $company_user->companyid;
            
            // Get company name for display
            $company = $DB->get_record('company', ['id' => $company_user->companyid], 'name');
            if ($company) {
                $role_context['companyname'] = $company->name;
            }
        }
    }
    
} elseif (has_capability('local/manireports:viewteacherdashboard', $context) || 
          $DB->record_exists_sql("SELECT 1 FROM {role_assignments} ra JOIN {role} r ON r.id = ra.roleid WHERE ra.userid = ? AND (r.shortname = 'editingteacher' OR r.shortname = 'teacher')", [$USER->id])) {
    $user_role = 'teacher';
    
    // Get courses where user is a teacher (editing or non-editing)
    $teacher_roles = $DB->get_records_sql("SELECT id FROM {role} WHERE shortname IN ('editingteacher', 'teacher')");
    $teacher_role_ids = array_keys($teacher_roles);
    
    if (!empty($teacher_role_ids)) {
        list($in_sql, $params) = $DB->get_in_or_equal($teacher_role_ids, SQL_PARAMS_NAMED, 'role');
        $params['userid'] = $USER->id;
        $params['courselevel'] = CONTEXT_COURSE;
        
        $sql = "SELECT DISTINCT c.id, c.fullname
                FROM {course} c
                JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = :courselevel
                JOIN {role_assignments} ra ON ra.contextid = ctx.id
                WHERE ra.userid = :userid AND ra.roleid $in_sql AND c.id > 1
                ORDER BY c.fullname";
        
        $teaching_courses = $DB->get_records_sql($sql, $params);
        $role_context['course_ids'] = array_keys($teaching_courses);
        $role_context['courses'] = $teaching_courses;
        $role_context['course_count'] = count($teaching_courses);
    }
    
} else {
    // Default to student role
    $user_role = 'student';
    
    // Check if user has student capability in any course
    $student_role = $DB->get_record('role', ['shortname' => 'student']);
    if ($student_role) {
        $sql = "SELECT DISTINCT c.id, c.fullname
                FROM {course} c
                JOIN {enrol} e ON c.id = e.courseid
                JOIN {user_enrolments} ue ON e.id = ue.enrolid
                WHERE ue.userid = :userid AND ue.status = 0 AND c.id > 1
                ORDER BY c.fullname";
        
        $enrolled_courses = $DB->get_records_sql($sql, ['userid' => $USER->id]);
        $role_context['course_ids'] = array_keys($enrolled_courses);
        $role_context['courses'] = $enrolled_courses;
        $role_context['course_count'] = count($enrolled_courses);
    }
}

// Store role info for JavaScript (will be used in Phase 3 for tab filtering)
$role_context['user_role'] = $user_role;
$role_context['user_fullname'] = fullname($USER);

// Debug logging (can be removed later)
error_log("Dashboard Role Detection: User {$USER->id} detected as '{$user_role}'");
if (isset($role_context['companyid'])) {
    error_log("  - Company ID: {$role_context['companyid']}");
}
if (isset($role_context['course_count'])) {
    error_log("  - Courses: {$role_context['course_count']}");
}
// ========================================================================
// END PHASE 1
// ========================================================================


// --- Backend Connection Logic ---
$start_param = optional_param('start', '', PARAM_TEXT);
$end_param = optional_param('end', '', PARAM_TEXT);

// Logic Fix: If start_param is '01-01-2000' (used as dummy for 'ALL'), treat as empty (All Time)
// Timestamp 946684800 is 01-01-2000
if ($start_param == '01-01-2000' || $start_param == '946684800') {
    $start_param = '';
    $end_param = '';
}

// Default: No filter = All Time (Logic handled by empty params)

$start_timestamp = 0;
$end_timestamp = 0;

if ($start_param) {
    // If it's a Unix timestamp (numeric), use it directly
    if (is_numeric($start_param)) {
        $start_timestamp = $start_param;
        // Convert back to d-m-Y for display in inputs
        $start_param = date('d-m-Y', $start_param);
    } else {
        $dt = DateTime::createFromFormat('d-m-Y', $start_param);
        if ($dt) {
            $dt->setTime(0, 0, 0);
            $start_timestamp = $dt->getTimestamp();
        }
    }
}
if ($end_param) {
    // If it's a Unix timestamp
    if (is_numeric($end_param)) {
        $end_timestamp = $end_param;
        $end_param = date('d-m-Y', $end_param);
    } else {
        $dt = DateTime::createFromFormat('d-m-Y', $end_param);
        if ($dt) {
            $dt->setTime(23, 59, 59);
            $end_timestamp = $dt->getTimestamp();
        }
    }
}

// ========================================================================
// PHASE 2: INSTANTIATE ROLE-SPECIFIC DATA LOADER
// ========================================================================
// Load role-specific data loader classes
require_once(__DIR__ . '/../classes/output/manager_data_loader.php');
require_once(__DIR__ . '/../classes/output/teacher_data_loader.php');
require_once(__DIR__ . '/../classes/output/student_data_loader.php');

// Instantiate appropriate data loader based on user role
switch ($user_role) {
    case 'admin':
        // Admin uses base loader (no filtering)
        $loader = new \local_manireports\output\dashboard_data_loader($USER->id, $start_timestamp, $end_timestamp);
        break;
        
    case 'manager':
        // Manager uses company-scoped loader
        $companyid = isset($role_context['companyid']) ? $role_context['companyid'] : 0;
        $loader = new \local_manireports\output\manager_data_loader($USER->id, $companyid, $start_timestamp, $end_timestamp);
        break;
        
    case 'teacher':
        // Teacher uses course-scoped loader
        $course_ids = isset($role_context['course_ids']) ? $role_context['course_ids'] : [];
        $loader = new \local_manireports\output\teacher_data_loader($USER->id, $course_ids, $start_timestamp, $end_timestamp);
        break;
        
    case 'student':
        // Student uses personal data loader
        $loader = new \local_manireports\output\student_data_loader($USER->id, $start_timestamp, $end_timestamp);
        break;
        
    default:
        // Fallback to base loader
        $loader = new \local_manireports\output\dashboard_data_loader($USER->id, $start_timestamp, $end_timestamp);
}

error_log("Dashboard Data Loader: Using " . get_class($loader) . " for role '{$user_role}'");
// ========================================================================
// END PHASE 2
// ========================================================================


// Fetch Data
// 1. KPIs
try {
    $kpi_data = $loader->get_admin_kpis();
} catch (\Exception $e) {
    $kpi_data = ['companies' => 0, 'courses' => 0, 'users' => 0, 'completion_rate' => 0];
    error_log("Error loading KPIs: " . $e->getMessage());
}

// 2. System Health
try {
    $system_health = $loader->get_system_health();
} catch (\Exception $e) {
    $system_health = ['db_size' => '0MB', 'file_size' => '0MB', 'cache_hit_rate' => 0, 'cron_status' => 0];
}

// 3. User Roles
try {
    $role_data = $loader->get_user_roles_distribution();
} catch (\Exception $e) {
    $role_data = ['admin' => 0, 'teacher' => 0, 'student' => 0];
}

// 4. Trend Data
try {
    $trend_data = $loader->get_completion_trends();
} catch (\Exception $e) {
    $trend_data = ['labels' => [], 'enrollments' => [], 'completions' => []];
}

// 5. Company Analytics (New Method)
try {
    $company_data = $loader->get_company_analytics(5);
} catch (\Exception $e) {
     $company_data = []; // Return empty array on failure
}

// 6. Top Courses Analytics (New Method)
try {
    $course_data = $loader->get_top_courses_analytics(10);
} catch (\Exception $e) {
    $course_data = [];
}

// 7. Avg Engagement (Time Spent) - Real Data
try {
    $avg_time_data = $loader->get_avg_daily_engagement();
} catch (\Exception $e) {
    $avg_time_data = ['labels' => [], 'data' => []];
}

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
$filter_role = optional_param('user_role', '', PARAM_TEXT); // Renamed from $user_role to avoid conflict
$user_status = optional_param('user_status', '', PARAM_TEXT);
$user_per_page = 10;

try {
    $users_metrics = $loader->get_users_tab_metrics();
} catch (\Exception $e) {
    $users_metrics = ['total_users' => 0, 'active_today' => 0, 'suspended_users' => 0, 'new_users' => 0];
}

try {
    $users_list_data = $loader->get_comprehensive_user_list($user_page, $user_per_page, $user_search, $filter_role, $user_status);
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

// New reminder data with error handling
try {
    $company_reminder_stats = $loader->get_company_reminder_stats();
} catch (Exception $e) {
    $company_reminder_stats = [];
    error_log('Error loading company reminder stats: ' . $e->getMessage());
}

try {
    $unified_reminder_status = $loader->get_unified_reminder_status();
} catch (Exception $e) {
    $unified_reminder_status = [];
    error_log('Error loading unified reminder status: ' . $e->getMessage());
}

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
    redirect(new moodle_url('/local/manireports/ui/dashboard.php', ['companyid' => $settings->company_id]), 'Settings saved', null, \core\output\notification::NOTIFY_SUCCESS);
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
/* Dashboard Styles */
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
    transition: var(--transition); font-size: 13px; /* Removed min-width global, added locally if needed */
}
.filter-input { min-width: 120px; }
.quick-filter-btn { min-width: 0 !important; width: 100%; text-align: center; }
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
.trend-up { color: var(--accent-success); }
.trend-down { color: var(--accent-danger); }
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

/* Status Badge Styles */
.status-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    cursor: help;
    transition: var(--transition);
}
.status-badge:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}
.status-info {
    background: rgba(59, 130, 246, 0.15);
    color: #3b82f6;
    border: 1px solid rgba(59, 130, 246, 0.3);
}
.status-success {
    background: rgba(34, 197, 94, 0.15);
    color: #22c55e;
    border: 1px solid rgba(34, 197, 94, 0.3);
}
.status-warning {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
    border: 1px solid rgba(245, 158, 11, 0.3);
}
.status-inactive {
    background: rgba(148, 163, 184, 0.15);
    color: #94a3b8;
    border: 1px solid rgba(148, 163, 184, 0.3);
}
.status-active {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.3);
}

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

/* Premium Glassmorphic List Styles */
.glass-table-container {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.glass-list-item {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid var(--glass-border);
    border-radius: 16px;
    padding: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}
[data-theme="light"] .glass-list-item {
    background: rgba(255, 255, 255, 0.6);
}
.glass-list-item:hover {
    background: rgba(255, 255, 255, 0.08);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
    border-color: rgba(99, 102, 241, 0.3);
}
[data-theme="light"] .glass-list-item:hover {
    background: rgba(255, 255, 255, 0.9);
}
.item-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: rgba(99, 102, 241, 0.1);
    color: var(--accent-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}
.item-info {
    flex: 1;
    margin-left: 20px;
    min-width: 0; /* Prevent flex overflow */
}
.item-title {
    font-size: 15px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: flex;
    align-items: center;
    gap: 8px;
}
.item-subtitle {
    font-size: 13px;
    color: var(--text-secondary);
    display: flex;
    gap: 12px;
}
.data-field-group {
    display: flex;
    align-items: center;
    gap: 24px;
    margin-right: 24px;
}
.data-field {
    text-align: left;
    min-width: 100px;
}
.data-label {
    font-size: 11px;
    text-transform: uppercase;
    color: var(--text-secondary);
    margin-bottom: 2px;
    letter-spacing: 0.5px;
}
.data-value {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
}
.meta-badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-secondary);
    border: 1px solid var(--glass-border);
}
.action-btn-kebab {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-secondary);
    cursor: pointer;
    transition: var(--transition);
}
.action-btn-kebab:hover {
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-primary);
}
.progress-ring {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 160px;
}
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
        <div class="tab-menu" style="display: flex; align-items: center; padding-right: 20px;">
            <!-- PHASE 3: Added data-roles attributes for tab filtering -->
            <div class="tab-item active" onclick="switchTab('overview')" data-tab="overview" data-roles="admin,manager,teacher,student"><i class="fa-solid fa-grid-2"></i> Overview</div>
            <div class="tab-item" onclick="switchTab('courses')" data-tab="courses" data-roles="admin,manager,teacher,student"><i class="fa-solid fa-book-open"></i> Courses</div>
            <div class="tab-item" onclick="switchTab('companies')" data-tab="companies" data-roles="admin"><i class="fa-solid fa-building"></i> Companies</div>
            <div class="tab-item" onclick="switchTab('users')" data-tab="users" data-roles="admin,manager"><i class="fa-solid fa-users"></i> Users</div>
            <div class="tab-item" onclick="switchTab('email')" data-tab="email" data-roles="admin"><i class="fa-solid fa-envelope"></i> Email Offload</div>
            <div class="tab-item" onclick="switchTab('certificates')" data-tab="certificates" data-roles="admin"><i class="fa-solid fa-certificate"></i> Cert Offload</div>
            <div class="tab-item" onclick="switchTab('reports')" data-tab="reports" data-roles="admin,manager,teacher"><i class="fa-solid fa-file-lines"></i> Reports</div>
            <div class="tab-item" onclick="switchTab('reminders')" data-tab="reminders" data-roles="admin"><i class="fa-solid fa-bell"></i> Reminders</div>

            <!-- Compact Filter & Actions (Right Aligned) -->
            <div class="tab-actions" style="margin-left: auto; display: flex; gap: 8px; align-items: center;">
                 <button class="glass-btn small" id="dateRangeTrigger" onclick="toggleDatePopover()" style="padding: 6px 12px; font-size: 13px; border-radius: 8px; border: 1px solid var(--glass-border); background: rgba(0,0,0,0.2); color: var(--text-secondary); cursor: pointer; display: flex; align-items: center; gap: 8px;">
                     <i class="fa-regular fa-calendar" style="color: var(--accent-primary);"></i> 
                     <span id="dateRangeLabel"><?php echo ($start_param && $end_param) ? $start_param . ' to ' . $end_param : 'All Time'; ?></span>
                     <i class="fa-solid fa-chevron-down" style="font-size: 10px;"></i>
                 </button>
                 
                 <!-- Date Popover (Moved outside to avoid clipping) -->
                 <!-- Placeholder for trigger logic reference if needed -->

                 <!-- Export Trigger -->
                 <div style="position: relative;">
                    <button class="glass-btn small" onclick="toggleExportMenu()" title="Export Report" style="padding: 6px 12px; border-radius: 8px; border: 1px solid var(--glass-border); background: var(--accent-primary); color: white; cursor: pointer;">
                        <i class="fa-solid fa-download"></i>
                    </button>
                    <!-- Export Dropdown moved to root -->
                 </div>
            </div>
        </div>

        <!-- Date Popover (Moved here to avoid clipping by tab-menu overflow) -->
        <div id="datePopover" style="display: none; position: fixed; top: 140px; right: 40px; background: rgba(30, 41, 59, 0.95); border: 1px solid var(--glass-border); backdrop-filter: blur(20px); padding: 20px; border-radius: 16px; box-shadow: 0 20px 50px rgba(0,0,0,0.5); z-index: 9999; width: 340px;">
            <div style="font-size: 13px; font-weight: 600; color: var(--text-secondary); margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Select Date Range</div>
            <div style="margin-bottom: 16px; display: flex; gap: 8px; align-items: center;">
                <input type="date" id="dateStart" class="filter-input" style="flex: 1; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1);" value="<?php echo $start_param; ?>">
                <span style="color: var(--text-secondary); font-weight: bold;">:</span>
                <input type="date" id="dateEnd" class="filter-input" style="flex: 1; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1);" value="<?php echo $end_param; ?>">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 8px; margin-bottom: 16px;">
                <button class="filter-select quick-filter-btn" onclick="setDateFilter('1W')" style="justify-content: center;">1W</button>
                <button class="filter-select quick-filter-btn" onclick="setDateFilter('1M')" style="justify-content: center;">1M</button>
                <button class="filter-select quick-filter-btn" onclick="setDateFilter('3M')" style="justify-content: center;">3M</button>
                <button class="filter-select quick-filter-btn" onclick="setDateFilter('YTD')" style="justify-content: center;">YTD</button>
            </div>
            <button class="filter-select quick-filter-btn" onclick="setDateFilter('ALL')" style="width: 100%; margin-bottom: 16px; justify-content: center;">ALL TIME</button>
            <div style="display: flex; gap: 10px;">
                <button class="export-btn" onclick="applyDateFilter()" style="flex: 1; justify-content: center; background: var(--accent-primary); border-radius: 8px;">Apply Filter</button>
                <button class="export-btn" onclick="clearAllFilters()" style="flex: 1; background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); justify-content: center; border-radius: 8px;">Clear</button>
            </div>
        </div>

        <!-- Export Dropdown Global (Fixed Position) -->
        <div id="exportDropdown" style="display: none; position: fixed; top: 190px; right: 20px; background: rgba(30, 41, 59, 0.95); border: 1px solid var(--glass-border); backdrop-filter: blur(20px); padding: 8px; border-radius: 12px; box-shadow: 0 20px 50px rgba(0,0,0,0.5); z-index: 9999; width: 180px;">
            <div class="dropdown-item" onclick="triggerExport('course_completion', 'csv')" style="padding: 10px; border-radius: 8px; color: var(--text-secondary); cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.2s;"><i class="fa-solid fa-file-csv"></i> Export CSV</div>
            <div class="dropdown-item" onclick="triggerExport('course_completion', 'xlsx')" style="padding: 10px; border-radius: 8px; color: var(--text-secondary); cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.2s;"><i class="fa-solid fa-file-excel"></i> Export Excel</div>
            <div class="dropdown-item" onclick="triggerExport('course_completion', 'pdf')" style="padding: 10px; border-radius: 8px; color: var(--text-secondary); cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.2s;"><i class="fa-solid fa-file-pdf"></i> Export PDF</div>
        </div>

        <style>
            .dropdown-item:hover { background: rgba(255, 255, 255, 0.1); color: var(--text-primary); }
        </style>



        <!-- Content Area -->
        <div class="content-area">
            <div id="tab-overview" class="tab-content active">
            <!-- ========================================================================
                 PHASE 5: ROLE-BASED KPI LABELS
                 ======================================================================== -->
            <?php
            // Define KPI labels based on role
            switch ($user_role) {
                case 'admin':
                    $kpi_labels = [
                        'kpi1' => 'Total Companies',
                        'kpi2' => 'Total Courses',
                        'kpi3' => 'Total Users',
                        'kpi4' => 'Completion %',
                        'icon1' => 'fa-building',
                        'icon2' => 'fa-book',
                        'icon3' => 'fa-users',
                        'icon4' => 'fa-trophy'
                    ];
                    $kpi_values = [
                        'kpi1' => $kpi_data['companies'], // Fixed: Mapped to companies
                        'kpi2' => $kpi_data['courses'],
                        'kpi3' => number_format($kpi_data['users']), // Fixed: Mapped to users
                        'kpi4' => $kpi_data['completion_rate']
                    ];
                    break;
                case 'manager':
                    $kpi_labels = [
                        'kpi1' => 'My Company',
                        'kpi2' => 'Company Courses',
                        'kpi3' => 'Company Users',
                        'kpi4' => 'Completion %',
                        'icon1' => 'fa-building',
                        'icon2' => 'fa-book',
                        'icon3' => 'fa-users',
                        'icon4' => 'fa-trophy'
                    ];
                    $kpi_values = [
                        'kpi1' => isset($role_context['companyname']) ? $role_context['companyname'] : 'My Company',
                        'kpi2' => $kpi_data['courses'],
                        'kpi3' => $kpi_data['users'],
                        'kpi4' => $kpi_data['completion_rate']
                    ];
                    break;
                case 'teacher':
                    $kpi_labels = [
                        'kpi1' => 'My Courses',
                        'kpi2' => 'In Progress',
                        'kpi3' => 'Total Students',
                        'kpi4' => 'Avg Progress',
                        'icon1' => 'fa-book',
                        'icon2' => 'fa-clock',
                        'icon3' => 'fa-user-graduate',
                        'icon4' => 'fa-chart-line'
                    ];
                    $kpi_values = [
                        'kpi1' => $kpi_data['courses'], // Fixed: Mapped to courses
                        'kpi2' => $kpi_data['active_users'], // In Progress count logic
                        'kpi3' => $kpi_data['users'], // Total Students
                        'kpi4' => $kpi_data['completion_rate']
                    ];
                    break;
                case 'student':
                    $kpi_labels = [
                        'kpi1' => 'Enrolled Courses',
                        'kpi2' => 'In Progress',
                        'kpi3' => 'Completed',
                        'kpi4' => 'Avg Progress',
                        'icon1' => 'fa-book-open',
                        'icon2' => 'fa-spinner',
                        'icon3' => 'fa-check-circle',
                        'icon4' => 'fa-chart-line'
                    ];
                    $kpi_values = [
                        'kpi1' => $kpi_data['users'], // Enrolled
                        'kpi2' => $kpi_data['courses'], // In Progress
                        'kpi3' => $kpi_data['companies'], // Completed
                        'kpi4' => $kpi_data['completion_rate']
                    ];
                    break;
                default:
                    $kpi_labels = [
                        'kpi1' => 'Total Companies',
                        'kpi2' => 'Total Courses',
                        'kpi3' => 'Total Users',
                        'kpi4' => 'Completion %',
                        'icon1' => 'fa-building',
                        'icon2' => 'fa-book',
                        'icon3' => 'fa-users',
                        'icon4' => 'fa-trophy'
                    ];
                    $kpi_values = [
                        'kpi1' => $kpi_data['companies'],
                        'kpi2' => $kpi_data['courses'],
                        'kpi3' => $kpi_data['users'],
                        'kpi4' => $kpi_data['completion_rate']
                    ];
            }
            ?>
            <!-- KPI Cards (Always Visible) -->
            <div class="kpi-cards">

            <!-- KPI 1 -->
            <div class="bento-card card-span-1">
                <img src="<?php echo $OUTPUT->image_url('kpi_company', 'local_manireports'); ?>" class="card-illustration" alt="KPI1">
                <div class="card-content-wrapper">
                    <div class="card-header">
                        <div class="card-title"><i class="fa-solid <?php echo $kpi_labels['icon1']; ?>" style="color: var(--accent-primary);"></i> <?php echo $kpi_labels['kpi1']; ?></div>
                    </div>
                    <div class="card-value"><?php echo $kpi_values['kpi1']; ?></div>
                    <div style="height: 60px;"><canvas id="chartCompanies"></canvas></div>
                </div>
            </div>

            <!-- KPI 2 -->
            <div class="bento-card card-span-1">
                <img src="<?php echo $OUTPUT->image_url('kpi_courses', 'local_manireports'); ?>" class="card-illustration" alt="KPI2">
                <div class="card-content-wrapper">
                    <div class="card-header">
                        <div class="card-title"><i class="fa-solid <?php echo $kpi_labels['icon2']; ?>" style="color: var(--accent-success);"></i> <?php echo $kpi_labels['kpi2']; ?></div>
                    </div>
                    <div class="card-value"><?php echo $kpi_values['kpi2']; ?></div>
                    <div style="height: 60px;"><canvas id="chartCourses"></canvas></div>
                </div>
            </div>

            <!-- KPI 3 -->
            <div class="bento-card card-span-1">
                <img src="<?php echo $OUTPUT->image_url('kpi_users', 'local_manireports'); ?>" class="card-illustration" alt="KPI3">
                <div class="card-content-wrapper">
                    <div class="card-header">
                        <div class="card-title"><i class="fa-solid <?php echo $kpi_labels['icon3']; ?>" style="color: var(--accent-warning);"></i> <?php echo $kpi_labels['kpi3']; ?></div>
                    </div>
                    <div class="card-value"><?php echo $kpi_values['kpi3']; ?></div>
                    <div style="height: 60px;"><canvas id="chartUsers"></canvas></div>
                </div>
            </div>

            <!-- KPI 4 -->
            <div class="bento-card card-span-1">
                <img src="<?php echo $OUTPUT->image_url('kpi_completion', 'local_manireports'); ?>" class="card-illustration" alt="KPI4">
                <div class="card-content-wrapper">
                    <div class="card-header">
                        <div class="card-title"><i class="fa-solid <?php echo $kpi_labels['icon4']; ?>" style="color: var(--accent-secondary);"></i> <?php echo $kpi_labels['kpi4']; ?></div>
                    </div>
                    <div class="card-value"><?php echo $kpi_values['kpi4']; ?><?php echo ($user_role === 'student' || $user_role === 'teacher') ? '%' : '%'; ?></div>
                    <div style="height: 6px; background: rgba(255,255,255,0.1); border-radius: 3px; margin-top: 20px;">
                        <div style="width: <?php echo $kpi_data['completion_rate']; ?>%; height: 100%; background: var(--accent-secondary); border-radius: 3px;"></div>
                    </div>
                </div>
            </div>
            </div>
            <!-- End KPI Cards -->
            <!-- ========================================================================
                 END PHASE 5
                 ======================================================================== -->

            <div class="bento-grid">

            <?php if ($user_role === 'admin'): ?>
            <?php $top_companies = $loader->get_top_companies_analytics(); ?>
            <!-- Row 2: 3-Column Layout (User Roles, Avg Time, Top Companies) -->
            <div class="manireports-dashboard-grid" style="grid-column: 1 / -1; display: grid; grid-template-columns: repeat(3, 1fr) !important; gap: 24px; margin-bottom: 24px;">
                
                <!-- 1. User Role Distribution -->
                <div class="bento-card">
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

                <!-- 2. Avg Time Spent per User Chart -->
                <div class="bento-card">
                    <div class="card-header">
                        <div class="card-title">Avg Time/User</div>
                    </div>
                    <div style="height: 250px; width: 100%;">
                        <canvas id="timeSpentChart"></canvas>
                    </div>
                </div>

                <!-- 3. Top Companies Widget -->
                <div class="bento-card">
                    <div class="card-header">
                        <div class="card-title">Top Companies</div>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 12px; height: 250px; overflow-y: auto;">
                        <?php foreach ($top_companies as $idx => $comp): ?>
                            <?php 
                                $rank = $idx + 1;
                                $icon_color = '#94a3b8'; // Default Gray
                                $icon_bg = 'rgba(148, 163, 184, 0.1)';
                                $icon_content = $rank;
                                
                                if ($rank == 1) {
                                    $icon_color = '#F59E0B'; // Gold
                                    $icon_bg = 'rgba(245, 158, 11, 0.1)';
                                    $icon_content = '<i class="fa-solid fa-medal"></i>';
                                } elseif ($rank == 2) {
                                    $icon_color = '#94A3B8'; // Silver
                                    $icon_bg = 'rgba(148, 163, 184, 0.1)';
                                    $icon_content = '<i class="fa-solid fa-medal"></i>';
                                } elseif ($rank == 3) {
                                    $icon_color = '#D97706'; // Bronze
                                    $icon_bg = 'rgba(217, 119, 6, 0.1)';
                                    $icon_content = '<i class="fa-solid fa-medal"></i>';
                                }
                            ?>
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <div style="display: flex; align-items: center; gap: 10px; width: 65%;">
                                    <!-- Rank Icon -->
                                    <div style="width: 28px; height: 28px; min-width: 28px; display: flex; align-items: center; justify-content: center; background: <?php echo $icon_bg; ?>; border-radius: 50%; color: <?php echo $icon_color; ?>; font-size: 12px; font-weight: 700;">
                                        <?php echo $icon_content; ?>
                                    </div>
                                    
                                    <div style="flex: 1; min-width: 0;">
                                        <div style="font-size: 13px; font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo $comp['name']; ?>"><?php echo $comp['name']; ?></div>
                                        <?php
                                            // Color cycling for progress bars
                                            $colors = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899'];
                                            $bar_color = $colors[$idx % count($colors)];
                                        ?>
                                        <div class="progress-bar-slim" style="width: 100%; height: 4px; margin-top: 6px; background: var(--glass-border);">
                                            <div style="width: <?php echo $comp['rate']; ?>%; height: 100%; background: <?php echo $bar_color; ?>; border-radius: 2px;"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div style="text-align: right;">
                                    <div style="font-size: 13px; font-weight: 700; color: var(--text-primary);"><?php echo $comp['rate']; ?>%</div>
                                    <div style="font-size: 11px; color: var(--text-secondary);"><?php echo number_format($comp['user_count']); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($top_companies)): ?>
                            <div style="text-align: center; color: var(--text-secondary); padding-top: 80px;">No company data available</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Row 3: 2-Column Layout (Active Users 50%, Completion Trend 50%) -->
            <div class="manireports-dashboard-grid" style="grid-column: 1 / -1; display: grid; grid-template-columns: repeat(2, 1fr) !important; gap: 24px; margin-bottom: 24px;">
                
                <!-- Active Users (Moved Here) -->
                <div class="bento-card">
                    <div class="card-header">
                        <div class="card-title">Daily Traffic Trend</div>
                    </div>
                    <div style="height: 300px; width: 100%;">
                        <canvas id="activeUsersChart"></canvas>
                    </div>
                </div>

                <!-- Completion Trend (Moved Here) -->
                <div class="bento-card">
                    <div class="card-header">
                        <div class="card-title">Course Completion Trend</div>
                    </div>
                    <div style="height: 300px; width: 100%;">
                        <canvas id="completionTrendChart"></canvas>
                    </div>
                </div>

            </div>
            <?php endif; ?>

            <?php if ($user_role === 'admin' || $user_role === 'manager'): ?>
            <!-- ADMIN ONLY: Live Analytics Row (Full Width) -->
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


                    <!-- Col 2: Live Courses (Scrollable Leaderboard) -->
                    <div>
                        <div style="font-size: 14px; font-weight: 600; color: var(--text-primary); margin-bottom: 16px;">Users by Course (Live)</div>
                        <div class="live-list-container" style="height: 200px; overflow-y: auto; padding-right: 8px;">
                            <div style="display: flex; flex-direction: column; gap: 8px;">
                                <?php 
                                    $rank = 1;
                                    foreach ($live_stats['top_courses'] as $course): 
                                ?>
                                    <?php 
                                        $percent = ($live_stats['active_users'] > 0) ? ($course->active_count / $live_stats['active_users']) * 100 : 0;
                                        $color = '#3b82f6'; // Default Blue
                                        if ($percent > 50) $color = '#8b5cf6'; // Purple for high activity
                                    ?>
                                    <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); border-radius: 8px; padding: 8px 12px; display: flex; align-items: center; gap: 10px;">
                                        <!-- Rank Badge -->
                                        <div style="width: 20px; height: 20px; border-radius: 50%; background: rgba(255,255,255,0.1); font-size: 10px; display: flex; align-items: center; justify-content: center; color: var(--text-secondary); font-weight: 700; flex-shrink: 0;">
                                            <?php echo $rank++; ?>
                                        </div>
                                        
                                        <div style="flex: 1; min-width: 0;">
                                            <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px;">
                                                <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text-primary);" title="<?php echo $course->fullname; ?>">
                                                    <?php echo $course->fullname; ?>
                                                </span>
                                                <span style="font-weight: 600; color: #10b981;"><?php echo $course->active_count; ?></span>
                                            </div>
                                            <div style="width: 100%; height: 4px; background: rgba(255,255,255,0.1); border-radius: 2px;">
                                                <div style="width: <?php echo $percent; ?>%; height: 100%; background: <?php echo $color; ?>; border-radius: 2px;"></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (empty($live_stats['top_courses'])): ?>
                                    <div style="font-size: 12px; color: var(--text-secondary); text-align: center; padding: 20px;">No active courses right now.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>


                    <!-- Col 3: 24h Timeline -->
                    <div>
                        <div style="font-size: 14px; font-weight: 600; color: var(--text-primary); margin-bottom: 16px;">Live Traffic Map</div>
                        <div id="world-map-markers" style="height: 200px; width: 100%;"></div>
                        <!-- jsVectorMap Dependencies (CDN) -->
                        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jsvectormap/dist/css/jsvectormap.min.css" />
                        <script src="https://cdn.jsdelivr.net/npm/jsvectormap/dist/js/jsvectormap.min.js"></script>
                        <script src="https://cdn.jsdelivr.net/npm/jsvectormap/dist/maps/world.js"></script>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($user_role === 'admin' || $user_role === 'manager'): ?>
            <!-- ADMIN & MANAGER: Company-wise Analytics Table -->
            <div class="bento-card card-span-4">
                <div class="card-header">
                    <div class="card-title">Company-wise Analytics</div>
                    <a href="#" onclick="switchTab('companies'); return false;" class="action-link" style="font-size: 12px;">View All</a>
                </div>

                <div class="glass-table-container">
                    <!-- Header Row -->
                    <div style="display: grid; grid-template-columns: 30% 1fr 50px; align-items: center; gap: 20px; padding: 0 20px; color: var(--text-secondary); font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">
                        <div>Company & Courses</div>
                        <div style="display: flex; justify-content: space-between; padding-right: 40px;">
                            <div style="width: 80px; min-width: 80px; text-align: left; flex-shrink: 0;">Users</div>
                            <div style="width: 120px; min-width: 120px; text-align: left; flex-shrink: 0;">Enrollment</div>
                            <div style="width: 160px; min-width: 160px; text-align: left; flex-shrink: 0;">Completion</div>
                        </div>
                        <div></div> <!-- Spacer for Kebab -->
                    </div>

                    <?php
                    if (!empty($company_data)) {
                        foreach ($company_data as $company) {
                            $pct = $company['completion_rate'];
                            $active_users = $company['active_users'] ?? 0;
                            $avg_time = $company['time'] ?? '0h 0m';
                            
                            // Color logic
                            $color = '#3b82f6';
                            if ($pct > 70) $color = '#10b981';
                            if ($pct < 40) $color = '#f59e0b';
                            
                            // Initials for Icon
                            $initials = mb_substr($company['name'], 0, 1);
                            
                            echo '<div class="glass-list-item" style="display: grid; grid-template-columns: 30% 1fr 50px; align-items: center; gap: 20px;">
                                    <div style="display: flex; align-items: center; overflow: hidden;">
                                        <div class="item-icon" style="background: rgba(99, 102, 241, 0.1); color: var(--accent-primary); flex-shrink: 0;">
                                            ' . $initials . '
                                        </div>
                                        <div class="item-info" style="min-width: 0; overflow: hidden;">
                                            <div class="item-title" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">' . $company['name'] . '</div>
                                            <div class="item-subtitle">
                                                <span class="meta-badge"><i class="fa-solid fa-book-open"></i> ' . $company['courses'] . ' Courses</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="data-field-group" style="justify-content: space-between; width: 100%; padding-right: 40px; margin-right: 0; gap: 0;">
                                        <!-- Users Field -->
                                        <div class="data-field" style="width: 80px; min-width: 80px;">
                                            <div class="data-label">Users</div>
                                            <div class="data-value">' . $company['users'] . '</div>
                                            <div style="font-size: 11px; color: #10b981; margin-top: 2px;">' . $active_users . ' Active</div>
                                        </div>

                                        <!-- Enrollment Field -->
                                        <div class="data-field" style="width: 120px; min-width: 120px;">
                                            <div class="data-label">Stats</div>
                                            <div class="data-value">' . $company['enrolled'] . ' Enrolled</div>
                                            <div style="font-size: 11px; color: var(--text-secondary); margin-top: 2px;">' . $company['completed'] . ' Completed</div>
                                        </div>

                                        <!-- Completion & Time Field -->
                                        <div class="data-field" style="width: 160px; min-width: 160px;">
                                            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                                <div class="data-label">Progress</div>
                                                <div style="font-size: 11px; font-weight: 600; color: ' . $color . ';">' . $pct . '%</div>
                                            </div>
                                            <div class="progress-bar-slim">
                                                <div class="progress-fill" style="width: ' . $pct . '%; background: ' . $color . ';"></div>
                                            </div>
                                            <div style="font-size: 11px; color: var(--text-secondary); margin-top: 6px; display: flex; align-items: center; gap: 4px;">
                                                <i class="fa-regular fa-clock"></i> Avg Time: ' . $avg_time . '
                                            </div>
                                        </div>
                                    </div>

                                    <a href="?company_q=' . urlencode($company['name']) . '" onclick="localStorage.setItem(\'activeTab\', \'companies\');" class="action-btn-kebab" style="justify-self: end; text-decoration: none;">
                                        <i class="fa-solid fa-chevron-right"></i>
                                    </a>
                                  </div>';
                        }
                    } else {
                        echo '<div style="text-align: center; padding: 40px; color: var(--text-secondary);">No company data available.</div>';
                    }
                    ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($user_role === 'admin' || $user_role === 'manager'): ?>
            <!-- ADMIN & MANAGER: Course Analytics Table (Top 10 Courses) -->
            <div class="bento-card card-span-4">
                <div class="card-header">
                    <div class="card-title">Course Analytics (Top 10 Courses)</div>
                    <a href="#" onclick="switchTab('courses'); return false;" class="action-link">View All</a>
                </div>
                <div class="glass-table-container">
                    <!-- Header -->
                    <div style="display: grid; grid-template-columns: 40% 1fr 50px; align-items: center; gap: 20px; padding: 0 20px; color: var(--text-secondary); font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">
                        <div>Course Information</div>
                        <div style="display: flex; justify-content: space-between; padding-right: 40px;">
                            <div style="min-width: 100px;">Enrollment</div>
                            <div style="min-width: 160px;">Progress & Time</div>
                            <div style="min-width: 80px;">Status</div>
                        </div>
                        <div></div> <!-- Spacer -->
                    </div>

                    <?php
                    // Fix: Use $course_data instead of undefined $top_courses
                    $top_courses = $course_data; 
                    if (!empty($top_courses)) {
                        foreach ($top_courses as $course) {
                            $pct = $course['progress'];
                            $color = '#3b82f6';
                            if ($pct > 70) $color = '#10b981';
                            
                            $avg_time = $course['avg_time'] ?? '-';
                            $category = $course['category'] ?? 'General';
                            
                            // Status Badge Style
                            $status_color = ($course['status'] === 'ACTIVE') ? '#10b981' : '#ef4444';
                            $status_bg = ($course['status'] === 'ACTIVE') ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)';

                            echo '<div class="glass-list-item" style="display: grid; grid-template-columns: 40% 1fr 50px; align-items: center; gap: 20px;">
                                    <div style="display: flex; align-items: center; overflow: hidden;">
                                        <div class="item-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b; flex-shrink: 0;">
                                            <i class="fa-solid fa-graduation-cap"></i>
                                        </div>
                                        <div class="item-info" style="min-width: 0; overflow: hidden;">
                                            <div class="item-title" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">' . $course['fullname'] . '</div>
                                            <div class="item-subtitle">
                                                <span class="meta-badge">' . $category . '</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="data-field-group" style="justify-content: space-between; width: 100%; padding-right: 40px; margin-right: 0; gap: 0;">
                                        <!-- Enrollment -->
                                        <div class="data-field">
                                            <div class="data-label">Students</div>
                                            <div class="data-value">' . $course['enrolled'] . ' Enrolled</div>
                                            <div style="font-size: 11px; color: var(--text-secondary); margin-top: 2px;">' . $course['completed'] . ' Completed</div>
                                        </div>

                                        <!-- Progress & Time -->
                                        <div class="data-field" style="min-width: 160px;">
                                            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                                <div class="data-label">Avg Progress</div>
                                                <div style="font-size: 11px; font-weight: 600; color: ' . $color . ';">' . $pct . '%</div>
                                            </div>
                                            <div class="progress-bar-slim">
                                                <div class="progress-fill" style="width: ' . $pct . '%; background: ' . $color . ';"></div>
                                            </div>
                                            <div style="font-size: 11px; color: var(--text-secondary); margin-top: 6px; display: flex; align-items: center; gap: 4px;">
                                                <i class="fa-regular fa-clock"></i> Time: ' . $avg_time . '
                                            </div>
                                        </div>

                                        <!-- Status -->
                                        <div class="data-field" style="min-width: 80px;">
                                            <span style="padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; background: ' . $status_bg . '; color: ' . $status_color . ';">
                                                ' . $course['status'] . '
                                            </span>
                                        </div>
                                    </div>

                                    <a href="' . $CFG->wwwroot . '/course/view.php?id=' . $course['id'] . '" class="action-btn-kebab" style="text-decoration: none; justify-self: end;">
                                        <i class="fa-solid fa-arrow-right-long"></i>
                                    </a>
                                  </div>';
                        }
                    } else {
                        echo '<div style="text-align: center; padding: 40px; color: var(--text-secondary);">No courses available.</div>';
                    }
                    ?>
                </div>
            </div>
            <?php endif; ?>

            </div>


        <!-- ========================================================================
             PHASE 4: ROLE-SPECIFIC CONTENT SECTIONS
             ======================================================================== -->
        
        <?php if ($user_role === 'manager' && isset($role_context['companyname'])): ?>
            <!-- MANAGER SECTION: My Company Users -->
            <div class="glass-table-container" style="margin-top: 32px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0px;">
                    <div class="card-title"><i class="fa-solid fa-users"></i> Company Users</div>
                </div>
                
                <!-- Header -->
                <div style="display: flex; justify-content: space-between; padding: 0 20px; color: var(--text-secondary); font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">
                    <div style="flex: 1;">User Details</div>
                    <div style="display: flex; gap: 40px; margin-right: 60px;">
                        <div style="min-width: 120px;">Last Access</div>
                        <div style="min-width: 140px;">Learning Stats</div>
                    </div>
                </div>

                <?php
                $company_users = $loader->get_company_users(20);
                if (empty($company_users)) {
                    echo '<div style="text-align: center; padding: 40px; color: var(--text-secondary);">No users found in your company</div>';
                } else {
                    foreach ($company_users as $user) {
                        // Initials
                        $initials = mb_substr($user['fullname'], 0, 1);
                        
                        echo '<div class="glass-list-item">
                                <div style="display: flex; align-items: center; flex: 1;">
                                    <div class="item-icon" style="background: rgba(99, 102, 241, 0.1); color: var(--accent-primary); border-radius: 50%;">
                                        ' . $initials . '
                                    </div>
                                    <div class="item-info">
                                        <div class="item-title">' . htmlspecialchars($user['fullname']) . '</div>
                                        <div class="item-subtitle">' . htmlspecialchars($user['email']) . '</div>
                                    </div>
                                </div>
                                
                                <div class="data-field-group">
                                    <!-- Last Access -->
                                    <div class="data-field" style="min-width: 120px;">
                                        <div class="data-label">Last Active</div>
                                        <div class="data-value" style="font-size: 13px;">' . $user['lastaccess'] . '</div>
                                    </div>

                                    <!-- Stats -->
                                    <div class="data-field" style="min-width: 140px;">
                                        <div class="data-label">Progress</div>
                                        <div style="font-size: 13px; color: var(--text-primary);">
                                            <span style="font-weight: 600;">' . $user['completions'] . '</span> Completed 
                                            <span style="color: var(--text-secondary);">/ ' . $user['enrollments'] . ' Enrolled</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="action-btn-kebab">
                                    <i class="fa-solid fa-ellipsis-vertical"></i>
                                </div>
                              </div>';
                    }
                }
                ?>
            </div>
        <?php endif; ?>

        <?php if ($user_role === 'teacher' && isset($role_context['course_count']) && $role_context['course_count'] > 0): ?>
        <!-- TEACHER SECTION: My Students -->
        <div class="bento-card card-span-4" style="margin-top: 32px;">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-chalkboard-teacher"></i> My Students (<?php echo $role_context['course_count']; ?> Courses)</div>
            </div>
            <?php
            $my_students = $loader->get_my_students();
            if (empty($my_students)) {
                echo '<p style="text-align: center; color: var(--text-secondary); padding: 20px;">No students found in your courses</p>';
            } else {
                foreach ($my_students as $course_data) {
                    echo '<div class="glass-table-container" style="margin-bottom: 32px;">';
                    // Course Header
                    echo '<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; padding: 0 10px;">';
                    echo '<div class="card-title" style="display: flex; align-items: center; gap: 10px; font-size: 16px;">';
                    echo '<i class="fa-solid fa-book" style="color: var(--accent-primary);"></i> ' . htmlspecialchars($course_data['course_name']);
                    echo '<span class="status-badge status-active" style="font-size: 11px;">' . $course_data['student_count'] . ' Students</span>';
                    echo '</div>';
                    echo '</div>';
                    
                    if (empty($course_data['students'])) {
                        echo '<div style="text-align: center; padding: 30px; color: var(--text-secondary);">';
                        echo '<i class="fa-solid fa-user-slash" style="font-size: 24px; margin-bottom: 10px; opacity: 0.5;"></i>';
                        echo '<p>No students currently enrolled.</p>';
                        echo '</div>';
                    } else {
                        // Header Row
                        echo '<div style="display: flex; justify-content: space-between; padding: 0 20px 8px; color: var(--text-secondary); font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid rgba(255,255,255,0.05); margin-bottom: 8px;">';
                        echo '<div style="flex: 1;">Student</div>';
                        echo '<div style="display: flex; gap: 40px; margin-right: 20px;">';
                        echo '<div style="min-width: 140px;">Progress</div>';
                        echo '<div style="min-width: 120px;">Last Access</div>';
                        echo '<div style="min-width: 80px;">Status</div>';
                        echo '</div>';
                        echo '</div>';

                        foreach ($course_data['students'] as $student) {
                            $pct = $student['progress'];
                            $color = '#3b82f6';
                            if ($pct > 70) $color = '#10b981';
                            
                            $status_badge = $student['completed'] 
                                ? '<span class="status-badge status-completed">Completed</span>' 
                                : '<span class="status-badge status-warning">In Progress</span>';

                            // Initials
                            $initials = mb_substr($student['fullname'], 0, 1);

                            echo '<div class="glass-list-item" style="padding: 16px 20px; border: none; background: rgba(255,255,255,0.02); margin-bottom: 4px;">
                                    <div style="display: flex; align-items: center; flex: 1;">
                                        <div class="item-icon" style="width: 36px; height: 36px; font-size: 14px; background: rgba(255, 255, 255, 0.1); color: var(--text-primary); border-radius: 50%;">
                                            ' . $initials . '
                                        </div>
                                        <div class="item-info" style="margin-left: 16px;">
                                            <div class="item-title" style="font-size: 14px;">' . htmlspecialchars($student['fullname']) . '</div>
                                            <div class="item-subtitle" style="font-size: 12px;">' . htmlspecialchars($student['email']) . '</div>
                                        </div>
                                    </div>
                                    
                                    <div class="data-field-group" style="gap: 40px; margin-right: 0;">
                                        <!-- Progress -->
                                        <div class="data-field" style="min-width: 140px;">
                                            <div class="progress-bar-slim" style="width: 100%;">
                                                <div class="progress-fill" style="width: ' . $pct . '%; background: ' . $color . ';"></div>
                                            </div>
                                            <div style="font-size: 11px; margin-top: 4px; color: var(--text-secondary); text-align: right;">' . $pct . '% Complete</div>
                                        </div>

                                        <!-- Last Access -->
                                        <div class="data-field" style="min-width: 120px;">
                                            <div class="data-value" style="font-size: 13px; font-weight: 500;">' . $student['lastaccess'] . '</div>
                                        </div>

                                        <!-- Status -->
                                        <div class="data-field" style="min-width: 80px;">
                                            ' . $status_badge . '
                                        </div>
                                    </div>
                                  </div>';
                        }
                    }
                    echo '</div>'; // End glass-table-container
                }
            }
            ?>
        </div>
        <!-- End Teacher Section Wrapper (Removed inner div to avoid double card nesting) -->
        <?php endif; ?>

        <?php if ($user_role === 'student' && isset($role_context['course_count']) && $role_context['course_count'] > 0): ?>
        <!-- STUDENT SECTION: My Courses -->
        <div class="bento-grid" style="margin-top: 32px;">
            <div class="bento-card card-span-4">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-graduation-cap"></i> My Courses (<?php echo $role_context['course_count']; ?>)</div>
                </div>
            </div>
            
            <?php
            $my_courses = $loader->get_my_courses();
            if (empty($my_courses)) {
                echo '<div class="bento-card card-span-4"><p style="text-align: center; color: var(--text-secondary); padding: 20px;">No courses found</p></div>';
            } else {
                foreach ($my_courses as $course) {
                    echo '<div class="bento-card card-span-2">';
                    echo '<div class="card-header">';
                    echo '<div class="card-title"><i class="fa-solid fa-book"></i> ' . htmlspecialchars($course['fullname']) . '</div>';
                    echo '<span class="status-badge ' . $course['status_class'] . '">' . $course['status'] . '</span>';
                    echo '</div>';
                    
                    echo '<div style="margin: 16px 0;">';
                    echo '<div style="display: flex; justify-content: space-between; margin-bottom: 8px;">';
                    echo '<span style="color: var(--text-secondary); font-size: 14px;">Progress</span>';
                    echo '<span style="color: var(--text-primary); font-weight: 600;">' . $course['progress'] . '%</span>';
                    echo '</div>';
                    echo '<div style="width: 100%; height: 8px; background: rgba(255,255,255,0.1); border-radius: 4px;">';
                    echo '<div style="width: ' . $course['progress'] . '%; height: 100%; background: var(--accent-success); border-radius: 4px;"></div>';
                    echo '</div>';
                    echo '</div>';
                    
                    echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin: 16px 0; font-size: 13px;">';
                    echo '<div><span style="color: var(--text-secondary);">Activities:</span> <span style="color: var(--text-primary);">' . $course['completed_activities'] . '/' . $course['total_activities'] . '</span></div>';
                    echo '<div><span style="color: var(--text-secondary);">Last Access:</span> <span style="color: var(--text-primary);">' . $course['last_access'] . '</span></div>';
                    echo '</div>';
                    
                    echo '<a href="' . $course['course_url'] . '" class="btn btn-primary" style="width: 100%; text-align: center; margin-top: 12px;">';
                    echo ($course['status'] === 'Completed' ? 'View Course' : 'Continue Learning') . ' →';
                    echo '</a>';
                    
                    echo '</div>';
                }
            }
            ?>
        </div>
        <?php endif; ?>
        <!-- ========================================================================
             END PHASE 4
             ======================================================================== -->

        </div> <!-- End of Overview Tab -->

        <!-- COURSES TAB -->
        <div id="tab-courses" class="tab-content">
            <!-- Filter Bar -->
            <div class="filter-area" style="margin-bottom: 32px; background: var(--glass-bg); padding: 20px; border-radius: 16px; border: 1px solid var(--glass-border); display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
                <!-- Search -->
                <div class="filter-item" style="flex: 1; min-width: 300px;">
                    <i class="fa-solid fa-search" style="color: var(--text-secondary);"></i>
                    <input type="text" id="courseSearchInput" class="filter-input" placeholder="Search Companies or Courses..." style="width: 100%; font-size: 16px; padding: 12px 16px; background: rgba(0,0,0,0.2); border: none; outline: none; color: var(--text-primary);" onkeyup="debounceLoadCourses()">
                </div>
                <!-- Category Filter -->
                <div class="filter-item">
                    <select id="courseCategorySelect" class="filter-select" style="background: var(--glass-bg); color: var(--text-primary); border: 1px solid var(--glass-border); padding: 10px 16px; border-radius: 12px; font-size: 14px; outline: none; cursor: pointer;">
                        <option value="0" style="background: var(--bg-body); color: var(--text-primary);">All Categories</option>
                        <?php foreach ($course_categories as $id => $name) { echo "<option value='$id' style='background: var(--bg-body); color: var(--text-primary);'>$name</option>"; } ?>
                    </select>
                </div>
                
                <!-- Filter Actions -->
                <div style="display: flex; gap: 8px;">
                     <!-- Date Filter -->
                     <!-- Date Filter Popover Trigger -->
                    <div style="position: relative;">
                        <button id="courseDateRangeTrigger" class="filter-select" onclick="toggleCourseDatePopover()" style="background: var(--glass-bg); color: var(--text-primary); border: 1px solid var(--glass-border); padding: 10px 16px; border-radius: 12px; font-size: 14px; outline: none; cursor: pointer; display: flex; align-items: center; gap: 8px; min-width: 200px;">
                            <i class="fa-regular fa-calendar" style="color: var(--accent-primary);"></i>
                            <span id="courseDateRangeLabel">Select Date Range</span>
                            <i class="fa-solid fa-chevron-down" style="font-size: 10px; margin-left: auto; color: var(--text-secondary);"></i>
                        </button>
                        
                        <!-- Popover Content (Exact Match to Overview) -->
                        <div id="courseDatePopover" style="display: none; position: absolute; top: 110%; right: 0; background: rgba(30, 41, 59, 0.95); border: 1px solid var(--glass-border); backdrop-filter: blur(20px); padding: 20px; border-radius: 16px; box-shadow: 0 20px 50px rgba(0,0,0,0.5); z-index: 1000; width: 340px;">
                            <div style="font-size: 13px; font-weight: 600; color: var(--text-secondary); margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Select Date Range</div>
                            
                            <div style="margin-bottom: 16px; display: flex; gap: 8px; align-items: center;">
                                <input type="date" id="courseDateStart" class="filter-input" style="flex: 1; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); width: 100%;" placeholder="dd-mm-yyyy">
                                <span style="color: var(--text-secondary); font-weight: bold;">:</span>
                                <input type="date" id="courseDateEnd" class="filter-input" style="flex: 1; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); width: 100%;" placeholder="dd-mm-yyyy">
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 8px; margin-bottom: 16px;">
                                <button class="filter-select quick-filter-btn" onclick="setCourseDateFilter('1W')" style="justify-content: center;">1W</button>
                                <button class="filter-select quick-filter-btn" onclick="setCourseDateFilter('1M')" style="justify-content: center;">1M</button>
                                <button class="filter-select quick-filter-btn" onclick="setCourseDateFilter('3M')" style="justify-content: center;">3M</button>
                                <button class="filter-select quick-filter-btn" onclick="setCourseDateFilter('YTD')" style="justify-content: center;">YTD</button>
                            </div>
                            
                            <button class="filter-select quick-filter-btn" onclick="setCourseDateFilter('ALL')" style="width: 100%; margin-bottom: 16px; justify-content: center;">ALL TIME</button>
                            
                            <div style="display: flex; gap: 10px;">
                                <button class="export-btn" onclick="applyCourseDateFilter()" style="flex: 1; justify-content: center; background: var(--accent-primary); border-radius: 8px;">Apply Filter</button>
                                <button class="export-btn" onclick="clearCourseFilters()" style="flex: 1; background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); justify-content: center; border-radius: 8px;">Clear</button>
                            </div>
                        </div>
                    </div>


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

                <?php if ($user_role !== 'student'): ?>
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
                <?php endif; ?>

                <!-- Row 3: Advanced Table (AJAX Powered) -->
                <div class="bento-card card-span-4">
                    <div class="card-header">
                        <div class="card-title">Comprehensive Course List</div>
                        <button class="export-btn" style="padding: 6px 12px; font-size: 12px;" onclick="triggerExport('course_completion', 'csv')">Export CSV</button>
                    </div>
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead class="sticky-header">
                            <tr>
                                <th class="table-header" style="width: 50%; max-width: 400px;">Course Name & Category</th>
                                <th class="table-header" style="width: 15%;">Enrollment</th>
                                <th class="table-header" style="width: 15%;">Progress & Time</th>
                                <th class="table-header" style="width: 10%; text-align: right;">Status</th>
                                <th class="table-header" style="width: 10%; text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="coursesTableBody">
                            <!-- Loaded via AJAX -->
                            <tr><td colspan="5" style="text-align: center; padding: 40px;">Loading courses...</td></tr>
                        </tbody>
                    </table>
                    
                    <!-- Pagination Controls -->
                    <div id="coursesPagination" style="padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--glass-border); display: none;">
                        <div id="coursesCountInfo" style="color: var(--text-secondary); font-size: 13px;"></div>
                        <div style="display: flex; gap: 8px;">
                            <button id="btnPrevCourse" class="export-btn" onclick="changeCoursePage(-1)" style="padding: 6px 12px; font-size: 12px;">Previous</button>
                            <span id="coursesPageInfo" style="display: flex; align-items: center; padding: 0 8px; color: var(--text-primary); font-size: 13px;">Page 1</span>
                            <button id="btnNextCourse" class="export-btn" onclick="changeCoursePage(1)" style="padding: 6px 12px; font-size: 12px;">Next</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        // AJAX COURSES LOGIC
        let currentCoursePage = 1;
        let courseDebounceTimer;

        function loadCourses(page) {
            if (page) currentCoursePage = page;
            
            const tbody = document.getElementById('coursesTableBody');
            const search = document.getElementById('courseSearchInput').value;
            const category = document.getElementById('courseCategorySelect').value;
            const dateStart = document.getElementById('courseDateStart') ? document.getElementById('courseDateStart').value : '';
            const dateEnd = document.getElementById('courseDateEnd') ? document.getElementById('courseDateEnd').value : '';
            const paginationEl = document.getElementById('coursesPagination');
            
            // Fade out current content slightly to indicate loading
            tbody.style.opacity = '0.5';

            const url = '<?php echo $CFG->wwwroot; ?>/local/manireports/ajax_courses.php?action=get_courses' +
                        '&sesskey=<?php echo sesskey(); ?>' +
                        '&page=' + currentCoursePage +
                        '&search=' + encodeURIComponent(search) +
                        '&category=' + category + 
                        '&start_date=' + encodeURIComponent(dateStart) +
                        '&end_date=' + encodeURIComponent(dateEnd);

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    tbody.innerHTML = data.html;
                    tbody.style.opacity = '1';
                    
                    if (data.pagination && data.pagination.total_pages > 0) {
                        paginationEl.style.display = 'flex';
                        updateCoursePagination(data.pagination);
                    } else {
                        paginationEl.style.display = 'none';
                    }
                })
                .catch(err => {
                    console.error('Error loading courses:', err);
                    tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px; color: var(--accent-danger);">Error loading courses.</td></tr>';
                    tbody.style.opacity = '1';
                });
        }

        function updateCoursePagination(pg) {
            const start = ((pg.current_page - 1) * pg.per_page) + 1;
            const end = Math.min(pg.current_page * pg.per_page, pg.total_records);
            
            document.getElementById('coursesCountInfo').innerText = `Showing ${start} to ${end} of ${pg.total_records} courses`;
            document.getElementById('coursesPageInfo').innerText = `Page ${pg.current_page} of ${pg.total_pages}`;
            
            const btnPrev = document.getElementById('btnPrevCourse');
            const btnNext = document.getElementById('btnNextCourse');
            
            btnPrev.disabled = (pg.current_page <= 1);
            btnNext.disabled = (pg.current_page >= pg.total_pages);
            
            // Update onclick handlers to pass specific page numbers
            btnPrev.onclick = () => loadCourses(pg.current_page - 1);
            btnNext.onclick = () => loadCourses(pg.current_page + 1);
        }

        function debounceLoadCourses() {
            clearTimeout(courseDebounceTimer);
            courseDebounceTimer = setTimeout(() => {
                currentCoursePage = 1; // Reset to page 1 on search
                loadCourses(1);
            }, 500); // 500ms delay
        }

        function clearCourseFilters() {
            document.getElementById('courseSearchInput').value = '';
            document.getElementById('courseCategorySelect').value = '0';
            currentCoursePage = 1;
            loadCourses(1);
        }

        // Initialize on Load
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('tab-courses')) {
                loadCourses(1);
            }
        });
        </script>

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

                    <!-- Company Reminder Stats (NEW) -->
                    <div class="bento-card card-span-4">
                        <div class="card-header">
                            <div class="card-title"><i class="fa-solid fa-building"></i> Company Reminder Stats</div>
                        </div>
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr>
                                    <th class="table-header">Company</th>
                                    <th class="table-header">Active Rules</th>
                                    <th class="table-header">Pending Reminders</th>
                                    <th class="table-header">Sent (Last 30 Days)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($company_reminder_stats as $stat): ?>
                                <tr class="table-row">
                                    <td class="table-cell" style="font-weight: 600;"><?php echo format_string($stat->name); ?></td>
                                    <td class="table-cell"><?php echo $stat->active_rules; ?></td>
                                    <td class="table-cell"><?php echo $stat->pending_reminders; ?></td>
                                    <td class="table-cell"><?php echo $stat->sent_last_30; ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($company_reminder_stats)): ?>
                                    <tr><td colspan="4" class="table-cell" style="text-align: center;">No company stats available.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Unified Reminder Status (REPLACES Recent Activity) -->
                    <div class="bento-card card-span-4">
                        <div class="card-header">
                            <div class="card-title"><i class="fa-solid fa-list-check"></i> Reminder Status</div>
                        </div>
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr>
                                    <th class="table-header">Rule</th>
                                    <th class="table-header">Recipient</th>
                                    <th class="table-header">Last Sent</th>
                                    <th class="table-header">Next Due</th>
                                    <th class="table-header">Status</th>
                                    <th class="table-header">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($unified_reminder_status as $item): ?>
                                <tr class="table-row">
                                    <td class="table-cell" style="font-weight: 600;"><?php echo format_string($item['rule_name']); ?></td>
                                    <td class="table-cell"><?php echo $item['recipient']; ?></td>
                                    <td class="table-cell">
                                        <?php 
                                            if ($item['last_sent']) {
                                                echo $item['last_sent'];
                                                if ($item['last_status']) {
                                                    echo ' <span style="color: var(--text-muted); font-size: 11px;">(' . $item['last_status'] . ')</span>';
                                                }
                                            } else {
                                                echo '<span style="color: var(--text-muted);">-</span>';
                                            }
                                        ?>
                                    </td>
                                    <td class="table-cell">
                                        <?php echo $item['next_due'] ? $item['next_due'] : '<span style="color: var(--text-muted);">-</span>'; ?>
                                    </td>
                                    <td class="table-cell">
                                        <?php 
                                            $status_class = 'status-info'; // Default blue for Pending
                                            $tooltip = 'Waiting to send first email';
                                            
                                            if (strpos($item['status'], 'Completed') !== false) {
                                                $status_class = 'status-success'; // Green for Completed
                                                $tooltip = 'All reminder emails sent';
                                                if ($item['last_sent']) {
                                                    $tooltip .= '. Last sent: ' . $item['last_sent'];
                                                }
                                            } else if (strpos($item['status'], 'Active') !== false) {
                                                $status_class = 'status-warning'; // Yellow for Active
                                                $tooltip = $item['emails_sent'] . ' of ' . $item['total_reminders'] . ' emails sent';
                                                if ($item['next_due']) {
                                                    $tooltip .= '. Next due: ' . $item['next_due'];
                                                }
                                            } else {
                                                // Pending
                                                if ($item['next_due']) {
                                                    $tooltip = 'First email scheduled for ' . $item['next_due'];
                                                }
                                            }
                                        ?>
                                        <span class="status-badge <?php echo $status_class; ?>" title="<?php echo htmlspecialchars($tooltip); ?>"><?php echo $item['status']; ?></span>
                                    </td>
                                    <td class="table-cell">
                                        <a href="javascript:void(0)" onclick="showReminderDetails('<?php echo $item['id']; ?>')" class="action-link">
                                            <i class="fa-regular fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($unified_reminder_status)): ?>
                                    <tr><td colspan="6" class="table-cell" style="text-align: center;">No reminder activity found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Reminder Details Modal -->
                <div id="reminderDetailsModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); backdrop-filter: blur(8px); z-index: 9999; align-items: center; justify-content: center;">
                    <div style="background: var(--card-bg); border-radius: 24px; padding: 32px; max-width: 600px; width: 90%; border: 1px solid var(--glass-border); box-shadow: 0 20px 60px rgba(0,0,0,0.5);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                            <h3 style="margin: 0; color: var(--text-primary);">Reminder Details</h3>
                            <button onclick="closeReminderDetails()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: var(--text-secondary);">&times;</button>
                        </div>
                        <div id="reminderDetailsContent" style="color: var(--text-primary); line-height: 1.8;">
                            <!-- Content will be populated by JavaScript -->
                        </div>
                        <div style="margin-top: 24px; text-align: right;">
                            <button onclick="closeReminderDetails()" class="btn btn-primary">Close</button>
                        </div>
                    </div>
                </div>

                <script>
                // Store reminder data for popup
                const reminderData = <?php echo json_encode($unified_reminder_status); ?>;

                function showReminderDetails(id) {
                    const item = reminderData.find(r => r.id === id);
                    if (!item) return;

                    let content = `
                        <div style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid var(--glass-border);">
                            <h4 style="margin: 0 0 12px 0; color: var(--accent-primary);">${item.rule_name}</h4>
                            <p style="margin: 8px 0;"><strong>Recipient:</strong> ${item.recipient}</p>
                            <p style="margin: 8px 0;"><strong>Progress:</strong> ${item.emails_sent} of ${item.total_reminders} emails sent</p>
                            <p style="margin: 8px 0;"><strong>Status:</strong> <span class="status-badge">${item.status}</span></p>
                        </div>
                        
                        <div style="margin-bottom: 16px;">
                            <h5 style="margin: 0 0 8px 0; color: var(--text-primary);">Timeline</h5>
                            ${item.last_sent ? `<p style="margin: 4px 0;"><strong>Last Sent:</strong> ${item.last_sent} ${item.last_status ? '(' + item.last_status + ')' : ''}</p>` : '<p style="margin: 4px 0; color: var(--text-muted);">No emails sent yet</p>'}
                            ${item.next_due ? `<p style="margin: 4px 0;"><strong>Next Due:</strong> ${item.next_due}</p>` : '<p style="margin: 4px 0; color: var(--text-muted);">No upcoming emails</p>'}
                        </div>
                    `;

                    document.getElementById('reminderDetailsContent').innerHTML = content;
                    document.getElementById('reminderDetailsModal').style.display = 'flex';
                }

                function closeReminderDetails() {
                    document.getElementById('reminderDetailsModal').style.display = 'none';
                }
                </script>
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

// ========================================================================
// PHASE 3: TAB FILTERING BASED ON USER ROLE
// ========================================================================
(function() {
    // Get user role from PHP and trim any whitespace
    const userRole = '<?php echo trim($user_role); ?>'.trim();
    
    console.log('Dashboard: Filtering tabs for role:', userRole, '(length:', userRole.length, ')');
    
    // Safety check - if role is empty, default to showing all tabs
    if (!userRole || userRole.length === 0) {
        console.error('ERROR: User role is empty! Showing all tabs by default.');
        return;
    }
    
    // Filter tabs based on role
    document.querySelectorAll('.tab-item[data-roles]').forEach(tabItem => {
        const allowedRoles = tabItem.dataset.roles.split(',').map(r => r.trim());
        const tabName = tabItem.dataset.tab;
        
        console.log('  - Checking tab:', tabName, 'allowed roles:', allowedRoles, 'user role:', userRole);
        
        if (!allowedRoles.includes(userRole)) {
            // Hide tab button
            tabItem.style.display = 'none';
            
            // Hide corresponding tab content
            const tabContent = document.getElementById('tab-' + tabName);
            if (tabContent) {
                tabContent.style.display = 'none';
            }
            
            console.log('    ✗ Hidden tab:', tabName);
        } else {
            console.log('    ✓ Visible tab:', tabName);
        }
    });
    
    // Set first visible tab as active
    const firstVisibleTab = document.querySelector('.tab-item[data-roles]:not([style*="display: none"])');
    if (firstVisibleTab) {
        const firstTabName = firstVisibleTab.dataset.tab;
        switchTab(firstTabName);
        console.log('  - Set active tab:', firstTabName);
    } else {
        console.error('ERROR: No visible tabs found!');
    }
})();
// ========================================================================
// END PHASE 3
// ========================================================================


// ===============================
// COURSE DATE FILTER LOGIC
// ===============================
function toggleCourseDatePopover() {
    const popover = document.getElementById('courseDatePopover');
    if (popover.style.display === 'none') {
        popover.style.display = 'block';
    } else {
        popover.style.display = 'none';
    }
}

function setCourseDateFilter(range) {
    const today = new Date();
    let startDate = new Date();

    const buttons = document.querySelectorAll('#courseDatePopover .quick-filter-btn');
    buttons.forEach(btn => btn.classList.remove('active'));
    if (window.event && window.event.target) window.event.target.classList.add('active');

    switch(range) {
        case '1W': startDate.setDate(today.getDate() - 7); break;
        case '1M': startDate.setMonth(today.getMonth() - 1); break;
        case '3M': startDate.setMonth(today.getMonth() - 3); break;
        case 'YTD': startDate = new Date(today.getFullYear(), 0, 1); break;
        case 'ALL': startDate = null; break;
    }

    const formatDate = (date) => {
        const d = date.getDate().toString().padStart(2, '0');
        const m = (date.getMonth() + 1).toString().padStart(2, '0');
        const y = date.getFullYear();
        return `${y}-${m}-${d}`;
    };

    // Update Label logic
    const label = document.getElementById('courseDateRangeLabel');
    if (label) label.innerText = (range === 'ALL') ? 'All Time' : 'Last ' + range;

    const startElem = document.getElementById('courseDateStart');
    const endElem = document.getElementById('courseDateEnd');
    
    if (range === 'ALL') {
         if (startElem) startElem.value = '';
         if (endElem) endElem.value = '';
    } else {
        if (startElem && startDate) startElem.value = formatDate(startDate);
        if (endElem) endElem.value = formatDate(today);
    }
}

function applyCourseDateFilter() {
    // 1. Update the label if custom range
    const start = document.getElementById('courseDateStart').value;
    const end = document.getElementById('courseDateEnd').value;
    const label = document.getElementById('courseDateRangeLabel');
    
    if (start && end) {
        label.innerText = `${start} to ${end}`;
    } else if (!start && !end) {
        label.innerText = 'All Time';
    }

    // 2. Hide Popover
    document.getElementById('courseDatePopover').style.display = 'none';

    // 3. Trigger Load
    loadCourses(1);
}

// Date Filter Logic (Popover)
function toggleDatePopover() {
    const popover = document.getElementById('datePopover');
    if (popover.style.display === 'none') {
        popover.style.display = 'block';
    } else {
        popover.style.display = 'none';
    }
}

// Close popover when clicking outside
document.addEventListener('click', function(event) {
    // 1. Overview Date Popover
    const popover = document.getElementById('datePopover');
    const trigger = document.getElementById('dateRangeTrigger');
    if (popover && trigger && !popover.contains(event.target) && !trigger.contains(event.target)) {
        popover.style.display = 'none';
    }

    // 2. Course Date Popover
    const coursePopover = document.getElementById('courseDatePopover');
    const courseTrigger = document.getElementById('courseDateRangeTrigger');
    if (coursePopover && courseTrigger && !coursePopover.contains(event.target) && !courseTrigger.contains(event.target)) {
        coursePopover.style.display = 'none';
    }
    
    // Export Dropdown
    const exportMenu = document.getElementById('exportDropdown');
    // We need to identify the export trigger button. It doesn't have an ID.
    // Let's rely on the click event bubbling or add a check if it's NOT the button.
    // Safest is to add an ID/class to the button or check target closest.
    if (exportMenu && exportMenu.style.display === 'block') {
         // Using closest to check if click is inside menu or trigger
         if (!event.target.closest('#exportDropdown') && !event.target.closest('button[onclick="toggleExportMenu()"]')) {
             exportMenu.style.display = 'none';
         }
    }
});
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
        case 'ALL': startDate = null; break; // Use null to indicate clear
    }

    const formatDate = (date) => {
        // Use Y-m-d for input type="date" value
        const d = date.getDate().toString().padStart(2, '0');
        const m = (date.getMonth() + 1).toString().padStart(2, '0');
        const y = date.getFullYear();
        return `${y}-${m}-${d}`;
    };

    // Update Label logic
    const label = document.getElementById('dateRangeLabel');
    if (label) label.innerText = (range === 'ALL') ? 'All Time' : 'Last ' + range;

    const startElem = document.getElementById('dateStart');
    const endElem = document.getElementById('dateEnd');
    
    if (range === 'ALL') {
         if (startElem) startElem.value = '';
         if (endElem) endElem.value = '';
    } else {
        if (startElem && startDate) startElem.value = formatDate(startDate);
        if (endElem) endElem.value = formatDate(today);
    }

    console.log(`Filter applied: ${range}`);
}

// Apply Date Filter from date inputs
function applyDateFilter() {
    const startInput = document.getElementById('dateStart');
    const endInput = document.getElementById('dateEnd');
    
    if (!startInput || !endInput) return;

    let start = startInput.value;
    let end = endInput.value;

    // Basic validation
    if (start && end && start > end) {
        alert('Start date cannot be after end date');
        return;
    }

    // Convert to Unix Timestamp (start of day)
    // Convert to Unix Timestamp (start of day)
    let startTime = start ? new Date(start).getTime() / 1000 : 0;
    // End of day: set to 23:59:59
    let endTime = end ? new Date(end).getTime() / 1000 + 86399 : 0;

    // Only convert if inputs are not empty
    if (start) {
        const d1 = new Date(start);
        startTime = Math.floor(d1.getTime() / 1000);
        // Format to d-m-Y for URL param
        start = `${d1.getDate().toString().padStart(2, '0')}-${(d1.getMonth() + 1).toString().padStart(2, '0')}-${d1.getFullYear()}`;
    }
    
    if (end) {
        const d2 = new Date(end);
        endTime = Math.floor(d2.getTime() / 1000) + 86399; // End of day
        end = `${d2.getDate().toString().padStart(2, '0')}-${(d2.getMonth() + 1).toString().padStart(2, '0')}-${d2.getFullYear()}`;
    }

    // Reload with params
    const url = new URL(window.location.href);
    if (start) url.searchParams.set('start', start);
    else url.searchParams.delete('start'); // Remove param if empty (All Time)
    
    if (end) url.searchParams.set('end', end);
    else url.searchParams.delete('end'); // Remove param if empty
    
    // Update label text if element exists
    const label = document.getElementById('dateRangeLabel');
    if (label) {
        if (start && end) label.innerText = start + ' to ' + end;
        else label.innerText = 'All Time';
    }
    
    // Hide popover
    const popover = document.getElementById('datePopover');
    if (popover) popover.style.display = 'none';

    window.location.href = url.toString();
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
    const datePopover = document.getElementById('datePopover');
    if (menu) {
        // Toggle menu
        if (menu.style.display === 'block') {
            menu.style.display = 'none';
        } else {
            // Close other popover if open
            if (datePopover) datePopover.style.display = 'none';
            menu.style.display = 'block';
        }
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
        if (el && typeof Chart !== 'undefined') {
            try {
                new Chart(el, config);
            } catch (e) {
                console.error('Error init chart ' + id, e);
            }
        } else {
            console.warn('Canvas or Chart.js missing for ' + id);
        }
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
                labels: <?php echo json_encode($live_stats['timeline_labels'] ?? []); ?>,
                datasets: [{
                    label: 'Active Users',
                    data: <?php echo json_encode($live_stats['timeline_data'] ?? []); ?>,
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
            labels: <?php echo json_encode($avg_time_data['labels'] ?? []); ?>,
            datasets: [{
                label: 'Avg Actions/User',
                data: <?php echo json_encode($avg_time_data['data'] ?? []); ?>,
                borderColor: '#8b5cf6',
                backgroundColor: 'rgba(139, 92, 246, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } }, // Keeping concise
            scales: {
                y: { 
                    grid: { color: 'rgba(255, 255, 255, 0.05)' }, 
                    ticks: { color: '#94a3b8' },
                    title: { display: true, text: 'Avg Actions', color: '#64748b' } 
                },
                x: { grid: { display: false }, ticks: { color: '#94a3b8' } }
            }
        }
    });

    // Completion Trend Chart
    initChart('completionTrendChart', {
        type: 'line',
        data: {
            labels: <?php echo json_encode($trend_data['labels'] ?? []); ?>,
            datasets: [
                {
                    label: 'Enrolled',
                    data: <?php echo json_encode($trend_data['enrollments'] ?? []); ?>,
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
                    data: <?php echo json_encode($trend_data['completions'] ?? []); ?>,
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
    // World Map Initialization
    const mapContainer = document.getElementById('world-map-markers');
    if (mapContainer && window.jsVectorMap) {
        const rawMapData = <?php echo json_encode($live_stats['map_data'] ?? []); ?>;
        
        // Define coords for major countries (Fallback lookup)
        // Since we are using "markers", we need Lat/Lng. 
        // If we want to highlight REGIONS (easier), we pass 'selectedRegions'.
        // User requested "blinking pointer". Markers are pointers.
        // We need a lookup for common country codes to Coords.
        const countryCoords = {
            'IN': [20.5937, 78.9629],
            'US': [37.0902, -95.7129],
            'GB': [55.3781, -3.4360],
            'CA': [56.1304, -106.3468],
            'AU': [-25.2744, 133.7751],
            'DE': [51.1657, 10.4515],
            'FR': [46.2276, 2.2137],
            'BR': [-14.2350, -51.9253],
            'CN': [35.8617, 104.1954],
            'JP': [36.2048, 138.2529],
            'RU': [61.5240, 105.3188],
            'ZA': [-30.5595, 22.9375]
        };

        const markers = rawMapData.map(item => {
            const coords = countryCoords[item.code] || [0, 0]; // Default to center if unknown
            return {
                name: item.code,
                coords: coords,
                value: item.value,
                style: { fill: '#10b981', stroke: '#fff', strokeWidth: 2 }
            };
        });

        // If India default is active, ensure it blinks
        const markerStyle = {
            initial: { fill: '#10b981', stroke: '#ffffff', strokeWidth: 2, r: 6 },
            hover: { fill: '#34d399', stroke: '#ffffff', strokeWidth: 2 }
        };

        // Fix: Only enable visualizeData if we have values, otherwise it crashes with hexToRgb error
        const values = {};
        markers.forEach(m => { values[m.name] = m.value; });

        const mapConfig = {
            selector: '#world-map-markers',
            map: 'world',
            zoomButtons: false,
            zoomOnScroll: false,
            regionStyle: {
                initial: { fill: '#334155' } // Use Hex to avoid hexToRgb error
            },
            onLoaded(map) {
                 document.querySelectorAll('.jvm-marker').forEach(el => {
                     el.classList.add('map-pulse');
                 });
            },
            tooltip: {
                text: function(name, count) {
                   return 'User ' + count;
                }
            }
        };

        // Only add markers if we actually have data
        if (markers.length > 0) {
            mapConfig.markers = markers;
            mapConfig.markerStyle = markerStyle;
            mapConfig.labels = {
                markers: {
                    render: (marker) => marker.value > 0 ? marker.value : ''
                }
            };
            // removed visualizeData entirely as it causes crashes on single color scales
            
            mapConfig.onMarkerTooltipShow = function(event, tooltip, index) {
                tooltip.text(
                    '<div style="text-align:center;">' + 
                    '<b style="color:#fff;">' + markers[index].name + '</b><br/>' + 
                    '<span style="color:#10b981;">● ' + markers[index].value + ' Active Users</span>' +
                    '</div>',
                    true
                );
            };
        }

        new jsVectorMap(mapConfig);
    }

    // Add Pulse CSS via JS if not in CSS file yet
    const style = document.createElement('style');
    style.innerHTML = `
        .jvm-marker.map-pulse {
            animation: mapPulse 1.5s infinite;
        }
        @keyframes mapPulse {
            0% { stroke-width: 0px; stroke-opacity: 0.5; }
            50% { stroke-width: 8px; stroke-opacity: 0.2; }
            100% { stroke-width: 0px; stroke-opacity: 0; }
        }
        .jvm-tooltip {
            background: rgba(15, 23, 42, 0.9) !important;
            border: 1px solid rgba(148, 163, 184, 0.1) !important;
            border-radius: 8px !important;
            padding: 8px 12px !important;
            font-family: 'Outfit', sans-serif !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1) !important;
        }
    `;
    document.head.appendChild(style);

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

// Drawer Logic
function openCourseDrawer(courseId) {
    document.getElementById('courseDrawerBackdrop').classList.add('open');
    document.getElementById('courseDrawer').classList.add('open');
    
    const content = document.getElementById('drawerContent');
    content.innerHTML = '<div style="height: 100%; display: flex; align-items: center; justify-content: center;"><div class="manireports-loading-spinner"></div></div>';

    fetch('<?php echo $CFG->wwwroot; ?>/local/manireports/ajax_courses.php?action=get_course_details&courseid=' + courseId + '&sesskey=<?php echo sesskey(); ?>')
        .then(res => res.json())
        .then(data => {
            content.innerHTML = `
                <div style="padding: 40px;">
                    <div style="margin-bottom: 24px;">
                        <span class="status-badge status-active" style="font-size: 12px;">${data.category}</span>
                    </div>
                    <h2 style="margin: 0 0 16px; font-size: 32px; font-weight: 700; color: var(--text-primary); letter-spacing: -0.5px;">${data.fullname}</h2>
                    <p style="color: var(--text-secondary); line-height: 1.7; font-size: 15px; margin-bottom: 32px;">${data.summary || 'No description available for this course.'}</p>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 40px;">
                        <div class="bento-card" style="padding: 20px;">
                            <div class="card-title" style="font-size: 13px; margin-bottom: 8px;"><i class="fa-solid fa-users" style="color: var(--accent-primary);"></i> Enrolled Users</div>
                            <div class="card-value" style="font-size: 28px; margin: 0;">${data.stats.enrolled}</div>
                        </div>
                        <div class="bento-card" style="padding: 20px;">
                            <div class="card-title" style="font-size: 13px; margin-bottom: 8px;"><i class="fa-solid fa-check-circle" style="color: var(--accent-success);"></i> Completions</div>
                            <div class="card-value" style="font-size: 28px; margin: 0;">${data.stats.completed} <span style="font-size: 14px; color: var(--text-secondary); font-weight: 500;">(${data.stats.completion_rate}%)</span></div>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 40px;">
                        <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary);">Course Instructors</h3>
                        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                            ${data.teachers ? data.teachers.split(', ').map(t => `<div style="padding: 10px 16px; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); border-radius: 12px; display: flex; align-items: center; gap: 8px; color: var(--text-primary);"><i class="fa-solid fa-user-tie" style="color: var(--text-secondary);"></i> ${t}</div>`).join('') : '<span style="color: var(--text-secondary);">No teachers assigned</span>'}
                        </div>
                    </div>

                    <div style="padding-top: 20px; border-top: 1px solid var(--glass-border);">
                        <a href="<?php echo $CFG->wwwroot; ?>/course/view.php?id=${data.id}" target="_blank" class="export-btn" style="display: block; text-align: center; text-decoration: none; padding: 16px; font-size: 16px; border-radius: 16px;">
                            Open Course Dashboard <i class="fa-solid fa-arrow-up-right-from-square" style="margin-left: 8px;"></i>
                        </a>
                    </div>
                </div>
            `;
        })
        .catch(err => {
            console.error('Drawer Error:', err);
            content.innerHTML = `
                <div style="height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px; text-align: center;">
                    <i class="fa-solid fa-triangle-exclamation" style="font-size: 48px; color: var(--accent-danger); margin-bottom: 20px;"></i>
                    <h3 style="color: var(--text-primary);">Error Loading Details</h3>
                    <p style="color: var(--text-secondary);">Could not retrieve course information.</p>
                    <button onclick="closeCourseDrawer()" class="export-btn" style="margin-top: 20px;">Close</button>
                </div>
            `;
        });
}

function closeCourseDrawer() {
    document.getElementById('courseDrawerBackdrop').classList.remove('open');
    document.getElementById('courseDrawer').classList.remove('open');
}
</script>
<style>
@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
    70% { box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
    100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

/* Sticky Header & Drawer Styles */
.sticky-header th { position: sticky; top: 0; z-index: 10; background: var(--glass-bg); backdrop-filter: blur(10px); box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.course-drawer { position: fixed; top: 0; right: -600px; width: 600px; height: 100vh; background: var(--bg-body); border-left: 1px solid var(--glass-border); z-index: 6000; transition: right 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: flex; flex-direction: column; box-shadow: -10px 0 40px rgba(0,0,0,0.5); }
.course-drawer.open { right: 0; }
.drawer-backdrop { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 5999; display: none; backdrop-filter: blur(2px); }
.drawer-backdrop.open { display: block; }
</style>



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
<!-- Course Drawer & Backdrop -->
<div id="courseDrawerBackdrop" class="drawer-backdrop" onclick="closeCourseDrawer()"></div>
<div id="courseDrawer" class="course-drawer">
    <div style="padding: 24px; border-bottom: 1px solid var(--glass-border); display: flex; justify-content: space-between; align-items: center;">
        <h3 style="margin: 0; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-secondary);">Quick View</h3>
        <button onclick="closeCourseDrawer()" style="background: none; border: none; color: var(--text-primary); cursor: pointer; font-size: 20px; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: background 0.2s;"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div id="drawerContent" style="flex: 1; overflow-y: auto;">
        <!-- Content -->
    </div>
</div>

<?php echo $OUTPUT->footer(); ?>
