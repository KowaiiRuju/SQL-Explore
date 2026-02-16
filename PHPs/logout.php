<?php
session_start();
require_once __DIR__ . '/db.php';

// Clear the remember-me token in the database
if (!empty($_SESSION['user'])) {
    try {
        $pdo  = get_pdo(true);
        $stmt = $pdo->prepare('UPDATE users SET remember_token = NULL WHERE username = :u');
        $stmt->execute([':u' => $_SESSION['user']]);
    } catch (Exception $e) {
        // Fail silently — we still want to log out
    }
}

// Clear the remember-me cookie
if (isset($_COOKIE['remember_me'])) {
    setcookie('remember_me', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// Destroy session
session_unset();
session_destroy();

header('Location: login.php');
exit;
?>
