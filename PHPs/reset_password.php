<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/csrf.php';

$pageTitle = 'Reset Password';
$bodyClass = 'body-login';
require __DIR__ . '/includes/header.php';

$message = '';
$error = '';
$email = $_SESSION['reset_email'] ?? '';

// If no email in session, redirect to forgot password
if (empty($email)) {
    header('Location: forgot_password.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Invalid form submission.';
    } else {
        $code = trim($_POST['code'] ?? '');
        $pass = $_POST['password'] ?? '';
        $pass_confirm = $_POST['password_confirm'] ?? '';
        
        if (empty($code) || empty($pass) || empty($pass_confirm)) {
            $error = 'All fields are required.';
        } elseif ($pass !== $pass_confirm) {
            $error = 'Passwords do not match.';
        } elseif (strlen($pass) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            try {
                $pdo = get_pdo(true);
                // Check code and expiration
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email AND reset_token = :code AND reset_expires > NOW()");
                $stmt->execute([':email' => $email, ':code' => $code]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // Update password and clear token
                    $hash = password_hash($pass, PASSWORD_DEFAULT);
                    $update = $pdo->prepare("UPDATE users SET password = :pass, reset_token = NULL, reset_expires = NULL WHERE id = :id");
                    $update->execute([':pass' => $hash, ':id' => $user['id']]);
                    
                    $message = 'Password reset successfully! You can now login.';
                    unset($_SESSION['reset_email']);
                    
                    // Optional: Redirect after success
                    header('Refresh: 3; url=login.php');
                } else {
                    $error = 'Invalid or expired reset code.';
                }
            } catch (Exception $e) {
                $error = 'Database error: ' . htmlspecialchars($e->getMessage());
            }
        }
    }
}
?>

<main class="main">
    <div class="auth-container">
        <div class="card shadow-lg border-0 p-4">
            <div class="text-center mb-4">
                <i class="bi bi-shield-lock-fill text-primary display-4"></i>
                <h1 class="h3 mt-3">Reset Password</h1>
                <p class="text-muted">Enter the code sent to <strong><?= htmlspecialchars($email) ?></strong>.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error d-flex align-items-center mb-4">
                    <i class="bi bi-exclamation-circle-fill me-2"></i>
                    <div><?= $error ?></div>
                </div>
            <?php endif; ?>

            <?php if ($message): ?>
                <div class="alert alert-success d-flex align-items-center mb-4">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <div><?= $message ?></div>
                </div>
            <?php else: ?>

            <form method="post" action="reset_password.php">
                <?php csrf_field(); ?>
                
                <div class="mb-3">
                    <label for="code" class="form-label">Reset Code</label>
                    <input type="text" class="form-control text-center fs-4 letter-spacing-2" id="code" name="code" placeholder="000000" maxlength="6" required autofocus autocomplete="off">
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">New Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password" placeholder="New password" required>
                        <button class="btn btn-outline-secondary toggle-password-sync" type="button">
                            <i class="bi bi-eye-slash"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password_confirm" class="form-label">Confirm Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm" placeholder="Confirm new password" required>
                        <button class="btn btn-outline-secondary toggle-password-sync" type="button">
                            <i class="bi bi-eye-slash"></i>
                        </button>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">Reset Password</button>
                    <a href="forgot_password.php" class="btn btn-light">Resend Code</a>
                </div>
            </form>
            <?php endif; ?>

            <div class="text-center mt-4">
                <a href="login.php" class="text-decoration-none text-muted">Back to Login</a>
            </div>
        </div>
    </div>
</main>

<style>
    .letter-spacing-2 { letter-spacing: 0.5em; }
</style>

<script>
    // Synchronized Password Toggle for Reset Page
    document.addEventListener('click', function(e) {
        const toggle = e.target.closest('.toggle-password-sync');
        if (toggle) {
            // Determine current state based on the main password field
            const passwordInput = document.getElementById('password');
            const isPassword = passwordInput.type === 'password';
            
            const newType = isPassword ? 'text' : 'password';
            const removedClass = isPassword ? 'bi-eye-slash' : 'bi-eye';
            const addedClass = isPassword ? 'bi-eye' : 'bi-eye-slash';
            
            // Toggle both inputs
            ['password', 'password_confirm'].forEach(id => {
                const input = document.getElementById(id);
                if (input) input.type = newType;
            });
            
            // Toggle all icons
            document.querySelectorAll('.toggle-password-sync i').forEach(icon => {
                icon.classList.remove(removedClass);
                icon.classList.add(addedClass);
            });
        }
    });
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
