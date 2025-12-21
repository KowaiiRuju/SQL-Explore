<?php
session_start();

// DB settings
$dbHost = '127.0.0.1';
$dbPort = 3306;
$dbUser = 'root';
$dbPass = '';
$dbName = 'sql_explore';

$message = '';
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($username === '' || $password === '') {
        $message = 'Please provide username and password.';
    } else {
        try {
            $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            
            $q = $pdo->prepare('SELECT * FROM users WHERE username = :u');
            $q->execute([':u' => $username]);
            $user = $q->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                // AUTHENTICATED
                $_SESSION['user'] = $user['username'];
                
                // --- THIS IS THE FIX ---
                // We force the database value (0 or 1) into a PHP Boolean (true or false)
                $_SESSION['is_admin'] = (bool)$user['is_admin']; 
                // -----------------------
                
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
    <title>Login</title>
    <style>body{font-family:Arial,Helvetica,sans-serif;padding:20px}</style>
</head>
<body>
    <h2>Login</h2>
    <?php if ($message): ?>
        <div style="color:#b22222;margin-bottom:12px"><?=htmlspecialchars($message)?></div>
    <?php endif; ?>
    <form action="login.php" method="post">
        <label for="username">Username</label><br>
        <input type="text" name="username" value="<?=htmlspecialchars($username)?>" required><br>
        <label for="password">Password</label><br>
        <input type="password" name="password" required><br><br>
        <input type="submit" value="Log In">
    </form>
    <p><a href="signup.php">Create an account?</a></p>
</body>
</html>