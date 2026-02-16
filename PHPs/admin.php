<?php
require_once __DIR__ . '/includes/session_guard.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/csrf.php';

// Strict Security Guard: only admins may access
if (empty($_SESSION['user']) || empty($_SESSION['is_admin'])) {
    header('Location: newsfeed.php');
    exit;
}

$message = '';
$error   = '';

try {
    $pdo = get_pdo(true);
    ensure_setup();

    // Handle POST actions: add, edit, delete
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_verify()) {
            $error = 'Invalid form submission. Please try again.';
        } else {
            $act = $_POST['action'] ?? '';

            if ($act === 'add') {
                $u       = trim($_POST['username'] ?? '');
                $p       = $_POST['password'] ?? '';
                $isAdmin = !empty($_POST['is_admin']) ? 1 : 0;
                $fn      = trim($_POST['f_name'] ?? '');
                $mn      = trim($_POST['m_name'] ?? '');
                $ln      = trim($_POST['l_name'] ?? '');
                $em      = trim($_POST['email'] ?? '');
                $gen     = $_POST['gender'] ?? '';
                $bd      = $_POST['birthdate'] ?? null;
                $tid     = !empty($_POST['team_id']) ? (int)$_POST['team_id'] : null;

                if ($u === '' || $p === '') {
                    $error = 'Username and password are required to add a user.';
                } else {
                    $hash = password_hash($p, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare(
                        'INSERT INTO users (username, password, is_admin, f_name, m_name, l_name, email, gender, birthdate, team_id)
                         VALUES (:u, :p, :a, :fn, :mn, :ln, :e, :g, :b, :t)'
                    );
                    $stmt->execute([
                        ':u'  => $u,
                        ':p'  => $hash,
                        ':a'  => $isAdmin,
                        ':fn' => $fn,
                        ':mn' => $mn,
                        ':ln' => $ln,
                        ':e'  => $em,
                        ':g'  => $gen,
                        ':b'  => $bd ?: null,
                        ':t'  => $tid,
                    ]);
                    $message = 'User added.';
                }

            } elseif ($act === 'edit') {
                $id      = (int) ($_POST['id'] ?? 0);
                $u       = trim($_POST['username'] ?? '');
                $p       = $_POST['password'] ?? null;
                $isAdmin = !empty($_POST['is_admin']) ? 1 : 0;

                $fn        = trim($_POST['f_name'] ?? '');
                $mn        = trim($_POST['m_name'] ?? '');
                $ln        = trim($_POST['l_name'] ?? '');
                $em        = trim($_POST['email'] ?? '');
                $gen       = $_POST['gender'] ?? '';
                $birthdate = $_POST['birthdate'] ?? null;
                $tid       = !empty($_POST['team_id']) ? (int)$_POST['team_id'] : null;

                if ($id <= 0 || $u === '') {
                    $error = 'Invalid input for edit.';
                } else {
                    $sql    = "UPDATE users SET username=:u, is_admin=:a, f_name=:fn, m_name=:mn, l_name=:ln, email=:e, gender=:g, birthdate=:b, team_id=:t";
                    $params = [
                        ':u'  => $u,
                        ':a'  => $isAdmin,
                        ':fn' => $fn,
                        ':mn' => $mn,
                        ':ln' => $ln,
                        ':e'  => $em,
                        ':g'  => $gen,
                        ':b'  => $birthdate,
                        ':t'  => $tid,
                        ':id' => $id,
                    ];

                    if ($p !== null && $p !== '') {
                        $hash          = password_hash($p, PASSWORD_DEFAULT);
                        $sql          .= ", password=:p";
                        $params[':p']  = $hash;
                    }

                    $sql .= " WHERE id=:id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $message = 'User updated.';
                }

            } elseif ($act === 'delete') {
                $id = (int) ($_POST['id'] ?? 0);
                if ($id <= 0) {
                    $error = 'Invalid user id for deletion.';
                } elseif ($id == ($_SESSION['user_id'] ?? 0)) {
                    $error = 'You cannot delete your own account while logged in.';
                } else {
                    $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
                    $stmt->execute([':id' => $id]);
                    $message = 'User deleted.';
                }
            }
        }
    }

    // Fetch users with team names for display
    $users = $pdo->query('SELECT u.*, t.name as team_name, t.color as team_color FROM users u LEFT JOIN teams t ON u.team_id = t.id ORDER BY u.id ASC')->fetchAll(PDO::FETCH_ASSOC);

    // Fetch teams for dropdowns
    $teams = $pdo->query('SELECT id, name, color FROM teams ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

    // Fetch current user profile for sidebar
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :u');
    $stmt->execute([':u' => $_SESSION['user']]);
    $userProfile = $stmt->fetch();

} catch (Exception $e) {
    $error = 'Database error: ' . htmlspecialchars($e->getMessage());
}

/* ── View ─────────────────────────────────────────── */
$pageTitle = 'Admin Dashboard - SQL Explore';
$pageCss   = ['newsfeed.css', 'admin.css'];
$bodyClass = 'body-dashboard admin-page';
require __DIR__ . '/includes/header.php';
?>

<div class="container-fluid p-0">
    <div class="row g-0">

        <?php include __DIR__ . '/includes/sidebar_layout.php'; ?>

        <!-- Main Content -->
        <main class="col-lg-9 col-xl-10 mt-5 mt-lg-0">
            <div class="container-fluid py-4 px-lg-5">
                <div class="row">
                    <div class="col-12">

                        <!-- Page Heading -->
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h1 class="h3 mb-1">Admin Dashboard</h1>
                                <p class="text-muted small mb-0">Manage user accounts and privileges.</p>
                            </div>
                            <span class="badge bg-primary rounded-pill px-3 py-2">
                                <i class="bi bi-shield-fill" aria-hidden="true"></i> <?= htmlspecialchars($_SESSION['user']) ?>
                            </span>
                        </div>

                        <!-- Messages -->
                        <?php if ($message): ?>
                            <div class="alert alert-success d-flex align-items-center" role="alert">
                                <i class="bi bi-check-circle-fill me-2" aria-hidden="true"></i>
                                <div><?= htmlspecialchars($message) ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if ($error): ?>
                            <div class="alert alert-danger d-flex align-items-center" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>
                                <div><?= htmlspecialchars($error) ?></div>
                            </div>
                        <?php endif; ?>

                        <!-- Users Card -->
                        <div class="admin-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h2 class="h5 mb-0 fw-bold">Users</h2>
                                <button class="btn btn-success rounded-pill px-3" onclick="openAddUserModal()">
                                    <i class="bi bi-person-plus-fill me-2" aria-hidden="true"></i>Add User
                                </button>
                            </div>

                            <!-- Search & Filter Bar -->
                            <div class="search-bar mb-3">
                                <div class="d-flex gap-2 align-items-center">
                                    <div class="input-group">
                                        <span class="input-group-text bg-white"><i class="bi bi-search" aria-hidden="true"></i></span>
                                        <input type="text" id="searchInput" class="form-control" placeholder="Search username…" aria-label="Search username" oninput="filterUsers()">
                                    </div>
                                    <button type="button" class="btn btn-outline-secondary filter-toggle-btn" onclick="toggleFilterPanel()" title="Search options" aria-label="Toggle search options" aria-expanded="false" aria-controls="filterPanel">
                                        <i class="bi bi-sliders" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <div id="searchResultCount" class="text-muted small mt-1 d-none"></div>
                                <div id="filterPanel" class="filter-panel">
                                    <div class="filter-panel-inner">
                                        <div class="row g-2 align-items-center">
                                            <div class="col-md-5">
                                                <label class="form-label small text-muted mb-1" for="matchMode">Match Mode</label>
                                                <select id="matchMode" class="form-select form-select-sm" aria-label="Match mode" onchange="filterUsers()">
                                                    <option value="general">General (contains)</option>
                                                    <option value="exact">Exact match</option>
                                                </select>
                                            </div>
                                            <div class="col-md-5">
                                                <label class="form-label small text-muted mb-1" for="roleFilter">Role Filter</label>
                                                <select id="roleFilter" class="form-select form-select-sm" aria-label="Filter by role" onchange="filterUsers()">
                                                    <option value="all">All Roles</option>
                                                    <option value="admin">Admins Only</option>
                                                    <option value="user">Users Only</option>
                                                </select>
                                            </div>
                                            <div class="col-md-2 d-flex align-items-end">
                                                <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="clearFilters()" title="Clear filters" aria-label="Clear all filters">
                                                    <i class="bi bi-x-lg me-1" aria-hidden="true"></i>Clear
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th scope="col">ID</th>
                                            <th scope="col">Username</th>
                                            <th scope="col">Team</th>
                                            <th scope="col">Admin Status</th>
                                            <th scope="col">Created</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (!empty($users)): foreach ($users as $u): ?>
                                        <tr>
                                            <td><span class="text-muted">#<?= htmlspecialchars($u['id']) ?></span></td>
                                            <td class="fw-medium"><?= htmlspecialchars($u['username']) ?></td>
                                            <td>
                                                <?php if (!empty($u['team_name'])): ?>
                                                    <span class="badge rounded-pill" style="background:<?= htmlspecialchars($u['team_color'] ?? '#6c5ce7') ?>; color:#fff;">
                                                        <i class="bi bi-people-fill me-1"></i><?= htmlspecialchars($u['team_name']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted small">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($u['is_admin']): ?>
                                                    <span class="badge bg-warning text-dark"><i class="bi bi-shield-fill me-1" aria-hidden="true"></i> Admin</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary"><i class="bi bi-person me-1" aria-hidden="true"></i> User</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-muted small"><?= htmlspecialchars($u['created_at']) ?></td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" aria-label="Edit user <?= htmlspecialchars($u['username']) ?>" onclick="openEditUserModal(<?= (int)$u['id'] ?>)">
                                                        <i class="bi bi-pencil" aria-hidden="true"></i>
                                                    </button>
                                                    <form method="post" action="admin.php" onsubmit="return confirm('Delete this user?');" class="d-inline">
                                                        <?php csrf_field(); ?>
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?= htmlspecialchars($u['id']) ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Delete user <?= htmlspecialchars($u['username']) ?>">
                                                            <i class="bi bi-trash" aria-hidden="true"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; else: ?>
                                        <tr><td colspan="6" class="text-center py-4 text-muted">No users found.</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Add User Modal -->
<div id="addUserModal" class="modal fade" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
                <button type="button" class="btn-close" onclick="closeAddUserModal()" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="post" action="admin.php" id="addUserForm">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="add">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="newUsername">Username <span class="text-danger">*</span></label>
                            <input type="text" id="newUsername" name="username" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="newPassword">Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" id="newPassword" name="password" class="form-control" required>
                                <button class="btn btn-outline-secondary toggle-password" type="button">
                                    <i class="bi bi-eye-slash"></i>
                                </button>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="newFname">First Name</label>
                            <input type="text" id="newFname" name="f_name" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="newMname">Middle Name</label>
                            <input type="text" id="newMname" name="m_name" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="newLname">Last Name</label>
                            <input type="text" id="newLname" name="l_name" class="form-control">
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="newEmail">Email</label>
                            <input type="email" id="newEmail" name="email" class="form-control" placeholder="you@example.com">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="newGender">Gender</label>
                            <select id="newGender" name="gender" class="form-select">
                                <option value="">Select</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="newBirthdate">Birthday</label>
                            <input type="date" id="newBirthdate" name="birthdate" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="newTeamId">Team</label>
                            <select id="newTeamId" name="team_id" class="form-select">
                                <option value="">No Team</option>
                                <?php foreach ($teams as $t): ?>
                                    <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_admin" value="1" id="newIsAdmin">
                                <label class="form-check-label" for="newIsAdmin">Grant Admin Privileges</label>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary" onclick="closeAddUserModal()">Cancel</button>
                        <button type="submit" class="btn btn-success"><i class="bi bi-person-plus-fill me-1" aria-hidden="true"></i>Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" class="modal fade" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="post" action="admin.php" id="editUserForm">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="editUserId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="editUsername">Username</label>
                            <input type="text" id="editUsername" name="username" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="editPassword">Password <small class="text-muted">(leave blank to keep)</small></label>
                            <input type="password" id="editPassword" name="password" class="form-control">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="editFname">First Name</label>
                            <input type="text" id="editFname" name="f_name" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="editMname">Middle Name</label>
                            <input type="text" id="editMname" name="m_name" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="editLname">Last Name</label>
                            <input type="text" id="editLname" name="l_name" class="form-control">
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="editEmail">Email</label>
                            <input type="email" id="editEmail" name="email" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="editGender">Gender</label>
                            <select id="editGender" name="gender" class="form-select">
                                <option value="">Select</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="editBirthdate">Birthday</label>
                            <input type="date" id="editBirthdate" name="birthdate" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="editTeamId">Team</label>
                            <select id="editTeamId" name="team_id" class="form-select">
                                <option value="">No Team</option>
                                <?php foreach ($teams as $t): ?>
                                    <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_admin" value="1" id="editIsAdmin">
                                <label class="form-check-label" for="editIsAdmin">Grant Admin Privileges</label>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Pass user data to JS for the edit modal -->
<script>
    const usersData = <?= json_encode(array_map(function($u) {
        return [
            'id'        => (int) $u['id'],
            'username'  => $u['username'],
            'f_name'    => $u['f_name'] ?? '',
            'm_name'    => $u['m_name'] ?? '',
            'l_name'    => $u['l_name'] ?? '',
            'email'     => $u['email'] ?? '',
            'gender'    => $u['gender'] ?? '',
            'birthdate' => $u['birthdate'] ?? '',
            'is_admin'  => (int)($u['is_admin'] ?? 0),
            'team_id'   => $u['team_id'] ? (int)$u['team_id'] : '',
        ];
    }, $users ?? []), JSON_HEX_TAG) ?>;
</script>

<?php
$pageScripts = ['admin.js'];
require __DIR__ . '/includes/footer.php';
?>
