<?php
require_once __DIR__ . '/db.php';

$message = '';
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$email = trim($_POST['email'] ?? '');

/**
 * Validate password strength requirements
 * @return string Empty if valid, error message if invalid
 */
function validate_password_strength($password) {
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters long.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return 'Password must contain at least one uppercase letter.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        return 'Password must contain at least one number.';
    }
    if (!preg_match('/[!@#$%^&*\-_]/', $password)) {
        return 'Password must contain at least one special character (!@#$%^&*-_).';
    }
    return '';
}

/**
 * Validate name fields (letters, hyphens, apostrophes, spaces only)
 * @return bool True if valid
 */
function validate_name($name) {
    return !empty($name) && strlen($name) <= 100 && preg_match('/^[a-zA-Z\s\'-]{2,}$/', $name);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($username === '' || $password === '' || $email === '') {
        $message = 'Please provide username, email, and password.';
    } else {
        try {
            // Connect to server (no DB) so we can create the database if it doesn't exist
            $pdoServer = get_pdo(false);
            $pdoServer->exec(sprintf("CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s COLLATE %s_general_ci", DB_NAME, DB_CHARSET, DB_CHARSET));

            // Connect to the application database
            $pdo = get_pdo(true);
            
            // Ensure schema is correct (table exists, columns exist)
            ensure_setup();

            // Validation: Username format and length
            if (strlen($username) < 3 || strlen($username) > 20) {
                $message = 'Username must be 3-20 characters long.';
            } elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
                $message = 'Username must contain only letters, numbers, underscores, and hyphens.';
            } 
            // Validation: Email format
            elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = 'Please provide a valid email address.';
            }
            // Validation: Password strength
            elseif ($passwordError = validate_password_strength($password)) {
                $message = $passwordError;
            } else {
                // Check if username already exists
                $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :u');
                $stmt->execute([':u' => $username]);
                
                if ($stmt->fetch()) {
                    $message = 'Username already taken. Please choose another.';
                }
                // Check if email already exists
                else {
                    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :e');
                    $stmt->execute([':e' => $email]);
                    
                    if ($stmt->fetch()) {
                        $message = 'Email already registered. Please use another or log in.';
                    } else {
                        // Get and validate profile fields
                        $f_name = trim($_POST['f_name'] ?? '');
                        $m_name = trim($_POST['m_name'] ?? '');
                        $l_name = trim($_POST['l_name'] ?? '');
                        $gender = $_POST['gender'] ?? '';
                        $age = (int)($_POST['age'] ?? 0);

                        // Validate name fields
                        if (!validate_name($f_name)) {
                            $message = 'First name is invalid (2-100 characters, letters/hyphens/apostrophes only).';
                        } elseif (!validate_name($l_name)) {
                            $message = 'Last name is invalid (2-100 characters, letters/hyphens/apostrophes only).';
                        } elseif (!empty($m_name) && !validate_name($m_name)) {
                            $message = 'Middle name is invalid (letters/hyphens/apostrophes only).';
                        } elseif (empty($gender) || !in_array($gender, ['Male', 'Female', 'Other'])) {
                            $message = 'Please select a valid gender.';
                        } elseif ($age < 13 || $age > 150) {
                            $message = 'Age must be between 13 and 150.';
                        } else {
                            // All validation passed - create new user
                            $hash = password_hash($password, PASSWORD_DEFAULT);
                            $ins = $pdo->prepare('INSERT INTO users (username, email, password, f_name, m_name, l_name, gender, age) VALUES (:u, :e, :p, :fn, :mn, :ln, :g, :a)');
                            $ins->execute([
                                ':u' => $username, 
                                ':e' => $email,
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
            }
        } catch (Exception $e) {
            $message = 'An error occurred during registration. Please try again.';
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="body-signup">
    <div class="container auth-container">
        <div class="card">
            <h2 style="text-align: center; margin-bottom: 2rem;">Create an account</h2>
            <?php if ($message): ?>
                <div class="alert alert-error"><?=htmlspecialchars($message)?></div>
            <?php endif; ?>
            
            <form method="post" action="signup.php">
                <div>
                    <label>First Name</label>
                    <input type="text" name="f_name" value="<?=htmlspecialchars($_POST['f_name'] ?? '')?>" required pattern="[a-zA-Z\s\'-]{2,}">
                </div>
                <div>
                    <label>Middle Name (Optional)</label>
                    <input type="text" name="m_name" value="<?=htmlspecialchars($_POST['m_name'] ?? '')?>" pattern="[a-zA-Z\s\'-]*">
                </div>
                <div>
                    <label>Last Name</label>
                    <input type="text" name="l_name" value="<?=htmlspecialchars($_POST['l_name'] ?? '')?>" required pattern="[a-zA-Z\s\'-]{2,}">
                </div>
                
                <div style="display:flex; gap: 1rem;">
                    <div style="flex:1;">
                        <label>Gender</label>
                        <select name="gender" class="form-control" style="width:100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px;" required>
                            <option value="">Select</option>
                            <option value="Male" <?= (($_POST['gender'] ?? '') === 'Male') ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= (($_POST['gender'] ?? '') === 'Female') ? 'selected' : '' ?>>Female</option>
                            <option value="Other" <?= (($_POST['gender'] ?? '') === 'Other') ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                    <div style="flex:1;">
                        <label>Age</label>
                        <input type="number" name="age" value="<?=htmlspecialchars($_POST['age'] ?? '')?>" min="13" max="150" required>
                    </div>
                </div>

                <div>
                    <label>Email Address</label>
                    <input type="email" name="email" value="<?=htmlspecialchars($email)?>" required>
                </div>

                <div>
                    <label>Username (3-20 characters)</label>
                    <input type="text" name="username" value="<?=htmlspecialchars($username)?>" required minlength="3" maxlength="20" pattern="[a-zA-Z0-9_\-]+">
                    <small style="color: grey; font-size: 0.8rem;">Letters, numbers, underscores, and hyphens only.</small>
                </div>
                <div>
                    <label>Password</label>
                    <input type="password" name="password" required minlength="8">
                    <small style="color: grey; font-size: 0.8rem;">
                        Min 8 chars • 1 uppercase • 1 number • 1 special character (!@#$%^&*-_)
                    </small>
                </div>
                
                <button type="submit" class="btn" style="width: 100%; margin-top: 1rem;">Sign Up</button>
            </form>
            <p style="text-align: center; margin-top: 1.5rem;">
                <a href="login.php">Back to login</a>
            </p>
        </div>
    </div>
</body>
</html>
