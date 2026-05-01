<?php
// Project Import Handler
require_once __DIR__ . '/../../bootstrap.php';

$auth = new AuthCore();

if (!$auth->isLoggedIn() || !$auth->isDeveloper()) {
    SecurityUtils::safeExit(json_encode(['success' => false, 'error' => 'Unauthorized']), 403, 'warning');
}

$user = $auth->getCurrentUser();

// Code analysis functions
function analyzeCodeQuality($content, $file_type) {
    $issues = [];
    $suggestions = [];
    
    if ($file_type === 'js') {
        // Check for console.log
        if (preg_match('/console\.log/', $content)) {
            $issues[] = 'Console.log statements found (should be removed in production)';
        }
        
        // Check for unused variables (basic)
        if (preg_match_all('/var\s+(\w+)/', $content, $matches)) {
            foreach ($matches[1] as $var) {
                if (!preg_match('/\b' . $var . '\b/', $content, $var_usage) || count($var_usage) < 2) {
                    $suggestions[] = "Variable '$var' might be unused";
                }
            }
        }
        
        // Check for missing semicolons
        if (preg_match('/[^;\s]\s*\n/', $content)) {
            $suggestions[] = 'Some statements might be missing semicolons';
        }
    }
    
    if ($file_type === 'html') {
        // Check for missing alt tags
        if (preg_match_all('/<img[^>]*>(?!.*alt=)/', $content)) {
            $issues[] = 'Some img tags missing alt attribute';
        }
        
        // Check for heading structure
        if (preg_match('/<h1>/', $content) && !preg_match('/<h2>/', $content)) {
            $suggestions[] = 'Consider using h2 tags for better content structure';
        }
    }
    
    if ($file_type === 'css') {
        // Check for duplicate selectors
        if (preg_match_all('/\.([a-zA-Z][\w-]*)/', $content, $matches)) {
            $selectors = array_count_values($matches[1]);
            foreach ($selectors as $selector => $count) {
                if ($count > 3) {
                    $suggestions[] = "Class '.$selector' used $count times - consider consolidation";
                }
            }
        }
    }
    
    return ['issues' => $issues, 'suggestions' => $suggestions];
}

function getCodeStatistics($content, $file_type) {
    $lines = count(explode("\n", $content));
    $chars = strlen($content);
    $words = str_word_count($content);
    
    $complexity = 'Low';
    if ($file_type === 'js') {
        $functions = preg_match_all('/function\s+\w+|\w+\s*=>|\w+\s*\([^)]*\)\s*{/', $content);
        $loops = preg_match_all('/\b(for|while|do)\b/', $content);
        $conditions = preg_match_all('/\bif\b|\bswitch\b/', $content);
        
        if ($functions > 10 || $loops > 5 || $conditions > 10) {
            $complexity = 'High';
        } elseif ($functions > 5 || $loops > 2 || $conditions > 5) {
            $complexity = 'Medium';
        }
    }
    
    return [
        'lines' => $lines,
        'characters' => $chars,
        'words' => $words,
        'complexity' => $complexity
    ];
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

// Handle file upload import with enhanced analysis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'import_files') {
    $project_name = SecurityUtils::validateInput(trim($_POST['project_name'] ?? ''), 'string', ['max_length' => 100]);
    $description = SecurityUtils::validateInput(trim($_POST['project_description'] ?? ''), 'string', ['max_length' => 500]);
    $framework = SecurityUtils::validateInput($_POST['framework'] ?? 'vanilla', 'string');
    
    $validation = ValidationPatterns::validateProject([
        'name' => $project_name,
        'description' => $description,
        'framework' => $framework
    ]);
    
    if (!$validation['valid']) {
        SecurityUtils::safeExit(json_encode(['success' => false, 'error' => 'Validation failed', 'details' => $validation['errors']]), 400, 'warning');
    }
    
    if (!isset($_FILES['project_files'])) {
        SecurityUtils::safeExit(json_encode(['success' => false, 'error' => 'No files uploaded']), 400, 'warning');
    }
    
    // Validate file uploads - expanded to support all programming languages
    $allowed_types = [
        'text/html', 'text/css', 'application/javascript', 'application/json', 'text/plain',
        'text/x-php', 'application/x-httpd-php', 'text/x-python', 'application/x-python-code',
        'text/x-java-source', 'text/x-c', 'text/x-c++', 'text/x-csharp', 'text/x-rust',
        'text/x-go', 'text/x-ruby', 'application/xml', 'text/xml', 'text/yaml', 'application/x-yaml',
        'application/zip', 'application/x-tar', 'application/gzip', 'application/x-rar-compressed',
        'image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/x-icon',
        'application/pdf', 'application/x-shockwave-flash', 'video/mp4', 'video/webm'
    ];
    $max_size = 10 * 1024 * 1024; // 10MB
    
    foreach ($_FILES['project_files']['tmp_name'] as $key => $tmp_name) {
        $file_info = [
            'name' => $_FILES['project_files']['name'][$key],
            'type' => $_FILES['project_files']['type'][$key],
            'tmp_name' => $tmp_name,
            'error' => $_FILES['project_files']['error'][$key],
            'size' => $_FILES['project_files']['size'][$key]
        ];
        
        $validation = ValidationPatterns::validateFileUpload($file_info, $allowed_types, $max_size);
        if (!$validation['valid']) {
            SecurityUtils::safeExit(json_encode(['success' => false, 'error' => 'File validation failed', 'details' => $validation['errors']]), 400, 'warning');
        }
    }
    
    // Create project
    $slug = ValidationPatterns::generateSlug($project_name);
    
    $conn = $auth->getConnection();
    $stmt = $conn->prepare("
        INSERT INTO projects (user_id, name, description, slug, framework, status) 
        VALUES (?, ?, ?, ?, 'development')
    ");
    $stmt->bind_param("issss", $user['id'], $project_name, $description, $slug, $framework);
    
    if ($stmt->execute()) {
        $new_project_id = $stmt->insert_id;
        $imported_files = 0;
        $file_analysis = [];
        $total_stats = ['lines' => 0, 'characters' => 0, 'words' => 0];
        $all_issues = [];
        $all_suggestions = [];
        
        // Process uploaded files
        foreach ($_FILES['project_files']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['project_files']['error'][$key] === UPLOAD_ERR_OK) {
                $file_name = $_FILES['project_files']['name'][$key];
                $file_content = SecurityUtils::safeFileGetContents($tmp_name);
                
                $file_type = pathinfo($file_name, PATHINFO_EXTENSION);
                $file_type_map = [
                    // Web frontend
                    'html' => 'html', 'htm' => 'html', 'css' => 'css', 'scss' => 'css', 'sass' => 'css', 'less' => 'css',
                    'js' => 'js', 'jsx' => 'js', 'ts' => 'js', 'tsx' => 'js', 'mjs' => 'js', 'cjs' => 'js',
                    // Config and data
                    'json' => 'json', 'xml' => 'json', 'yaml' => 'json', 'yml' => 'json', 'toml' => 'json', 'ini' => 'json',
                    // PHP
                    'php' => 'php', 'phtml' => 'php', 'phar' => 'php',
                    // Python
                    'py' => 'py', 'pyw' => 'py', 'pyi' => 'py', 'pyx' => 'py',
                    // Java and JVM
                    'java' => 'java', 'kt' => 'java', 'kts' => 'java', 'scala' => 'java', 'clj' => 'java', 'cljs' => 'java', 'groovy' => 'java',
                    // C/C++/C#
                    'c' => 'cpp', 'cpp' => 'cpp', 'cxx' => 'cpp', 'cc' => 'cpp', 'c++' => 'cpp', 'h' => 'cpp', 'hpp' => 'cpp', 'cs' => 'cpp', 'vala' => 'cpp',
                    // Rust and Go
                    'rs' => 'rust', 'go' => 'go',
                    // Ruby and Perl
                    'rb' => 'ruby', 'rbw' => 'ruby', 'pl' => 'perl', 'pm' => 'perl', 't' => 'perl', 'pod' => 'perl',
                    // Swift and Objective-C
                    'swift' => 'swift', 'm' => 'swift', 'mm' => 'swift',
                    // Shell and scripts
                    'sh' => 'sh', 'bash' => 'sh', 'zsh' => 'sh', 'fish' => 'sh', 'ps1' => 'sh', 'bat' => 'sh', 'cmd' => 'sh',
                    // Database
                    'sql' => 'sql', 'sqlite' => 'sql', 'db' => 'sql',
                    // Mobile
                    'dart' => 'dart',
                    // Documentation
                    'md' => 'md', 'markdown' => 'md', 'txt' => 'md', 'rst' => 'md', 'adoc' => 'md', 'tex' => 'md',
                    // Images
                    'jpg' => 'image', 'jpeg' => 'image', 'png' => 'image', 'gif' => 'image', 'svg' => 'image', 'ico' => 'image', 'webp' => 'image', 'avif' => 'image',
                    'bmp' => 'image', 'tiff' => 'image', 'psd' => 'image', 'ai' => 'image', 'eps' => 'image', 'pdf' => 'image',
                    // Fonts
                    'woff' => 'font', 'woff2' => 'font', 'ttf' => 'font', 'otf' => 'font', 'eot' => 'font',
                    // Audio/Video
                    'mp3' => 'media', 'wav' => 'media', 'ogg' => 'media', 'mp4' => 'media', 'webm' => 'media', 'avi' => 'media', 'mov' => 'media',
                    // Archives
                    'zip' => 'archive', 'tar' => 'archive', 'gz' => 'archive', 'rar' => 'archive', '7z' => 'archive',
                    // Other
                    'wasm' => 'wasm', 'dockerfile' => 'config', 'makefile' => 'config', 'cmake' => 'config', 'rake' => 'config', 'gradle' => 'config', 'pom' => 'config', 'csproj' => 'config',
                    'package' => 'config', 'lock' => 'config', 'gemfile' => 'config', 'requirements' => 'config', 'composer' => 'config',
                    'map' => 'config', 'min' => 'config', 'bundle' => 'config', 'd' => 'config', 'test' => 'config', 'spec' => 'config'
                ];
                $file_type_enum = $file_type_map[$file_type] ?? 'other';
                $file_size = strlen($file_content);
                
                // Analyze code
                $stats = getCodeStatistics($file_content, $file_type);
                $analysis = analyzeCodeQuality($file_content, $file_type);
                
                $file_analysis[$file_name] = [
                    'type' => $file_type,
                    'stats' => $stats,
                    'issues' => $analysis['issues'],
                    'suggestions' => $analysis['suggestions'],
                    'size' => $file_size,
                    'path' => $file_name
                ];
                
                // Update totals
                $total_stats['lines'] += $stats['lines'];
                $total_stats['characters'] += $stats['characters'];
                $total_stats['words'] += $stats['words'];
                
                // Collect all issues and suggestions
                $all_issues = array_merge($all_issues, $analysis['issues']);
                $all_suggestions = array_merge($all_suggestions, $analysis['suggestions']);
                $all_issues = array_merge($all_issues, $analysis['issues']);
                $all_suggestions = array_merge($all_suggestions, $analysis['suggestions']);
                
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
            'imported_files' => $imported_files,
            'analysis' => [
                'files' => $file_analysis,
                'total_stats' => $total_stats,
                'total_issues' => array_unique($all_issues),
                'total_suggestions' => array_unique($all_suggestions)
            ]
        ]);
    } else {
        SecurityUtils::safeExit(json_encode(['success' => false, 'error' => 'Failed to create project']), 500, 'error');
    }
    SecurityUtils::safeExit('', 200, 'info');
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
    
    $content = SecurityUtils::safeFileGetContents($import_url, false, $context);
    
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

SecurityUtils::safeExit(json_encode(['success' => false, 'error' => 'Invalid action']), 400, 'warning');
?>
