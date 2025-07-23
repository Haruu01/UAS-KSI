# Manual Vulnerability Testing Guide

## Prerequisites
1. Two test user accounts:
   - User1: user@passwordmanager.com / user123
   - User2: user2@passwordmanager.com / user123
2. Admin account: admin@passwordmanager.com / admin123
3. Browser with developer tools
4. Burp Suite or OWASP ZAP (optional)

## Test 1: Broken Access Control

### 1.1 Horizontal Privilege Escalation
**Objective**: Test if users can access other users' data

**Steps**:
1. Login as User1
2. Navigate to password entries and note the URL pattern
3. Try to access User2's password entries by changing IDs in URL
4. Test URLs:
   ```
   /adminn/password-entries/9/edit (User2's password)
   /adminn/password-entries/9 (View User2's password)
   ```

**Expected Result**: Access denied or redirect to own data

### 1.2 Vertical Privilege Escalation
**Objective**: Test if regular users can access admin features

**Steps**:
1. Login as regular user
2. Try to access admin-only URLs:
   ```
   /adminn/audit-logs
   /adminn/security-status
   ```
3. Check if admin menu items are visible
4. Test direct API calls to admin functions

**Expected Result**: Access denied with proper error handling

### 1.3 Direct Object References
**Objective**: Test if object IDs can be manipulated

**Steps**:
1. Login as any user
2. Intercept requests with browser dev tools
3. Modify object IDs in requests:
   - Category IDs
   - Shared password IDs
   - User IDs in forms

## Test 2: Injection Vulnerabilities

### 2.1 SQL Injection
**Objective**: Test for SQL injection in input fields

**Test Payloads**:
```sql
' OR 1=1 --
'; DROP TABLE users; --
' UNION SELECT * FROM password_entries --
admin'--
admin' OR '1'='1
```

**Test Locations**:
1. Login form (email/password)
2. Search functionality
3. Password entry forms
4. Category names
5. Export/import functionality

**Steps**:
1. Enter payloads in each input field
2. Monitor for SQL errors
3. Check if unexpected data is returned
4. Look for database structure disclosure

### 2.2 XSS (Cross-Site Scripting)
**Objective**: Test for XSS vulnerabilities

**Test Payloads**:
```html
<script>alert('XSS')</script>
<img src=x onerror=alert('XSS')>
<svg onload=alert('XSS')>
javascript:alert('XSS')
"><script>alert('XSS')</script>
```

**Test Locations**:
1. Password entry title
2. Password entry notes
3. Category names
4. User profile fields
5. Search results display

**Steps**:
1. Create entries with XSS payloads
2. View the entries in different contexts
3. Check if scripts execute
4. Test reflected XSS in search/error messages

### 2.3 Command Injection
**Objective**: Test for OS command injection

**Test Payloads**:
```bash
; ls -la
| whoami
& dir
`id`
$(whoami)
```

**Test Locations**:
1. File upload functionality
2. Export/import features
3. Any system integration points

## Test 3: Authentication & Session Management

### 3.1 Brute Force Protection
**Objective**: Test account lockout mechanisms

**Steps**:
1. Attempt multiple failed logins
2. Check if account gets locked
3. Test lockout duration
4. Verify if lockout can be bypassed

### 3.2 Session Management
**Objective**: Test session security

**Steps**:
1. Login and capture session cookie
2. Test session fixation
3. Check session timeout
4. Test concurrent sessions
5. Verify logout functionality

### 3.3 Password Reset
**Objective**: Test password reset security

**Steps**:
1. Test password reset flow
2. Check for user enumeration
3. Test reset token security
4. Verify token expiration

## Test 4: File Upload Security

### 4.1 File Type Validation
**Objective**: Test file upload restrictions

**Steps**:
1. Try uploading different file types:
   - .php files renamed to .json
   - .exe files
   - Large files
   - Files with null bytes in name
2. Test import functionality with malicious files

### 4.2 File Content Validation
**Objective**: Test file content security

**Steps**:
1. Upload JSON files with malicious content
2. Test with oversized JSON
3. Test with malformed JSON
4. Include script tags in JSON values

## Test 5: Business Logic Flaws

### 5.1 Password Sharing Logic
**Objective**: Test sharing mechanism security

**Steps**:
1. Share password with another user
2. Try to modify sharing permissions
3. Test sharing with non-existent users
4. Check if sharing can be bypassed

### 5.2 Export/Import Logic
**Objective**: Test data export/import security

**Steps**:
1. Export data and analyze structure
2. Modify exported data and re-import
3. Test with data from different users
4. Check encryption of exported data

## Test 6: Information Disclosure

### 6.1 Error Messages
**Objective**: Test for information leakage in errors

**Steps**:
1. Trigger various error conditions
2. Check error messages for sensitive info
3. Test with invalid inputs
4. Monitor server responses

### 6.2 Debug Information
**Objective**: Check for debug information exposure

**Steps**:
1. Check for debug mode indicators
2. Look for stack traces
3. Check HTTP headers for version info
4. Test for backup files

## Test 7: Security Headers

### 7.1 HTTP Security Headers
**Objective**: Verify security headers implementation

**Check for**:
- Content-Security-Policy
- X-Frame-Options
- X-Content-Type-Options
- X-XSS-Protection
- Strict-Transport-Security
- Referrer-Policy

**Steps**:
1. Use browser dev tools to inspect headers
2. Test with online header analyzers
3. Verify CSP effectiveness

## Automated Testing Tools

### OWASP ZAP
```bash
# Install OWASP ZAP
# Configure proxy to 127.0.0.1:8080
# Run spider scan
# Run active scan
```

### Burp Suite
```bash
# Configure browser proxy
# Intercept and modify requests
# Use Burp Scanner (Pro version)
# Analyze results
```

### SQLMap
```bash
# Test for SQL injection
sqlmap -u "http://127.0.0.1:8000/adminn/password-entries" --cookie="session_cookie" --forms --dbs
```

## Reporting Template

### Vulnerability Report Format
```
Title: [Vulnerability Name]
Severity: [Critical/High/Medium/Low]
CVSS Score: [0-10]
Description: [Detailed description]
Steps to Reproduce: [Step by step]
Impact: [Business impact]
Recommendation: [How to fix]
References: [OWASP, CVE, etc.]
```

## Risk Assessment Matrix

| Likelihood | Impact | Risk Level |
|------------|--------|------------|
| High | High | Critical |
| High | Medium | High |
| Medium | High | High |
| Medium | Medium | Medium |
| Low | High | Medium |
| Low | Medium | Low |
| Low | Low | Low |
