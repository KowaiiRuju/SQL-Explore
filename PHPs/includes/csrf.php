<?php
/**
 * CSRF Protection Utilities
 * Generates, outputs, and validates CSRF tokens stored in $_SESSION.
 */

/**
 * Get or create a CSRF token for the current session.
 */
function csrf_token(): string {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

/**
 * Output a hidden input field containing the CSRF token.
 */
function csrf_field(): void {
    echo '<input type="hidden" name="_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

/**
 * Verify the submitted CSRF token against the session token.
 * Returns true if valid, false otherwise.
 */
function csrf_verify(): bool {
    $submitted = $_POST['_token'] ?? '';
    $stored    = $_SESSION['_csrf_token'] ?? '';

    if ($submitted === '' || $stored === '') {
        return false;
    }

    return hash_equals($stored, $submitted);
}
