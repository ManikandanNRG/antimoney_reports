/**
 * Dashboard Charts Module
 * 
 * Renders charts for dashboard designs using Chart.js
 * 
 * @module     local_manireports/dashboard_charts
 * @copyright  2024 ManiReports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {
    'use strict';

    /**
     * Load Chart.js library
     */
    var loadChartJs = function() {
        return new Promise(function(resolve, reject) {
            if (typeof Chart !== 'undefined') {
                resolve();
                return;
            }

            var script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
            script.onload = function() {
                resolve();
            };
            script.onerror = function() {
                reject(new Error('Failed to load Chart.js'));
            };
            document.head.appendChild(script);
        });
    };

    /**
     * Render bar chart for course completion trends
     */
    var renderCompletionTrendChart = function() {
        var canvas = document.getElementById('completionChart');
        if (!canvas) {
            return;
        }

        loadChartJs().then(function() {
            var ctx = canvas.getContext('2d');
            new Chart(ctx, {
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
                            labels: {
                                font: {
                                    size: 12
                                },
                                padding: 15,
                                usePointStyle: true
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                font: {
                                    size: 11
                                }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            ticks: {
                                font: {
                                    size: 11
                                }
                            },
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        });
    };

    /**
     * Render pie/doughnut chart for course distribution
     */
    var renderDistributionChart = function() {
        var canvas = document.getElementById('distributionChart');
        if (!canvas) {
            return;
        }

        loadChartJs().then(function() {
            var ctx = canvas.getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Mathematics', 'Science', 'English', 'History', 'Arts'],
                    datasets: [{
                        data: [25, 20, 18, 22, 15],
                        backgroundColor: [
                            '#007bff',
                            '#28a745',
                            '#ffc107',
                            '#dc3545',
                            '#6f42c1'
                        ],
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
                            labels: {
                                font: {
                                    size: 12
                                },
                                padding: 15,
                                usePointStyle: true
                            }
                        }
                    }
                }
            });
        });
    };

    /**
     * Render line chart for engagement trends
     */
    var renderEngagementTrendChart = function() {
        var canvas = document.getElementById('engagementTrendChart');
        if (!canvas) {
            return;
        }

        loadChartJs().then(function() {
            var ctx = canvas.getContext('2d');
            new Chart(ctx, {
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
                        legend: {
                            position: 'top',
                            labels: {
                                font: {
                                    size: 12
                                },
                                padding: 15,
                                usePointStyle: true
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                font: {
                                    size: 11
                                }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            ticks: {
                                font: {
                                    size: 11
                                }
                            },
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        });
    };

    /**
     * Render pie chart for course status
     */
    var renderCourseStatusChart = function() {
        var canvas = document.getElementById('courseStatusChart');
        if (!canvas) {
            return;
        }

        loadChartJs().then(function() {
            var ctx = canvas.getContext('2d');
            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: ['Active', 'Completed', 'Archived', 'Draft'],
                    datasets: [{
                        data: [45, 30, 15, 10],
                        backgroundColor: [
                            '#28a745',
                            '#007bff',
                            '#6c757d',
                            '#ffc107'
                        ],
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
                            labels: {
                                font: {
                                    size: 12
                                },
                                padding: 15,
                                usePointStyle: true
                            }
                        }
                    }
                }
            });
        });
    };

    /**
     * Render performance distribution chart
     */
    var renderPerformanceChart = function() {
        var canvas = document.getElementById('performanceChart');
        if (!canvas) {
            return;
        }

        loadChartJs().then(function() {
            var ctx = canvas.getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Excellent', 'Good', 'Average', 'Below Avg', 'Poor'],
                    datasets: [{
                        label: 'Number of Students',
                        data: [120, 180, 150, 80, 40],
                        backgroundColor: [
                            '#28a745',
                            '#17a2b8',
                            '#ffc107',
                            '#fd7e14',
                            '#dc3545'
                        ],
                        borderColor: [
                            '#1e7e34',
                            '#0c5460',
                            '#e0a800',
                            '#d35400',
                            '#c82333'
                        ],
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                font: {
                                    size: 11
                                }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        y: {
                            ticks: {
                                font: {
                                    size: 11
                                }
                            },
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        });
    };

    /**
     * Initialize all dashboard charts
     */
    var initDashboardCharts = function() {
        renderCompletionTrendChart();
        renderDistributionChart();
        renderEngagementTrendChart();
        renderCourseStatusChart();
        renderPerformanceChart();
    };

    return {
        init: initDashboardCharts,
        renderCompletionTrendChart: renderCompletionTrendChart,
        renderDistributionChart: renderDistributionChart,
        renderEngagementTrendChart: renderEngagementTrendChart,
        renderCourseStatusChart: renderCourseStatusChart,
        renderPerformanceChart: renderPerformanceChart
    };
});
