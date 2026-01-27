<?php
session_start();
require_once __DIR__ . '/db.php';

// Use the local config in this folder which returns a configured PDO instance
$message = '';
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($username === '' || $password === '') {
        $message = 'Please provide username and password.';
    } else {
        try {
            $pdo = get_pdo(true);

            $q = $pdo->prepare('SELECT * FROM users WHERE username = :u');
            $q->execute([':u' => $username]);
            $user = $q->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // AUTHENTICATED
                $_SESSION['user'] = $user['username'];
                $_SESSION['is_admin'] = (bool)$user['is_admin'];

                header('Location: index.php');
                exit;
            } else {
                $message = 'Invalid username or password.';
            }
        } catch (Exception $e) {
            $message = 'Database error: ' . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SQL Explore</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/login.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="body-login">
    <div class="container auth-container">
        <div class="card">
            <h2 class="login-title">Login</h2>
            <?php if ($message): ?>
                <div class="alert alert-error"><?=htmlspecialchars($message)?></div>
            <?php endif; ?>
            <form action="login.php" method="post">
                <div>
                    <label for="username">Username</label>
                    <input type="text" name="username" value="<?=htmlspecialchars($username)?>" required>
                </div>
                <div>
                    <label for="password">Password</label>
                    <input type="password" name="password" required>
                </div>
                
                <button type="submit" class="btn btn-full">Log In</button>
            </form>
            <p class="auth-link">
                <a href="signup.php">Create an account?</a>
            </p>
        </div>
    </div>
</body>
</html>