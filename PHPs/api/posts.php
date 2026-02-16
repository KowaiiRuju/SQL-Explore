<?php
/**
 * Posts API — handles AJAX requests for likes, comments, and post deletion.
 * Expects JSON or form data via POST.
 * All POST/DELETE operations require valid CSRF token.
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

        /* ── Toggle Like ─────────────────────────── */
        case 'toggle_like':
            $postId = (int)($_POST['post_id'] ?? 0);
            if (!$postId) {
                echo json_encode(['error' => 'Invalid post ID']);
                exit;
            }

            // Check if already liked
            $check = $pdo->prepare('SELECT id FROM post_likes WHERE post_id = :pid AND user_id = :uid');
            $check->execute([':pid' => $postId, ':uid' => $currentUser['id']]);

            if ($check->fetch()) {
                // Unlike
                $pdo->prepare('DELETE FROM post_likes WHERE post_id = :pid AND user_id = :uid')
                     ->execute([':pid' => $postId, ':uid' => $currentUser['id']]);
                $liked = false;
            } else {
                // Like
                $pdo->prepare('INSERT INTO post_likes (post_id, user_id) VALUES (:pid, :uid)')
                     ->execute([':pid' => $postId, ':uid' => $currentUser['id']]);
                $liked = true;
            }

            // Get updated count
            $count = $pdo->prepare('SELECT COUNT(*) as c FROM post_likes WHERE post_id = :pid');
            $count->execute([':pid' => $postId]);

            echo json_encode([
                'success' => true,
                'liked'   => $liked,
                'count'   => (int)$count->fetch()['c']
            ]);
            break;

        /* ── Add Comment ─────────────────────────── */
        case 'add_comment':
            $postId  = (int)($_POST['post_id'] ?? 0);
            $content = trim($_POST['content'] ?? '');

            if (!$postId || $content === '') {
                echo json_encode(['error' => 'Post ID and content are required']);
                exit;
            }

            $stmt = $pdo->prepare('INSERT INTO post_comments (post_id, user_id, content) VALUES (:pid, :uid, :c)');
            $stmt->execute([':pid' => $postId, ':uid' => $currentUser['id'], ':c' => $content]);

            $commentId = $pdo->lastInsertId();

            // Get comment count
            $countStmt = $pdo->prepare('SELECT COUNT(*) as c FROM post_comments WHERE post_id = :pid');
            $countStmt->execute([':pid' => $postId]);

            echo json_encode([
                'success' => true,
                'comment' => [
                    'id'         => (int)$commentId,
                    'content'    => $content,
                    'username'   => $currentUser['username'],
                    'name'       => trim(($currentUser['f_name'] ?? '') . ' ' . ($currentUser['l_name'] ?? '')) ?: $currentUser['username'],
                    'profile_pic'=> $currentUser['profile_pic'] ?? '',
                    'created_at' => date('Y-m-d H:i:s'),
                ],
                'count' => (int)$countStmt->fetch()['c']
            ]);
            break;

        /* ── Get Comments ────────────────────────── */
        case 'get_comments':
            $postId = (int)($_GET['post_id'] ?? $_POST['post_id'] ?? 0);
            if (!$postId) {
                echo json_encode(['error' => 'Invalid post ID']);
                exit;
            }

            $stmt = $pdo->prepare('
                SELECT c.*, u.username, u.f_name, u.l_name, u.profile_pic
                FROM post_comments c
                JOIN users u ON c.user_id = u.id
                WHERE c.post_id = :pid
                ORDER BY c.created_at ASC
            ');
            $stmt->execute([':pid' => $postId]);
            $comments = $stmt->fetchAll();

            $result = array_map(function($c) {
                return [
                    'id'          => (int)$c['id'],
                    'content'     => $c['content'],
                    'username'    => $c['username'],
                    'name'        => trim(($c['f_name'] ?? '') . ' ' . ($c['l_name'] ?? '')) ?: $c['username'],
                    'profile_pic' => $c['profile_pic'] ?? '',
                    'created_at'  => $c['created_at'],
                ];
            }, $comments);

            echo json_encode(['success' => true, 'comments' => $result]);
            break;

        /* ── Delete Post (admin only) ────────────── */
        case 'delete_post':
            if (empty($currentUser['is_admin'])) {
                echo json_encode(['error' => 'Admin only']);
                exit;
            }
            $postId = (int)($_POST['post_id'] ?? 0);
            if (!$postId) {
                echo json_encode(['error' => 'Invalid post ID']);
                exit;
            }

            // Get post image to delete file
            $post = $pdo->prepare('SELECT image FROM posts WHERE id = :id');
            $post->execute([':id' => $postId]);
            $postData = $post->fetch();
            if ($postData && !empty($postData['image'])) {
                $imgPath = __DIR__ . '/../../uploads/' . $postData['image'];
                if (file_exists($imgPath)) unlink($imgPath);
            }

            // Delete associated data
            $pdo->prepare('DELETE FROM post_likes WHERE post_id = :pid')->execute([':pid' => $postId]);
            $pdo->prepare('DELETE FROM post_comments WHERE post_id = :pid')->execute([':pid' => $postId]);
            $pdo->prepare('DELETE FROM posts WHERE id = :id')->execute([':id' => $postId]);

            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['error' => 'Unknown action']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
