# FP Experiences - Security Testing Checklist

Comprehensive security testing checklist for FP Experiences plugin.

## 1. Nonce Validation

### Form Nonces
- [ ] All admin forms include nonce fields
- [ ] All frontend forms include nonce fields
- [ ] Nonces verified on form submission
- [ ] Invalid nonces rejected with error message
- [ ] Nonce expiration handled gracefully

### AJAX Nonces
- [ ] All AJAX requests include nonce
- [ ] Nonces verified in AJAX handlers
- [ ] Invalid nonces return error response
- [ ] Nonce passed via `_ajax_nonce` or custom parameter

### REST API Nonces
- [ ] State-changing REST endpoints require nonce
- [ ] Nonce verified in permission callback
- [ ] Invalid nonces return 401/403

**Test Cases:**
- Submit form without nonce → Should fail
- Submit form with invalid nonce → Should fail
- Submit form with expired nonce → Should fail
- Submit form with valid nonce → Should succeed

---

## 2. Capability Checks

### Admin Actions
- [ ] All admin pages check capabilities
- [ ] Unauthorized users see error message
- [ ] Capability checks before data access
- [ ] Capability checks before data modification

### REST API Permissions
- [ ] All REST endpoints have permission callbacks
- [ ] Unauthorized requests return 401/403
- [ ] Error messages don't leak sensitive info

### Frontend Actions
- [ ] User-specific actions check user permissions
- [ ] Guest users can't access user-only features
- [ ] Role-based access enforced

**Test Cases:**
- Access admin page without capability → Should be blocked
- Call REST API without authentication → Should return 401
- Modify data without permission → Should fail

---

## 3. SQL Injection Prevention

### Prepared Statements
- [ ] All database queries use prepared statements
- [ ] No direct SQL concatenation with user input
- [ ] `$wpdb->prepare()` used for all queries
- [ ] Placeholders used correctly (%s, %d, %f)

### Input Sanitization
- [ ] All user input sanitized before queries
- [ ] Integer inputs cast to int
- [ ] String inputs sanitized
- [ ] Array inputs validated

**Test Cases:**
- Inject SQL in form fields → Should be escaped
- Inject SQL in URL parameters → Should be escaped
- Inject SQL in REST API parameters → Should be escaped

**Example Vulnerable Code (DO NOT USE):**
```php
// ❌ VULNERABLE
$wpdb->query("SELECT * FROM table WHERE id = " . $_GET['id']);

// ✅ SECURE
$wpdb->prepare("SELECT * FROM table WHERE id = %d", $_GET['id']);
```

---

## 4. XSS Prevention

### Output Escaping
- [ ] All outputs escaped appropriately
- [ ] HTML context: `esc_html()`, `esc_attr()`
- [ ] URL context: `esc_url()`
- [ ] JavaScript context: `wp_json_encode()`
- [ ] Textarea context: `esc_textarea()`

### Input Sanitization
- [ ] All user input sanitized
- [ ] Text fields: `sanitize_text_field()`
- [ ] Textarea: `sanitize_textarea_field()`
- [ ] Email: `sanitize_email()`
- [ ] URL: `esc_url_raw()`

### JavaScript Context
- [ ] No `innerHTML` with user data
- [ ] No `eval()` with user data
- [ ] Data attributes escaped
- [ ] JSON data properly encoded

**Test Cases:**
- Inject `<script>` in form fields → Should be escaped
- Inject `onclick=` in attributes → Should be escaped
- Inject JavaScript in URL → Should be escaped

**Example Vulnerable Code (DO NOT USE):**
```php
// ❌ VULNERABLE
echo "<div>" . $_GET['name'] . "</div>";

// ✅ SECURE
echo "<div>" . esc_html($_GET['name']) . "</div>";
```

---

## 5. CSRF Protection

### State-Changing Actions
- [ ] All state-changing actions have nonces
- [ ] Nonces verified before action execution
- [ ] Referer checks (if applicable)
- [ ] CSRF attempts logged

**Test Cases:**
- Submit action from external site → Should fail
- Submit action without nonce → Should fail
- Submit action with invalid nonce → Should fail

---

## 6. Authentication & Authorization

### User Authentication
- [ ] User sessions validated
- [ ] Logged-out users can't access protected resources
- [ ] Session timeout handled
- [ ] Concurrent sessions handled

### Role-Based Access
- [ ] Roles checked before access
- [ ] Custom capabilities enforced
- [ ] Role escalation prevented
- [ ] Default role permissions correct

**Test Cases:**
- Access admin as subscriber → Should be blocked
- Access user data as different user → Should be blocked
- Escalate role via form → Should fail

---

## 7. Input Validation

### Type Validation
- [ ] Integer inputs validated as integers
- [ ] String inputs validated for length
- [ ] Email inputs validated as emails
- [ ] URL inputs validated as URLs
- [ ] Date inputs validated as dates

### Range Validation
- [ ] Numeric inputs checked for ranges
- [ ] String inputs checked for length limits
- [ ] Array inputs checked for size
- [ ] File inputs checked for size/type

### Format Validation
- [ ] Email format validated
- [ ] URL format validated
- [ ] Date format validated
- [ ] Phone format validated (if applicable)

**Test Cases:**
- Submit invalid email → Should be rejected
- Submit negative quantity → Should be rejected
- Submit oversized string → Should be rejected

---

## 8. File Upload Security

### File Type Validation
- [ ] Only allowed file types accepted
- [ ] MIME type validation
- [ ] File extension validation
- [ ] Malicious files rejected

### File Size Limits
- [ ] File size limits enforced
- [ ] Large files rejected
- [ ] Error messages clear

### File Storage
- [ ] Files stored outside web root (if sensitive)
- [ ] File names sanitized
- [ ] Directory traversal prevented

**Test Cases:**
- Upload PHP file → Should be rejected
- Upload oversized file → Should be rejected
- Upload file with malicious name → Should be sanitized

---

## 9. Sensitive Data Protection

### Data Storage
- [ ] Passwords never stored in plain text
- [ ] API keys encrypted or obfuscated
- [ ] Personal data protected
- [ ] Sensitive data not logged

### Data Transmission
- [ ] Sensitive data sent over HTTPS
- [ ] API keys not exposed in responses
- [ ] Error messages don't leak sensitive info

### Data Access
- [ ] Sensitive data only accessible to authorized users
- [ ] Audit logging for sensitive operations
- [ ] Data retention policies enforced

---

## 10. REST API Security

### Authentication
- [ ] All endpoints require authentication (where needed)
- [ ] Cookie authentication works
- [ ] Application passwords work
- [ ] OAuth tokens validated (if used)

### Authorization
- [ ] Permission callbacks enforced
- [ ] Capability checks correct
- [ ] User-specific data isolated

### Input/Output
- [ ] All inputs validated
- [ ] All outputs sanitized
- [ ] Error responses don't leak info
- [ ] Rate limiting (if implemented)

**Test Cases:**
- Call REST API without auth → Should return 401
- Call REST API with wrong permissions → Should return 403
- Inject SQL in REST parameters → Should be escaped

---

## 11. Session Security

### Session Management
- [ ] Sessions created securely
- [ ] Session IDs unpredictable
- [ ] Session timeout enforced
- [ ] Session fixation prevented

### Session Data
- [ ] Sensitive data not stored in sessions
- [ ] Session data validated
- [ ] Session hijacking prevented

---

## 12. Error Handling

### Error Messages
- [ ] Error messages don't leak sensitive info
- [ ] Stack traces hidden in production
- [ ] Generic error messages for users
- [ ] Detailed errors logged securely

### Error Logging
- [ ] Errors logged securely
- [ ] Log files protected
- [ ] Log rotation implemented
- [ ] Sensitive data not logged

---

## Security Testing Tools

### Recommended Tools
- **OWASP ZAP** - Web application security scanner
- **Burp Suite** - Web vulnerability scanner
- **WordPress Security Scanner** - WP-specific scanner
- **PHP Security Checker** - Dependency vulnerability scanner

### Manual Testing
- Test all forms with malicious input
- Test all REST endpoints with invalid data
- Test authentication bypass attempts
- Test privilege escalation attempts

---

## Security Checklist Summary

**Before Release:**
- [ ] All nonces verified
- [ ] All capabilities checked
- [ ] All inputs sanitized
- [ ] All outputs escaped
- [ ] All queries prepared
- [ ] All errors handled securely
- [ ] Security audit completed
- [ ] Vulnerability scan passed

**Security Score Target:** 100% (All checks passed)

---

## Reporting Security Issues

If you find a security vulnerability:

1. **DO NOT** create a public issue
2. Email security team directly
3. Include:
   - Description of vulnerability
   - Steps to reproduce
   - Potential impact
   - Suggested fix (if any)

**Security Contact:** [Add contact information]







