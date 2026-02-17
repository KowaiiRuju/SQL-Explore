<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/helpers.php';

$message  = '';
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $message = 'Invalid form submission. Please try again.';
    } elseif ($username === '' || $password === '') {
        $message = 'Please provide username and password.';
    } else {
        try {
            // Check rate limiting BEFORE attempting login
            $rateLimitCheck = check_login_rate_limit($username);
            if (!$rateLimitCheck['allowed']) {
                $message = $rateLimitCheck['message'];
            } else {
                $pdo = get_pdo(true);
                ensure_setup();

                $q = $pdo->prepare('SELECT * FROM users WHERE username = :u OR email = :e');
                $q->execute([':u' => $username, ':e' => $username]);
                $user = $q->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user['password'])) {
                    // Record successful login attempt
                    record_login_attempt($username, true);

                    $_SESSION['user']     = $user['username'];
                    $_SESSION['is_admin'] = (bool) $user['is_admin'];

                    // Handle "Remember Me"
                    if (!empty($_POST['remember_me'])) {
                        $token     = bin2hex(random_bytes(32));
                        $tokenHash = hash('sha256', $token);

                        $upd = $pdo->prepare('UPDATE users SET remember_token = :t WHERE id = :id');
                        $upd->execute([':t' => $tokenHash, ':id' => $user['id']]);

                        // Cookie lasts 30 days
                        setcookie('remember_me', $user['id'] . ':' . $token, [
                            'expires'  => time() + (30 * 24 * 60 * 60),
                            'path'     => '/',
                            'httponly' => true,
                            'samesite' => 'Lax',
                        ]);

                        $_SESSION['remember_me'] = true;
                    } else {
                        // Start the 30-minute inactivity timer
                        $_SESSION['last_activity'] = time();
                    }

                    header('Location: newsfeed.php');
                    exit;
                } else {
                    // Record failed login attempt
                    record_login_attempt($username, false);
                    $message = 'Invalid username or password.';
                }
            }
        } catch (Exception $e) {
            $message = 'An error occurred during login. Please try again.';
            error_log('Login error: ' . $e->getMessage());
        }
    }
}

/* ── View ─────────────────────────────────────────── */
$pageTitle = 'Login - SQL Explore';
$pageCss   = ['auth_shared.css', 'login.css'];
$bodyClass = 'body-login';
require __DIR__ . '/includes/header.php';

// Branding / Heading
$welcomeText = 'Hello,<br>welcome!';
require __DIR__ . '/includes/auth_header.php';
?>

                <?php if ($message): ?>
                    <div class="alert alert-danger py-2 fs-6" role="alert"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>

                <form action="login.php" method="post" novalidate>
                    <?php csrf_field(); ?>

                    <div class="mb-3">
                        <input type="text" id="username" name="username" class="form-control stylish-input" value="<?= htmlspecialchars($username) ?>" required autocomplete="username" placeholder="Username or Email">
                    </div>

                    <div class="mb-3">
                        <div class="input-group stylish-input-group">
                            <input type="password" id="password" name="password" class="form-control stylish-input" required autocomplete="current-password" placeholder="Password">
                            <button class="btn toggle-password" type="button">
                                <i class="bi bi-eye-slash"></i>
                            </button>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="rememberMe" name="remember_me" value="1">
                            <label class="form-check-label small text-muted" for="rememberMe">
                                Remember me
                            </label>
                        </div>
                        <a href="forgot_password.php" class="small text-muted text-decoration-none">Forgot password?</a>
                    </div>

                    <div class="action-buttons">
                        <button type="submit" class="btn btn-login">Login</button>
                        <a href="signup.php" class="btn btn-signup">Sign up</a>
                    </div>

                    <?php require __DIR__ . '/includes/social_links.php'; ?>
                </form>

<?php 
require __DIR__ . '/includes/auth_footer.php';

$pageScripts = [];
require __DIR__ . '/includes/footer.php';
?>
