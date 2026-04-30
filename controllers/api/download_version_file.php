<?php
require_once __DIR__ . '/../../bootstrap.php';

$auth = new AuthCore();

if (!$auth->isLoggedIn()) {
    SecurityUtils::safeRedirect('../auth/login.php', 302, 'Unauthorized access attempt');
}

$file_id = SecurityUtils::validateInput($_GET['id'] ?? null, 'id', ['int']);

if (!$file_id) {
    SecurityUtils::safeRedirect('../api/version-control.php', 400, 'Invalid file ID');
}

$conn = $auth->getConnection();

// Get file information and verify access
$stmt = $conn->prepare("
    SELECT vf.*, av.app_id, av.version, a.title as app_title
    FROM version_files vf
    JOIN app_versions av ON vf.version_id = av.id
    JOIN apps a ON av.app_id = a.id
    JOIN projects p ON a.project_id = p.id
    WHERE vf.id = ? AND p.user_id = ?
");
$stmt->bind_param("ii", $file_id, $auth->getCurrentUser()['id']);
$stmt->execute();
$file = $stmt->get_result()->fetch_assoc();

if (!$file) {
    SecurityUtils::safeRedirect('version-control.php', 404, 'File not found or access denied');
}

$file_path = '../' . $file['file_path'];

if (!file_exists($file_path)) {
    SecurityUtils::safeRedirect('version-control.php', 404, 'File not found on server');
}

// Set headers for file download
header('Content-Type: ' . $file['file_type']);
header('Content-Disposition: attachment; filename="' . htmlspecialchars($file['original_name']) . '"');
header('Content-Length: ' . $file['file_size']);
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Output file content
readfile($file_path);
exit();
?>
