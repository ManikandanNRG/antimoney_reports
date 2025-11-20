<?php
/**
 * Dashboard Design V3 - Data-Rich & Compact
 * 
 * Features:
 * - Comprehensive data tables with status badges
 * - Multiple visualization types
 * - Compact layout for information density
 * - Quick action buttons
 * - Optimized for power users and managers
 */

require_once(__DIR__ . '/../../../config.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/manireports/designs/dashboard_v3_datarich.php'));
$PAGE->set_title('Dashboard - Data-Rich Design V3');
$PAGE->set_heading('ManiReports - Data-Rich & Compact Dashboard');

echo $OUTPUT->header();
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<style>
    /* Design V3 - Data-Rich & Compact Styles */
    
    .dashboard-v3 {
        background: #f5f6f8;
        padding: 20px;
    }
    
    .dashboard-v3-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        gap: 20px;
    }
    
    .header-title {
        font-size: 26px;
        font-weight: 700;
        color: #1a1a1a;
        margin: 0;
    }
    
    .header-controls {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    
    .filter-btn, .export-btn, .refresh-btn {
        padding: 8px 16px;
        border: 1px solid #d0d0d0;
        background: white;
        border-radius: 6px;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    
    .filter-btn:hover, .export-btn:hover, .refresh-btn:hover {
        background: #f0f0f0;
        border-color: #999;
    }
    
    /* KPI Summary Row */
    .kpi-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }
    
    .kpi-mini {
        background: white;
        padding: 15px;
        border-radius: 8px;
        border: 1px solid #e0e0e0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .kpi-mini-content {
        flex: 1;
    }
    
    .kpi-mini-label {
        font-size: 11px;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 4px;
    }
    
    .kpi-mini-value {
        font-size: 22px;
        font-weight: 700;
        color: #1a1a1a;
    }
    
    .kpi-mini-icon {
        font-size: 28px;
        opacity: 0.3;
    }
    
    /* Main Content Grid */
    .content-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 25px;
    }
    
    .card {
        background: white;
        border-radius: 8px;
        border: 1px solid #e0e0e0;
        overflow: hidden;
    }
    
    .card-header {
        padding: 15px 20px;
        border-bottom: 1px solid #e0e0e0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #fafbfc;
    }
    
    .card-title {
        font-size: 14px;
        font-weight: 600;
        color: #1a1a1a;
    }
    
    .card-actions {
        display: flex;
        gap: 8px;
    }
    
    .card-action-btn {
        padding: 4px 8px;
        border: none;
        background: transparent;
        color: #667eea;
        cursor: pointer;
        font-size: 12px;
        border-radius: 4px;
        transition: all 0.2s;
    }
    
    .card-action-btn:hover {
        background: #f0f0f0;
    }
    
    .card-body {
        padding: 20px;
    }
    
    /* Data Table */
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }
    
    .data-table thead {
        background: #f8f9fa;
    }
    
    .data-table th {
        padding: 12px;
        text-align: left;
        font-weight: 600;
        color: #6c757d;
        border-bottom: 1px solid #e0e0e0;
        font-size: 12px;
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
    
    /* Status Badges */
    .badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .badge-success {
        background: #d4edda;
        color: #155724;
    }
    
    .badge-warning {
        background: #fff3cd;
        color: #856404;
    }
    
    .badge-danger {
        background: #f8d7da;
        color: #721c24;
    }
    
    .badge-info {
        background: #d1ecf1;
        color: #0c5460;
    }
    
    /* Progress Bar */
    .progress-bar-container {
        width: 100%;
        height: 6px;
        background: #e0e0e0;
        border-radius: 3px;
        overflow: hidden;
    }
    
    .progress-bar {
        height: 100%;
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        border-radius: 3px;
    }
    
    /* Chart Placeholder */
    .chart-placeholder-v3 {
        height: 250px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ccc;
        background: #fafbfc;
    }
    
    /* Full Width Section */
    .full-width {
        grid-column: 1 / -1;
    }
    
    /* Responsive */
    @media (max-width: 1024px) {
        .content-grid {
            grid-template-columns: 1fr;
        }
        
        .full-width {
            grid-column: 1;
        }
    }
    
    @media (max-width: 768px) {
        .dashboard-v3-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .header-controls {
            width: 100%;
            flex-wrap: wrap;
        }
        
        .kpi-summary {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .data-table {
            font-size: 12px;
        }
        
        .data-table th, .data-table td {
            padding: 8px;
        }
    }
</style>

<div class="dashboard-v3">
    <!-- Header -->
    <div class="dashboard-v3-header">
        <h1 class="header-title">Analytics Dashboard</h1>
        <div class="header-controls">
            <button class="filter-btn">🔍 Filter</button>
            <button class="export-btn">⬇ Export</button>
            <button class="refresh-btn">🔄 Refresh</button>
        </div>
    </div>
    
    <!-- KPI Summary -->
    <div class="kpi-summary">
        <div class="kpi-mini">
            <div class="kpi-mini-content">
                <div class="kpi-mini-label">Total Students</div>
                <div class="kpi-mini-value">1,247</div>
            </div>
            <div class="kpi-mini-icon">👥</div>
        </div>
        
        <div class="kpi-mini">
            <div class="kpi-mini-content">
                <div class="kpi-mini-label">Active Courses</div>
                <div class="kpi-mini-value">48</div>
            </div>
            <div class="kpi-mini-icon">📚</div>
        </div>
        
        <div class="kpi-mini">
            <div class="kpi-mini-content">
                <div class="kpi-mini-label">Completion Rate</div>
                <div class="kpi-mini-value">68.5%</div>
            </div>
            <div class="kpi-mini-icon">✓</div>
        </div>
        
        <div class="kpi-mini">
            <div class="kpi-mini-content">
                <div class="kpi-mini-label">Avg. Engagement</div>
                <div class="kpi-mini-value">4.2h</div>
            </div>
            <div class="kpi-mini-icon">⏱</div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="content-grid">
        <!-- Course Overview Table -->
        <div class="card full-width">
            <div class="card-header">
                <div class="card-title">Course Overview</div>
                <div class="card-actions">
                    <button class="card-action-btn">View All</button>
                </div>
            </div>
            <div class="card-body">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Course Name</th>
                            <th>Enrolled</th>
                            <th>Completed</th>
                            <th>Progress</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Advanced Analytics</strong></td>
                            <td>245</td>
                            <td>168</td>
                            <td>
                                <div class="progress-bar-container">
                                    <div class="progress-bar" style="width: 68.6%;"></div>
                                </div>
                            </td>
                            <td><span class="badge badge-success">Active</span></td>
                            <td><a href="#" style="color: #667eea; text-decoration: none;">View</a></td>
                        </tr>
                        <tr>
                            <td><strong>Data Science 101</strong></td>
                            <td>189</td>
                            <td>142</td>
                            <td>
                                <div class="progress-bar-container">
                                    <div class="progress-bar" style="width: 75.1%;"></div>
                                </div>
                            </td>
                            <td><span class="badge badge-success">Active</span></td>
                            <td><a href="#" style="color: #667eea; text-decoration: none;">View</a></td>
                        </tr>
                        <tr>
                            <td><strong>Python Basics</strong></td>
                            <td>312</td>
                            <td>198</td>
                            <td>
                                <div class="progress-bar-container">
                                    <div class="progress-bar" style="width: 63.5%;"></div>
                                </div>
                            </td>
                            <td><span class="badge badge-warning">In Progress</span></td>
                            <td><a href="#" style="color: #667eea; text-decoration: none;">View</a></td>
                        </tr>
                        <tr>
                            <td><strong>Web Development</strong></td>
                            <td>156</td>
                            <td>89</td>
                            <td>
                                <div class="progress-bar-container">
                                    <div class="progress-bar" style="width: 57.1%;"></div>
                                </div>
                            </td>
                            <td><span class="badge badge-info">Upcoming</span></td>
                            <td><a href="#" style="color: #667eea; text-decoration: none;">View</a></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Engagement Chart -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">Engagement Trend</div>
            </div>
            <div class="card-body">
                <div class="chart-placeholder-v3">
                    <canvas id="engagementTrendChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Performance Distribution -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">Performance Distribution</div>
            </div>
            <div class="card-body">
                <div class="chart-placeholder-v3">
                    <canvas id="performanceChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- At-Risk Students -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">At-Risk Students</div>
                <div class="card-actions">
                    <button class="card-action-btn">View All</button>
                </div>
            </div>
            <div class="card-body">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Course</th>
                            <th>Risk Level</th>
                            <th>Last Active</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Sarah Johnson</strong></td>
                            <td>Advanced Analytics</td>
                            <td><span class="badge badge-danger">High</span></td>
                            <td>5 days ago</td>
                        </tr>
                        <tr>
                            <td><strong>Mike Chen</strong></td>
                            <td>Data Science 101</td>
                            <td><span class="badge badge-warning">Medium</span></td>
                            <td>2 days ago</td>
                        </tr>
                        <tr>
                            <td><strong>Emma Davis</strong></td>
                            <td>Python Basics</td>
                            <td><span class="badge badge-warning">Medium</span></td>
                            <td>3 days ago</td>
                        </tr>
                        <tr>
                            <td><strong>James Wilson</strong></td>
                            <td>Web Development</td>
                            <td><span class="badge badge-danger">High</span></td>
                            <td>1 week ago</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Recent Submissions -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">Recent Submissions</div>
                <div class="card-actions">
                    <button class="card-action-btn">View All</button>
                </div>
            </div>
            <div class="card-body">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Assignment</th>
                            <th>Grade</th>
                            <th>Submitted</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>John Smith</strong></td>
                            <td>Quiz 5</td>
                            <td><strong>92%</strong></td>
                            <td>2 hours ago</td>
                        </tr>
                        <tr>
                            <td><strong>Lisa Brown</strong></td>
                            <td>Project 2</td>
                            <td><strong>88%</strong></td>
                            <td>4 hours ago</td>
                        </tr>
                        <tr>
                            <td><strong>Tom Garcia</strong></td>
                            <td>Quiz 5</td>
                            <td><strong>85%</strong></td>
                            <td>6 hours ago</td>
                        </tr>
                        <tr>
                            <td><strong>Anna Lee</strong></td>
                            <td>Assignment 3</td>
                            <td><strong>95%</strong></td>
                            <td>1 day ago</td>
                        </tr>
                    </tbody>
                </table>
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
