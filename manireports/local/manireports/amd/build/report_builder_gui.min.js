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
 * GUI Report Builder JavaScript
 *
 * @module     local_manireports/report_builder_gui
 * @package    local_manireports
 * @copyright  2024 ManiReports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/notification'], function($, Ajax, Notification) {

    var config = {
        tables: [],
        columns: [],
        joins: [],
        filters: [],
        groupby: [],
        orderby: [],
        filter_logic: 'AND'
    };

    var tables = [];
    var tableColumns = {};

    /**
     * Initialize the GUI builder
     *
     * @param {int} reportid Report ID
     * @param {string} configJson Configuration JSON
     * @param {string} tablesJson Tables JSON
     */
    function init(reportid, configJson, tablesJson) {
        tables = JSON.parse(tablesJson);
        
        if (configJson && configJson !== '{}') {
            config = JSON.parse(configJson);
            renderExistingConfig();
        }

        setupEventHandlers();
        updateSQLPreview();
    }

    /**
     * Setup event handlers
     */
    function setupEventHandlers() {
        // Add table button
        $('#btn-add-table').on('click', function() {
            var tableName = $('#add-table').val();
            if (tableName) {
                addTable(tableName);
                $('#add-table').val('');
            }
        });

        // Add join button
        $('#btn-add-join').on('click', function() {
            addJoin();
        });

        // Add filter button
        $('#btn-add-filter').on('click', function() {
            addFilter();
        });

        // Add group by button
        $('#btn-add-groupby').on('click', function() {
            addGroupBy();
        });

        // Add order by button
        $('#btn-add-orderby').on('click', function() {
            addOrderBy();
        });

        // Filter logic change
        $('#filter-logic').on('change', function() {
            config.filter_logic = $(this).val();
            updateSQLPreview();
        });

        // Form submission
        $('#gui-builder-form').on('submit', function() {
            $('#config-json').val(JSON.stringify(config));
        });
    }

    /**
     * Add a table to the configuration
     *
     * @param {string} tableName Table name
     */
    function addTable(tableName) {
        // Check if table already added
        var exists = config.tables.some(function(t) {
            return t.alias === tableName;
        });

        if (exists) {
            Notification.addNotification({
                message: 'Table already added',
                type: 'warning'
            });
            return;
        }

        config.tables.push({
            alias: tableName
        });

        renderTables();
        loadTableColumns(tableName);
        updateSQLPreview();
    }

    /**
     * Remove a table from configuration
     *
     * @param {string} tableName Table name
     */
    function removeTable(tableName) {
        config.tables = config.tables.filter(function(t) {
            return t.alias !== tableName;
        });

        // Remove columns from this table
        config.columns = config.columns.filter(function(c) {
            return c.table !== tableName;
        });

        // Remove joins involving this table
        config.joins = config.joins.filter(function(j) {
            return j.table !== tableName && j.left_table !== tableName;
        });

        renderTables();
        renderColumns();
        renderJoins();
        updateSQLPreview();
    }

    /**
     * Load columns for a table
     *
     * @param {string} tableName Table name
     */
    function loadTableColumns(tableName) {
        if (tableColumns[tableName]) {
            renderColumnSelector();
            return;
        }

        Ajax.call([{
            methodname: 'local_manireports_get_table_columns',
            args: {tablename: tableName},
            done: function(response) {
                tableColumns[tableName] = response.columns;
                renderColumnSelector();
            },
            fail: Notification.exception
        }]);
    }

    /**
     * Add a column to configuration
     *
     * @param {string} tableName Table name
     * @param {string} columnName Column name
     */
    function addColumn(tableName, columnName) {
        config.columns.push({
            table: tableName,
            name: columnName,
            aggregation: '',
            alias: ''
        });

        renderColumns();
        updateSQLPreview();
    }

    /**
     * Remove a column from configuration
     *
     * @param {int} index Column index
     */
    function removeColumn(index) {
        config.columns.splice(index, 1);
        renderColumns();
        updateSQLPreview();
    }

    /**
     * Update column configuration
     *
     * @param {int} index Column index
     * @param {string} field Field name
     * @param {string} value Field value
     */
    function updateColumn(index, field, value) {
        config.columns[index][field] = value;
        updateSQLPreview();
    }

    /**
     * Add a join to configuration
     */
    function addJoin() {
        if (config.tables.length < 2) {
            Notification.addNotification({
                message: 'Add at least 2 tables before creating joins',
                type: 'warning'
            });
            return;
        }

        config.joins.push({
            type: 'INNER',
            table: config.tables[1].alias,
            left_table: config.tables[0].alias,
            left_column: 'id',
            right_column: 'id'
        });

        renderJoins();
        updateSQLPreview();
    }

    /**
     * Remove a join from configuration
     *
     * @param {int} index Join index
     */
    function removeJoin(index) {
        config.joins.splice(index, 1);
        renderJoins();
        updateSQLPreview();
    }

    /**
     * Update join configuration
     *
     * @param {int} index Join index
     * @param {string} field Field name
     * @param {string} value Field value
     */
    function updateJoin(index, field, value) {
        config.joins[index][field] = value;
        updateSQLPreview();
    }

    /**
     * Add a filter to configuration
     */
    function addFilter() {
        if (config.tables.length === 0) {
            Notification.addNotification({
                message: 'Add at least one table before creating filters',
                type: 'warning'
            });
            return;
        }

        config.filters.push({
            table: config.tables[0].alias,
            column: '',
            operator: '=',
            value: ''
        });

        renderFilters();
        updateSQLPreview();
    }

    /**
     * Remove a filter from configuration
     *
     * @param {int} index Filter index
     */
    function removeFilter(index) {
        config.filters.splice(index, 1);
        renderFilters();
        updateSQLPreview();
    }

    /**
     * Update filter configuration
     *
     * @param {int} index Filter index
     * @param {string} field Field name
     * @param {string} value Field value
     */
    function updateFilter(index, field, value) {
        config.filters[index][field] = value;
        updateSQLPreview();
    }

    /**
     * Add a group by to configuration
     */
    function addGroupBy() {
        if (config.tables.length === 0) {
            Notification.addNotification({
                message: 'Add at least one table before grouping',
                type: 'warning'
            });
            return;
        }

        config.groupby.push({
            table: config.tables[0].alias,
            column: ''
        });

        renderGroupBy();
        updateSQLPreview();
    }

    /**
     * Remove a group by from configuration
     *
     * @param {int} index Group by index
     */
    function removeGroupBy(index) {
        config.groupby.splice(index, 1);
        renderGroupBy();
        updateSQLPreview();
    }

    /**
     * Update group by configuration
     *
     * @param {int} index Group by index
     * @param {string} field Field name
     * @param {string} value Field value
     */
    function updateGroupBy(index, field, value) {
        config.groupby[index][field] = value;
        updateSQLPreview();
    }

    /**
     * Add an order by to configuration
     */
    function addOrderBy() {
        if (config.tables.length === 0) {
            Notification.addNotification({
                message: 'Add at least one table before sorting',
                type: 'warning'
            });
            return;
        }

        config.orderby.push({
            table: config.tables[0].alias,
            column: '',
            direction: 'ASC'
        });

        renderOrderBy();
        updateSQLPreview();
    }

    /**
     * Remove an order by from configuration
     *
     * @param {int} index Order by index
     */
    function removeOrderBy(index) {
        config.orderby.splice(index, 1);
        renderOrderBy();
        updateSQLPreview();
    }

    /**
     * Update order by configuration
     *
     * @param {int} index Order by index
     * @param {string} field Field name
     * @param {string} value Field value
     */
    function updateOrderBy(index, field, value) {
        config.orderby[index][field] = value;
        updateSQLPreview();
    }

    /**
     * Render existing configuration
     */
    function renderExistingConfig() {
        renderTables();
        renderColumns();
        renderJoins();
        renderFilters();
        renderGroupBy();
        renderOrderBy();
        
        // Load columns for all tables
        config.tables.forEach(function(table) {
            loadTableColumns(table.alias);
        });
        
        // Set filter logic
        $('#filter-logic').val(config.filter_logic || 'AND');
    }

    /**
     * Render tables list
     */
    function renderTables() {
        var html = '';
        config.tables.forEach(function(table) {
            var tableInfo = tables.find(function(t) {
                return t.name === table.alias;
            });
            html += '<div class="badge badge-primary mr-2 mb-2">' +
                    (tableInfo ? tableInfo.label : table.alias) +
                    ' <button type="button" class="btn btn-sm btn-link text-white p-0 ml-1" ' +
                    'onclick="removeTable(\'' + table.alias + '\')">&times;</button>' +
                    '</div>';
        });
        $('#selected-tables').html(html);
    }

    /**
     * Render column selector
     */
    function renderColumnSelector() {
        var html = '';
        config.tables.forEach(function(table) {
            if (tableColumns[table.alias]) {
                html += '<div class="mb-2"><strong>' + table.alias + '</strong></div>';
                html += '<div class="row mb-3">';
                tableColumns[table.alias].forEach(function(column) {
                    html += '<div class="col-md-4">' +
                            '<button type="button" class="btn btn-sm btn-outline-secondary btn-block" ' +
                            'onclick="addColumn(\'' + table.alias + '\', \'' + column.name + '\')">' +
                            column.label +
                            '</button></div>';
                });
                html += '</div>';
            }
        });
        $('#column-selector').html(html);
    }

    /**
     * Render selected columns
     */
    function renderColumns() {
        var html = '<table class="table table-sm"><thead><tr>' +
                   '<th>Table</th><th>Column</th><th>Aggregation</th><th>Alias</th><th>Action</th>' +
                   '</tr></thead><tbody>';
        
        config.columns.forEach(function(column, index) {
            html += '<tr>' +
                    '<td>' + column.table + '</td>' +
                    '<td>' + column.name + '</td>' +
                    '<td><select class="form-control form-control-sm" onchange="updateColumn(' + index + ', \'aggregation\', this.value)">' +
                    '<option value="">None</option>' +
                    '<option value="COUNT"' + (column.aggregation === 'COUNT' ? ' selected' : '') + '>COUNT</option>' +
                    '<option value="SUM"' + (column.aggregation === 'SUM' ? ' selected' : '') + '>SUM</option>' +
                    '<option value="AVG"' + (column.aggregation === 'AVG' ? ' selected' : '') + '>AVG</option>' +
                    '<option value="MIN"' + (column.aggregation === 'MIN' ? ' selected' : '') + '>MIN</option>' +
                    '<option value="MAX"' + (column.aggregation === 'MAX' ? ' selected' : '') + '>MAX</option>' +
                    '</select></td>' +
                    '<td><input type="text" class="form-control form-control-sm" value="' + (column.alias || '') + '" ' +
                    'onchange="updateColumn(' + index + ', \'alias\', this.value)"></td>' +
                    '<td><button type="button" class="btn btn-sm btn-danger" onclick="removeColumn(' + index + ')">Remove</button></td>' +
                    '</tr>';
        });
        
        html += '</tbody></table>';
        $('#selected-columns').html(html);
    }

    /**
     * Render joins list
     */
    function renderJoins() {
        var html = '';
        config.joins.forEach(function(join, index) {
            html += '<div class="card mb-2"><div class="card-body">' +
                    '<div class="row">' +
                    '<div class="col-md-2">' +
                    '<select class="form-control form-control-sm" onchange="updateJoin(' + index + ', \'type\', this.value)">' +
                    '<option value="INNER"' + (join.type === 'INNER' ? ' selected' : '') + '>INNER</option>' +
                    '<option value="LEFT"' + (join.type === 'LEFT' ? ' selected' : '') + '>LEFT</option>' +
                    '<option value="RIGHT"' + (join.type === 'RIGHT' ? ' selected' : '') + '>RIGHT</option>' +
                    '</select></div>' +
                    '<div class="col-md-2">' +
                    '<select class="form-control form-control-sm" onchange="updateJoin(' + index + ', \'table\', this.value)">';
            
            config.tables.forEach(function(table) {
                html += '<option value="' + table.alias + '"' + (join.table === table.alias ? ' selected' : '') + '>' +
                        table.alias + '</option>';
            });
            
            html += '</select></div>' +
                    '<div class="col-md-3">' +
                    '<input type="text" class="form-control form-control-sm" placeholder="Left column" ' +
                    'value="' + join.left_column + '" onchange="updateJoin(' + index + ', \'left_column\', this.value)"></div>' +
                    '<div class="col-md-3">' +
                    '<input type="text" class="form-control form-control-sm" placeholder="Right column" ' +
                    'value="' + join.right_column + '" onchange="updateJoin(' + index + ', \'right_column\', this.value)"></div>' +
                    '<div class="col-md-2">' +
                    '<button type="button" class="btn btn-sm btn-danger" onclick="removeJoin(' + index + ')">Remove</button></div>' +
                    '</div></div></div>';
        });
        $('#joins-list').html(html);
    }

    /**
     * Render filters list
     */
    function renderFilters() {
        var html = '';
        config.filters.forEach(function(filter, index) {
            html += '<div class="card mb-2"><div class="card-body">' +
                    '<div class="row">' +
                    '<div class="col-md-2">' +
                    '<select class="form-control form-control-sm" onchange="updateFilter(' + index + ', \'table\', this.value)">';
            
            config.tables.forEach(function(table) {
                html += '<option value="' + table.alias + '"' + (filter.table === table.alias ? ' selected' : '') + '>' +
                        table.alias + '</option>';
            });
            
            html += '</select></div>' +
                    '<div class="col-md-2">' +
                    '<input type="text" class="form-control form-control-sm" placeholder="Column" ' +
                    'value="' + filter.column + '" onchange="updateFilter(' + index + ', \'column\', this.value)"></div>' +
                    '<div class="col-md-2">' +
                    '<select class="form-control form-control-sm" onchange="updateFilter(' + index + ', \'operator\', this.value)">' +
                    '<option value="="' + (filter.operator === '=' ? ' selected' : '') + '>=</option>' +
                    '<option value="!="' + (filter.operator === '!=' ? ' selected' : '') + '>!=</option>' +
                    '<option value=">"' + (filter.operator === '>' ? ' selected' : '') + '>></option>' +
                    '<option value=">="' + (filter.operator === '>=' ? ' selected' : '') + '>>=</option>' +
                    '<option value="<"' + (filter.operator === '<' ? ' selected' : '') + '><</option>' +
                    '<option value="<="' + (filter.operator === '<=' ? ' selected' : '') + '><=</option>' +
                    '<option value="LIKE"' + (filter.operator === 'LIKE' ? ' selected' : '') + '>LIKE</option>' +
                    '<option value="IN"' + (filter.operator === 'IN' ? ' selected' : '') + '>IN</option>' +
                    '<option value="IS NULL"' + (filter.operator === 'IS NULL' ? ' selected' : '') + '>IS NULL</option>' +
                    '<option value="IS NOT NULL"' + (filter.operator === 'IS NOT NULL' ? ' selected' : '') + '>IS NOT NULL</option>' +
                    '</select></div>' +
                    '<div class="col-md-4">' +
                    '<input type="text" class="form-control form-control-sm" placeholder="Value" ' +
                    'value="' + (filter.value || '') + '" onchange="updateFilter(' + index + ', \'value\', this.value)"></div>' +
                    '<div class="col-md-2">' +
                    '<button type="button" class="btn btn-sm btn-danger" onclick="removeFilter(' + index + ')">Remove</button></div>' +
                    '</div></div></div>';
        });
        $('#filters-list').html(html);
    }

    /**
     * Render group by list
     */
    function renderGroupBy() {
        var html = '';
        config.groupby.forEach(function(group, index) {
            html += '<div class="card mb-2"><div class="card-body">' +
                    '<div class="row">' +
                    '<div class="col-md-4">' +
                    '<select class="form-control form-control-sm" onchange="updateGroupBy(' + index + ', \'table\', this.value)">';
            
            config.tables.forEach(function(table) {
                html += '<option value="' + table.alias + '"' + (group.table === table.alias ? ' selected' : '') + '>' +
                        table.alias + '</option>';
            });
            
            html += '</select></div>' +
                    '<div class="col-md-6">' +
                    '<input type="text" class="form-control form-control-sm" placeholder="Column" ' +
                    'value="' + group.column + '" onchange="updateGroupBy(' + index + ', \'column\', this.value)"></div>' +
                    '<div class="col-md-2">' +
                    '<button type="button" class="btn btn-sm btn-danger" onclick="removeGroupBy(' + index + ')">Remove</button></div>' +
                    '</div></div></div>';
        });
        $('#groupby-list').html(html);
    }

    /**
     * Render order by list
     */
    function renderOrderBy() {
        var html = '';
        config.orderby.forEach(function(order, index) {
            html += '<div class="card mb-2"><div class="card-body">' +
                    '<div class="row">' +
                    '<div class="col-md-3">' +
                    '<select class="form-control form-control-sm" onchange="updateOrderBy(' + index + ', \'table\', this.value)">';
            
            config.tables.forEach(function(table) {
                html += '<option value="' + table.alias + '"' + (order.table === table.alias ? ' selected' : '') + '>' +
                        table.alias + '</option>';
            });
            
            html += '</select></div>' +
                    '<div class="col-md-4">' +
                    '<input type="text" class="form-control form-control-sm" placeholder="Column" ' +
                    'value="' + order.column + '" onchange="updateOrderBy(' + index + ', \'column\', this.value)"></div>' +
                    '<div class="col-md-3">' +
                    '<select class="form-control form-control-sm" onchange="updateOrderBy(' + index + ', \'direction\', this.value)">' +
                    '<option value="ASC"' + (order.direction === 'ASC' ? ' selected' : '') + '>ASC</option>' +
                    '<option value="DESC"' + (order.direction === 'DESC' ? ' selected' : '') + '>DESC</option>' +
                    '</select></div>' +
                    '<div class="col-md-2">' +
                    '<button type="button" class="btn btn-sm btn-danger" onclick="removeOrderBy(' + index + ')">Remove</button></div>' +
                    '</div></div></div>';
        });
        $('#orderby-list').html(html);
    }

    /**
     * Update SQL preview
     */
    function updateSQLPreview() {
        Ajax.call([{
            methodname: 'local_manireports_build_sql_preview',
            args: {config: JSON.stringify(config)},
            done: function(response) {
                $('#sql-preview').text(response.sql);
            },
            fail: function(error) {
                $('#sql-preview').text('Error: ' + error.message);
            }
        }]);
    }

    // Expose functions to global scope for onclick handlers
    window.removeTable = removeTable;
    window.addColumn = addColumn;
    window.removeColumn = removeColumn;
    window.updateColumn = updateColumn;
    window.removeJoin = removeJoin;
    window.updateJoin = updateJoin;
    window.removeFilter = removeFilter;
    window.updateFilter = updateFilter;
    window.removeGroupBy = removeGroupBy;
    window.updateGroupBy = updateGroupBy;
    window.removeOrderBy = removeOrderBy;
    window.updateOrderBy = updateOrderBy;

    return {
        init: init
    };
});
