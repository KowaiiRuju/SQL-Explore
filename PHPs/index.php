<?php
session_start();

// Guard: Kick out if not logged in
if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db.php';
$pdo = get_pdo(true);

// Fetch current user details
$stmt = $pdo->prepare('SELECT * FROM users WHERE username = :u');
$stmt->execute([':u' => $_SESSION['user']]);
$userProfile = $stmt->fetch();

// Fallback if deleted or error
if (!$userProfile) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Get some stats for the dashboard
try {
    $usersCount = $pdo->query("SELECT COUNT(*) as count FROM users")->fetch()['count'];
    $adminCount = $pdo->query("SELECT COUNT(*) as count FROM users WHERE is_admin = 1")->fetch()['count'];
    
    // Get recent activity (if you have an activities/logs table)
    $recentActivity = [];
    if ($pdo->query("SHOW TABLES LIKE 'activity_logs'")->rowCount() > 0) {
        $stmt = $pdo->prepare("SELECT * FROM activity_logs WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 5");
        $stmt->execute([':user_id' => $userProfile['id']]);
        $recentActivity = $stmt->fetchAll();
    }
} catch (Exception $e) {
    // Silently fail - stats are optional
    $usersCount = 0;
    $adminCount = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SQL Explore</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/index.css">
</head>
<body class="body-home">

    <!-- Fixed Sidebar Toggle Button -->
    <button class="btn btn-dark position-fixed top-0 start-0 m-3 z-3 d-flex align-items-center justify-content-center" 
            style="width: 50px; height: 50px; border-radius: 12px;"
            type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas">
        <i class="bi bi-list fs-4"></i>
    </button>

    <!-- Include Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main" id="mainContent">
        <div class="container">
            <!-- Welcome Section -->
            <div class="welcome-section mb-4">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <?php if (!empty($userProfile['profile_pic']) && file_exists(__DIR__ . '/../uploads/' . $userProfile['profile_pic'])): ?>
                            <img src="../uploads/<?= htmlspecialchars($userProfile['profile_pic']) ?>" 
                                 alt="Profile Picture" 
                                 class="welcome-profile-pic rounded-circle"
                                 style="width: 80px; height: 80px; object-fit: cover; border: 3px solid var(--primary);">
                        <?php else: ?>
                            <div class="welcome-avatar" style="width: 80px; height: 80px; background: linear-gradient(45deg, var(--primary), #f472b6); display: flex; align-items: center; justify-content: center; border-radius: 50%;">
                                <i class="bi bi-person-fill" style="font-size: 2.5rem; color: #fff;"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col">
                        <h1 class="greeting mb-2">Hello, <?= htmlspecialchars($userProfile['f_name'] ?? $_SESSION['user']) ?>!</h1>
                        <p class="lead text-muted mb-0">
                            <?php 
                            $hour = date('H');
                            if ($hour < 12) {
                                echo "Good morning! Ready to explore the database?";
                            } elseif ($hour < 18) {
                                echo "Good afternoon! What would you like to do today?";
                            } else {
                                echo "Good evening! Time to dive into some data.";
                            }
                            ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card stat-card">
                        <div class="card-body text-center">
                            <div class="stat-icon text-primary">
                                <i class="bi bi-people-fill"></i>
                            </div>
                            <div class="stat-value"><?= $usersCount ?></div>
                            <div class="stat-label">Total Users</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card">
                        <div class="card-body text-center">
                            <div class="stat-icon text-warning">
                                <i class="bi bi-shield-check"></i>
                            </div>
                            <div class="stat-value"><?= $adminCount ?></div>
                            <div class="stat-label">Administrators</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card">
                        <div class="card-body text-center">
                            <div class="stat-icon text-success">
                                <i class="bi bi-database"></i>
                            </div>
                            <div class="stat-value"><?= count(getAllTables($pdo)) ?></div>
                            <div class="stat-label">Database Tables</div>
                        </div>
                    </div>
                </div>
            </div>



        </div>
    </div>

    <!-- Profile Modal -->
    <div class="modal fade" id="profileModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-lines-fill"></i> User Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <?php if (!empty($userProfile['profile_pic']) && file_exists(__DIR__ . '/../uploads/' . $userProfile['profile_pic'])): ?>
                            <img src="../uploads/<?= htmlspecialchars($userProfile['profile_pic']) ?>" 
                                 alt="Profile Picture" 
                                 class="rounded-circle mx-auto mb-3"
                                 style="width: 100px; height: 100px; object-fit: cover; border: 4px solid var(--primary);">
                        <?php else: ?>
                            <div class="welcome-avatar mx-auto" style="background: linear-gradient(45deg, var(--primary), #f472b6);">
                                <i class="bi bi-person-fill"></i>
                            </div>
                        <?php endif; ?>
                        <h4><?= htmlspecialchars($userProfile['f_name'] ?? '') ?> <?= htmlspecialchars($userProfile['l_name'] ?? '') ?></h4>
                        <p class="text-muted mb-2">@<?= htmlspecialchars($userProfile['username']) ?></p>
                        
                        <div class="d-flex justify-content-center gap-3 mb-3 flex-wrap">
                            <span class="badge bg-light text-dark border">
                                <i class="bi bi-gender-ambiguous"></i> <?= htmlspecialchars($userProfile['gender'] ?? 'Not set') ?>
                            </span>
                            <span class="badge bg-light text-dark border">
                                <i class="bi bi-calendar-event"></i> <?= htmlspecialchars($userProfile['age'] ?? 'N/A') ?> years
                            </span>
                            <span class="badge bg-light text-dark border">
                                <i class="bi bi-envelope"></i> <?= htmlspecialchars($userProfile['email'] ?? 'No email') ?>
                            </span>
                        </div>

                        <?php if ($userProfile['is_admin']): ?>
                            <span class="badge bg-warning text-dark px-3 py-2 mb-2">
                                <i class="bi bi-shield-check"></i> Administrator
                            </span>
                        <?php else: ?>
                            <span class="badge bg-secondary px-3 py-2 mb-2">
                                <i class="bi bi-person"></i> Regular User
                            </span>
                        <?php endif; ?>
                        
                        <p class="text-muted small mt-3">
                            <i class="bi bi-calendar-plus"></i> Joined <?= date('F j, Y', strtotime($userProfile['created_at'])) ?>
                        </p>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="card border">
                                <div class="card-body text-center">
                                    <h6 class="text-muted">Account Status</h6>
                                    <span class="badge bg-success">Active</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border">
                                <div class="card-body text-center">
                                    <h6 class="text-muted">Last Login</h6>
                                    <small><?= date('Y-m-d H:i', strtotime($userProfile['last_login'] ?? 'now')) ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="profile.php" class="btn btn-primary">
                        <i class="bi bi-gear"></i> Edit Profile
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../scripts/index.js"></script>

</body>
</html>

<?php
// Helper functions
function getAllTables($pdo) {
    try {
        $stmt = $pdo->query("SHOW TABLES");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        return ['users', 'activity_logs', 'settings'];
    }
}

function getTableRowCount($pdo, $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM " . $table);
        return $stmt->fetch()['count'];
    } catch (Exception $e) {
        return rand(10, 1000); // Fallback random number
    }
}

function getActivityIcon($actionType) {
    $icons = [
        'login' => 'box-arrow-in-right',
        'logout' => 'box-arrow-right',
        'query' => 'search',
        'update' => 'pencil',
        'delete' => 'trash',
        'create' => 'plus-circle',
        'view' => 'eye'
    ];
    return $icons[$actionType] ?? 'activity';
}

function time_ago($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff/60) . ' minutes ago';
    if ($diff < 86400) return floor($diff/3600) . ' hours ago';
    if ($diff < 2592000) return floor($diff/86400) . ' days ago';
    return date('M j, Y', $time);
}
?>