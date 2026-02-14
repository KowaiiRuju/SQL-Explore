<?php
require_once __DIR__ . '/db.php';

$message = '';
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// AJAX: Check available username
if (isset($_GET['action']) && $_GET['action'] === 'check_username') {
    header('Content-Type: application/json');
    $u = trim($_GET['username'] ?? '');
    if (strlen($u) < 3 || !preg_match('/^[a-zA-Z0-9_]+$/', $u)) {
        echo json_encode(['available' => false, 'message' => 'Invalid format (3+ chars, alphanumeric/underscore)']);
        exit;
    }
    
    try {
        $pdo = get_pdo(true);
        // Ensure table exists before checking (just in case)
        ensure_setup(); 
        
        $stmt = $pdo->prepare('SELECT count(*) FROM users WHERE username = :u');
        $stmt->execute([':u' => $u]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['available' => false, 'message' => 'Username taken']);
        } else {
            echo json_encode(['available' => true]);
        }
    } catch (Exception $e) {
        // If DB doesn't exist yet, username is essentially available for the first user
        echo json_encode(['available' => true]); 
    }
    exit;
}

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
                    $birthdate = $_POST['birthdate'] ?? '';

                    if (empty($f_name) || empty($l_name) || empty($gender) || empty($birthdate)) {
                        $message = 'Please fill in all required profile fields (First Name, Last Name, Gender, Birthday).';
                    } else {
                        // Create new user
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $ins = $pdo->prepare('INSERT INTO users (username, password, f_name, m_name, l_name, gender, birthdate) VALUES (:u, :p, :fn, :mn, :ln, :g, :b)');
                        $ins->execute([
                            ':u' => $username, 
                            ':p' => $hash,
                            ':fn' => $f_name,
                            ':mn' => $m_name,
                            ':ln' => $l_name,
                            ':g' => $gender,
                            ':b' => $birthdate
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/signup.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</head>
<body class="body-signup">
    <div class="container auth-container">
        <div class="card">
            <?php if ($message): ?>
                <div class="alert alert-error"><?=htmlspecialchars($message)?></div>
            <?php endif; ?>
            
            <form method="post" action="signup.php" id="signupForm" novalidate>
                <!-- Step 1: Account -->
                <div class="step" id="step1">
                    <div class="step-header">
                        <h3>Choose a Username</h3>
                        <p>Let's find you a unique identity.</p>
                    </div>
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" id="usernameInput" value="<?=htmlspecialchars($username)?>" required minlength="3" pattern="[a-zA-Z0-9_]+" autocomplete="username">
                        <small class="form-hint" id="usernameHint">At least 3 characters, letters/numbers/underscores only.</small>
                        <div id="usernameFeedback" class="validation-feedback"></div>
                    </div>
                    <button type="button" class="btn btn-full btn-primary btn-next" onclick="nextStep(1)">Next <i class="bi bi-arrow-right"></i></button>
                </div>

                <!-- Step 2: Security -->
                <div class="step d-none" id="step2">
                    <div class="step-header">

                        <h3>Secure your Account</h3>
                        <p>Pick a strong password.</p>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" id="passwordInput" required minlength="8" autocomplete="new-password">
                        <small class="form-hint">Must be at least 8 characters long.</small>
                    </div>
                    <div class="buttons-row">
                        <button type="button" class="btn btn-secondary btn-prev" onclick="prevStep(2)">Back</button>
                        <button type="button" class="btn btn-primary btn-next" onclick="nextStep(2)">Next <i class="bi bi-arrow-right"></i></button>
                    </div>
                </div>

                <!-- Step 3: Identity -->
                <div class="step d-none" id="step3">
                    <div class="step-header">

                        <h3>Who are you?</h3>
                        <p>Tell us your name.</p>
                    </div>
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="f_name" value="<?=htmlspecialchars($_POST['f_name'] ?? '')?>" required>
                    </div>
                    <div class="form-group">
                        <label>Middle Name <span class="text-muted">(Optional)</span></label>
                        <input type="text" name="m_name" value="<?=htmlspecialchars($_POST['m_name'] ?? '')?>">
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="l_name" value="<?=htmlspecialchars($_POST['l_name'] ?? '')?>" required>
                    </div>
                    <div class="buttons-row">
                        <button type="button" class="btn btn-secondary btn-prev" onclick="prevStep(3)">Back</button>
                        <button type="button" class="btn btn-primary btn-next" onclick="nextStep(3)">Next <i class="bi bi-arrow-right"></i></button>
                    </div>
                </div>

                <!-- Step 4: Personal -->
                <div class="step d-none" id="step4">
                    <div class="step-header">
            
                        <h3>Final Details</h3>
                        <p>A bit more about you.</p>
                    </div>
                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender" class="form-control form-select" required>
                            <option value="">Select Gender</option>
                            <option value="Male" <?= (($_POST['gender'] ?? '') === 'Male') ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= (($_POST['gender'] ?? '') === 'Female') ? 'selected' : '' ?>>Female</option>
                            <option value="Other" <?= (($_POST['gender'] ?? '') === 'Other') ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Birthdate</label>
                        <input type="text" name="birthdate" id="birthdateInput" placeholder="Select your birthdate" value="<?=htmlspecialchars($_POST['birthdate'] ?? '')?>" required readonly>
                    </div>
                    <div class="buttons-row">
                        <button type="button" class="btn btn-secondary btn-prev" onclick="prevStep(4)">Back</button>
                        <button type="submit" class="btn btn-success btn-submit">Create Account <i class="bi bi-check-lg"></i></button>
                    </div>
                </div>
            </form>
            <p class="auth-link">
                <a href="login.php">Back to login</a>
            </p>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="../scripts/signup_wizard.js"></script>
    <script>
        flatpickr('#birthdateInput', {
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'F j, Y',
            maxDate: 'today',
            defaultDate: document.getElementById('birthdateInput').value || null,
            disableMobile: true
        });
    </script>
</body>
</html>
