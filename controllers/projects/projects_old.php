<?php
require_once '../core/mainlogincore.php';

$auth = new AuthCore();

if (!$auth->isLoggedIn()) {
    header('Location: ../controllers/auth/login.php');
    exit();
}

if (!$auth->isDeveloper()) {
    header('Location: index.php');
    exit();
}

$user = $auth->getCurrentUser();

// Handle project creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'create_project') {
    $name = trim($_POST['project_name'] ?? '');
    $description = trim($_POST['project_description'] ?? '');
    $framework = $_POST['framework'] ?? 'vanilla';
    
    if (!empty($name)) {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $name));
        $slug = rtrim($slug, '-');
        
        $conn = $auth->getConnection();
        $stmt = $conn->prepare("
            INSERT INTO projects (user_id, name, description, slug, framework) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("issss", $user['id'], $name, $description, $slug, $framework);
        
        if ($stmt->execute()) {
            $new_project_id = $stmt->insert_id;
            
            // Create default files
            $default_files = [
                'index.html' => "<!DOCTYPE html>\n<html>\n<head>\n    <title>$name</title>\n    <link rel=\"stylesheet\" href=\"style.css\">\n</head>\n<body>\n    <h1>Welcome to $name</h1>\n    <script src=\"script.js\"></script>\n</body>\n</html>",
                'style.css' => "body {\n    font-family: Arial, sans-serif;\n    margin: 0;\n    padding: 20px;\n    background: #f5f5f5;\n}\n\nh1 {\n    color: #333;\n    text-align: center;\n}",
                'script.js' => "// Welcome to your new project!\n// Add your JavaScript code here"
            ];
            
            foreach ($default_files as $file_name => $content) {
                $file_type = pathinfo($file_name, PATHINFO_EXTENSION);
                $file_type_map = [
                    'html' => 'html',
                    'css' => 'css', 
                    'js' => 'js'
                ];
                $file_type_enum = $file_type_map[$file_type] ?? 'other';
                $file_size = strlen($content);
                
                $stmt = $conn->prepare("INSERT INTO project_files (project_id, file_path, file_name, file_type, content, file_size) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("issssi", $new_project_id, $file_name, $file_name, $file_type_enum, $content, $file_size);
                $stmt->execute();
            }
            
            header('Location: ../core/dashboard.php?page=workspace&project_id=' . $new_project_id);
            exit();
        }
    }
}

// Handle project deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'delete_project') {
    $project_id = $_POST['project_id'] ?? null;
    
    if ($project_id) {
        $conn = $auth->getConnection();
        $stmt = $conn->prepare("DELETE FROM projects WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $project_id, $user['id']);
        $stmt->execute();
        
        header('Location: projects.php');
        exit();
    }
}

// Get user's projects with enhanced statistics
$conn = $auth->getConnection();
$stmt = $conn->prepare("
    SELECT p.*, 
           COUNT(pf.id) as file_count,
           MAX(pf.updated_at) as last_modified,
           SUM(pf.file_size) as total_size,
           SUM(CASE WHEN pf.file_type = 'js' THEN 1 ELSE 0 END) as js_files,
           SUM(CASE WHEN pf.file_type = 'html' THEN 1 ELSE 0 END) as html_files,
           SUM(CASE WHEN pf.file_type = 'css' THEN 1 ELSE 0 END) as css_files,
           SUM(CASE WHEN pf.file_type IN ('html', 'css', 'js') THEN LENGTH(pf.content) - LENGTH(REPLACE(pf.content, '\n', '')) + 1 ELSE 0 END) as total_lines
    FROM projects p
    LEFT JOIN project_files pf ON p.id = pf.project_id
    WHERE p.user_id = ?
    GROUP BY p.id
    ORDER BY p.updated_at DESC
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Analyze code quality for each project
foreach ($projects as &$project) {
    $project['code_issues'] = [];
    $project['suggestions'] = [];
    
    // Get files for analysis
    $files_stmt = $conn->prepare("SELECT file_name, file_type, content FROM project_files WHERE project_id = ?");
    $files_stmt->bind_param("i", $project['id']);
    $files_stmt->execute();
    $files = $files_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($files as $file) {
        $content = $file['content'];
        $file_type = $file['file_type'];
        
        // Basic code analysis
        if ($file_type === 'js') {
            if (preg_match('/console\.log/', $content)) {
                $project['code_issues'][] = "Console.log w " . ($file['name'] ?? 'unknown file');
            }
            if (preg_match('/debugger/', $content)) {
                $project['code_issues'][] = "Debugger w " . ($file['name'] ?? 'unknown file');
            }
        }
        
        if ($file_type === 'html') {
            if (preg_match_all('/<img[^>]*>(?!.*alt=)/', $content)) {
                $project['code_issues'][] = "Brak alt atrybutów w " . ($file['name'] ?? 'unknown file');
            }
        }
        
        if ($file_type === 'css') {
            if (preg_match('/!important/', $content)) {
                $project['suggestions'][] = "Użyto !important w " . ($file['name'] ?? 'unknown file');
            }
        }
    }
    
    $project['code_issues'] = array_unique($project['code_issues']);
    $project['suggestions'] = array_unique($project['suggestions']);
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects - Bloxer</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/reboot.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        .projects-container {
            min-height: 100vh;
            background: var(--bg-primary);
            padding: 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 0 20px;
        }
        
        .header h1 {
            color: var(--text-primary);
            margin: 0;
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
        }
        
        .user-menu {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            display: flex;
            gap: 10px;
        }
        
        .user-button {
            background: rgba(255,255,255,0.1);
            border: 1px solid var(--glass-border);
            border-radius: 50px;
            padding: 8px 16px;
            color: var(--text-primary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .user-button:hover {
            background: rgba(255,255,255,0.15);
        }
        
        .back-button {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1000;
            display: flex;
            gap: 10px;
        }
        
        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .project-card {
            background: var(--bg-secondary);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .project-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .project-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        
        .project-title {
            font-size: 1.2em;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0 0 5px 0;
        }
        
        .project-framework {
            display: inline-block;
            padding: 4px 8px;
            background: var(--accent);
            color: white;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: 500;
        }
        
        .project-status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: 500;
        }
        
        .status-draft {
            background: rgba(156, 163, 175, 0.2);
            color: #9ca3af;
        }
        
        .status-development {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
        }
        
        .status-testing {
            background: rgba(251, 146, 60, 0.2);
            color: #fb923c;
        }
        
        .status-published {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
        }
        
        .project-description {
            color: var(--text-secondary);
            margin-bottom: 15px;
            line-height: 1.5;
        }
        
        .project-stats {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            font-size: 0.9em;
            color: var(--text-secondary);
        }
        
        .stat {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .project-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9em;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-primary {
            background: var(--accent);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--accent-hover);
        }
        
        .btn-secondary {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--glass-border);
        }
        
        .btn-secondary:hover {
            background: var(--bg-hover);
        }
        
        .btn-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        .btn-danger:hover {
            background: rgba(239, 68, 68, 0.2);
        }
        
        .create-project-btn {
            background: var(--bg-secondary);
            border: 2px dashed var(--glass-border);
            border-radius: 12px;
            padding: 40px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 200px;
        }
        
        .create-project-btn:hover {
            border-color: var(--accent);
            background: var(--bg-hover);
        }
        
        .create-project-btn i {
            font-size: 48px;
            color: var(--text-secondary);
            margin-bottom: 15px;
        }
        
        .create-project-btn h3 {
            color: var(--text-primary);
            margin: 0 0 5px 0;
        }
        
        .create-project-btn p {
            color: var(--text-secondary);
            margin: 0;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: var(--bg-secondary);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            color: var(--text-secondary);
            font-size: 24px;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: background 0.2s ease;
        }
        
        .close-btn:hover {
            background: var(--bg-hover);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--glass-border);
            border-radius: 6px;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 14px;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-state i {
            font-size: 64px;
            color: var(--text-secondary);
            margin-bottom: 20px;
        }
        
        .empty-state h2 {
            color: var(--text-primary);
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: var(--text-secondary);
            margin-bottom: 20px;
        }
        
        /* Import Button Styling */
        .import-btn {
            border-color: var(--accent);
            background: rgba(99, 102, 241, 0.1);
        }
        
        .import-btn:hover {
            border-color: var(--accent-hover);
            background: rgba(99, 102, 241, 0.2);
        }
        
        /* File Types and Code Quality */
        .file-types {
            display: flex;
            gap: 8px;
            margin: 12px 0;
            flex-wrap: wrap;
        }
        
        .file-type {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .file-type.html {
            background: rgba(227, 79, 38, 0.1);
            color: #e34f26;
        }
        
        .file-type.css {
            background: rgba(41, 128, 185, 0.1);
            color: #2980b9;
        }
        
        .file-type.js {
            background: rgba(241, 221, 0, 0.1);
            color: #f1dd00;
        }
        
        .code-quality {
            display: flex;
            gap: 8px;
            margin: 12px 0;
            flex-wrap: wrap;
        }
        
        .quality-indicator {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            cursor: help;
        }
        
        .quality-indicator.issues {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }
        
        .quality-indicator.suggestions {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }
        
        .project-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 16px;
        }
        
        .project-actions .btn {
            flex: 1;
            min-width: 80px;
            justify-content: center;
        }
        
        /* Wizard Styles */
        .wizard-modal {
            max-width: 800px;
            width: 90%;
        }
        
        .wizard-content {
            position: relative;
        }
        
        .wizard-progress {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .progress-bar {
            flex: 1;
            height: 4px;
            background: var(--bg-tertiary);
            border-radius: 2px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: var(--accent);
            transition: width 0.3s ease;
            width: 33.33%;
        }
        
        .wizard-step {
            min-height: 300px;
        }
        
        .method-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .method-card {
            background: linear-gradient(135deg, var(--bg-tertiary), var(--bg-secondary));
            border: 2px solid var(--glass-border);
            border-radius: 16px;
            padding: 2.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .method-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at center, rgba(99,102,241,0.05) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .method-card:hover {
            border-color: var(--accent);
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 15px 40px rgba(99,102,241,0.25);
        }
        
        .method-card:hover::before {
            opacity: 1;
        }
        
        .method-card.selected {
            border-color: var(--accent);
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(99, 102, 241, 0.05));
            box-shadow: 0 8px 25px rgba(99,102,241,0.3);
            transform: translateY(-3px);
        }
        
        .method-card.selected::before {
            opacity: 1;
        }
        
        .method-card i {
            font-size: 3rem;
            color: var(--accent);
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }
        
        .method-card:hover i {
            transform: scale(1.1);
            color: var(--accent-hover);
        }
        
        .method-card h4 {
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .method-card p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .templates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .template-card {
            background: var(--bg-tertiary);
            border: 2px solid var(--glass-border);
            border-radius: 8px;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .template-card:hover {
            border-color: var(--accent);
            transform: translateY(-2px);
        }
        
        .template-card.selected {
            border-color: var(--accent);
            background: rgba(99, 102, 241, 0.1);
        }
        
        .template-icon {
            font-size: 1.5rem;
            color: var(--accent);
            margin-bottom: 0.5rem;
        }
        
        .template-info h3 {
            font-size: 1rem;
            margin: 0.5rem 0;
            color: var(--text-primary);
        }
        
        .template-info p {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin: 0.5rem 0;
        }
        
        .template-category {
            display: inline-block;
            padding: 2px 6px;
            background: var(--accent);
            color: white;
            border-radius: 4px;
            font-size: 0.7rem;
        }
        
        .template-files {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 0.5rem;
        }
        
        .upload-area {
            border: 2px dashed var(--glass-border);
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            margin: 1rem 0;
            transition: border-color 0.3s ease;
        }
        
        .upload-area:hover {
            border-color: var(--accent);
        }
        
        .upload-label {
            cursor: pointer;
            display: block;
        }
        
        .upload-label i {
            font-size: 2rem;
            color: var(--accent);
            margin-bottom: 1rem;
        }
        
        .file-list {
            margin-top: 1rem;
        }
        
        .file-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            background: var(--bg-tertiary);
            border-radius: 4px;
            margin-bottom: 0.5rem;
        }
        
        .file-size {
            margin-left: auto;
            color: var(--text-secondary);
            font-size: 0.8rem;
        }
        
        .review-section {
            background: var(--bg-tertiary);
            border-radius: 8px;
            padding: 1.5rem;
            margin: 1rem 0;
        }
        
        .review-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--glass-border);
        }
        
        .review-item:last-child {
            border-bottom: none;
        }
        
        .wizard-navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid var(--glass-border);
        }
        
        .field-error {
            color: var(--error);
            font-size: 0.8rem;
            margin-top: 0.5rem;
            display: block;
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: var(--error);
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
        }
        
        .success-message {
            text-align: center;
            padding: 2rem;
        }
        
        .success-icon {
            font-size: 4rem;
            color: var(--success);
            margin-bottom: 1rem;
        }
        
        .success-message h2 {
            color: var(--text-primary);
            margin-bottom: 1rem;
        }
        
        .success-message p {
            color: var(--text-secondary);
            margin-bottom: 2rem;
        }
        
        .success-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }
    </style>
</head>
<body>
    <div class="projects-container">
        <!-- Breadcrumbs -->
        <div class="back-button">
            <a href="../core/dashboard.php" class="btn btn-secondary">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </div>
        
        <!-- User Menu -->
        <div class="user-menu">
            <a href="../core/dashboard.php" class="user-button">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="../core/dashboard.php" class="user-button">
                <i class="fas fa-code"></i>
                <span>Workspace</span>
            </a>
            <a href="publish.php" class="user-button">
                <i class="fas fa-rocket"></i>
                <span>Publish</span>
            </a>
            <a href="../controllers/auth/logout.php" class="user-button" style="background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.3);">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
        
        <!-- Header -->
        <div class="header">
            <h1>My Projects</h1>
            <div class="header-actions">
                <button class="btn btn-secondary" onclick="showImportModal()">
                    <i class="fas fa-upload"></i>
                    Import Project
                </button>
                <button class="btn btn-secondary" onclick="showExportModal()">
                    <i class="fas fa-download"></i>
                    Export Project
                </button>
            </div>
        </div>
        
        <!-- Projects Grid -->
        <div class="projects-grid">
            <!-- Create New Project -->
            <div class="create-project-btn" onclick="openWizard()">
                <i class="fas fa-plus"></i>
                <h3>Create New Project</h3>
                <p>Start building something amazing</p>
            </div>
            
            <!-- Import Project -->
            <div class="create-project-btn import-btn" onclick="openImportModal()">
                <i class="fas fa-upload"></i>
                <h3>Import Project</h3>
                <p>Import existing files or URL</p>
            </div>
            
            <!-- Existing Projects -->
            <?php if (!empty($projects)): ?>
                <?php foreach ($projects as $project): ?>
                    <div class="project-card">
                        <div class="project-header">
                            <div>
                                <h3 class="project-title"><?php echo htmlspecialchars($project['name']); ?></h3>
                                <span class="project-framework"><?php echo htmlspecialchars($project['framework']); ?></span>
                            </div>
                            <span class="project-status status-<?php echo htmlspecialchars($project['status']); ?>">
                                <?php echo ucfirst(htmlspecialchars($project['status'])); ?>
                            </span>
                        </div>
                        
                        <?php if ($project['description']): ?>
                            <p class="project-description"><?php echo htmlspecialchars($project['description']); ?></p>
                        <?php endif; ?>
                        
                        <div class="project-stats">
                            <div class="stat">
                                <i class="fas fa-file"></i>
                                <span><?php echo $project['file_count']; ?> files</span>
                            </div>
                            <div class="stat">
                                <i class="fas fa-code"></i>
                                <span><?php echo number_format($project['total_lines'] ?? 0); ?> lines</span>
                            </div>
                            <div class="stat">
                                <i class="fas fa-clock"></i>
                                <span><?php echo $project['last_modified'] ? date('M j, Y', strtotime($project['last_modified'])) : 'Never'; ?></span>
                            </div>
                        </div>
                        
                        <!-- File Type Distribution -->
                        <div class="file-types">
                            <?php if ($project['html_files'] > 0): ?>
                                <span class="file-type html" title="HTML files">
                                    <i class="fab fa-html5"></i> <?php echo $project['html_files']; ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($project['css_files'] > 0): ?>
                                <span class="file-type css" title="CSS files">
                                    <i class="fab fa-css3-alt"></i> <?php echo $project['css_files']; ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($project['js_files'] > 0): ?>
                                <span class="file-type js" title="JavaScript files">
                                    <i class="fab fa-js"></i> <?php echo $project['js_files']; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Code Quality Indicators -->
                        <?php if (!empty($project['code_issues']) || !empty($project['suggestions'])): ?>
                            <div class="code-quality">
                                <?php if (!empty($project['code_issues'])): ?>
                                    <div class="quality-indicator issues" title="<?php echo implode(', ', $project['code_issues']); ?>">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <span><?php echo count($project['code_issues']); ?> issues</span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($project['suggestions'])): ?>
                                    <div class="quality-indicator suggestions" title="<?php echo implode(', ', $project['suggestions']); ?>">
                                        <i class="fas fa-lightbulb"></i>
                                        <span><?php echo count($project['suggestions']); ?> suggestions</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="project-actions">
                            <a href="../core/dashboard.php?page=workspace&project_id=<?php echo $project['id']; ?>" class="btn btn-primary">
                                <i class="fas fa-code"></i>
                                Edit
                            </a>
                            <button class="btn btn-secondary" onclick="analyzeProject(<?php echo $project['id']; ?>)">
                                <i class="fas fa-search"></i>
                                Analyze
                            </button>
                            <button class="btn btn-secondary" onclick="exportProject(<?php echo $project['id']; ?>)">
                                <i class="fas fa-download"></i>
                                Export
                            </button>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this project?')">
                                <input type="hidden" name="action" value="delete_project">
                                <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Empty State -->
        <?php if (empty($projects)): ?>
            <div class="empty-state">
                <i class="fas fa-folder-open"></i>
                <h2>No projects yet</h2>
                <p>Create your first project to get started</p>
                <button class="btn btn-primary" onclick="openModal()">
                    <i class="fas fa-plus"></i>
                    Create Project
                </button>
            </div>
        <?php endif; ?>
        
        <!-- Create Project Modal -->
        <div id="createModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Create New Project</h2>
                    <button class="close-btn" onclick="closeModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="create_project">
                    
                    <div class="form-group">
                        <label for="project_name">Project Name *</label>
                        <input type="text" id="project_name" name="project_name" required 
                               placeholder="My Awesome Project">
                    </div>
                    
                    <div class="form-group">
                        <label for="project_description">Description</label>
                        <textarea id="project_description" name="project_description" 
                                  placeholder="Describe your project..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="framework">Framework</label>
                        <select id="framework" name="framework">
                            <option value="vanilla">Vanilla JS</option>
                            <option value="react">React</option>
                            <option value="vue">Vue.js</option>
                            <option value="angular">Angular</option>
                            <option value="svelte">Svelte</option>
                        </select>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            Create Project
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Project Wizard -->
        <div id="projectWizard" class="modal wizard-modal" style="display: none;">
            <div class="wizard-content">
                <div class="wizard-header">
                    <h2>Create New Project</h2>
                    <button class="close-btn" onclick="closeWizard()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="wizard-progress">
                    <div class="progress-bar">
                        <div id="progressFill" class="progress-fill"></div>
                    </div>
                    <span id="progressText">Step 1 of 3</span>
                </div>
                
                <div id="wizardError" class="alert alert-error" style="display: none;"></div>
                
                <!-- Step 1: Choose Method -->
                <div id="step1" class="wizard-step">
                    <h3>How would you like to create your project?</h3>
                    <div class="method-options">
                        <div class="method-card" data-method="template" onclick="selectMethod('template')">
                            <i class="fas fa-magic"></i>
                            <h4>Start with Template</h4>
                            <p>Choose from pre-built templates to get started quickly</p>
                        </div>
                        
                        <div class="method-card" data-method="files" onclick="selectMethod('files')">
                            <i class="fas fa-file-upload"></i>
                            <h4>Import Files</h4>
                            <p>Upload your existing HTML, CSS, and JavaScript files</p>
                        </div>
                        
                        <div class="method-card" data-method="url" onclick="selectMethod('url')">
                            <i class="fas fa-link"></i>
                            <h4>Import from URL</h4>
                            <p>Import a project from a web URL</p>
                        </div>
                    </div>
                </div>
                
                <!-- Step 2: Configure Project -->
                <div id="step2" class="wizard-step" style="display: none;">
                    <h3>Project Details</h3>
                    <div class="form-group">
                        <label for="projectName">Project Name *</label>
                        <input type="text" id="projectName" placeholder="My Awesome Project">
                        <div id="nameError" class="field-error" style="display: none;"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="projectDescription">Description</label>
                        <textarea id="projectDescription" placeholder="Describe your project..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="projectFramework">Framework</label>
                        <select id="projectFramework">
                            <option value="vanilla">Vanilla JS</option>
                            <option value="react">React</option>
                            <option value="vue">Vue.js</option>
                            <option value="angular">Angular</option>
                            <option value="svelte">Svelte</option>
                        </select>
                    </div>
                    
                    <!-- Template Selection -->
                    <div id="templateSelection" class="template-section" style="display: none;">
                        <h4>Choose a Template</h4>
                        <div id="templatesGrid" class="templates-grid"></div>
                    </div>
                    
                    <!-- File Upload -->
                    <div id="fileUpload" class="upload-section" style="display: none;">
                        <h4>Upload Files</h4>
                        <div class="upload-area">
                            <input type="file" id="importFiles" multiple accept=".html,.css,.js,.json,.md" style="display: none;">
                            <label for="importFiles" class="upload-label">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Click to upload or drag and drop</p>
                                <small>Supports: HTML, CSS, JavaScript, JSON, Markdown</small>
                            </label>
                        </div>
                        <div id="fileList" class="file-list"></div>
                    </div>
                    
                    <!-- URL Import -->
                    <div id="urlImport" class="url-section" style="display: none;">
                        <h4>Import from URL</h4>
                        <div class="form-group">
                            <label for="importUrl">Project URL</label>
                            <input type="url" id="importUrl" placeholder="https://example.com/project.html">
                            <div id="urlError" class="field-error" style="display: none;"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Step 3: Review -->
                <div id="step3" class="wizard-step" style="display: none;">
                    <h3>Review and Create</h3>
                    <div class="review-section">
                        <div class="review-item">
                            <strong>Project Name:</strong>
                            <span id="reviewName"></span>
                        </div>
                        <div class="review-item">
                            <strong>Framework:</strong>
                            <span id="reviewFramework"></span>
                        </div>
                        <div class="review-item">
                            <strong>Method:</strong>
                            <span id="reviewMethod"></span>
                        </div>
                        <div class="review-item">
                            <strong>Files:</strong>
                            <span id="reviewFiles"></span>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeWizard()">
                            Cancel
                        </button>
                        <button type="button" class="btn btn-primary" id="finishBtn" onclick="finishWizard()">
                            <i class="fas fa-check"></i>
                            Create Project
                        </button>
                    </div>
                </div>
                
                <!-- Navigation -->
                <div class="wizard-navigation">
                    <button type="button" class="btn btn-secondary" id="prevBtn" onclick="prevStep()">
                        <i class="fas fa-arrow-left"></i>
                        Previous
                    </button>
                    <button type="button" class="btn btn-primary" id="nextBtn" onclick="nextStep()" disabled>
                        Next
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        let wizard = null;
        
        function openModal() {
            document.getElementById('createModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('createModal').classList.remove('active');
        }
        
        function openWizard() {
            document.getElementById('projectWizard').style.display = 'flex';
            if (!wizard) {
                wizard = new ProjectWizard();
            }
        }
        
        function closeWizard() {
            if (confirm('Are you sure you want to cancel? Any unsaved changes will be lost.')) {
                document.getElementById('projectWizard').style.display = 'none';
            }
        }
        
        function selectMethod(method) {
            // Update UI
            document.querySelectorAll('.method-card').forEach(card => {
                card.classList.remove('selected');
            });
            document.querySelector(`[data-method="${method}"]`).classList.add('selected');
            
            // Store method
            if (wizard) wizard.projectData.method = method;
            
            // Show relevant section
            document.getElementById('templateSelection').style.display = method === 'template' ? 'block' : 'none';
            document.getElementById('fileUpload').style.display = method === 'files' ? 'block' : 'none';
            document.getElementById('urlImport').style.display = method === 'url' ? 'block' : 'none';
            
            // Enable next button
            document.getElementById('nextBtn').disabled = false;
        }
        
        // Close modal when clicking outside
        document.getElementById('createModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
                closeWizard();
            }
        });
    </script>
    
    <!-- Import Modal -->
    <div id="importModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Import Project</h2>
                <button class="close-btn" onclick="closeImportModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 20px; color: var(--text-secondary);">
                    Upload a JSON file exported from Bloxer to import a project with all its files.
                </p>
                <form id="importForm" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="importFile">Select JSON File:</label>
                        <input type="file" id="importFile" name="import_file" accept=".json" required>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeImportModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Import Project</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Export Modal -->
    <div id="exportModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Export Project</h2>
                <button class="close-btn" onclick="closeExportModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 20px; color: var(--text-secondary);">
                    Select a project to export as a JSON file containing all project data and files.
                </p>
                <div class="form-group">
                    <label for="exportProject">Select Project:</label>
                    <select id="exportProject" required>
                        <option value="">Choose a project...</option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?php echo $project['id']; ?>">
                                <?php echo htmlspecialchars($project['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeExportModal()">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="exportProject()">Export as JSON</button>
                    <button type="button" class="btn btn-secondary" onclick="exportTemplate()">Export as Template</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function showImportModal() {
            document.getElementById('importModal').style.display = 'flex';
        }
        
        function closeImportModal() {
            document.getElementById('importModal').style.display = 'none';
            document.getElementById('importForm').reset();
        }
        
        function showExportModal() {
            document.getElementById('exportModal').style.display = 'flex';
        }
        
        function closeExportModal() {
            document.getElementById('exportModal').style.display = 'none';
        }
        
        async function exportProject() {
            const projectId = document.getElementById('exportProject').value;
            
            if (!projectId) {
                alert('Please select a project to export');
                return;
            }
            
            try {
                const response = await fetch('project-import-export.php', {
                    method: 'POST',
                    body: `action=export_project&project_id=${projectId}`,
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    }
                });
                
                if (response.ok) {
                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = response.headers.get('Content-Disposition').split('filename=')[1].replace(/"/g, '');
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                    closeExportModal();
                } else {
                    alert('Failed to export project');
                }
            } catch (error) {
                console.error('Export error:', error);
                alert('An error occurred while exporting the project');
            }
        }
        
        async function exportTemplate() {
            const projectId = document.getElementById('exportProject').value;
            
            if (!projectId) {
                alert('Please select a project to export as template');
                return;
            }
            
            try {
                const response = await fetch('project-import-export.php', {
                    method: 'POST',
                    body: `action=export_template&project_id=${projectId}`,
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    }
                });
                
                if (response.ok) {
                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = response.headers.get('Content-Disposition').split('filename=')[1].replace(/"/g, '');
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                    closeExportModal();
                } else {
                    alert('Failed to export template');
                }
            } catch (error) {
                console.error('Export error:', error);
                alert('An error occurred while exporting the template');
            }
        }
        
        // Handle import form submission
        document.getElementById('importForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'import_project');
            
            try {
                const response = await fetch('project-import-export.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Project imported successfully!');
                    closeImportModal();
                    window.location.href = `../core/dashboard.php?page=workspace&project_id=${result.project_id}`;
                } else {
                    alert(result.error || 'Failed to import project');
                }
            } catch (error) {
                console.error('Import error:', error);
                alert('An error occurred while importing the project');
            }
        });
    </script>
    
    <script src="../assets/js/project-wizard.js"></script>
</body>
</html>
