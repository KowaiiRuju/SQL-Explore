<?php
// Database helper for SQL-Explore
// Simple defaults for XAMPP

define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'sql_explore');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

function get_pdo($useDb = true) {
    $dsn = $useDb 
        ? sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET)
        : sprintf('mysql:host=%s;port=%s;charset=%s', DB_HOST, DB_PORT, DB_CHARSET);
    
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    return new PDO($dsn, DB_USER, DB_PASS, $options);
}

/**
 * Ensures the users table has all required columns.
 */
function ensure_setup() {
    $pdo = get_pdo(true);
    
    // Create table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(191) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        is_admin TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Add missing columns
    $desiredColumns = [
        'f_name' => 'VARCHAR(100)',
        'm_name' => 'VARCHAR(100)',
        'l_name' => 'VARCHAR(100)',
        'gender' => 'VARCHAR(20)',
        'birthdate' => 'DATE',
        'profile_pic' => 'VARCHAR(255)'
    ];

    $stmt = $pdo->query("SHOW COLUMNS FROM users");
    $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($desiredColumns as $col => $def) {
        if (!in_array($col, $existingColumns)) {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `$col` $def");
        }
    }
}
