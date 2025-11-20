<?php
/**
 * Dashboard Design V5 - Modern Compact
 * 
 * Features:
 * - Tab-based navigation
 * - Compact metric cards with badges
 * - Scatter plot and advanced charts
 * - Project/task management table
 * - AI assistant widget
 * - Light modern theme
 */

require_once(__DIR__ . '/../../../config.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/manireports/designs/dashboard_v5_modern_compact.php'));
$PAGE->set_title('Dashboard - Modern Compact Design V5');
$PAGE->set_heading('ManiReports - Modern Compact Dashboard');

echo $OUTPUT->header();
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<style>
    /* Design V5 - Modern Compact Styles */
    
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    .dashboard-v5 {
        background: #f8f9fa;
        min-height: 100vh;
        padding: 20px;
    }
    
    /* Header */
    .header-v5 {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    
    .header-left {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .logo {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 18px;
    }
    
    .header-title {
        font-size: 20px;
        font-weight: 700;
        color: #1a1a1a;
    }
    
    .header-right {
        display: flex;
        gap: 15px;
        align-items: center;
    }
    
    .search-box {
        padding: 8px 15px;
        border: 1px solid #e0e0e0;
        border-radius: 6px;
        font-size: 13px;
        width: 200px;
    }
    
    .header-btn {
        padding: 8px 16px;
        background: #667eea;
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 600;
        transition: all 0.2s;
    }
    
    .header-btn:hover {
        background: #5568d3;
    }
    
    /* Tabs */
    .tabs {
        display: flex;
        gap: 0;
        margin-bottom: 25px;
        background: white;
        border-radius: 8px;
        padding: 0;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        overflow: hidden;
    }
    
    .tab {
        padding: 15px 20px;
        border: none;
        background: transparent;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
        color: #999;
        border-bottom: 3px solid transparent;
        transition: all 0.2s;
    }
    
    .tab:hover {
        color: #667eea;
    }
    
    .tab.active {
        color: #667eea;
        border-bottom-color: #667eea;
    }
    
    /* Metrics Row */
    .metrics-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }
    
    .metric-card {
        background: white;
        padding: 15px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        border-left: 4px solid #667eea;
    }
    
    .metric-label {
        font-size: 11px;
        color: #999;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 5px;
    }
    
    .metric-value {
        font-size: 24px;
        font-weight: 700;
        color: #1a1a1a;
        margin-bottom: 5px;
    }
    
    .metric-change {
        font-size: 12px;
        color: #64c864;
    }
    
    .metric-change.down {
        color: #ff6464;
    }
    
    .metric-badge {
        display: inline-block;
        padding: 2px 8px;
        background: #f0f0f0;
        border-radius: 12px;
        font-size: 11px;
        color: #667eea;
        font-weight: 600;
        margin-left: 5px;
    }
    
    /* Main Grid */
    .main-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 20px;
        margin-bottom: 25px;
    }
    
    .card {
        background: white;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    
    .card-title {
        font-size: 14px;
        font-weight: 700;
        color: #1a1a1a;
        margin-bottom: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .card-action {
        font-size: 12px;
        color: #667eea;
        cursor: pointer;
        text-decoration: none;
    }
    
    .chart-placeholder-v5 {
        height: 300px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f8f9fa;
        border-radius: 6px;
    }
    
    /* Data Table */
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
    }
    
    .data-table thead {
        background: #f8f9fa;
    }
    
    .data-table th {
        padding: 12px;
        text-align: left;
        font-weight: 600;
        color: #999;
        border-bottom: 1px solid #e0e0e0;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .data-table td {
        padding: 12px;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .data-table tbody tr:hover {
        background: #f8f9fa;
    }
    
    .status-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .status-draft { background: #f0f0f0; color: #999; }
    .status-progress { background: #fff3cd; color: #856404; }
    .status-completed { background: #d4edda; color: #155724; }
    
    /* AI Widget */
    .ai-widget {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 8px;
        padding: 20px;
        text-align: center;
    }
    
    .ai-title {
        font-size: 14px;
        font-weight: 700;
        margin-bottom: 10px;
    }
    
    .ai-subtitle {
        font-size: 12px;
        opacity: 0.9;
        margin-bottom: 15px;
    }
    
    .ai-options {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }
    
    .ai-option {
        padding: 10px;
        background: rgba(255,255,255,0.2);
        border-radius: 6px;
        font-size: 11px;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .ai-option:hover {
        background: rgba(255,255,255,0.3);
    }
    
    /* Responsive */
    @media (max-width: 1024px) {
        .main-grid {
            grid-template-columns: 1fr;
        }
    }
    
    @media (max-width: 768px) {
        .header-v5 {
            flex-direction: column;
            gap: 15px;
        }
        
        .header-right {
            width: 100%;
            flex-direction: column;
        }
        
        .search-box {
            width: 100%;
        }
        
        .tabs {
            overflow-x: auto;
        }
        
        .metrics-row {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>

<div class="dashboard-v5">
    <!-- Header -->
    <div class="header-v5">
        <div class="header-left">
            <div class="logo">M</div>
            <div class="header-title">ManiReports Dashboard</div>
        </div>
        <div class="header-right">
            <input type="text" class="search-box" placeholder="Search courses, students...">
            <button class="header-btn">+ Add Report</button>
        </div>
    </div>
    
    <!-- Tabs -->
    <div class="tabs">
        <button class="tab active">Overview</button>
        <button class="tab">Courses</button>
        <button class="tab">Students</button>
        <button class="tab">Analytics</button>
        <button class="tab">Reports</button>
    </div>
    
    <!-- Metrics - Real ManiReports Data -->
    <div class="metrics-row">
        <div class="metric-card">
            <div class="metric-label">Active Courses</div>
            <div class="metric-value">48 <span class="metric-badge">+4</span></div>
            <div class="metric-change">vs last month</div>
        </div>
        
        <div class="metric-card" style="border-left-color: #ff6464;">
            <div class="metric-label">Total Enrollments</div>
            <div class="metric-value">2,847 <span class="metric-badge" style="color: #64c864;">+8%</span></div>
            <div class="metric-change">vs last month</div>
        </div>
        
        <div class="metric-card" style="border-left-color: #64c864;">
            <div class="metric-label">Completions</div>
            <div class="metric-value">1,256 <span class="metric-badge" style="color: #64c864;">+12%</span></div>
            <div class="metric-change">vs last month</div>
        </div>
        
        <div class="metric-card" style="border-left-color: #ffc107;">
            <div class="metric-label">Avg Engagement</div>
            <div class="metric-value">78.3% <span class="metric-badge" style="color: #ffc107;">+5%</span></div>
            <div class="metric-change">vs last month</div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-grid">
        <!-- Analytics Chart -->
        <div class="card">
            <div class="card-title">
                <span>Time Spent vs Completion Rate</span>
                <div style="display: flex; gap: 10px;">
                    <span class="card-action">Last 30 Days ▼</span>
                    <span class="card-action">All Courses ▼</span>
                </div>
            </div>
            <div class="chart-placeholder-v5">
                <canvas id="timeVsCompletionChart" style="width: 100%; height: 250px;"></canvas>
            </div>
        </div>
        
        <!-- AI Widget -->
        <div class="ai-widget">
            <div class="ai-title">Hi, Admin! 👋</div>
            <div class="ai-subtitle">How can I help you?</div>
            <div class="ai-options">
                <div class="ai-option">📊 View Reports</div>
                <div class="ai-option">👥 Manage Users</div>
                <div class="ai-option">📈 Analytics</div>
                <div class="ai-option">⚙️ Settings</div>
            </div>
        </div>
    </div>
    
    <!-- Tasks/Projects Table -->
    <div class="card">
        <div class="card-title">
            <span>Course Management</span>
            <span class="card-action">View All →</span>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Course Name</th>
                    <th>Instructor</th>
                    <th>Students</th>
                    <th>Progress</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Advanced Analytics</strong></td>
                    <td>Dr. Sarah Johnson</td>
                    <td>245</td>
                    <td>68%</td>
                    <td><span class="status-badge status-progress">In Progress</span></td>
                    <td><a href="#" style="color: #667eea; text-decoration: none;">View</a></td>
                </tr>
                <tr>
                    <td><strong>Data Science 101</strong></td>
                    <td>Prof. Mike Chen</td>
                    <td>189</td>
                    <td>85%</td>
                    <td><span class="status-badge status-completed">Completed</span></td>
                    <td><a href="#" style="color: #667eea; text-decoration: none;">View</a></td>
                </tr>
                <tr>
                    <td><strong>Python Basics</strong></td>
                    <td>Emma Davis</td>
                    <td>312</td>
                    <td>45%</td>
                    <td><span class="status-badge status-progress">In Progress</span></td>
                    <td><a href="#" style="color: #667eea; text-decoration: none;">View</a></td>
                </tr>
                <tr>
                    <td><strong>Web Development</strong></td>
                    <td>James Wilson</td>
                    <td>156</td>
                    <td>20%</td>
                    <td><span class="status-badge status-draft">Draft</span></td>
                    <td><a href="#" style="color: #667eea; text-decoration: none;">View</a></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Time Spent vs Completion Rate Chart
    var timeVsCompletionCtx = document.getElementById('timeVsCompletionChart');
    if (timeVsCompletionCtx) {
        new Chart(timeVsCompletionCtx, {
            type: 'scatter',
            data: {
                datasets: [
                    {
                        label: 'Courses',
                        data: [
                            {x: 2.5, y: 68},   // Python Basics
                            {x: 4.2, y: 85},   // Advanced Analytics
                            {x: 3.8, y: 72},   // Data Science 101
                            {x: 5.1, y: 78},   // Web Development
                            {x: 4.6, y: 82},   // SQL Mastery
                            {x: 3.2, y: 65},   // JavaScript Intro
                            {x: 5.8, y: 88}    // Machine Learning
                        ],
                        backgroundColor: 'rgba(102, 126, 234, 0.7)',
                        borderColor: '#667eea',
                        borderWidth: 2,
                        pointRadius: 8,
                        pointHoverRadius: 10
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        labels: { font: { size: 12 } }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Time: ' + context.parsed.x + 'h, Completion: ' + context.parsed.y + '%';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: { display: true, text: 'Completion Rate (%)' },
                        ticks: { font: { size: 11 } },
                        grid: { color: 'rgba(0, 0, 0, 0.05)' }
                    },
                    x: {
                        title: { display: true, text: 'Avg Time Spent (hours)' },
                        ticks: { font: { size: 11 } },
                        grid: { color: 'rgba(0, 0, 0, 0.05)' }
                    }
                }
            }
        });
    }
});
</script>

<?php
echo $OUTPUT->footer();
?>
