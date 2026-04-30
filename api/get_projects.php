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

try {
    $user = $auth->getCurrentUser();
    $conn = $auth->getConnection();
    
    $stmt = $conn->prepare("
        SELECT p.*, COUNT(pf.id) as file_count 
        FROM projects p 
        LEFT JOIN project_files pf ON p.id = pf.project_id 
        WHERE p.user_id = ? 
        GROUP BY p.id 
        ORDER BY p.updated_at DESC
    ");
    
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode([
        'success' => true,
        'projects' => $projects,
        'count' => count($projects)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
