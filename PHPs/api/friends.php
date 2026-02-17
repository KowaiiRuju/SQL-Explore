<?php
/**
 * Friends API — handles friend requests, searching, and list management.
 * All POST operations require valid CSRF token.
 */
session_start();
require_once __DIR__ . '/../includes/csrf.php';
header('Content-Type: application/json');

if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

require_once __DIR__ . '/../db.php';

$pdo = get_pdo(true);
ensure_setup();

// Get current user
$stmt = $pdo->prepare('SELECT id, username, f_name, l_name, profile_pic FROM users WHERE username = :u');
$stmt->execute([':u' => $_SESSION['user']]);
$currentUser = $stmt->fetch();

if (!$currentUser) {
    http_response_code(401);
    echo json_encode(['error' => 'User not found']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$myId   = (int)$currentUser['id'];

// CSRF Protection: Validate CSRF token for all POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        http_response_code(403);
        echo json_encode(['error' => 'CSRF token validation failed']);
        exit;
    }
}

try {
    switch ($action) {

        /* ── Search Users ────────────────────────── */
        case 'search_users':
            $q = trim($_GET['q'] ?? '');
            
            // Validate search query length and content
            if (strlen($q) < 1 || strlen($q) > 50) {
                echo json_encode(['success' => true, 'users' => []]);
                exit;
            }

            // Sanitize search term for LIKE queries by escaping special characters
            // Escape backslash first to avoid issues with consecutive escapes
            $q = str_replace('\\', '\\\\', $q);
            $q = str_replace('%', '\%', $q);
            $q = str_replace('_', '\_', $q);
            
            // Find users matching query, excluding self.
            // Also fetch current friendship status if any.
            $sql = "
                SELECT u.id, u.username, u.f_name, u.l_name, u.profile_pic,
                       (SELECT status FROM friendships f 
                        WHERE (f.user_id1 = :me AND f.user_id2 = u.id) 
                           OR (f.user_id1 = u.id AND f.user_id2 = :me2)
                        LIMIT 1) as friend_status,
                       (SELECT user_id1 FROM friendships f 
                        WHERE (f.user_id1 = :me3 AND f.user_id2 = u.id) 
                           OR (f.user_id1 = u.id AND f.user_id2 = :me4)
                        LIMIT 1) as requester_id
                FROM users u
                WHERE u.id != :me5 
                  AND (
                    u.username LIKE :q ESCAPE '\\\\' 
                    OR u.f_name LIKE :q2 ESCAPE '\\\\' 
                    OR u.l_name LIKE :q3 ESCAPE '\\\\'
                    OR CONCAT(u.f_name, ' ', u.l_name) LIKE :q4 ESCAPE '\\\\'
                  )
                ORDER BY u.username
                LIMIT 20
            ";
            
            $stmt = $pdo->prepare($sql);
            $likeQ = "%$q%";
            $stmt->execute([
                ':me' => $myId, ':me2' => $myId, 
                ':me3' => $myId, ':me4' => $myId, 
                ':me5' => $myId,
                ':q' => $likeQ, ':q2' => $likeQ, ':q3' => $likeQ, ':q4' => $likeQ
            ]);
            
            $results = $stmt->fetchAll();
            
            $users = array_map(function($u) use ($myId) {
                $status = 'none'; // none, pending_sent, pending_received, accepted
                if ($u['friend_status'] === 'accepted') {
                    $status = 'accepted';
                } elseif ($u['friend_status'] === 'pending') {
                    $status = ($u['requester_id'] == $myId) ? 'pending_sent' : 'pending_received';
                }

                return [
                    'id'          => (int)$u['id'],
                    'username'    => $u['username'],
                    'name'        => trim(($u['f_name'] ?? '') . ' ' . ($u['l_name'] ?? '')) ?: $u['username'],
                    'profile_pic' => $u['profile_pic'] ?? '',
                    'status'      => $status
                ];
            }, $results);

            echo json_encode(['success' => true, 'users' => $users]);
            break;

        /* ── Send Friend Request ─────────────────── */
        case 'send_request':
            $targetId = (int)($_POST['user_id'] ?? 0);
            if (!$targetId || $targetId === $myId) {
                echo json_encode(['error' => 'Invalid user']);
                exit;
            }

            // Check existing
            $check = $pdo->prepare("SELECT id FROM friendships WHERE (user_id1 = :m AND user_id2 = :t) OR (user_id1 = :t2 AND user_id2 = :m2)");
            $check->execute([':m' => $myId, ':t' => $targetId, ':t2' => $targetId, ':m2' => $myId]);
            
            if ($check->fetch()) {
                echo json_encode(['error' => 'Request already exists or you are already friends']);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO friendships (user_id1, user_id2, status) VALUES (:m, :t, 'pending')");
            $stmt->execute([':m' => $myId, ':t' => $targetId]);
            
            echo json_encode(['success' => true]);
            break;

        /* ── Accept Request ──────────────────────── */
        case 'accept_request':
            $targetId = (int)($_POST['user_id'] ?? 0);
            if (!$targetId) {
                echo json_encode(['error' => 'Invalid user']);
                exit;
            }

            // Must be pending AND I must be the receiver (user_id2)
            // But wait, my schema is (user_id1, user_id2).
            // If I am accepting, I should be user_id2.
            
            $stmt = $pdo->prepare("UPDATE friendships SET status = 'accepted' WHERE user_id1 = :other AND user_id2 = :me AND status = 'pending'");
            $stmt->execute([':other' => $targetId, ':me' => $myId]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['error' => 'No pending request found']);
            }
            break;

        /* ── Remove/Cancel/Reject ────────────────── */
        case 'remove_friend': // Covers cancel request, reject request, and unfriend
            $targetId = (int)($_POST['user_id'] ?? 0);
            if (!$targetId) {
                echo json_encode(['error' => 'Invalid user']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM friendships WHERE (user_id1 = :m AND user_id2 = :t) OR (user_id1 = :t2 AND user_id2 = :m2)");
            $stmt->execute([':m' => $myId, ':t' => $targetId, ':t2' => $targetId, ':m2' => $myId]);
            
            echo json_encode(['success' => true]);
            break;

        /* ── Get Pending Requests (Incoming) ─────── */
        case 'get_requests':
            $stmt = $pdo->prepare("
                SELECT u.id, u.username, u.f_name, u.l_name, u.profile_pic, f.created_at
                FROM friendships f
                JOIN users u ON f.user_id1 = u.id
                WHERE f.user_id2 = :me AND f.status = 'pending'
                ORDER BY f.created_at DESC
            ");
            $stmt->execute([':me' => $myId]);
            $requests = $stmt->fetchAll();
            
            $result = array_map(function($r) {
                return [
                    'id'          => (int)$r['id'],
                    'username'    => $r['username'],
                    'name'        => trim(($r['f_name'] ?? '') . ' ' . ($r['l_name'] ?? '')) ?: $r['username'],
                    'profile_pic' => $r['profile_pic'] ?? '',
                    'time_ago'    => $r['created_at'] // Client can format
                ];
            }, $requests);
            
            echo json_encode(['success' => true, 'requests' => $result]);
            break;

        /* ── Get Friends List ────────────────────── */
        case 'get_friends':
            // Fetch accepted friends
            // They can be user_id1 or user_id2
            $stmt = $pdo->prepare("
                SELECT u.id, u.username, u.f_name, u.l_name, u.profile_pic
                FROM users u
                JOIN friendships f ON (f.user_id1 = u.id OR f.user_id2 = u.id)
                WHERE (f.user_id1 = :me OR f.user_id2 = :me2)
                  AND f.status = 'accepted'
                  AND u.id != :me3
                ORDER BY u.f_name, u.username
            ");
            $stmt->execute([':me' => $myId, ':me2' => $myId, ':me3' => $myId]);
            $friends = $stmt->fetchAll();

            $result = array_map(function($f) {
                return [
                    'id'          => (int)$f['id'],
                    'username'    => $f['username'],
                    'name'        => trim(($f['f_name'] ?? '') . ' ' . ($f['l_name'] ?? '')) ?: $f['username'],
                    'profile_pic' => $f['profile_pic'] ?? ''
                ];
            }, $friends);

            echo json_encode(['success' => true, 'friends' => $result]);
            break;

        default:
            echo json_encode(['error' => 'Unknown action']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
