<?php
require_once __DIR__ . '/includes/session_guard.php';
require_once __DIR__ . '/includes/csrf.php';
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

$pageTitle = 'Friends - SQL Explore';
$pageCss   = ['newsfeed.css', 'friends.css'];
$bodyClass = 'body-dashboard';
require __DIR__ . '/includes/header.php';
?>

<div class="container-fluid p-0">
    <div class="row g-0">
        <?php 
        $mobileKey = 'Friends';
        include __DIR__ . '/includes/sidebar_layout.php'; 
        ?>
        
        <!-- Mobile Action Buttons (Teleported to Navbar) -->
        <div id="mobileActionsTemplate" class="d-none">
            <button class="btn btn-light rounded-circle mobile-search-btn" type="button">
                <i class="bi bi-search"></i>
            </button>
        </div>

        <!-- Main Content -->
        <!-- Main Content -->
        <main class="col-lg-9 col-xl-10">
            <div class="container pb-4 pt-0 pt-lg-4 px-lg-5">
                
                <!-- Search Section -->
                <div class="row justify-content-center mb-4 search-container-mobile">
                    <div class="col-md-8 col-12">
                        <div class="search-box position-relative">
                            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                            <input type="text" id="friendSearchInput" class="form-control form-control-lg ps-5 rounded-pill shadow-sm" placeholder="Search for people to add..." autocomplete="off">
                            <button class="btn btn-sm btn-light position-absolute top-50 end-0 translate-middle-y me-2 rounded-circle d-none" id="clearSearchBtn">
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Search Results (Hidden by default) -->
                <div id="searchResultsSection" class="d-none mb-5">
                    <h5 class="fw-bold mb-3">Search Results</h5>
                    <div class="row g-3" id="searchResultsGrid"></div>
                    <div class="text-center mt-3">
                        <button class="btn btn-link text-decoration-none close-search-btn">Close Search</button>
                    </div>
                </div>

                <!-- Main Tabs -->
                <div id="friendsMainContent">
                    <ul class="nav nav-pills mb-4 gap-2" id="friendsTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active rounded-pill px-4" id="pills-friends-tab" data-bs-toggle="pill" data-bs-target="#pills-friends" type="button" role="tab">
                                My Friends <span class="badge bg-white text-primary ms-1" id="friendsCountBadge">0</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link rounded-pill px-4" id="pills-requests-tab" data-bs-toggle="pill" data-bs-target="#pills-requests" type="button" role="tab">
                                Friend Requests <span class="badge bg-danger ms-1 d-none" id="requestsCountBadge">0</span>
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="friendsTabContent">
                        
                        <!-- My Friends Tab -->
                        <div class="tab-pane fade show active" id="pills-friends" role="tabpanel">
                            <div class="row g-3" id="friendsGrid">
                                <div class="col-12 text-center py-5 text-muted">
                                    <div class="spinner-border text-primary" role="status"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Friend Requests Tab -->
                        <div class="tab-pane fade" id="pills-requests" role="tabpanel">
                            <div class="row g-3" id="requestsGrid">
                                <div class="col-12 text-center py-5 text-muted">
                                    <i class="bi bi-inbox" style="font-size:2rem;"></i>
                                    <p class="mt-2">No pending requests</p>
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
