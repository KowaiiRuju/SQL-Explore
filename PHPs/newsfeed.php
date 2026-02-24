<?php
require_once __DIR__ . '/includes/session_guard.php';

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/csrf.php';

$pdo = get_pdo(true);
ensure_setup();

// Fetch current user details
$stmt = $pdo->prepare('SELECT * FROM users WHERE username = :u');
$stmt->execute([':u' => $_SESSION['user']]);
$userProfile = $stmt->fetch();

if (!$userProfile) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$postMessage = '';
$postError   = '';

// Handle post creation (admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($userProfile['is_admin'])) {
    if (!csrf_verify()) {
        $postError = 'Invalid form submission.';
    } else {
        $postContent = trim($_POST['post_content'] ?? '');
        if ($postContent === '') {
            $postError = 'Post content cannot be empty.';
        } else {
            $imagePath = null;

            // Handle image upload
            if (!empty($_FILES['post_image']['name']) && $_FILES['post_image']['error'] === UPLOAD_ERR_OK) {
                $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $_FILES['post_image']['tmp_name']);
                finfo_close($finfo);

                if (in_array($mime, $allowed)) {
                    $ext = pathinfo($_FILES['post_image']['name'], PATHINFO_EXTENSION);
                    $filename = 'post_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $dest = __DIR__ . '/../uploads/' . $filename;
                    if (move_uploaded_file($_FILES['post_image']['tmp_name'], $dest)) {
                        $imagePath = $filename;
                    }
                } else {
                    $postError = 'Invalid image type. Only JPEG, PNG, GIF, and WebP are allowed.';
                }
            }

            if ($postError === '') {
                $stmt = $pdo->prepare('INSERT INTO posts (user_id, content, image) VALUES (:uid, :c, :img)');
                $stmt->execute([
                    ':uid' => $userProfile['id'],
                    ':c'   => $postContent,
                    ':img' => $imagePath,
                ]);
                // Redirect to avoid form resubmission
                header('Location: newsfeed.php');
                exit;
            }
        }
    }
}

// Stats for Right Widget
$usersCount = 0; $adminCount = 0; $tableCount = 0;
if (!empty($userProfile['is_admin'])) {
    try {
        $usersCount = $pdo->query("SELECT COUNT(*) as count FROM users")->fetch()['count'];
        $adminCount = $pdo->query("SELECT COUNT(*) as count FROM users WHERE is_admin = 1")->fetch()['count'];
        $tableCount = count(getAllTables($pdo));
    } catch (Exception $e) {}
}

// Fetch teams for leaderboard
try {
    $feedTeams = $pdo->query("SELECT t.*, (SELECT COUNT(*) FROM users u WHERE u.team_id = t.id AND (u.is_admin = 0 OR u.is_admin IS NULL)) as member_count FROM teams t ORDER BY score DESC, name ASC")->fetchAll();
} catch (Exception $e) {
    $feedTeams = [];
}

// Fetch posts with author info, like counts, comment counts
$posts = $pdo->query("
    SELECT 
        p.*,
        u.username, u.f_name, u.l_name, u.profile_pic, u.is_admin as author_is_admin,
        (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.id) as like_count,
        (SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id = p.id) as comment_count
    FROM posts p
    JOIN users u ON p.user_id = u.id
    ORDER BY p.created_at DESC
")->fetchAll();

// Get IDs of posts liked by current user
$likedPostIds = [];
$likedStmt = $pdo->prepare('SELECT post_id FROM post_likes WHERE user_id = :uid');
$likedStmt->execute([':uid' => $userProfile['id']]);
foreach ($likedStmt->fetchAll() as $row) {
    $likedPostIds[] = (int)$row['post_id'];
}

// Get latest 3 comments per post
$commentsMap = [];
foreach ($posts as $post) {
    $cStmt = $pdo->prepare('
        SELECT c.*, u.username, u.f_name, u.l_name, u.profile_pic
        FROM post_comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.post_id = :pid
        ORDER BY c.created_at DESC
        LIMIT 3
    ');
    $cStmt->execute([':pid' => $post['id']]);
    $commentsMap[$post['id']] = array_reverse($cStmt->fetchAll());
}

/* ── View ─────────────────────────────────────────── */
$pageTitle = 'Newsfeed - SQL Explore';
$pageCss   = ['newsfeed.css', 'lightbox.css'];
$bodyClass = 'body-dashboard';
require __DIR__ . '/includes/header.php';

// Helper: time ago
function timeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    if ($diff->d > 30) return date('M j, Y', strtotime($datetime));
    if ($diff->d > 0) return $diff->d . 'd ago';
    if ($diff->h > 0) return $diff->h . 'h ago';
    if ($diff->i > 0) return $diff->i . 'm ago';
    return 'Just now';
}
?>

<div class="container-fluid p-0">
    <div class="row g-0">
        
        <?php include __DIR__ . '/includes/sidebar_layout.php'; ?>

        <!-- Main Content -->
        <main class="col-lg-9 col-xl-10 mt-5 mt-lg-0">
            <div class="container-fluid py-4 px-lg-5">
                <div class="row">
                    <!-- Feed Area (Center) -->
                    <div class="col-lg-8">
                        
                        <!-- Mobile Leaderboard Toggle -->
                        <div class="d-lg-none mb-3">
                            <button class="btn btn-warning w-100 rounded-pill fw-bold" type="button" data-bs-toggle="offcanvas" data-bs-target="#leaderboardOffcanvas">
                                <i class="bi bi-trophy-fill me-2"></i>Show Leaderboard
                            </button>
                        </div>
                        
                        <?php if ($postError): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($postError) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Post Input Card (Admin Only) -->
                        <?php if (!empty($userProfile['is_admin'])): ?>
                        <div class="input-card">
                            <form method="post" enctype="multipart/form-data" id="postForm">
                                <?php csrf_field(); ?>
                                <div class="d-flex align-items-start gap-3 mb-3">
                                    <?php if (!empty($userProfile['profile_pic'])): ?>
                                        <img src="../uploads/<?= htmlspecialchars($userProfile['profile_pic']) ?>" class="rounded-circle" width="40" height="40" style="object-fit:cover;">
                                    <?php else: ?>
                                        <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center text-white" style="width:40px; height:40px; flex-shrink:0;">
                                            <i class="bi bi-person-fill"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="flex-grow-1">
                                        <div class="mb-2">
                                            <span class="fw-semibold small"><?= htmlspecialchars(trim(($userProfile['f_name'] ?? '') . ' ' . ($userProfile['l_name'] ?? ''))) ?: htmlspecialchars($userProfile['username']) ?></span>
                                            <span class="badge bg-warning text-dark ms-1" style="font-size:0.65rem;"><i class="bi bi-shield-fill me-1"></i>Admin</span>
                                        </div>
                                        <textarea class="feed-input" name="post_content" rows="2" placeholder="Share something with your team..." required></textarea>
                                    </div>
                                </div>
                                <!-- Image preview -->
                                <div id="imagePreviewContainer" class="mb-3 d-none">
                                    <div class="position-relative d-inline-block">
                                        <img id="imagePreview" src="" class="rounded" style="max-height:200px; max-width:100%;">
                                        <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1 rounded-circle" onclick="clearImagePreview()" style="width:28px; height:28px; padding:0;">
                                            <i class="bi bi-x"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="feed-actions">
                                    <div class="action-icons">
                                        <label for="postImageInput" style="cursor:pointer;" title="Attach image">
                                            <i class="bi bi-image"></i>
                                        </label>
                                        <input type="file" id="postImageInput" name="post_image" accept="image/*" class="d-none" onchange="previewPostImage(this)">
                                    </div>
                                    <button type="submit" class="btn btn-primary px-4 rounded-pill">
                                        <i class="bi bi-send me-1"></i>Post
                                    </button>
                                </div>
                            </form>
                        </div>
                        <?php endif; ?>

                        <!-- Posts Feed -->
                        <?php if (empty($posts)): ?>
                            <div class="post-card text-center py-5">
                                <i class="bi bi-newspaper text-muted" style="font-size: 3rem;"></i>
                                <h5 class="mt-3 text-muted">No posts yet</h5>
                                <p class="text-muted">
                                    <?= !empty($userProfile['is_admin']) ? 'Be the first to share something!' : 'Check back later for updates from your admin.' ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($posts as $post): ?>
                                <?php
                                    $authorName = trim(($post['f_name'] ?? '') . ' ' . ($post['l_name'] ?? '')) ?: $post['username'];
                                    $isLiked = in_array((int)$post['id'], $likedPostIds);
                                    $postComments = $commentsMap[$post['id']] ?? [];
                                ?>
                                <div class="post-card" id="post-<?= (int)$post['id'] ?>">
                                    <div class="post-header">
                                        <?php if (!empty($post['profile_pic'])): ?>
                                            <img src="../uploads/<?= htmlspecialchars($post['profile_pic']) ?>" class="post-avatar" alt="<?= htmlspecialchars($authorName) ?>" style="object-fit:cover;">
                                        <?php else: ?>
                                            <div class="post-avatar rounded-circle bg-primary d-flex align-items-center justify-content-center text-white" style="font-size:1.2rem;">
                                                <i class="bi bi-person-fill"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="post-author">
                                            <h6>
                                                <?= htmlspecialchars($authorName) ?>
                                                <?php if (!empty($post['author_is_admin'])): ?>
                                                    <span class="badge bg-warning text-dark ms-1" style="font-size:0.65rem;">Admin</span>
                                                <?php endif; ?>
                                            </h6>
                                            <span class="post-meta"><?= timeAgo($post['created_at']) ?></span>
                                        </div>
                                        <?php if (!empty($userProfile['is_admin'])): ?>
                                            <div class="ms-auto">
                                                <button class="btn btn-sm btn-danger rounded-circle" onclick="deletePost(<?= (int)$post['id'] ?>)" title="Delete post" style="width:30px; height:30px; padding:0;">
                                                    <i class="bi bi-trash text-white"></i>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="post-content">
                                        <?= nl2br(htmlspecialchars($post['content'])) ?>
                                    </div>

                                    <?php if (!empty($post['image'])): ?>
                                        <div class="post-image mt-3">
                                            <img src="../uploads/<?= htmlspecialchars($post['image']) ?>" class="rounded w-100 clickable-image" style="max-height:400px; object-fit:cover; cursor: pointer;" alt="Post image" onclick="openLightbox(this.src)">
                                        </div>
                                    <?php endif; ?>

                                    <div class="post-footer">
                                        <button class="btn btn-sm btn-link text-decoration-none like-btn <?= $isLiked ? 'liked' : '' ?>"
                                                onclick="toggleLike(<?= (int)$post['id'] ?>, this)"
                                                data-post-id="<?= (int)$post['id'] ?>">
                                            <i class="bi <?= $isLiked ? 'bi-hand-thumbs-up-fill' : 'bi-hand-thumbs-up' ?> me-1"></i>
                                            <span class="like-count"><?= (int)$post['like_count'] ?></span>
                                        </button>
                                        <button class="btn btn-sm btn-link text-decoration-none comment-toggle-btn"
                                                onclick="toggleComments(<?= (int)$post['id'] ?>)">
                                            <i class="bi bi-chat-dots me-1"></i>
                                            <span class="comment-count-<?= (int)$post['id'] ?>"><?= (int)$post['comment_count'] ?></span>
                                        </button>
                                    </div>

                                    <!-- Comments Section (collapsible) -->
                                    <div class="comments-section" id="comments-<?= (int)$post['id'] ?>" style="display:none;">
                                        <div class="comments-list" id="comments-list-<?= (int)$post['id'] ?>">
                                            <?php foreach ($postComments as $c): ?>
                                                <?php $cName = trim(($c['f_name'] ?? '') . ' ' . ($c['l_name'] ?? '')) ?: $c['username']; ?>
                                                <div class="comment-item">
                                                    <?php if (!empty($c['profile_pic'])): ?>
                                                        <img src="../uploads/<?= htmlspecialchars($c['profile_pic']) ?>" class="comment-avatar" alt="">
                                                    <?php else: ?>
                                                        <div class="comment-avatar bg-light d-flex align-items-center justify-content-center text-muted">
                                                            <i class="bi bi-person-fill" style="font-size:0.7rem;"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="comment-body">
                                                        <div class="comment-bubble">
                                                            <strong class="small"><?= htmlspecialchars($cName) ?></strong>
                                                            <p class="mb-0 small"><?= nl2br(htmlspecialchars($c['content'])) ?></p>
                                                        </div>
                                                        <small class="text-muted ms-2"><?= timeAgo($c['created_at']) ?></small>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                            <?php if ((int)$post['comment_count'] > 3): ?>
                                                <button class="btn btn-sm btn-link text-muted load-more-comments" onclick="loadAllComments(<?= (int)$post['id'] ?>)">
                                                    View all <?= (int)$post['comment_count'] ?> comments
                                                </button>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Comment Input -->
                                        <div class="comment-input-area">
                                            <div class="d-flex gap-2 align-items-center">
                                                <?php if (!empty($userProfile['profile_pic'])): ?>
                                                    <img src="../uploads/<?= htmlspecialchars($userProfile['profile_pic']) ?>" class="comment-avatar" alt="">
                                                <?php else: ?>
                                                    <div class="comment-avatar bg-primary d-flex align-items-center justify-content-center text-white">
                                                        <i class="bi bi-person-fill" style="font-size:0.7rem;"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <input type="text" class="form-control form-control-sm comment-input" 
                                                       placeholder="Write a comment..."
                                                       onkeydown="if(event.key==='Enter'){event.preventDefault();submitComment(<?= (int)$post['id'] ?>, this);}">
                                                <button class="btn btn-sm btn-primary rounded-circle" 
                                                        onclick="submitComment(<?= (int)$post['id'] ?>, this.previousElementSibling)"
                                                        style="width:32px; height:32px; padding:0; flex-shrink:0;">
                                                    <i class="bi bi-send-fill" style="font-size:0.75rem;"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                    </div>

                    <!-- Right Widgets -->
                    <div class="col-lg-4">
                        
                        <!-- Stats Widget (Real Data) -->
                        <?php if (!empty($userProfile['is_admin'])): ?>
                        <div class="widget-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="widget-title mb-0">Overview</h6>
                                <i class="bi bi-three-dots-vertical text-muted"></i>
                            </div>
                            
                            <div class="stat-mini-card">
                                <div class="stat-mini-icon">
                                    <i class="bi bi-people-fill"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold"><?= $usersCount ?></h6>
                                    <small class="text-muted">Total Users</small>
                                </div>
                            </div>
                             <div class="stat-mini-card">
                                <div class="stat-mini-icon text-warning bg-warning-subtle">
                                    <i class="bi bi-shield-check"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold"><?= $adminCount ?></h6>
                                    <small class="text-muted">Admins</small>
                                </div>
                            </div>
                             <div class="stat-mini-card">
                                <div class="stat-mini-icon text-success bg-success-subtle">
                                    <i class="bi bi-database"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold"><?= $tableCount ?></h6>
                                    <small class="text-muted">Tables</small>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Leaderboard Widget -->
                        <div class="widget-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="widget-title mb-0"><i class="bi bi-trophy-fill text-warning me-2"></i>Leaderboard</h6>
                            </div>
                            
                            <?php if (empty($feedTeams)): ?>
                                <p class="text-muted small text-center mb-0">No teams yet</p>
                            <?php else: ?>
                                <?php $rank = 1; foreach ($feedTeams as $ft): ?>
                                <div class="team-item d-flex align-items-center mb-3">
                                    <span class="fw-bold text-muted me-3" style="width: 20px; text-align: center;"><?= $rank++ ?></span>
                                    
                                    <div class="team-icon me-3 position-relative" style="background: <?= htmlspecialchars($ft['color']) ?>; width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                                        <?php if (!empty($ft['logo']) && file_exists(__DIR__ . '/../uploads/' . $ft['logo'])): ?>
                                            <img src="../uploads/<?= htmlspecialchars($ft['logo']) ?>" class="w-100 h-100 object-fit-cover">
                                        <?php else: ?>
                                            <i class="bi bi-people-fill text-white"></i>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="team-info flex-grow-1">
                                        <h6 class="mb-0 fw-bold"><?= htmlspecialchars($ft['name']) ?></h6>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-primary fw-bold"><?= (int)$ft['score'] ?> pts</small>
                                            <small class="text-muted" style="font-size: 0.75rem;"><?= (int)$ft['member_count'] ?> mem</small>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Profile Modal -->
<div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="profileModalLabel">User Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <?php $modalPic = !empty($userProfile['profile_pic']) ? $userProfile['profile_pic'] : null; ?>
                    <?php if ($modalPic): ?>
                        <img src="../uploads/<?= htmlspecialchars($modalPic) ?>" alt="Profile" class="rounded-circle mb-3" width="100" height="100" style="object-fit:cover; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                    <?php else: ?>
                        <div class="rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:100px; height:100px; background:#6c5ce7; color:#fff; font-size:2.5rem;">
                            <i class="bi bi-person-fill"></i>
                        </div>
                    <?php endif; ?>
                    <h4 class="mb-0"><?= htmlspecialchars(trim(($userProfile['f_name'] ?? '') . ' ' . ($userProfile['l_name'] ?? ''))) ?: htmlspecialchars($userProfile['username']) ?></h4>
                    <p class="text-muted mb-0">@<?= htmlspecialchars($userProfile['username']) ?></p>
                    <?php if (!empty($userProfile['is_admin'])): ?>
                        <span class="badge bg-warning text-dark mt-2"><i class="bi bi-shield-fill me-1"></i>Admin</span>
                    <?php else: ?>
                        <span class="badge bg-secondary mt-2"><i class="bi bi-person me-1"></i>User</span>
                    <?php endif; ?>
                </div>

                <div class="border-top pt-3">
                    <?php if (!empty($userProfile['email'])): ?>
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-envelope-fill text-muted me-3" style="width:20px; text-align:center;"></i>
                        <div>
                            <small class="text-muted d-block">Email</small>
                            <span><?= htmlspecialchars($userProfile['email']) ?></span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($userProfile['gender'])): ?>
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-gender-ambiguous text-muted me-3" style="width:20px; text-align:center;"></i>
                        <div>
                            <small class="text-muted d-block">Gender</small>
                            <span><?= htmlspecialchars($userProfile['gender']) ?></span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($userProfile['birthdate'])): ?>
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-calendar-event-fill text-muted me-3" style="width:20px; text-align:center;"></i>
                        <div>
                            <small class="text-muted d-block">Birthday</small>
                            <span><?= htmlspecialchars(date('F j, Y', strtotime($userProfile['birthdate']))) ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="text-center mt-4">
                    <a href="profile.php" class="btn btn-primary btn-sm rounded-pill px-4">
                        <i class="bi bi-pencil me-1"></i> Edit Profile
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Mobile Leaderboard Offcanvas -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="leaderboardOffcanvas" aria-labelledby="leaderboardOffcanvasLabel" style="background: #ffffff;">
  <div class="offcanvas-header border-bottom" style="background: #fff;">
    <h5 class="offcanvas-title fw-bold" id="leaderboardOffcanvasLabel"><i class="bi bi-trophy-fill text-warning me-2"></i>Leaderboard</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body" style="background: #f8f9fa;">
        <?php if (empty($feedTeams)): ?>
            <p class="text-muted small text-center mb-0">No teams yet</p>
        <?php else: ?>
            <?php $rank = 1; foreach ($feedTeams as $ft): ?>
            <div class="d-flex align-items-center mb-3 p-3 rounded-3 shadow-sm" style="background: #fff; border: 1px solid #e9ecef;">
                <span class="fw-bold me-3 d-flex align-items-center justify-content-center rounded-circle" style="width: 28px; height: 28px; font-size: 0.85rem; background: <?= $rank <= 3 ? ($rank === 1 ? '#ffd700' : ($rank === 2 ? '#c0c0c0' : '#cd7f32')) : '#e9ecef' ?>; color: <?= $rank <= 3 ? '#fff' : '#6c757d' ?>;"><?= $rank++ ?></span>
                
                <div class="me-3" style="background: <?= htmlspecialchars($ft['color']) ?>; width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0;">
                    <?php if (!empty($ft['logo']) && file_exists(__DIR__ . '/../uploads/' . $ft['logo'])): ?>
                        <img src="../uploads/<?= htmlspecialchars($ft['logo']) ?>" class="w-100 h-100 object-fit-cover">
                    <?php else: ?>
                        <i class="bi bi-people-fill text-white"></i>
                    <?php endif; ?>
                </div>
                
                <div class="flex-grow-1">
                    <h6 class="mb-0 fw-bold text-dark"><?= htmlspecialchars($ft['name']) ?></h6>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-primary fw-bold" style="font-size: 0.9rem;"><?= (int)$ft['score'] ?> pts</span>
                        <small class="text-secondary"><?= (int)$ft['member_count'] ?> members</small>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
  </div>
</div>

<!-- Image Lightbox Modal -->
<div class="modal fade" id="imageLightboxModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content bg-dark">
            <div class="modal-header border-secondary bg-dark">
                <h6 class="modal-title text-white d-flex align-items-center gap-2">
                    <img id="lightboxAuthorPic" src="" class="rounded-circle" style="width:40px; height:40px; object-fit:cover; background: #444; display: block;" onerror="this.style.display='none'">
                    <span id="lightboxAuthorName" class="text-white"></span>
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0 bg-dark d-flex" style="gap: 0; background: #000 !important;">
                <!-- Left side: Image (70%) -->
                <div class="flex-shrink-0" style="width: 70%; background: #000; display: flex; align-items: center; justify-content: center;">
                    <img id="lightboxImage" src="" class="img-fluid" style="max-height: 85vh; max-width: 100%; object-fit: contain;">
                </div>
                
                <!-- Right side: Post Details, Likes, Comments (30%) -->
                <div class="flex-grow-1" style="width: 30%; background: #1a1a1a; border-left: 1px solid #333; max-height: 100vh; display: flex; flex-direction: column; overflow: hidden;">
                    <!-- Post Content -->
                    <div class="p-3 border-bottom border-secondary">
                        <p id="lightboxPostContent" class="text-white mb-2"></p>
                        <small class="text-secondary" id="lightboxPostTime"></small>
                    </div>
                    
                    <!-- Stats -->
                    <div class="p-3 border-bottom border-secondary d-flex gap-3 justify-content-around">
                        <div class="text-center">
                            <div class="text-white fw-bold" id="lightboxLikeCount">0</div>
                            <small class="text-secondary">Likes</small>
                        </div>
                        <div class="text-center">
                            <div class="text-white fw-bold" id="lightboxCommentCount">0</div>
                            <small class="text-secondary">Comments</small>
                        </div>
                    </div>
                    
                    <!-- Likes Section -->
                    <div class="p-3 border-bottom border-secondary">
                        <h6 class="text-white mb-3">Likes</h6>
                        <div id="lightboxLikesList" style="max-height: 200px; overflow-y: auto;">
                            <small class="text-secondary">No likes yet</small>
                        </div>
                    </div>
                    
                    <!-- Comments Section -->
                    <div class="p-3 flex-grow-1 d-flex flex-column" style="overflow: hidden;">
                        <h6 class="text-white mb-3">Comments</h6>
                        <div id="lightboxCommentsList" class="flex-grow-1" style="overflow-y: auto; margin-bottom: 1rem;">
                            <small class="text-secondary">No comments yet</small>
                        </div>
                        
                        <!-- Comment Input -->
                        <div class="border-top border-secondary pt-3">
                            <div class="d-flex gap-2 align-items-center">
                                <?php if (!empty($userProfile['profile_pic'])): ?>
                                    <img src="../uploads/<?= htmlspecialchars($userProfile['profile_pic']) ?>" class="rounded-circle" style="width:32px; height:32px; object-fit:cover;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center text-white" style="width:32px; height:32px; font-size:0.75rem;">
                                        <i class="bi bi-person-fill"></i>
                                    </div>
                                <?php endif; ?>
                                <input type="text" id="lightboxCommentInput" class="form-control form-control-sm bg-secondary border-0 text-white" 
                                       placeholder="Write a comment..."
                                       style="font-size: 0.9rem;">
                                <button class="btn btn-sm btn-primary rounded-circle" 
                                        onclick="submitCommentFromLightbox()"
                                        style="width:32px; height:32px; padding:0; flex-shrink:0;">
                                    <i class="bi bi-send-fill" style="font-size:0.75rem;"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>



<?php
$pageScripts = ['newsfeed.js'];
require __DIR__ . '/includes/footer.php';
?>