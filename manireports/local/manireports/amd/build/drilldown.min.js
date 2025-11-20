/**
 * Drill-down functionality for interactive chart navigation
 *
 * @module     local_manireports/drilldown
 * @copyright  2024 ManiReports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/url', 'core/notification', 'core/str'], function($, url, notification, str) {

    /**
     * Initialize drill-down functionality
     */
    var DrillDown = function() {
        this.history = [];
        this.currentFilters = {};
        this.init();
    };

    /**
     * Initialize drill-down handlers
     */
    DrillDown.prototype.init = function() {
        var self = this;

        // Restore navigation history from sessionStorage
        this.restoreHistory();

        // Handle back button clicks
        $(document).on('click', '[data-action="drilldown-back"]', function(e) {
            e.preventDefault();
            self.navigateBack();
        });

        // Handle filter clear
        $(document).on('click', '[data-action="drilldown-clear"]', function(e) {
            e.preventDefault();
            self.clearFilters();
        });

        // Handle export from drill-down view
        $(document).on('click', '[data-action="drilldown-export"]', function(e) {
            e.preventDefault();
            var format = $(this).data('format');
            self.exportDrillDown(format);
        });

        // Update browser history state
        window.addEventListener('popstate', function(event) {
            if (event.state && event.state.drilldown) {
                self.restoreState(event.state);
            }
        });
    };

    /**
     * Attach drill-down handler to a chart
     *
     * @param {Object} chart Chart.js instance
     * @param {Object} config Drill-down configuration
     */
    DrillDown.prototype.attachToChart = function(chart, config) {
        var self = this;

        if (!chart || !config) {
            return;
        }

        // Store config on chart instance
        chart.drilldownConfig = config;

        // Add click handler to chart canvas
        chart.canvas.onclick = function(evt) {
            var activePoints = chart.getElementsAtEventForMode(
                evt,
                'nearest',
                {intersect: true},
                false
            );

            if (activePoints.length > 0) {
                var firstPoint = activePoints[0];
                var label = chart.data.labels[firstPoint.index];
                var value = chart.data.datasets[firstPoint.datasetIndex].data[firstPoint.index];
                var datasetLabel = chart.data.datasets[firstPoint.datasetIndex].label;

                self.handleChartClick(chart, {
                    label: label,
                    value: value,
                    datasetLabel: datasetLabel,
                    index: firstPoint.index,
                    datasetIndex: firstPoint.datasetIndex
                });
            }
        };

        // Change cursor on hover
        chart.canvas.style.cursor = 'pointer';
    };

    /**
     * Handle chart click event
     *
     * @param {Object} chart Chart.js instance
     * @param {Object} clickData Data about the clicked element
     */
    DrillDown.prototype.handleChartClick = function(chart, clickData) {
        var config = chart.drilldownConfig;

        if (!config || !config.enabled) {
            return;
        }

        // Extract dimension and value
        var dimension = config.dimension || 'id';
        var dimensionValue = this.extractDimensionValue(clickData, config);

        if (!dimensionValue) {
            notification.addNotification({
                message: 'Unable to extract drill-down value',
                type: 'error'
            });
            return;
        }

        // Build filters
        var filters = this.buildFilters(dimension, dimensionValue, config);

        // Save current state to history
        this.saveToHistory({
            url: window.location.href,
            filters: $.extend({}, this.currentFilters),
            title: document.title
        });

        // Navigate to drill-down view
        this.navigateToDrillDown(config.reportType, filters, clickData);
    };

    /**
     * Extract dimension value from clicked data
     *
     * @param {Object} clickData Click event data
     * @param {Object} config Drill-down configuration
     * @return {String|Number} Dimension value
     */
    DrillDown.prototype.extractDimensionValue = function(clickData, config) {
        // If custom extractor function provided
        if (config.valueExtractor && typeof config.valueExtractor === 'function') {
            return config.valueExtractor(clickData);
        }

        // Default: use label as value
        return clickData.label;
    };

    /**
     * Build filter parameters for drill-down
     *
     * @param {String} dimension Filter dimension
     * @param {String|Number} value Filter value
     * @param {Object} config Drill-down configuration
     * @return {Object} Filter parameters
     */
    DrillDown.prototype.buildFilters = function(dimension, value, config) {
        var filters = $.extend({}, this.currentFilters);

        // Add new filter
        filters[dimension] = value;

        // Add any additional filters from config
        if (config.additionalFilters) {
            $.extend(filters, config.additionalFilters);
        }

        return filters;
    };

    /**
     * Navigate to drill-down report view
     *
     * @param {String} reportType Type of report to display
     * @param {Object} filters Filter parameters
     * @param {Object} clickData Original click data
     */
    DrillDown.prototype.navigateToDrillDown = function(reportType, filters, clickData) {
        var self = this;

        // Build URL with filters
        var reportUrl = url.relativeUrl('/local/manireports/ui/report_view.php', {
            type: reportType,
            drilldown: 1
        });

        // Add filters to URL
        $.each(filters, function(key, value) {
            reportUrl += '&filter_' + key + '=' + encodeURIComponent(value);
        });

        // Update current filters
        this.currentFilters = filters;

        // Save state to browser history
        var state = {
            drilldown: true,
            reportType: reportType,
            filters: filters,
            clickData: clickData
        };

        window.history.pushState(state, '', reportUrl);

        // Load drill-down view
        this.loadDrillDownView(reportUrl);
    };

    /**
     * Load drill-down view via AJAX
     *
     * @param {String} reportUrl URL to load
     */
    DrillDown.prototype.loadDrillDownView = function(reportUrl) {
        var self = this;

        // Show loading indicator
        this.showLoading();

        // Load content
        $.ajax({
            url: reportUrl,
            method: 'GET',
            dataType: 'html',
            success: function(response) {
                self.hideLoading();
                self.renderDrillDownView(response);
            },
            error: function(xhr, status, error) {
                self.hideLoading();
                notification.addNotification({
                    message: 'Failed to load drill-down view: ' + error,
                    type: 'error'
                });
            }
        });
    };

    /**
     * Render drill-down view in the page
     *
     * @param {String} html HTML content to render
     */
    DrillDown.prototype.renderDrillDownView = function(html) {
        // Find main content area
        var $contentArea = $('#region-main');

        if ($contentArea.length === 0) {
            $contentArea = $('.main-content');
        }

        if ($contentArea.length === 0) {
            // Fallback: replace entire body content
            $('body').html(html);
            return;
        }

        // Replace content
        $contentArea.html(html);

        // Display applied filters
        this.displayAppliedFilters();

        // Scroll to top
        window.scrollTo(0, 0);
    };

    /**
     * Display applied filters prominently
     */
    DrillDown.prototype.displayAppliedFilters = function() {
        var self = this;

        if (Object.keys(this.currentFilters).length === 0) {
            return;
        }

        // Create filter display container
        var $filterDisplay = $('<div class="manireports-drilldown-filters alert alert-info"></div>');
        $filterDisplay.append('<strong>Applied Filters:</strong> ');

        // Add filter badges
        $.each(this.currentFilters, function(key, value) {
            var $badge = $('<span class="badge badge-primary mr-2"></span>');
            $badge.text(self.formatFilterLabel(key) + ': ' + value);

            // Add remove button
            var $removeBtn = $('<button class="btn btn-sm btn-link p-0 ml-1" data-filter-key="' + key + '">×</button>');
            $removeBtn.on('click', function() {
                self.removeFilter(key);
            });

            $badge.append($removeBtn);
            $filterDisplay.append($badge);
        });

        // Add clear all button
        var $clearBtn = $('<button class="btn btn-sm btn-secondary ml-2" data-action="drilldown-clear">Clear All</button>');
        $filterDisplay.append($clearBtn);

        // Add back button if history exists
        if (this.history.length > 0) {
            var $backBtn = $('<button class="btn btn-sm btn-primary ml-2" data-action="drilldown-back">← Back</button>');
            $filterDisplay.append($backBtn);
        }

        // Insert at top of content area
        $('#region-main').prepend($filterDisplay);
    };

    /**
     * Format filter key as human-readable label
     *
     * @param {String} key Filter key
     * @return {String} Formatted label
     */
    DrillDown.prototype.formatFilterLabel = function(key) {
        // Convert snake_case or camelCase to Title Case
        return key
            .replace(/_/g, ' ')
            .replace(/([A-Z])/g, ' $1')
            .replace(/^./, function(str) {
                return str.toUpperCase();
            })
            .trim();
    };

    /**
     * Remove a specific filter
     *
     * @param {String} key Filter key to remove
     */
    DrillDown.prototype.removeFilter = function(key) {
        delete this.currentFilters[key];

        // Reload view with updated filters
        var reportType = this.getCurrentReportType();
        this.navigateToDrillDown(reportType, this.currentFilters, {});
    };

    /**
     * Clear all filters
     */
    DrillDown.prototype.clearFilters = function() {
        this.currentFilters = {};
        this.history = [];

        // Navigate to base report view
        var reportType = this.getCurrentReportType();
        var baseUrl = url.relativeUrl('/local/manireports/ui/report_view.php', {
            type: reportType
        });

        window.location.href = baseUrl;
    };

    /**
     * Navigate back in drill-down history
     */
    DrillDown.prototype.navigateBack = function() {
        if (this.history.length === 0) {
            return;
        }

        var previousState = this.history.pop();
        this.currentFilters = previousState.filters;

        // Update sessionStorage
        this.saveHistory();

        // Navigate to previous URL
        window.location.href = previousState.url;
    };

    /**
     * Save current state to history
     *
     * @param {Object} state State to save
     */
    DrillDown.prototype.saveToHistory = function(state) {
        this.history.push(state);
        this.saveHistory();
    };

    /**
     * Save history to sessionStorage
     */
    DrillDown.prototype.saveHistory = function() {
        try {
            sessionStorage.setItem('manireports_drilldown_history', JSON.stringify(this.history));
            sessionStorage.setItem('manireports_drilldown_filters', JSON.stringify(this.currentFilters));
        } catch (e) {
            // SessionStorage not available
        }
    };

    /**
     * Restore history from sessionStorage
     */
    DrillDown.prototype.restoreHistory = function() {
        try {
            var historyJson = sessionStorage.getItem('manireports_drilldown_history');
            var filtersJson = sessionStorage.getItem('manireports_drilldown_filters');

            if (historyJson) {
                this.history = JSON.parse(historyJson);
            }

            if (filtersJson) {
                this.currentFilters = JSON.parse(filtersJson);
            }
        } catch (e) {
            // SessionStorage not available or invalid JSON
            this.history = [];
            this.currentFilters = {};
        }
    };

    /**
     * Restore state from browser history
     *
     * @param {Object} state State object
     */
    DrillDown.prototype.restoreState = function(state) {
        if (state.filters) {
            this.currentFilters = state.filters;
        }

        if (state.reportType) {
            this.loadDrillDownView(window.location.href);
        }
    };

    /**
     * Get current report type from URL
     *
     * @return {String} Report type
     */
    DrillDown.prototype.getCurrentReportType = function() {
        var urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('type') || 'course_completion';
    };

    /**
     * Export drill-down view with current filters
     *
     * @param {String} format Export format (csv, xlsx, pdf)
     */
    DrillDown.prototype.exportDrillDown = function(format) {
        var reportType = this.getCurrentReportType();

        // Build export URL with filters
        var exportUrl = url.relativeUrl('/local/manireports/ui/export.php', {
            type: reportType,
            format: format
        });

        // Add filters to URL
        $.each(this.currentFilters, function(key, value) {
            exportUrl += '&filter_' + key + '=' + encodeURIComponent(value);
        });

        // Trigger download
        window.location.href = exportUrl;
    };

    /**
     * Show loading indicator
     */
    DrillDown.prototype.showLoading = function() {
        var $loading = $('<div class="manireports-loading-overlay"></div>');
        $loading.append('<div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>');
        $('body').append($loading);
    };

    /**
     * Hide loading indicator
     */
    DrillDown.prototype.hideLoading = function() {
        $('.manireports-loading-overlay').remove();
    };

    return {
        /**
         * Initialize drill-down functionality
         *
         * @return {DrillDown} DrillDown instance
         */
        init: function() {
            return new DrillDown();
        },

        /**
         * Attach drill-down to a chart
         *
         * @param {Object} chart Chart.js instance
         * @param {Object} config Drill-down configuration
         */
        attachToChart: function(chart, config) {
            var instance = new DrillDown();
            instance.attachToChart(chart, config);
        }
    };
});
