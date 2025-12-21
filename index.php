<?php
session_start();

// Guard: Kick out if not logged in
if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home</title>
    <style>body{font-family:Arial,Helvetica,sans-serif;padding:20px}</style>
</head>
<body>
    <h1>Welcome, <?= htmlspecialchars($_SESSION['user']) ?></h1>

    <?php if ($_SESSION['is_admin'] ?? false): ?>
        <div style="padding: 10px; border: 1px solid gold; background-color: #fffbe6; display: inline-block;">
            <strong>Admin Access Granted</strong><br>
            <a href="admin.php">Go to Admin Dashboard</a>
        </div>
    <?php endif; ?>

    <br><br>
    <form method="post" action="logout.php">
        <button type="submit">Log out</button>
    </form>
</body>
</html>