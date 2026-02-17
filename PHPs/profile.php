<?php
require_once __DIR__ . '/includes/session_guard.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/csrf.php';

$pdo = get_pdo(true);
// ensure_setup(); // Ensure columns exist

$message = '';
$error = '';

// Fetch current user
$stmt = $pdo->prepare('
    SELECT u.*, t.name as team_name, t.color as team_color, t.logo as team_logo 
    FROM users u 
    LEFT JOIN teams t ON u.team_id = t.id 
    WHERE u.username = :u
');
$stmt->execute([':u' => $_SESSION['user']]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Invalid request. Please refresh and try again.';
    } else {
        $f_name = trim($_POST['f_name'] ?? '');
        $l_name = trim($_POST['l_name'] ?? '');
        // Email and other fields if editable? Users usually can't change email easily without verification, but let's allow it for now as per old logic
        $email = trim($_POST['email'] ?? $user['email']); 
        $bio = trim($_POST['bio'] ?? '');
        
        // Ensure upload directory exists
        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Handle Profile Pic Upload
        $profilePicPath = $user['profile_pic'];
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = validate_and_upload_image($_FILES['profile_pic'], $uploadDir);
            
            if ($uploadResult['success']) {
                // Delete old profile pic if exists
                if (!empty($user['profile_pic']) && file_exists($uploadDir . $user['profile_pic'])) {
                    @unlink($uploadDir . $user['profile_pic']);
                }
                $profilePicPath = $uploadResult['filename'];
            } else {
                $error = $uploadResult['error'];
            }
        }

        // Handle Cover Photo Upload
        $coverPhotoPath = $user['cover_photo'];
        if (isset($_FILES['cover_photo']) && $_FILES['cover_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = validate_and_upload_image($_FILES['cover_photo'], $uploadDir);
            
            if ($uploadResult['success']) {
                // Delete old cover photo if exists
                if (!empty($user['cover_photo']) && file_exists($uploadDir . $user['cover_photo'])) {
                    @unlink($uploadDir . $user['cover_photo']);
                }
                $coverPhotoPath = $uploadResult['filename'];
            } else {
                $error = $uploadResult['error'];
            }
        }

        if (!$error) {
            try {
                $sql = 'UPDATE users SET f_name=:fn, l_name=:ln, email=:e, bio=:bio, profile_pic=:pfp, cover_photo=:cover WHERE id=:id';
                $upd = $pdo->prepare($sql);
                $upd->execute([
                    ':fn' => $f_name,
                    ':ln' => $l_name,
                    ':e' => $email,
                    ':bio' => $bio,
                    ':pfp' => $profilePicPath,
                    ':cover' => $coverPhotoPath,
                    ':id' => $user['id']
                ]);
                $message = 'Profile updated successfully!';
                
                // Refresh user
                $stmt->execute([':u' => $_SESSION['user']]);
                $user = $stmt->fetch();
            } catch (Exception $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Get Friends Count
$friendStmt = $pdo->prepare("SELECT COUNT(*) FROM friendships WHERE (user_id1 = :id1 OR user_id2 = :id2) AND status = 'accepted'");
$friendStmt->execute([':id1' => $user['id'], ':id2' => $user['id']]);
$friendCount = $friendStmt->fetchColumn();

// Setup Page
$userProfile = $user;
$pageTitle = 'My Profile';
$pageCss = ['newsfeed.css', 'profile.css'];
$bodyClass = 'body-profile';
require __DIR__ . '/includes/header.php';
?>

<div class="container-fluid p-0">
    <div class="row g-0">
        <?php include __DIR__ . '/includes/sidebar_layout.php'; ?>

        <!-- Main Content -->
        <main class="col-lg-9 col-xl-10 mt-0">
            <div class="container py-0 px-0">
                
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show m-3 rounded-3" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show m-3 rounded-3" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Cover Photo Area -->
                <div class="profile-cover position-relative">
                    <?php if (!empty($user['cover_photo']) && file_exists(__DIR__ . '/../uploads/' . $user['cover_photo'])): ?>
                        <img src="../uploads/<?= htmlspecialchars($user['cover_photo']) ?>" class="img-cover-full" alt="Cover">
                    <?php else: ?>
                        <div class="bg-profile-cover-fallback"></div>
                    <?php endif; ?>
                    
                    
                </div>

                <div class="container px-4 px-lg-5">
                    <div class="row">
                        <!-- Profile Header Info -->
                        <div class="col-12 position-relative">
                            <!-- Profile Pic -->
                            <div class="profile-avatar-container">
                                <?php if (!empty($user['profile_pic']) && file_exists(__DIR__ . '/../uploads/' . $user['profile_pic'])): ?>
                                    <img src="../uploads/<?= htmlspecialchars($user['profile_pic']) ?>" class="profile-avatar shadow border border-4 border-white">
                                <?php else: ?>
                                    <div class="profile-avatar shadow border border-4 border-white d-flex align-items-center justify-content-center bg-white text-primary display-4">
                                        <i class="bi bi-person-fill"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="d-flex justify-content-end align-items-center mt-3 gap-2 profile-actions">
                                <button class="btn btn-outline-secondary rounded-pill" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                                    <i class="bi bi-pencil me-1"></i> Edit Profile
                                </button>
                            </div>

                            <!-- Name & Bio -->
                            <div class="mt-2 mb-4">
                                <h2 class="fw-bold mb-0">
                                    <?= htmlspecialchars(($user['f_name'] ?? '') . ' ' . ($user['l_name'] ?? '')) ?>
                                    <?php if (!empty($user['is_admin'])): ?>
                                        <i class="bi bi-patch-check-fill text-primary ms-1 icon-verify" title="Verified Member"></i>
                                    <?php endif; ?>
                                </h2>
                                <p class="text-muted mb-2">@<?= htmlspecialchars($user['username']) ?></p>
                                
                                <?php if (!empty($user['bio'])): ?>
                                    <p class="text-muted text-pre-wrap"><?= htmlspecialchars($user['bio'] ?? 'No bio yet.') ?></p>
                                <?php else: ?>
                                    <p class="text-muted fst-italic mb-2">No bio yet.</p>
                                <?php endif; ?>

                                <!-- Stats -->
                                <div class="d-flex gap-4 mt-3 text-muted small align-items-center">
                                    <span><strong class="text-dark"><?= $friendCount ?></strong> Friends</span>
                                    <span>Joined <strong class="text-dark"><?= !empty($user['created_at']) ? date('M Y', strtotime($user['created_at'])) : 'N/A' ?></strong></span>
                                    <?php if (!empty($user['team_name'])): ?>
                                        <span class="badge rounded-pill text-white px-3 py-2 d-inline-flex align-items-center gap-2" style="background-color: <?= htmlspecialchars($user['team_color']) ?>">
                                            <?php if (!empty($user['team_logo']) && file_exists(__DIR__ . '/../uploads/' . $user['team_logo'])): ?>
                                                <img src="../uploads/<?= htmlspecialchars($user['team_logo']) ?>" class="rounded-circle border border-white team-logo-limit">
                                            <?php else: ?>
                                                <i class="bi bi-people-fill"></i>
                                            <?php endif; ?>
                                            <?= htmlspecialchars($user['team_name']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <hr class="my-4 text-muted opacity-25">

                    <!-- Content Tabs -->
                    <ul class="nav nav-pills mb-4" id="pills-tab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active rounded-pill px-4" id="pills-about-tab" data-bs-toggle="pill" data-bs-target="#pills-about" type="button" role="tab">About</button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="pills-tabContent">
                        <div class="tab-pane fade show active" id="pills-about" role="tabpanel">
                            <div class="card border-0 shadow-sm rounded-4 mb-5">
                                <div class="card-body p-4">
                                    <h5 class="fw-bold mb-4">Personal Information</h5>
                                    <div class="row g-4">
                                        <div class="col-md-6">
                                            <small class="text-muted d-block text-uppercase fw-bold label-small-caps">Email</small>
                                            <div class="fw-medium text-break">
                                                <?= !empty($user['email']) ? htmlspecialchars($user['email']) : '<span class="text-muted fst-italic">Not specified</span>' ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <small class="text-muted d-block text-uppercase fw-bold label-small-caps">Birthday</small>
                                            <div class="fw-medium">
                                                <?= !empty($user['birthdate']) ? date('F j, Y', strtotime($user['birthdate'])) : '<span class="text-muted fst-italic">Not specified</span>' ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <small class="text-muted d-block text-uppercase fw-bold label-small-caps">Gender</small>
                                            <div class="fw-medium">
                                                <?= !empty($user['gender']) ? htmlspecialchars($user['gender']) : '<span class="text-muted fst-italic">Not specified</span>' ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <small class="text-muted d-block text-uppercase fw-bold label-small-caps">Joined</small>
                                            <div class="fw-medium">
                                                <?= !empty($user['created_at']) ? date('F j, Y', strtotime($user['created_at'])) : '<span class="text-muted fst-italic">Unknown</span>' ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

            </div>
        </main>
    </div>
</div>

<!-- Edit Profile Modal -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold fs-4">Edit Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 pt-3">
                <form id="editProfileForm" method="POST" enctype="multipart/form-data">
                    <?php csrf_field(); ?>
                    
                    <!-- Profile Pic Upload -->
                    <div class="text-center mb-5">
                        <div class="mx-auto position-relative avatar-upload-wrapper">
                            <div class="rounded-circle overflow-hidden border border-3 border-light shadow-sm">
                                <?php if (!empty($user['profile_pic']) && file_exists(__DIR__ . '/../uploads/' . $user['profile_pic'])): ?>
                                    <img src="../uploads/<?= htmlspecialchars($user['profile_pic']) ?>" id="previewAvatar" class="w-100 h-100 object-fit-cover">
                                <?php else: ?>
                                    <div class="w-100 h-100 bg-light d-flex align-items-center justify-content-center text-primary display-4">
                                        <i class="bi bi-person-fill"></i>
                                    </div>
                                    <img id="previewAvatar" class="w-100 h-100 object-fit-cover d-none">
                                <?php endif; ?>
                            </div>
                            <label for="profilePicInput" class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle d-flex align-items-center justify-content-center shadow-sm avatar-upload-btn" title="Change Profile Picture">
                                <i class="bi bi-camera-fill"></i>
                            </label>
                            <input type="file" name="profile_pic" id="uploadAvatar" class="d-none" accept="image/*">
                        </div>
                        <div class="small text-muted mt-2">Update Profile Picture</div>
                    </div>

                    <div class="row g-4">
                        <div class="col-12">
                            <label class="form-label small fw-bold text-uppercase text-muted">Cover Photo</label>
                            <input type="file" name="cover_photo" class="form-control" accept="image/*">
                            <div class="form-text">Recommended size: 1200x300px.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase text-muted">First Name</label>
                            <input type="text" name="f_name" class="form-control form-control-lg" value="<?= htmlspecialchars($user['f_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase text-muted">Last Name</label>
                            <input type="text" name="l_name" class="form-control form-control-lg" value="<?= htmlspecialchars($user['l_name']) ?>" required>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label small fw-bold text-uppercase text-muted">Bio</label>
                            <textarea name="bio" class="form-control" rows="3" placeholder="Tell us a little about yourself..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-bold text-uppercase text-muted">Email</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                    </div>

                    <div class="d-grid mt-5">
                        <button type="submit" class="btn btn-primary btn-lg rounded-pill fw-bold">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php 
$pageScripts = ['profile.js'];
require __DIR__ . '/includes/footer.php'; 
?>
