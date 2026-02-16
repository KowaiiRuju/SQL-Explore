<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/mailer.php';

$pageTitle = 'Forgot Password';
$bodyClass = 'body-login'; // Use login styles for auth pages
require __DIR__ . '/includes/header.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Invalid form submission.';
    } else {
        $email = trim($_POST['email'] ?? '');
        
        if (empty($email)) {
            $error = 'Please enter your email address.';
        } else {
            try {
                // Ensure setup in case db is fresh
                ensure_setup();
                
                $pdo = get_pdo(true);
                $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = :email");
                $stmt->execute([':email' => $email]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // Generate 6-digit code
                    $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                    // Save to DB using MySQL time to avoid timezone mismatch
                    $update = $pdo->prepare("UPDATE users SET reset_token = :token, reset_expires = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id = :id");
                    $update->execute([
                        ':token' => $code,
                        ':id' => $user['id']
                    ]);
                    
                    // Send Email
                    $body = "Hi " . htmlspecialchars($user['username']) . ",\n\n";
                    $body .= "Your password reset code is: " . $code . "\n\n";
                    $body .= "This code expires in 15 minutes.\n";
                    
                    send_email($email, "Password Reset Code", $body);
                    
                    // Redirect to reset page
                    $_SESSION['reset_email'] = $email;
                    header('Location: reset_password.php');
                    exit;
                } else {
                    // Don't reveal user existence, but show same message or generic
                    // For UX, we'll just say sent if email is valid format, or "Email not found" if we don't care about enumeration
                    $error = 'No account found with that email address.';
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
                <i class="bi bi-key-fill text-primary display-4"></i>
                <h1 class="h3 mt-3">Forgot Password</h1>
                <p class="text-muted">Enter your email to receive a reset code.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error d-flex align-items-center mb-4">
                    <i class="bi bi-exclamation-circle-fill me-2"></i>
                    <div><?= $error ?></div>
                </div>
            <?php endif; ?>

            <form method="post" action="forgot_password.php">
                <?php csrf_field(); ?>
                
                <div class="mb-4">
                    <label for="email" class="form-label">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control" id="email" name="email" placeholder="you@example.com" required autofocus>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">Send Reset Code</button>
                    <a href="login.php" class="btn btn-light">Back to Login</a>
                </div>

                <div class="text-center mt-4">
                    <p class="text-muted small mb-0">
                        Did you remember your password? <a href="login.php" class="text-decoration-none fw-bold">Sign In</a>
                    </p>
                </div>
            </form>
        </div>
    </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
