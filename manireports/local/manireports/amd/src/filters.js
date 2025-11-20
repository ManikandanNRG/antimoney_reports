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
 * Filter handling module for ManiReports.
 *
 * @module     local_manireports/filters
 * @copyright  2024 ManiReports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/notification'], function($, Ajax, Notification) {

    var debounceTimer = null;
    var DEBOUNCE_DELAY = 300; // milliseconds

    /**
     * Initialize filter handling.
     *
     * @param {String} formSelector Form selector
     * @param {Function} callback Callback function when filters change
     */
    var init = function(formSelector, callback) {
        var $form = $(formSelector);
        if (!$form.length) {
            return;
        }

        // Handle filter changes with debouncing.
        $form.find('input, select').on('change keyup', function() {
            debounce(function() {
                var filters = getFilters($form);
                updateURL(filters);
                saveFiltersToSession(filters);
                if (callback) {
                    callback(filters);
                }
            }, DEBOUNCE_DELAY);
        });

        // Load saved filters from session.
        loadFiltersFromSession($form);
    };

    /**
     * Debounce function execution.
     *
     * @param {Function} func Function to debounce
     * @param {Number} delay Delay in milliseconds
     */
    var debounce = function(func, delay) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(func, delay);
    };

    /**
     * Get filter values from form.
     *
     * @param {jQuery} $form Form element
     * @return {Object} Filter values
     */
    var getFilters = function($form) {
        var filters = {};
        $form.find('input, select').each(function() {
            var $input = $(this);
            var name = $input.attr('name');
            var value = $input.val();
            if (name && value) {
                filters[name] = value;
            }
        });
        return filters;
    };

    /**
     * Update URL with filter parameters.
     *
     * @param {Object} filters Filter values
     */
    var updateURL = function(filters) {
        var url = new URL(window.location);
        Object.keys(filters).forEach(function(key) {
            url.searchParams.set(key, filters[key]);
        });
        window.history.pushState({}, '', url);
    };

    /**
     * Save filters to session storage.
     *
     * @param {Object} filters Filter values
     */
    var saveFiltersToSession = function(filters) {
        var key = 'manireports_filters_' + window.location.pathname;
        sessionStorage.setItem(key, JSON.stringify(filters));
    };

    /**
     * Load filters from session storage.
     *
     * @param {jQuery} $form Form element
     */
    var loadFiltersFromSession = function($form) {
        var key = 'manireports_filters_' + window.location.pathname;
        var saved = sessionStorage.getItem(key);
        if (saved) {
            try {
                var filters = JSON.parse(saved);
                Object.keys(filters).forEach(function(name) {
                    $form.find('[name="' + name + '"]').val(filters[name]);
                });
            } catch (e) {
                // Ignore invalid JSON.
            }
        }
    };

    /**
     * Clear all filters.
     *
     * @param {jQuery} $form Form element
     */
    var clearFilters = function($form) {
        $form.find('input, select').val('');
        var key = 'manireports_filters_' + window.location.pathname;
        sessionStorage.removeItem(key);
    };

    /**
     * Add quick filter buttons.
     *
     * @param {String} containerSelector Container selector
     * @param {Array} buttons Array of button configs
     */
    var addQuickFilters = function(containerSelector, buttons) {
        var $container = $(containerSelector);
        if (!$container.length) {
            return;
        }

        buttons.forEach(function(button) {
            var $btn = $('<button>')
                .addClass('btn btn-sm btn-secondary mr-1')
                .text(button.label)
                .on('click', function(e) {
                    e.preventDefault();
                    if (button.callback) {
                        button.callback();
                    }
                });
            $container.append($btn);
        });
    };

    return {
        init: init,
        getFilters: getFilters,
        clearFilters: clearFilters,
        addQuickFilters: addQuickFilters
    };
});
