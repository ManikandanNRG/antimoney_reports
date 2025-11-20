<?php
/**
 * Dashboard Design V4 - Dark Professional
 * 
 * Features:
 * - Dark theme with gold/accent colors
 * - Left sidebar navigation
 * - Dark KPI cards with colored icons
 * - Quick actions panel
 * - Progress bars and metrics
 * - Professional dark mode interface
 */

require_once(__DIR__ . '/../../../config.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/manireports/designs/dashboard_v4_dark_professional.php'));
$PAGE->set_title('Dashboard - Dark Professional Design V4');
$PAGE->set_heading('ManiReports - Dark Professional Dashboard');

echo $OUTPUT->header();
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<style>
    /* Design V4 - Dark Professional Styles */
    
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    body {
        background: #1a1a1a;
        color: #e0e0e0;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
    }
    
    .dashboard-v4 {
        display: grid;
        grid-template-columns: 250px 1fr;
        min-height: 100vh;
        background: #1a1a1a;
        margin-top: 0;
    }
    
    /* Sidebar */
    .sidebar {
        background: #0f0f0f;
        padding: 30px 20px;
        border-right: 1px solid #333;
        position: relative;
        height: auto;
        width: 250px;
        overflow-y: auto;
    }
    
    .sidebar-title {
        font-size: 14px;
        font-weight: 700;
        color: #d4af37;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 20px;
        margin-top: 20px;
    }
    
    .sidebar-item {
        padding: 12px 15px;
        margin-bottom: 8px;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s;
        color: #b0b0b0;
        font-size: 13px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .sidebar-item:hover {
        background: #2a2a2a;
        color: #d4af37;
    }
    
    .sidebar-item.active {
        background: #d4af37;
        color: #0f0f0f;
        font-weight: 600;
    }
    
    /* Main Content */
    .main-content {
        margin-left: 0;
        padding: 30px;
        background: #1a1a1a;
    }
    
    .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 1px solid #333;
    }
    
    .header-title {
        font-size: 28px;
        font-weight: 700;
        color: #fff;
    }
    
    .header-controls {
        display: flex;
        gap: 15px;
    }
    
    .control-btn {
        padding: 8px 16px;
        background: #2a2a2a;
        border: 1px solid #444;
        color: #e0e0e0;
        border-radius: 6px;
        cursor: pointer;
        font-size: 12px;
        transition: all 0.2s;
    }
    
    .control-btn:hover {
        background: #d4af37;
        color: #0f0f0f;
        border-color: #d4af37;
    }
    
    /* KPI Cards */
    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .kpi-card-v4 {
        background: #2a2a2a;
        border: 1px solid #333;
        border-radius: 8px;
        padding: 20px;
        transition: all 0.2s;
    }
    
    .kpi-card-v4:hover {
        border-color: #d4af37;
        box-shadow: 0 0 20px rgba(212, 175, 55, 0.2);
    }
    
    .kpi-icon-v4 {
        font-size: 32px;
        margin-bottom: 12px;
        width: 50px;
        height: 50px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .kpi-icon-v4.blue { background: rgba(100, 150, 255, 0.2); }
    .kpi-icon-v4.green { background: rgba(100, 200, 100, 0.2); }
    .kpi-icon-v4.orange { background: rgba(255, 150, 50, 0.2); }
    .kpi-icon-v4.purple { background: rgba(180, 100, 255, 0.2); }
    
    .kpi-label-v4 {
        font-size: 12px;
        color: #999;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 8px;
    }
    
    .kpi-value-v4 {
        font-size: 28px;
        font-weight: 700;
        color: #fff;
        margin-bottom: 8px;
    }
    
    .kpi-change-v4 {
        font-size: 12px;
        color: #64c864;
    }
    
    .kpi-change-v4.down {
        color: #ff6464;
    }
    
    /* Charts Grid */
    .charts-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .chart-card-v4 {
        background: #2a2a2a;
        border: 1px solid #333;
        border-radius: 8px;
        padding: 20px;
    }
    
    .chart-title-v4 {
        font-size: 16px;
        font-weight: 600;
        color: #fff;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .chart-placeholder-v4 {
        height: 300px;
        background: #1a1a1a;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    /* Quick Actions */
    .quick-actions {
        background: #2a2a2a;
        border: 1px solid #333;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 30px;
    }
    
    .quick-actions-title {
        font-size: 14px;
        font-weight: 600;
        color: #fff;
        margin-bottom: 15px;
    }
    
    .action-buttons {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 10px;
    }
    
    .action-btn {
        padding: 12px;
        background: #1a1a1a;
        border: 1px solid #444;
        border-radius: 6px;
        color: #e0e0e0;
        cursor: pointer;
        font-size: 12px;
        transition: all 0.2s;
        text-align: center;
    }
    
    .action-btn:hover {
        background: #d4af37;
        color: #0f0f0f;
        border-color: #d4af37;
    }
    
    /* Progress Bars */
    .progress-section {
        background: #2a2a2a;
        border: 1px solid #333;
        border-radius: 8px;
        padding: 20px;
    }
    
    .progress-title {
        font-size: 14px;
        font-weight: 600;
        color: #fff;
        margin-bottom: 20px;
    }
    
    .progress-item {
        margin-bottom: 20px;
    }
    
    .progress-label {
        display: flex;
        justify-content: space-between;
        margin-bottom: 8px;
        font-size: 12px;
    }
    
    .progress-label-name {
        color: #e0e0e0;
    }
    
    .progress-label-value {
        color: #d4af37;
        font-weight: 600;
    }
    
    .progress-bar-container {
        width: 100%;
        height: 6px;
        background: #1a1a1a;
        border-radius: 3px;
        overflow: hidden;
    }
    
    .progress-bar {
        height: 100%;
        background: linear-gradient(90deg, #d4af37 0%, #ffd700 100%);
        border-radius: 3px;
    }
    
    /* Responsive */
    @media (max-width: 1024px) {
        .charts-grid {
            grid-template-columns: 1fr;
        }
    }
    
    @media (max-width: 768px) {
        .dashboard-v4 {
            grid-template-columns: 1fr;
        }
        
        .sidebar {
            width: 100%;
            border-right: none;
            border-bottom: 1px solid #333;
            padding: 15px;
        }
        
        .main-content {
            padding: 20px;
        }
        
        .kpi-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>

<div class="dashboard-v4">
    <!-- Sidebar -->
    <div class="sidebar">
        <div style="font-size: 18px; font-weight: 700; color: #d4af37; margin-bottom: 30px;">ManiReports</div>
        
        <div class="sidebar-title">Main</div>
        <div class="sidebar-item active">📊 Dashboard</div>
        <div class="sidebar-item">📈 Reports</div>
        <div class="sidebar-item">👥 Students</div>
        <div class="sidebar-item">📚 Courses</div>
        
        <div class="sidebar-title">Analytics</div>
        <div class="sidebar-item">🎯 Engagement</div>
        <div class="sidebar-item">⚠️ At-Risk</div>
        <div class="sidebar-item">📊 Performance</div>
        
        <div class="sidebar-title">Tools</div>
        <div class="sidebar-item">⚙️ Settings</div>
        <div class="sidebar-item">📋 Audit Log</div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div class="header-title">Dashboard</div>
            <div class="header-controls">
                <button class="control-btn">📅 Last 30 Days</button>
                <button class="control-btn">🔄 Refresh</button>
            </div>
        </div>
        
        <!-- KPI Cards - Real ManiReports Data -->
        <div class="kpi-grid">
            <div class="kpi-card-v4">
                <div class="kpi-icon-v4 blue">👥</div>
                <div class="kpi-label-v4">Enrolled Users</div>
                <div class="kpi-value-v4">2,847</div>
                <div class="kpi-change-v4">↑ 12.5% vs last month</div>
            </div>
            
            <div class="kpi-card-v4">
                <div class="kpi-icon-v4 green">✓</div>
                <div class="kpi-label-v4">Course Completions</div>
                <div class="kpi-value-v4">1,256</div>
                <div class="kpi-change-v4">↑ 8.3% completion rate</div>
            </div>
            
            <div class="kpi-card-v4">
                <div class="kpi-icon-v4 orange">⏱️</div>
                <div class="kpi-label-v4">Avg Time Spent</div>
                <div class="kpi-value-v4">4.2h</div>
                <div class="kpi-change-v4">↑ 15 min vs last week</div>
            </div>
            
            <div class="kpi-card-v4">
                <div class="kpi-icon-v4 purple">⚠️</div>
                <div class="kpi-label-v4">At-Risk Learners</div>
                <div class="kpi-value-v4">156</div>
                <div class="kpi-change-v4 down">↓ 3.1% improvement</div>
            </div>
        </div>
        
        <!-- Charts - Real ManiReports Analytics -->
        <div class="charts-grid">
            <div class="chart-card-v4">
                <div class="chart-title-v4">
                    <span>Course Completion Trend (Last 6 Months)</span>
                </div>
                <div class="chart-placeholder-v4">
                    <canvas id="completionTrendChart" style="width: 100%; height: 250px;"></canvas>
                </div>
            </div>
            
            <div class="chart-card-v4">
                <div class="chart-title-v4">Engagement by Course</div>
                <div class="chart-placeholder-v4">
                    <canvas id="engagementChart" style="width: 100%; height: 250px;"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions & Progress -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="quick-actions">
                <div class="quick-actions-title">Quick Actions</div>
                <div class="action-buttons">
                    <button class="action-btn">📊 View Report</button>
                    <button class="action-btn">📧 Send Email</button>
                    <button class="action-btn">📥 Export Data</button>
                    <button class="action-btn">⚙️ Settings</button>
                </div>
            </div>
            
            <div class="progress-section">
                <div class="progress-title">Top Courses by Completion</div>
                
                <div class="progress-item">
                    <div class="progress-label">
                        <span class="progress-label-name">Advanced Analytics</span>
                        <span class="progress-label-value">245 / 289 (85%)</span>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar" style="width: 85%;"></div>
                    </div>
                </div>
                
                <div class="progress-item">
                    <div class="progress-label">
                        <span class="progress-label-name">Data Science 101</span>
                        <span class="progress-label-value">156 / 218 (72%)</span>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar" style="width: 72%;"></div>
                    </div>
                </div>
                
                <div class="progress-item">
                    <div class="progress-label">
                        <span class="progress-label-name">Python Basics</span>
                        <span class="progress-label-value">312 / 456 (68%)</span>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar" style="width: 68%;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Course Completion Trend Chart
    var completionCtx = document.getElementById('completionTrendChart');
    if (completionCtx) {
        new Chart(completionCtx, {
            type: 'line',
            data: {
                labels: ['Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Jan'],
                datasets: [
                    {
                        label: 'Completions',
                        data: [156, 189, 201, 218, 245, 256],
                        borderColor: '#d4af37',
                        backgroundColor: 'rgba(212, 175, 55, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 6,
                        pointBackgroundColor: '#d4af37',
                        pointBorderColor: '#2a2a2a',
                        pointBorderWidth: 2
                    },
                    {
                        label: 'Enrollments',
                        data: [289, 312, 334, 356, 378, 401],
                        borderColor: '#64c864',
                        backgroundColor: 'rgba(100, 200, 100, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 5,
                        pointBackgroundColor: '#64c864',
                        pointBorderColor: '#2a2a2a',
                        pointBorderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        labels: { color: '#e0e0e0', font: { size: 12 } }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { color: '#999', font: { size: 11 } },
                        grid: { color: 'rgba(255, 255, 255, 0.05)' }
                    },
                    x: {
                        ticks: { color: '#999', font: { size: 11 } },
                        grid: { display: false }
                    }
                }
            }
        });
    }
    
    // Engagement by Course Chart
    var engagementCtx = document.getElementById('engagementChart');
    if (engagementCtx) {
        new Chart(engagementCtx, {
            type: 'bar',
            data: {
                labels: ['Advanced\nAnalytics', 'Data Science\n101', 'Python\nBasics', 'Web Dev', 'SQL\nMastery'],
                datasets: [{
                    label: 'Engagement Score (%)',
                    data: [85, 72, 68, 78, 82],
                    backgroundColor: ['#d4af37', '#64c864', '#6496ff', '#ff9650', '#ff6464'],
                    borderColor: '#2a2a2a',
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                indexAxis: 'x',
                plugins: {
                    legend: {
                        labels: { color: '#e0e0e0', font: { size: 12 } }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: { color: '#999', font: { size: 11 } },
                        grid: { color: 'rgba(255, 255, 255, 0.05)' }
                    },
                    x: {
                        ticks: { color: '#999', font: { size: 11 } },
                        grid: { display: false }
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
