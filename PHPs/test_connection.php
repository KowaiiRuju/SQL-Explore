<?php
require_once __DIR__ . '/db.php';

echo "<h2>Database Connection Test</h2>";
echo "<pre>";

try {
    echo "1. Testing connection without DB:\n";
    $pdoServer = get_pdo(false);
    echo "   ✓ Connected to MySQL server\n\n";

    echo "2. Creating database:\n";
    $result = $pdoServer->exec(sprintf("CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s COLLATE %s_general_ci", DB_NAME, DB_CHARSET, DB_CHARSET));
    echo "   ✓ Database created/checked\n\n";

    echo "3. Connecting to database:\n";
    $pdo = get_pdo(true);
    echo "   ✓ Connected to " . DB_NAME . "\n\n";
    
    echo "4. Running schema setup:\n";
    ensure_setup();
    echo "   ✓ Schema setup complete\n\n";
    
    echo "5. Checking table structure:\n";
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "   - {$col['Field']}: {$col['Type']} " . ($col['Null'] === 'NO' ? 'NOT NULL' : '') . "\n";
    }
    
    echo "\n✓ ALL TESTS PASSED - Database connection is working!\n";
    
} catch (Exception $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo "</pre>";
?>
