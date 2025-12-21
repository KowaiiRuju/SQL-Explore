<?php
session_start();

// Strict Security Guard:
// If user is NOT logged in OR user is NOT an admin, kick them out.
if (empty($_SESSION['user']) || empty($_SESSION['is_admin'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin Dashboard</title>
    <style>body{font-family:Arial,sans-serif;padding:20px; background-color: #f4f4f4;}</style>
</head>
<body>
    <h1>Admin Only Area</h1>
    <p>You are seeing this because you are logged in as: <strong><?= htmlspecialchars($_SESSION['user']) ?></strong></p>
    <p><a href="index.php">Back to Home</a></p>
</body>
</html>