<?php
require_once '../controllers/core/mainlogincore.php';
require_once '../config/security.php';

$auth = new AuthCore();

if (!$auth->isLoggedIn() || !$auth->isDeveloper()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access - developer privileges required']);
    SecurityUtils::safeExit('', 403, 'warning');
}

$user = $auth->getCurrentUser();
$action = SecurityUtils::validateInput($_POST['action'] ?? null, 'action');

// Get file versions
if ($action === 'get_versions') {
    $project_id = SecurityUtils::validateInput($_POST['project_id'] ?? null, 'int');
    $file_path = SecurityUtils::validateInput($_POST['file_path'] ?? '', 'string', 255);
    
    if ($project_id === 0 || empty($file_path)) {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters: project_id and file_path are required']);
        SecurityUtils::safeExit('', 400, 'warning');
    }
    
    $conn = $auth->getConnection();
    
    // Verify project ownership
    $stmt = $conn->prepare("SELECT id FROM projects WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $project_id, $user['id']);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Project not found']);
        SecurityUtils::safeExit('', 404, 'warning');
    }
    
    // Get file versions
    $stmt = $conn->prepare("
        SELECT id, file_path, content, file_type, version_number, change_description, created_at FROM file_versions 
        WHERE project_id = ? AND file_path = ? 
        ORDER BY version_number DESC
        LIMIT 20
    ");
    $stmt->bind_param("is", $project_id, $file_path);
    $stmt->execute();
    $versions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode(['success' => true, 'versions' => $versions, 'count' => count($versions)]);
    SecurityUtils::safeExit('', 200, 'info');
}

// Restore file version
if ($action === 'restore_version') {
    $project_id = SecurityUtils::validateInput($_POST['project_id'] ?? null, 'int');
    $file_path = SecurityUtils::validateInput($_POST['file_path'] ?? '', 'string', 255);
    $version_id = SecurityUtils::validateInput($_POST['version_id'] ?? null, 'int');
    
    if ($project_id === 0 || empty($file_path) || $version_id === 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters: project_id, file_path, and version_id are required']);
        SecurityUtils::safeExit('', 400, 'warning');
    }
    
    $conn = $auth->getConnection();
    
    // Verify project ownership
    $stmt = $conn->prepare("SELECT id FROM projects WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $project_id, $user['id']);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Project not found']);
        SecurityUtils::safeExit('', 404, 'warning');
    }
    
    // Get version to restore
    $stmt = $conn->prepare("
        SELECT id, file_path, content, file_type, version_number, change_description FROM file_versions 
        WHERE id = ? AND project_id = ? AND file_path = ?
    ");
    $stmt->bind_param("iis", $version_id, $project_id, $file_path);
    $stmt->execute();
    $version = $stmt->get_result()->fetch_assoc();
    
    if (!$version) {
        echo json_encode(['success' => false, 'error' => 'Version not found or access denied']);
        SecurityUtils::safeExit('', 404, 'warning');
    }
    
    // Update current file with version content
    $stmt = $conn->prepare("
        UPDATE project_files 
        SET content = ?, file_size = ?, updated_at = NOW() 
        WHERE project_id = ? AND file_path = ?
    ");
    $file_size = strlen($version['content']);
    $stmt->bind_param("ssis", $version['content'], $file_size, $project_id, $file_path);
    
    if ($stmt->execute()) {
        // Create a new version entry for the restore
        createFileVersion($conn, $project_id, $file_path, $version['content'], "Restored from version {$version['version_number']}");
        
        echo json_encode(['success' => true, 'message' => 'File restored successfully']);
        SecurityUtils::safeExit('', 200, 'info');
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to restore file - please try again']);
        SecurityUtils::safeExit('', 500, 'error');
    }
}

// Create file version (called from editor.php when saving)
function createFileVersion($conn, $project_id, $file_path, $content, $change_description = '') {
    // Get current version number
    $stmt = $conn->prepare("
        SELECT MAX(version_number) as max_version 
        FROM file_versions 
        WHERE project_id = ? AND file_path = ?
    ");
    $stmt->bind_param("is", $project_id, $file_path);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $new_version = ($result['max_version'] ?? 0) + 1;
    
    // Only create version if content is different from last version
    $stmt = $conn->prepare("
        SELECT content FROM file_versions 
        WHERE project_id = ? AND file_path = ? 
        ORDER BY version_number DESC 
        LIMIT 1
    ");
    $stmt->bind_param("is", $project_id, $file_path);
    $stmt->execute();
    $last_version = $stmt->get_result()->fetch_assoc();
    
    if ($last_version && $last_version['content'] === $content) {
        return; // No change, don't create new version
    }
    
    // Insert new version
    $stmt = $conn->prepare("
        INSERT INTO file_versions 
        (project_id, file_path, version_number, content, change_description, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("isis", $project_id, $file_path, $new_version, $content, $change_description);
    $stmt->execute();
    
    // Clean up old versions (keep only last 20 per file)
    $stmt = $conn->prepare("
        DELETE FROM file_versions 
        WHERE project_id = ? AND file_path = ? AND id NOT IN (
            SELECT id FROM (
                SELECT id FROM file_versions 
                WHERE project_id = ? AND file_path = ? 
                ORDER BY version_number DESC 
                LIMIT 20
            ) as recent
        )
    ");
    $stmt->bind_param("isss", $project_id, $file_path, $project_id, $file_path);
    $stmt->execute();
}

// Get project version history
if ($action === 'get_project_history') {
    $project_id = SecurityUtils::validateInput($_POST['project_id'] ?? null, 'int');
    
    if ($project_id === 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid project ID']);
        SecurityUtils::safeExit('', 400, 'warning');
    }
    
    $conn = $auth->getConnection();
    
    // Verify project ownership
    $stmt = $conn->prepare("SELECT id FROM projects WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $project_id, $user['id']);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Project not found']);
        SecurityUtils::safeExit('', 404, 'warning');
    }
    
    // Get recent version history across all files
    $stmt = $conn->prepare("
        SELECT DISTINCT file_path, MAX(version_number) as latest_version,
               MAX(created_at) as latest_created
        FROM file_versions 
        WHERE project_id = ?
        GROUP BY file_path
        ORDER BY latest_created DESC
        LIMIT 50
    ");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode(['success' => true, 'history' => $history]);
    SecurityUtils::safeExit('', 200, 'info');
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
SecurityUtils::safeExit('', 400, 'warning');
?>
