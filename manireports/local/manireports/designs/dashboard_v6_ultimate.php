<?php
/**
 * Dashboard Design V6 - The Ultimate Glassmorphic Experience
 * 
 * Features:
 * - Light/Dark Mode Toggle
 * - Premium Glassmorphism UI
 * - Bento Grid Layout
 * - Real Admin Metrics (Mock Data)
 * - Interactive Visualizations
 */

require_once(__DIR__ . '/../../../config.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/manireports/designs/dashboard_v6_ultimate.php'));
$PAGE->set_title('ManiReports - Ultimate Dashboard');
$PAGE->set_heading('ManiReports Ultimate');
$PAGE->set_pagelayout('embedded');

echo $OUTPUT->header();
?>

<!-- External Libraries -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    :root {
        /* Dark Theme (Default) */
        --bg-body: #0f172a;
        --glass-bg: rgba(30, 41, 59, 0.7);
        --glass-border: rgba(255, 255, 255, 0.08);
        --text-primary: #f8fafc;
        --text-secondary: #94a3b8;
        --card-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        --sidebar-bg: rgba(15, 23, 42, 0.6);
        
        /* Accents */
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
        margin: 0;
        padding: 0;
        background-color: var(--bg-body);
        background-image: 
            radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.15) 0px, transparent 50%),
            radial-gradient(at 100% 0%, rgba(139, 92, 246, 0.15) 0px, transparent 50%);
        background-attachment: fixed;
        color: var(--text-primary);
        font-family: 'Outfit', sans-serif;
        min-height: 100vh;
        transition: background-color 0.3s ease;
    }

    .dashboard-container {
        display: grid;
        grid-template-columns: 280px 1fr;
        min-height: 100vh;
    }

    /* Sidebar */
    .sidebar {
        padding: 32px;
        background: var(--sidebar-bg);
        backdrop-filter: blur(20px);
        border-right: 1px solid var(--glass-border);
        display: flex;
        flex-direction: column;
        gap: 40px;
        position: sticky;
        top: 0;
        height: 100vh;
        z-index: 100;
    }

    .brand {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 24px;
        font-weight: 700;
        color: var(--text-primary);
    }

    .brand-logo {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        color: white;
        box-shadow: 0 8px 16px rgba(99, 102, 241, 0.25);
    }

    .nav-menu {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .nav-label {
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        color: var(--text-secondary);
        margin-bottom: 12px;
        padding-left: 16px;
    }

    .nav-item {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 16px;
        border-radius: 16px;
        color: var(--text-secondary);
        text-decoration: none;
        transition: var(--transition);
        font-weight: 500;
    }

    .nav-item:hover, .nav-item.active {
        background: rgba(99, 102, 241, 0.1);
        color: var(--text-primary);
    }

    .nav-item.active {
        border-left: 3px solid var(--accent-primary);
    }

    .nav-item i {
        width: 20px;
        text-align: center;
        font-size: 18px;
    }

    /* Main Content */
    .main-content {
        padding: 40px;
        overflow-y: auto;
    }

    .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 40px;
    }

    .welcome-text h1 {
        font-size: 32px;
        margin: 0 0 8px 0;
        font-weight: 600;
        color: var(--text-primary);
    }

    .welcome-text p {
        margin: 0;
        color: var(--text-secondary);
    }

    .header-actions {
        display: flex;
        gap: 16px;
        align-items: center;
    }

    .icon-btn {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        border: 1px solid var(--glass-border);
        background: var(--glass-bg);
        color: var(--text-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: var(--transition);
    }

    .icon-btn:hover {
        background: rgba(99, 102, 241, 0.1);
        transform: translateY(-2px);
    }

    /* Theme Toggle */
    .theme-toggle {
        position: relative;
        width: 60px;
        height: 30px;
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-radius: 20px;
        cursor: pointer;
        display: flex;
        align-items: center;
        padding: 2px;
        transition: var(--transition);
    }

    .theme-toggle-thumb {
        width: 24px;
        height: 24px;
        background: var(--accent-primary);
        border-radius: 50%;
        position: absolute;
        left: 4px;
        transition: var(--transition);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 12px;
    }

    [data-theme="light"] .theme-toggle-thumb {
        left: 32px;
        background: var(--accent-warning);
    }

    .user-profile {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 8px 16px 8px 8px;
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-radius: 14px;
        cursor: pointer;
        transition: var(--transition);
        color: var(--text-primary);
    }

    .user-profile:hover {
        background: rgba(99, 102, 241, 0.1);
    }

    .avatar {
        width: 32px;
        height: 32px;
        border-radius: 10px;
        background: linear-gradient(135deg, #f59e0b, #ef4444);
    }

    /* Bento Grid */
    .bento-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        grid-template-rows: repeat(4, auto);
        gap: 24px;
    }

    .bento-card {
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-radius: var(--card-radius);
        padding: 24px;
        backdrop-filter: blur(10px);
        transition: var(--transition);
        position: relative;
        overflow: hidden;
        box-shadow: 0 4px 6px rgba(0,0,0,0.02);
    }

    .bento-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--card-shadow);
        border-color: var(--accent-primary);
    }

    .card-span-1 { grid-column: span 1; }
    .card-span-2 { grid-column: span 2; }
    .card-span-3 { grid-column: span 3; }
    .card-span-4 { grid-column: span 4; }
    .card-row-2 { grid-row: span 2; }

    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 20px;
    }

    .card-title {
        font-size: 16px;
        font-weight: 600;
        color: var(--text-secondary);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .card-value {
        font-size: 36px;
        font-weight: 700;
        margin-bottom: 8px;
        color: var(--text-primary);
    }

    .card-trend {
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .trend-up { color: var(--accent-success); }
    .trend-down { color: var(--accent-danger); }

    /* AI Assistant Card */
    .ai-card {
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
        border: 1px solid rgba(139, 92, 246, 0.3);
    }

    .ai-input {
        width: 100%;
        padding: 16px 48px 16px 20px;
        background: rgba(0, 0, 0, 0.1);
        border: 1px solid var(--glass-border);
        border-radius: 16px;
        color: var(--text-primary);
        font-family: inherit;
        outline: none;
        transition: var(--transition);
    }

    [data-theme="light"] .ai-input {
        background: rgba(255, 255, 255, 0.5);
        color: #1e293b;
    }

    .ai-input:focus {
        border-color: var(--accent-primary);
        background: rgba(0, 0, 0, 0.2);
    }

    /* Tables */
    .table-header {
        color: var(--text-secondary);
        font-weight: 500;
        font-size: 12px;
        text-transform: uppercase;
        padding: 12px;
        text-align: left;
    }

    .table-row {
        border-bottom: 1px solid var(--glass-border);
        transition: var(--transition);
    }

    .table-row:last-child { border-bottom: none; }

    .table-row:hover {
        background: rgba(99, 102, 241, 0.05);
    }

    .table-cell {
        padding: 16px 12px;
        color: var(--text-primary);
    }

    .status-badge {
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }

    .status-active { background: rgba(16, 185, 129, 0.2); color: var(--accent-success); }
    .status-inactive { background: rgba(239, 68, 68, 0.2); color: var(--accent-danger); }

    /* Responsive */
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

<div class="dashboard-container">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-logo">M</div>
            <span>ManiReports</span>
        </div>

        <nav class="nav-menu">
            <div class="nav-label">Overview</div>
            <a href="#" class="nav-item active"><i class="fa-solid fa-grid-2"></i> <span>Dashboard</span></a>
            <a href="#" class="nav-item"><i class="fa-solid fa-chart-pie"></i> <span>Analytics</span></a>
            <a href="#" class="nav-item"><i class="fa-solid fa-users"></i> <span>Students</span></a>

            <div class="nav-label">Management</div>
            <a href="#" class="nav-item"><i class="fa-solid fa-book-open"></i> <span>Courses</span></a>
            <a href="#" class="nav-item"><i class="fa-solid fa-calendar-check"></i> <span>Schedules</span></a>
            <a href="#" class="nav-item"><i class="fa-solid fa-cloud"></i> <span>Cloud Jobs</span></a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Header -->
        <header class="header">
            <div class="welcome-text">
                <h1>Welcome back, Admin 👋</h1>
                <p>Here's what's happening with your learning platform today.</p>
            </div>
            <div class="header-actions">
                <!-- Theme Toggle -->
                <div class="theme-toggle" onclick="toggleTheme()" title="Toggle Light/Dark Mode">
                    <div class="theme-toggle-thumb"><i class="fa-solid fa-moon"></i></div>
                </div>
                
                <button class="icon-btn"><i class="fa-regular fa-bell"></i></button>
                <div class="user-profile">
                    <div class="avatar"></div>
                    <span style="font-size: 14px; font-weight: 500;">Admin User</span>
                </div>
            </div>
        </header>

        <!-- Bento Grid Layout -->
        <div class="bento-grid">
            
            <!-- KPI 1: Total Users -->
            <div class="bento-card card-span-1">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-users" style="color: var(--accent-primary);"></i> Total Users</div>
                    <div class="card-trend trend-up"><i class="fa-solid fa-arrow-trend-up"></i> 12%</div>
                </div>
                <div class="card-value">2,847</div>
                <div style="height: 60px;"><canvas id="chartUsers"></canvas></div>
            </div>

            <!-- KPI 2: Active Users (30 Days) -->
            <div class="bento-card card-span-1">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-user-check" style="color: var(--accent-success);"></i> Active (30d)</div>
                    <div class="card-trend trend-up"><i class="fa-solid fa-arrow-trend-up"></i> 5%</div>
                </div>
                <div class="card-value">1,942</div>
                <div style="height: 60px;"><canvas id="chartActive"></canvas></div>
            </div>

            <!-- KPI 3: Course Completions -->
            <div class="bento-card card-span-1">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-trophy" style="color: var(--accent-warning);"></i> Completions</div>
                    <div class="card-trend trend-up"><i class="fa-solid fa-arrow-trend-up"></i> 8%</div>
                </div>
                <div class="card-value">1,256</div>
                <div style="height: 60px;"><canvas id="chartCompletions"></canvas></div>
            </div>

            <!-- KPI 4: Inactive Users (Alert) -->
            <div class="bento-card card-span-1" style="border-color: rgba(239, 68, 68, 0.3);">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-user-times" style="color: var(--accent-danger);"></i> Inactive Users</div>
                    <div class="card-trend trend-down"><i class="fa-solid fa-arrow-trend-down"></i> 2%</div>
                </div>
                <div class="card-value" style="color: var(--accent-danger);">156</div>
                <p style="font-size: 12px; color: var(--text-secondary);">Users with no login > 30 days</p>
            </div>

            <!-- Main Chart: Activity Overview -->
            <div class="bento-card card-span-3 card-row-2">
                <div class="card-header">
                    <div class="card-title">Platform Activity Overview</div>
                    <select style="background: var(--glass-bg); border: 1px solid var(--glass-border); color: var(--text-primary); padding: 4px 8px; border-radius: 8px; outline: none;">
                        <option>Last 30 Days</option>
                        <option>Last 7 Days</option>
                    </select>
                </div>
                <div style="height: 300px; width: 100%;">
                    <canvas id="mainChart"></canvas>
                </div>
            </div>

            <!-- Inactive Users List -->
            <div class="bento-card card-span-1 card-row-2">
                <div class="card-header">
                    <div class="card-title">Inactive Users Alert</div>
                </div>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php
                    // Mock Inactive Users Data
                    $inactive_users = [
                        ['name' => 'John Doe', 'days' => 45],
                        ['name' => 'Jane Smith', 'days' => 38],
                        ['name' => 'Mike Johnson', 'days' => 32],
                        ['name' => 'Sarah Connor', 'days' => 60],
                        ['name' => 'Kyle Reese', 'days' => 31],
                    ];
                    foreach ($inactive_users as $user) {
                        echo '<div style="display: flex; align-items: center; gap: 12px; padding: 10px; background: rgba(239, 68, 68, 0.1); border-radius: 12px;">
                                <div style="width: 32px; height: 32px; background: var(--accent-danger); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white;">
                                    <i class="fa-solid fa-user-clock"></i>
                                </div>
                                <div>
                                    <div style="font-size: 13px; font-weight: 600; color: var(--text-primary);">' . $user['name'] . '</div>
                                    <div style="font-size: 11px; color: var(--accent-danger);">' . $user['days'] . ' days inactive</div>
                                </div>
                              </div>';
                    }
                    ?>
                </div>
                <button style="margin-top: 20px; width: 100%; padding: 12px; background: var(--glass-bg); border: 1px solid var(--glass-border); color: var(--text-primary); border-radius: 12px; cursor: pointer;">View All Inactive</button>
            </div>

            <!-- Top Accessed Courses Table -->
            <div class="bento-card card-span-4">
                <div class="card-header">
                    <div class="card-title">Top Accessed Courses (Last 30 Days)</div>
                    <button class="icon-btn" style="width: 32px; height: 32px;"><i class="fa-solid fa-ellipsis"></i></button>
                </div>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th class="table-header">Course Name</th>
                            <th class="table-header">Shortname</th>
                            <th class="table-header">Unique Users</th>
                            <th class="table-header">Total Accesses</th>
                            <th class="table-header">Trend</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Mock Course Usage Data
                        $courses = [
                            ['name' => 'Python for Data Science', 'short' => 'PY101', 'users' => 450, 'access' => 2300, 'trend' => '+15%'],
                            ['name' => 'Advanced Machine Learning', 'short' => 'ML201', 'users' => 320, 'access' => 1850, 'trend' => '+8%'],
                            ['name' => 'Web Development Bootcamp', 'short' => 'WEB300', 'users' => 280, 'access' => 1600, 'trend' => '+12%'],
                            ['name' => 'Cybersecurity Basics', 'short' => 'SEC101', 'users' => 210, 'access' => 1200, 'trend' => '-5%'],
                        ];
                        foreach ($courses as $course) {
                            echo '<tr class="table-row">
                                    <td class="table-cell" style="font-weight: 600;">' . $course['name'] . '</td>
                                    <td class="table-cell" style="color: var(--text-secondary);">' . $course['short'] . '</td>
                                    <td class="table-cell">' . $course['users'] . '</td>
                                    <td class="table-cell">' . $course['access'] . '</td>
                                    <td class="table-cell" style="color: ' . ($course['trend'][0] == '+' ? 'var(--accent-success)' : 'var(--accent-danger)') . ';">' . $course['trend'] . '</td>
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
// Theme Toggle Logic
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

document.addEventListener('DOMContentLoaded', function() {
    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { x: { display: false }, y: { display: false } },
        elements: { point: { radius: 0 }, line: { tension: 0.4 } }
    };

    // Chart: Users
    new Chart(document.getElementById('chartUsers'), {
        type: 'line',
        data: {
            labels: [1,2,3,4,5,6,7],
            datasets: [{
                data: [65, 59, 80, 81, 56, 55, 90],
                borderColor: '#6366f1',
                borderWidth: 2,
                fill: true,
                backgroundColor: (ctx) => {
                    const gradient = ctx.chart.ctx.createLinearGradient(0, 0, 0, 60);
                    gradient.addColorStop(0, 'rgba(99, 102, 241, 0.2)');
                    gradient.addColorStop(1, 'rgba(99, 102, 241, 0)');
                    return gradient;
                }
            }]
        },
        options: commonOptions
    });

    // Chart: Active Users
    new Chart(document.getElementById('chartActive'), {
        type: 'line',
        data: {
            labels: [1,2,3,4,5,6,7],
            datasets: [{
                data: [20, 40, 35, 50, 45, 60, 55],
                borderColor: '#10b981',
                borderWidth: 2,
                fill: true,
                backgroundColor: (ctx) => {
                    const gradient = ctx.chart.ctx.createLinearGradient(0, 0, 0, 60);
                    gradient.addColorStop(0, 'rgba(16, 185, 129, 0.2)');
                    gradient.addColorStop(1, 'rgba(16, 185, 129, 0)');
                    return gradient;
                }
            }]
        },
        options: commonOptions
    });

    // Chart: Completions
    new Chart(document.getElementById('chartCompletions'), {
        type: 'bar',
        data: {
            labels: [1,2,3,4,5,6,7],
            datasets: [{
                data: [40, 30, 60, 50, 70, 60, 80],
                backgroundColor: '#f59e0b',
                borderRadius: 2
            }]
        },
        options: commonOptions
    });

    // Main Chart
    new Chart(document.getElementById('mainChart'), {
        type: 'line',
        data: {
            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            datasets: [
                {
                    label: 'User Access',
                    data: [150, 230, 180, 320, 290, 340, 380],
                    borderColor: '#6366f1',
                    backgroundColor: (ctx) => {
                        const gradient = ctx.chart.ctx.createLinearGradient(0, 0, 0, 300);
                        gradient.addColorStop(0, 'rgba(99, 102, 241, 0.4)');
                        gradient.addColorStop(1, 'rgba(99, 102, 241, 0)');
                        return gradient;
                    },
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'Completions',
                    data: [50, 80, 60, 120, 90, 140, 160],
                    borderColor: '#10b981',
                    backgroundColor: 'transparent',
                    borderWidth: 3,
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
                y: {
                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                    ticks: { color: '#94a3b8', font: { family: 'Outfit' } }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#94a3b8', font: { family: 'Outfit' } }
                }
            }
        }
    });
});
</script>

<?php
echo $OUTPUT->footer();
?>
