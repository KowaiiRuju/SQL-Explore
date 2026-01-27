<?php
require_once __DIR__ . '/db.php';

echo "<h1>Database Setup & Verification</h1>";

try {
    echo "<p>Connecting to database...</p>";
    $pdo = get_pdo(true);
    echo "<p style='color:green'>&#10003; Connected to database '" . DB_NAME . "'</p>";

    echo "<p>Running schema check (ensure_setup)...</p>";
    ensure_setup();
    
    // Verify Columns specifically
    $stmt = $pdo->query("SHOW COLUMNS FROM users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h3>Current Columns in 'users' table:</h3>";
    echo "<ul>";
    $required = ['username', 'password', 'f_name', 'm_name', 'l_name', 'gender', 'age', 'is_admin'];
    $missing = [];

    foreach ($columns as $col) {
        echo "<li>" . htmlspecialchars($col) . "</li>";
    }
    echo "</ul>";

    foreach ($required as $req) {
        if (!in_array($req, $columns)) {
            $missing[] = $req;
        }
    }

    if (empty($missing)) {
        echo "<h2 style='color:green'>SUCCESS: All required form fields are present!</h2>";
    } else {
        echo "<h2 style='color:red'>FAILURE: Missing columns: " . implode(', ', $missing) . "</h2>";
    }

} catch (Exception $e) {
    echo "<h2 style='color:red'>Error: " . htmlspecialchars($e->getMessage()) . "</h2>";
}
?>
