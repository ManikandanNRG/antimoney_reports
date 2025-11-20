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
 * External API for GUI report builder
 *
 * @package    local_manireports
 * @copyright  2024 ManiReports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_manireports\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;
use local_manireports\api\query_builder;

/**
 * External API for GUI report builder
 *
 * @package    local_manireports
 * @copyright  2024 ManiReports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gui_builder extends external_api {

    /**
     * Returns description of method parameters for get_table_columns
     *
     * @return external_function_parameters
     */
    public static function get_table_columns_parameters() {
        return new external_function_parameters([
            'tablename' => new external_value(PARAM_ALPHANUMEXT, 'Table name'),
        ]);
    }

    /**
     * Get columns for a table
     *
     * @param string $tablename Table name
     * @return array
     */
    public static function get_table_columns($tablename) {
        global $USER;

        // Validate parameters
        $params = self::validate_parameters(self::get_table_columns_parameters(), [
            'tablename' => $tablename,
        ]);

        // Check capability
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/manireports:customreports', $context);

        // Get columns
        $columns = query_builder::get_table_columns($params['tablename']);

        return [
            'columns' => $columns,
        ];
    }

    /**
     * Returns description of method result value for get_table_columns
     *
     * @return external_single_structure
     */
    public static function get_table_columns_returns() {
        return new external_single_structure([
            'columns' => new external_multiple_structure(
                new external_single_structure([
                    'name' => new external_value(PARAM_TEXT, 'Column name'),
                    'type' => new external_value(PARAM_TEXT, 'Column type'),
                    'label' => new external_value(PARAM_TEXT, 'Column label'),
                ])
            ),
        ]);
    }

    /**
     * Returns description of method parameters for build_sql_preview
     *
     * @return external_function_parameters
     */
    public static function build_sql_preview_parameters() {
        return new external_function_parameters([
            'config' => new external_value(PARAM_RAW, 'Configuration JSON'),
        ]);
    }

    /**
     * Build SQL preview from configuration
     *
     * @param string $config Configuration JSON
     * @return array
     */
    public static function build_sql_preview($config) {
        global $USER;

        // Validate parameters
        $params = self::validate_parameters(self::build_sql_preview_parameters(), [
            'config' => $config,
        ]);

        // Check capability
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/manireports:customreports', $context);

        try {
            // Parse configuration
            $configobj = json_decode($params['config']);
            if (!$configobj) {
                throw new \moodle_exception('invalidconfig', 'local_manireports', '', 'Invalid JSON');
            }

            // Build SQL
            $result = query_builder::build_sql_from_config($configobj);

            return [
                'sql' => $result['sql'],
                'success' => true,
            ];
        } catch (\Exception $e) {
            return [
                'sql' => 'Error: ' . $e->getMessage(),
                'success' => false,
            ];
        }
    }

    /**
     * Returns description of method result value for build_sql_preview
     *
     * @return external_single_structure
     */
    public static function build_sql_preview_returns() {
        return new external_single_structure([
            'sql' => new external_value(PARAM_RAW, 'Generated SQL'),
            'success' => new external_value(PARAM_BOOL, 'Success status'),
        ]);
    }
}
