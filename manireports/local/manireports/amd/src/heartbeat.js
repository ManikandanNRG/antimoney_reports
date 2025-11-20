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
 * Time tracking heartbeat module.
 *
 * @module     local_manireports/heartbeat
 * @copyright  2024 ManiReports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/notification'], function($, Ajax, Notification) {

    /**
     * Heartbeat manager class.
     */
    var HeartbeatManager = function() {
        this.courseid = null;
        this.userid = null;
        this.interval = 25000; // 25 seconds default
        this.timer = null;
        this.lastHeartbeat = 0;
        this.enabled = false;
    };

    /**
     * Initialize heartbeat tracking.
     *
     * @param {int} courseid Course ID
     * @param {int} userid User ID
     * @param {int} interval Heartbeat interval in seconds
     */
    HeartbeatManager.prototype.init = function(courseid, userid, interval) {
        this.courseid = courseid;
        this.userid = userid;
        this.interval = (interval || 25) * 1000; // Convert to milliseconds
        this.enabled = true;

        // Add randomization to prevent server load spikes (±5 seconds)
        this.interval += Math.floor(Math.random() * 10000) - 5000;

        // Load last heartbeat from sessionStorage
        var stored = sessionStorage.getItem('manireports_last_heartbeat');
        if (stored) {
            this.lastHeartbeat = parseInt(stored);
        }

        // Start heartbeat
        this.start();

        // Send initial heartbeat
        this.sendHeartbeat();

        // Handle page visibility changes
        var self = this;
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                self.stop();
            } else {
                self.start();
                self.sendHeartbeat();
            }
        });

        // Handle page unload
        window.addEventListener('beforeunload', function() {
            self.stop();
        });
    };

    /**
     * Start heartbeat timer.
     */
    HeartbeatManager.prototype.start = function() {
        if (!this.enabled || this.timer) {
            return;
        }

        var self = this;
        this.timer = setInterval(function() {
            self.sendHeartbeat();
        }, this.interval);
    };

    /**
     * Stop heartbeat timer.
     */
    HeartbeatManager.prototype.stop = function() {
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
    };

    /**
     * Send heartbeat to server.
     */
    HeartbeatManager.prototype.sendHeartbeat = function() {
        if (!this.enabled) {
            return;
        }

        var now = Math.floor(Date.now() / 1000);

        // Don't send if last heartbeat was less than 10 seconds ago
        if (now - this.lastHeartbeat < 10) {
            return;
        }

        var self = this;

        // Use direct AJAX call to heartbeat endpoint
        $.ajax({
            url: M.cfg.wwwroot + '/local/manireports/ui/ajax/heartbeat.php',
            method: 'POST',
            data: {
                courseid: this.courseid,
                userid: this.userid,
                timestamp: now,
                sesskey: M.cfg.sesskey
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    self.lastHeartbeat = now;
                    sessionStorage.setItem('manireports_last_heartbeat', now);
                }
            },
            error: function(xhr, status, error) {
                // Silently fail - don't disrupt user experience
                // eslint-disable-next-line no-console
                console.warn('Heartbeat failed:', error);
            }
        });
    };

    /**
     * Disable heartbeat tracking.
     */
    HeartbeatManager.prototype.disable = function() {
        this.enabled = false;
        this.stop();
    };

    return {
        /**
         * Initialize heartbeat tracking for a course.
         *
         * @param {int} courseid Course ID
         * @param {int} userid User ID
         * @param {int} interval Heartbeat interval in seconds
         */
        init: function(courseid, userid, interval) {
            var manager = new HeartbeatManager();
            manager.init(courseid, userid, interval);
            return manager;
        }
    };
});
