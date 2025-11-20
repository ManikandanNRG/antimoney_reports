<?php
/**
 * Dashboard Design V1 - Modern Professional
 * 
 * Features:
 * - Clean, minimalist KPI cards with trend indicators
 * - Large primary chart (bar chart with dual metrics)
 * - Secondary visualizations (pie/donut charts)
 * - Professional blue/orange color scheme
 * - Optimized for admin and manager dashboards
 */

require_once(__DIR__ . '/../../../config.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/manireports/designs/dashboard_v1_modern.php'));
$PAGE->set_title('Dashboard - Modern Design V1');
$PAGE->set_heading('ManiReports - Modern Professional Dashboard');

echo $OUTPUT->header();
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<style>
    /* Design V1 - Modern Professional Styles */
    
    .dashboard-v1 {
        background: #f8f9fa;
        padding: 20px;
    }
    
    /* KPI Cards Section */
    .kpi-section {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .kpi-card {
        background: white;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        border-left: 4px solid #007bff;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    
    .kpi-card.success { border-left-color: #28a745; }
    .kpi-card.warning { border-left-color: #ffc107; }
    .kpi-card.danger { border-left-color: #dc3545; }
    
    .kpi-label {
        font-size: 12px;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .kpi-icon {
        width: 20px;
        height: 20px;
        background: #e9ecef;
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
    }
    
    .kpi-value {
        font-size: 28px;
        font-weight: 600;
        color: #212529;
        margin-bottom: 8px;
    }
    
    .kpi-trend {
        font-size: 12px;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    
    .kpi-trend.up { color: #28a745; }
    .kpi-trend.down { color: #dc3545; }
    
    .trend-arrow {
        font-size: 14px;
    }
    
    /* Charts Section */
    .charts-section {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .chart-card {
        background: white;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .chart-title {
        font-size: 16px;
        font-weight: 600;
        color: #212529;
        margin-bottom: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .chart-legend {
        display: flex;
        gap: 20px;
        margin-bottom: 15px;
        flex-wrap: wrap;
    }
    
    .legend-item {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
    }
    
    .legend-color {
        width: 12px;
        height: 12px;
        border-radius: 2px;
    }
    
    /* Time Period Selector */
    .time-selector {
        display: flex;
        gap: 8px;
        background: #f8f9fa;
        padding: 4px;
        border-radius: 6px;
        width: fit-content;
    }
    
    .time-btn {
        padding: 6px 12px;
        border: none;
        background: transparent;
        border-radius: 4px;
        font-size: 12px;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .time-btn.active {
        background: #007bff;
        color: white;
    }
    
    .time-btn:hover {
        background: #e9ecef;
    }
    
    /* Secondary Charts */
    .secondary-charts {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .chart-placeholder {
        background: white;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        min-height: 300px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #6c757d;
        font-size: 14px;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .charts-section {
            grid-template-columns: 1fr;
        }
        
        .kpi-section {
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        }
    }
</style>

<div class="dashboard-v1">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <h2 style="margin: 0; font-size: 24px; color: #212529;">Dashboard Overview</h2>
        <div class="time-selector">
            <button class="time-btn active">1D</button>
            <button class="time-btn">7D</button>
            <button class="time-btn">1M</button>
            <button class="time-btn">3M</button>
            <button class="time-btn">All</button>
        </div>
    </div>
    
    <!-- KPI Cards -->
    <div class="kpi-section">
        <div class="kpi-card success">
            <div class="kpi-label">
                <div class="kpi-icon">👥</div>
                Total Enrolled Students
            </div>
            <div class="kpi-value">1,247</div>
            <div class="kpi-trend up">
                <span class="trend-arrow">↑</span>
                <span>12.5% from last month</span>
            </div>
        </div>
        
        <div class="kpi-card">
            <div class="kpi-label">
                <div class="kpi-icon">✓</div>
                Courses Completed
            </div>
            <div class="kpi-value">342</div>
            <div class="kpi-trend up">
                <span class="trend-arrow">↑</span>
                <span>8.3% from last month</span>
            </div>
        </div>
        
        <div class="kpi-card warning">
            <div class="kpi-label">
                <div class="kpi-icon">⚠</div>
                At-Risk Students
            </div>
            <div class="kpi-value">89</div>
            <div class="kpi-trend down">
                <span class="trend-arrow">↓</span>
                <span>3.2% from last month</span>
            </div>
        </div>
        
        <div class="kpi-card">
            <div class="kpi-label">
                <div class="kpi-icon">⏱</div>
                Avg. Time Spent
            </div>
            <div class="kpi-value">4.2h</div>
            <div class="kpi-trend up">
                <span class="trend-arrow">↑</span>
                <span>2.1% from last month</span>
            </div>
        </div>
    </div>
    
    <!-- Primary Chart -->
    <div class="charts-section">
        <div class="chart-card">
            <div class="chart-title">
                <span>Course Completion Trends</span>
                <div class="time-selector" style="margin: 0;">
                    <button class="time-btn active">1M</button>
                    <button class="time-btn">3M</button>
                    <button class="time-btn">6M</button>
                </div>
            </div>
            <div class="chart-legend">
                <div class="legend-item">
                    <div class="legend-color" style="background: #007bff;"></div>
                    <span>Completed</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #ffc107;"></div>
                    <span>In Progress</span>
                </div>
            </div>
            <div class="chart-placeholder">
                <canvas id="completionChart" style="width: 100%; height: 250px;"></canvas>
            </div>
        </div>
        
        <div class="chart-card">
            <div class="chart-title">Course Distribution</div>
            <div class="chart-placeholder">
                <canvas id="distributionChart" style="width: 100%; height: 250px;"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Secondary Charts -->
    <div class="secondary-charts">
        <div class="chart-card">
            <div class="chart-title">Engagement by Department</div>
            <div class="chart-placeholder">
                <canvas id="engagementChart" style="width: 100%; height: 250px;"></canvas>
            </div>
        </div>
        
        <div class="chart-card">
            <div class="chart-title">Student Performance</div>
            <div class="chart-placeholder">
                <canvas id="performanceChart" style="width: 100%; height: 250px;"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Completion Trend Chart
    var completionCtx = document.getElementById('completionChart');
    if (completionCtx) {
        new Chart(completionCtx, {
            type: 'bar',
            data: {
                labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5', 'Week 6'],
                datasets: [
                    {
                        label: 'Completed',
                        data: [45, 52, 48, 61, 55, 68],
                        backgroundColor: '#007bff',
                        borderColor: '#0056b3',
                        borderWidth: 1,
                        borderRadius: 4
                    },
                    {
                        label: 'In Progress',
                        data: [28, 35, 42, 38, 45, 32],
                        backgroundColor: '#ffc107',
                        borderColor: '#e0a800',
                        borderWidth: 1,
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { font: { size: 12 }, padding: 15, usePointStyle: true }
                    }
                },
                scales: {
                    y: { beginAtZero: true, max: 100, ticks: { font: { size: 11 } }, grid: { color: 'rgba(0, 0, 0, 0.05)' } },
                    x: { ticks: { font: { size: 11 } }, grid: { display: false } }
                }
            }
        });
    }

    // Distribution Chart
    var distributionCtx = document.getElementById('distributionChart');
    if (distributionCtx) {
        new Chart(distributionCtx, {
            type: 'doughnut',
            data: {
                labels: ['Mathematics', 'Science', 'English', 'History', 'Arts'],
                datasets: [{
                    data: [25, 20, 18, 22, 15],
                    backgroundColor: ['#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1'],
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

    // Engagement Chart
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

    // Engagement by Department Chart
    var engagementCtx = document.getElementById('engagementChart');
    if (engagementCtx) {
        new Chart(engagementCtx, {
            type: 'bar',
            data: {
                labels: ['Engineering', 'Business', 'Science', 'Arts', 'Medicine'],
                datasets: [{
                    label: 'Engagement Score',
                    data: [85, 72, 90, 68, 88],
                    backgroundColor: '#007bff',
                    borderColor: '#0056b3',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: true, position: 'top', labels: { font: { size: 12 }, padding: 15 } }
                },
                scales: {
                    y: { beginAtZero: true, max: 100, ticks: { font: { size: 11 } }, grid: { color: 'rgba(0, 0, 0, 0.05)' } },
                    x: { ticks: { font: { size: 11 } }, grid: { display: false } }
                }
            }
        });
    }

    // Performance Chart
    var performanceCtx = document.getElementById('performanceChart');
    if (performanceCtx) {
        new Chart(performanceCtx, {
            type: 'bar',
            data: {
                labels: ['Excellent', 'Good', 'Average', 'Below Avg', 'Poor'],
                datasets: [{
                    label: 'Number of Students',
                    data: [120, 180, 150, 80, 40],
                    backgroundColor: ['#28a745', '#17a2b8', '#ffc107', '#fd7e14', '#dc3545'],
                    borderColor: ['#1e7e34', '#0c5460', '#e0a800', '#d35400', '#c82333'],
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: true,
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, ticks: { font: { size: 11 } }, grid: { color: 'rgba(0, 0, 0, 0.05)' } },
                    y: { ticks: { font: { size: 11 } }, grid: { display: false } }
                }
            }
        });
    }
});
</script>

<?php
echo $OUTPUT->footer();
?>
