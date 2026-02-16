<?php
/**
 * Messages API — handles AJAX requests for sending, fetching messages, and marking as read.
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
$stmt = $pdo->prepare('SELECT id, username, f_name, l_name, profile_pic, is_admin FROM users WHERE username = :u');
$stmt->execute([':u' => $_SESSION['user']]);
$currentUser = $stmt->fetch();

if (!$currentUser) {
    http_response_code(401);
    echo json_encode(['error' => 'User not found']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

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

        /* ── Get Conversations List ──────────────── */
        case 'get_conversations':
            // Get all users the current user has exchanged messages with, plus unread counts
            $stmt = $pdo->prepare("
                SELECT u.id, u.username, u.f_name, u.l_name, u.profile_pic,
                    (SELECT COUNT(*) FROM messages m WHERE m.sender_id = u.id AND m.receiver_id = :me AND m.is_read = 0) as unread_count,
                    (SELECT m2.content FROM messages m2 
                     WHERE (m2.sender_id = :me2 AND m2.receiver_id = u.id) OR (m2.sender_id = u.id AND m2.receiver_id = :me3) 
                     ORDER BY m2.created_at DESC LIMIT 1) as last_message,
                    (SELECT m3.created_at FROM messages m3 
                     WHERE (m3.sender_id = :me4 AND m3.receiver_id = u.id) OR (m3.sender_id = u.id AND m3.receiver_id = :me5) 
                     ORDER BY m3.created_at DESC LIMIT 1) as last_message_time
                FROM users u
                WHERE u.id != :me6
                  AND (
                      EXISTS (SELECT 1 FROM messages m WHERE m.sender_id = :me7 AND m.receiver_id = u.id)
                      OR EXISTS (SELECT 1 FROM messages m WHERE m.sender_id = u.id AND m.receiver_id = :me8)
                  )
                ORDER BY last_message_time DESC
            ");
            $myId = $currentUser['id'];
            $stmt->execute([
                ':me'  => $myId, ':me2' => $myId, ':me3' => $myId,
                ':me4' => $myId, ':me5' => $myId, ':me6' => $myId,
                ':me7' => $myId, ':me8' => $myId,
            ]);
            $conversations = $stmt->fetchAll();

            $result = array_map(function($c) {
                return [
                    'user_id'      => (int)$c['id'],
                    'username'     => $c['username'],
                    'name'         => trim(($c['f_name'] ?? '') . ' ' . ($c['l_name'] ?? '')) ?: $c['username'],
                    'profile_pic'  => $c['profile_pic'] ?? '',
                    'unread_count' => (int)$c['unread_count'],
                    'last_message' => $c['last_message'] ?? '',
                    'last_time'    => $c['last_message_time'] ?? '',
                ];
            }, $conversations);

            echo json_encode(['success' => true, 'conversations' => $result]);
            break;

        /* ── Get Messages with a User ────────────── */
        case 'get_messages':
            $otherId = (int)($_GET['user_id'] ?? $_POST['user_id'] ?? 0);
            if (!$otherId) {
                echo json_encode(['error' => 'Invalid user ID']);
                exit;
            }

            $myId = $currentUser['id'];

            // Mark messages from other user as read
            $pdo->prepare('UPDATE messages SET is_read = 1 WHERE sender_id = :other AND receiver_id = :me AND is_read = 0')
                ->execute([':other' => $otherId, ':me' => $myId]);

            // Fetch conversation
            $stmt = $pdo->prepare("
                SELECT m.*, u.username, u.f_name, u.l_name, u.profile_pic
                FROM messages m
                JOIN users u ON m.sender_id = u.id
                WHERE (m.sender_id = :me AND m.receiver_id = :other)
                   OR (m.sender_id = :other2 AND m.receiver_id = :me2)
                ORDER BY m.created_at ASC
            ");
            $stmt->execute([':me' => $myId, ':other' => $otherId, ':other2' => $otherId, ':me2' => $myId]);
            $messages = $stmt->fetchAll();

            $result = array_map(function($m) use ($currentUser) {
                return [
                    'id'          => (int)$m['id'],
                    'sender_id'   => (int)$m['sender_id'],
                    'content'     => $m['content'],
                    'is_mine'     => (int)$m['sender_id'] === (int)$currentUser['id'],
                    'name'        => trim(($m['f_name'] ?? '') . ' ' . ($m['l_name'] ?? '')) ?: $m['username'],
                    'profile_pic' => $m['profile_pic'] ?? '',
                    'created_at'  => $m['created_at'],
                ];
            }, $messages);

            echo json_encode(['success' => true, 'messages' => $result]);
            break;

        /* ── Send Message ────────────────────────── */
        case 'send_message':
            $receiverId = (int)($_POST['receiver_id'] ?? 0);
            $content    = trim($_POST['content'] ?? '');

            if (!$receiverId || $content === '') {
                echo json_encode(['error' => 'Receiver and content are required']);
                exit;
            }

            $stmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, content) VALUES (:sid, :rid, :c)');
            $stmt->execute([':sid' => $currentUser['id'], ':rid' => $receiverId, ':c' => $content]);

            echo json_encode([
                'success' => true,
                'message' => [
                    'id'         => (int)$pdo->lastInsertId(),
                    'sender_id'  => (int)$currentUser['id'],
                    'content'    => $content,
                    'is_mine'    => true,
                    'name'       => trim(($currentUser['f_name'] ?? '') . ' ' . ($currentUser['l_name'] ?? '')) ?: $currentUser['username'],
                    'profile_pic'=> $currentUser['profile_pic'] ?? '',
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            ]);
            break;

        /* ── Search Users (for new conversation) ─── */
        case 'search_users':
            $q = trim($_GET['q'] ?? '');
            if (strlen($q) < 1) {
                echo json_encode(['success' => true, 'users' => []]);
                exit;
            }

            $stmt = $pdo->prepare("
                SELECT id, username, f_name, l_name, profile_pic 
                FROM users 
                WHERE id != :me AND (username LIKE :q OR f_name LIKE :q2 OR l_name LIKE :q3)
                ORDER BY username
                LIMIT 10
            ");
            $stmt->execute([
                ':me' => $currentUser['id'],
                ':q'  => "%$q%", ':q2' => "%$q%", ':q3' => "%$q%"
            ]);

            $result = array_map(function($u) {
                return [
                    'id'          => (int)$u['id'],
                    'username'    => $u['username'],
                    'name'        => trim(($u['f_name'] ?? '') . ' ' . ($u['l_name'] ?? '')) ?: $u['username'],
                    'profile_pic' => $u['profile_pic'] ?? '',
                ];
            }, $stmt->fetchAll());

            echo json_encode(['success' => true, 'users' => $result]);
            break;

        /* ── Get Unread Count (for badge) ─────────── */
        case 'unread_count':
            $stmt = $pdo->prepare('SELECT COUNT(*) as c FROM messages WHERE receiver_id = :me AND is_read = 0');
            $stmt->execute([':me' => $currentUser['id']]);
            echo json_encode(['success' => true, 'count' => (int)$stmt->fetch()['c']]);
            break;

        /* ── Delete Conversation ─────────────────── */
        case 'delete_conversation':
            $otherId = (int)($_POST['user_id'] ?? 0);
            if (!$otherId) {
                echo json_encode(['error' => 'Invalid user ID']);
                exit;
            }

            $myId = $currentUser['id'];

            // Delete all messages between the two users
            $stmt = $pdo->prepare("DELETE FROM messages WHERE (sender_id = :me AND receiver_id = :other) OR (sender_id = :other2 AND receiver_id = :me2)");
            $stmt->execute([':me' => $myId, ':other' => $otherId, ':other2' => $otherId, ':me2' => $myId]);

            echo json_encode(['success' => true, 'message' => 'Conversation deleted']);
            break;

        default:
            echo json_encode(['error' => 'Unknown action']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
