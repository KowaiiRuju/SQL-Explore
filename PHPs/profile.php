<?php
session_start();
require_once __DIR__ . '/db.php';

// Guard
if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$pdo = get_pdo(true);
$message = '';
$error = '';
$user = null;

// Fetch current user
try {
    // Ensure DB setup (schema update) in case it hasn't run yet
    ensure_setup();

    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :u');
    $stmt->execute([':u' => $_SESSION['user']]);
    $user = $stmt->fetch();

    if (!$user) {
        session_destroy();
        header('Location: login.php');
        exit;
    }
} catch (Exception $e) {
    $error = 'Database error: ' . htmlspecialchars($e->getMessage());
}

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $f_name = trim($_POST['f_name'] ?? '');
    $m_name = trim($_POST['m_name'] ?? '');
    $l_name = trim($_POST['l_name'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $age = (int)($_POST['age'] ?? 0);
    $uploadPath = $user['profile_pic'] ?? null; // Default to existing

    // Validation
    if (empty($f_name) || empty($l_name) || empty($gender) || $age <= 0) {
        $error = 'Please fill in all required fields (First Name, Last Name, Gender, Age).';
    } else {
        // Handle File Upload
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_pic'];
            $maxSize = 3 * 1024 * 1024; // 3MB

            // Check size
            if ($file['size'] > $maxSize) {
                $error = 'Profile picture must be under 3MB.';
            } 
            // Check type
            elseif (!str_starts_with($file['type'], 'image/')) {
                $error = 'Uploaded file must be an image.';
            } else {
                // Process upload
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'pfp_' . $user['id'] . '_' . uniqid() . '.' . $ext;
                $targetDir = __DIR__ . '/../uploads/';
                
                // Create dir if somehow missing (though we made it)
                if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

                $targetFile = $targetDir . $filename;

                if (move_uploaded_file($file['tmp_name'], $targetFile)) {
                    // Delete old PFP if exists and is not a default/external one?
                    // For now, simple replacement.
                    $uploadPath = '../uploads/' . $filename; // Relative path for DB/HTML
                } else {
                    $error = 'Failed to save uploaded file.';
                }
            }
        } elseif (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] !== UPLOAD_ERR_NO_FILE) {
             // Handle other upload errors
             $code = $_FILES['profile_pic']['error'];
             if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
                 $error = 'File is too large (server limit).';
             } else {
                 $error = 'File upload error code: ' . $code;
             }
        }
    }

    if (!$error) {
        try {
            $sql = 'UPDATE users SET f_name=:fn, m_name=:mn, l_name=:ln, gender=:g, age=:a, profile_pic=:pic WHERE id=:id';
            $upd = $pdo->prepare($sql);
            $upd->execute([
                ':fn' => $f_name,
                ':mn' => $m_name,
                ':ln' => $l_name,
                ':g' => $gender,
                ':a' => $age,
                ':pic' => $uploadPath,
                ':id' => $user['id']
            ]);
            $message = 'Profile updated successfully!';
            
            // Refresh user data
            $stmt->execute([':u' => $_SESSION['user']]);
            $user = $stmt->fetch();

        } catch (Exception $e) {
            $error = 'Update failed: ' . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - SQL Explore</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/profile.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="body-home">

    <!-- Sidebar Toggle -->
    <button class="btn btn-dark position-fixed top-0 start-0 m-3 z-3 d-flex align-items-center justify-content-center" 
            style="width: 50px; height: 50px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);"
            type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas">
        <i class="bi bi-list fs-4 text-white"></i>
    </button>

    <?php include 'sidebar.php'; ?>

    <div class="main">
        <div class="container profile-container">
            <div class="card profile-card">
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0">Edit Profile</h1>
                    <a href="index.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left me-1"></i> Dashboard
                    </a>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success d-flex align-items-center" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <div><?=htmlspecialchars($message)?></div>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex align-items-center" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <div><?=htmlspecialchars($error)?></div>
                    </div>
                <?php endif; ?>

                <?php if ($user): ?>
                <form method="post" action="profile.php" enctype="multipart/form-data">
                    
                    <div class="profile-header">
                        <div class="profile-avatar-container">
                            <?php 
                                $pfpSource = !empty($user['profile_pic']) ? $user['profile_pic'] : null;
                            ?>
                            <?php if ($pfpSource): ?>
                                <img src="<?= htmlspecialchars($pfpSource) ?>" alt="Profile" class="profile-avatar" id="avatarPreview">
                            <?php else: ?>
                                <div class="profile-avatar d-flex align-items-center justify-content-center bg-light text-secondary" id="avatarPlaceholder" style="font-size: 3rem;">
                                    <i class="bi bi-person-fill"></i>
                                </div>
                                <img src="" alt="Profile" class="profile-avatar d-none" id="avatarPreview">
                            <?php endif; ?>
                            
                            <label for="pfpInput" class="profile-avatar-overlay" title="Change Profile Picture">
                                <i class="bi bi-camera-fill"></i>
                            </label>
                            <input type="file" name="profile_pic" id="pfpInput" class="d-none" accept="image/*" onchange="previewImage(this)">
                        </div>
                        <h4 class="mb-1"><?= htmlspecialchars($user['username']) ?></h4>
                        <div class="upload-hint">Click the camera icon to update. Max 3MB.</div>
                    </div>

                    <div class="row g-4">
                        <div class="col-12">
                            <div class="form-section-title">
                                <i class="bi bi-person-vcard"></i> Personal Information
                            </div>
                        </div>

                        <?php 
                            // Helper to get value from POST or DB
                            $f_name_val = $_POST['f_name'] ?? $user['f_name'] ?? '';
                            $m_name_val = $_POST['m_name'] ?? $user['m_name'] ?? '';
                            $l_name_val = $_POST['l_name'] ?? $user['l_name'] ?? '';
                            $gender_val = $_POST['gender'] ?? $user['gender'] ?? '';
                            $age_val = $_POST['age'] ?? $user['age'] ?? '';
                        ?>

                        <div class="col-md-4">
                            <label class="form-label">First Name</label>
                            <input type="text" name="f_name" class="form-control" value="<?=htmlspecialchars($f_name_val)?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Middle Name</label>
                            <input type="text" name="m_name" class="form-control" value="<?=htmlspecialchars($m_name_val)?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="l_name" class="form-control" value="<?=htmlspecialchars($l_name_val)?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Gender</label>
                            <select name="gender" class="form-select" required>
                                <option value="">Select</option>
                                <option value="Male" <?= ($gender_val === 'Male') ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= ($gender_val === 'Female') ? 'selected' : '' ?>>Female</option>
                                <option value="Other" <?= ($gender_val === 'Other') ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Age</label>
                            <input type="number" name="age" class="form-control" value="<?=htmlspecialchars($age_val)?>" min="1" required>
                        </div>

                        <div class="col-12 mt-4 text-end">
                            <button type="submit" class="btn btn-primary px-4 py-2">
                                <i class="bi bi-save me-2"></i> Save Changes
                            </button>
                        </div>
                    </div>
                </form>
                <?php else: ?>
                    <p class="text-danger">User not found.</p>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <!-- Script for image preview -->
    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('avatarPreview');
                    const placeholder = document.getElementById('avatarPlaceholder');
                    
                    preview.src = e.target.result;
                    preview.classList.remove('d-none');
                    if (placeholder) placeholder.classList.add('d-none');
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
