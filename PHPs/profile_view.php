<?php
require_once __DIR__ . '/includes/session_guard.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/helpers.php';

$pdo = get_pdo(true);
ensure_setup();

// Get target user ID
$targetId = (int)($_GET['id'] ?? 0);
if (!$targetId) {
    header('Location: newsfeed.php');
    exit;
}

// Check if it's me
$stmt = $pdo->prepare('SELECT id FROM users WHERE username = :u');
$stmt->execute([':u' => $_SESSION['user']]);
$currentUser = $stmt->fetch();
$myId = (int)$currentUser['id'];

if ($targetId === $myId) {
    header('Location: profile.php');
    exit;
}

// Fetch target user
$stmt = $pdo->prepare('
    SELECT u.*, t.name as team_name, t.color as team_color, t.logo as team_logo 
    FROM users u 
    LEFT JOIN teams t ON u.team_id = t.id 
    WHERE u.id = :id
');
$stmt->execute([':id' => $targetId]);
$user = $stmt->fetch();

if (!$user) {
    // User not found
    header('Location: newsfeed.php');
    exit;
}

// Get friend status
$friendStatus = 'none'; // none, pending_sent, pending_received, accepted
$stmt = $pdo->prepare("
    SELECT user_id1, status FROM friendships 
    WHERE (user_id1 = :me AND user_id2 = :other) 
       OR (user_id1 = :other2 AND user_id2 = :me2)
");
$stmt->execute([':me' => $myId, ':other' => $targetId, ':other2' => $targetId, ':me2' => $myId]);
$friendship = $stmt->fetch();

if ($friendship) {
    if ($friendship['status'] === 'accepted') {
        $friendStatus = 'accepted';
    } elseif ($friendship['status'] === 'pending') {
        $friendStatus = ($friendship['user_id1'] == $myId) ? 'pending_sent' : 'pending_received';
    }
}

// Common vars for header
$userProfile = $currentUser; // For sidebar
$pageTitle = htmlspecialchars($user['username']) . ' - Profile';
$pageCss   = ['newsfeed.css', 'profile_view.css', 'profile.css'];
$bodyClass = 'body-dashboard';
require __DIR__ . '/includes/header.php';
?>

<div class="container-fluid p-0">
    <div class="row g-0">
        <?php include __DIR__ . '/includes/sidebar_layout.php'; ?>

        <!-- Main Content -->
        <main class="col-lg-9 col-xl-10 mt-5 mt-lg-0">
            <div class="container py-5 px-lg-5">
                
                <div class="row justify-content-center">
                    <div class="col-md-8 col-lg-6">
                        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                            <!-- Header / Banner placeholder -->
                            <div class="bg-primary text-center pt-5 pb-5 position-relative bg-profile-banner">
                                <!-- Back button -->
                                <a href="friends.php" class="btn btn-sm btn-light btn-back position-absolute top-0 start-0 m-3 rounded-circle shadow-sm" title="Back to Friends">
                                    <i class="bi bi-arrow-left"></i>
                                </a>
                            </div>

                            <div class="card-body text-center pt-0 position-relative mt-n60">
                                <!-- Profile Pic -->
                                <?php if (!empty($user['profile_pic'])): ?>
                                    <img src="../uploads/<?= htmlspecialchars($user['profile_pic']) ?>" class="rounded-circle border border-4 border-white shadow img-cover-full bg-white" width="120" height="120">
                                <?php else: ?>
                                    <div class="rounded-circle border border-4 border-white shadow mx-auto d-flex align-items-center justify-content-center bg-white text-primary profile-avatar-lg-placeholder">
                                        <i class="bi bi-person-fill"></i>
                                    </div>
                                <?php endif; ?>

                                <h3 class="mt-3 fw-bold mb-0">
                                    <?= htmlspecialchars(trim(($user['f_name'] ?? '') . ' ' . ($user['l_name'] ?? ''))) ?: htmlspecialchars($user['username']) ?>
                                </h3>
                                
                                <p class="text-muted mb-2">@<?= htmlspecialchars($user['username']) ?></p>

                                <?php if (!empty($user['team_name'])): ?>
                                    <span class="badge rounded-pill mb-3 px-3 py-2 d-inline-flex align-items-center gap-2" style="background-color: <?= htmlspecialchars($user['team_color'] ?? '#6c5ce7') ?>; color: #fff; font-weight: 500;">
                                        <?php if (!empty($user['team_logo']) && file_exists(__DIR__ . '/../uploads/' . $user['team_logo'])): ?>
                                            <img src="../uploads/<?= htmlspecialchars($user['team_logo']) ?>" class="rounded-circle border border-white team-logo-sm">
                                        <?php else: ?>
                                            <i class="bi bi-people-fill"></i>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($user['team_name']) ?>
                                    </span>
                                <?php endif; ?>

                                <!-- Comparison / Info Grid -->
                                <div class="row mt-4 mb-4 text-start px-4">
                                    <div class="col-6 mb-3">
                                        <small class="text-muted d-block text-uppercase fw-bold text-xs-bold">Joined</small>
                                        <span><?= date('F j, Y', strtotime($user['created_at'])) ?></span>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <small class="text-muted d-block text-uppercase fw-bold text-xs-bold">Role</small>
                                        <span><?= !empty($user['is_admin']) ? 'Admin' : 'Member' ?></span>
                                    </div>
                                    <!-- Sensitive info hidden -->
                                </div>

                                <!-- Action Buttons -->
                                <div class="d-flex justify-content-center gap-2 pb-3">
                                    <?php if ($friendStatus === 'accepted'): ?>
                                        <button class="btn btn-success rounded-pill px-4" disabled>
                                            <i class="bi bi-check-lg me-1"></i> Friends
                                        </button>
                                        <button class="btn btn-outline-danger rounded-pill px-3 action-btn" data-action="remove_friend" data-id="<?= $targetId ?>">
                                            <i class="bi bi-person-x"></i>
                                        </button>
                                        <a href="messages.php?user=<?= $targetId ?>" class="btn btn-primary rounded-pill px-3">
                                            <i class="bi bi-chat-dots"></i>
                                        </a>

                                    <?php elseif ($friendStatus === 'pending_sent'): ?>
                                        <button class="btn btn-secondary rounded-pill px-4 action-btn" data-action="remove_friend" data-id="<?= $targetId ?>">
                                            <i class="bi bi-x-lg me-1"></i> Cancel Request
                                        </button>

                                    <?php elseif ($friendStatus === 'pending_received'): ?>
                                        <button class="btn btn-primary rounded-pill px-4 action-btn" data-action="accept_request" data-id="<?= $targetId ?>">
                                            <i class="bi bi-person-check me-1"></i> Accept Request
                                        </button>
                                        <button class="btn btn-outline-danger rounded-pill px-3 action-btn" data-action="remove_friend" data-id="<?= $targetId ?>">
                                            <i class="bi bi-x-lg"></i>
                                        </button>

                                    <?php else: ?>
                                        <button class="btn btn-primary rounded-pill px-4 action-btn" data-action="send_request" data-id="<?= $targetId ?>">
                                            <i class="bi bi-person-plus me-1"></i> Add Friend
                                        </button>
                                    <?php endif; ?>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>
</div>

<script src="../scripts/friends.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
