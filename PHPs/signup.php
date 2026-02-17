<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/helpers.php';

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
        ensure_setup();
        
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :u');
        $stmt->execute([':u' => $u]);
        if ($stmt->fetch()) {
            echo json_encode(['available' => false, 'message' => 'Username taken']);
        } else {
            echo json_encode(['available' => true]);
        }
    } catch (Exception $e) {
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
 */
function validate_password_strength($password) {
    if (strlen($password) < 8) return 'Password must be at least 8 characters long.';
    if (!preg_match('/[A-Z]/', $password)) return 'Password must contain at least one uppercase letter.';
    if (!preg_match('/[0-9]/', $password)) return 'Password must contain at least one number.';
    if (!preg_match('/[!@#$%^&*\-_]/', $password)) return 'Password must contain at least one special character (!@#$%^&*-_).';
    return '';
}

/**
 * Validate name fields
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

            if (strlen($username) < 3 || strlen($username) > 20) {
                $message = 'Username must be 3-20 characters long.';
            } elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
                $message = 'Username must contain only letters, numbers, underscores, and hyphens.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = 'Please provide a valid email address.';
            } elseif ($passwordError = validate_password_strength($password)) {
                $message = $passwordError;
            } else {
                $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :u');
                $stmt->execute([':u' => $username]);
                if ($stmt->fetch()) {
                    $message = 'Username already taken.';
                } else {
                    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :e');
                    $stmt->execute([':e' => $email]);
                    if ($stmt->fetch()) {
                        $message = 'Email already registered.';
                    } else {
                        $f_name = trim($_POST['f_name'] ?? '');
                        $l_name = trim($_POST['l_name'] ?? '');
                        $gender = $_POST['gender'] ?? '';
                        $birthdate = $_POST['birthdate'] ?? '';
                        
                        $age = 0;
                        if (!empty($birthdate)) {
                            try {
                                $dob = new DateTime($birthdate);
                                $now = new DateTime();
                                $age = $now->diff($dob)->y;
                            } catch (Exception $e) { $age = 0; }
                        }

                        if (!validate_name($f_name)) {
                            $message = 'First name is invalid.';
                        } elseif (!validate_name($l_name)) {
                            $message = 'Last name is invalid.';
                        } elseif (empty($gender) || !in_array($gender, ['Male', 'Female', 'Other'])) {
                            $message = 'Select a valid gender.';
                        } elseif (empty($birthdate)) {
                            $message = 'Enter birthdate.';
                        } elseif ($age < 13 || $age > 150) {
                            $message = 'You must be 13+ years old.';
                        } else {
                            $hash = password_hash($password, PASSWORD_DEFAULT);
                            $ins = $pdo->prepare('INSERT INTO users (username, email, password, f_name, l_name, gender, age, birthdate) VALUES (:u, :e, :p, :fn, :ln, :g, :a, :b)');
                            $ins->execute([
                                ':u' => $username, ':e' => $email, ':p' => $hash,
                                ':fn' => $f_name, ':ln' => $l_name, ':g' => $gender,
                                ':a' => $age, ':b' => $birthdate
                            ]);
                            header('Location: login.php?created=1');
                            exit;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Sign Up - SQL Explore';
$pageCss   = ['auth_shared.css', 'login.css', 'signup.css'];
$bodyClass = 'body-signup body-login';
require __DIR__ . '/includes/header.php';

// Branding / Heading
$welcomeText = 'Join us,<br>get started!';
$extraHeaderContent = '
<!-- Progress Bar -->
<div class="progress mt-4 mb-2" style="height: 6px; background: #f1f5f9; border-radius: 10px; overflow: hidden;">
    <div id="progressBar" class="progress-bar" role="progressbar" style="width: 25%; background: linear-gradient(to right, #f595a6, #d946ef); border-radius: 10px; transition: width 0.4s ease;"></div>
</div>';

require __DIR__ . '/includes/auth_header.php';
?>

            <?php if ($message): ?>
                <div class="alert alert-danger py-2 fs-6" role="alert"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form id="signupForm" method="post" action="signup.php" novalidate>
                <?php csrf_field(); ?>

                <!-- Step 1: Username -->
                <div id="step1" class="step">
                    <h6 class="mb-3 text-muted small text-uppercase fw-bold">Step 1 of 4: Username</h6>
                    <div class="mb-4">
                        <div class="input-group stylish-input-group">
                            <input type="text" name="username" id="usernameInput" class="form-control stylish-input" 
                                   required minlength="3" maxlength="20" pattern="[a-zA-Z0-9_\-]+"
                                   value="<?=htmlspecialchars($username)?>" autocomplete="off" placeholder="Username (e.g. dragon_slayer)">
                        </div>
                        <div id="usernameFeedback" class="validation-feedback mt-1 small">
                            3-20 characters: letters, numbers, _, -
                        </div>
                    </div>
                    <div class="action-buttons">
                        <button type="button" class="btn btn-login btn-next w-100" onclick="nextStep(1)">Next Step</button>
                    </div>
                </div>

                <!-- Step 2: Password -->
                <div id="step2" class="step d-none">
                    <h6 class="mb-3 text-muted small text-uppercase fw-bold">Step 2 of 4: Security</h6>
                    <div class="mb-4">
                        <div class="input-group stylish-input-group">
                            <input type="password" name="password" id="passwordInput" class="form-control stylish-input" 
                                   required minlength="8" placeholder="Create a strong password">
                            <button class="btn toggle-password" type="button">
                                <i class="bi bi-eye-slash"></i>
                            </button>
                        </div>
                        <div class="password-requirements mt-2 small text-muted">
                            <ul class="list-unstyled mb-0">
                                <li id="req-length" class="mb-1"><i class="bi bi-circle"></i> 8+ characters</li>
                                <li id="req-upper" class="mb-1"><i class="bi bi-circle"></i> Uppercase letter</li>
                                <li id="req-number" class="mb-1"><i class="bi bi-circle"></i> Number</li>
                                <li id="req-special"><i class="bi bi-circle"></i> Special char (!@#$%^&*-_)</li>
                            </ul>
                        </div>
                    </div>
                    <div class="action-buttons">
                        <button type="button" class="btn btn-signup" onclick="prevStep(2)">Back</button>
                        <button type="button" class="btn btn-login btn-next flex-grow-1" onclick="nextStep(2)">Next Step</button>
                    </div>
                </div>

                <!-- Step 3: Names -->
                <div id="step3" class="step d-none">
                    <h6 class="mb-3 text-muted small text-uppercase fw-bold">Step 3 of 4: Personal</h6>
                    <div class="mb-3">
                        <input type="text" name="f_name" class="form-control stylish-input" placeholder="First Name"
                               value="<?=htmlspecialchars($_POST['f_name'] ?? '')?>" required pattern="[a-zA-Z\s\'-]{2,}">
                    </div>
                    <div class="mb-4">
                        <input type="text" name="l_name" class="form-control stylish-input" placeholder="Last Name"
                               value="<?=htmlspecialchars($_POST['l_name'] ?? '')?>" required pattern="[a-zA-Z\s\'-]{2,}">
                    </div>
                    <div class="action-buttons">
                        <button type="button" class="btn btn-signup" onclick="prevStep(3)">Back</button>
                        <button type="button" class="btn btn-login btn-next flex-grow-1" onclick="nextStep(3)">Next Step</button>
                    </div>
                </div>

                <!-- Step 4: Final Details -->
                <div id="step4" class="step d-none">
                    <h6 class="mb-3 text-muted small text-uppercase fw-bold">Step 4 of 4: Finalize</h6>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <select name="gender" class="form-select stylish-input border-bottom" required>
                                <option value="" disabled selected>Gender</option>
                                <option value="Male" <?= (($_POST['gender'] ?? '') === 'Male') ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= (($_POST['gender'] ?? '') === 'Female') ? 'selected' : '' ?>>Female</option>
                                <option value="Other" <?= (($_POST['gender'] ?? '') === 'Other') ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <input type="date" name="birthdate" class="form-control stylish-input" 
                                   value="<?=htmlspecialchars($_POST['birthdate'] ?? '')?>" required>
                        </div>
                    </div>
                    <div class="mb-4">
                        <div class="input-group stylish-input-group">
                            <input type="email" name="email" class="form-control stylish-input" 
                                   value="<?=htmlspecialchars($email)?>" required placeholder="Email (you@example.com)">
                        </div>
                    </div>
                    <div class="action-buttons">
                        <button type="button" class="btn btn-signup" onclick="prevStep(4)">Back</button>
                        <button type="submit" class="btn btn-login flex-grow-1">Create Account</button>
                    </div>
                </div>

                <div class="login-footer mt-5">
                    <a href="login.php" class="small text-muted text-decoration-none">Already have an account? <strong>Login</strong></a>
                </div>
            </form>

<?php 
require __DIR__ . '/includes/auth_footer.php';

$pageScripts = ['signup_wizard.js'];
require __DIR__ . '/includes/footer.php';
?>


