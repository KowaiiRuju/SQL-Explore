<?php
require_once __DIR__ . '/db.php';

// API: Check Username Availability
if (isset($_GET['action']) && $_GET['action'] === 'check_username') {
    header('Content-Type: application/json');
    $u = trim($_GET['username'] ?? '');
    
    if (strlen($u) < 3) {
        echo json_encode(['available' => false, 'message' => 'Too short']);
        exit;
    }
    
    try {
        $pdo = get_pdo(true);
        // Ensure table exists (in case it's a fresh install)
        ensure_setup();
        
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :u');
        $stmt->execute([':u' => $u]);
        if ($stmt->fetch()) {
            echo json_encode(['available' => false, 'message' => 'Username taken']);
        } else {
            echo json_encode(['available' => true]);
        }
    } catch (Exception $e) {
        // If DB not setup yet, it might fail. assume available or error.
        // But ensure_setup() above should handle it.
        echo json_encode(['available' => false, 'message' => 'Error checking']);
    }
    exit;
}

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
            $pdo = get_pdo(true);
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
                        $l_name = trim($_POST['l_name'] ?? '');
                        $gender = $_POST['gender'] ?? '';
                        $birthdate = $_POST['birthdate'] ?? '';
                        
                        // Calculate Age
                        $age = 0;
                        if (!empty($birthdate)) {
                            try {
                                $dob = new DateTime($birthdate);
                                $now = new DateTime();
                                $age = $now->diff($dob)->y;
                            } catch (Exception $e) {
                                $age = 0;
                            }
                        }

                        // Validate name fields
                        if (!validate_name($f_name)) {
                            $message = 'First name is invalid (2-100 characters, letters/hyphens/apostrophes only).';
                        } elseif (!validate_name($l_name)) {
                            $message = 'Last name is invalid (2-100 characters, letters/hyphens/apostrophes only).';
                        } elseif (empty($gender) || !in_array($gender, ['Male', 'Female', 'Other'])) {
                            $message = 'Please select a valid gender.';
                        } elseif (empty($birthdate)) {
                            $message = 'Please enter your date of birth.';
                        } elseif ($age < 13 || $age > 150) {
                            $message = 'You must be at least 13 years old to sign up.';
                        } else {
                            // All validation passed - create new user
                            $hash = password_hash($password, PASSWORD_DEFAULT);
                            $ins = $pdo->prepare('INSERT INTO users (username, email, password, f_name, l_name, gender, age, birthdate) VALUES (:u, :e, :p, :fn, :ln, :g, :a, :b)');
                            $ins->execute([
                                ':u' => $username, 
                                ':e' => $email,
                                ':p' => $hash,
                                ':fn' => $f_name,
                                ':ln' => $l_name,
                                ':g' => $gender,
                                ':a' => $age,
                                ':b' => $birthdate
                            ]);
                            
                            header('Location: login.php?created=1');
                            exit;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            error_log('Signup error: ' . $e->getMessage());
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
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Wizard transitions */
        .step {
            animation: fadeIn 0.4s ease-out;
        }
    </style>
</head>
<body class="body-signup">
    <div class="container auth-container">
        <div class="card">
            <h2 class="signup-h2 mb-4 text-center">Create an account</h2>
            
            <?php if ($message): ?>
                <div class="alert alert-error"><?=htmlspecialchars($message)?></div>
            <?php endif; ?>
            
            <form id="signupForm" method="post" action="signup.php" novalidate>
                
                <!-- Step 1: Username -->
                <div id="step1" class="step">
                    <h5 class="mb-3 text-muted">Step 1 of 4: Setup Username</h5>
                    <div class="mb-4">
                        <label class="form-label">Choose a username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-at"></i></span>
                            <input type="text" name="username" id="usernameInput" class="form-control" 
                                   required minlength="3" maxlength="20" pattern="[a-zA-Z0-9_\-]+"
                                   value="<?=htmlspecialchars($username)?>" autocomplete="off" placeholder="e.g. dragon_slayer">
                        </div>
                        <div id="usernameFeedback" class="form-text mt-2">
                            Letters, numbers, underscores, and hyphens only (3-20 chars).
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary w-100 btn-next" onclick="nextStep(1)">
                        Next <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                </div>

                <!-- Step 2: Password -->
                <div id="step2" class="step d-none">
                    <h5 class="mb-3 text-muted">Step 2 of 4: Secure your account</h5>
                    <div class="mb-4">
                        <label class="form-label">Create a password</label>
                        <div class="input-group">
                            <input type="password" name="password" id="passwordInput" class="form-control" 
                                   required minlength="8" placeholder="Enter a strong password">
                            <button class="btn btn-outline-secondary toggle-password" type="button">
                                <i class="bi bi-eye-slash"></i>
                            </button>
                        </div>
                        <div class="password-requirements mt-2">
                            <p class="mb-2 small text-muted">Password must contain:</p>
                            <ul class="list-unstyled small text-muted pl-0">
                                <li id="req-length"><i class="bi bi-circle"></i> At least 8 characters</li>
                                <li id="req-upper"><i class="bi bi-circle"></i> At least one uppercase letter</li>
                                <li id="req-number"><i class="bi bi-circle"></i> At least one number</li>
                                <li id="req-special"><i class="bi bi-circle"></i> At least one special char (!@#$%^&amp;*-_)</li>
                            </ul>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary" onclick="prevStep(2)">Back</button>
                        <button type="button" class="btn btn-primary flex-grow-1 btn-next" onclick="nextStep(2)">
                            Next <i class="bi bi-arrow-right ms-1"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 3: Names -->
                <div id="step3" class="step d-none">
                    <h5 class="mb-3 text-muted">Step 3 of 4: Personal Details</h5>
                    <div class="mb-3">
                        <label class="form-label">First Name</label>
                        <input type="text" name="f_name" class="form-control" 
                               value="<?=htmlspecialchars($_POST['f_name'] ?? '')?>" required pattern="[a-zA-Z\s\'-]{2,}">
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Last Name</label>
                        <input type="text" name="l_name" class="form-control" 
                               value="<?=htmlspecialchars($_POST['l_name'] ?? '')?>" required pattern="[a-zA-Z\s\'-]{2,}">
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary" onclick="prevStep(3)">Back</button>
                        <button type="button" class="btn btn-primary flex-grow-1 btn-next" onclick="nextStep(3)">
                            Next <i class="bi bi-arrow-right ms-1"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 4: Demographics & Email -->
                <div id="step4" class="step d-none">
                    <h5 class="mb-3 text-muted">Step 4 of 4: Final Details</h5>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Gender</label>
                            <select name="gender" class="form-select" required>
                                <option value="">Select</option>
                                <option value="Male" <?= (($_POST['gender'] ?? '') === 'Male') ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= (($_POST['gender'] ?? '') === 'Female') ? 'selected' : '' ?>>Female</option>
                                <option value="Other" <?= (($_POST['gender'] ?? '') === 'Other') ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" name="birthdate" class="form-control" 
                                   value="<?=htmlspecialchars($_POST['birthdate'] ?? '')?>" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Email Address</label>
                        <div class="input-group">
                             <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input type="email" name="email" class="form-control" 
                                   value="<?=htmlspecialchars($email)?>" required placeholder="you@example.com">
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary" onclick="prevStep(4)">Back</button>
                        <button type="submit" class="btn btn-success flex-grow-1">
                            Create Account <i class="bi bi-check-lg ms-1"></i>
                        </button>
                    </div>
                </div>

            </form>
            
            <p class="text-center mt-4 mb-0">
                <a href="login.php" class="auth-link">Back to login</a>
            </p>
        </div>
    </div>
    
    <script src="../scripts/main.js"></script>
    <script src="../scripts/signup_wizard.js"></script>
</body>
</html>
