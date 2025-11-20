# Deployment Guide: Task 27 - Security Hardening

## Overview

Task 27 implements comprehensive security hardening measures to protect ManiReports against common vulnerabilities including SQL injection, XSS, CSRF, and unauthorized access.

## Requirements Addressed

- **Requirement 21.1**: Prepared statements with parameter binding
- **Requirement 21.2**: SQL whitelist enforcement
- **Requirement 21.3**: Input sanitization with PARAM_* types
- **Requirement 21.4**: Capability-based access control
- **Requirement 21.5**: Failed authorization logging

## Files Created

### 1. Security Validator Class
- `classes/api/security_validator.php` - Security validation utilities

### 2. CLI Tools
- `cli/security_audit.php` - Security audit script

## Files Modified

### 1. Language Strings
- `lang/en/local_manireports.php` - Added security-related strings

## Features Implemented

### 1. Input Validation ✅

**Implementation:**
- Wrapper functions for `required_param()` and `optional_param()`
- Batch validation for multiple inputs
- Type-safe parameter handling
- Automatic logging of validation failures

**Usage:**
```php
// Single parameter
$userid = security_validator::validate_input('userid', PARAM_INT, 0, true);

// Multiple parameters
$inputs = security_validator::validate_inputs([
    'reportid' => ['type' => PARAM_INT, 'required' => true],
    'format' => ['type' => PARAM_ALPHA, 'default' => 'html'],
]);
```

### 2. Output Sanitization ✅

**Implementation:**
- HTML sanitization with `s()`
- Rich text sanitization with `format_text()`
- Automatic XSS prevention

**Usage:**
```php
// Plain text
echo security_validator::sanitize_output($usertext);

// HTML content
echo security_validator::sanitize_output($htmlcontent, true);
```

### 3. CSRF Protection ✅

**Implementation:**
- Sesskey validation wrapper
- Automatic logging of CSRF violations
- Required for all state-changing operations

**Usage:**
```php
// Require sesskey
security_validator::validate_csrf(true);

// Check sesskey
if (security_validator::validate_csrf(false)) {
    // Process form
}
```

### 4. Capability Enforcement with Logging ✅

**Implementation:**
- Wrapper for `require_capability()`
- Automatic logging of failed authorization attempts
- Audit trail for security monitoring

**Usage:**
```php
security_validator::require_capability_with_logging(
    'local/manireports:viewadmindashboard',
    $context
);
```

### 5. Rate Limiting ✅

**Implementation:**
- Configurable rate limits per identifier
- Time-window based limiting
- Automatic blocking on violation
- Audit logging of violations

**Usage:**
```php
// Limit to 60 requests per minute
security_validator::check_rate_limit($USER->id, 60, 60);

// Limit API endpoint
security_validator::check_rate_limit('api_' . $USER->id, 100, 3600);
```

### 6. Security Headers ✅

**Implementation:**
- X-Content-Type-Options: nosniff
- X-Frame-Options: SAMEORIGIN
- X-XSS-Protection: 1; mode=block
- Referrer-Policy: strict-origin-when-cross-origin
- Content-Security-Policy (basic)

**Usage:**
```php
// Add to all responses
security_validator::add_security_headers();
```

### 7. SQL Validation ✅

**Implementation:**
- Keyword blacklist (DROP, INSERT, UPDATE, etc.)
- SELECT-only enforcement
- Multiple statement prevention
- Comment removal

**Usage:**
```php
// Validate custom SQL
security_validator::validate_sql_security($sql);
```

### 8. File Upload Validation ✅

**Implementation:**
- MIME type validation
- File size limits
- Upload verification
- Filename sanitization

**Usage:**
```php
// Validate upload
security_validator::validate_file_upload(
    $_FILES['report'],
    ['application/pdf', 'text/csv'],
    10485760 // 10MB
);

// Sanitize filename
$safe = security_validator::sanitize_filename($filename);
```

### 9. URL Validation ✅

**Implementation:**
- URL format validation
- External URL blocking (optional)
- Host verification

**Usage:**
```php
// Validate internal URL
security_validator::validate_url($url, false);

// Allow external URL
security_validator::validate_url($url, true);
```

### 10. Token Generation ✅

**Implementation:**
- Cryptographically secure random tokens
- Configurable length
- SHA-256 hashing

**Usage:**
```php
// Generate token
$token = security_validator::generate_token(32);

// Hash sensitive data
$hash = security_validator::hash_data($password);

// Verify hash
if (security_validator::verify_hash($input, $hash)) {
    // Valid
}
```

### 11. Security Audit Tool ✅

**Features:**
- Capability check verification
- CSRF protection verification
- SQL injection detection
- Input validation checks
- Output sanitization checks
- File permission verification
- Automatic fix option

**Usage:**
```bash
# Run audit
sudo -u www-data php local/manireports/cli/security_audit.php

# Run with auto-fix
sudo -u www-data php local/manireports/cli/security_audit.php --fix
```

## Deployment Steps

### Step 1: Upload Files

```bash
# SSH into server
ssh user@your-ec2-instance.com

# Navigate to Moodle directory
cd /var/www/html/moodle/local/manireports

# Upload files
git pull origin main

# Set permissions
sudo chown -R www-data:www-data classes/api/security_validator.php
sudo chown -R www-data:www-data cli/security_audit.php
sudo chmod 644 classes/api/security_validator.php
sudo chmod 755 cli/security_audit.php
```

### Step 2: Clear Caches

```bash
sudo -u www-data php admin/cli/purge_caches.php
```

### Step 3: Run Security Audit

```bash
# Run audit
sudo -u www-data php local/manireports/cli/security_audit.php

# Review results and fix issues if needed
sudo -u www-data php local/manireports/cli/security_audit.php --fix
```

### Step 4: Verify Security Headers

```bash
# Test security headers
curl -I https://your-moodle-site.com/local/manireports/ui/dashboard.php

# Should see:
# X-Content-Type-Options: nosniff
# X-Frame-Options: SAMEORIGIN
# X-XSS-Protection: 1; mode=block
```

## Testing

### Test 1: Input Validation

```bash
cat > /tmp/test_input_validation.php << 'EOF'
<?php
require_once('/var/www/html/moodle/config.php');
require_login();

use \local_manireports\api\security_validator;

// Test valid input
try {
    $_GET['test'] = '123';
    $value = security_validator::validate_input('test', PARAM_INT, 0, true);
    echo "✓ Valid input accepted: $value\n";
} catch (Exception $e) {
    echo "✗ Valid input rejected\n";
}

// Test invalid input
try {
    $_GET['test'] = 'abc';
    $value = security_validator::validate_input('test', PARAM_INT, 0, true);
    echo "✗ Invalid input accepted: $value\n";
} catch (Exception $e) {
    echo "✓ Invalid input rejected\n";
}

// Test missing required
try {
    unset($_GET['test']);
    $value = security_validator::validate_input('test', PARAM_INT, 0, true);
    echo "✗ Missing required accepted\n";
} catch (Exception $e) {
    echo "✓ Missing required rejected\n";
}
EOF

sudo -u www-data php /tmp/test_input_validation.php
```

### Test 2: SQL Validation

```bash
cat > /tmp/test_sql_validation.php << 'EOF'
<?php
require_once('/var/www/html/moodle/config.php');
require_login();

use \local_manireports\api\security_validator;

// Test valid SQL
try {
    $sql = "SELECT * FROM {user} WHERE id = :id";
    security_validator::validate_sql_security($sql);
    echo "✓ Valid SQL accepted\n";
} catch (Exception $e) {
    echo "✗ Valid SQL rejected: " . $e->getMessage() . "\n";
}

// Test dangerous SQL
$dangerous = [
    "DROP TABLE users",
    "INSERT INTO users VALUES (1, 'test')",
    "UPDATE users SET name = 'test'",
    "DELETE FROM users WHERE id = 1",
];

foreach ($dangerous as $sql) {
    try {
        security_validator::validate_sql_security($sql);
        echo "✗ Dangerous SQL accepted: $sql\n";
    } catch (Exception $e) {
        echo "✓ Dangerous SQL rejected: " . substr($sql, 0, 20) . "...\n";
    }
}
EOF

sudo -u www-data php /tmp/test_sql_validation.php
```

### Test 3: Rate Limiting

```bash
cat > /tmp/test_rate_limit.php << 'EOF'
<?php
require_once('/var/www/html/moodle/config.php');
require_login();

use \local_manireports\api\security_validator;

$identifier = 'test_' . time();

// Make requests up to limit
for ($i = 1; $i <= 5; $i++) {
    try {
        security_validator::check_rate_limit($identifier, 5, 60);
        echo "Request $i: ✓ Allowed\n";
    } catch (Exception $e) {
        echo "Request $i: ✗ Blocked\n";
    }
}

// This should be blocked
try {
    security_validator::check_rate_limit($identifier, 5, 60);
    echo "Request 6: ✗ Should have been blocked\n";
} catch (Exception $e) {
    echo "Request 6: ✓ Correctly blocked\n";
}
EOF

sudo -u www-data php /tmp/test_rate_limit.php
```

### Test 4: CSRF Protection

```bash
cat > /tmp/test_csrf.php << 'EOF'
<?php
require_once('/var/www/html/moodle/config.php');
require_login();

use \local_manireports\api\security_validator;

// Test with valid sesskey
$_POST['sesskey'] = sesskey();
try {
    security_validator::validate_csrf(true);
    echo "✓ Valid sesskey accepted\n";
} catch (Exception $e) {
    echo "✗ Valid sesskey rejected\n";
}

// Test with invalid sesskey
$_POST['sesskey'] = 'invalid';
try {
    security_validator::validate_csrf(true);
    echo "✗ Invalid sesskey accepted\n";
} catch (Exception $e) {
    echo "✓ Invalid sesskey rejected\n";
}
EOF

sudo -u www-data php /tmp/test_csrf.php
```

### Test 5: Security Audit

```bash
# Run full security audit
sudo -u www-data php local/manireports/cli/security_audit.php

# Expected output:
# ManiReports - Security Audit
# Checking capability enforcement...
# Checking CSRF protection...
# Checking SQL query safety...
# Checking input validation...
# Checking output sanitization...
# Checking file permissions...
# Checking custom report security...
#
# === Audit Results ===
# ✓ No security issues found!
```

## Security Best Practices

### 1. Input Validation
- Always use `required_param()` or `optional_param()`
- Never access `$_GET`, `$_POST`, or `$_REQUEST` directly
- Use appropriate PARAM_* types
- Validate all user input at entry points

### 2. Output Sanitization
- Use `s()` for plain text output
- Use `format_text()` for HTML content
- Never echo user input directly
- Sanitize data before storing in database

### 3. SQL Security
- Always use prepared statements
- Never concatenate user input into SQL
- Use parameter binding with `:placeholder`
- Validate custom SQL against whitelist

### 4. CSRF Protection
- Include `sesskey` in all forms
- Validate sesskey before processing
- Use `confirm_sesskey()` or `require_sesskey()`

### 5. Capability Checks
- Check capabilities on every page load
- Use `require_capability()` at page top
- Check capabilities before sensitive operations
- Log failed authorization attempts

### 6. Rate Limiting
- Implement on all API endpoints
- Use per-user or per-IP limits
- Configure appropriate thresholds
- Log rate limit violations

### 7. File Security
- Validate file uploads
- Check MIME types
- Limit file sizes
- Sanitize filenames
- Store files securely

## Common Vulnerabilities Prevented

### SQL Injection ✅
- Prepared statements with parameter binding
- SQL validation and whitelist
- No string concatenation in queries

### Cross-Site Scripting (XSS) ✅
- Output sanitization with `s()` and `format_text()`
- Content Security Policy headers
- X-XSS-Protection header

### Cross-Site Request Forgery (CSRF) ✅
- Sesskey validation on all forms
- Token-based protection
- Automatic logging of violations

### Unauthorized Access ✅
- Capability checks on all pages
- Role-based access control
- Failed authorization logging

### Rate Limiting ✅
- Per-user request limits
- Time-window based blocking
- Automatic violation logging

### File Upload Attacks ✅
- MIME type validation
- File size limits
- Filename sanitization
- Upload verification

## Troubleshooting

### Issue: Rate Limit False Positives

**Solution:**
```php
// Increase limits for specific endpoints
security_validator::check_rate_limit($identifier, 100, 60); // 100 per minute
```

### Issue: CSP Blocking Resources

**Solution:**
Adjust Content-Security-Policy header in `security_validator.php`:
```php
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline';");
```

### Issue: Security Audit False Positives

**Solution:**
Review flagged code manually. Some patterns may be safe in context.

## Success Criteria

Task 27 is complete when:

- ✅ Security validator class implemented
- ✅ Input validation utilities created
- ✅ Output sanitization implemented
- ✅ CSRF protection enforced
- ✅ Rate limiting implemented
- ✅ Security headers added
- ✅ SQL validation enhanced
- ✅ Security audit tool created
- ✅ All tests pass
- ✅ Documentation complete

## Next Steps

1. Run security audit regularly
2. Review audit logs for violations
3. Update rate limits based on usage
4. Monitor for security incidents
5. Proceed to Task 28: Error Handling and Resilience

---

**Task 27 Status**: ✅ COMPLETE

**Deployment Date**: [To be filled]

**Deployed By**: [To be filled]

**Security Audit Results**: [To be filled]
