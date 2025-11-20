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
 * xAPI Integration for ManiReports
 *
 * Integrates xAPI statement data and video analytics into engagement metrics.
 * Gracefully handles absence of xAPI plugins.
 *
 * @package    local_manireports
 * @copyright  2024 ManiReports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_manireports\api;

defined('MOODLE_INTERNAL') || die();

/**
 * xAPI Integration class
 *
 * Provides methods to check for xAPI availability, query xAPI statements,
 * and extract engagement metrics from xAPI data.
 */
class xapi_integration {

    /**
     * Check if xAPI logstore plugin is installed and enabled
     *
     * @return bool True if xAPI is available
     */
    public function is_xapi_available() {
        global $CFG;

        // Check if xAPI logstore plugin exists.
        $xapipath = $CFG->dirroot . '/admin/tool/log/store/xapi';
        if (!file_exists($xapipath)) {
            return false;
        }

        // Check if plugin is enabled.
        $enabledstores = get_config('tool_log', 'enabled_stores');
        if (empty($enabledstores)) {
            return false;
        }

        $stores = explode(',', $enabledstores);
        return in_array('logstore_xapi', $stores);
    }

    /**
     * Check if xAPI integration is enabled in plugin settings
     *
     * @return bool True if enabled
     */
    public function is_xapi_enabled() {
        return (bool) get_config('local_manireports', 'enable_xapi_integration');
    }

    /**
     * Get xAPI statements for a user in a course
     *
     * @param int $userid User ID
     * @param int $courseid Course ID
     * @param int $startdate Start date timestamp (optional)
     * @param int $enddate End date timestamp (optional)
     * @return array Array of xAPI statements
     */
    public function get_xapi_statements($userid, $courseid, $startdate = null, $enddate = null) {
        global $DB;

        if (!$this->is_xapi_available() || !$this->is_xapi_enabled()) {
            return [];
        }

        try {
            // Build query conditions.
            $conditions = [
                'userid' => $userid,
                'courseid' => $courseid,
            ];

            $sql = "SELECT *
                      FROM {logstore_xapi_log}
                     WHERE userid = :userid
                       AND courseid = :courseid";

            if ($startdate) {
                $sql .= " AND timecreated >= :startdate";
                $conditions['startdate'] = $startdate;
            }

            if ($enddate) {
                $sql .= " AND timecreated <= :enddate";
                $conditions['enddate'] = $enddate;
            }

            $sql .= " ORDER BY timecreated DESC";

            return $DB->get_records_sql($sql, $conditions);

        } catch (\dml_exception $e) {
            debugging('Error fetching xAPI statements: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }
    }

    /**
     * Extract video watch time from xAPI statements
     *
     * Looks for video-related xAPI verbs and extracts duration data.
     *
     * @param array $statements Array of xAPI statements
     * @return int Total video watch time in seconds
     */
    public function extract_video_watch_time($statements) {
        $totaltime = 0;

        foreach ($statements as $statement) {
            if (empty($statement->statement)) {
                continue;
            }

            $data = json_decode($statement->statement, true);
            if (!$data) {
                continue;
            }

            // Check for video-related verbs.
            $verb = $data['verb']['id'] ?? '';
            $videoVerbs = [
                'http://adlnet.gov/expapi/verbs/played',
                'http://adlnet.gov/expapi/verbs/completed',
                'https://w3id.org/xapi/video/verbs/played',
                'https://w3id.org/xapi/video/verbs/completed',
            ];

            if (in_array($verb, $videoVerbs)) {
                // Extract duration from result.
                if (isset($data['result']['duration'])) {
                    $duration = $this->parse_iso8601_duration($data['result']['duration']);
                    $totaltime += $duration;
                }

                // Extract duration from extensions.
                if (isset($data['result']['extensions'])) {
                    foreach ($data['result']['extensions'] as $key => $value) {
                        if (strpos($key, 'duration') !== false || strpos($key, 'time') !== false) {
                            if (is_numeric($value)) {
                                $totaltime += (int) $value;
                            }
                        }
                    }
                }
            }
        }

        return $totaltime;
    }

    /**
     * Get video engagement metrics for a user
     *
     * @param int $userid User ID
     * @param int $courseid Course ID
     * @param int $days Number of days to look back
     * @return array Video engagement metrics
     */
    public function get_video_engagement($userid, $courseid, $days = 30) {
        $startdate = time() - ($days * 24 * 60 * 60);
        $statements = $this->get_xapi_statements($userid, $courseid, $startdate);

        $metrics = [
            'total_watch_time' => 0,
            'videos_started' => 0,
            'videos_completed' => 0,
            'completion_rate' => 0,
        ];

        $startedVideos = [];
        $completedVideos = [];

        foreach ($statements as $statement) {
            if (empty($statement->statement)) {
                continue;
            }

            $data = json_decode($statement->statement, true);
            if (!$data) {
                continue;
            }

            $verb = $data['verb']['id'] ?? '';
            $objectid = $data['object']['id'] ?? '';

            // Track started videos.
            if (strpos($verb, 'played') !== false) {
                $startedVideos[$objectid] = true;
            }

            // Track completed videos.
            if (strpos($verb, 'completed') !== false) {
                $completedVideos[$objectid] = true;
            }
        }

        $metrics['total_watch_time'] = $this->extract_video_watch_time($statements);
        $metrics['videos_started'] = count($startedVideos);
        $metrics['videos_completed'] = count($completedVideos);

        if ($metrics['videos_started'] > 0) {
            $metrics['completion_rate'] = round(
                ($metrics['videos_completed'] / $metrics['videos_started']) * 100,
                2
            );
        }

        return $metrics;
    }

    /**
     * Get xAPI-based engagement score
     *
     * Calculates engagement score based on xAPI activity.
     *
     * @param int $userid User ID
     * @param int $courseid Course ID
     * @param int $days Number of days to look back
     * @return float Engagement score (0-100)
     */
    public function get_xapi_engagement_score($userid, $courseid, $days = 30) {
        if (!$this->is_xapi_available() || !$this->is_xapi_enabled()) {
            return 0;
        }

        $startdate = time() - ($days * 24 * 60 * 60);
        $statements = $this->get_xapi_statements($userid, $courseid, $startdate);

        if (empty($statements)) {
            return 0;
        }

        // Calculate score based on:
        // - Number of statements (activity level).
        // - Variety of verbs (engagement diversity).
        // - Video completion rate.
        $statementcount = count($statements);
        $uniqueverbs = [];
        $videoMetrics = $this->get_video_engagement($userid, $courseid, $days);

        foreach ($statements as $statement) {
            if (empty($statement->statement)) {
                continue;
            }

            $data = json_decode($statement->statement, true);
            if (!$data) {
                continue;
            }

            $verb = $data['verb']['id'] ?? '';
            if ($verb) {
                $uniqueverbs[$verb] = true;
            }
        }

        // Scoring formula.
        $activityScore = min(($statementcount / 100) * 40, 40); // Max 40 points for activity.
        $diversityScore = min((count($uniqueverbs) / 10) * 30, 30); // Max 30 points for diversity.
        $videoScore = ($videoMetrics['completion_rate'] / 100) * 30; // Max 30 points for video completion.

        $totalscore = $activityScore + $diversityScore + $videoScore;

        return round($totalscore, 2);
    }

    /**
     * Get xAPI dashboard widget data
     *
     * @param int $userid User ID
     * @param int $courseid Course ID (0 for all courses)
     * @return array Widget data
     */
    public function get_xapi_widget_data($userid, $courseid = 0) {
        if (!$this->is_xapi_available() || !$this->is_xapi_enabled()) {
            return [
                'available' => false,
                'message' => get_string('xapi:notavailable', 'local_manireports'),
            ];
        }

        $data = [
            'available' => true,
            'engagement_score' => 0,
            'video_metrics' => [],
            'activity_count' => 0,
            'unique_verbs' => 0,
        ];

        if ($courseid > 0) {
            // Single course metrics.
            $data['engagement_score'] = $this->get_xapi_engagement_score($userid, $courseid);
            $data['video_metrics'] = $this->get_video_engagement($userid, $courseid);

            $statements = $this->get_xapi_statements($userid, $courseid);
            $data['activity_count'] = count($statements);

            $uniqueverbs = [];
            foreach ($statements as $statement) {
                if (empty($statement->statement)) {
                    continue;
                }
                $stmtdata = json_decode($statement->statement, true);
                if ($stmtdata && isset($stmtdata['verb']['id'])) {
                    $uniqueverbs[$stmtdata['verb']['id']] = true;
                }
            }
            $data['unique_verbs'] = count($uniqueverbs);

        } else {
            // All courses - aggregate metrics.
            global $DB;

            try {
                $courses = $DB->get_records_sql(
                    "SELECT DISTINCT courseid
                       FROM {logstore_xapi_log}
                      WHERE userid = :userid",
                    ['userid' => $userid]
                );

                $totalScore = 0;
                $totalVideos = 0;
                $totalWatchTime = 0;
                $totalActivity = 0;
                $allVerbs = [];

                foreach ($courses as $course) {
                    $score = $this->get_xapi_engagement_score($userid, $course->courseid);
                    $totalScore += $score;

                    $videoMetrics = $this->get_video_engagement($userid, $course->courseid);
                    $totalVideos += $videoMetrics['videos_completed'];
                    $totalWatchTime += $videoMetrics['total_watch_time'];

                    $statements = $this->get_xapi_statements($userid, $course->courseid);
                    $totalActivity += count($statements);

                    foreach ($statements as $statement) {
                        if (empty($statement->statement)) {
                            continue;
                        }
                        $stmtdata = json_decode($statement->statement, true);
                        if ($stmtdata && isset($stmtdata['verb']['id'])) {
                            $allVerbs[$stmtdata['verb']['id']] = true;
                        }
                    }
                }

                $coursecount = count($courses);
                $data['engagement_score'] = $coursecount > 0 ? round($totalScore / $coursecount, 2) : 0;
                $data['video_metrics'] = [
                    'videos_completed' => $totalVideos,
                    'total_watch_time' => $totalWatchTime,
                ];
                $data['activity_count'] = $totalActivity;
                $data['unique_verbs'] = count($allVerbs);

            } catch (\dml_exception $e) {
                debugging('Error fetching xAPI aggregate data: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        return $data;
    }

    /**
     * Parse ISO 8601 duration string to seconds
     *
     * @param string $duration ISO 8601 duration (e.g., "PT1H30M")
     * @return int Duration in seconds
     */
    private function parse_iso8601_duration($duration) {
        if (empty($duration)) {
            return 0;
        }

        $interval = new \DateInterval($duration);

        $seconds = 0;
        $seconds += $interval->y * 365 * 24 * 60 * 60;
        $seconds += $interval->m * 30 * 24 * 60 * 60;
        $seconds += $interval->d * 24 * 60 * 60;
        $seconds += $interval->h * 60 * 60;
        $seconds += $interval->i * 60;
        $seconds += $interval->s;

        return $seconds;
    }

    /**
     * Integrate xAPI metrics into existing engagement calculation
     *
     * @param int $userid User ID
     * @param int $courseid Course ID
     * @param float $baseEngagementScore Base engagement score from other sources
     * @return float Enhanced engagement score
     */
    public function enhance_engagement_score($userid, $courseid, $baseEngagementScore) {
        if (!$this->is_xapi_available() || !$this->is_xapi_enabled()) {
            return $baseEngagementScore;
        }

        $xapiScore = $this->get_xapi_engagement_score($userid, $courseid);

        // Weight: 70% base score, 30% xAPI score.
        $weight = get_config('local_manireports', 'xapi_score_weight');
        if ($weight === false) {
            $weight = 0.3; // Default 30%.
        } else {
            $weight = floatval($weight);
        }

        $enhancedScore = ($baseEngagementScore * (1 - $weight)) + ($xapiScore * $weight);

        return round($enhancedScore, 2);
    }
}
