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
 * Analytics engine for engagement scoring and at-risk detection.
 *
 * @package     local_manireports
 * @copyright   2024 ManiReports
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_manireports\api;

defined('MOODLE_INTERNAL') || die();

/**
 * Analytics engine class.
 */
class analytics_engine {

    /**
     * Calculate engagement score for a user in a course.
     *
     * Engagement score is calculated based on:
     * - Time spent (weight: 40%)
     * - Login frequency (weight: 30%)
     * - Activity completion (weight: 30%)
     *
     * @param int $userid User ID
     * @param int $courseid Course ID
     * @param int $days Number of days to analyze (default: 30)
     * @return array Array with 'score' (0-100) and 'components' breakdown
     */
    public function calculate_engagement_score($userid, $courseid, $days = 30) {
        global $DB;

        $cutoff_date = date('Y-m-d', strtotime("-{$days} days"));
        $now = time();
        $cutoff_timestamp = strtotime($cutoff_date);

        // Component 1: Time spent (40% weight).
        $time_score = $this->calculate_time_score($userid, $courseid, $cutoff_date);

        // Component 2: Login frequency (30% weight).
        $login_score = $this->calculate_login_score($userid, $cutoff_timestamp, $days);

        // Component 3: Activity completion (30% weight).
        $completion_score = $this->calculate_completion_score($userid, $courseid);

        // Calculate weighted total.
        $total_score = ($time_score * 0.4) + ($login_score * 0.3) + ($completion_score * 0.3);

        // Integrate xAPI if available and enabled.
        $xapi = new xapi_integration();
        if ($xapi->is_xapi_available() && $xapi->is_xapi_enabled()) {
            $total_score = $xapi->enhance_engagement_score($userid, $courseid, $total_score);
        }

        return array(
            'score' => round($total_score, 2),
            'components' => array(
                'time_spent' => round($time_score, 2),
                'login_frequency' => round($login_score, 2),
                'activity_completion' => round($completion_score, 2)
            ),
            'period_days' => $days,
            'xapi_enhanced' => ($xapi->is_xapi_available() && $xapi->is_xapi_enabled())
        );
    }

    /**
     * Calculate time spent score (0-100).
     *
     * @param int $userid User ID
     * @param int $courseid Course ID
     * @param string $cutoff_date Cutoff date (Y-m-d format)
     * @return float Score 0-100
     */
    private function calculate_time_score($userid, $courseid, $cutoff_date) {
        global $DB;

        // Get total time spent in the period.
        $sql = "SELECT COALESCE(SUM(duration), 0) as total_time
                  FROM {manireports_time_daily}
                 WHERE userid = :userid
                   AND courseid = :courseid
                   AND date >= :cutoff_date";

        $result = $DB->get_record_sql($sql, array(
            'userid' => $userid,
            'courseid' => $courseid,
            'cutoff_date' => $cutoff_date
        ));

        $total_seconds = $result ? $result->total_time : 0;
        $total_hours = $total_seconds / 3600;

        // Expected: 2 hours per week = 8-10 hours per month.
        // Score: 0 hours = 0, 10+ hours = 100.
        $expected_hours = 10;
        $score = min(100, ($total_hours / $expected_hours) * 100);

        return $score;
    }

    /**
     * Calculate login frequency score (0-100).
     *
     * @param int $userid User ID
     * @param int $cutoff_timestamp Cutoff timestamp
     * @param int $days Number of days in period
     * @return float Score 0-100
     */
    private function calculate_login_score($userid, $cutoff_timestamp, $days) {
        global $DB;

        // Get user's last access.
        $user = $DB->get_record('user', array('id' => $userid), 'lastaccess');

        if (!$user || $user->lastaccess < $cutoff_timestamp) {
            // No login in the period.
            return 0;
        }

        // Calculate days since last login.
        $days_since_login = (time() - $user->lastaccess) / 86400;

        // Expected: Login at least once per week.
        // Score: 0 days = 100, 7+ days = 0.
        if ($days_since_login <= 1) {
            $score = 100;
        } else if ($days_since_login >= 7) {
            $score = 0;
        } else {
            $score = 100 - (($days_since_login / 7) * 100);
        }

        return $score;
    }

    /**
     * Calculate activity completion score (0-100).
     *
     * @param int $userid User ID
     * @param int $courseid Course ID
     * @return float Score 0-100
     */
    private function calculate_completion_score($userid, $courseid) {
        global $DB;

        // Get total activities with completion enabled.
        $total_sql = "SELECT COUNT(DISTINCT cm.id) as total
                        FROM {course_modules} cm
                       WHERE cm.course = :courseid
                         AND cm.completion > 0
                         AND cm.deletioninprogress = 0";

        $total_result = $DB->get_record_sql($total_sql, array('courseid' => $courseid));
        $total_activities = $total_result ? $total_result->total : 0;

        if ($total_activities == 0) {
            // No activities with completion tracking.
            return 100; // Neutral score.
        }

        // Get completed activities.
        $completed_sql = "SELECT COUNT(DISTINCT cmc.coursemoduleid) as completed
                            FROM {course_modules_completion} cmc
                            JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                           WHERE cmc.userid = :userid
                             AND cm.course = :courseid
                             AND cmc.completionstate > 0
                             AND cm.deletioninprogress = 0";

        $completed_result = $DB->get_record_sql($completed_sql, array(
            'userid' => $userid,
            'courseid' => $courseid
        ));
        $completed_activities = $completed_result ? $completed_result->completed : 0;

        // Calculate percentage.
        $score = ($completed_activities / $total_activities) * 100;

        return $score;
    }

    /**
     * Get activity metrics for a user in a course.
     *
     * @param int $userid User ID
     * @param int $courseid Course ID
     * @return array Activity metrics
     */
    public function get_activity_metrics($userid, $courseid) {
        global $DB;

        $metrics = array();

        // Total time spent (last 30 days).
        $cutoff_date = date('Y-m-d', strtotime('-30 days'));
        $time_sql = "SELECT COALESCE(SUM(duration), 0) as total_time
                       FROM {manireports_time_daily}
                      WHERE userid = :userid
                        AND courseid = :courseid
                        AND date >= :cutoff_date";

        $time_result = $DB->get_record_sql($time_sql, array(
            'userid' => $userid,
            'courseid' => $courseid,
            'cutoff_date' => $cutoff_date
        ));

        $metrics['time_spent_seconds'] = $time_result ? $time_result->total_time : 0;
        $metrics['time_spent_hours'] = round($metrics['time_spent_seconds'] / 3600, 2);

        // Last access.
        $user = $DB->get_record('user', array('id' => $userid), 'lastaccess');
        $metrics['last_access'] = $user ? $user->lastaccess : 0;
        $metrics['days_since_login'] = $user && $user->lastaccess > 0 
            ? round((time() - $user->lastaccess) / 86400, 1) 
            : null;

        // Activity completion.
        $total_sql = "SELECT COUNT(DISTINCT cm.id) as total
                        FROM {course_modules} cm
                       WHERE cm.course = :courseid
                         AND cm.completion > 0
                         AND cm.deletioninprogress = 0";

        $total_result = $DB->get_record_sql($total_sql, array('courseid' => $courseid));
        $metrics['total_activities'] = $total_result ? $total_result->total : 0;

        $completed_sql = "SELECT COUNT(DISTINCT cmc.coursemoduleid) as completed
                            FROM {course_modules_completion} cmc
                            JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                           WHERE cmc.userid = :userid
                             AND cm.course = :courseid
                             AND cmc.completionstate > 0
                             AND cm.deletioninprogress = 0";

        $completed_result = $DB->get_record_sql($completed_sql, array(
            'userid' => $userid,
            'courseid' => $courseid
        ));
        $metrics['completed_activities'] = $completed_result ? $completed_result->completed : 0;

        $metrics['completion_percentage'] = $metrics['total_activities'] > 0
            ? round(($metrics['completed_activities'] / $metrics['total_activities']) * 100, 2)
            : 0;

        return $metrics;
    }
}

    /**
     * Detect at-risk learners in a course.
     *
     * A learner is considered at-risk if they meet any of these criteria:
     * - Time spent < minimum threshold
     * - Days since last login > maximum threshold
     * - Completion percentage < minimum threshold
     *
     * @param int $courseid Course ID
     * @param array $thresholds Optional thresholds (overrides config)
     * @return array Array of at-risk learners with risk details
     */
    public function detect_at_risk_learners($courseid, $thresholds = null) {
        global $DB;

        // Get thresholds from config or use defaults.
        if ($thresholds === null) {
            $thresholds = $this->get_risk_thresholds();
        }

        $at_risk_learners = array();

        // Get all enrolled users in the course.
        $sql = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email, u.lastaccess
                  FROM {user} u
                  JOIN {user_enrolments} ue ON ue.userid = u.id
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE e.courseid = :courseid
                   AND u.deleted = 0
                   AND u.suspended = 0
                   AND ue.status = 0";

        $users = $DB->get_records_sql($sql, array('courseid' => $courseid));

        foreach ($users as $user) {
            $risk_factors = $this->evaluate_risk_factors($user->id, $courseid, $thresholds);

            if ($risk_factors['is_at_risk']) {
                $at_risk_learners[] = array(
                    'userid' => $user->id,
                    'firstname' => $user->firstname,
                    'lastname' => $user->lastname,
                    'email' => $user->email,
                    'risk_score' => $risk_factors['risk_score'],
                    'risk_factors' => $risk_factors['factors'],
                    'metrics' => $risk_factors['metrics']
                );
            }
        }

        // Sort by risk score (highest first).
        usort($at_risk_learners, function($a, $b) {
            return $b['risk_score'] - $a['risk_score'];
        });

        return $at_risk_learners;
    }

    /**
     * Evaluate risk factors for a specific user.
     *
     * @param int $userid User ID
     * @param int $courseid Course ID
     * @param array $thresholds Risk thresholds
     * @return array Risk evaluation results
     */
    private function evaluate_risk_factors($userid, $courseid, $thresholds) {
        $metrics = $this->get_activity_metrics($userid, $courseid);
        $factors = array();
        $risk_points = 0;

        // Factor 1: Low time spent.
        $min_hours = $thresholds['min_time_hours'];
        if ($metrics['time_spent_hours'] < $min_hours) {
            $factors[] = 'Low time spent (' . $metrics['time_spent_hours'] . 'h < ' . $min_hours . 'h)';
            $risk_points += 30;
        }

        // Factor 2: No recent login.
        $max_days = $thresholds['max_days_since_login'];
        if ($metrics['days_since_login'] === null || $metrics['days_since_login'] > $max_days) {
            $days_text = $metrics['days_since_login'] === null ? 'Never' : $metrics['days_since_login'] . ' days';
            $factors[] = 'No recent login (' . $days_text . ' > ' . $max_days . ' days)';
            $risk_points += 40;
        }

        // Factor 3: Low completion.
        $min_completion = $thresholds['min_completion_percentage'];
        if ($metrics['completion_percentage'] < $min_completion) {
            $factors[] = 'Low completion (' . $metrics['completion_percentage'] . '% < ' . $min_completion . '%)';
            $risk_points += 30;
        }

        // Determine if at-risk (risk score >= 50).
        $is_at_risk = $risk_points >= 50;

        return array(
            'is_at_risk' => $is_at_risk,
            'risk_score' => min(100, $risk_points),
            'factors' => $factors,
            'metrics' => $metrics
        );
    }

    /**
     * Get risk thresholds from plugin configuration.
     *
     * @return array Risk thresholds
     */
    private function get_risk_thresholds() {
        $config = get_config('local_manireports');

        return array(
            'min_time_hours' => isset($config->atrisk_mintime) ? $config->atrisk_mintime : 5,
            'max_days_since_login' => isset($config->atrisk_maxdays) ? $config->atrisk_maxdays : 7,
            'min_completion_percentage' => isset($config->atrisk_mincompletion) ? $config->atrisk_mincompletion : 30
        );
    }

    /**
     * Get at-risk learner count for a course.
     *
     * @param int $courseid Course ID
     * @return int Number of at-risk learners
     */
    public function get_at_risk_count($courseid) {
        $at_risk_learners = $this->detect_at_risk_learners($courseid);
        return count($at_risk_learners);
    }

    /**
     * Check if a specific user is at-risk in a course.
     *
     * @param int $userid User ID
     * @param int $courseid Course ID
     * @return array Risk evaluation results
     */
    public function is_user_at_risk($userid, $courseid) {
        $thresholds = $this->get_risk_thresholds();
        return $this->evaluate_risk_factors($userid, $courseid, $thresholds);
    }
}
