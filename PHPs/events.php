<?php
require_once __DIR__ . '/includes/session_guard.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/csrf.php';

$isAdmin = !empty($_SESSION['is_admin']);

$message = '';
$error   = '';

try {
    $pdo = get_pdo(true);
    ensure_setup();

    // Fetch current admin user early (needed for post attribution)
    $adminStmt = $pdo->prepare('SELECT id, username, f_name, l_name FROM users WHERE username = :u');
    $adminStmt->execute([':u' => $_SESSION['user']]);
    $adminUser = $adminStmt->fetch();

    // Handle POST actions (Admin only)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
        if (!csrf_verify()) {
            $error = 'Invalid form submission.';
        } else {
            $act = $_POST['action'] ?? '';

            if ($act === 'add_event') {
                $title = trim($_POST['event_title'] ?? '');
                // Date defaults to NOW if not provided (though we won't show input)
                $date  = date('Y-m-d H:i:s');

                if ($title === '') {
                    $error = 'Event title is required.';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO events (title, event_date) VALUES (:t, :dt)");
                    $stmt->execute([':t' => $title, ':dt' => $date]);

                    // Auto-post to timeline
                    if ($adminUser) {
                        $postContent = "📢 New Event: \"" . $title . "\"\n\nA new event has been created! Stay tuned for results.";
                        $pdo->prepare('INSERT INTO posts (user_id, content) VALUES (:uid, :c)')
                            ->execute([':uid' => $adminUser['id'], ':c' => $postContent]);
                    }

                    $message = "Event created and announced on the timeline.";
                }
            } elseif ($act === 'delete_event') {
                $eid = (int)($_POST['event_id'] ?? 0);
                if ($eid) {
                    // deduct scores before deleting
                    $scores = $pdo->prepare("SELECT team_id, score FROM event_scores WHERE event_id = ?");
                    $scores->execute([$eid]);
                    foreach ($scores->fetchAll() as $s) {
                        $pdo->prepare("UPDATE teams SET score = score - ? WHERE id = ?")->execute([(int)$s['score'], (int)$s['team_id']]);
                    }

                    $pdo->prepare("DELETE FROM events WHERE id = ?")->execute([$eid]);
                    $message = "Event deleted and associated scores removed.";
                }
            } elseif ($act === 'save_scores') {
                $eid = (int)($_POST['event_id'] ?? 0);
                $teamScores = $_POST['scores'] ?? []; // array of team_id => score

                if ($eid && is_array($teamScores)) {
                    foreach ($teamScores as $tid => $newScore) {
                        $tid = (int)$tid;
                        $newScore = (int)$newScore;

                        // Get old score
                        $stmt = $pdo->prepare("SELECT score FROM event_scores WHERE event_id = ? AND team_id = ?");
                        $stmt->execute([$eid, $tid]);
                        $oldScore = $stmt->fetchColumn();
                        $oldScore = $oldScore !== false ? (int)$oldScore : 0;

                        if ($oldScore !== $newScore) {
                            $diff = $newScore - $oldScore;

                            if ($stmt->rowCount() > 0) {
                                $pdo->prepare("UPDATE event_scores SET score = ? WHERE event_id = ? AND team_id = ?")
                                    ->execute([$newScore, $eid, $tid]);
                            } else {
                                $pdo->prepare("INSERT INTO event_scores (event_id, team_id, score) VALUES (?, ?, ?)")
                                    ->execute([$eid, $tid, $newScore]);
                            }

                            $pdo->prepare("UPDATE teams SET score = score + ? WHERE id = ?")->execute([$diff, $tid]);
                        }
                    }
                    $message = "Scores updated.";
                }
            } elseif ($act === 'save_placements') {
                $eid = (int)($_POST['event_id'] ?? 0);
                $placements = [
                    ['team' => $_POST['first_place'] ?? 0, 'score' => $_POST['first_score'] ?? 0],
                    ['team' => $_POST['second_place'] ?? 0, 'score' => $_POST['second_score'] ?? 0],
                    ['team' => $_POST['third_place'] ?? 0, 'score' => $_POST['third_score'] ?? 0],
                ];

                $processedTeams = [];

                foreach ($placements as $p) {
                    $tid = (int)$p['team'];
                    $score = (int)$p['score'];

                    if ($tid > 0 && !in_array($tid, $processedTeams)) {
                        // Same logic as save_scores but for specific teams
                         // Get old score
                         $stmt = $pdo->prepare("SELECT score FROM event_scores WHERE event_id = ? AND team_id = ?");
                         $stmt->execute([$eid, $tid]);
                         $oldScore = $stmt->fetchColumn();
                         $oldScore = $oldScore !== false ? (int)$oldScore : 0;
 
                         if ($oldScore !== $score) {
                             $diff = $score - $oldScore;
 
                             if ($stmt->rowCount() > 0) {
                                 $pdo->prepare("UPDATE event_scores SET score = ? WHERE event_id = ? AND team_id = ?")
                                     ->execute([$score, $eid, $tid]);
                             } else {
                                 $pdo->prepare("INSERT INTO event_scores (event_id, team_id, score) VALUES (?, ?, ?)")
                                     ->execute([$eid, $tid, $score]);
                             }
 
                             $pdo->prepare("UPDATE teams SET score = score + ? WHERE id = ?")->execute([$diff, $tid]);
                         }
                         $processedTeams[] = $tid;
                    }
                }
                 // Auto-post placement results to timeline
                 if ($adminUser) {
                     // Fetch event title
                     $evtStmt = $pdo->prepare('SELECT title FROM events WHERE id = ?');
                     $evtStmt->execute([$eid]);
                     $evtTitle = $evtStmt->fetchColumn() ?: 'Event';

                     $labels = ['🥇 1st Place', '🥈 2nd Place', '🥉 3rd Place'];
                     $lines = [];
                     foreach ($placements as $i => $p) {
                         $tid = (int)$p['team'];
                         $score = (int)$p['score'];
                         if ($tid > 0) {
                             $tStmt = $pdo->prepare('SELECT name FROM teams WHERE id = ?');
                             $tStmt->execute([$tid]);
                             $tName = $tStmt->fetchColumn() ?: 'Unknown';
                             $lines[] = $labels[$i] . ': ' . $tName . ' (' . $score . ' pts)';
                         }
                     }

                     if (!empty($lines)) {
                         $postContent = "🏆 Results for \"" . $evtTitle . "\"\n\n" . implode("\n", $lines) . "\n\nCongratulations to the winners!";
                         $pdo->prepare('INSERT INTO posts (user_id, content) VALUES (:uid, :c)')
                             ->execute([':uid' => $adminUser['id'], ':c' => $postContent]);
                     }
                 }
                 $message = "Placement scores assigned and results posted.";
            }
        }
    }

    // Fetch Events
    $events = $pdo->query("SELECT * FROM events ORDER BY event_date DESC, created_at DESC")->fetchAll();

    // Fetch Teams
    $teams = $pdo->query("SELECT * FROM teams ORDER BY name")->fetchAll();

    // Fetch all event scores
    $allEventScores = $pdo->query("SELECT * FROM event_scores")->fetchAll();
    $scoreMap = [];
    foreach ($allEventScores as $es) {
        $scoreMap[$es['event_id']][$es['team_id']] = $es['score'];
    }

    // Current user profile
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :u');
    $stmt->execute([':u' => $_SESSION['user']]);
    $userProfile = $stmt->fetch();

} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
    $events = [];
    $teams = [];
}

$pageTitle = 'Events - SQL Explore';
$pageCss   = ['newsfeed.css', 'admin.css', 'events.css'];
$bodyClass = 'body-dashboard';
require __DIR__ . '/includes/header.php';
?>

<div class="container-fluid p-0">
    <div class="row g-0">
        <?php include __DIR__ . '/includes/sidebar_layout.php'; ?>

        <main class="col-lg-9 col-xl-10 mt-5 mt-lg-0">
            <div class="container-fluid py-4 px-lg-5">
                <div class="row">
                    <div class="col-12">
                        
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h1 class="h3 mb-1">Events & Scoring</h1>
                                <p class="text-muted small mb-0">Manage events and assign placing points.</p>
                            </div>
                            <?php if ($isAdmin): ?>
                                <button class="btn btn-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addEventModal">
                                    <i class="bi bi-calendar-plus me-2"></i>New Event
                                </button>
                            <?php endif; ?>
                        </div>

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

                        <?php if (empty($events)): ?>
                            <div class="admin-card text-center py-5">
                                <i class="bi bi-calendar-x text-muted event-empty-icon"></i>
                                <h5 class="mt-3 text-muted">No events yet</h5>
                                <?php if ($isAdmin): ?>
                                    <p class="text-muted">Create an event to start scoring teams.</p>
                                    <button class="btn btn-outline-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addEventModal">
                                        <i class="bi bi-plus-lg me-1"></i> Create Event
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="row g-4">
                                <?php foreach ($events as $evt): ?>
                                    <div class="col-12">
                                        <div class="admin-card">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <div class="d-flex align-items-center gap-2 mb-1">
                                                        <h5 class="mb-0 fw-bold"><?= htmlspecialchars($evt['title']) ?></h5>
                                                        <?php if (!empty($evt['event_date'])): ?>
                                                            <span class="badge bg-light text-dark border"><i class="bi bi-clock me-1"></i><?= date('M j, Y h:i A', strtotime($evt['event_date'])) ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <?php if ($isAdmin): ?>
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-light rounded-pill" data-bs-toggle="dropdown">
                                                            <i class="bi bi-three-dots-vertical"></i>
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-end">
                                                            <li>
                                                                <button class="dropdown-item" onclick="openPlacementModal(<?= (int)$evt['id'] ?>)">
                                                                    <i class="bi bi-trophy me-2"></i>Rank Teams
                                                                </button>
                                                            </li>
                                                            <li>
                                                                <button class="dropdown-item" onclick="openScoreModal(<?= (int)$evt['id'] ?>)">
                                                                    <i class="bi bi-pencil me-2"></i>Edit All Scores
                                                                </button>
                                                            </li>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li>
                                                                <form method="post" class="delete-event-form">
                                                                    <?php csrf_field(); ?>
                                                                    <input type="hidden" name="action" value="delete_event">
                                                                    <input type="hidden" name="event_id" value="<?= (int)$evt['id'] ?>">
                                                                    <button type="submit" class="dropdown-item text-white bg-danger rounded">
                                                                        <i class="bi bi-trash me-2"></i>Delete Event
                                                                    </button>
                                                                </form>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Scores Display -->
                                            <div class="d-flex flex-wrap gap-2 mt-2">
                                                <?php 
                                                    $hasScores = false;
                                                    // Sort teams by score for this event for display
                                                    $evtScores = $scoreMap[$evt['id']] ?? [];
                                                    arsort($evtScores);
                                                    
                                                    foreach ($evtScores as $tid => $s) {
                                                        if ($s > 0) {
                                                            $hasScores = true;
                                                            // Find team info
                                                            $tInfo = array_filter($teams, fn($tm) => $tm['id'] == $tid);
                                                            $tInfo = reset($tInfo);
                                                            if ($tInfo) {
                                                                echo sprintf(
                                                                    '<span class="badge rounded-pill px-3 py-2 d-flex align-items-center gap-2" style="background-color: %s20; color: %s; border: 1px solid %s;">
                                                                        <span class="rounded-circle" style="width:10px; height:10px; background-color: %s;"></span>
                                                                        %s: <strong>%d</strong>
                                                                    </span>',
                                                                    htmlspecialchars($tInfo['color']),
                                                                    htmlspecialchars($tInfo['color']),
                                                                    htmlspecialchars($tInfo['color']),
                                                                    htmlspecialchars($tInfo['color']),
                                                                    htmlspecialchars($tInfo['name']),
                                                                    $s
                                                                );
                                                            }
                                                        }
                                                    }

                                                    if (!$hasScores) {
                                                        echo '<small class="text-muted fst-italic">No scores recorded yet.</small>';
                                                    }
                                                ?>
                                                <?php if ($isAdmin && !$hasScores): ?>
                                                    <button class="btn btn-sm btn-link text-decoration-none p-0 ms-2" onclick="openPlacementModal(<?= (int)$evt['id'] ?>)">Rank Teams</button>
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

<?php if ($isAdmin): ?>
<!-- Add Event Modal -->
<div class="modal fade" id="addEventModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="post">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="add_event">
                    <p class="text-muted small">Date and time will be set to now automatically.</p>
                    <div class="mb-3">
                        <label class="form-label">Event Title <span class="text-danger">*</span></label>
                        <input type="text" name="event_title" class="form-control" required placeholder="e.g. Tug of War">
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Event</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Placement Modal (Rank Teams) -->
<div class="modal fade" id="placementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Rank Teams</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="post">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="save_placements">
                    <input type="hidden" name="event_id" id="placementEventId">
                    <p class="text-muted small mb-3">Select the winners. Points will be assigned accordingly.</p>
                    
                    <div class="mb-3">
                        <label class="form-label text-warning fw-bold"><i class="bi bi-trophy-fill me-1"></i>1st Place</label>
                        <div class="d-flex gap-2">
                            <select name="first_place" class="form-select">
                                <option value="">Select Team</option>
                                <?php foreach($teams as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" name="first_score" class="form-control w-100px" value="100" placeholder="Pts">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-secondary fw-bold"><i class="bi bi-trophy-fill me-1"></i>2nd Place</label>
                        <div class="d-flex gap-2">
                            <select name="second_place" class="form-select">
                                <option value="">Select Team</option>
                                <?php foreach($teams as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" name="second_score" class="form-control w-100px" value="50" placeholder="Pts">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-dark fw-bold opacity-60"><i class="bi bi-trophy-fill me-1"></i>3rd Place</label>
                        <div class="d-flex gap-2">
                            <select name="third_place" class="form-select">
                                <option value="">Select Team</option>
                                <?php foreach($teams as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" name="third_score" class="form-control w-100px" value="25" placeholder="Pts">
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-3">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Save Rankings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Score Modal (Advanced/Edit All) -->
<div class="modal fade" id="scoreModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit All Scores</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="post" id="scoreForm">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="save_scores">
                    <input type="hidden" name="event_id" id="scoreEventId">
                    <p class="text-muted small mb-3">Manually adjust points for each team.</p>
                    
                    <div id="teamScoreInputs">
                        <?php foreach ($teams as $t): ?>
                            <div class="mb-2 d-flex align-items-center justify-content-between p-2 border rounded">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle team-dot-sm" style="background:<?= htmlspecialchars($t['color']) ?>;"></div>
                                    <span class="fw-medium"><?= htmlspecialchars($t['name']) ?></span>
                                </div>
                                <input type="number" name="scores[<?= (int)$t['id'] ?>]" class="form-control form-control-sm text-end w-100px" value="0" min="0" id="score_input_<?= (int)$t['id'] ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-3">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Save Scores</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    const scoreMap = <?= json_encode($scoreMap) ?>;
    const teamsData = <?= json_encode(array_map(function($t){
        return ['id' => (int)$t['id']];
    }, $teams)) ?>;
</script>
<?php endif; ?>

<?php
$pageScripts = ['events.js']; 
require __DIR__ . '/includes/footer.php';
?>
