<?php
// Secure Entry Point
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// If the requested resource exists as a file, serve it (Router script compatibility)
if ($uri !== '/' && $uri !== '/index.php' && file_exists(__DIR__ . $uri)) {
    return false;
}

// Redirect to PHPs/index.php relative to the current script's directory
// This supports both root deployment (php -S) and subdirectory deployment (Apache)
$base = dirname($_SERVER['SCRIPT_NAME']);
// Ensure no double slashes if base is /
$base = rtrim($base, '/\\');
header('Location: ' . $base . '/PHPs/index.php');
exit;
?>
