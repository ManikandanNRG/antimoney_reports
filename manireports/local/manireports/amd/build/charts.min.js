// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Chart rendering module for ManiReports.
 *
 * @module     local_manireports/charts
 * @copyright  2024 ManiReports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/notification', 'local_manireports/drilldown'], function($, Ajax, Notification, DrillDown) {

    /**
     * Initialize chart rendering.
     *
     * @param {String} canvasId Canvas element ID
     * @param {Object} config Chart configuration
     */
    var renderChart = function(canvasId, config) {
        var canvas = document.getElementById(canvasId);
        if (!canvas) {
            return;
        }

        // Check if Chart.js is loaded.
        if (typeof Chart === 'undefined') {
            loadChartJs().then(function() {
                createChart(canvas, config);
            }).catch(function(error) {
                Notification.exception(error);
            });
        } else {
            createChart(canvas, config);
        }
    };

    /**
     * Load Chart.js library from CDN.
     *
     * @return {Promise}
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
     * Create chart instance.
     *
     * @param {HTMLElement} canvas Canvas element
     * @param {Object} config Chart configuration
     * @return {Object} Chart instance
     */
    var createChart = function(canvas, config) {
        var ctx = canvas.getContext('2d');
        var chart = new Chart(ctx, config);

        // Attach drill-down if configured
        if (config.drilldown && config.drilldown.enabled) {
            DrillDown.attachToChart(chart, config.drilldown);
        }

        return chart;
    };

    /**
     * Fetch chart data via AJAX and render.
     *
     * @param {String} canvasId Canvas element ID
     * @param {String} reportType Report type
     * @param {Object} params Report parameters
     */
    var renderChartFromAjax = function(canvasId, reportType, params) {
        var promises = Ajax.call([{
            methodname: 'local_manireports_get_chart_data',
            args: {
                reporttype: reportType,
                params: JSON.stringify(params || {})
            }
        }]);

        promises[0].done(function(response) {
            var config = JSON.parse(response.config);
            renderChart(canvasId, config);
        }).fail(function(error) {
            Notification.exception(error);
        });
    };

    /**
     * Update chart with new data.
     *
     * @param {Object} chart Chart instance
     * @param {Object} newData New data
     */
    var updateChart = function(chart, newData) {
        chart.data = newData;
        chart.update();
    };

    return {
        renderChart: renderChart,
        renderChartFromAjax: renderChartFromAjax,
        updateChart: updateChart,
        createChart: createChart
    };
});
