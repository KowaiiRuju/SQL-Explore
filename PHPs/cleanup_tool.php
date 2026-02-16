<?php
// cleanup_tool.php
// Access this via browser/HTTP to clean up unused files
require_once __DIR__ . '/db.php';

echo "<pre>";
try {
    $pdo = get_pdo(); 
    echo "Connected to DB via Web Context.\n";
    
    // 1. Get List
    $usedFiles = [];

    // Users
    $stmt = $pdo->query("SELECT profile_pic, cover_photo FROM users");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row['profile_pic'])) $usedFiles[] = $row['profile_pic'];
        if (!empty($row['cover_photo'])) $usedFiles[] = $row['cover_photo'];
    }

    // Teams
    $stmt = $pdo->query("SELECT logo FROM teams");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row['logo'])) $usedFiles[] = $row['logo'];
    }

    // Posts
    $stmt = $pdo->query("SELECT image FROM posts");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row['image'])) $usedFiles[] = $row['image'];
    }

    $usedFiles = array_unique($usedFiles);
    echo "Found " . count($usedFiles) . " used files.\n";

    // 2. Scan Uploads
    $uploadDir = __DIR__ . '/../uploads'; 
    $realUploadDir = realpath($uploadDir);
    
    if (!$realUploadDir || !is_dir($realUploadDir)) {
        die("Uploads dir not found at $uploadDir\n");
    }

    echo "Scanning $realUploadDir...\n";
    $files = scandir($realUploadDir);
    $deleted = 0;
    $kept = 0;

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        if (is_dir($realUploadDir . '/' . $file)) continue;

        if (!in_array($file, $usedFiles)) {
            if (unlink($realUploadDir . '/' . $file)) {
                echo "Deleted: $file\n";
                $deleted++;
            } else {
                echo "Failed to delete: $file\n";
            }
        } else {
            $kept++;
        }
    }

    echo "--------------------------\n";
    echo "Cleanup Complete.\n";
    echo "Deleted: $deleted\n";
    echo "Kept:    $kept\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
echo "</pre>";
?>
