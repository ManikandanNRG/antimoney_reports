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
 * Chart factory for creating chart instances.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_manireports\charts;

defined('MOODLE_INTERNAL') || die();

/**
 * Chart factory class.
 */
class chart_factory {

    /**
     * Create a chart instance.
     *
     * @param string $type Chart type (line, bar, pie)
     * @param array $data Chart data
     * @param array $config Chart configuration
     * @return base_chart Chart instance
     * @throws \moodle_exception If chart type is invalid
     */
    public static function create($type, $data = array(), $config = array()) {
        $classname = "\\local_manireports\\charts\\{$type}_chart";

        if (!class_exists($classname)) {
            throw new \moodle_exception('error:invalidcharttype', 'local_manireports', '', $type);
        }

        return new $classname($data, $config);
    }

    /**
     * Get list of available chart types.
     *
     * @return array Array of chart type identifiers
     */
    public static function get_available_types() {
        return array('line', 'bar', 'pie');
    }
}
