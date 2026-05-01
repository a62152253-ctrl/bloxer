<?php
/**
 * Delete Account Handler
 * Processes account deletion requests
 */

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../bootstrap.php';

// Check if user is logged in
$auth = new AuthCore();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'You must be logged in to delete your account']);
    exit;
}

// Get current user
$user = $auth->getCurrentUser();
$password = $_POST['password'] ?? '';

if (empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Password is required']);
    exit;
}

// Verify password
if (!password_verify($password, $user['password'])) {
    echo json_encode(['success' => false, 'message' => 'Incorrect password']);
    exit;
}

try {
    $conn = $auth->getConnection();
    $conn->begin_transaction();
    
    $userId = $user['id'];
    
    // 1. Delete user's installed apps
    $stmt = $conn->prepare("DELETE FROM user_apps WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    
    // 2. Delete user's reviews
    $stmt = $conn->prepare("DELETE FROM app_reviews WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    
    // 3. Delete user's ratings
    $stmt = $conn->prepare("DELETE FROM app_ratings WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    
    // 4. Delete user's saved apps
    $stmt = $conn->prepare("DELETE FROM saved_apps WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    
    // 5. Delete user's notifications
    $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    
    // 6. Delete user's notification preferences
    $stmt = $conn->prepare("DELETE FROM notification_preferences WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    
    // 7. Delete user's projects and associated files
    // First get all project IDs
    $stmt = $conn->prepare("SELECT id FROM projects WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $projectIds = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($projectIds as $project) {
        $projectId = $project['id'];
        
        // Delete project files
        $stmt = $conn->prepare("DELETE FROM project_files WHERE project_id = ?");
        $stmt->bind_param("i", $projectId);
        $stmt->execute();
        
        // Delete project versions
        $stmt = $conn->prepare("DELETE FROM project_versions WHERE project_id = ?");
        $stmt->bind_param("i", $projectId);
        $stmt->execute();
        
        // Delete apps associated with this project
        $stmt = $conn->prepare("DELETE FROM apps WHERE project_id = ?");
        $stmt->bind_param("i", $projectId);
        $stmt->execute();
    }
    
    // Delete the projects themselves
    $stmt = $conn->prepare("DELETE FROM projects WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    
    // 8. Delete user's offers (as buyer)
    $stmt = $conn->prepare("DELETE FROM offers WHERE buyer_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    
    // 9. Delete user's offer messages
    $stmt = $conn->prepare("DELETE FROM offer_messages WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    
    // 10. Delete user's comments
    $stmt = $conn->prepare("DELETE FROM comments WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    
    // 11. Delete user's collaboration invites (both sent and received)
    $stmt = $conn->prepare("DELETE FROM collaboration_invites WHERE user_id = ? OR invited_user_id = ?");
    $stmt->bind_param("ii", $userId, $userId);
    $stmt->execute();
    
    // 12. Delete user's collaboration memberships
    $stmt = $conn->prepare("DELETE FROM collaboration_members WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    
    // 13. Delete user's chat participations
    $stmt = $conn->prepare("DELETE FROM chat_participants WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    
    // 14. Delete user's chat messages
    $stmt = $conn->prepare("DELETE FROM chat_messages WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    
    // 15. Delete user's preferences
    $stmt = $conn->prepare("DELETE FROM user_preferences WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    
    // 16. Delete user's developer follows (as follower)
    $stmt = $conn->prepare("DELETE FROM developer_follows WHERE follower_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    
    // 17. Delete remember tokens
    $stmt = $conn->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    
    // 18. Finally, delete the user account
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    // Log out user
    $auth->logout();
    
    echo json_encode(['success' => true, 'message' => 'Account deleted successfully']);
    
} catch (Exception $e) {
    // Rollback on error
    if (isset($conn)) {
        $conn->rollback();
    }
    
    error_log("Account deletion error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while deleting your account. Please try again.']);
}
?>
