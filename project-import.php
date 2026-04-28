<?php
// Project Import Handler
require_once 'mainlogincore.php';

$auth = new AuthCore();

if (!$auth->isLoggedIn() || !$auth->isDeveloper()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Handle project import from sample files
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'import_sample') {
    $project_name = trim($_POST['project_name'] ?? '');
    $description = trim($_POST['project_description'] ?? '');
    $framework = $_POST['framework'] ?? 'vanilla';
    $template_id = $_POST['template_id'] ?? 'blank';
    
    if (empty($project_name)) {
        echo json_encode(['success' => false, 'error' => 'Project name is required']);
        exit();
    }
    
    // Get template files
    $templates = include 'project-templates-complete.php';
    $template = null;
    
    foreach ($templates['templates'] as $t) {
        if ($t['id'] === $template_id) {
            $template = $t;
            break;
        }
    }
    
    if (!$template) {
        echo json_encode(['success' => false, 'error' => 'Template not found']);
        exit();
    }
    
    // Create project
    $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $project_name));
    $slug = rtrim($slug, '-');
    
    $conn = $auth->getConnection();
    $stmt = $conn->prepare("
        INSERT INTO projects (user_id, name, description, slug, framework) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("issss", $user['id'], $project_name, $description, $slug, $framework);
    
    if ($stmt->execute()) {
        $new_project_id = $stmt->insert_id;
        
        // Create files from template
        foreach ($template['files'] as $file_name => $content) {
            $file_type = pathinfo($file_name, PATHINFO_EXTENSION);
            $file_type_map = [
                'html' => 'html',
                'css' => 'css', 
                'js' => 'js',
                'json' => 'json',
                'md' => 'md'
            ];
            $file_type_enum = $file_type_map[$file_type] ?? 'other';
            $file_size = strlen($content);
            
            $stmt = $conn->prepare("INSERT INTO project_files (project_id, file_path, file_name, file_type, content, file_size) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssi", $new_project_id, $file_name, $file_name, $file_type_enum, $content, $file_size);
            $stmt->execute();
        }
        
        echo json_encode(['success' => true, 'project_id' => $new_project_id]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to create project']);
    }
    exit();
}

// Handle file upload import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'import_files') {
    $project_name = trim($_POST['project_name'] ?? '');
    $description = trim($_POST['project_description'] ?? '');
    $framework = $_POST['framework'] ?? 'vanilla';
    
    if (empty($project_name)) {
        echo json_encode(['success' => false, 'error' => 'Project name is required']);
        exit();
    }
    
    if (!isset($_FILES['project_files'])) {
        echo json_encode(['success' => false, 'error' => 'No files uploaded']);
        exit();
    }
    
    // Create project
    $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $project_name));
    $slug = rtrim($slug, '-');
    
    $conn = $auth->getConnection();
    $stmt = $conn->prepare("
        INSERT INTO projects (user_id, name, description, slug, framework) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("issss", $user['id'], $project_name, $description, $slug, $framework);
    
    if ($stmt->execute()) {
        $new_project_id = $stmt->insert_id;
        $imported_files = 0;
        
        // Process uploaded files
        foreach ($_FILES['project_files']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['project_files']['error'][$key] === UPLOAD_ERR_OK) {
                $file_name = $_FILES['project_files']['name'][$key];
                $file_content = file_get_contents($tmp_name);
                
                $file_type = pathinfo($file_name, PATHINFO_EXTENSION);
                $file_type_map = [
                    'html' => 'html',
                    'css' => 'css', 
                    'js' => 'js',
                    'json' => 'json',
                    'md' => 'md'
                ];
                $file_type_enum = $file_type_map[$file_type] ?? 'other';
                $file_size = strlen($file_content);
                
                $stmt = $conn->prepare("INSERT INTO project_files (project_id, file_path, file_name, file_type, content, file_size) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("issssi", $new_project_id, $file_name, $file_name, $file_type_enum, $file_content, $file_size);
                
                if ($stmt->execute()) {
                    $imported_files++;
                }
            }
        }
        
        echo json_encode([
            'success' => true, 
            'project_id' => $new_project_id,
            'imported_files' => $imported_files
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to create project']);
    }
    exit();
}

// Handle URL import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'import_url') {
    $project_name = trim($_POST['project_name'] ?? '');
    $description = trim($_POST['project_description'] ?? '');
    $framework = $_POST['framework'] ?? 'vanilla';
    $import_url = trim($_POST['import_url'] ?? '');
    
    if (empty($project_name) || empty($import_url)) {
        echo json_encode(['success' => false, 'error' => 'Project name and URL are required']);
        exit();
    }
    
    // Validate URL
    if (!filter_var($import_url, FILTER_VALIDATE_URL)) {
        echo json_encode(['success' => false, 'error' => 'Invalid URL']);
        exit();
    }
    
    // Fetch content from URL
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'Bloxer-Importer/1.0'
        ]
    ]);
    
    $content = @file_get_contents($import_url, false, $context);
    
    if ($content === false) {
        echo json_encode(['success' => false, 'error' => 'Failed to fetch content from URL']);
        exit();
    }
    
    // Create project
    $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $project_name));
    $slug = rtrim($slug, '-');
    
    $conn = $auth->getConnection();
    $stmt = $conn->prepare("
        INSERT INTO projects (user_id, name, description, slug, framework) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("issss", $user['id'], $project_name, $description, $slug, $framework);
    
    if ($stmt->execute()) {
        $new_project_id = $stmt->insert_id;
        
        // Determine file type and name from URL
        $url_path = parse_url($import_url, PHP_URL_PATH);
        $file_name = basename($url_path) ?: 'imported.html';
        $file_type = pathinfo($file_name, PATHINFO_EXTENSION);
        
        $file_type_map = [
            'html' => 'html',
            'css' => 'css', 
            'js' => 'js',
            'json' => 'json',
            'md' => 'md'
        ];
        $file_type_enum = $file_type_map[$file_type] ?? 'other';
        $file_size = strlen($content);
        
        $stmt = $conn->prepare("INSERT INTO project_files (project_id, file_path, file_name, file_type, content, file_size) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssi", $new_project_id, $file_name, $file_name, $file_type_enum, $content, $file_size);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'project_id' => $new_project_id]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to save imported file']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to create project']);
    }
    exit();
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
?>
