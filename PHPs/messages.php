<?php
require_once __DIR__ . '/includes/session_guard.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/helpers.php';

$pdo = get_pdo(true);
ensure_setup();

// Fetch current user
$stmt = $pdo->prepare('SELECT * FROM users WHERE username = :u');
$stmt->execute([':u' => $_SESSION['user']]);
$userProfile = $stmt->fetch();

if (!$userProfile) {
    session_destroy();
    header('Location: login.php');
    exit;
}

/* ── View ─────────────────────────────────────────── */
$pageTitle = 'Messages - SQL Explore';
$pageCss   = ['newsfeed.css', 'messages.css'];
$bodyClass = 'body-dashboard';
require __DIR__ . '/includes/header.php';
?>

<div class="container-fluid p-0">
    <div class="row g-0">
        
        <?php 
        $mobileKey = 'Messages';
        include __DIR__ . '/includes/sidebar_layout.php'; 
        ?>
        
        <!-- Mobile Action Buttons (Teleported to Navbar) -->
        <div id="mobileActionsTemplate" class="d-none">
            <button class="btn btn-light rounded-circle" type="button" onclick="document.querySelector('.conversations-search input').focus(); window.scrollTo(0,0);">
                <i class="bi bi-search"></i>
            </button>
            <button class="btn btn-light rounded-circle text-primary" type="button" onclick="document.getElementById('newChatBtn').click();">
                <i class="bi bi-pencil-square"></i>
            </button>
        </div>

        <!-- Main Content -->
        <main class="col-lg-9 col-xl-10 mt-5 mt-lg-0">
            <div class="messages-container">
                
                <!-- Conversations Sidebar -->
                <div class="conversations-panel" id="conversationsPanel">
                    <div class="conversations-header">
                        <h5 class="mb-0 fw-bold">Messages</h5>
                        <button class="btn btn-sm btn-primary rounded-circle" id="newChatBtn" title="New conversation" style="width:36px; height:36px; padding:0;">
                            <i class="bi bi-pencil-square"></i>
                        </button>
                    </div>

                    <!-- Search/New Conversation -->
                    <div class="conversations-search">
                        <div class="position-relative">
                            <i class="bi bi-search position-absolute" style="left:12px; top:50%; transform:translateY(-50%); color:#b2bec3;"></i>
                            <input type="text" class="form-control" id="userSearchInput" placeholder="Search users..." style="padding-left:36px;">
                        </div>
                        <div id="searchResults" class="search-results d-none"></div>
                    </div>

                    <!-- Conversation List -->
                    <div class="conversations-list" id="conversationsList">
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-chat-dots" style="font-size:2rem;"></i>
                            <p class="small mt-2">Loading conversations...</p>
                        </div>
                    </div>
                </div>

                <!-- Chat Area -->
                <div class="chat-panel" id="chatPanel">
                    <!-- Empty State -->
                    <div class="chat-empty" id="chatEmpty">
                        <div class="text-center">
                            <i class="bi bi-chat-heart" style="font-size:4rem; color:#dfe6e9;"></i>
                            <h5 class="mt-3 text-muted">Select a conversation</h5>
                            <p class="text-muted small">Choose someone from the left or start a new conversation.</p>
                        </div>
                    </div>

                    <!-- Chat Header (hidden until user selected) -->
                    <div class="chat-header d-none" id="chatHeader">
                        <button class="btn btn-sm btn-light me-2 d-lg-none" id="backToConversations">
                            <i class="bi bi-arrow-left"></i>
                        </button>
                        <div class="d-flex align-items-center justify-content-between w-100">
                            <div class="d-flex align-items-center gap-3">
                                <div id="chatAvatar" class="chat-user-avatar"></div>
                                <div>
                                    <h6 class="mb-0 fw-bold" id="chatUserName">User</h6>
                                    <small class="text-muted" id="chatUserHandle">@username</small>
                                </div>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-light btn-sm rounded-circle" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="width:36px; height:36px; padding:0;">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="border-radius:12px;">
                                    <li><a class="dropdown-item" href="#" id="viewProfileOption"><i class="bi bi-person me-2"></i>View Profile</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="#" id="deleteConvOption"><i class="bi bi-trash me-2"></i>Delete Conversation</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Messages Area -->
                    <div class="chat-messages d-none" id="chatMessages"></div>

                    <!-- Message Input -->
                    <div class="chat-input-area d-none" id="chatInputArea">
                        <div class="d-flex gap-2 align-items-end">
                            <textarea class="form-control" id="messageInput" rows="1" placeholder="Type a message..." style="resize:none;"></textarea>
                            <button class="btn btn-primary rounded-circle" id="sendMessageBtn" style="width:42px; height:42px; padding:0; flex-shrink:0;">
                                <i class="bi bi-send-fill"></i>
                            </button>
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
                    <?php if (!empty($userProfile['profile_pic'])): ?>
                        <img src="../uploads/<?= htmlspecialchars($userProfile['profile_pic']) ?>" alt="Profile" class="rounded-circle mb-3" width="100" height="100" style="object-fit:cover; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                    <?php else: ?>
                        <div class="rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:100px; height:100px; background:#6c5ce7; color:#fff; font-size:2.5rem;">
                            <i class="bi bi-person-fill"></i>
                        </div>
                    <?php endif; ?>
                    <h4 class="mb-0"><?= htmlspecialchars(trim(($userProfile['f_name'] ?? '') . ' ' . ($userProfile['l_name'] ?? ''))) ?: htmlspecialchars($userProfile['username']) ?></h4>
                    <p class="text-muted mb-0">@<?= htmlspecialchars($userProfile['username']) ?></p>
                </div>
                <div class="text-center mt-3">
                    <a href="profile.php" class="btn btn-primary btn-sm rounded-pill px-4">
                        <i class="bi bi-pencil me-1"></i> Edit Profile
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- New Message Modal -->
<div class="modal fade" id="newMessageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold">New Message</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="input-group mb-3">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" class="form-control bg-light border-start-0" id="friendSearchInputModal" placeholder="Search friends...">
                </div>
                <div id="friendListContainer" class="list-group list-group-flush overflow-auto" style="max-height: 300px;">
                    <div class="text-center py-3 text-muted small">Loading friends...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Store current user ID for JS -->
<script>const CURRENT_USER_ID = <?= (int)$userProfile['id'] ?>;</script>

<?php
$pageScripts = ['messages.js'];
require __DIR__ . '/includes/footer.php';
?>
