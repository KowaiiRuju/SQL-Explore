# SQL-Explore

Minimal PHP/MySQL starter for a login-protected page.

Quick start
- Start XAMPP (Apache + MySQL).
- Browse to: `http://localhost/SQL-Explore/login.php` to sign in or `signup.php` to create an account.

Default DB settings
- Host: `127.0.0.1`
- Port: `3306`
- Database: `sql_explore` (created automatically)
- User: `root` (empty password on many XAMPP installs)

Files of interest
- `index.php` — protected home page; redirects to `login.php` if not authenticated.
- `login.php` — authenticates against `sql_explore.users` and creates a session.
- `signup.php` — creates a new user (hashes password with `password_hash`).
- `logout.php` — clears the session and returns to `login.php`.

How it works
- On signup the script creates the `sql_explore` database and a `users` table if they don't exist.
- Passwords are stored hashed using `password_hash()`.
- `index.php` checks `$_SESSION['user']` and verifies the username exists in the DB before granting access.

Viewing the database
- Use phpMyAdmin (http://localhost/phpmyadmin) or the MySQL CLI/Workbench.
- Example CLI commands:
  ```bash
  mysql -u root -p -e "SHOW DATABASES;"
  mysql -u root -p -e "SELECT * FROM sql_explore.users\G"
  ```

Notes & next steps
- Move DB credentials into a `config.php` and exclude it from version control.
- Add CSRF protection, input validation, rate limiting, and HTTPS before production use.
- If you want, I can update login to use prepared statements everywhere and add a simple admin UI.
