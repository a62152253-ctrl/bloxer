<?php
require_once '../controllers/core/mainlogincore.php';

$auth = new AuthCore();

$action = $_POST['action'] ?? null;
$user = $auth->getCurrentUser();

// Handle comment submission
if ($action === 'submit_comment' && $user) {
    $project_id = intval($_POST['project_id'] ?? 0);
    $app_id = intval($_POST['app_id'] ?? 0);
    $comment_text = trim($_POST['comment'] ?? '');
    $parent_id = intval($_POST['parent_id'] ?? 0);
    
    if (empty($comment_text)) {
        echo json_encode(['success' => false, 'error' => 'Comment cannot be empty']);
        SecurityUtils::safeExit('', 400, 'warning');
    }
    
    if (strlen($comment_text) > 1000) {
        echo json_encode(['success' => false, 'error' => 'Comment too long (max 1000 characters)']);
        SecurityUtils::safeExit('', 400, 'warning');
    }
    
    if ($project_id === 0 && $app_id === 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid target']);
        SecurityUtils::safeExit('', 400, 'warning');
    }
    
    $conn = $auth->getConnection();
    
    // Verify access permissions
    if ($project_id > 0) {
        $stmt = $conn->prepare("SELECT id FROM projects WHERE id = ? AND status = 'published'");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows === 0) {
            echo json_encode(['success' => false, 'error' => 'Project not found']);
            SecurityUtils::safeExit('', 404, 'warning');
        }
    }
    
    if ($app_id > 0) {
        $stmt = $conn->prepare("SELECT id FROM apps WHERE id = ? AND status IN ('published', 'featured')");
        $stmt->bind_param("i", $app_id);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows === 0) {
            echo json_encode(['success' => false, 'error' => 'App not found']);
            SecurityUtils::safeExit('', 404, 'warning');
        }
    }
    
    // Insert comment
    $stmt = $conn->prepare("
        INSERT INTO comments (user_id, project_id, app_id, parent_id, comment_text, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("iiis", $user['id'], $project_id, $app_id, $parent_id, $comment_text);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Comment posted successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to post comment']);
    }
    SecurityUtils::safeExit('', 400, 'warning');
}

// Handle comment deletion
if ($action === 'delete_comment' && $user) {
    $comment_id = intval($_POST['comment_id'] ?? 0);
    
    if ($comment_id === 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid comment ID']);
        SecurityUtils::safeExit('', 400, 'warning');
    }
    
    $conn = $auth->getConnection();
    
    // Verify comment ownership or admin rights
    $stmt = $conn->prepare("
        SELECT c.*, p.user_id as project_owner_id, a.project_id as app_project_id
        FROM comments c
        LEFT JOIN projects p ON c.project_id = p.id
        LEFT JOIN apps a ON c.app_id = a.id
        WHERE c.id = ?
    ");
    $stmt->bind_param("i", $comment_id);
    $stmt->execute();
    $comment = $stmt->get_result()->fetch_assoc();
    
    if (!$comment) {
        echo json_encode(['success' => false, 'error' => 'Comment not found']);
        SecurityUtils::safeExit('', 400, 'warning');
    }
    
    // Check if user can delete this comment
    $can_delete = ($comment['user_id'] == $user['id']) || 
                 ($comment['project_owner_id'] == $user['id']) ||
                 ($comment['app_project_id'] && isProjectOwner($conn, $comment['app_project_id'], $user['id']));
    
    if (!$can_delete) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        SecurityUtils::safeExit('', 400, 'warning');
    }
    
    // Delete comment and its replies
    $stmt = $conn->prepare("DELETE FROM comments WHERE id = ? OR parent_id = ?");
    $stmt->bind_param("ii", $comment_id, $comment_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Comment deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to delete comment']);
    }
    SecurityUtils::safeExit('', 400, 'warning');
}

// Get comments for project or app
if ($action === 'get_comments') {
    $project_id = intval($_POST['project_id'] ?? 0);
    $app_id = intval($_POST['app_id'] ?? 0);
    $page = max(1, intval($_POST['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    if ($project_id === 0 && $app_id === 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid target']);
        SecurityUtils::safeExit('', 400, 'warning');
    }
    
    $conn = $auth->getConnection();
    
    // Build query
    if ($project_id > 0) {
        $target_field = 'project_id';
        $target_value = $project_id;
    } else {
        $target_field = 'app_id';
        $target_value = $app_id;
    }
    
    // Get comments with user info
    $stmt = $conn->prepare("
        SELECT c.*, u.username, u.avatar_url,
               CASE WHEN c.user_id = ? THEN 1 ELSE 0 END as is_own_comment
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.$target_field = ? AND c.parent_id = 0
        ORDER BY c.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $user_id = $user ? $user['id'] : 0;
    $stmt->bind_param("iiii", $user_id, $target_value, $limit, $offset);
    $stmt->execute();
    $comments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get replies for each comment
    foreach ($comments as &$comment) {
        $stmt = $conn->prepare("
            SELECT c.*, u.username, u.avatar_url,
                   CASE WHEN c.user_id = ? THEN 1 ELSE 0 END as is_own_comment
            FROM comments c
            JOIN users u ON c.user_id = u.id
            WHERE c.parent_id = ?
            ORDER BY c.created_at ASC
        ");
        $stmt->bind_param("ii", $user_id, $comment['id']);
        $stmt->execute();
        $replies = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $comment['replies'] = $replies;
        $comment['reply_count'] = count($replies);
    }
    
    // Get total count
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM comments WHERE $target_field = ? AND parent_id = 0");
    $stmt->bind_param("i", $target_value);
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];
    
    echo json_encode([
        'success' => true,
        'comments' => $comments,
        'total' => $total,
        'pages' => ceil($total / $limit)
    ]);
    SecurityUtils::safeExit('', 400, 'warning');
}

function isProjectOwner($conn, $project_id, $user_id) {
    $stmt = $conn->prepare("SELECT user_id FROM projects WHERE id = ?");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $project = $stmt->get_result()->fetch_assoc();
    
    return $project && $project['user_id'] == $user_id;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
?>
