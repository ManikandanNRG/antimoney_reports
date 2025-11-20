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
 * Dashboard management API for ManiReports.
 *
 * @package    local_manireports
 * @copyright  2025 ManiReports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_manireports\api;

defined('MOODLE_INTERNAL') || die();

/**
 * Dashboard management API class.
 *
 * Handles CRUD operations for custom dashboards including creation,
 * layout management, retrieval, and deletion.
 */
class dashboard_manager {

    /**
     * Create a new dashboard.
     *
     * @param object $dashboard Dashboard data object with properties:
     *                         - name (required): Dashboard name
     *                         - description (optional): Dashboard description
     *                         - scope (required): 'personal', 'global', or 'company'
     *                         - companyid (optional): Company ID for company-scoped dashboards
     *                         - layoutjson (required): JSON-encoded layout configuration
     * @return int Dashboard ID
     * @throws \moodle_exception If validation fails
     */
    public function create_dashboard($dashboard) {
        global $DB, $USER;

        // Validate required fields.
        if (empty($dashboard->name)) {
            throw new \moodle_exception('dashboardnamerequired', 'local_manireports');
        }

        if (empty($dashboard->scope)) {
            throw new \moodle_exception('dashboardscoperequired', 'local_manireports');
        }

        // Validate scope.
        $validscopes = ['personal', 'global', 'company'];
        if (!in_array($dashboard->scope, $validscopes)) {
            throw new \moodle_exception('invaliddashboardscope', 'local_manireports');
        }

        // Validate company scope.
        if ($dashboard->scope === 'company' && empty($dashboard->companyid)) {
            throw new \moodle_exception('companyidrequired', 'local_manireports');
        }

        // Validate layout JSON.
        if (empty($dashboard->layoutjson)) {
            $dashboard->layoutjson = json_encode([]);
        } else if (is_string($dashboard->layoutjson)) {
            // Validate JSON format.
            $decoded = json_decode($dashboard->layoutjson);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \moodle_exception('invalidjson', 'local_manireports');
            }
        } else if (is_array($dashboard->layoutjson) || is_object($dashboard->layoutjson)) {
            $dashboard->layoutjson = json_encode($dashboard->layoutjson);
        }

        // Prepare record.
        $record = new \stdClass();
        $record->name = $dashboard->name;
        $record->description = $dashboard->description ?? '';
        $record->scope = $dashboard->scope;
        $record->companyid = $dashboard->companyid ?? null;
        $record->layoutjson = $dashboard->layoutjson;
        $record->createdby = $USER->id;
        $record->timecreated = time();
        $record->timemodified = time();

        // Insert record.
        $dashboardid = $DB->insert_record('manireports_dashboards', $record);

        // Log action.
        $logger = new audit_logger();
        $logger->log_action('create', 'dashboard', $dashboardid, json_encode([
            'name' => $record->name,
            'scope' => $record->scope
        ]));

        return $dashboardid;
    }

    /**
     * Save dashboard layout configuration.
     *
     * @param int $dashboardid Dashboard ID
     * @param mixed $layout Layout configuration (array, object, or JSON string)
     * @return bool Success status
     * @throws \moodle_exception If dashboard not found or validation fails
     */
    public function save_dashboard_layout($dashboardid, $layout) {
        global $DB;

        // Verify dashboard exists.
        $dashboard = $DB->get_record('manireports_dashboards', ['id' => $dashboardid]);
        if (!$dashboard) {
            throw new \moodle_exception('dashboardnotfound', 'local_manireports');
        }

        // Check permission.
        $this->check_dashboard_permission($dashboard, 'edit');

        // Convert layout to JSON if needed.
        if (is_string($layout)) {
            // Validate JSON format.
            $decoded = json_decode($layout);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \moodle_exception('invalidjson', 'local_manireports');
            }
            $layoutjson = $layout;
        } else {
            $layoutjson = json_encode($layout);
        }

        // Update record.
        $dashboard->layoutjson = $layoutjson;
        $dashboard->timemodified = time();
        $result = $DB->update_record('manireports_dashboards', $dashboard);

        // Log action.
        $logger = new audit_logger();
        $logger->log_action('update', 'dashboard', $dashboardid, 'Layout updated');

        return $result;
    }

    /**
     * Get dashboard by ID.
     *
     * @param int $dashboardid Dashboard ID
     * @return object Dashboard record with decoded layout
     * @throws \moodle_exception If dashboard not found or access denied
     */
    public function get_dashboard($dashboardid) {
        global $DB;

        $dashboard = $DB->get_record('manireports_dashboards', ['id' => $dashboardid]);
        if (!$dashboard) {
            throw new \moodle_exception('dashboardnotfound', 'local_manireports');
        }

        // Check permission.
        $this->check_dashboard_permission($dashboard, 'view');

        // Decode layout JSON.
        $dashboard->layout = json_decode($dashboard->layoutjson);

        return $dashboard;
    }

    /**
     * Get all dashboards accessible to current user.
     *
     * @param string $scope Optional scope filter ('personal', 'global', 'company')
     * @return array Array of dashboard records
     */
    public function get_dashboards($scope = null) {
        global $DB, $USER;

        $params = [];
        $whereclauses = [];

        // Filter by scope if provided.
        if ($scope !== null) {
            $whereclauses[] = 'scope = :scope';
            $params['scope'] = $scope;
        }

        // Apply access filters based on scope.
        $scopefilter = '(scope = :scopepersonal AND createdby = :userid)';
        $params['scopepersonal'] = 'personal';
        $params['userid'] = $USER->id;

        // Add global dashboards if user has capability.
        if (has_capability('local/manireports:viewadmindashboard', \context_system::instance())) {
            $scopefilter .= ' OR scope = :scopeglobal';
            $params['scopeglobal'] = 'global';
        }

        // Add company dashboards if IOMAD is installed.
        $iomadfilter = new iomad_filter();
        if ($iomadfilter->is_iomad_installed()) {
            $companies = $iomadfilter->get_user_companies($USER->id);
            if (!empty($companies)) {
                list($insql, $incompany) = $DB->get_in_or_equal($companies, SQL_PARAMS_NAMED, 'company');
                $scopefilter .= " OR (scope = :scopecompany AND companyid $insql)";
                $params['scopecompany'] = 'company';
                $params = array_merge($params, $incompany);
            }
        }

        $whereclauses[] = "($scopefilter)";

        $where = implode(' AND ', $whereclauses);
        $sql = "SELECT * FROM {manireports_dashboards} WHERE $where ORDER BY timecreated DESC";

        $dashboards = $DB->get_records_sql($sql, $params);

        // Decode layout JSON for each dashboard.
        foreach ($dashboards as $dashboard) {
            $dashboard->layout = json_decode($dashboard->layoutjson);
        }

        return $dashboards;
    }

    /**
     * Delete a dashboard.
     *
     * @param int $dashboardid Dashboard ID
     * @return bool Success status
     * @throws \moodle_exception If dashboard not found or access denied
     */
    public function delete_dashboard($dashboardid) {
        global $DB;

        $dashboard = $DB->get_record('manireports_dashboards', ['id' => $dashboardid]);
        if (!$dashboard) {
            throw new \moodle_exception('dashboardnotfound', 'local_manireports');
        }

        // Check permission.
        $this->check_dashboard_permission($dashboard, 'delete');

        // Delete associated widgets.
        $DB->delete_records('manireports_dashboard_widgets', ['dashboardid' => $dashboardid]);

        // Delete dashboard.
        $result = $DB->delete_records('manireports_dashboards', ['id' => $dashboardid]);

        // Log action.
        $logger = new audit_logger();
        $logger->log_action('delete', 'dashboard', $dashboardid, json_encode([
            'name' => $dashboard->name
        ]));

        return $result;
    }

    /**
     * Update dashboard metadata (name, description, scope).
     *
     * @param int $dashboardid Dashboard ID
     * @param object $data Update data
     * @return bool Success status
     * @throws \moodle_exception If dashboard not found or validation fails
     */
    public function update_dashboard($dashboardid, $data) {
        global $DB;

        $dashboard = $DB->get_record('manireports_dashboards', ['id' => $dashboardid]);
        if (!$dashboard) {
            throw new \moodle_exception('dashboardnotfound', 'local_manireports');
        }

        // Check permission.
        $this->check_dashboard_permission($dashboard, 'edit');

        // Update fields.
        if (isset($data->name)) {
            $dashboard->name = $data->name;
        }
        if (isset($data->description)) {
            $dashboard->description = $data->description;
        }
        if (isset($data->scope)) {
            // Validate scope.
            $validscopes = ['personal', 'global', 'company'];
            if (!in_array($data->scope, $validscopes)) {
                throw new \moodle_exception('invaliddashboardscope', 'local_manireports');
            }
            $dashboard->scope = $data->scope;
        }
        if (isset($data->companyid)) {
            $dashboard->companyid = $data->companyid;
        }

        $dashboard->timemodified = time();
        $result = $DB->update_record('manireports_dashboards', $dashboard);

        // Log action.
        $logger = new audit_logger();
        $logger->log_action('update', 'dashboard', $dashboardid, 'Metadata updated');

        return $result;
    }

    /**
     * Check if user has permission to perform action on dashboard.
     *
     * @param object $dashboard Dashboard record
     * @param string $action Action type ('view', 'edit', 'delete')
     * @throws \moodle_exception If permission denied
     */
    private function check_dashboard_permission($dashboard, $action) {
        global $USER;

        $context = \context_system::instance();

        // Personal dashboards: only creator can edit/delete.
        if ($dashboard->scope === 'personal') {
            if ($action === 'view') {
                if ($dashboard->createdby != $USER->id) {
                    throw new \moodle_exception('nopermission', 'local_manireports');
                }
            } else {
                if ($dashboard->createdby != $USER->id) {
                    throw new \moodle_exception('nopermission', 'local_manireports');
                }
            }
            return;
        }

        // Global dashboards: require admin capability.
        if ($dashboard->scope === 'global') {
            if ($action === 'view') {
                require_capability('local/manireports:viewadmindashboard', $context);
            } else {
                require_capability('local/manireports:managereports', $context);
            }
            return;
        }

        // Company dashboards: check company access.
        if ($dashboard->scope === 'company') {
            $iomadfilter = new iomad_filter();
            if ($iomadfilter->is_iomad_installed()) {
                $companies = $iomadfilter->get_user_companies($USER->id);
                if (!in_array($dashboard->companyid, $companies)) {
                    throw new \moodle_exception('nopermission', 'local_manireports');
                }
            }

            if ($action !== 'view') {
                require_capability('local/manireports:managereports', $context);
            }
            return;
        }
    }
}
