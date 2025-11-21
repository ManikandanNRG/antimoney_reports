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
$kpi_data = $loader->get_admin_kpis();
$company_data = $loader->get_company_analytics();
$course_data = $loader->get_table_data('course_progress', 10);
$system_health = $loader->get_system_health();
$role_data = $loader->get_user_roles_distribution();
$trend_data = $loader->get_completion_trends();

try {
    $live_stats = $loader->get_live_statistics();
} catch (Exception $e) {
    $live_stats = [
        'active_users' => 0,
        'peak_today' => 0,
        'active_courses_count' => 0,
        'top_courses' => [],
        'timeline_labels' => [],
        'timeline_data' => []
    ];
}

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
.status-badge { padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; }
.status-active { background: rgba(16, 185, 129, 0.2); color: var(--accent-success); }
.status-inactive { background: rgba(239, 68, 68, 0.2); color: var(--accent-danger); }
.status-warning { background: rgba(245, 158, 11, 0.2); color: var(--accent-warning); }
.health-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--glass-border); }
.health-item:last-child { border-bottom: none; }
.health-info { display: flex; align-items: center; gap: 12px; }
.health-icon { width: 32px; height: 32px; border-radius: 8px; background: rgba(255, 255, 255, 0.05); display: flex; align-items: center; justify-content: center; color: var(--text-secondary); }
.health-name { font-size: 14px; font-weight: 500; color: var(--text-primary); }
.health-status { display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 500; }
.status-dot { width: 8px; height: 8px; border-radius: 50%; }
.dot-success { background: var(--accent-success); box-shadow: 0 0 8px rgba(16, 185, 129, 0.4); }
.dot-warning { background: var(--accent-warning); box-shadow: 0 0 8px rgba(245, 158, 11, 0.4); }
.dot-danger { background: var(--accent-danger); box-shadow: 0 0 8px rgba(239, 68, 68, 0.4); }
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
            <div class="tab-item" onclick="switchTab('email')"><i class="fa-solid fa-envelope"></i> Email</div>
            <div class="tab-item" onclick="switchTab('certificates')"><i class="fa-solid fa-certificate"></i> Certificates</div>
            <div class="tab-item" onclick="switchTab('reports')"><i class="fa-solid fa-file-lines"></i> Reports</div>
        </div>

        <!-- Filter Area -->
        <div class="filter-area">
            <div class="filter-item">
                <i class="fa-regular fa-calendar" style="color: var(--accent-primary);"></i>
                <input type="text" id="dateStart" class="filter-input" placeholder="Start Date" style="width: 100px;">
                <span style="color: var(--text-secondary);">-</span>
                <input type="text" id="dateEnd" class="filter-input" placeholder="End Date" style="width: 100px;">
            </div>
            <div class="filter-item">
                <button class="filter-select quick-filter-btn" onclick="setDateFilter('1W')">1W</button>
                <button class="filter-select quick-filter-btn" onclick="setDateFilter('1M')">1M</button>
                <button class="filter-select quick-filter-btn" onclick="setDateFilter('3M')">3M</button>
                <button class="filter-select quick-filter-btn" onclick="setDateFilter('YTD')">YTD</button>
                <button class="filter-select quick-filter-btn active" onclick="setDateFilter('ALL')">ALL</button>
            </div>
            <div class="filter-item" style="margin-left: auto;">
                <button class="export-btn"><i class="fa-solid fa-download"></i> Export Report</button>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content-area">
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
                            <th class="table-header">Course Count</th>
                            <th class="table-header">User Count</th>
                            <th class="table-header">Enrolled</th>
                            <th class="table-header">Completed</th>
                            <th class="table-header">Completion %</th>
                            <th class="table-header">Avg Time Spent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (!empty($company_data)) {
                            foreach ($company_data as $company) {
                                $completion_pct = ($company['enrolled'] > 0) ? round(($company['completed'] / $company['enrolled']) * 100) : 0;
                                echo '<tr class="table-row">
                                        <td class="table-cell" style="font-weight: 600;">' . $company['name'] . '</td>
                                        <td class="table-cell">' . $company['courses'] . '</td>
                                        <td class="table-cell">' . $company['users'] . '</td>
                                        <td class="table-cell">' . $company['enrolled'] . '</td>
                                        <td class="table-cell">' . $company['completed'] . '</td>
                                        <td class="table-cell" style="color: var(--accent-success);">' . $completion_pct . '%</td>
                                        <td class="table-cell">' . $company['time'] . '</td>
                                      </tr>';
                            }
                        } else {
                            echo '<tr><td colspan="7" class="table-cell">No company data available.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- Course Analytics Table (Top 10 Courses) -->
            <div class="bento-card card-span-4">
                <div class="card-header">
                    <div class="card-title">Course Analytics (Top 10 Courses)</div>
                </div>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <?php 
                            if (!empty($course_data['headers'])) {
                                foreach ($course_data['headers'] as $header) {
                                    echo '<th class="table-header">' . $header . '</th>';
                                }
                            }
                            ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (!empty($course_data['rows'])) {
                            foreach ($course_data['rows'] as $row) {
                                echo '<tr class="table-row">';
                                foreach ($row as $cell) {
                                    echo '<td class="table-cell">' . $cell . '</td>';
                                }
                                echo '</tr>';
                            }
                        } else {
                            echo '<tr><td colspan="5" class="table-cell">No course data available.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            </div>
        </div>
    </main>
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
    if (typeof userRolesChart !== 'undefined') {
        const newGapColor = isLight ? '#1e293b' : '#ffffff'; // Switched because isLight is the OLD state
        userRolesChart.data.datasets.forEach(dataset => {
            dataset.borderColor = newGapColor;
        });
        userRolesChart.update();
    }
}

// Switch Tabs
function switchTab(tabName) {
    document.querySelectorAll('.tab-item').forEach(tab => tab.classList.remove('active'));
    event.target.closest('.tab-item').classList.add('active');
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    document.getElementById('tab-' + tabName).classList.add('active');
}

document.addEventListener('DOMContentLoaded', function() {
    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { x: { display: false }, y: { display: false } },
        elements: { point: { radius: 0 }, line: { tension: 0.4 } }
    };

    // KPI Mini Charts
    new Chart(document.getElementById('chartCompanies'), {
        type: 'line',
        data: { labels: [1,2,3,4,5,6,7], datasets: [{ data: [18, 19, 20, 21, 22, 23, 24], borderColor: '#6366f1', borderWidth: 2, fill: true, backgroundColor: (ctx) => {
            const gradient = ctx.chart.ctx.createLinearGradient(0, 0, 0, 60);
            gradient.addColorStop(0, 'rgba(99, 102, 241, 0.2)');
            gradient.addColorStop(1, 'rgba(99, 102, 241, 0)');
            return gradient;
        }}]},
        options: commonOptions
    });

    new Chart(document.getElementById('chartCourses'), {
        type: 'line',
        data: { labels: [1,2,3,4,5,6,7], datasets: [{ data: [140, 145, 148, 150, 152, 154, 156], borderColor: '#10b981', borderWidth: 2, fill: true, backgroundColor: (ctx) => {
            const gradient = ctx.chart.ctx.createLinearGradient(0, 0, 0, 60);
            gradient.addColorStop(0, 'rgba(16, 185, 129, 0.2)');
            gradient.addColorStop(1, 'rgba(16, 185, 129, 0)');
            return gradient;
        }}]},
        options: commonOptions
    });

    new Chart(document.getElementById('chartUsers'), {
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
    new Chart(document.getElementById('activeUsersChart'), {
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
    new Chart(document.getElementById('timeSpentChart'), {
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

    // Completion Trend Chart (Multi-line Area)
    new Chart(document.getElementById('completionTrendChart'), {
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

    // User Roles Donut Chart (3 Concentric Rings with Logarithmic Scaling)
    const roleData = {
        admin: <?php echo $role_data['admin']; ?>,
        teacher: <?php echo $role_data['teacher']; ?>,
        student: <?php echo $role_data['student']; ?>
    };

    // Logarithmic Scaling Function
    // We use Math.log10(value + 1) to handle 0 and scale appropriately
    // We normalize against the largest value (student) to determine ring length
    const maxVal = Math.max(roleData.student, roleData.teacher, roleData.admin, 1);
    const logMax = Math.log10(maxVal + 10); // +10 to give a bit of headroom/base

    const getScaledValue = (val) => {
        if (val === 0) return 0;
        // Calculate log score: e.g. log(4) vs log(13000)
        // We want a minimum visibility for non-zero items, say 15%
        const logVal = Math.log10(val + 1);
        const ratio = logVal / logMax;
        
        // Map ratio (0 to 1) to percentage (15 to 100)
        // If ratio is small, boost it.
        return Math.max(ratio * 100, 15); 
    };

    const adminPct = getScaledValue(roleData.admin);
    const teacherPct = getScaledValue(roleData.teacher);
    const studentPct = getScaledValue(roleData.student); // Should be close to 100%

    // Use a neutral grey for the track that works on both dark and light backgrounds
    const trackColor = 'rgba(148, 163, 184, 0.15)'; 
    
    // Dynamic Gap Color
    const isLightMode = document.body.getAttribute('data-theme') === 'light';
    const gapColor = isLightMode ? '#ffffff' : '#1e293b';
    const gapWidth = 4; 

    // Assign to global variable for theme toggling
    window.userRolesChart = new Chart(document.getElementById('chartUserRoles'), {
        type: 'doughnut',
        data: {
            // labels must match the order of datasets (Outer -> Inner)
            labels: ['Student', 'Teacher', 'Admin'], 
            datasets: [
                // Outer Ring (Student)
                {
                    data: [studentPct, 100 - studentPct],
                    backgroundColor: ['#ef4444', trackColor],
                    borderWidth: gapWidth,
                    borderColor: gapColor,
                    borderRadius: [20, 0],
                    cutout: '50%' // Reduced from 85% to give rings more space
                },
                // Middle Ring (Teacher)
                {
                    data: [teacherPct, 100 - teacherPct],
                    backgroundColor: ['#f59e0b', trackColor],
                    borderWidth: gapWidth,
                    borderColor: gapColor,
                    borderRadius: [20, 0],
                    cutout: '50%'
                },
                // Inner Ring (Admin)
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
                            // Show real data in tooltip, not the scaled percentage
                            const label = context.chart.data.labels[context.datasetIndex];
                            const realValue = roleData[label.toLowerCase()];
                            return `${label}: ${realValue}`;
                        }
                    }
                }
            },
            animation: {
                animateScale: true,
                animateRotate: true
            }
        }
    });
    });

document.addEventListener('DOMContentLoaded', function() {
    // Live Update Timer
    let seconds = 0;
    setInterval(function() {
        seconds++;
        document.getElementById('live-update-timer').innerText = seconds;
    }, 1000);

    // 24h Timeline Chart
    const timelineCtx = document.getElementById('timelineChart').getContext('2d');
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
                    ticks: { 
                        color: '#94a3b8', 
                        font: { size: 10 },
                        maxTicksLimit: 6 
                    }
                }
            }
        }
    });
    });

// Date Filter Logic
function setDateFilter(range) {
    const buttons = document.querySelectorAll('.quick-filter-btn');
    buttons.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');

    const today = new Date();
    let startDate = new Date();

    switch(range) {
        case '1W':
            startDate.setDate(today.getDate() - 7);
            break;
        case '1M':
            startDate.setMonth(today.getMonth() - 1);
            break;
        case '3M':
            startDate.setMonth(today.getMonth() - 3);
            break;
        case 'YTD':
            startDate = new Date(today.getFullYear(), 0, 1);
            break;
        case 'ALL':
            startDate = new Date(2000, 0, 1); // Arbitrary past date
            break;
    }

    // Format dates as dd-mm-yyyy
    const formatDate = (date) => {
        const d = date.getDate().toString().padStart(2, '0');
        const m = (date.getMonth() + 1).toString().padStart(2, '0');
        const y = date.getFullYear();
        return `${d}-${m}-${y}`;
    };

    document.getElementById('dateStart').value = formatDate(startDate);
    document.getElementById('dateEnd').value = formatDate(today);

    // Trigger backend update (Reload page with params for now)
    // window.location.href = `?start=${formatDate(startDate)}&end=${formatDate(today)}`;
    // For now, just log to console as we are in dev mode
    console.log(`Filter applied: ${range} (${formatDate(startDate)} - ${formatDate(today)})`);
}
</script>
<style>
@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
    70% { box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
    100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}
</style>

<?php
echo $OUTPUT->footer();
?>
