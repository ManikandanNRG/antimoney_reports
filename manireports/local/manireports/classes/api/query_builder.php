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
 * Query builder API for GUI report builder
 *
 * @package    local_manireports
 * @copyright  2024 ManiReports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_manireports\api;

defined('MOODLE_INTERNAL') || die();

/**
 * Query builder class for constructing SQL from GUI configuration
 *
 * @package    local_manireports
 * @copyright  2024 ManiReports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class query_builder {

    /**
     * Whitelist of allowed Moodle tables for GUI report builder
     *
     * @var array
     */
    private static $allowed_tables = [
        'user' => 'mdl_user',
        'course' => 'mdl_course',
        'course_categories' => 'mdl_course_categories',
        'enrol' => 'mdl_enrol',
        'user_enrolments' => 'mdl_user_enrolments',
        'course_completions' => 'mdl_course_completions',
        'course_modules' => 'mdl_course_modules',
        'course_modules_completion' => 'mdl_course_modules_completion',
        'grade_grades' => 'mdl_grade_grades',
        'grade_items' => 'mdl_grade_items',
        'quiz' => 'mdl_quiz',
        'quiz_attempts' => 'mdl_quiz_attempts',
        'scorm' => 'mdl_scorm',
        'scorm_scoes_track' => 'mdl_scorm_scoes_track',
        'logstore_standard_log' => 'mdl_logstore_standard_log',
        'role_assignments' => 'mdl_role_assignments',
        'context' => 'mdl_context',
    ];

    /**
     * Get list of allowed tables with metadata
     *
     * @return array Array of table information
     */
    public static function get_allowed_tables() {
        global $DB;

        $tables = [];
        foreach (self::$allowed_tables as $alias => $tablename) {
            $tables[$alias] = [
                'name' => $alias,
                'tablename' => $tablename,
                'label' => get_string('table_' . $alias, 'local_manireports', $alias),
            ];
        }

        return $tables;
    }

    /**
     * Get columns for a specific table
     *
     * @param string $tablealias Table alias
     * @return array Array of column information
     */
    public static function get_table_columns($tablealias) {
        global $DB;

        if (!isset(self::$allowed_tables[$tablealias])) {
            return [];
        }

        $tablename = str_replace('mdl_', '', self::$allowed_tables[$tablealias]);
        
        // Get column information from database
        $columns = $DB->get_columns($tablename);
        
        $result = [];
        foreach ($columns as $column) {
            $result[] = [
                'name' => $column->name,
                'type' => $column->meta_type,
                'label' => get_string('column_' . $tablealias . '_' . $column->name, 'local_manireports', 
                    ucfirst(str_replace('_', ' ', $column->name))),
            ];
        }

        return $result;
    }

    /**
     * Build SQL query from GUI configuration
     *
     * @param object $config Configuration object containing tables, columns, joins, filters, etc.
     * @return array Array with 'sql' and 'params' keys
     * @throws \moodle_exception If configuration is invalid
     */
    public static function build_sql_from_config($config) {
        // Validate configuration
        self::validate_config($config);

        // Build SELECT clause
        $select = self::build_select_clause($config);

        // Build FROM clause
        $from = self::build_from_clause($config);

        // Build JOIN clauses
        $joins = self::build_join_clauses($config);

        // Build WHERE clause
        list($where, $params) = self::build_where_clause($config);

        // Build GROUP BY clause
        $groupby = self::build_groupby_clause($config);

        // Build ORDER BY clause
        $orderby = self::build_orderby_clause($config);

        // Construct final SQL
        $sql = "SELECT {$select}\n";
        $sql .= "FROM {$from}\n";
        
        if (!empty($joins)) {
            $sql .= implode("\n", $joins) . "\n";
        }
        
        if (!empty($where)) {
            $sql .= "WHERE {$where}\n";
        }
        
        if (!empty($groupby)) {
            $sql .= "GROUP BY {$groupby}\n";
        }
        
        if (!empty($orderby)) {
            $sql .= "ORDER BY {$orderby}";
        }

        return [
            'sql' => $sql,
            'params' => $params,
        ];
    }

    /**
     * Validate configuration object
     *
     * @param object $config Configuration object
     * @throws \moodle_exception If configuration is invalid
     */
    private static function validate_config($config) {
        if (empty($config->tables) || !is_array($config->tables)) {
            throw new \moodle_exception('invalidconfig', 'local_manireports', '', 'Missing or invalid tables');
        }

        if (empty($config->columns) || !is_array($config->columns)) {
            throw new \moodle_exception('invalidconfig', 'local_manireports', '', 'Missing or invalid columns');
        }

        // Validate all tables are in whitelist
        foreach ($config->tables as $table) {
            if (!isset(self::$allowed_tables[$table->alias])) {
                throw new \moodle_exception('invalidtable', 'local_manireports', '', $table->alias);
            }
        }
    }

    /**
     * Build SELECT clause
     *
     * @param object $config Configuration object
     * @return string SELECT clause
     */
    private static function build_select_clause($config) {
        $columns = [];

        foreach ($config->columns as $column) {
            $tablealias = $column->table;
            $columnname = $column->name;
            $alias = isset($column->alias) ? $column->alias : null;

            // Apply aggregation if specified
            if (isset($column->aggregation) && !empty($column->aggregation)) {
                $agg = strtoupper($column->aggregation);
                $colexpr = "{$agg}({$tablealias}.{$columnname})";
            } else {
                $colexpr = "{$tablealias}.{$columnname}";
            }

            // Add alias if specified
            if ($alias) {
                $colexpr .= " AS {$alias}";
            }

            $columns[] = $colexpr;
        }

        return implode(', ', $columns);
    }

    /**
     * Build FROM clause
     *
     * @param object $config Configuration object
     * @return string FROM clause
     */
    private static function build_from_clause($config) {
        // Use first table as main table
        $maintable = $config->tables[0];
        $tablename = self::$allowed_tables[$maintable->alias];
        
        return "{{$maintable->alias}} {$maintable->alias}";
    }

    /**
     * Build JOIN clauses
     *
     * @param object $config Configuration object
     * @return array Array of JOIN clauses
     */
    private static function build_join_clauses($config) {
        $joins = [];

        if (empty($config->joins)) {
            return $joins;
        }

        foreach ($config->joins as $join) {
            $jointype = strtoupper($join->type); // INNER, LEFT, RIGHT
            $tablealias = $join->table;
            $tablename = self::$allowed_tables[$tablealias];
            
            $lefttable = $join->left_table;
            $leftcolumn = $join->left_column;
            $rightcolumn = $join->right_column;

            $joins[] = "{$jointype} JOIN {{$tablealias}} {$tablealias} " .
                       "ON {$lefttable}.{$leftcolumn} = {$tablealias}.{$rightcolumn}";
        }

        return $joins;
    }

    /**
     * Build WHERE clause
     *
     * @param object $config Configuration object
     * @return array Array with WHERE clause and parameters
     */
    private static function build_where_clause($config) {
        $conditions = [];
        $params = [];

        if (empty($config->filters)) {
            return ['', []];
        }

        $paramcount = 0;
        foreach ($config->filters as $filter) {
            $tablealias = $filter->table;
            $columnname = $filter->column;
            $operator = $filter->operator;
            $value = $filter->value;

            $paramname = "param{$paramcount}";
            $paramcount++;

            switch ($operator) {
                case '=':
                case '!=':
                case '>':
                case '>=':
                case '<':
                case '<=':
                    $conditions[] = "{$tablealias}.{$columnname} {$operator} :{$paramname}";
                    $params[$paramname] = $value;
                    break;

                case 'LIKE':
                case 'NOT LIKE':
                    $conditions[] = "{$tablealias}.{$columnname} {$operator} :{$paramname}";
                    $params[$paramname] = "%{$value}%";
                    break;

                case 'IN':
                    if (is_array($value)) {
                        $inparams = [];
                        foreach ($value as $idx => $val) {
                            $inparamname = "{$paramname}_{$idx}";
                            $inparams[] = ":{$inparamname}";
                            $params[$inparamname] = $val;
                        }
                        $conditions[] = "{$tablealias}.{$columnname} IN (" . implode(', ', $inparams) . ")";
                    }
                    break;

                case 'IS NULL':
                    $conditions[] = "{$tablealias}.{$columnname} IS NULL";
                    break;

                case 'IS NOT NULL':
                    $conditions[] = "{$tablealias}.{$columnname} IS NOT NULL";
                    break;

                case 'BETWEEN':
                    if (isset($filter->value2)) {
                        $paramname2 = "param{$paramcount}";
                        $paramcount++;
                        $conditions[] = "{$tablealias}.{$columnname} BETWEEN :{$paramname} AND :{$paramname2}";
                        $params[$paramname] = $value;
                        $params[$paramname2] = $filter->value2;
                    }
                    break;
            }
        }

        // Combine conditions with AND/OR logic
        $logic = isset($config->filter_logic) ? strtoupper($config->filter_logic) : 'AND';
        $where = implode(" {$logic} ", $conditions);

        return [$where, $params];
    }

    /**
     * Build GROUP BY clause
     *
     * @param object $config Configuration object
     * @return string GROUP BY clause
     */
    private static function build_groupby_clause($config) {
        if (empty($config->groupby)) {
            return '';
        }

        $groups = [];
        foreach ($config->groupby as $group) {
            $groups[] = "{$group->table}.{$group->column}";
        }

        return implode(', ', $groups);
    }

    /**
     * Build ORDER BY clause
     *
     * @param object $config Configuration object
     * @return string ORDER BY clause
     */
    private static function build_orderby_clause($config) {
        if (empty($config->orderby)) {
            return '';
        }

        $orders = [];
        foreach ($config->orderby as $order) {
            $direction = isset($order->direction) ? strtoupper($order->direction) : 'ASC';
            $orders[] = "{$order->table}.{$order->column} {$direction}";
        }

        return implode(', ', $orders);
    }

    /**
     * Get available operators for a column type
     *
     * @param string $columntype Column type (from meta_type)
     * @return array Array of operators
     */
    public static function get_operators_for_type($columntype) {
        $operators = [
            'C' => ['=', '!=', 'LIKE', 'NOT LIKE', 'IN', 'IS NULL', 'IS NOT NULL'], // Character
            'I' => ['=', '!=', '>', '>=', '<', '<=', 'IN', 'BETWEEN', 'IS NULL', 'IS NOT NULL'], // Integer
            'N' => ['=', '!=', '>', '>=', '<', '<=', 'BETWEEN', 'IS NULL', 'IS NOT NULL'], // Number
            'R' => ['=', '!=', '>', '>=', '<', '<=', 'BETWEEN', 'IS NULL', 'IS NOT NULL'], // Real
            'D' => ['=', '!=', '>', '>=', '<', '<=', 'BETWEEN', 'IS NULL', 'IS NOT NULL'], // Date
            'T' => ['=', '!=', '>', '>=', '<', '<=', 'BETWEEN', 'IS NULL', 'IS NOT NULL'], // Timestamp
            'B' => ['=', '!=', 'IS NULL', 'IS NOT NULL'], // Binary/Boolean
            'X' => ['LIKE', 'NOT LIKE', 'IS NULL', 'IS NOT NULL'], // Text
        ];

        return isset($operators[$columntype]) ? $operators[$columntype] : $operators['C'];
    }

    /**
     * Get available aggregation functions
     *
     * @return array Array of aggregation functions
     */
    public static function get_aggregation_functions() {
        return [
            '' => get_string('none', 'local_manireports'),
            'COUNT' => 'COUNT',
            'SUM' => 'SUM',
            'AVG' => 'AVG',
            'MIN' => 'MIN',
            'MAX' => 'MAX',
        ];
    }

    /**
     * Get available join types
     *
     * @return array Array of join types
     */
    public static function get_join_types() {
        return [
            'INNER' => 'INNER JOIN',
            'LEFT' => 'LEFT JOIN',
            'RIGHT' => 'RIGHT JOIN',
        ];
    }
}
