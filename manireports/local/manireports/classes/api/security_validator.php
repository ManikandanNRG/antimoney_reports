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
 * Security validation utilities for ManiReports
 *
 * Provides input validation, output sanitization, and security checks.
 *
 * @package    local_manireports
 * @copyright  2024 ManiReports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_manireports\api;

defined('MOODLE_INTERNAL') || die();

/**
 * Security validation and sanitization utilities
 */
class security_validator {

    /** @var array Rate limiting storage */
    private static $rate_limits = [];

    /**
     * Validate and sanitize user input
     *
     * @param string $name Parameter name
     * @param int $type PARAM_* constant
     * @param mixed $default Default value
     * @param bool $required Whether parameter is required
     * @return mixed Sanitized value
     * @throws \moodle_exception If required parameter is missing
     */
    public static function validate_input($name, $type, $default = null, $required = false) {
        if ($required) {
            return required_param($name, $type);
        } else {
            return optional_param($name, $default, $type);
        }
    }

    /**
     * Validate array of inputs
     *
     * @param array $inputs Array of [name => [type, default, required]]
     * @return array Sanitized values
     */
    public static function validate_inputs($inputs) {
        $validated = [];
        
        foreach ($inputs as $name => $config) {
            $type = $config['type'];
            $default = $config['default'] ?? null;
            $required = $config['required'] ?? false;
            
            $validated[$name] = self::validate_input($name, $type, $default, $required);
        }
        
        return $validated;
    }

    /**
     * Sanitize output for display
     *
     * @param string $text Text to sanitize
     * @param bool $allowhtml Whether to allow HTML
     * @return string Sanitized text
     */
    public static function sanitize_output($text, $allowhtml = false) {
        if ($allowhtml) {
            return format_text($text, FORMAT_HTML, ['noclean' => false]);
        } else {
            return s($text);
        }
    }

    /**
     * Validate CSRF token
     *
     * @param bool $required Whether sesskey is required
     * @return bool True if valid
     * @throws \moodle_exception If sesskey is invalid
     */
    public static function validate_csrf($required = true) {
        if ($required) {
            require_sesskey();
        } else {
            return confirm_sesskey();
        }
        return true;
    }

    /**
     * Check capability and log failures
     *
     * @param string $capability Capability to check
     * @param \context $context Context to check in
     * @param int $userid User ID (0 for current user)
     * @param bool $doanything Whether to check doanything
     * @return bool True if has capability
     * @throws \required_capability_exception If capability check fails
     */
    public static function require_capability_with_logging($capability, $context, $userid = 0, $doanything = true) {
        global $USER;
        
        if ($userid === 0) {
            $userid = $USER->id;
        }
        
        try {
            require_capability($capability, $context, $userid, $doanything);
            return true;
        } catch (\required_capability_exception $e) {
            // Log failed authorization attempt.
            $logger = new audit_logger();
            $logger->log_action('failed_authorization', 'security', 0, [
                'capability' => $capability,
                'userid' => $userid,
                'context' => $context->id,
            ]);
            
            throw $e;
        }
    }

    /**
     * Implement rate limiting for API endpoints
     *
     * @param string $identifier Unique identifier (e.g., userid, IP)
     * @param int $maxrequests Maximum requests allowed
     * @param int $timewindow Time window in seconds
     * @return bool True if request is allowed
     * @throws \moodle_exception If rate limit exceeded
     */
    public static function check_rate_limit($identifier, $maxrequests = 60, $timewindow = 60) {
        $now = time();
        $key = 'ratelimit_' . $identifier;
        
        // Initialize if not exists.
        if (!isset(self::$rate_limits[$key])) {
            self::$rate_limits[$key] = [
                'requests' => [],
                'blocked_until' => 0,
            ];
        }
        
        $limit = &self::$rate_limits[$key];
        
        // Check if currently blocked.
        if ($limit['blocked_until'] > $now) {
            throw new \moodle_exception('ratelimitexceeded', 'local_manireports', '', null,
                'Rate limit exceeded. Try again in ' . ($limit['blocked_until'] - $now) . ' seconds.');
        }
        
        // Remove old requests outside time window.
        $limit['requests'] = array_filter($limit['requests'], function($timestamp) use ($now, $timewindow) {
            return $timestamp > ($now - $timewindow);
        });
        
        // Check if limit exceeded.
        if (count($limit['requests']) >= $maxrequests) {
            $limit['blocked_until'] = $now + $timewindow;
            
            // Log rate limit violation.
            $logger = new audit_logger();
            $logger->log_action('rate_limit_exceeded', 'security', 0, [
                'identifier' => $identifier,
                'requests' => count($limit['requests']),
                'max_requests' => $maxrequests,
                'time_window' => $timewindow,
            ]);
            
            throw new \moodle_exception('ratelimitexceeded', 'local_manireports');
        }
        
        // Add current request.
        $limit['requests'][] = $now;
        
        return true;
    }

    /**
     * Add security headers to response
     *
     * @return void
     */
    public static function add_security_headers() {
        // X-Content-Type-Options.
        header('X-Content-Type-Options: nosniff');
        
        // X-Frame-Options.
        header('X-Frame-Options: SAMEORIGIN');
        
        // X-XSS-Protection.
        header('X-XSS-Protection: 1; mode=block');
        
        // Referrer-Policy.
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Content-Security-Policy (basic).
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline';");
    }

    /**
     * Validate SQL query for security
     *
     * @param string $sql SQL query
     * @return bool True if valid
     * @throws \moodle_exception If SQL is invalid
     */
    public static function validate_sql_security($sql) {
        // Remove comments.
        $sql = preg_replace('/--[^\n]*\n/', "\n", $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        
        // Check for dangerous keywords.
        $dangerous = [
            'DROP', 'CREATE', 'ALTER', 'TRUNCATE',
            'INSERT', 'UPDATE', 'DELETE',
            'GRANT', 'REVOKE', 'EXEC', 'EXECUTE',
            'LOAD_FILE', 'INTO OUTFILE', 'INTO DUMPFILE',
        ];
        
        foreach ($dangerous as $keyword) {
            if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/i', $sql)) {
                throw new \moodle_exception('dangerousql', 'local_manireports', '', $keyword);
            }
        }
        
        // Must start with SELECT.
        if (!preg_match('/^\s*SELECT\b/i', $sql)) {
            throw new \moodle_exception('sqlmustselect', 'local_manireports');
        }
        
        // Check for multiple statements.
        if (preg_match('/;\s*\w+/i', $sql)) {
            throw new \moodle_exception('multiplestatements', 'local_manireports');
        }
        
        return true;
    }

    /**
     * Validate file upload
     *
     * @param array $file File from $_FILES
     * @param array $allowedtypes Allowed MIME types
     * @param int $maxsize Maximum file size in bytes
     * @return bool True if valid
     * @throws \moodle_exception If file is invalid
     */
    public static function validate_file_upload($file, $allowedtypes = [], $maxsize = 10485760) {
        // Check if file was uploaded.
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new \moodle_exception('invalidfile', 'local_manireports');
        }
        
        // Check file size.
        if ($file['size'] > $maxsize) {
            throw new \moodle_exception('filetoolarge', 'local_manireports', '', 
                ['size' => $file['size'], 'max' => $maxsize]);
        }
        
        // Check MIME type.
        if (!empty($allowedtypes)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimetype = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimetype, $allowedtypes)) {
                throw new \moodle_exception('invalidfiletype', 'local_manireports', '', $mimetype);
            }
        }
        
        return true;
    }

    /**
     * Sanitize filename
     *
     * @param string $filename Filename to sanitize
     * @return string Sanitized filename
     */
    public static function sanitize_filename($filename) {
        // Remove path components.
        $filename = basename($filename);
        
        // Remove dangerous characters.
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Limit length.
        if (strlen($filename) > 255) {
            $filename = substr($filename, 0, 255);
        }
        
        return $filename;
    }

    /**
     * Validate URL
     *
     * @param string $url URL to validate
     * @param bool $allowexternal Whether to allow external URLs
     * @return bool True if valid
     * @throws \moodle_exception If URL is invalid
     */
    public static function validate_url($url, $allowexternal = false) {
        global $CFG;
        
        // Basic URL validation.
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \moodle_exception('invalidurl', 'local_manireports');
        }
        
        // Check if external URL is allowed.
        if (!$allowexternal) {
            $parsed = parse_url($url);
            $siteurl = parse_url($CFG->wwwroot);
            
            if ($parsed['host'] !== $siteurl['host']) {
                throw new \moodle_exception('externalurlnotallowed', 'local_manireports');
            }
        }
        
        return true;
    }

    /**
     * Generate secure random token
     *
     * @param int $length Token length
     * @return string Random token
     */
    public static function generate_token($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Hash sensitive data
     *
     * @param string $data Data to hash
     * @return string Hashed data
     */
    public static function hash_data($data) {
        return hash('sha256', $data);
    }

    /**
     * Verify hashed data
     *
     * @param string $data Original data
     * @param string $hash Hash to verify against
     * @return bool True if matches
     */
    public static function verify_hash($data, $hash) {
        return hash_equals($hash, self::hash_data($data));
    }
}
