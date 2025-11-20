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
 * Base chart class for all chart types.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_manireports\charts;

defined('MOODLE_INTERNAL') || die();

/**
 * Abstract base chart class.
 */
abstract class base_chart {

    /**
     * @var array Chart data
     */
    protected $data;

    /**
     * @var array Chart configuration
     */
    protected $config;

    /**
     * Constructor.
     *
     * @param array $data Chart data
     * @param array $config Chart configuration
     */
    public function __construct($data = array(), $config = array()) {
        $this->data = $data;
        $this->config = array_merge($this->get_default_config(), $config);
    }

    /**
     * Get chart data formatted for Chart.js.
     *
     * Must be implemented by child classes.
     *
     * @return array Chart.js data structure
     */
    abstract public function get_chart_data();

    /**
     * Get chart configuration for Chart.js.
     *
     * Must be implemented by child classes.
     *
     * @return array Chart.js configuration
     */
    abstract public function get_chart_config();

    /**
     * Get default configuration.
     *
     * Can be overridden by child classes.
     *
     * @return array Default configuration
     */
    protected function get_default_config() {
        return array(
            'responsive' => true,
            'maintainAspectRatio' => true,
            'aspectRatio' => 2,
        );
    }

    /**
     * Get complete Chart.js configuration including data.
     *
     * @return array Complete Chart.js configuration
     */
    public function get_chartjs_config() {
        $config = array(
            'type' => $this->get_chart_type(),
            'data' => $this->get_chart_data(),
            'options' => $this->get_chart_config()
        );

        // Add drill-down configuration if enabled
        if (isset($this->config['drilldown']) && $this->config['drilldown']) {
            $config['drilldown'] = $this->get_drilldown_config();
        }

        return $config;
    }

    /**
     * Get drill-down configuration.
     *
     * Can be overridden by child classes.
     *
     * @return array Drill-down configuration
     */
    protected function get_drilldown_config() {
        $drilldownconfig = array(
            'enabled' => true,
            'dimension' => 'id',
            'reportType' => 'course_completion'
        );

        // Merge with custom config if provided
        if (isset($this->config['drilldown']) && is_array($this->config['drilldown'])) {
            $drilldownconfig = array_merge($drilldownconfig, $this->config['drilldown']);
        }

        return $drilldownconfig;
    }

    /**
     * Get chart type identifier.
     *
     * Must be implemented by child classes.
     *
     * @return string Chart type (line, bar, pie, etc.)
     */
    abstract protected function get_chart_type();

    /**
     * Render chart as JSON for JavaScript.
     *
     * @return string JSON encoded chart configuration
     */
    public function render_json() {
        return json_encode($this->get_chartjs_config());
    }

    /**
     * Generate random color.
     *
     * @param float $opacity Opacity (0-1)
     * @return string RGBA color string
     */
    protected function generate_color($opacity = 1) {
        $r = rand(0, 255);
        $g = rand(0, 255);
        $b = rand(0, 255);
        return "rgba($r, $g, $b, $opacity)";
    }

    /**
     * Get predefined color palette.
     *
     * @param int $index Color index
     * @param float $opacity Opacity (0-1)
     * @return string RGBA color string
     */
    protected function get_palette_color($index, $opacity = 1) {
        $colors = array(
            array(54, 162, 235),   // Blue
            array(255, 99, 132),   // Red
            array(255, 206, 86),   // Yellow
            array(75, 192, 192),   // Green
            array(153, 102, 255),  // Purple
            array(255, 159, 64),   // Orange
            array(199, 199, 199),  // Grey
            array(83, 102, 255),   // Indigo
            array(255, 99, 255),   // Pink
            array(99, 255, 132),   // Light Green
        );

        $color = $colors[$index % count($colors)];
        return "rgba({$color[0]}, {$color[1]}, {$color[2]}, $opacity)";
    }
}
