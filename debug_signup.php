<?php
// Debug script to check what's happening in signup
require_once 'PHPs/db.php';

echo "Testing Database Setup...\n\n";

try {
    echo "1. Testing connection...\n";
    $pdo = get_pdo(true);
    echo "   ✓ Connected to database\n\n";
    
    echo "2. Running schema setup...\n";
    ensure_setup();
    echo "   ✓ Schema setup complete\n\n";
    
    echo "3. Checking table structure...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "   Columns in users table:\n";
    foreach ($columns as $col) {
        echo "     - {$col['Field']}: {$col['Type']}\n";
    }
    echo "\n";
    
    echo "4. Attempting test insert...\n";
    $testUsername = 'testuser_' . time();
    $hash = password_hash('TestPassword123', PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare('INSERT INTO users (username, password, f_name, m_name, l_name, gender, age) VALUES (:u, :p, :fn, :mn, :ln, :g, :a)');
    $stmt->execute([
        ':u' => $testUsername,
        ':p' => $hash,
        ':fn' => 'Test',
        ':mn' => 'Middle',
        ':ln' => 'User',
        ':g' => 'Male',
        ':a' => 25
    ]);
    echo "   ✓ Test user created successfully!\n";
    echo "   Username: $testUsername\n\n";
    
    echo "✓ All tests passed! Your signup should work now.\n";
    
} catch (Exception $e) {
    echo "✗ Error occurred:\n";
    echo "   " . $e->getMessage() . "\n\n";
    echo "Troubleshooting steps:\n";
    echo "1. Make sure XAMPP is running (MySQL service should be active)\n";
    echo "2. Verify your database credentials in db.php are correct\n";
    echo "3. Check that MySQL port 3306 is accessible\n";
}
?>
