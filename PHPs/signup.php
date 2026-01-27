<?php
require_once __DIR__ . '/db.php';

$message = '';
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($username === '' || $password === '') {
        $message = 'Please provide username and password.';
    } else {
        try {
            // Connect to server (no DB) so we can create the database if it doesn't exist
            $pdoServer = get_pdo(false);
            $pdoServer->exec(sprintf("CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s COLLATE %s_general_ci", DB_NAME, DB_CHARSET, DB_CHARSET));

            // Connect to the application database
            $pdo = get_pdo(true);
            
            // Ensure schema is correct (table exists, columns exist)
            ensure_setup();

            // Check if user exists
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :u');
            $stmt->execute([':u' => $username]);
            
            if ($stmt->fetch()) {
                $message = 'Username already taken.';
            } else {
                // Validation Rules
                if (strlen($username) < 3 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
                    $message = 'Username must be at least 3 characters and contain only letters, numbers, and underscores.';
                } elseif (strlen($password) < 8) {
                    $message = 'Password must be at least 8 characters long.';
                } else {
                    $f_name = trim($_POST['f_name'] ?? '');
                    $m_name = trim($_POST['m_name'] ?? '');
                    $l_name = trim($_POST['l_name'] ?? '');
                    $gender = $_POST['gender'] ?? '';
                    $age = (int)($_POST['age'] ?? 0);

                    if (empty($f_name) || empty($l_name) || empty($gender) || $age <= 0) {
                        $message = 'Please fill in all required profile fields (First Name, Last Name, Gender, Age).';
                    } else {
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
                        
                        header('Location: login.php?created=1');
                        exit;
                    }
                }
            }
        } catch (Exception $e) {
            $message = 'Database error: ' . htmlspecialchars($e->getMessage());
            error_log('Signup error: ' . $e->getFile() . ':' . $e->getLine() . ' - ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Sign Up - SQL Explore</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/signup.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="body-signup">
    <div class="container auth-container">
        <div class="card">
            <h2 class="signup-title">Create an account</h2>
            <?php if ($message): ?>
                <div class="alert alert-error"><?=htmlspecialchars($message)?></div>
            <?php endif; ?>
            
            <form method="post" action="signup.php">
                <div>
                    <label>First Name</label>
                    <input type="text" name="f_name" value="<?=htmlspecialchars($_POST['f_name'] ?? '')?>" required>
                </div>
                <div>
                    <label>Middle Name (Optional)</label>
                    <input type="text" name="m_name" value="<?=htmlspecialchars($_POST['m_name'] ?? '')?>">
                </div>
                <div>
                    <label>Last Name</label>
                    <input type="text" name="l_name" value="<?=htmlspecialchars($_POST['l_name'] ?? '')?>" required>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <label>Gender</label>
                        <select name="gender" class="form-control form-select" required>
                            <option value="">Select</option>
                            <option value="Male" <?= (($_POST['gender'] ?? '') === 'Male') ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= (($_POST['gender'] ?? '') === 'Female') ? 'selected' : '' ?>>Female</option>
                            <option value="Other" <?= (($_POST['gender'] ?? '') === 'Other') ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                    <div class="form-col">
                        <label>Age</label>
                        <input type="number" name="age" value="<?=htmlspecialchars($_POST['age'] ?? '')?>" min="1" required>
                    </div>
                </div>

                <div>
                    <label>Username (min 3 chars)</label>
                    <input type="text" name="username" value="<?=htmlspecialchars($username)?>" required minlength="3" pattern="[a-zA-Z0-9_]+">
                    <small class="form-hint">Letters, numbers, underscores only.</small>
                </div>
                <div>
                    <label>Password (min 8 chars)</label>
                    <input type="password" name="password" required minlength="8">
                </div>
                <!-- 
                     Note: In a real app we might ask for password confirmation here. 
                     For now we keep it simple as per original logic.
                -->
                <button type="submit" class="btn btn-full">Sign Up</button>
            </form>
            <p class="auth-link">
                <a href="login.php">Back to login</a>
            </p>
        </div>
    </div>
</body>
</html>
