<?php
/**
 * Shared Helper Functions for SQL-Explore
 */

/**
 * Get client IP address (handles proxies)
 * @return string Client IP address
 */
function get_client_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } else {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

/**
 * Check and record login attempts for rate limiting
 * @param string $username Username attempting to login
 * @return array ['allowed' => bool, 'message' => string, 'remaining_attempts' => int]
 */
function check_login_rate_limit($username) {
    try {
        $pdo = get_pdo(true);
        $ip = get_client_ip();
        
        // Define rate limiting parameters
        $max_attempts = 5;
        $lockout_minutes = 15;
        $cleanup_hours = 24;

        // Clean up old login attempts (older than 24 hours)
        $pdo->prepare("DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL ? HOUR)")
             ->execute([$cleanup_hours]);

        // Check failed attempts in the last 15 minutes
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as attempt_count 
            FROM login_attempts 
            WHERE username = ? AND ip_address = ? AND success = 0 
            AND attempt_time > DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ");
        $stmt->execute([$username, $ip, $lockout_minutes]);
        $result = $stmt->fetch();
        $failed_attempts = (int)$result['attempt_count'];

        if ($failed_attempts >= $max_attempts) {
            return [
                'allowed' => false,
                'message' => 'Too many failed login attempts. Please try again in ' . $lockout_minutes . ' minutes.',
                'remaining_attempts' => 0
            ];
        }

        return [
            'allowed' => true,
            'message' => '',
            'remaining_attempts' => $max_attempts - $failed_attempts
        ];
    } catch (Exception $e) {
        error_log('Rate limit check error: ' . $e->getMessage());
        // Fail open - allow login if database check fails
        return ['allowed' => true, 'message' => '', 'remaining_attempts' => 5];
    }
}

/**
 * Record a login attempt
 * @param string $username Username that attempted login
 * @param bool $success Whether login was successful
 */
function record_login_attempt($username, $success = false) {
    try {
        $pdo = get_pdo(true);
        $ip = get_client_ip();
        
        $stmt = $pdo->prepare("
            INSERT INTO login_attempts (username, ip_address, success, attempt_time)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$username, $ip, $success ? 1 : 0]);
    } catch (Exception $e) {
        error_log('Failed to record login attempt: ' . $e->getMessage());
    }
}

/**
 * Retrieve all table names from the database.
 */
function getAllTables(PDO $pdo): array {
    try {
        $stmt = $pdo->query("SHOW TABLES");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        return ['users', 'activity_logs', 'settings'];
    }
}

/**
 * Get the row count for a given table.
 */
function getTableRowCount(PDO $pdo, string $table): int {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM `" . $table . "`");
        return (int) $stmt->fetch()['count'];
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Map an activity action type to a Bootstrap Icon name.
 */
function getActivityIcon(string $actionType): string {
    $icons = [
        'login'  => 'box-arrow-in-right',
        'logout' => 'box-arrow-right',
        'query'  => 'search',
        'update' => 'pencil',
        'delete' => 'trash',
        'create' => 'plus-circle',
        'view'   => 'eye',
    ];
    return $icons[$actionType] ?? 'activity';
}

/**
 * Return a human-readable "time ago" string.
 */
function time_ago(string $datetime): string {
    $time = strtotime($datetime);
    $now  = time();
    $diff = $now - $time;

    if ($diff < 60)      return 'Just now';
    if ($diff < 3600)    return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400)   return floor($diff / 3600) . ' hours ago';
    if ($diff < 2592000) return floor($diff / 86400) . ' days ago';
    return date('M j, Y', $time);
}
/**
 * Validate and process file upload for images
 * 
 * @param array $file $_FILES array element
 * @param string $uploadDir Directory to save file to
 * @param int $maxFileSize Maximum file size in bytes (default 5MB)
 * @param array $allowedExtensions Allowed file extensions (default: jpg, jpeg, png, webp)
 * @return array ['success' => bool, 'filename' => string|null, 'error' => string|null]
 */
function validate_and_upload_image($file, $uploadDir, $maxFileSize = 5242880, $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp']) {
    $result = ['success' => false, 'filename' => null, 'error' => null];

    // Check for upload errors
    if (!isset($file['error'])) {
        $result['error'] = 'No file was uploaded.';
        return $result;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit.',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form upload limit.',
            UPLOAD_ERR_PARTIAL => 'File was partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was selected.',
            UPLOAD_ERR_NO_TMP_DIR => 'Upload directory not found.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file.',
            UPLOAD_ERR_EXTENSION => 'File upload blocked by extension handler.',
        ];
        $result['error'] = $uploadErrors[$file['error']] ?? 'Unknown upload error.';
        return $result;
    }

    // Validate file size
    if ($file['size'] > $maxFileSize) {
        $result['error'] = 'File is too large (max ' . round($maxFileSize / 1048576, 1) . 'MB).';
        return $result;
    }

    // Validate file size is not 0
    if ($file['size'] === 0) {
        $result['error'] = 'Uploaded file is empty.';
        return $result;
    }

    // Get file extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Whitelist extension check
    if (!in_array($ext, $allowedExtensions, true)) {
        $result['error'] = 'Invalid file type. Only ' . implode(', ', array_map('strtoupper', $allowedExtensions)) . ' allowed.';
        return $result;
    }

    // Validate MIME type using finfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedMimes = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
    ];

    $mimeValid = false;
    foreach ($allowedMimes as $mime => $exts) {
        if ($mimeType === $mime && in_array($ext, $exts, true)) {
            $mimeValid = true;
            break;
        }
    }

    if (!$mimeValid) {
        $result['error'] = 'File content does not match the declared format.';
        return $result;
    }

    // Check if upload directory exists and is writable
    if (!is_dir($uploadDir)) {
        if (!@mkdir($uploadDir, 0755, true)) {
            $result['error'] = 'Upload directory cannot be created.';
            return $result;
        }
    }

    if (!is_writable($uploadDir)) {
        $result['error'] = 'Upload directory is not writable.';
        return $result;
    }

    // Generate safe filename
    $filename = 'img_' . bin2hex(random_bytes(8)) . '.' . $ext;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        $result['error'] = 'Failed to move uploaded file.';
        return $result;
    }

    $result['success'] = true;
    $result['filename'] = $filename;
    return $result;
}