<?php
/**
 * Dashboard Design V2 - Colorful & Engaging
 * 
 * Features:
 * - Vibrant colored KPI cards with icons
 * - Multiple chart types (bar, pie, line)
 * - Side-by-side metric comparisons
 * - Activity feed and alerts
 * - Inspired by modern accounting dashboards
 */

require_once(__DIR__ . '/../../../config.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/manireports/designs/dashboard_v2_colorful.php'));
$PAGE->set_title('Dashboard - Colorful Design V2');
$PAGE->set_heading('ManiReports - Colorful & Engaging Dashboard');

echo $OUTPUT->header();
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<style>
    /* Design V2 - Colorful & Engaging Styles */
    
    .dashboard-v2 {
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        padding: 20px;
        min-height: 100vh;
    }
    
    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    
    .dashboard-title {
        font-size: 28px;
        font-weight: 700;
        color: #1a1a1a;
        margin: 0;
    }
    
    .user-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
    }
    
    /* Colorful KPI Cards */
    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .kpi-card-v2 {
        border-radius: 12px;
        padding: 20px;
        color: white;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        transition: transform 0.3s, box-shadow 0.3s;
        position: relative;
        overflow: hidden;
    }
    
    .kpi-card-v2:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }
    
    .kpi-card-v2::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200px;
        height: 200px;
        border-radius: 50%;
        opacity: 0.1;
    }
    
    .kpi-card-v2.orange {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }
    
    .kpi-card-v2.blue {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    }
    
    .kpi-card-v2.green {
        background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
    }
    
    .kpi-card-v2.purple {
        background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
    }
    
    .kpi-card-v2.red {
        background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
    }
    
    .kpi-icon-v2 {
        font-size: 32px;
        margin-bottom: 10px;
    }
    
    .kpi-label-v2 {
        font-size: 13px;
        opacity: 0.9;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .kpi-value-v2 {
        font-size: 32px;
        font-weight: 700;
        margin-bottom: 8px;
    }
    
    .kpi-change-v2 {
        font-size: 12px;
        opacity: 0.85;
    }
    
    /* Charts Container */
    .charts-container {
        display: grid;
        grid-template-columns: 1.5fr 1fr;
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .chart-card-v2 {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    
    .chart-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    
    .chart-title-v2 {
        font-size: 18px;
        font-weight: 600;
        color: #1a1a1a;
    }
    
    .chart-controls {
        display: flex;
        gap: 8px;
    }
    
    .control-btn {
        padding: 6px 12px;
        border: 1px solid #e0e0e0;
        background: white;
        border-radius: 6px;
        font-size: 12px;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .control-btn.active {
        background: #667eea;
        color: white;
        border-color: #667eea;
    }
    
    .control-btn:hover {
        border-color: #667eea;
    }
    
    /* Metrics Comparison */
    .metrics-comparison {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .metric-box {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        border-left: 4px solid #667eea;
    }
    
    .metric-label {
        font-size: 12px;
        color: #6c757d;
        margin-bottom: 5px;
    }
    
    .metric-value {
        font-size: 24px;
        font-weight: 700;
        color: #1a1a1a;
    }
    
    /* Activity Feed */
    .activity-section {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    
    .activity-title {
        font-size: 18px;
        font-weight: 600;
        color: #1a1a1a;
        margin-bottom: 20px;
    }
    
    .activity-item {
        display: flex;
        gap: 15px;
        padding: 15px 0;
        border-bottom: 1px solid #e0e0e0;
    }
    
    .activity-item:last-child {
        border-bottom: none;
    }
    
    .activity-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        flex-shrink: 0;
    }
    
    .activity-icon.success { background: #d4edda; }
    .activity-icon.warning { background: #fff3cd; }
    .activity-icon.info { background: #d1ecf1; }
    
    .activity-content {
        flex: 1;
    }
    
    .activity-text {
        font-size: 14px;
        color: #1a1a1a;
        margin-bottom: 4px;
    }
    
    .activity-time {
        font-size: 12px;
        color: #6c757d;
    }
    
    /* Responsive */
    @media (max-width: 1024px) {
        .charts-container {
            grid-template-columns: 1fr;
        }
    }
    
    @media (max-width: 768px) {
        .dashboard-header {
            flex-direction: column;
            gap: 15px;
            text-align: center;
        }
        
        .kpi-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .metrics-comparison {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="dashboard-v2">
    <!-- Header -->
    <div class="dashboard-header">
        <h1 class="dashboard-title">Dashboard</h1>
        <div class="user-info">
            <div>
                <div style="font-size: 14px; color: #6c757d;">Welcome back</div>
                <div style="font-size: 16px; font-weight: 600; color: #1a1a1a;">Admin User</div>
            </div>
            <div class="user-avatar">AU</div>
        </div>
    </div>
    
    <!-- KPI Cards -->
    <div class="kpi-grid">
        <div class="kpi-card-v2 orange">
            <div class="kpi-icon-v2">👥</div>
            <div class="kpi-label-v2">Total Students</div>
            <div class="kpi-value-v2">1,247</div>
            <div class="kpi-change-v2">↑ 12.5% this month</div>
        </div>
        
        <div class="kpi-card-v2 blue">
            <div class="kpi-icon-v2">📚</div>
            <div class="kpi-label-v2">Active Courses</div>
            <div class="kpi-value-v2">48</div>
            <div class="kpi-change-v2">↑ 5.2% this month</div>
        </div>
        
        <div class="kpi-card-v2 green">
            <div class="kpi-icon-v2">✓</div>
            <div class="kpi-label-v2">Completions</div>
            <div class="kpi-value-v2">342</div>
            <div class="kpi-change-v2">↑ 8.3% this month</div>
        </div>
        
        <div class="kpi-card-v2 red">
            <div class="kpi-icon-v2">⚠</div>
            <div class="kpi-label-v2">At-Risk</div>
            <div class="kpi-value-v2">89</div>
            <div class="kpi-change-v2">↓ 3.2% this month</div>
        </div>
    </div>
    
    <!-- Charts Section -->
    <div class="charts-container">
        <div class="chart-card-v2">
            <div class="chart-header">
                <div class="chart-title-v2">Engagement Trends</div>
                <div class="chart-controls">
                    <button class="control-btn active">1M</button>
                    <button class="control-btn">3M</button>
                    <button class="control-btn">6M</button>
                </div>
            </div>
            <div style="height: 300px; display: flex; align-items: center; justify-content: center; color: #ccc;">
                <canvas id="engagementTrendChart"></canvas>
            </div>
        </div>
        
        <div class="chart-card-v2">
            <div class="chart-header">
                <div class="chart-title-v2">Course Status</div>
            </div>
            <div style="height: 300px; display: flex; align-items: center; justify-content: center; color: #ccc;">
                <canvas id="courseStatusChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Metrics Comparison -->
    <div class="chart-card-v2" style="margin-bottom: 30px;">
        <div class="chart-title-v2" style="margin-bottom: 20px;">Income vs Engagement</div>
        <div class="metrics-comparison">
            <div class="metric-box">
                <div class="metric-label">Total Engagement Hours</div>
                <div class="metric-value">2,847h</div>
            </div>
            <div class="metric-box" style="border-left-color: #f5576c;">
                <div class="metric-label">Average per Student</div>
                <div class="metric-value">2.3h</div>
            </div>
            <div class="metric-box" style="border-left-color: #43e97b;">
                <div class="metric-label">This Month</div>
                <div class="metric-value">847h</div>
            </div>
            <div class="metric-box" style="border-left-color: #fa709a;">
                <div class="metric-label">Last Month</div>
                <div class="metric-value">756h</div>
            </div>
        </div>
    </div>
    
    <!-- Activity Feed -->
    <div class="activity-section">
        <div class="activity-title">Recent Activity</div>
        
        <div class="activity-item">
            <div class="activity-icon success">✓</div>
            <div class="activity-content">
                <div class="activity-text"><strong>Course Completed</strong> - "Advanced Analytics" by John Smith</div>
                <div class="activity-time">2 hours ago</div>
            </div>
        </div>
        
        <div class="activity-item">
            <div class="activity-icon warning">⚠</div>
            <div class="activity-content">
                <div class="activity-text"><strong>At-Risk Alert</strong> - Sarah Johnson hasn't logged in for 5 days</div>
                <div class="activity-time">4 hours ago</div>
            </div>
        </div>
        
        <div class="activity-item">
            <div class="activity-icon info">ℹ</div>
            <div class="activity-content">
                <div class="activity-text"><strong>New Enrollment</strong> - 12 students enrolled in "Data Science 101"</div>
                <div class="activity-time">1 day ago</div>
            </div>
        </div>
        
        <div class="activity-item">
            <div class="activity-icon success">✓</div>
            <div class="activity-content">
                <div class="activity-text"><strong>Report Generated</strong> - Monthly engagement report is ready</div>
                <div class="activity-time">2 days ago</div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Engagement Trend Chart
    var engagementCtx = document.getElementById('engagementTrendChart');
    if (engagementCtx) {
        new Chart(engagementCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [
                    {
                        label: 'Active Users',
                        data: [320, 380, 420, 450, 480, 520],
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 5,
                        pointBackgroundColor: '#007bff',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    },
                    {
                        label: 'Engagement Hours',
                        data: [150, 180, 220, 250, 280, 320],
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 5,
                        pointBackgroundColor: '#28a745',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'top', labels: { font: { size: 12 }, padding: 15, usePointStyle: true } }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { font: { size: 11 } }, grid: { color: 'rgba(0, 0, 0, 0.05)' } },
                    x: { ticks: { font: { size: 11 } }, grid: { display: false } }
                }
            }
        });
    }

    // Course Status Chart
    var courseStatusCtx = document.getElementById('courseStatusChart');
    if (courseStatusCtx) {
        new Chart(courseStatusCtx, {
            type: 'pie',
            data: {
                labels: ['Active', 'Completed', 'Archived', 'Draft'],
                datasets: [{
                    data: [45, 30, 15, 10],
                    backgroundColor: ['#28a745', '#007bff', '#6c757d', '#ffc107'],
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { font: { size: 12 }, padding: 15, usePointStyle: true }
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
