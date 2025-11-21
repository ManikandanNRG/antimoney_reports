<?php
/**
 * Dashboard V7 - Ultimate Admin Dashboard with Tabs & Filters
 * Based on V6 Glassmorphic Design Template
 * 
 * Features:
 * - Collapsible Sidebar (hamburger menu)
 * - Horizontal Tab Menu (Overview, Courses, Companies, Users, Email, Certificates, Schedules)
 * - Global Filter Area (Date, Company, Course, User, Role, Export)
 * - Persistent KPI Cards (4 cards always visible)
 * - Tab-specific content areas
 */

require_once(__DIR__ . '/../../../config.php');
require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/manireports/designs/dashboard_v7_ultimate.php'));
$PAGE->set_title('ManiReports - Dashboard V7');
$PAGE->set_heading('Dashboard V7 Ultimate');
$PAGE->set_pagelayout('embedded');

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
.dashboard-container { display: grid; grid-template-columns: 280px 1fr; height: 100vh; overflow: hidden; transition: var(--transition); }
.dashboard-container.sidebar-collapsed { grid-template-columns: 0px 1fr; }
.sidebar {
    padding: 32px; background: var(--sidebar-bg); backdrop-filter: blur(20px);
    border-right: 1px solid var(--glass-border); display: flex; flex-direction: column;
    gap: 40px; height: 100%; overflow-y: auto; z-index: 100;
    transition: var(--transition); width: 280px;
}
.sidebar-collapsed .sidebar { transform: translateX(-100%); width: 0; padding: 0; opacity: 0; }
.hamburger-btn {
    width: 48px; height: 48px; border-radius: 14px; border: 1px solid var(--glass-border);
    background: var(--glass-bg); color: var(--text-primary); display: flex;
    align-items: center; justify-content: center; cursor: pointer; transition: var(--transition);
    font-size: 20px; margin-right: 16px;
}
.hamburger-btn:hover { background: rgba(99, 102, 241, 0.1); }
.brand { display: flex; align-items: center; gap: 12px; font-size: 24px; font-weight: 700; color: var(--text-primary); flex-shrink: 0; }
.brand-logo {
    width: 40px; height: 40px; background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
    border-radius: 12px; display: flex; align-items: center; justify-content: center;
    font-size: 20px; color: white; box-shadow: 0 8px 16px rgba(99, 102, 241, 0.25);
}
.nav-menu { display: flex; flex-direction: column; gap: 8px; }
.nav-label { font-size: 12px; text-transform: uppercase; letter-spacing: 1.5px; color: var(--text-secondary); margin-bottom: 12px; padding-left: 16px; }
.nav-item {
    display: flex; align-items: center; gap: 16px; padding: 16px; border-radius: 16px;
    color: var(--text-secondary); text-decoration: none; transition: var(--transition);
    font-weight: 500; cursor: pointer; position: relative; z-index: 1;
}
.nav-item:hover, .nav-item.active { background: rgba(99, 102, 241, 0.1); color: var(--text-primary); }
.nav-item.active { border-left: 3px solid var(--accent-primary); }
.nav-item i { width: 20px; text-align: center; font-size: 18px; }
.main-content { padding: 0; height: 100%; overflow-y: auto; scroll-behavior: smooth; }
.header { display: flex; justify-content: space-between; align-items: center; padding: 24px 40px; background: var(--glass-bg); border-bottom: 1px solid var(--glass-border); backdrop-filter: blur(10px); position: sticky; top: 0; z-index: 50; }
.header-left { display: flex; align-items: center; gap: 16px; }
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
    padding: 8px 16px; background: rgba(0, 0, 0, 0.1); border: 1px solid var(--glass-border);
    border-radius: 10px; color: var(--text-primary); font-family: inherit; outline: none;
    transition: var(--transition); min-width: 150px;
}
[data-theme="light"] .filter-select, [data-theme="light"] .filter-input { background: rgba(255, 255, 255, 0.5); }
.filter-select:focus, .filter-input:focus { border-color: var(--accent-primary); background: rgba(0, 0, 0, 0.15); }
.export-btn {
    padding: 8px 16px; background: var(--accent-primary); color: white; border: none;
    border-radius: 10px; cursor: pointer; transition: var(--transition); font-weight: 500;
}
.export-btn:hover { background: var(--accent-secondary); transform: translateY(-2px); }
.content-area { padding: 40px; }
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
.card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
.card-title { font-size: 16px; font-weight: 600; color: var(--text-secondary); display: flex; align-items: center; gap: 8px; }
.card-value { font-size: 36px; font-weight: 700; margin-bottom: 8px; color: var(--text-primary); }
.card-trend { font-size: 14px; display: flex; align-items: center; gap: 4px; }
.trend-up { color: var(--accent-success); }
.trend-down { color: var(--accent-danger); }
.card-illustration { position: absolute; top: 10px; right: 10px; width: 100px; height: 100px; object-fit: contain; opacity: 0.9; pointer-events: none; z-index: 0; }
.card-content-wrapper { position: relative; z-index: 1; }
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
    .dashboard-container { grid-template-columns: 1fr; }
    .sidebar { display: none; }
    .bento-grid { grid-template-columns: 1fr; }
    .card-span-1, .card-span-2, .card-span-3, .card-span-4 { grid-column: span 1; }
}
</style>

<div class="dashboard-container" id="dashboardContainer">
    <aside class="sidebar">
        <div class="brand"><div class="brand-logo">M</div><span>ManiReports</span></div>
        <nav class="nav-menu">
            <div class="nav-label">Overview</div>
            <a href="#" class="nav-item active"><i class="fa-solid fa-grid-2"></i> <span>Dashboard</span></a>
            <a href="#" class="nav-item"><i class="fa-solid fa-chart-pie"></i> <span>Reports</span></a>
            <a href="#" class="nav-item"><i class="fa-solid fa-users"></i> <span>Users</span></a>
            <div class="nav-label">Management</div>
            <a href="#" class="nav-item"><i class="fa-solid fa-book-open"></i> <span>Courses</span></a>
            <a href="#" class="nav-item"><i class="fa-solid fa-calendar-check"></i> <span>Schedules</span></a>
            <a href="#" class="nav-item"><i class="fa-solid fa-cloud"></i> <span>Cloud Jobs</span></a>
        </nav>
    </aside>

    <main class="main-content">
        <!-- Header -->
        <header class="header">
            <div class="header-left">
                <button class="hamburger-btn" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
                <div class="welcome-text">
                    <h1 style="font-size: 24px; margin: 0;">Welcome back, Admin 👋</h1>
                    <p style="margin: 0; font-size: 14px;">Complete platform analytics and monitoring</p>
                </div>
            </div>
            <div class="header-actions">
                <div class="theme-toggle" onclick="toggleTheme()"><div class="theme-toggle-thumb"><i class="fa-solid fa-moon"></i></div></div>
                <button class="icon-btn"><i class="fa-regular fa-bell"></i></button>
                <div class="user-profile"><div class="avatar"></div><span style="font-size: 14px; font-weight: 500;">Admin</span></div>
            </div>
        </header>

        <!-- Tab Menu -->
        <div class="tab-menu">
            <div class="tab-item active" onclick="switchTab('overview')"><i class="fa-solid fa-chart-line"></i> Overview</div>
            <div class="tab-item" onclick="switchTab('courses')"><i class="fa-solid fa-book"></i> Courses</div>
            <div class="tab-item" onclick="switchTab('companies')"><i class="fa-solid fa-building"></i> Companies</div>
            <div class="tab-item" onclick="switchTab('users')"><i class="fa-solid fa-users"></i> Users</div>
            <div class="tab-item" onclick="switchTab('email')"><i class="fa-solid fa-envelope"></i> Email</div>
            <div class="tab-item" onclick="switchTab('certificates')"><i class="fa-solid fa-certificate"></i> Certificates</div>
            <div class="tab-item" onclick="switchTab('schedules')"><i class="fa-solid fa-calendar-check"></i> Schedules</div>
        </div>

        <!-- Filter Area -->
        <div class="filter-area">
            <div class="filter-item">
                <i class="fa-solid fa-calendar" style="color: var(--text-secondary);"></i>
                <select class="filter-select" id="dateRange">
                    <option value="7d">Last 7 Days</option>
                    <option value="30d" selected>Last 30 Days</option>
                    <option value="90d">Last 90 Days</option>
                    <option value="365d">Last Year</option>
                </select>
            </div>
            <div class="filter-item">
                <i class="fa-solid fa-building" style="color: var(--text-secondary);"></i>
                <select class="filter-select" id="companyFilter">
                    <option value="">All Companies</option>
                    <option value="1">Tech Corp</option>
                    <option value="2">Edu Solutions</option>
                    <option value="3">Global Training</option>
                </select>
            </div>
            <div class="filter-item">
                <i class="fa-solid fa-book" style="color: var(--text-secondary);"></i>
                <select class="filter-select" id="courseFilter">
                    <option value="">All Courses</option>
                    <option value="1">Python for Data Science</option>
                    <option value="2">Machine Learning</option>
                </select>
            </div>
            <div class="filter-item">
                <i class="fa-solid fa-user" style="color: var(--text-secondary);"></i>
                <input type="text" class="filter-input" id="userSearch" placeholder="Search user or email...">
            </div>
            <div class="filter-item">
                <i class="fa-solid fa-user-tag" style="color: var(--text-secondary);"></i>
                <select class="filter-select" id="roleFilter">
                    <option value="">All Roles</option>
                    <option value="admin">Admin</option>
                    <option value="manager">Manager</option>
                    <option value="teacher">Teacher</option>
                    <option value="student">Student</option>
                </select>
            </div>
            <button class="export-btn"><i class="fa-solid fa-download"></i> Export</button>
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
                    <div class="card-value">24</div>
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
                    <div class="card-value">156</div>
                    <div style="height: 60px;"><canvas id="chartCourses"></canvas></div>
                </div>
            </div>

            <!-- KPI 3: Total Users -->
            <div class="bento-card card-span-1">
                <img src="https://avatar.iran.liara.run/public/boy?username=Users" class="card-illustration" alt="Users">
                <div class="card-content-wrapper">
                    <div class="card-header">
                        <div class="card-title"><i class="fa-solid fa-users" style="color: var(--accent-warning);"></i> Total Users</div>
                    </div>
                    <div class="card-value">8,542</div>
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
                    <div class="card-value">76%</div>
                    <div style="height: 6px; background: rgba(255,255,255,0.1); border-radius: 3px; margin-top: 20px;">
                        <div style="width: 76%; height: 100%; background: var(--accent-secondary); border-radius: 3px;"></div>
                    </div>
                </div>
            </div>
            </div>
            <!-- End KPI Cards -->

            <!-- Tab Content: Overview -->
            <div id="tab-overview" class="tab-content active">
                <div class="bento-grid">

            <!-- System Health Widget -->
            <div class="bento-card card-span-1 card-row-2">
                <div class="card-header">
                    <div class="card-title">System Health</div>
                    <div class="status-badge status-active">Good</div>
                </div>
                <div style="display: flex; flex-direction: column; gap: 4px;">
                    <div class="health-item">
                        <div class="health-info">
                            <div class="health-icon"><i class="fa-solid fa-database"></i></div>
                            <div class="health-name">Database Size</div>
                        </div>
                        <div class="health-status">
                            <div class="status-dot dot-success"></div> 850MB
                        </div>
                    </div>
                    <div class="health-item">
                        <div class="health-info">
                            <div class="health-icon"><i class="fa-solid fa-bolt"></i></div>
                            <div class="health-name">Cache Hit Rate</div>
                        </div>
                        <div class="health-status">
                            <div class="status-dot dot-success"></div> 96%
                        </div>
                    </div>
                    <div class="health-item">
                        <div class="health-info">
                            <div class="health-icon"><i class="fa-solid fa-hourglass"></i></div>
                            <div class="health-name">Avg Query Time</div>
                        </div>
                        <div class="health-status">
                            <div class="status-dot dot-success"></div> 45ms
                        </div>
                    </div>
                    <div class="health-item">
                        <div class="health-info">
                            <div class="health-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                            <div class="health-name">Error Rate</div>
                        </div>
                        <div class="health-status">
                            <div class="status-dot dot-success"></div> 0.08%
                        </div>
                    </div>
                    <div class="health-item">
                        <div class="health-info">
                            <div class="health-icon"><i class="fa-solid fa-clock"></i></div>
                            <div class="health-name">Last Cache Update</div>
                        </div>
                        <div class="health-status">
                            <div class="status-dot dot-success"></div> 2m ago
                        </div>
                    </div>
                    <div class="health-item">
                        <div class="health-info">
                            <div class="health-icon"><i class="fa-solid fa-calendar-check"></i></div>
                            <div class="health-name">Scheduled Tasks</div>
                        </div>
                        <div class="health-status">
                            <div class="status-dot dot-success"></div> Running
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Users 24h Time Chart -->
            <div class="bento-card card-span-2">
                <div class="card-header">
                    <div class="card-title">Active Users (24h)</div>
                    <select id="activeUsersRange" style="background: var(--glass-bg); border: 1px solid var(--glass-border); color: var(--text-primary); padding: 4px 8px; border-radius: 8px; outline: none;">
                        <option value="7d">Last 7 Days</option>
                        <option value="30d" selected>Last 30 Days</option>
                    </select>
                </div>
                <div style="height: 250px; width: 100%;">
                    <canvas id="activeUsersChart"></canvas>
                </div>
            </div>

            <!-- Avg Time Spent per User Chart -->
            <div class="bento-card card-span-1">
                <div class="card-header">
                    <div class="card-title">Avg Time/User</div>
                    <select id="timeSpentRange" style="background: var(--glass-bg); border: 1px solid var(--glass-border); color: var(--text-primary); padding: 4px 8px; border-radius: 8px; outline: none;">
                        <option value="7d">7d</option>
                        <option value="30d" selected>30d</option>
                    </select>
                </div>
                <div style="height: 250px; width: 100%;">
                    <canvas id="timeSpentChart"></canvas>
                </div>
            </div>

            <!-- Course Completion Trend Chart -->
            <div class="bento-card card-span-3">
                <div class="card-header">
                    <div class="card-title">Course Completion Trend</div>
                    <select id="completionRange" style="background: var(--glass-bg); border: 1px solid var(--glass-border); color: var(--text-primary); padding: 4px 8px; border-radius: 8px; outline: none;">
                        <option value="30d">Last 30 Days</option>
                        <option value="90d" selected>Last 90 Days</option>
                        <option value="365d">Last 365 Days</option>
                    </select>
                </div>
                <div style="height: 300px; width: 100%;">
                    <canvas id="completionTrendChart"></canvas>
                </div>
            </div>

            <!-- Company-wise Analytics Table -->
            <div class="bento-card card-span-4">
                <div class="card-header">
                    <div class="card-title">Company-wise Analytics</div>
                    <button class="icon-btn" style="width: 32px; height: 32px;"><i class="fa-solid fa-ellipsis"></i></button>
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
                        $companies = [
                            ['name' => 'Tech Corp', 'courses' => 45, 'users' => 1200, 'enrolled' => 3400, 'completed' => 2890, 'time' => '24.5h'],
                            ['name' => 'Edu Solutions', 'courses' => 38, 'users' => 980, 'enrolled' => 2850, 'completed' => 2280, 'time' => '22.1h'],
                            ['name' => 'Global Training', 'courses' => 52, 'users' => 1450, 'enrolled' => 4200, 'completed' => 3360, 'time' => '28.3h'],
                            ['name' => 'Learning Hub', 'courses' => 29, 'users' => 720, 'enrolled' => 1980, 'completed' => 1584, 'time' => '18.7h'],
                        ];
                        foreach ($companies as $company) {
                            $completion_pct = round(($company['completed'] / $company['enrolled']) * 100);
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
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- Course Analytics Table (Top 10 Courses) -->
            <div class="bento-card card-span-4">
                <div class="card-header">
                    <div class="card-title">Course Analytics (Top 10 Courses)</div>
                    <button class="icon-btn" style="width: 32px; height: 32px;"><i class="fa-solid fa-download"></i></button>
                </div>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th class="table-header">Course Name</th>
                            <th class="table-header">Shortname</th>
                            <th class="table-header">Enrolled</th>
                            <th class="table-header">Completed</th>
                            <th class="table-header">Completion %</th>
                            <th class="table-header">Avg Time</th>
                            <th class="table-header">Avg Grade</th>
                            <th class="table-header">Last Activity</th>
                            <th class="table-header">Active Users (7d/30d)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $courses = [
                            ['name' => 'Python for Data Science', 'short' => 'PY101', 'enrolled' => 450, 'completed' => 380, 'time' => '24.5h', 'grade' => 85, 'date' => '2025-01-18', 'active7' => 120, 'active30' => 380],
                            ['name' => 'Advanced Machine Learning', 'short' => 'ML201', 'enrolled' => 320, 'completed' => 245, 'time' => '32.1h', 'grade' => 82, 'date' => '2025-01-17', 'active7' => 95, 'active30' => 280],
                            ['name' => 'Web Development Bootcamp', 'short' => 'WEB300', 'enrolled' => 280, 'completed' => 210, 'time' => '28.3h', 'grade' => 88, 'date' => '2025-01-18', 'active7' => 85, 'active30' => 245],
                            ['name' => 'Cybersecurity Basics', 'short' => 'SEC101', 'enrolled' => 210, 'completed' => 156, 'time' => '18.7h', 'grade' => 79, 'date' => '2025-01-16', 'active7' => 62, 'active30' => 180],
                            ['name' => 'Cloud Computing Essentials', 'short' => 'CLOUD201', 'enrolled' => 195, 'completed' => 142, 'time' => '22.4h', 'grade' => 84, 'date' => '2025-01-18', 'active7' => 58, 'active30' => 165],
                        ];
                        foreach ($courses as $course) {
                            $completion_pct = round(($course['completed'] / $course['enrolled']) * 100);
                            echo '<tr class="table-row">
                                    <td class="table-cell" style="font-weight: 600;">' . $course['name'] . '</td>
                                    <td class="table-cell" style="color: var(--text-secondary);">' . $course['short'] . '</td>
                                    <td class="table-cell">' . $course['enrolled'] . '</td>
                                    <td class="table-cell">' . $course['completed'] . '</td>
                                    <td class="table-cell" style="color: var(--accent-success);">' . $completion_pct . '%</td>
                                    <td class="table-cell">' . $course['time'] . '</td>
                                    <td class="table-cell">' . $course['grade'] . '%</td>
                                    <td class="table-cell" style="color: var(--text-secondary);">' . $course['date'] . '</td>
                                    <td class="table-cell">' . $course['active7'] . ' / ' . $course['active30'] . '</td>
                                  </tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- User/Learner Analytics Table -->
            <div class="bento-card card-span-4">
                <div class="card-header">
                    <div class="card-title">User/Learner Analytics</div>
                    <button class="icon-btn" style="width: 32px; height: 32px;"><i class="fa-solid fa-filter"></i></button>
                </div>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th class="table-header">User Name</th>
                            <th class="table-header">Email</th>
                            <th class="table-header">Company</th>
                            <th class="table-header">Role</th>
                            <th class="table-header">Enrolled Courses</th>
                            <th class="table-header">Completed</th>
                            <th class="table-header">Last Login</th>
                            <th class="table-header">Avg Time/Day</th>
                            <th class="table-header">Risk Score</th>
                            <th class="table-header">Last Cert</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $users = [
                            ['name' => 'John Smith', 'email' => 'john@techcorp.com', 'company' => 'Tech Corp', 'role' => 'Student', 'enrolled' => 8, 'completed' => 6, 'login' => '2025-01-18', 'time' => '2.5h', 'risk' => 15, 'cert' => '2025-01-10'],
                            ['name' => 'Sarah Johnson', 'email' => 'sarah@edu.com', 'company' => 'Edu Solutions', 'role' => 'Student', 'enrolled' => 5, 'completed' => 5, 'login' => '2025-01-17', 'time' => '3.2h', 'risk' => 5, 'cert' => '2025-01-15'],
                            ['name' => 'Mike Davis', 'email' => 'mike@global.com', 'company' => 'Global Training', 'role' => 'Student', 'enrolled' => 12, 'completed' => 8, 'login' => '2025-01-18', 'time' => '4.1h', 'risk' => 20, 'cert' => '2025-01-12'],
                            ['name' => 'Emily Brown', 'email' => 'emily@learning.com', 'company' => 'Learning Hub', 'role' => 'Student', 'enrolled' => 6, 'completed' => 4, 'login' => '2025-01-16', 'time' => '1.8h', 'risk' => 35, 'cert' => '2024-12-20'],
                        ];
                        foreach ($users as $user) {
                            $risk_color = $user['risk'] < 20 ? 'var(--accent-success)' : ($user['risk'] < 40 ? 'var(--accent-warning)' : 'var(--accent-danger)');
                            echo '<tr class="table-row">
                                    <td class="table-cell" style="font-weight: 600;">' . $user['name'] . '</td>
                                    <td class="table-cell" style="color: var(--text-secondary);">' . $user['email'] . '</td>
                                    <td class="table-cell">' . $user['company'] . '</td>
                                    <td class="table-cell">' . $user['role'] . '</td>
                                    <td class="table-cell">' . $user['enrolled'] . '</td>
                                    <td class="table-cell">' . $user['completed'] . '</td>
                                    <td class="table-cell" style="color: var(--text-secondary);">' . $user['login'] . '</td>
                                    <td class="table-cell">' . $user['time'] . '</td>
                                    <td class="table-cell" style="color: ' . $risk_color . ';">' . $user['risk'] . '</td>
                                    <td class="table-cell" style="color: var(--text-secondary);">' . $user['cert'] . '</td>
                                  </tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- SCORM Analytics Table -->
            <div class="bento-card card-span-4">
                <div class="card-header">
                    <div class="card-title">SCORM Analytics</div>
                    <button class="icon-btn" style="width: 32px; height: 32px;"><i class="fa-solid fa-chart-bar"></i></button>
                </div>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th class="table-header">SCORM Title</th>
                            <th class="table-header">Course</th>
                            <th class="table-header">Attempts</th>
                            <th class="table-header">Unique Learners</th>
                            <th class="table-header">Completed</th>
                            <th class="table-header">Passed</th>
                            <th class="table-header">Failed</th>
                            <th class="table-header">Avg Time</th>
                            <th class="table-header">Interactions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $scorms = [
                            ['title' => 'Python Basics Module', 'course' => 'PY101', 'attempts' => 450, 'learners' => 380, 'completed' => 360, 'passed' => 340, 'failed' => 20, 'time' => '45m', 'interactions' => 1250],
                            ['title' => 'ML Algorithms Deep Dive', 'course' => 'ML201', 'attempts' => 320, 'learners' => 245, 'completed' => 230, 'passed' => 210, 'failed' => 20, 'time' => '62m', 'interactions' => 980],
                            ['title' => 'Web Security Fundamentals', 'course' => 'SEC101', 'attempts' => 210, 'learners' => 156, 'completed' => 145, 'passed' => 135, 'failed' => 10, 'time' => '38m', 'interactions' => 620],
                        ];
                        foreach ($scorms as $scorm) {
                            echo '<tr class="table-row">
                                    <td class="table-cell" style="font-weight: 600;">' . $scorm['title'] . '</td>
                                    <td class="table-cell" style="color: var(--text-secondary);">' . $scorm['course'] . '</td>
                                    <td class="table-cell">' . $scorm['attempts'] . '</td>
                                    <td class="table-cell">' . $scorm['learners'] . '</td>
                                    <td class="table-cell" style="color: var(--accent-success);">' . $scorm['completed'] . '</td>
                                    <td class="table-cell" style="color: var(--accent-success);">' . $scorm['passed'] . '</td>
                                    <td class="table-cell" style="color: var(--accent-danger);">' . $scorm['failed'] . '</td>
                                    <td class="table-cell">' . $scorm['time'] . '</td>
                                    <td class="table-cell">' . $scorm['interactions'] . '</td>
                                  </tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- Reports & Schedules Panel -->
            <div class="bento-card card-span-2">
                <div class="card-header">
                    <div class="card-title">Reports & Schedules</div>
                    <button class="icon-btn" style="width: 32px; height: 32px;"><i class="fa-solid fa-plus"></i></button>
                </div>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th class="table-header">Report Name</th>
                            <th class="table-header">Created By</th>
                            <th class="table-header">Frequency</th>
                            <th class="table-header">Next Run</th>
                            <th class="table-header">Last Run</th>
                            <th class="table-header">Status</th>
                            <th class="table-header">Recipients</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $reports = [
                            ['name' => 'Weekly Completion Report', 'creator' => 'Admin', 'freq' => 'Weekly', 'next' => '2025-01-25', 'last' => '2025-01-18', 'status' => 'Active', 'recipients' => 5],
                            ['name' => 'Monthly User Engagement', 'creator' => 'Manager', 'freq' => 'Monthly', 'next' => '2025-02-01', 'last' => '2025-01-01', 'status' => 'Active', 'recipients' => 12],
                            ['name' => 'Daily Active Users', 'creator' => 'Admin', 'freq' => 'Daily', 'next' => '2025-01-19', 'last' => '2025-01-18', 'status' => 'Active', 'recipients' => 3],
                        ];
                        foreach ($reports as $report) {
                            $status_class = $report['status'] == 'Active' ? 'status-active' : 'status-inactive';
                            echo '<tr class="table-row">
                                    <td class="table-cell" style="font-weight: 600;">' . $report['name'] . '</td>
                                    <td class="table-cell">' . $report['creator'] . '</td>
                                    <td class="table-cell">' . $report['freq'] . '</td>
                                    <td class="table-cell" style="color: var(--text-secondary);">' . $report['next'] . '</td>
                                    <td class="table-cell" style="color: var(--text-secondary);">' . $report['last'] . '</td>
                                    <td class="table-cell"><span class="status-badge ' . $status_class . '">' . $report['status'] . '</span></td>
                                    <td class="table-cell">' . $report['recipients'] . '</td>
                                  </tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- Cloud Offload/Job Monitor -->
            <div class="bento-card card-span-2">
                <div class="card-header">
                    <div class="card-title">Cloud Offload / Job Monitor</div>
                    <button class="icon-btn" style="width: 32px; height: 32px;"><i class="fa-solid fa-refresh"></i></button>
                </div>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th class="table-header">Job Type</th>
                            <th class="table-header">Tenant/Company</th>
                            <th class="table-header">Status</th>
                            <th class="table-header">Total Recipients</th>
                            <th class="table-header">Succeeded</th>
                            <th class="table-header">Failed</th>
                            <th class="table-header">Created</th>
                            <th class="table-header">Queue Depth</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $jobs = [
                            ['type' => 'Email Report', 'tenant' => 'Tech Corp', 'status' => 'Completed', 'total' => 120, 'success' => 118, 'failed' => 2, 'created' => '2025-01-18 08:00', 'queue' => 0],
                            ['type' => 'Certificate Generation', 'tenant' => 'Edu Solutions', 'status' => 'Processing', 'total' => 45, 'success' => 38, 'failed' => 0, 'created' => '2025-01-18 09:15', 'queue' => 7],
                            ['type' => 'Bulk Email', 'tenant' => 'Global Training', 'status' => 'Queued', 'total' => 850, 'success' => 0, 'failed' => 0, 'created' => '2025-01-18 10:30', 'queue' => 850],
                        ];
                        foreach ($jobs as $job) {
                            $status_class = $job['status'] == 'Completed' ? 'status-active' : ($job['status'] == 'Processing' ? 'status-warning' : 'status-inactive');
                            echo '<tr class="table-row">
                                    <td class="table-cell" style="font-weight: 600;">' . $job['type'] . '</td>
                                    <td class="table-cell">' . $job['tenant'] . '</td>
                                    <td class="table-cell"><span class="status-badge ' . $status_class . '">' . $job['status'] . '</span></td>
                                    <td class="table-cell">' . $job['total'] . '</td>
                                    <td class="table-cell" style="color: var(--accent-success);">' . $job['success'] . '</td>
                                    <td class="table-cell" style="color: var(--accent-danger);">' . $job['failed'] . '</td>
                                    <td class="table-cell" style="color: var(--text-secondary);">' . $job['created'] . '</td>
                                    <td class="table-cell">' . $job['queue'] . '</td>
                                  </tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>

        </div>
    </main>
</div>

<script>
// Toggle Sidebar
function toggleSidebar() {
    const container = document.getElementById('dashboardContainer');
    container.classList.toggle('sidebar-collapsed');
}

// Toggle Theme
function toggleTheme() {
    const body = document.body;
    const icon = document.querySelector('.theme-toggle-thumb i');
    if (body.getAttribute('data-theme') === 'light') {
        body.removeAttribute('data-theme');
        icon.className = 'fa-solid fa-moon';
    } else {
        body.setAttribute('data-theme', 'light');
        icon.className = 'fa-solid fa-sun';
    }
}

// Switch Tabs
function switchTab(tabName) {
    // Update tab buttons
    document.querySelectorAll('.tab-item').forEach(tab => tab.classList.remove('active'));
    event.target.closest('.tab-item').classList.add('active');
    
    // Update tab content
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
        type: 'bar',
        data: { labels: [1,2,3,4,5,6,7], datasets: [{ data: [7800, 7950, 8100, 8250, 8350, 8450, 8542], backgroundColor: '#f59e0b', borderRadius: 2 }]},
        options: commonOptions
    });

    // Active Users Chart
    new Chart(document.getElementById('activeUsersChart'), {
        type: 'line',
        data: {
            labels: ['Day 1', 'Day 2', 'Day 3', 'Day 4', 'Day 5', 'Day 6', 'Day 7'],
            datasets: [{
                label: 'Active Users',
                data: [680, 720, 710, 780, 750, 820, 850],
                borderColor: '#6366f1',
                backgroundColor: (ctx) => {
                    const gradient = ctx.chart.ctx.createLinearGradient(0, 0, 0, 250);
                    gradient.addColorStop(0, 'rgba(99, 102, 241, 0.3)');
                    gradient.addColorStop(1, 'rgba(99, 102, 241, 0)');
                    return gradient;
                },
                borderWidth: 3,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { grid: { color: 'rgba(255, 255, 255, 0.05)' }, ticks: { color: '#94a3b8', font: { family: 'Outfit' } } },
                x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { family: 'Outfit' } } }
            }
        }
    });

    // Time Spent Chart
    new Chart(document.getElementById('timeSpentChart'), {
        type: 'bar',
        data: {
            labels: ['W1', 'W2', 'W3', 'W4'],
            datasets: [{
                label: 'Avg Time (hours)',
                data: [2.3, 2.5, 2.8, 3.1],
                backgroundColor: '#10b981',
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(255, 255, 255, 0.05)' }, ticks: { color: '#94a3b8', font: { family: 'Outfit' } } },
                x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { family: 'Outfit' } } }
            }
        }
    });

    // Completion Trend Chart
    new Chart(document.getElementById('completionTrendChart'), {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            datasets: [
                {
                    label: 'Completions',
                    data: [450, 580, 620, 750, 820, 950, 1100, 1250, 1380, 1520, 1680, 1850],
                    borderColor: '#f59e0b',
                    backgroundColor: (ctx) => {
                        const gradient = ctx.chart.ctx.createLinearGradient(0, 0, 0, 300);
                        gradient.addColorStop(0, 'rgba(245, 158, 11, 0.4)');
                        gradient.addColorStop(1, 'rgba(245, 158, 11, 0)');
                        return gradient;
                    },
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'Enrollments',
                    data: [1200, 1450, 1580, 1850, 2100, 2350, 2600, 2850, 3100, 3350, 3600, 3850],
                    borderColor: '#10b981',
                    backgroundColor: 'transparent',
                    borderWidth: 2,
                    borderDash: [5, 5],
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: { color: '#94a3b8', font: { family: 'Outfit' } },
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
});
</script>

<?php
echo $OUTPUT->footer();
?>
