<?php
require_once '../controllers/core/mainlogincore.php';

$auth = new AuthCore();

if (!$auth->isLoggedIn() || !$auth->isDeveloper()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access - developer privileges required']);
    exit();
}

$project_id = $_GET['project_id'] ?? null;
$file_path = $_GET['file'] ?? null;
$user = $auth->getCurrentUser();

if (!$project_id || !$file_path) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters: project_id and file_path']);
    exit();
}

$conn = $auth->getConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}
// Verify project ownership
$stmt = $conn->prepare("SELECT id FROM projects WHERE id = ? AND user_id = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Database error during ownership check']);
    exit();
}
$stmt->bind_param("ii", $project_id, $user['id']);
if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'error' => 'Database error during ownership verification']);
    exit();
}

if ($stmt->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Project not found or access denied']);
    exit();
}

// Get file content
$stmt = $conn->prepare("SELECT content FROM project_files WHERE project_id = ? AND file_path = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Database error during file query']);
    exit();
}
$stmt->bind_param("is", $project_id, $file_path);
if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'error' => 'Database error during file execution']);
    exit();
}

$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $file = $result->fetch_assoc();
    echo json_encode(['success' => true, 'content' => $file['content'], 'size' => strlen($file['content'])]);
} else {
    echo json_encode(['success' => false, 'error' => 'File not found']);
}
?>
