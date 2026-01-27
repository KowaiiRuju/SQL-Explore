<?php
session_start();
require_once __DIR__ . '/db.php';

// Strict Security Guard: only admins may access
if (empty($_SESSION['user']) || empty($_SESSION['is_admin'])) {
    header('Location: index.php');
    exit;
}

    $message = '';
    $error = '';

    try {
        $pdo = get_pdo(true);

        // Ensure users table exists (idempotent)
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(191) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            is_admin TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=" . DB_CHARSET . ";");

        // Handle POST actions: add, edit, delete
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $act = $_POST['action'] ?? '';

            if ($act === 'add') {
                $u = trim($_POST['username'] ?? '');
                $p = $_POST['password'] ?? '';
                $isAdmin = !empty($_POST['is_admin']) ? 1 : 0;

                if ($u === '' || $p === '') {
                    $error = 'Username and password are required to add a user.';
                } else {
                    $hash = password_hash($p, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare('INSERT INTO users (username, password, is_admin) VALUES (:u,:p,:a)');
                    $stmt->execute([':u' => $u, ':p' => $hash, ':a' => $isAdmin]);
                    $message = 'User added.';
                }
            } elseif ($act === 'edit') {
                $id = (int)($_POST['id'] ?? 0);
                $u = trim($_POST['username'] ?? '');
                $p = $_POST['password'] ?? null;
                $isAdmin = !empty($_POST['is_admin']) ? 1 : 0;
                
                // Profile fields
                $fn = trim($_POST['f_name'] ?? '');
                $mn = trim($_POST['m_name'] ?? '');
                $ln = trim($_POST['l_name'] ?? '');
                $gen = $_POST['gender'] ?? '';
                $age = (int)($_POST['age'] ?? 0);

                if ($id <= 0 || $u === '') {
                    $error = 'Invalid input for edit.';
                } else {
                    $sql = "UPDATE users SET username=:u, is_admin=:a, f_name=:fn, m_name=:mn, l_name=:ln, gender=:g, age=:age";
                    $params = [
                        ':u' => $u, 
                        ':a' => $isAdmin, 
                        ':fn' => $fn, 
                        ':mn' => $mn, 
                        ':ln' => $ln, 
                        ':g' => $gen, 
                        ':age' => $age,
                        ':id' => $id
                    ];

                    if ($p !== null && $p !== '') {
                        $hash = password_hash($p, PASSWORD_DEFAULT);
                        $sql .= ", password=:p";
                        $params[':p'] = $hash;
                    }
                    
                    $sql .= " WHERE id=:id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    
                    $message = 'User updated.';
                }
            } elseif ($act === 'delete') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) {
                    $error = 'Invalid user id for deletion.';
                } else {
                    // Prevent admin from deleting themselves
                    if ($id == ($_SESSION['user_id'] ?? 0)) {
                        $error = 'You cannot delete your own account while logged in.';
                    } else {
                        $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
                        $stmt->execute([':id' => $id]);
                        $message = 'User deleted.';
                    }
                }
            }
        }

        // Fetch users for display
        $users = $pdo->query('SELECT * FROM users ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        $error = 'Database error: ' . htmlspecialchars($e->getMessage());
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Admin Dashboard</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <link rel="stylesheet" href="../css/style.css">
        <link rel="stylesheet" href="../css/admin.css">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    </head>
    <body class="body-home"> <!-- Reusing body-home for background compliance -->

        <!-- Fixed Sidebar Toggle Button -->
        <button class="btn btn-dark position-fixed top-0 start-0 m-3 z-3 d-flex align-items-center justify-content-center" 
                style="width: 50px; height: 50px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);"
                type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas">
            <i class="bi bi-list fs-4 text-white"></i>
        </button>

        <?php include 'sidebar.php'; ?>

        <div class="main">
            <div class="container">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h1 class="mb-0">Admin Dashboard</h1>
                            <span class="badge bg-primary rounded-pill px-3 py-2">
                                <i class="bi bi-person-fill"></i> <?= htmlspecialchars($_SESSION['user']) ?>
                            </span>
                        </div>
                        
                        <!-- Messages -->
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

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h2 class="h4 mb-1">Users</h2>
                                <p class="text-muted small mb-0">Manage user accounts and privileges.</p>
                            </div>
                            <button class="btn btn-success" onclick="openAddUserModal()">
                                <i class="bi bi-person-plus-fill me-2"></i>Add User
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Admin Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (!empty($users)): foreach ($users as $u): ?>
                                    <tr>
                                        <td><span class="text-muted">#<?= htmlspecialchars($u['id']) ?></span></td>
                                        <td class="fw-medium"><?= htmlspecialchars($u['username']) ?></td>
                                        <td>
                                            <?php if ($u['is_admin']): ?>
                                                <span class="badge bg-warning text-dark"><i class="bi bi-shield-fill me-1"></i> Admin</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary"><i class="bi bi-person me-1"></i> User</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-muted small"><?= htmlspecialchars($u['created_at']) ?></td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a href="admin.php?action=edit&id=<?=urlencode($u['id'])?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <form method="post" action="admin.php" onsubmit="return confirm('Delete this user?');" class="d-inline">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?=htmlspecialchars($u['id'])?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="5" class="text-center py-4 text-muted">No users found.</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Edit Section (Conditional) -->
                        <?php
                        $editUser = null;
                        if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
                            $id = (int)$_GET['id'];
                            foreach ($users as $uu) if ($uu['id'] == $id) { $editUser = $uu; break; }
                        }
                        ?>
                        <?php if ($editUser): ?>
                        <div class="mt-5 p-4 bg-light rounded-3 border">
                            <h3 class="h5 mb-3">Edit User: <span class="text-primary"><?=htmlspecialchars($editUser['username'])?></span></h3>
                            <form method="post" action="admin.php">
                                <input type="hidden" name="action" value="edit">
                                <input type="hidden" name="id" value="<?=htmlspecialchars($editUser['id'])?>">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Username</label>
                                        <input type="text" name="username" class="form-control" value="<?=htmlspecialchars($editUser['username'] ?? '')?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Password <small class="text-muted">(leave blank to keep)</small></label>
                                        <input type="password" name="password" class="form-control">
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label class="form-label">First Name</label>
                                        <input type="text" name="f_name" class="form-control" value="<?=htmlspecialchars($editUser['f_name'] ?? '')?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Middle Name</label>
                                        <input type="text" name="m_name" class="form-control" value="<?=htmlspecialchars($editUser['m_name'] ?? '')?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Last Name</label>
                                        <input type="text" name="l_name" class="form-control" value="<?=htmlspecialchars($editUser['l_name'] ?? '')?>">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Gender</label>
                                        <select name="gender" class="form-select">
                                            <option value="">Select</option>
                                            <option value="Male" <?= ($editUser['gender'] === 'Male') ? 'selected' : '' ?>>Male</option>
                                            <option value="Female" <?= ($editUser['gender'] === 'Female') ? 'selected' : '' ?>>Female</option>
                                            <option value="Other" <?= ($editUser['gender'] === 'Other') ? 'selected' : '' ?>>Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Age</label>
                                        <input type="number" name="age" class="form-control" value="<?=htmlspecialchars($editUser['age'] ?? '')?>" min="1">
                                    </div>

                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="is_admin" value="1" id="isAdminCheck" <?= ($editUser && $editUser['is_admin']) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="isAdminCheck">Grant Admin Privileges</label>
                                        </div>
                                    </div>
                                    <div class="col-12 mt-3">
                                        <button type="submit" class="btn btn-primary">Update User</button>
                                        <a href="admin.php" class="btn btn-link text-decoration-none">Cancel</a>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </div>

        <!-- Add User Modal -->
        <div id="addUserModal" class="modal fade" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New User</h5>
                        <button type="button" class="btn-close" onclick="closeAddUserModal()" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="post" action="admin.php" id="addUserForm">
                            <input type="hidden" name="action" value="add">
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" name="is_admin" value="1" id="newIsAdmin">
                                <label class="form-check-label" for="newIsAdmin">Is Admin</label>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Create User</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Scripts -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="../scripts/admin.js"></script>

    </body>
    </html>