<?php
require_once '../controllers/core/mainlogincore.php';
require_once '../config/security.php';

header('Content-Type: application/json');

$auth = new AuthCore();

if (!$auth->isLoggedIn()) {
    SecurityUtils::safeExit(['success' => false, 'error' => 'Authentication required - please log in'], 401, 'warning');
}

if (!$auth->isDeveloper()) {
    SecurityUtils::safeExit(['success' => false, 'error' => 'Developer privileges required'], 403, 'warning');
}

// Validate and sanitize project ID
$project_id = SecurityUtils::validateInput($_GET['project_id'] ?? null, 'int');

if (!$project_id) {
    SecurityUtils::safeExit(['success' => false, 'error' => 'Project ID is required'], 400, 'warning');
}

// Rate limiting check
if (!SecurityUtils::checkRateLimit('api_project_files', 30, 60)) { // 30 requests per minute
    SecurityUtils::logSecurityEvent('Rate limit exceeded', "Project files API, Project ID: $project_id");
    SecurityUtils::safeExit(['success' => false, 'error' => 'Rate limit exceeded'], 429, 'warning');
}

try {
    $user = $auth->getCurrentUser();
    $conn = $auth->getConnection();
    
    // Verify project ownership
    $stmt = $conn->prepare("SELECT id FROM projects WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $project_id, $user['id']);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 0) {
        SecurityUtils::logSecurityEvent('Unauthorized project access', "Project ID: $project_id, User ID: {$user['id']}");
        SecurityUtils::safeExit(['success' => false, 'error' => 'Project not found or access denied'], 403, 'warning');
    }
    
    // Get project files with security filtering
    $stmt = $conn->prepare("SELECT file_path, file_size, created_at FROM project_files WHERE project_id = ? ORDER BY file_path");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $files = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Sanitize file paths for output
    $safe_files = [];
    foreach ($files as $file) {
        $safe_files[] = [
            'file_path' => htmlspecialchars($file['file_path']),
            'file_size' => (int)$file['file_size'],
            'created_at' => $file['created_at']
        ];
    }
    
    SecurityUtils::safeExit([
        'success' => true,
        'files' => $safe_files,
        'count' => count($safe_files)
    ], 200, 'success');
    
} catch (Exception $e) {
    SecurityUtils::logSecurityEvent('Database error in get_project_files', "Error: " . $e->getMessage() . ", Project ID: $project_id");
    SecurityUtils::safeExit([
        'success' => false,
        'error' => 'Database error occurred'
    ], 500, 'error');
}
?>
