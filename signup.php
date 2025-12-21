<?php
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
            // Connect without DB name first to create it if missing
            $pdo = new PDO("mysql:host={$dbHost};port={$dbPort}", $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
            
            // Connect to the specific DB
            $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            
            // Create table with is_admin column
            $pdo->exec("CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(191) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                is_admin TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            // Check if user exists
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :u');
            $stmt->execute([':u' => $username]);
            
            if ($stmt->fetch()) {
                $message = 'Username already taken.';
            } else {
                // Create new user
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $ins = $pdo->prepare('INSERT INTO users (username, password) VALUES (:u, :p)');
                $ins->execute([':u' => $username, ':p' => $hash]);
                
                header('Location: login.php?created=1');
                exit;
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
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Sign Up</title>
    <style>body{font-family:Arial,Helvetica,sans-serif;padding:20px}</style>
</head>
<body>
    <h2>Create an account</h2>
    <?php if ($message): ?>
        <div style="color:#b22222;margin-bottom:12px"><?=htmlspecialchars($message)?></div>
    <?php endif; ?>
    
    <form method="post" action="signup.php">
        <label>Username</label><br>
        <input type="text" name="username" value="<?=htmlspecialchars($username)?>" required><br>
        <label>Password</label><br>
        <input type="password" name="password" required><br><br>
        <button type="submit">Sign Up</button>
    </form>
    <p><a href="login.php">Back to login</a></p>
</body>
</html>