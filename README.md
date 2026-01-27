# SQL-Explore

A modern PHP + MySQL starter project with user authentication, profile management, and an admin dashboard. Features a responsive UI with sidebar navigation and profile pictures. Designed for local development (XAMPP).

## Features
- **User Authentication** — Signup, login, and session management
- **Profile Pictures** — Upload and display user avatars
- **Sidebar Navigation** — Modern offcanvas sidebar with quick links
- **Profile Modal** — View user details at a glance
- **Admin Dashboard** — Create, edit, and delete users (admin only)
- **Responsive Design** — Bootstrap 5 with custom styling

## Requirements
- PHP (7.4+ recommended)
- MySQL or MariaDB
- XAMPP recommended for an easy local setup (Apache + MySQL)

## Quick Start
1. Start XAMPP (Apache + MySQL).
2. Place this project in your XAMPP `htdocs` folder (e.g. `C:\xampp\htdocs\SQL-Explore`).
3. Visit `http://localhost/SQL-Explore/signup.php` to create an account.
4. Visit `http://localhost/SQL-Explore/login.php` to sign in.
5. Visit `http://localhost/SQL-Explore/PHPs/admin.php` to access the admin dashboard (requires admin privileges).

## Setup Details
- The application will create the `sql_explore` database and a `users` table on first signup if they do not already exist.
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
│   ├── index.php      # Dashboard (protected)
│   ├── admin.php      # Admin dashboard
│   ├── profile.php    # Profile editing
│   ├── sidebar.php    # Reusable sidebar component
│   ├── db.php         # Database connection helper
│   ├── login.php      # User login
│   ├── signup.php     # User registration
│   └── logout.php     # Session logout
├── css/               # Stylesheets
├── scripts/           # JavaScript files
├── uploads/           # User profile pictures
└── index.php          # Entry point router
```

## Key Files
- `PHPs/index.php` — Protected dashboard with welcome section and profile picture display
- `PHPs/admin.php` — **Admin Dashboard**: Create, edit, and delete users (admin only)
- `PHPs/db.php` — Database connection helper using PDO
- `PHPs/sidebar.php` — Offcanvas sidebar with navigation and profile modal trigger
- `uploads/` — Stores user-uploaded profile pictures

## How it works
- `signup.php` creates the database and `users` table if missing, then stores user credentials hashed with `password_hash()`.
- `login.php` validates credentials and populates `$_SESSION['user']` on success.
- `index.php` checks the session and queries the database to confirm the user before showing protected content.
- `admin.php` verifies `$_SESSION['is_admin']` before granting access to user management features.

## Development tips
- View the database with phpMyAdmin: http://localhost/phpmyadmin
- Example CLI commands:

```bash
mysql -u root -e "SHOW DATABASES;"
mysql -u root -e "SELECT * FROM sql_explore.users\G"
```

## Security & next steps
- Use prepared statements everywhere to prevent SQL injection (already implemented in `admin.php`).
- Add CSRF protection and input validation on all forms.
- Enforce HTTPS in production.
- Add rate limiting for login attempts.

## License
MIT (or your choice).
