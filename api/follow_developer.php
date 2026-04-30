<?php
require_once '../controllers/core/mainlogincore.php';
require_once '../config/security.php';

header('Content-Type: application/json');

$auth = new AuthCore();

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$user = $auth->getCurrentUser();
$developer_id = SecurityUtils::validateInput($_POST['developer_id'] ?? null, 'int');
$action = SecurityUtils::validateInput($_POST['action'] ?? null, 'string');

if (!$developer_id || !in_array($action, ['follow', 'unfollow'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

$conn = $auth->getConnection();

// Check if developer exists and is actually a developer
$stmt = $conn->prepare("SELECT id, user_type FROM users WHERE id = ? AND user_type = 'developer' AND status = 'active'");
$stmt->bind_param("i", $developer_id);
$stmt->execute();
$developer = $stmt->get_result()->fetch_assoc();

if (!$developer) {
    echo json_encode(['success' => false, 'message' => 'Developer not found']);
    exit;
}

// Check if developer_follows table exists
$check_table = $conn->query("SHOW TABLES LIKE 'developer_follows'");
if ($check_table->num_rows === 0) {
    // Create the table if it doesn't exist
    $create_table_sql = file_get_contents('../database/create_developer_follows_table.sql');
    if ($create_table_sql) {
        $conn->multi_query($create_table_sql);
        // Clear results
        while ($conn->next_result()) {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        }
    }
}

if ($action === 'follow') {
    // Check if already following
    $stmt = $conn->prepare("SELECT id FROM developer_follows WHERE follower_id = ? AND developer_id = ?");
    $stmt->bind_param("ii", $user['id'], $developer_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Already following this developer']);
        exit;
    }
    
    // Follow the developer
    $stmt = $conn->prepare("INSERT INTO developer_follows (follower_id, developer_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $user['id'], $developer_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Developer followed successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to follow developer']);
    }
    
} else if ($action === 'unfollow') {
    // Unfollow the developer
    $stmt = $conn->prepare("DELETE FROM developer_follows WHERE follower_id = ? AND developer_id = ?");
    $stmt->bind_param("ii", $user['id'], $developer_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Developer unfollowed successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to unfollow developer']);
    }
}

$stmt->close();
$conn->close();
?>
