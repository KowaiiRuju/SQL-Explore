# SQL-Explore

A modern PHP + MySQL social platform starter with event scoring, user authentication, profile management, and admin dashboard. Features a responsive UI with sidebar navigation, messaging, friend system, and profile pictures. Designed for local development (XAMPP).

## Features
- **User Authentication** — Secure signup, login with rate limiting, and session management
- **Profile System** — Profile pictures, cover photos, bio, and personal information
- **Messaging** — Real-time messaging between users
- **Friend Management** — Add/remove friends with request system
- **Social Feed** — Posts with likes, comments, and sharing
- **Admin Dashboard** — Create, edit, and delete users (admin only)
- **Security** — CSRF protection, input validation, file upload security, SQL injection prevention
- **Responsive Design** — Bootstrap 5 with custom styling

## Requirements
- PHP (7.4+ recommended)
- MySQL or MariaDB
- XAMPP recommended for an easy local setup (Apache + MySQL)

## Quick Start
1. Start XAMPP (Apache + MySQL).
2. Place this project in your XAMPP `htdocs` folder (e.g. `C:\xampp\htdocs\SQL-Explore`).
3. Visit `http://localhost/SQL-Explore/signup.php` to create an account.
   - Username: 3-20 characters (letters, numbers, hyphens, underscores)
   - Email: Valid email address (required, must be unique)
   - Password: 8+ characters with uppercase, number, and special character
   - Names: Letters, hyphens, and apostrophes only
4. Visit `http://localhost/SQL-Explore/login.php` to sign in.
   - Protected against brute-force attacks (5 failed attempts = 15 min lockout)
5. Visit `http://localhost/SQL-Explore/PHPs/admin.php` to access the admin dashboard (requires admin privileges).

## Setup Details
- The application will create the `sql_explore` database and all necessary tables on first signup if they do not already exist.
- Tables automatically created:
  - `users` — User accounts and profiles
  - `login_attempts` — Login attempt tracking for rate limiting
  - `posts` — Social feed posts
  - `post_likes` — Post likes
  - `post_comments` — Post comments
  - `messages` — User-to-user messaging
  - `friendships` — Friend requests and connections
  - `teams` — Team management
  - `events` — Event management
  - `event_scores` — Event scoring

- Typical local DB settings (XAMPP):
  - Host: `127.0.0.1`
  - Port: `3306`
  - Database: `sql_explore`
  - User: `root` (often an empty password on XAMPP)

- **Database Connection**: The database connection logic is located in `PHPs/db.php`. Update DB constants there or use environment variables for non-local environments.

## Project Structure
```
SQL-Explore/
├── PHPs/
│   ├── index.php             # Dashboard (protected)
│   ├── admin.php             # Admin dashboard
│   ├── profile.php           # Profile editing
│   ├── newsfeed.php          # Social feed
│   ├── messages.php          # Messaging interface
│   ├── friends.php           # Friend management
│   ├── events.php            # Event management
│   ├── teams.php             # Team management
│   ├── sidebar.php           # Reusable sidebar component
│   ├── db.php                # Database connection & setup
│   ├── login.php             # User login (with rate limiting)
│   ├── signup.php            # User registration (with validation)
│   ├── logout.php            # Session logout
│   ├── includes/
│   │   ├── csrf.php          # CSRF token protection
│   │   ├── helpers.php       # Helper functions (file upload, rate limiting)
│   │   ├── session_guard.php # Session & remember-me
│   │   ├── header.php        # HTML header template
│   │   ├── footer.php        # HTML footer template
│   │   └── mailer.php        # Email utilities
│   └── api/
│       ├── posts.php         # Posts API (CSRF protected)
│       ├── messages.php      # Messages API (CSRF protected)
│       └── friends.php       # Friends API (CSRF protected, SQL-safe)
├── css/
│   ├── style.css             # Global styles
│   ├── admin.css             # Admin dashboard styles
│   ├── landing.css           # Landing page styles
│   ├── login.css             # Login page styles
│   └── ...                   # Other page-specific styles
├── scripts/
│   ├── admin.js              # Admin dashboard scripts
│   ├── messages.js           # Messaging functionality
│   ├── friends.js            # Friend management
│   ├── newsfeed.js           # Social feed scripts
│   └── ...                   # Other scripts
├── uploads/                  # User-uploaded files (profile pictures, cover photos)
├── images/                   # Static images and graphics
├── index.php                 # Landing page / entry point
├── local.php.ini             # Local PHP configuration
├── README.md                 # This file
├── CRITICAL_FIXES_SUMMARY.md # Summary of security fixes
└── IMPLEMENTATION_NOTES.md   # Developer notes on security features
```

## Key Files
- `PHPs/index.php` — Protected dashboard with welcome section and profile picture display
- `PHPs/admin.php` — Admin Dashboard: Create, edit, and delete users (admin only)
- `PHPs/db.php` — Database connection helper using PDO with auto table creation
- `PHPs/includes/helpers.php` — Helper functions including:
  - `validate_and_upload_image()` — Secure file upload with MIME type checking
  - `check_login_rate_limit()` — Login attempt tracking
  - `record_login_attempt()` — Log login attempts for security
- `PHPs/includes/csrf.php` — CSRF token generation and validation
- `PHPs/api/*.php` — JSON APIs with CSRF and SQL injection protection
- `uploads/` — Stores user-uploaded files (max 5MB, images only)

## Security Features
✅ **Strong Input Validation**
- Username: 3-20 characters (letters, numbers, hyphens, underscores)
- Email: Valid format with duplicate prevention
- Passwords: 8+ chars with uppercase, number, and special character
- Names: Letters, hyphens, apostrophes only (2-100 chars)
- File uploads: MIME type verification, max 5MB, random filenames

✅ **CSRF Protection**
- All forms include CSRF tokens
- All API endpoints validate CSRF tokens for POST requests
- Token stored in `$_SESSION` and form hidden fields

✅ **SQL Injection Prevention**
- PDO prepared statements everywhere
- LIKE queries properly escape wildcards
- Input parameters bound to queries

✅ **Login Security**
- Passwords hashed with `password_hash(PASSWORD_DEFAULT)`
- Rate limiting: 5 failed attempts → 15-minute lockout
- Brute-force protection by username + IP address
- Automatic cleanup of old login attempt records

✅ **File Upload Security**
- File type whitelist (JPG, PNG, WebP only)
- MIME type validation using `finfo_file()`
- Random filename generation prevents path traversal
- Size limits enforce (max 5MB)
- Prevents malicious file execution

✅ **Session Management**
- HTTP-only session cookies
- Remember-me functionality with secure tokens
- Session timeout after 30 minutes of inactivity

## How it works
- `signup.php` validates all input, creates database and `users` table if missing, then stores user credentials hashed
- `login.php` validates credentials with rate limiting and records login attempts for security
- `profile.php` allows users to update profile with secure file upload handling
- `newsfeed.php` displays social feed with posts, likes, and comments (CSRF protected)
- `messages.php` handles user-to-user messaging via secure API
- `friends.php` manages friend requests with SQL-safe search
- `admin.php` verifies `$_SESSION['is_admin']` before granting user management access
- All APIs include CSRF token validation and proper error handling

## Development tips
- View the database with phpMyAdmin: http://localhost/phpmyadmin
- Create admin user:
```sql
UPDATE sql_explore.users SET is_admin = 1 WHERE username = 'your_username';
```
- Example CLI commands:
```bash
mysql -u root -e "SHOW DATABASES;"
mysql -u root -e "SELECT username, is_admin FROM sql_explore.users;"
mysql -u root -e "DELETE FROM sql_explore.login_attempts;" # Clear rate limits
```

## Security Implementation Summary
See the following files for detailed information:
- `CRITICAL_FIXES_SUMMARY.md` — Summary of all security fixes implemented
- `IMPLEMENTATION_NOTES.md` — Developer guide for security features
- `ISSUES_AND_RECOMMENDATIONS.md` — Full security analysis and recommendations

Recent improvements (Feb 2026):
- ✅ Enhanced input validation on signup
- ✅ Secure file upload with MIME type checking
- ✅ CSRF protection on all API endpoints
- ✅ SQL injection prevention in LIKE queries
- ✅ Login rate limiting with brute-force protection

## Future Enhancements
- [ ] Move database credentials to `.env` file
- [ ] Add email verification for new accounts
- [ ] Implement password reset functionality
- [ ] Add two-factor authentication (2FA)
- [ ] Add admin audit logging
- [ ] Implement pagination for large datasets
- [ ] Add API documentation (Swagger/OpenAPI)
- [ ] Set up automated security testing

## Production Deployment Checklist
- [ ] Move database credentials to environment variables
- [ ] Enable HTTPS/SSL certificates
- [ ] Set proper file permissions (755 for dirs, 644 for files)
- [ ] Configure proper error logging
- [ ] Set up database backups
- [ ] Enable CORS headers if needed
- [ ] Add rate limiting at web server level
- [ ] Run security audit with OWASP tools
## Troubleshooting

### Database Issues
**Problem:** "Database error" on signup/login
- **Solution:** Ensure MySQL is running in XAMPP. Check `PHPs/db.php` for correct credentials.

**Problem:** "Access denied for user 'root'"
- **Solution:** Update DB_USER and DB_PASS in `PHPs/db.php` to match your MySQL setup.

### File Upload Issues
**Problem:** "Failed to save profile picture" or "File is too large"
- **Solution:** Ensure `uploads/` directory exists and is writable (755 permissions). Max file size is 5MB.

**Problem:** "File content does not match the declared format"
- **Solution:** Upload files must actually be images. Renaming .exe to .jpg won't work (MIME type is checked).

### Login Issues
**Problem:** "Too many failed login attempts. Please try again in 15 minutes."
- **Solution:** This is by design after 5 failed attempts. Wait 15 minutes or clear the `login_attempts` table:
```sql
DELETE FROM sql_explore.login_attempts;
```

**Problem:** Can't login with correct credentials
- **Solution:** Ensure username or email matches exactly. Passwords are case-sensitive.

### API Issues
**Problem:** API returns "CSRF token validation failed"
- **Solution:** Ensure POST requests include valid `_token` parameter from the form's hidden input.

## Support
For issues or questions:
1. Check this README for troubleshooting
2. Review error logs in browser console
3. Check PHP error logs in XAMPP
4. Consult `IMPLEMENTATION_NOTES.md` for detailed feature documentation

## License
MIT License - Feel free to use and modify for your projects.