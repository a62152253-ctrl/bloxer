<?php
require_once '../core/mainlogincore.php';

$auth = new AuthCore();

if (!$auth->isLoggedIn() || !$auth->isDeveloper()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$user = $auth->getCurrentUser();
$action = $_POST['action'] ?? null;

// Handle project export
if ($action === 'export_project') {
    $project_id = intval($_POST['project_id'] ?? 0);
    
    if ($project_id === 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid project ID']);
        exit();
    }
    
    $conn = $auth->getConnection();
    
    // Verify project ownership
    $stmt = $conn->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $project_id, $user['id']);
    $stmt->execute();
    $project = $stmt->get_result()->fetch_assoc();
    
    if (!$project) {
        echo json_encode(['success' => false, 'error' => 'Project not found']);
        exit();
    }
    
    // Get project files
    $stmt = $conn->prepare("SELECT * FROM project_files WHERE project_id = ? ORDER BY file_path");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $files = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Create export data structure
    $export_data = [
        'project' => [
            'name' => $project['name'],
            'description' => $project['description'],
            'framework' => $project['framework'],
            'status' => $project['status'],
            'created_at' => $project['created_at'],
            'updated_at' => $project['updated_at']
        ],
        'files' => []
    ];
    
    foreach ($files as $file) {
        $export_data['files'][] = [
            'path' => $file['file_path'],
            'name' => $file['file_name'],
            'type' => $file['file_type'],
            'content' => $file['content'],
            'size' => $file['file_size']
        ];
    }
    
    // Set headers for JSON download
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . sanitize_filename($project['name']) . '.json"');
    
    echo json_encode($export_data, JSON_PRETTY_PRINT);
    exit();
}

// Handle project import
if ($action === 'import_project') {
    if (!isset($_FILES['import_file'])) {
        echo json_encode(['success' => false, 'error' => 'No file uploaded']);
        exit();
    }
    
    $file = $_FILES['import_file'];
    
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'File upload error']);
        exit();
    }
    
    if ($file['size'] > 10 * 1024 * 1024) { // 10MB limit
        echo json_encode(['success' => false, 'error' => 'File too large']);
        exit();
    }
    
    $content = file_get_contents($file['tmp_name']);
    $import_data = json_decode($content, true);
    
    if (!$import_data || !isset($import_data['project']) || !isset($import_data['files'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid JSON format']);
        exit();
    }
    
    $conn = $auth->getConnection();
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Create new project
        $project_name = $import_data['project']['name'] . ' (Imported)';
        $project_description = $import_data['project']['description'] ?? '';
        $framework = $import_data['project']['framework'] ?? 'vanilla';
        
        $stmt = $conn->prepare("
            INSERT INTO projects (user_id, name, description, framework, status, created_at, updated_at) 
            VALUES (?, ?, ?, ?, 'draft', NOW(), NOW())
        ");
        $stmt->bind_param("issss", $user['id'], $project_name, $project_description, $framework);
        $stmt->execute();
        
        $new_project_id = $conn->insert_id;
        
        // Import files
        foreach ($import_data['files'] as $file_data) {
            $file_path = $file_data['path'];
            $file_name = $file_data['name'];
            $file_type = $file_data['type'];
            $file_content = $file_data['content'] ?? '';
            $file_size = strlen($file_content);
            
            $stmt = $conn->prepare("
                INSERT INTO project_files (project_id, file_path, file_name, file_type, content, file_size) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("issssi", $new_project_id, $file_path, $file_name, $file_type, $file_content, $file_size);
            $stmt->execute();
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'project_id' => $new_project_id,
            'message' => 'Project imported successfully'
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'Import failed: ' . $e->getMessage()]);
    }
    
    exit();
}

// Handle template export
if ($action === 'export_template') {
    $project_id = intval($_POST['project_id'] ?? 0);
    
    if ($project_id === 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid project ID']);
        exit();
    }
    
    $conn = $auth->getConnection();
    
    // Verify project ownership
    $stmt = $conn->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $project_id, $user['id']);
    $stmt->execute();
    $project = $stmt->get_result()->fetch_assoc();
    
    if (!$project) {
        echo json_encode(['success' => false, 'error' => 'Project not found']);
        exit();
    }
    
    // Get project files
    $stmt = $conn->prepare("SELECT * FROM project_files WHERE project_id = ? ORDER BY file_path");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $files = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Create template structure
    $template_data = [
        'template' => [
            'name' => $project['name'],
            'description' => $project['description'],
            'framework' => $project['framework'],
            'category' => 'general',
            'author' => $user['username'],
            'version' => '1.0.0',
            'created_at' => date('Y-m-d H:i:s')
        ],
        'files' => []
    ];
    
    foreach ($files as $file) {
        $template_data['files'][] = [
            'path' => $file['file_path'],
            'name' => $file['file_name'],
            'type' => $file['file_type'],
            'content' => $file['content']
        ];
    }
    
    // Set headers for JSON download
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . sanitize_filename($project['name']) . '-template.json"');
    
    echo json_encode($template_data, JSON_PRETTY_PRINT);
    exit();
}

function sanitize_filename($filename) {
    // Remove special characters and replace spaces with underscores
    $filename = preg_replace('/[^a-zA-Z0-9\-_\s]/', '', $filename);
    $filename = preg_replace('/\s+/', '_', $filename);
    return strtolower($filename);
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
?>
