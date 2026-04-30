<?php
require_once '../controllers/core/mainlogincore.php';

header('Content-Type: application/json');

$auth = new AuthCore();

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Authentication required - please log in']);
    exit();
}

if (!$auth->isDeveloper()) {
    echo json_encode(['success' => false, 'error' => 'Developer privileges required']);
    exit();
}

$project_id = $_GET['project_id'] ?? null;

if (!$project_id) {
    echo json_encode(['success' => false, 'error' => 'Project ID is required']);
    exit();
}

try {
    $user = $auth->getCurrentUser();
    $conn = $auth->getConnection();
    
    // Verify project ownership
    $stmt = $conn->prepare("SELECT id FROM projects WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $project_id, $user['id']);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Project not found or access denied']);
        exit();
    }
    
    // Get project files
    $stmt = $conn->prepare("SELECT * FROM project_files WHERE project_id = ? ORDER BY file_path");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $files = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode([
        'success' => true,
        'files' => $files,
        'count' => count($files)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
