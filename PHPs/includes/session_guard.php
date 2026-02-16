<?php
/**
 * Session Guard — centralized session timeout & remember-me logic.
 *
 * Include this file at the top of every protected page INSTEAD of
 * calling session_start() + the inline guard manually.
 *
 * Behaviour:
 *  - Normal login  → session expires after 30 minutes of inactivity.
 *  - "Remember Me" → session never expires; user stays logged in
 *                     until they explicitly log out.
 */

// 30-minute inactivity timeout (in seconds)
define('SESSION_TIMEOUT', 30 * 60);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db.php';

/**
 * Attempt auto-login via the remember_me cookie.
 * Returns true on success, false otherwise.
 */
function _try_remember_me_login(): bool
{
    if (empty($_COOKIE['remember_me'])) {
        return false;
    }

    $parts = explode(':', $_COOKIE['remember_me'], 2);
    if (count($parts) !== 2) {
        return false;
    }

    [$userId, $token] = $parts;
    $userId = (int) $userId;

    try {
        $pdo  = get_pdo(true);
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && !empty($user['remember_token']) && hash_equals($user['remember_token'], hash('sha256', $token))) {
            // Token is valid — restore the session
            $_SESSION['user']          = $user['username'];
            $_SESSION['is_admin']      = (bool) $user['is_admin'];
            $_SESSION['remember_me']   = true;
            // No last_activity needed — remember-me sessions don't expire
            return true;
        }
    } catch (Exception $e) {
        // Fail silently; user will be redirected to login
    }

    // Invalid cookie — clear it
    setcookie('remember_me', '', time() - 3600, '/');
    return false;
}

// ── Main guard logic ─────────────────────────────────────────────

if (empty($_SESSION['user'])) {
    // Session is empty — maybe the user has a remember-me cookie
    if (!_try_remember_me_login()) {
        header('Location: login.php');
        exit;
    }
}

// ── Inactivity timeout (only for NON-remember-me sessions) ───────

if (empty($_SESSION['remember_me'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        // Session has expired
        session_unset();
        session_destroy();
        header('Location: login.php?expired=1');
        exit;
    }
    // Refresh the timer on every valid request
    $_SESSION['last_activity'] = time();
}
