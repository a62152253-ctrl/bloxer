<?php
require_once __DIR__ . '/../../bootstrap.php';

$auth = new AuthCore();

if (!$auth->isLoggedIn()) {
    SecurityUtils::safeExit('Unauthorized access', 401, 'warning');
}

$version_id = $_GET['id'] ?? null;

if (!$version_id) {
    SecurityUtils::safeExit('Version ID required', 400, 'warning');
}

$conn = $auth->getConnection();

// Get version details
$stmt = $conn->prepare("
    SELECT av.*, a.title as app_title, a.project_id, p.user_id 
    FROM app_versions av 
    JOIN apps a ON av.app_id = a.id 
    JOIN projects p ON a.project_id = p.id 
    WHERE av.id = ?
");
$stmt->bind_param("i", $version_id);
$stmt->execute();
$version = $stmt->get_result()->fetch_assoc();

if (!$version) {
    SecurityUtils::safeExit('Version not found', 404, 'warning');
}

$user = $auth->getCurrentUser();

// Check access (owner or installed user)
$is_owner = $version['user_id'] == $user['id'];

if (!$is_owner) {
    // Check if user has this app installed
    $stmt = $conn->prepare("SELECT id FROM user_apps WHERE user_id = ? AND app_id = ?");
    $stmt->bind_param("ii", $user['id'], $version['app_id']);
    $stmt->execute();
    $is_installed = $stmt->get_result()->num_rows > 0;
    
    if (!$is_installed) {
        SecurityUtils::safeExit('Access denied', 403, 'warning');
    }
}

// Generate download package
$package_data = $version['package_data'] ?? '';
$app_title = $version['app_title'];
$version_number = $version['version'];

if (empty($package_data)) {
    SecurityUtils::safeExit('Version package not available', 404, 'warning');
}

// Create ZIP file
$zip_file = tempnam(sys_get_temp_dir(), 'version_');
$zip = new ZipArchive();

if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
    $files = json_decode($package_data, true);
    
    if ($files) {
        foreach ($files as $file) {
            $zip->addFromString($file['file_path'], $file['content']);
        }
    }
    
    // Add changelog
    $changelog_content = "Version {$version_number}\n" . str_repeat("=", 50) . "\n\n";
    $changelog_content .= "App: {$app_title}\n";
    $changelog_content .= "Released: " . date('Y-m-d', strtotime($version['created_at'])) . "\n\n";
    $changelog_content .= "Changelog:\n" . $version['changelog'];
    
    $zip->addFromString('CHANGELOG.txt', $changelog_content);
    $zip->close();
    
    // Send file to user
    $filename = "{$app_title}_v{$version_number}.zip";
    
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($zip_file));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    readfile($zip_file);
    
    // Clean up
    unlink($zip_file);
    exit();
} else {
    SecurityUtils::safeExit('Failed to create download package', 500, 'warning');
}
?>
