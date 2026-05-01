<?php
require_once '../controllers/core/mainlogincore.php';
require_once '../config/security.php';

$auth = new AuthCore();

if (!$auth->isLoggedIn() || !$auth->isDeveloper()) {
    SecurityUtils::safeExit(['success' => false, 'error' => 'Unauthorized access - developer privileges required'], 403, 'warning');
}

// Validate and sanitize inputs
$project_id = SecurityUtils::validateInput($_GET['project_id'] ?? null, 'int');
$file_path = SecurityUtils::validateInput($_GET['file'] ?? null, 'string', 255);

if (!$project_id || !$file_path) {
    SecurityUtils::safeExit(['success' => false, 'error' => 'Missing required parameters: project_id and file_path'], 400, 'warning');
}

// Prevent directory traversal attacks
if (strpos($file_path, '..') !== false || strpos($file_path, '/') !== false || strpos($file_path, '\\') !== false) {
    SecurityUtils::logSecurityEvent('Directory traversal attempt', "File path: $file_path, Project ID: $project_id");
    SecurityUtils::safeExit(['success' => false, 'error' => 'Invalid file path'], 400, 'warning');
}

// Only allow specific file extensions
$allowed_extensions = ['php', 'html', 'htm', 'css', 'js', 'jsx', 'ts', 'tsx', 'json', 'xml', 'txt', 'md', 'sql'];
$file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
if (!in_array($file_extension, $allowed_extensions)) {
    SecurityUtils::logSecurityEvent('Unauthorized file type access', "File: $file_path, Extension: $file_extension");
    SecurityUtils::safeExit(['success' => false, 'error' => 'File type not allowed'], 403, 'warning');
}
$user = $auth->getCurrentUser();

$conn = $auth->getConnection();
if (!$conn) {
    SecurityUtils::safeExit(['success' => false, 'error' => 'Database connection failed'], 500, 'error');
}
// Verify project ownership
$stmt = $conn->prepare("SELECT id FROM projects WHERE id = ? AND user_id = ?");
if (!$stmt) {
    SecurityUtils::safeExit(['success' => false, 'error' => 'Database error during ownership check'], 500, 'error');
}
$stmt->bind_param("ii", $project_id, $user['id']);
if (!$stmt->execute()) {
    SecurityUtils::safeExit(['success' => false, 'error' => 'Database error during ownership verification'], 500, 'error');
}

if ($stmt->get_result()->num_rows === 0) {
    SecurityUtils::logSecurityEvent('Unauthorized project access', "Project ID: $project_id, User ID: {$user['id']}");
    SecurityUtils::safeExit(['success' => false, 'error' => 'Project not found or access denied'], 403, 'warning');
}

// Get file content
$stmt = $conn->prepare("SELECT content FROM project_files WHERE project_id = ? AND file_path = ?");
if (!$stmt) {
    SecurityUtils::safeExit(['success' => false, 'error' => 'Database error during file query'], 500, 'error');
}
$stmt->bind_param("is", $project_id, $file_path);
if (!$stmt->execute()) {
    SecurityUtils::safeExit(['success' => false, 'error' => 'Database error during file execution'], 500, 'error');
}

$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $file = $result->fetch_assoc();
    SecurityUtils::safeExit(['success' => true, 'content' => $file['content'], 'size' => strlen($file['content'])], 200, 'success');
} else {
    SecurityUtils::safeExit(['success' => false, 'error' => 'File not found'], 404, 'warning');
}
?>
