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
 * Pie chart implementation.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_manireports\charts;

defined('MOODLE_INTERNAL') || die();

/**
 * Pie chart class.
 */
class pie_chart extends base_chart {

    /**
     * Get chart type.
     *
     * @return string
     */
    protected function get_chart_type() {
        return 'pie';
    }

    /**
     * Get chart data.
     *
     * @return array
     */
    public function get_chart_data() {
        $data = $this->data['data'] ?? array();
        $labels = $this->data['labels'] ?? array();

        // Generate colors for each segment.
        $backgroundColors = array();
        $borderColors = array();
        for ($i = 0; $i < count($data); $i++) {
            $backgroundColors[] = $this->get_palette_color($i, 0.8);
            $borderColors[] = $this->get_palette_color($i, 1);
        }

        return array(
            'labels' => $labels,
            'datasets' => array(
                array(
                    'data' => $data,
                    'backgroundColor' => $backgroundColors,
                    'borderColor' => $borderColors,
                    'borderWidth' => 1
                )
            )
        );
    }

    /**
     * Get chart configuration.
     *
     * @return array
     */
    public function get_chart_config() {
        return array_merge(parent::get_default_config(), array(
            'plugins' => array(
                'legend' => array(
                    'display' => true,
                    'position' => 'right'
                ),
                'title' => array(
                    'display' => !empty($this->config['title']),
                    'text' => $this->config['title'] ?? ''
                )
            )
        ));
    }
}
