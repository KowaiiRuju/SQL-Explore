<?php
// Sidebar Content - extracted for reuse
// Assumes $userProfile is available or needs to be fetched if included randomly.
// But mostly included in context where $userProfile exists (dashboard) or session.
$displayName = $_SESSION['user'];
if (isset($userProfile)) {
    // Gracefully handle missing name parts
    $fName = $userProfile['f_name'] ?? '';
    $lName = $userProfile['l_name'] ?? '';
    $fullName = trim($fName . ' ' . $lName);
    if (!empty($fullName)) {
        $displayName = $fullName;
    }
    
    $role = $userProfile['role'] ?? (!empty($userProfile['is_admin']) ? 'Administrator' : 'Explorer');
    $pic = $userProfile['profile_pic'] ?? '';
} else {
    $role = 'User';
    $pic = '';
}

// Detect current page for active sidebar link
$_currentPage = basename($_SERVER['SCRIPT_NAME']);
?>

<div class="sidebar-content d-flex flex-column">


    <!-- User Profile Widget -->
    <a href="profile.php" class="text-decoration-none text-dark">
        <div class="user-profile-widget p-2 rounded hover-bg-light transition-all">
            <?php if (!empty($pic) && file_exists(__DIR__ . '/../uploads/' . $pic)): ?>
                <img src="../uploads/<?= htmlspecialchars($pic) ?>" alt="User" class="user-avatar-lg shadow-sm">
            <?php else: ?>
                <div class="user-avatar-lg d-flex align-items-center justify-content-center bg-white shadow-sm mx-auto text-primary" style="font-size: 2rem;">
                    <i class="bi bi-person-fill"></i>
                </div>
            <?php endif; ?>
            <h5 class="user-name mt-2 mb-0"><?= htmlspecialchars($displayName) ?></h5>
            <p class="user-role text-muted small mb-0"><?= htmlspecialchars($role) ?></p>
        </div>
    </a>

    <!-- Navigation -->
    <nav class="nav flex-column flex-grow-1 mt-3">
        <span class="text-uppercase text-muted small fw-bold mb-3 ps-3" style="font-size: 0.75rem; letter-spacing: 1px;">Menu</span>
        
        <a class="nav-link <?= $_currentPage === 'newsfeed.php' ? 'active' : '' ?>" href="newsfeed.php">
            <i class="bi bi-columns-gap"></i> Timeline
        </a>

        <a class="nav-link d-flex justify-content-between align-items-center <?= $_currentPage === 'friends.php' || $_currentPage === 'profile_view.php' ? 'active' : '' ?>" href="friends.php">
            <span><i class="bi bi-people"></i> Friends</span>
            <?php
                try {
                    $_frPdo = get_pdo(true);
                    $_reqStmt = $_frPdo->prepare("SELECT COUNT(*) as c FROM friendships WHERE user_id2 = :uid AND status = 'pending'");
                    $_reqStmt->execute([':uid' => $userProfile['id'] ?? 0]);
                    $_reqCount = (int)$_reqStmt->fetch()['c'];
                    if ($_reqCount > 0): ?>
                        <span class="badge-notification bg-danger"><?= $_reqCount ?></span>
                    <?php endif;
                } catch (Exception $e) {}
            ?>
        </a>
        
        <?php if (!empty($userProfile['is_admin'])): ?>
        <a class="nav-link <?= $_currentPage === 'admin.php' ? 'active' : '' ?>" href="admin.php">
            <i class="bi bi-speedometer2"></i> Admin Panel
        </a>
        <a class="nav-link <?= $_currentPage === 'teams.php' ? 'active' : '' ?>" href="teams.php">
            <i class="bi bi-people-fill"></i> Teams
        </a>
        <a class="nav-link <?= $_currentPage === 'events.php' ? 'active' : '' ?>" href="events.php">
            <i class="bi bi-calendar-event"></i> Events
        </a>
        <?php endif; ?>

        <a class="nav-link <?= $_currentPage === 'profile.php' ? 'active' : '' ?>" href="profile.php">
            <i class="bi bi-person"></i> Profile
        </a>
        
        <a class="nav-link d-flex justify-content-between align-items-center <?= $_currentPage === 'messages.php' ? 'active' : '' ?>" href="messages.php">
            <span><i class="bi bi-chat-left-text"></i> Message</span>
            <?php
                try {
                    $_msgPdo = get_pdo(true);
                    $_unreadStmt = $_msgPdo->prepare('SELECT COUNT(*) as c FROM messages WHERE receiver_id = :uid AND is_read = 0');
                    $_unreadStmt->execute([':uid' => $userProfile['id'] ?? 0]);
                    $_unreadCount = (int)$_unreadStmt->fetch()['c'];
                    if ($_unreadCount > 0): ?>
                        <span class="badge-notification"><?= $_unreadCount ?></span>
                    <?php endif;
                } catch (Exception $e) {}
            ?>
        </a>
        
        
        <div class="mt-auto">
            <a class="nav-link text-danger" href="logout.php">
                <i class="bi bi-power"></i> Logout
            </a>
        </div>
    </nav>
</div>
