<?php
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
 * Widget configuration and management API for ManiReports.
 *
 * @package    local_manireports
 * @copyright  2025 ManiReports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_manireports\api;

defined('MOODLE_INTERNAL') || die();

/**
 * Widget management API class.
 *
 * Handles widget configuration, data source mapping, and CRUD operations.
 */
class widget_manager {

    /**
     * Widget type definitions with configuration schemas.
     *
     * @var array
     */
    private static $widget_types = [
        'kpi' => [
            'name' => 'KPI Card',
            'description' => 'Single metric with trend indicator',
            'config_schema' => [
                'metric' => 'string',      // Metric identifier.
                'label' => 'string',       // Display label.
                'format' => 'string',      // number, percentage, duration.
                'trend' => 'boolean',      // Show trend indicator.
                'comparison_period' => 'string', // previous_period, previous_year.
                'filters' => 'object'      // Filter configuration.
            ],
            'data_sources' => [
                'total_users',
                'active_users',
                'course_completions',
                'avg_engagement_score',
                'total_time_spent'
            ]
        ],
        'line' => [
            'name' => 'Line Chart',
            'description' => 'Trend visualization over time',
            'config_schema' => [
                'metric' => 'string',
                'label' => 'string',
                'time_period' => 'string', // 7days, 30days, 90days, 12months.
                'group_by' => 'string',    // day, week, month.
                'filters' => 'object'
            ],
            'data_sources' => [
                'enrollment_trend',
                'completion_trend',
                'engagement_trend',
                'time_spent_trend',
                'login_trend'
            ]
        ],
        'bar' => [
            'name' => 'Bar Chart',
            'description' => 'Comparison across categories',
            'config_schema' => [
                'metric' => 'string',
                'label' => 'string',
                'dimension' => 'string',   // course, company, department.
                'limit' => 'integer',      // Top N items.
                'sort' => 'string',        // asc, desc.
                'filters' => 'object'
            ],
            'data_sources' => [
                'top_courses',
                'completion_by_course',
                'engagement_by_course',
                'time_by_course',
                'quiz_scores_by_course'
            ]
        ],
        'pie' => [
            'name' => 'Pie Chart',
            'description' => 'Proportions and distributions',
            'config_schema' => [
                'metric' => 'string',
                'label' => 'string',
                'dimension' => 'string',
                'limit' => 'integer',
                'filters' => 'object'
            ],
            'data_sources' => [
                'completion_status',
                'enrollment_by_company',
                'users_by_role',
                'activity_types'
            ]
        ],
        'table' => [
            'name' => 'Data Table',
            'description' => 'Tabular data display',
            'config_schema' => [
                'report_id' => 'integer',  // Reference to report.
                'columns' => 'array',      // Column configuration.
                'page_size' => 'integer',  // Rows per page.
                'sortable' => 'boolean',
                'filters' => 'object'
            ],
            'data_sources' => [
                'custom_report',
                'course_list',
                'user_list',
                'completion_list'
            ]
        ]
    ];

    /**
     * Get all available widget types.
     *
     * @return array Widget type definitions
     */
    public function get_widget_types() {
        return self::$widget_types;
    }

    /**
     * Get widget type definition.
     *
     * @param string $type Widget type
     * @return array|null Widget type definition or null if not found
     */
    public function get_widget_type($type) {
        return self::$widget_types[$type] ?? null;
    }

    /**
     * Validate widget configuration against schema.
     *
     * @param string $type Widget type
     * @param object $config Widget configuration
     * @return bool True if valid
     * @throws \moodle_exception If validation fails
     */
    public function validate_widget_config($type, $config) {
        $typedef = $this->get_widget_type($type);
        if (!$typedef) {
            throw new \moodle_exception('invalidwidgettype', 'local_manireports');
        }

        $schema = $typedef['config_schema'];

        // Check required fields based on type.
        if (!isset($config->metric) && !isset($config->report_id)) {
            throw new \moodle_exception('widgetmetricrequired', 'local_manireports');
        }

        if (!isset($config->label)) {
            throw new \moodle_exception('widgetlabelrequired', 'local_manireports');
        }

        // Validate data source.
        if (isset($config->metric)) {
            $validsources = $typedef['data_sources'];
            if (!in_array($config->metric, $validsources)) {
                throw new \moodle_exception('invaliddatasource', 'local_manireports');
            }
        }

        return true;
    }

    /**
     * Create a widget for a dashboard.
     *
     * @param int $dashboardid Dashboard ID
     * @param object $widget Widget data with properties:
     *                      - widgettype: Widget type (kpi, line, bar, pie, table)
     *                      - title: Widget title
     *                      - position: Position in grid
     *                      - configjson: JSON configuration
     * @return int Widget ID
     * @throws \moodle_exception If validation fails
     */
    public function create_widget($dashboardid, $widget) {
        global $DB;

        // Verify dashboard exists.
        if (!$DB->record_exists('manireports_dashboards', ['id' => $dashboardid])) {
            throw new \moodle_exception('dashboardnotfound', 'local_manireports');
        }

        // Validate widget type.
        if (!isset(self::$widget_types[$widget->widgettype])) {
            throw new \moodle_exception('invalidwidgettype', 'local_manireports');
        }

        // Decode and validate config.
        if (is_string($widget->configjson)) {
            $config = json_decode($widget->configjson);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \moodle_exception('invalidjson', 'local_manireports');
            }
        } else {
            $config = $widget->configjson;
            $widget->configjson = json_encode($config);
        }

        $this->validate_widget_config($widget->widgettype, $config);

        // Prepare record.
        $record = new \stdClass();
        $record->dashboardid = $dashboardid;
        $record->widgettype = $widget->widgettype;
        $record->title = $widget->title;
        $record->position = $widget->position ?? 0;
        $record->configjson = $widget->configjson;

        return $DB->insert_record('manireports_dashboard_widgets', $record);
    }

    /**
     * Update widget configuration.
     *
     * @param int $widgetid Widget ID
     * @param object $data Update data
     * @return bool Success status
     * @throws \moodle_exception If widget not found or validation fails
     */
    public function update_widget($widgetid, $data) {
        global $DB;

        $widget = $DB->get_record('manireports_dashboard_widgets', ['id' => $widgetid]);
        if (!$widget) {
            throw new \moodle_exception('widgetnotfound', 'local_manireports');
        }

        // Update fields.
        if (isset($data->title)) {
            $widget->title = $data->title;
        }
        if (isset($data->position)) {
            $widget->position = $data->position;
        }
        if (isset($data->configjson)) {
            if (is_string($data->configjson)) {
                $config = json_decode($data->configjson);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \moodle_exception('invalidjson', 'local_manireports');
                }
            } else {
                $config = $data->configjson;
                $data->configjson = json_encode($config);
            }

            $this->validate_widget_config($widget->widgettype, $config);
            $widget->configjson = $data->configjson;
        }

        return $DB->update_record('manireports_dashboard_widgets', $widget);
    }

    /**
     * Delete a widget.
     *
     * @param int $widgetid Widget ID
     * @return bool Success status
     */
    public function delete_widget($widgetid) {
        global $DB;
        return $DB->delete_records('manireports_dashboard_widgets', ['id' => $widgetid]);
    }

    /**
     * Get all widgets for a dashboard.
     *
     * @param int $dashboardid Dashboard ID
     * @return array Array of widget records with decoded config
     */
    public function get_dashboard_widgets($dashboardid) {
        global $DB;

        $widgets = $DB->get_records('manireports_dashboard_widgets',
            ['dashboardid' => $dashboardid],
            'position ASC');

        // Decode config JSON.
        foreach ($widgets as $widget) {
            $widget->config = json_decode($widget->configjson);
        }

        return $widgets;
    }

    /**
     * Get widget data based on configuration.
     *
     * @param object $widget Widget record with config
     * @return array Widget data ready for rendering
     */
    public function get_widget_data($widget) {
        $config = is_string($widget->configjson) ? json_decode($widget->configjson) : $widget->configjson;

        // Route to appropriate data source handler.
        switch ($widget->widgettype) {
            case 'kpi':
                return $this->get_kpi_data($config);
            case 'line':
                return $this->get_line_chart_data($config);
            case 'bar':
                return $this->get_bar_chart_data($config);
            case 'pie':
                return $this->get_pie_chart_data($config);
            case 'table':
                return $this->get_table_data($config);
            default:
                return [];
        }
    }

    /**
     * Get KPI widget data.
     *
     * @param object $config Widget configuration
     * @return array KPI data
     */
    private function get_kpi_data($config) {
        global $DB;

        $data = [
            'value' => 0,
            'label' => $config->label ?? '',
            'format' => $config->format ?? 'number',
            'trend' => null
        ];

        // Map metric to query.
        switch ($config->metric) {
            case 'total_users':
                $data['value'] = $DB->count_records('user', ['deleted' => 0]);
                break;
            case 'active_users':
                $since = time() - (30 * 24 * 3600); // Last 30 days.
                $data['value'] = $DB->count_records_select('user',
                    'deleted = 0 AND lastaccess > ?', [$since]);
                break;
            case 'course_completions':
                $data['value'] = $DB->count_records('course_completions');
                break;
            case 'total_time_spent':
                $result = $DB->get_record_sql(
                    "SELECT SUM(duration) as total FROM {manireports_usertime_daily}"
                );
                $data['value'] = $result ? $result->total : 0;
                $data['format'] = 'duration';
                break;
        }

        return $data;
    }

    /**
     * Get line chart data.
     *
     * @param object $config Widget configuration
     * @return array Chart data
     */
    private function get_line_chart_data($config) {
        // Placeholder - would query based on metric and time period.
        return [
            'labels' => [],
            'datasets' => []
        ];
    }

    /**
     * Get bar chart data.
     *
     * @param object $config Widget configuration
     * @return array Chart data
     */
    private function get_bar_chart_data($config) {
        // Placeholder - would query based on metric and dimension.
        return [
            'labels' => [],
            'datasets' => []
        ];
    }

    /**
     * Get pie chart data.
     *
     * @param object $config Widget configuration
     * @return array Chart data
     */
    private function get_pie_chart_data($config) {
        // Placeholder - would query based on metric and dimension.
        return [
            'labels' => [],
            'datasets' => []
        ];
    }

    /**
     * Get table data.
     *
     * @param object $config Widget configuration
     * @return array Table data
     */
    private function get_table_data($config) {
        // Placeholder - would execute report or query.
        return [
            'columns' => [],
            'rows' => []
        ];
    }
}
