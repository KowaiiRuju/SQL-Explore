<?php
require_once __DIR__ . '/includes/session_guard.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/csrf.php';

// Admin-only page
if (empty($_SESSION['user']) || empty($_SESSION['is_admin'])) {
    header('Location: newsfeed.php');
    exit;
}

$message = '';
$error   = '';

try {
    $pdo = get_pdo(true);
    ensure_setup();

    // Handle POST actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_verify()) {
            $error = 'Invalid form submission. Please try again.';
        } else {
            $act = $_POST['action'] ?? '';

            if ($act === 'add_team') {
                $name  = trim($_POST['team_name'] ?? '');
                $color = trim($_POST['team_color'] ?? '#6c5ce7');
                $logo  = null;
                $score = 0; // Default score

                // Handle Logo Upload
                if (isset($_FILES['team_logo']) && $_FILES['team_logo']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['team_logo']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                        $filename = 'team_' . time() . '_' . uniqid() . '.' . $ext;
                        if (move_uploaded_file($_FILES['team_logo']['tmp_name'], __DIR__ . '/../uploads/' . $filename)) {
                            $logo = $filename;
                        }
                    }
                }

                if ($name === '') {
                    $error = 'Team name is required.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO teams (name, color, logo, score) VALUES (:n, :c, :l, :s)');
                    $stmt->execute([':n' => $name, ':c' => $color, ':l' => $logo, ':s' => $score]);
                    $message = "Team \"$name\" created successfully.";
                }
            } elseif ($act === 'edit_team') {
                $id    = (int)($_POST['team_id'] ?? 0);
                $name  = trim($_POST['team_name'] ?? '');
                $color = trim($_POST['team_color'] ?? '#6c5ce7');
                $score = (int)($_POST['team_score'] ?? 0);
                
                if ($id && $name !== '') {
                    // Check for existing team to handle logo replacement
                    $oldTeam = $pdo->query("SELECT logo FROM teams WHERE id = $id")->fetch();
                    $logo = $oldTeam['logo'] ?? null;

                    if (isset($_FILES['team_logo']) && $_FILES['team_logo']['error'] === UPLOAD_ERR_OK) {
                        $ext = strtolower(pathinfo($_FILES['team_logo']['name'], PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                            $filename = 'team_' . $id . '_' . time() . '.' . $ext;
                            if (move_uploaded_file($_FILES['team_logo']['tmp_name'], __DIR__ . '/../uploads/' . $filename)) {
                                $logo = $filename;
                                // Ideally delete old logo if it exists
                            }
                        }
                    }

                    $stmt = $pdo->prepare('UPDATE teams SET name = :n, color = :c, logo = :l, score = :s WHERE id = :id');
                    $stmt->execute([':n' => $name, ':c' => $color, ':l' => $logo, ':s' => $score, ':id' => $id]);
                    $message = "Team updated successfully.";
                }
            } elseif ($act === 'delete_team') {
                $id = (int)($_POST['team_id'] ?? 0);
                if ($id) {
                    // Unassign users from this team
                    $pdo->prepare('UPDATE users SET team_id = NULL WHERE team_id = :id')->execute([':id' => $id]);
                    $pdo->prepare('DELETE FROM teams WHERE id = :id')->execute([':id' => $id]);
                    $message = "Team deleted. Members have been unassigned.";
                }
            } elseif ($act === 'assign_members') {
                $teamId  = (int)($_POST['team_id'] ?? 0);
                $userIds = $_POST['user_ids'] ?? [];
                if ($teamId) {
                    // Remove everyone currently in team
                    $pdo->prepare('UPDATE users SET team_id = NULL WHERE team_id = :tid')->execute([':tid' => $teamId]);
                    // Add selected users
                    if (!empty($userIds)) {
                        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
                        $stmt = $pdo->prepare("UPDATE users SET team_id = ? WHERE id IN ($placeholders) AND (is_admin = 0 OR is_admin IS NULL)");
                        $params = array_merge([$teamId], array_map('intval', $userIds));
                        $stmt->execute($params);
                    }
                    $message = "Team members updated.";
                }
            } elseif ($act === 'auto_assign') {
                // Fetch all users and teams
                $allUserIds = $pdo->query("SELECT id FROM users WHERE is_admin = 0 OR is_admin IS NULL")->fetchAll(PDO::FETCH_COLUMN);
                $allTeamIds = $pdo->query("SELECT id FROM teams")->fetchAll(PDO::FETCH_COLUMN);

                if (empty($allTeamIds)) {
                    $error = "No teams available to assign members to.";
                } elseif (empty($allUserIds)) {
                    $error = "No users found to assign.";
                } else {
                    shuffle($allUserIds); // Randomize users
                    $teamCount = count($allTeamIds);
                    $usersPerTeam = floor(count($allUserIds) / $teamCount);
                    $remainder = count($allUserIds) % $teamCount;

                    $offset = 0;
                    foreach ($allTeamIds as $index => $tid) {
                        $limit = $usersPerTeam + ($index < $remainder ? 1 : 0); // Distribute remainder
                        if ($limit > 0) {
                            $chunk = array_slice($allUserIds, $offset, $limit);
                            $offset += $limit;
                            
                            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                            $stmt = $pdo->prepare("UPDATE users SET team_id = ? WHERE id IN ($placeholders)");
                            $params = array_merge([$tid], $chunk);
                            $stmt->execute($params);
                        }
                    }
                    $message = "All users have been randomly assigned to teams.";
                }
            } elseif ($act === 'reset_assignments') {
                $pdo->exec("UPDATE users SET team_id = NULL");
                $message = "All team assignments have been reset.";
            }
        }
    }

    // Fetch data
    $teams = $pdo->query("SELECT t.*, (SELECT COUNT(*) FROM users u WHERE u.team_id = t.id AND (u.is_admin = 0 OR u.is_admin IS NULL)) as member_count FROM teams t ORDER BY score DESC, name ASC")->fetchAll();
    $allUsers = $pdo->query("SELECT id, username, f_name, l_name, team_id FROM users WHERE is_admin = 0 OR is_admin IS NULL ORDER BY username")->fetchAll();

    // Current user profile for sidebar
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :u');
    $stmt->execute([':u' => $_SESSION['user']]);
    $userProfile = $stmt->fetch();

} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
    $teams = [];
    $allUsers = [];
}

/* ── View ─────────────────────────────────────────── */
$pageTitle = 'Team Management - SQL Explore';
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
                                <h1 class="h3 mb-1">Team Management</h1>
                                <p class="text-muted small mb-0">Create, edit, and manage teams and their members.</p>
                            </div>
                            <div class="d-flex gap-2">
                                <form method="post" onsubmit="return confirm('Are you sure you want to randomly assign ALL users to teams? Existing assignments will be overwritten.');" class="d-inline">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="action" value="auto_assign">
                                    <button type="submit" class="btn btn-outline-primary rounded-pill px-3">
                                        <i class="bi bi-shuffle me-2"></i>Random Assign
                                    </button>
                                </form>
                                <form method="post" onsubmit="return confirm('Are you sure you want to RESET all team assignments? This cannot be undone.');" class="d-inline">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="action" value="reset_assignments">
                                    <button type="submit" class="btn btn-outline-danger rounded-pill px-3">
                                        <i class="bi bi-trash me-2"></i>Reset Teams
                                    </button>
                                </form>
                                <button class="btn btn-success rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addTeamModal">
                                    <i class="bi bi-people-fill me-2"></i>New Team
                                </button>
                            </div>
                        </div>

                        <!-- Messages -->
                        <?php if ($message): ?>
                            <div class="alert alert-success d-flex align-items-center" role="alert">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                <div><?= htmlspecialchars($message) ?></div>
                            </div>
                        <?php endif; ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger d-flex align-items-center" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <div><?= htmlspecialchars($error) ?></div>
                            </div>
                        <?php endif; ?>

                        <!-- Teams Grid -->
                        <?php if (empty($teams)): ?>
                            <div class="admin-card text-center py-5">
                                <i class="bi bi-people text-muted" style="font-size: 3rem;"></i>
                                <h5 class="mt-3 text-muted">No teams yet</h5>
                                <p class="text-muted">Create your first team to get started.</p>
                                <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addTeamModal">
                                    <i class="bi bi-plus-lg me-1"></i> Create Team
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="row g-4">
                                <?php foreach ($teams as $team): ?>
                                    <?php
                                        // Get members for this team
                                        $teamMembers = array_filter($allUsers, fn($u) => (int)($u['team_id'] ?? 0) === (int)$team['id']);
                                    ?>
                                    <div class="col-md-6 col-xl-4">
                                        <div class="admin-card h-100">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="rounded-circle overflow-hidden d-flex align-items-center justify-content-center border" style="width:48px; height:48px; background:<?= htmlspecialchars($team['color']) ?>; color:#fff; font-size:1.2rem; flex-shrink:0;">
                                                        <?php if (!empty($team['logo']) && file_exists(__DIR__ . '/../uploads/' . $team['logo'])): ?>
                                                            <img src="../uploads/<?= htmlspecialchars($team['logo']) ?>" class="w-100 h-100 object-fit-cover">
                                                        <?php else: ?>
                                                            <i class="bi bi-people-fill"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <h5 class="mb-0 fw-bold"><?= htmlspecialchars($team['name']) ?></h5>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <small class="text-muted"><?= (int)$team['member_count'] ?> members</small>
                                                            <span class="badge bg-primary rounded-pill" style="font-size: 0.65rem;"><?= (int)$team['score'] ?> pts</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-light rounded-pill" data-bs-toggle="dropdown" aria-expanded="false">
                                                        <i class="bi bi-three-dots-vertical"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <li>
                                                            <button class="dropdown-item" onclick="openEditTeamModal(<?= (int)$team['id'] ?>)">
                                                                <i class="bi bi-pencil me-2"></i>Edit Team
                                                            </button>
                                                        </li>
                                                        <li>
                                                            <button class="dropdown-item" onclick="openMembersModal(<?= (int)$team['id'] ?>)">
                                                                <i class="bi bi-person-plus me-2"></i>Manage Members
                                                            </button>
                                                        </li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <form method="post" onsubmit="return confirm('Delete this team? Members will be unassigned.');">
                                                                <?php csrf_field(); ?>
                                                                <input type="hidden" name="action" value="delete_team">
                                                                <input type="hidden" name="team_id" value="<?= (int)$team['id'] ?>">
                                                                <button type="submit" class="dropdown-item text-white bg-danger rounded">
                                                                    <i class="bi bi-trash me-2"></i>Delete Team
                                                                </button>
                                                            </form>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>

                                            <!-- Members list -->
                                            <div class="border-top pt-3">
                                                <?php if (empty($teamMembers)): ?>
                                                    <p class="text-muted small mb-0 text-center">No members assigned</p>
                                                <?php else: ?>
                                                    <?php foreach (array_slice($teamMembers, 0, 5) as $member): ?>
                                                        <div class="d-flex align-items-center mb-2">
                                                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-2" style="width:32px; height:32px; font-size:0.8rem;">
                                                                <i class="bi bi-person-fill text-muted"></i>
                                                            </div>
                                                            <div>
                                                                <small class="fw-medium d-block"><?= htmlspecialchars(trim(($member['f_name'] ?? '') . ' ' . ($member['l_name'] ?? ''))) ?: htmlspecialchars($member['username']) ?></small>
                                                                <small class="text-muted">@<?= htmlspecialchars($member['username']) ?></small>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                    <?php if (count($teamMembers) > 5): ?>
                                                        <small class="text-muted">+<?= count($teamMembers) - 5 ?> more</small>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Add Team Modal -->
<div class="modal fade" id="addTeamModal" tabindex="-1" aria-labelledby="addTeamModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addTeamModalLabel">Create New Team</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="post" id="addTeamForm" enctype="multipart/form-data">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="add_team">
                    <div class="mb-3 text-center">
                        <label class="form-label d-block text-muted small">Team Logo</label>
                        <div class="d-inline-block position-relative">
                            <label for="newTeamLogo" class="btn btn-light rounded-circle border d-flex align-items-center justify-content-center shadow-sm" style="width: 80px; height: 80px; cursor:pointer;">
                                <i class="bi bi-camera text-muted fs-4"></i>
                            </label>
                            <input type="file" id="newTeamLogo" name="team_logo" class="d-none" accept="image/*" onchange="previewImage(this, 'addLogoP')">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="newTeamName">Team Name <span class="text-danger">*</span></label>
                        <input type="text" id="newTeamName" name="team_name" class="form-control" required placeholder="e.g. Alpha Squad">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="newTeamColor">Team Color</label>
                        <div class="d-flex align-items-center gap-3">
                            <input type="color" id="newTeamColor" name="team_color" class="form-control form-control-color" value="#6c5ce7" title="Choose team color">
                            <span class="text-muted small" id="colorPreview">#6c5ce7</span>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success"><i class="bi bi-plus-lg me-1"></i>Create Team</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Team Modal -->
<div class="modal fade" id="editTeamModal" tabindex="-1" aria-labelledby="editTeamModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editTeamModalLabel">Edit Team</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="post" id="editTeamForm" enctype="multipart/form-data">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="edit_team">
                    <input type="hidden" name="team_id" id="editTeamId">
                    <div class="mb-3 text-center">
                        <label class="form-label d-block text-muted small">Team Logo</label>
                        <div class="d-inline-block position-relative">
                            <div class="rounded-circle overflow-hidden border d-flex align-items-center justify-content-center mb-2 mx-auto" style="width: 80px; height: 80px; background: #f8f9fa;">
                                <img id="editLogoPreview" class="w-100 h-100 object-fit-cover d-none">
                                <i class="bi bi-people text-muted fs-4" id="editLogoPlaceholder"></i>
                            </div>
                            <label for="editTeamLogo" class="btn btn-sm btn-outline-primary rounded-pill">Change Logo</label>
                            <input type="file" id="editTeamLogo" name="team_logo" class="d-none" accept="image/*">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="editTeamName">Team Name <span class="text-danger">*</span></label>
                        <input type="text" id="editTeamName" name="team_name" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="editTeamColor">Team Color</label>
                            <div class="d-flex align-items-center gap-3">
                                <input type="color" id="editTeamColor" name="team_color" class="form-control form-control-color" value="#6c5ce7">
                                <span class="text-muted small" id="editColorPreview">#6c5ce7</span>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="editTeamScore">Team Score</label>
                            <input type="number" id="editTeamScore" name="team_score" class="form-control" min="0">
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Manage Members Modal -->
<div class="modal fade" id="membersModal" tabindex="-1" aria-labelledby="membersModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="membersModalLabel">Manage Members</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="post" id="membersForm">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="assign_members">
                    <input type="hidden" name="team_id" id="membersTeamId">
                    <p class="text-muted small mb-3">Select users to assign to this team. Users already in another team will be moved.</p>
                    <div class="mb-3">
                        <input type="text" id="memberSearch" class="form-control mb-2" placeholder="Search users..." oninput="filterMembersList()">
                    </div>
                    <div class="border rounded p-2" style="max-height: 300px; overflow-y: auto;" id="membersList">
                        <?php foreach ($allUsers as $u): ?>
                            <div class="form-check py-1 member-row" data-username="<?= htmlspecialchars(strtolower($u['username'])) ?>">
                                <input class="form-check-input member-check" type="checkbox" name="user_ids[]" value="<?= (int)$u['id'] ?>" id="memberUser<?= (int)$u['id'] ?>" data-team="<?= (int)($u['team_id'] ?? 0) ?>">
                                <label class="form-check-label" for="memberUser<?= (int)$u['id'] ?>">
                                    <span class="fw-medium"><?= htmlspecialchars(trim(($u['f_name'] ?? '') . ' ' . ($u['l_name'] ?? ''))) ?: htmlspecialchars($u['username']) ?></span>
                                    <small class="text-muted ms-2">@<?= htmlspecialchars($u['username']) ?></small>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-3">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Members</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Pass teams data to JS -->
<script>
    const teamsData = <?= json_encode(array_map(function($t) {
        return [
            'id'    => (int) $t['id'],
            'name'  => $t['name'],
            'color' => $t['color'],
            'logo'  => $t['logo'] ?? null,
            'score' => (int) ($t['score'] ?? 0),
        ];
    }, $teams), JSON_HEX_TAG) ?>;

    // Color preview
    document.getElementById('newTeamColor')?.addEventListener('input', function() {
        document.getElementById('colorPreview').textContent = this.value;
    });
    document.getElementById('editTeamColor')?.addEventListener('input', function() {
        document.getElementById('editColorPreview').textContent = this.value;
    });

    // Logo Previews
    const setupPreview = (input, imgId, placeholderId) => {
        if (!input) return;
        input.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (imgId) {
                        const img = document.getElementById(imgId);
                        img.src = e.target.result;
                        img.classList.remove('d-none');
                    }
                    if (placeholderId) {
                        document.getElementById(placeholderId).classList.add('d-none');
                    }
                }
                reader.readAsDataURL(this.files[0]);
            }
        });
    };

    setupPreview(document.getElementById('editTeamLogo'), 'editLogoPreview', 'editLogoPlaceholder');
    setupPreview(document.getElementById('newTeamLogo'), null, null); // Add support for new logo preview if UI supported it

    function openEditTeamModal(teamId) {
        const team = teamsData.find(t => t.id === teamId);
        if (!team) return;
        document.getElementById('editTeamId').value = team.id;
        document.getElementById('editTeamName').value = team.name;
        document.getElementById('editTeamColor').value = team.color;
        document.getElementById('editColorPreview').textContent = team.color;
        document.getElementById('editTeamScore').value = team.score;
        document.getElementById('editTeamModalLabel').textContent = 'Edit: ' + team.name;
        
        // Handle logo visual
        const prevImg = document.getElementById('editLogoPreview');
        const prevIcon = document.getElementById('editLogoPlaceholder');
        const fileInput = document.getElementById('editTeamLogo');
        fileInput.value = ''; // Reset input selection

        if (team.logo) {
            prevImg.src = '../uploads/' + team.logo;
            prevImg.classList.remove('d-none');
            prevIcon.classList.add('d-none');
        } else {
            prevImg.classList.add('d-none');
            prevIcon.classList.remove('d-none');
        }
        
        new bootstrap.Modal(document.getElementById('editTeamModal')).show();
    }

    function openMembersModal(teamId) {
        const team = teamsData.find(t => t.id === teamId);
        if (!team) return;
        document.getElementById('membersTeamId').value = team.id;
        document.getElementById('membersModalLabel').textContent = 'Manage Members: ' + team.name;
        // Check appropriate users
        document.querySelectorAll('.member-check').forEach(cb => {
            cb.checked = parseInt(cb.dataset.team) === teamId;
        });
        document.getElementById('memberSearch').value = '';
        filterMembersList();
        new bootstrap.Modal(document.getElementById('membersModal')).show();
    }

    function filterMembersList() {
        const q = document.getElementById('memberSearch').value.trim().toLowerCase();
        document.querySelectorAll('.member-row').forEach(row => {
            row.style.display = !q || row.dataset.username.includes(q) ? '' : 'none';
        });
    }
</script>

<?php
$pageScripts = [];
require __DIR__ . '/includes/footer.php';
?>
