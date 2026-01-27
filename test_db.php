<?php
require_once 'PHPs/db.php';

try {
    $pdo = get_pdo(true);
    ensure_setup();
    
    // Test inserting a user
    $hash = password_hash('password123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (username, password, f_name, m_name, l_name, gender, age) VALUES (:u, :p, :fn, :mn, :ln, :g, :a)');
    $stmt->execute([
        ':u' => 'testuser',
        ':p' => $hash,
        ':fn' => 'Test',
        ':mn' => '',
        ':ln' => 'User',
        ':g' => 'Male',
        ':a' => 25
    ]);
    
    echo 'Database setup and test insert successful!';
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>
