<?php
// Direct test of signup functionality
require_once 'PHPs/db.php';

// Simulate the signup data
$_POST['username'] = 'testuser_' . time();
$_POST['password'] = 'TestPassword123';
$_POST['f_name'] = 'Test';
$_POST['m_name'] = 'Middle';
$_POST['l_name'] = 'User';
$_POST['gender'] = 'Male';
$_POST['age'] = '25';

$message = '';
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

try {
    // Connect to server (no DB) so we can create the database if it doesn't exist
    echo "Step 1: Connecting to server (no DB)...\n";
    $pdoServer = get_pdo(false);
    echo "   ✓ Connected\n";

    echo "Step 2: Creating database if not exists...\n";
    $pdoServer->exec(sprintf("CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s COLLATE %s_general_ci", DB_NAME, DB_CHARSET, DB_CHARSET));
    echo "   ✓ Database checked/created\n";

    // Connect to the application database
    echo "Step 3: Connecting to application database...\n";
    $pdo = get_pdo(true);
    echo "   ✓ Connected to " . DB_NAME . "\n";
    
    // Ensure schema is correct (table exists, columns exist)
    echo "Step 4: Running schema setup...\n";
    ensure_setup();
    echo "   ✓ Schema setup complete\n";

    // Check if user exists
    echo "Step 5: Checking if user exists...\n";
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :u');
    $stmt->execute([':u' => $username]);
    
    if ($stmt->fetch()) {
        $message = 'Username already taken.';
        echo "   ✗ Username already exists\n";
    } else {
        echo "   ✓ Username is available\n";
        
        // Validation Rules
        if (strlen($username) < 3 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $message = 'Username must be at least 3 characters and contain only letters, numbers, and underscores.';
            echo "   ✗ Username validation failed\n";
        } elseif (strlen($password) < 8) {
            $message = 'Password must be at least 8 characters long.';
            echo "   ✗ Password validation failed\n";
        } else {
            $f_name = trim($_POST['f_name'] ?? '');
            $m_name = trim($_POST['m_name'] ?? '');
            $l_name = trim($_POST['l_name'] ?? '');
            $gender = $_POST['gender'] ?? '';
            $age = (int)($_POST['age'] ?? 0);

            if (empty($f_name) || empty($l_name) || empty($gender) || $age <= 0) {
                $message = 'Please fill in all required profile fields (First Name, Last Name, Gender, Age).';
                echo "   ✗ Profile validation failed\n";
            } else {
                echo "Step 6: Creating new user...\n";
                // Create new user
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $ins = $pdo->prepare('INSERT INTO users (username, password, f_name, m_name, l_name, gender, age) VALUES (:u, :p, :fn, :mn, :ln, :g, :a)');
                $ins->execute([
                    ':u' => $username, 
                    ':p' => $hash,
                    ':fn' => $f_name,
                    ':mn' => $m_name,
                    ':ln' => $l_name,
                    ':g' => $gender,
                    ':a' => $age
                ]);
                echo "   ✓ User created successfully!\n";
                echo "\n✓ SUCCESS! Test user '$username' has been created.\n";
            }
        }
    }
    
    if ($message) {
        echo "\n✗ ERROR: $message\n";
    }
    
} catch (Exception $e) {
    echo "\n✗ EXCEPTION: " . $e->getMessage() . "\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
}
?>
