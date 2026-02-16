# SQL-Explore: Issues & Recommendations

## 📋 Project Overview
This is a modern PHP/MySQL social networking application (event scoring system) featuring user authentication, profiles, admin dashboard, messaging, and friend management. Built for local XAMPP development.

---

## 🔴 CRITICAL SECURITY ISSUES

### 1. **Missing Input Validation in Signup**
**File:** [PHPs/signup.php](PHPs/signup.php)
**Severity:** HIGH
- Username only has basic length check (no regex validation for allowed characters)
- No email validation before storing
- Missing password confirmation (users can mistype and lock themselves out)
- No check for malicious/profane usernames

**Recommended Fix:**
```php
// Add validation for username format (alphanumeric, underscores, hyphens only)
if (!preg_match('/^[a-zA-Z0-9_-]{3,20}$/', $username)) {
    $message = 'Username must be 3-20 characters, alphanumeric with - or _ only.';
}

// Add email validation
if (!filter_var($email ?? '', FILTER_VALIDATE_EMAIL)) {
    $message = 'Invalid email address.';
}

// Add password confirmation field in signup form
```

---

### 2. **File Upload Security (Profile Pictures)**
**File:** [PHPs/profile.php](PHPs/profile.php#L332)
**Severity:** HIGH
- Profile picture uploads likely lack file type validation
- No file size limits mentioned
- Potential for arbitrary file upload vulnerability
- No virus scanning

**Recommended Fix:**
```php
// Add strict file validation
function validate_upload_file($file) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if ($file['size'] > $max_size) {
        return ['error' => 'File too large (max 5MB)'];
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    
    if (!in_array($mime, $allowed_types)) {
        return ['error' => 'Invalid file type. Only JPEG, PNG, WebP allowed'];
    }
    
    // Generate random filename to prevent directory traversal
    $filename = uniqid('profile_', true) . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
    return ['filename' => $filename, 'error' => null];
}
```

---

### 3. **API Endpoints Missing CSRF Protection**
**Files:** `PHPs/api/*.php` (posts.php, messages.php, friends.php)
**Severity:** HIGH
- API endpoints only check session authentication
- No CSRF token validation on POST/DELETE operations
- Vulnerable to cross-site request forgery attacks

**Recommended Fix:**
```php
// Add to each API endpoint
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// Add CSRF check for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../includes/csrf.php';
    if (!csrf_verify()) {
        http_response_code(403);
        echo json_encode(['error' => 'CSRF validation failed']);
        exit;
    }
}
```

---

### 4. **SQL Injection in Friend Search (Partial)**
**File:** [PHPs/api/friends.php](PHPs/api/friends.php#L40)
**Severity:** MEDIUM
- Uses LIKE query with user input from GET parameter (`$q`)
- While prepared statements are used, the LIKE wildcards could allow pattern-based injection
- Search term not properly sanitized before LIKE matching

**Recommended Fix:**
```php
// Sanitize search term for LIKE queries
$q = trim($_GET['q'] ?? '');
if (strlen($q) < 1 || strlen($q) > 50) {
    echo json_encode(['success' => true, 'users' => []]);
    exit;
}

// Escape LIKE wildcards
$q = str_replace(['%', '_'], ['\%', '\_'], $q);
$q = '%' . $q . '%';

// Now use in prepared statement
$sql = "... WHERE u.username LIKE :q ESCAPE '\\' ...";
```

---

### 5. **Missing Rate Limiting on Login**
**File:** [PHPs/login.php](PHPs/login.php#L56)
**Severity:** MEDIUM
- No brute-force protection on login attempts
- No rate limiting, delays, or account lockout mechanisms
- Attackers can try unlimited password combinations

**Recommended Fix:**
```php
// Add to login.php (store in DB or Redis)
function check_login_rate_limit($username) {
    $pdo = get_pdo(true);
    $key = 'login_attempt_' . md5($username);
    
    // Check if too many attempts in last 15 minutes
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM login_attempts 
        WHERE username = :u AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $stmt->execute([':u' => $username]);
    $result = $stmt->fetch();
    
    if ($result['count'] > 5) {
        return ['blocked' => true, 'message' => 'Too many attempts. Try again in 15 minutes.'];
    }
    return ['blocked' => false];
}
```

---

## 🟠 MAJOR ISSUES

### 6. **Weak Session Management**
**File:** [PHPs/includes/session_guard.php](PHPs/includes/session_guard.php)
**Severity:** MEDIUM
- Session cookie settings not hardened (no HttpOnly, Secure, SameSite flags visible)
- No session regeneration after login
- Remember-me cookie potentially stored in plaintext

**Recommended Fix:**
```php
// In session_guard.php at startup
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => false, // Set to true in production with HTTPS
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// After successful login
session_regenerate_id(true); // Destroy old session
```

---

### 7. **Missing Input Sanitization on Profile Updates**
**File:** [PHPs/profile.php](PHPs/profile.php#L145)
**Severity:** MEDIUM
- Displays user data with htmlspecialchars() ✓ (good)
- But accept user bio/textarea input without length limits
- XSS risk if any field is not properly escaped on display

**Recommended Fix:**
```php
// Add to profile.php when processing updates
$bio = trim($_POST['bio'] ?? '');
if (strlen($bio) > 500) {
    $error = 'Bio must be 500 characters or less.';
} else {
    // Still sanitize before storage
    $bio = htmlspecialchars($bio, ENT_QUOTES, 'UTF-8');
}
```

---

### 8. **Database Credentials Hardcoded**
**File:** [PHPs/db.php](PHPs/db.php)
**Severity:** MEDIUM
- Database credentials hardcoded in PHP file
- No environment variable support
- Will expose credentials in version control

**Recommended Fix:**
```php
// Create .env file in project root
// DB_HOST=localhost
// DB_USER=root
// DB_PASS=yourpassword
// DB_NAME=sql_explore

// In db.php
$dotenv = parse_ini_file(__DIR__ . '/.env');

define('DB_HOST', $dotenv['DB_HOST'] ?? 'localhost');
define('DB_USER', $dotenv['DB_USER'] ?? 'root');
define('DB_PASS', $dotenv['DB_PASS'] ?? '');
```

---

### 9. **Admin Actions Lack Audit Logging**
**File:** [PHPs/admin.php](PHPs/admin.php#L17)
**Severity:** MEDIUM
- No logging of user create/edit/delete operations
- Can't track who made what changes and when
- Critical for security and compliance

**Recommended Fix:**
```php
// Add audit_log table to schema
// Log all admin actions
function log_admin_action($action, $target_user, $details) {
    $pdo = get_pdo(true);
    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (admin_id, action, target_user, details, created_at)
        VALUES ((SELECT id FROM users WHERE username = :admin), :action, :target, :details, NOW())
    ");
    $stmt->execute([
        ':admin' => $_SESSION['user'],
        ':action' => $action,
        ':target' => $target_user,
        ':details' => json_encode($details)
    ]);
}
```

---

### 10. **Missing Error Handling in Database Operations**
**File:** Multiple files
**Severity:** MEDIUM
- Generic error messages don't reveal info, but database errors could be verbose
- No structured error logging for debugging

**Recommended Fix:**
```php
// Create error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log(sprintf(
        "[%s] %s in %s:%d\n",
        date('Y-m-d H:i:s'),
        $errstr,
        basename($errfile),
        $errline
    ), 3, __DIR__ . '/logs/errors.log');
    
    // Show generic message to user
    http_response_code(500);
    echo 'An error occurred. Please try again.';
    exit;
});
```

---

## 🟡 MODERATE ISSUES

### 11. **No Password Strength Enforcement**
**File:** [PHPs/signup.php](PHPs/signup.php#L33)
**Severity:** LOW-MEDIUM
- Only checks minimum length (8 chars)
- No complexity requirements (uppercase, numbers, symbols)

**Fix:**
```php
function validate_password_strength($password) {
    $errors = [];
    
    if (strlen($password) < 8) 
        $errors[] = 'At least 8 characters';
    if (!preg_match('/[A-Z]/', $password)) 
        $errors[] = 'One uppercase letter';
    if (!preg_match('/[0-9]/', $password)) 
        $errors[] = 'One number';
    if (!preg_match('/[!@#$%^&*]/', $password)) 
        $errors[] = 'One special character (!@#$%^&*)';
    
    return $errors;
}
```

---

### 12. **Missing Email Verification**
**File:** [PHPs/signup.php](PHPs/signup.php)
**Severity:** LOW-MEDIUM
- Users can sign up with any email without verification
- No confirmation email sent
- Could allow spam/fake accounts

**Fix:**
- Send verification email with token link
- Flag account as unverified until email clicked
- Consider adding email field to user profile during signup

---

### 13. **No HTTPS Enforcement**
**File:** All files
**Severity:** MEDIUM (for production)
**Note:** Currently using HTTP-only, acceptable for local XAMPP, but:

**Recommendation:**
```php
// In main entry point (index.php or top of all pages)
if ($_SERVER['HTTP_X_FORWARDED_PROTO'] !== 'https' && $_ENV['APP_ENV'] === 'production') {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit;
}
```

---

### 14. **Inconsistent Data Type Casting**
**File:** [PHPs/admin.php](PHPs/admin.php#L40-L50)
**Severity:** LOW
- Some IDs cast to int, some not
- Could lead to type juggling vulnerabilities

**Fix:**
```php
// Ensure consistent casting
$userId = (int)($_POST['user_id'] ?? 0);
$postId = (int)($_GET['post_id'] ?? 0);
$teamId = (int)($_POST['team_id'] ?? 0);
```

---

### 15. **Missing Content-Type Headers**
**File:** Some pages
**Severity:** LOW
**Note:** API files set JSON headers ✓
**Recommendation:** All HTML pages should set:
```php
header('Content-Type: text/html; charset=utf-8');
```

---

## 🟢 MINOR IMPROVEMENTS

### 16. **Dead/Incomplete Code**
**File:** Various
- `cleanup_photos.php` and `cleanup_tool.php` - unclear purpose
- Several commented-out sections in code
- TODO comments without follow-up

**Recommendation:** Remove dead code or document its purpose

---

### 17. **Missing API Documentation**
**Files:** `PHPs/api/*.php`
- No swagger/OpenAPI docs
- API parameters not documented
- Would benefit from auto-generated docs

**Recommendation:** Add PHPDoc comments:
```php
/**
 * @route POST /api/posts.php
 * @param action string - 'toggle_like', 'add_comment', etc.
 * @param post_id int - ID of post to interact with
 * @returns {success: bool, data: object}
 */
```

---

### 18. **No Pagination on Lists**
**File:** [PHPs/admin.php](PHPs/admin.php)
- User list could load hundreds/thousands of users at once
- No pagination implemented

**Recommendation:**
```php
$page = (int)($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

$stmt = $pdo->prepare("SELECT * FROM users LIMIT :limit OFFSET :offset");
$stmt->execute([':limit' => $per_page, ':offset' => $offset]);
```

---

### 19. **Unused Endpoints/Features**
- `events.php` referenced but not fully connected
- `teams.php` exists but integration unclear
- Some admin features seem partial

**Recommendation:** Complete or remove incomplete features

---

### 20. **Missing Logging/Analytics**
- No user activity logging
- No page view tracking
- No API call metrics

**Recommendation:** Add simple logging:
```php
function log_event($type, $user_id, $data) {
    $pdo = get_pdo(true);
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (type, user_id, data, created_at)
        VALUES (:type, :user_id, :data, NOW())
    ");
    $stmt->execute([
        ':type' => $type,
        ':user_id' => $user_id,
        ':data' => json_encode($data)
    ]);
}
```

---

## ✅ POSITIVE SECURITY ASPECTS

- ✓ Passwords properly hashed with `password_hash()`
- ✓ PDO prepared statements prevent SQL injection in most places
- ✓ CSRF tokens implemented for forms
- ✓ Session guard implemented for protected pages
- ✓ Admin checks properly restrict sensitive operations
- ✓ User input escaped with `htmlspecialchars()` when displayed
- ✓ Reasonable error messages (not overly verbose)

---

## 📋 PRIORITY IMPLEMENTATION ORDER

1. **CRITICAL (Do First):**
   - [ ] Add CSRF protection to API endpoints
   - [ ] Implement file upload validation
   - [ ] Add input validation for username/email

2. **HIGH (Do Soon):**
   - [ ] Add login rate limiting
   - [ ] Implement session regeneration
   - [ ] Add password strength requirements

3. **MEDIUM (Do Next):**
   - [ ] Move database credentials to .env
   - [ ] Add admin audit logging
   - [ ] Improve error handling

4. **LOW (Nice to Have):**
   - [ ] Email verification
   - [ ] API documentation
   - [ ] Activity logging

---

## 🚀 TESTING RECOMMENDATIONS

1. **Security Testing:**
   - Test file upload with malicious files (.exe, .php)
   - Try XSS payloads in bio/profile fields
   - Attempt SQL injection in search
   - Brute-force password guessing

2. **Functional Testing:**
   - Verify all CRUD operations work
   - Test pagination/filtering
   - Check responsive design

3. **Performance Testing:**
   - Load test with 1000+ users
   - Check query performance
   - Monitor memory usage

---

## 📞 NEXT STEPS

1. Create a `.env` file for configuration
2. Set up a staging environment with proper logging
3. Conduct security audit with OWASP checklist
4. Add unit/integration tests
5. Deploy with HTTPS/SSL certificates

---

**Last Updated:** February 16, 2026
**Risk Level:** Medium (acceptable for educational/local development, needs hardening for production)
