<?php
require_once 'mainlogincore.php';

$auth = new AuthCore();

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

if (!$auth->isDeveloper()) {
    header('Location: marketplace.php');
    exit();
}

$user = $auth->getCurrentUser();
$page = $_GET['page'] ?? 'workspace';
$project_id = $_GET['project_id'] ?? null;

// Handle project actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($_POST['action']) {
        case 'create_project':
            $name = trim($_POST['project_name'] ?? '');
            $description = trim($_POST['project_description'] ?? '');
            $framework = $_POST['framework'] ?? 'vanilla';
            
        if (!empty($name)) {
            // Validate project name
            if (strlen($name) < 2 || strlen($name) > 100) {
                $errors[] = "Project name must be between 2 and 100 characters";
            }
            
            // Validate description
            if (!empty($description) && strlen($description) > 500) {
                $errors[] = "Description must not exceed 500 characters";
            }
            
            // Validate framework
            $allowed_frameworks = ['vanilla', 'react', 'vue', 'angular'];
            if (!in_array($framework, $allowed_frameworks)) {
                $framework = 'vanilla'; // Default to vanilla if invalid
            }
            
            if (empty($errors)) {
                $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $name));
                $slug = rtrim($slug, '-');
                
                if (empty($slug)) {
                    $slug = 'project-' . time();
                }
                
                $conn = $auth->getConnection();
                $stmt = $conn->prepare("
                    INSERT INTO projects (user_id, name, description, slug, framework) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                if (!$stmt) {
                    error_log("Prepare failed: " . $conn->error);
                    $errors[] = "Database error occurred";
                } else {
                    $stmt->bind_param("issss", $user['id'], $name, $description, $slug, $framework);
                    if (!$stmt->execute()) {
                        error_log("Execute failed: " . $stmt->error);
                        $errors[] = "Failed to create project";
                    }
                }
                    if (empty($errors)) {
                        $new_project_id = $stmt->insert_id;
                        
                        // Create default files
                        $default_files = [
                            'index.html' => "<!DOCTYPE html>\n<html>\n<head>\n    <title>" . htmlspecialchars($name) . "</title>\n</head>\n<body>\n    <h1>Welcome to " . htmlspecialchars($name) . "</h1>\n</body>\n</html>",
                            'style.css' => "body {\n    font-family: Arial, sans-serif;\n    margin: 0;\n    padding: 20px;\n}",
                            'script.js' => "// Welcome to your new project!\nconsole.log('Hello, Bloxer!');"
                        ];
                        
                        $conn = $auth->getConnection();
                        foreach ($default_files as $file_name => $content) {
                            $file_type = pathinfo($file_name, PATHINFO_EXTENSION);
                            $stmt = $conn->prepare("
                                INSERT INTO project_files (project_id, file_path, file_name, file_type, content) 
                                VALUES (?, ?, ?, ?, ?)
                            ");
                            if ($stmt) {
                                $filePath = "/$file_name";
                                $stmt->bind_param("issss", $new_project_id, $filePath, $file_name, $file_type, $content);
                                $stmt->execute();
                            }
                        }
                        
                        header('Location: dashboard.php?page=workspace&project_id=' . $new_project_id);
                        exit();
                    }
                }
            }
            
            if (!empty($errors)) {
                // Store errors in session for display
                $_SESSION['form_errors'] = $errors;
                $_SESSION['form_data'] = $_POST;
                header('Location: dashboard.php?page=projects');
                exit();
            }
            break;
            
        case 'save_file':
            $project_id = $_POST['project_id'] ?? null;
            $file_path = $_POST['file_path'] ?? null;
            $content = $_POST['content'] ?? '';
            
            $conn = $auth->getConnection();
            if ($project_id && $file_path) {
                // Verify project ownership
                $stmt = $conn->prepare("SELECT id FROM projects WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ii", $project_id, $user['id']);
                $stmt->execute();
                
                if ($stmt->get_result()->num_rows > 0) {
                    $file_type = pathinfo($file_path, PATHINFO_EXTENSION);
                    $file_name = basename($file_path);
                    
                    $stmt = $conn->prepare("
                        INSERT INTO project_files (project_id, file_path, file_name, file_type, content) 
                        VALUES (?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE content = ?, updated_at = CURRENT_TIMESTAMP
                    ");
                    $stmt->bind_param("isssss", $project_id, $file_path, $file_name, $file_type, $content, $content);
                    $stmt->execute();
                    
                    echo json_encode(['success' => true]);
                    exit();
                }
            }
            echo json_encode(['success' => false]);
            exit();
            break;
            
        case 'delete_project':
            $project_id = $_POST['project_id'] ?? null;
            
            $conn = $auth->getConnection();
            if ($project_id) {
                $stmt = $conn->prepare("DELETE FROM projects WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ii", $project_id, $user['id']);
                $stmt->execute();
                
                header('Location: dashboard.php?page=projects');
                exit();
            }
            break;
            
        case 'publish_app':
            $project_id = $_POST['project_id'] ?? null;
            $title = trim($_POST['title'] ?? '');
            $short_description = trim($_POST['short_description'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $category = $_POST['category'] ?? '';
            $tags = trim($_POST['tags'] ?? '');
            $thumbnail_url = trim($_POST['thumbnail_url'] ?? '');
            $demo_url = trim($_POST['demo_url'] ?? '');
            $is_free = $_POST['is_free'] ?? '1';
            $price = floatval($_POST['price'] ?? 0);
            
            if ($project_id && $title && $short_description && $description && $category) {
                $conn = $auth->getConnection();
                
                // Verify project ownership
                $stmt = $conn->prepare("SELECT id FROM projects WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ii", $project_id, $user['id']);
                $stmt->execute();
                
                if ($stmt->get_result()->num_rows > 0) {
                    // Generate slug
                    $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $title));
                    $slug = rtrim($slug, '-');
                    
                    // Check if app already exists
                    $stmt = $conn->prepare("SELECT id FROM apps WHERE project_id = ?");
                    $stmt->bind_param("i", $project_id);
                    $stmt->execute();
                    
                    if ($stmt->get_result()->num_rows > 0) {
                        // Update existing app
                        $stmt = $conn->prepare("
                            UPDATE apps SET 
                                title = ?, slug = ?, short_description = ?, description = ?, 
                                category = ?, tags = ?, thumbnail_url = ?, demo_url = ?,
                                is_free = ?, price = ?, status = 'published', published_at = NOW()
                            WHERE project_id = ?
                        ");
                        $stmt->bind_param("ssssssssdsi", $title, $slug, $short_description, $description, 
                                        $category, $tags, $thumbnail_url, $demo_url, $is_free, $price, $project_id);
                    } else {
                        // Create new app
                        $stmt = $conn->prepare("
                            INSERT INTO apps (
                                project_id, title, slug, short_description, description, 
                                category, tags, thumbnail_url, demo_url, is_free, price, 
                                status, published_at
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'published', NOW())
                        ");
                        $stmt->bind_param("isssssssdsd", $project_id, $title, $slug, $short_description, 
                                        $description, $category, $tags, $thumbnail_url, $demo_url, $is_free, $price);
                    }
                    
                    $stmt->execute();
                    
                    header('Location: dashboard.php?page=publish');
                    exit();
                }
            }
            break;
        
        case 'respond_offer':
            $offer_id = intval($_POST['offer_id'] ?? 0);
            $decision = $_POST['decision'] ?? '';
            $response_message = trim($_POST['response_message'] ?? '');

            if ($offer_id && in_array($decision, ['accept', 'decline'])) {
                $conn = $auth->getConnection();
                $stmt = $conn->prepare("SELECT o.*, a.project_id, p.name AS project_name FROM offers o JOIN apps a ON o.app_id = a.id JOIN projects p ON a.project_id = p.id WHERE o.id = ? AND o.developer_id = ?");
                $stmt->bind_param("ii", $offer_id, $user['id']);
                $stmt->execute();
                $offer = $stmt->get_result()->fetch_assoc();

                if ($offer) {
                    if ($decision === 'accept' && $offer['status'] === 'pending') {
                        $stmt = $conn->prepare("UPDATE offers SET status = 'accepted', transfer_notes = ?, transferred_at = NOW(), updated_at = NOW() WHERE id = ?");
                        $stmt->bind_param("si", $response_message, $offer_id);
                        $stmt->execute();
                    }

                    if ($decision === 'decline') {
                        $stmt = $conn->prepare("UPDATE offers SET status = 'declined', updated_at = NOW() WHERE id = ?");
                        $stmt->bind_param("i", $offer_id);
                        $stmt->execute();
                    }

                    if (!empty($response_message)) {
                        $stmt = $conn->prepare("INSERT INTO offer_messages (offer_id, sender_id, message) VALUES (?, ?, ?)");
                        if ($stmt) {
                            $stmt->bind_param("iis", $offer_id, $user['id'], $response_message);
                            $stmt->execute();
                        }
                    }
                }
            }
            header('Location: dashboard.php?page=offers');
            exit();
            break;
    }
}

// Get user's projects
$conn = $auth->getConnection();
$stmt = $conn->prepare("
    SELECT p.*, COUNT(pf.id) as file_count 
    FROM projects p 
    LEFT JOIN project_files pf ON p.id = pf.project_id 
    WHERE p.user_id = ? 
    GROUP BY p.id 
    ORDER BY p.updated_at DESC
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get current project
$current_project = null;
$conn = $auth->getConnection();
if ($project_id) {
    $stmt = $conn->prepare("
        SELECT p.*, COUNT(pf.id) as file_count 
        FROM projects p 
        LEFT JOIN project_files pf ON p.id = pf.project_id 
        WHERE p.id = ? AND p.user_id = ?
        GROUP BY p.id
    ");
    $stmt->bind_param("ii", $project_id, $user['id']);
    $stmt->execute();
    $current_project = $stmt->get_result()->fetch_assoc();
    
    if ($current_project) {
        // Get project files
        $conn = $auth->getConnection();
        $stmt = $conn->prepare("
            SELECT * FROM project_files WHERE project_id = ? ORDER BY file_path
        ");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $project_files = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

$offers = [];
if ($page === 'offers') {
    $stmt = $conn->prepare("SELECT o.*, a.title AS app_title, u.username AS buyer_name, u.email AS buyer_email, p.name AS source_project_name FROM offers o JOIN apps a ON o.app_id = a.id JOIN users u ON o.buyer_id = u.id LEFT JOIN projects p ON a.project_id = p.id WHERE o.developer_id = ? ORDER BY o.created_at DESC");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $offers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($offers as &$offer_item) {
        $stmt = $conn->prepare("SELECT om.*, u.username FROM offer_messages om JOIN users u ON om.sender_id = u.id WHERE om.offer_id = ? ORDER BY om.created_at ASC");
        $stmt->bind_param("i", $offer_item['id']);
        $stmt->execute();
        $offer_item['messages'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    unset($offer_item);
}

?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Creator Dashboard - Bloxer</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .dashboard-container {
            display: flex;
            height: 100vh;
            background: var(--deep-black);
        }
        
        .sidebar {
            width: 250px;
            background: rgba(255,255,255,0.04);
            border-right: 1px solid var(--glass-border);
            padding: 20px;
            overflow-y: auto;
        }
        
        .main-content {
            flex: 1;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .top-bar {
            background: rgba(255,255,255,0.04);
            border-bottom: 1px solid var(--glass-border);
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .workspace-area {
            flex: 1;
            display: flex;
            overflow: hidden;
        }
        
        .file-explorer {
            width: 250px;
            background: rgba(255,255,255,0.02);
            border-right: 1px solid var(--glass-border);
            padding: 15px;
            overflow-y: auto;
        }
        
        .editor-area {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .editor-tabs {
            background: rgba(255,255,255,0.04);
            border-bottom: 1px solid var(--glass-border);
            display: flex;
            overflow-x: auto;
        }
        
        .editor-tab {
            padding: 10px 20px;
            background: rgba(255,255,255,0.06);
            border-right: 1px solid var(--glass-border);
            cursor: pointer;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .editor-tab.active {
            background: rgba(255,255,255,0.12);
            color: var(--text-primary);
        }
        
        .editor-tab:hover {
            background: rgba(255,255,255,0.08);
        }
        
        .editor-content {
            flex: 1;
            position: relative;
        }
        
        .preview-panel {
            width: 400px;
            background: rgba(255,255,255,0.02);
            border-left: 1px solid var(--glass-border);
            display: flex;
            flex-direction: column;
        }
        
        .preview-header {
            background: rgba(255,255,255,0.04);
            border-bottom: 1px solid var(--glass-border);
            padding: 10px 15px;
            font-weight: 600;
        }
        
        .preview-content {
            flex: 1;
            padding: 15px;
            overflow: auto;
        }
        
        .preview-frame {
            width: 100%;
            height: 100%;
            border: none;
            background: white;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 15px;
            margin: 5px 0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            color: var(--text-secondary);
        }
        
        .nav-item:hover {
            background: rgba(255,255,255,0.08);
            color: var(--text-primary);
        }
        
        .nav-item.active {
            background: var(--btn-gradient-start);
            color: var(--text-primary);
        }
        
        .project-card {
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .project-card:hover {
            background: rgba(255,255,255,0.06);
            transform: translateY(-2px);
        }
        
        .file-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            margin: 2px 0;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            color: var(--text-secondary);
        }
        
        .file-item:hover {
            background: rgba(255,255,255,0.08);
            color: var(--text-primary);
        }
        
        .file-item.active {
            background: rgba(99,102,241,0.2);
            color: var(--text-primary);
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            backdrop-filter: blur(20px);
        }
        
                
        #monaco-editor {
            height: 100%;
            width: 100%;
        }
        
        .btn-small {
            padding: 8px 16px;
            font-size: 0.9rem;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .stat-card {
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--btn-gradient-start);
        }
        
        .stat-label {
            color: var(--text-secondary);
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">
                <h1>Bloxer</h1>
                <p>Creator Dashboard</p>
            </div>
            
            <nav>
                <div class="nav-item <?php echo $page === 'workspace' ? 'active' : ''; ?>" onclick="window.location.href='<?php echo !empty($projects) ? 'editor.php?project_id=' . $projects[0]['id'] : 'editor.php'; ?>'">
                    <i class="fas fa-code"></i>
                    <span>Workspace</span>
                </div>
                <div class="nav-item <?php echo $page === 'projects' ? 'active' : ''; ?>" onclick="window.location.href='projects.php'">
                    <i class="fas fa-folder"></i>
                    <span>Projects</span>
                </div>
                <div class="nav-item <?php echo $page === 'offers' ? 'active' : ''; ?>" onclick="window.location.href='dashboard.php?page=offers'">
                    <i class="fas fa-handshake"></i>
                    <span>Offers</span>
                </div>
                <div class="nav-item <?php echo $page === 'publish' ? 'active' : ''; ?>" onclick="window.location.href='publish.php'">
                    <i class="fas fa-rocket"></i>
                    <span>Publish Center</span>
                </div>
            </nav>
            
            <div style="margin-top: auto; padding-top: 20px; border-top: 1px solid var(--glass-border);">
                <?php if (isset($_SESSION['form_errors'])): ?>
                    <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 8px; padding: 12px; margin-bottom: 15px;">
                        <?php foreach ($_SESSION['form_errors'] as $error): ?>
                            <div style="color: #ef4444; font-size: 0.9em; margin-bottom: 4px;">
                                <i class="fas fa-exclamation-triangle" style="margin-right: 6px;"></i>
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php unset($_SESSION['form_errors']); unset($_SESSION['form_data']); ?>
                <?php endif; ?>
                
                <div class="nav-item" onclick="window.location.href='logout.php'">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div>
                    <h3 style="margin: 0; color: var(--text-primary);">
                         alt="Avatar" style="width: 32px; height: 32px; border-radius: 50%;">
                    <span><?php echo htmlspecialchars($user['username']); ?></span>
                </div>
            </div>
            
            <!-- Page Content -->
            <div class="workspace-area">
                <?php if ($page === 'workspace' && $current_project): ?>
                    <!-- File Explorer -->
                    <div class="file-explorer">
                        <h4 style="margin-bottom: 15px;">Files</h4>
                        <?php foreach ($project_files as $file): ?>
                            <div class="file-item" onclick="openFile('<?php echo htmlspecialchars($file['file_path']); ?>', '<?php echo htmlspecialchars($file['file_type']); ?>')">
                                <i class="fas fa-file-code"></i>
                                <span><?php echo htmlspecialchars($file['file_name']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Editor Area -->
                    <div class="editor-area">
                        <div class="editor-tabs" id="editor-tabs">
                            <!-- Tabs will be added dynamically -->
                        </div>
                        <div class="editor-content">
                            <div id="monaco-editor"></div>
                        </div>
                    </div>
                    
                    <!-- Preview Panel -->
                    <div class="preview-panel">
                        <div class="preview-header">
                            <i class="fas fa-eye"></i> Live Preview
                        </div>
                        <div class="preview-content">
                            <iframe class="preview-frame" id="preview-frame"></iframe>
                        </div>
                    </div>
                    
                <?php elseif ($page === 'projects'): ?>
                    <div style="padding: 30px; flex: 1; overflow-y: auto;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                            <h2 style="color: var(--text-primary);">My Projects</h2>
                            <button class="btn btn-small" onclick="showCreateProjectModal()">
                                <i class="fas fa-plus"></i> New Project
                            </button>
                        </div>
                        
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-value"><?php echo count($projects); ?></div>
                                <div class="stat-label">Total Projects</div>
                            </div>
                            <div class="stat-card">
                                <?php $total_files = array_sum(array_column($projects, 'file_count')); ?>
                            <div class="stat-value"><?php echo $total_files; ?></div>
                                <div class="stat-label">Total Files</div>
                            </div>
                        </div>
                        
                        <?php foreach ($projects as $project): ?>
                            <div class="project-card" onclick="navigate('workspace', <?php echo $project['id']; ?>)">
                                <div style="display: flex; justify-content: space-between; align-items: start;">
                                    <div>
                                        <h4 style="margin-bottom: 5px;"><?php echo htmlspecialchars($project['name']); ?></h4>
                                        <p style="color: var(--text-secondary); margin-bottom: 10px;"><?php echo htmlspecialchars($project['description'] ?? 'No description'); ?></p>
                                        <small style="color: var(--text-muted);">
                                            <i class="fas fa-folder"></i> <?php echo $project['file_count']; ?> files • 
                                            <i class="fas fa-code"></i> <?php echo htmlspecialchars($project['framework']); ?> • 
                                            Updated <?php echo date('M j, Y', strtotime($project['updated_at'])); ?>
                                        </small>
                                    </div>
                                    <button class="btn btn-small btn-danger" onclick="deleteProject(event, <?php echo $project['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                                    
                <?php elseif ($page === 'offers'): ?>
                    <div style="padding: 30px; flex: 1; overflow-y: auto;">
                        <h2 style="color: var(--text-primary); margin-bottom: 30px;">Incoming Offers</h2>
                        <p style="color: var(--text-secondary); margin-bottom: 25px; max-width: 800px;">
                            Review deal requests from buyers, respond with a transfer note, and confirm when the project handoff is ready.
                        </p>

                        <?php if (empty($offers)): ?>
                            <div class="empty-state" style="text-align: center; padding: 60px 20px; color: var(--text-secondary);">
                                <i class="fas fa-comments" style="font-size: 4rem; margin-bottom: 20px; opacity: 0.5;"></i>
                                <h3>No incoming offers yet</h3>
                                <p>Once buyers send deal proposals, they will appear here for review.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($offers as $offer): ?>
                                <div class="project-card" style="margin-bottom: 20px; padding: 25px;">
                                    <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
                                        <div style="flex: 1; min-width: 250px;">
                                            <h4 style="margin-bottom: 10px; color: var(--text-primary);">
                                                <?php echo htmlspecialchars($offer['app_title']); ?>
                                            </h4>
                                            <p style="color: var(--text-secondary); margin-bottom: 8px;"><strong>Buyer:</strong> <?php echo htmlspecialchars($offer['buyer_name']); ?> (<?php echo htmlspecialchars($offer['buyer_email']); ?>)</p>
                                            <p style="color: var(--text-secondary); margin-bottom: 8px;"><strong>Offered:</strong> $<?php echo number_format($offer['amount'], 2); ?></p>
                                            <p style="color: var(--text-secondary); margin-bottom: 8px;"><strong>Phone:</strong> <?php echo htmlspecialchars($offer['phone_number'] ?: 'Not provided'); ?></p>
                                            <p style="color: var(--text-secondary); margin-bottom: 8px;"><strong>Status:</strong> <?php echo ucfirst($offer['status']); ?></p>
                                            <?php if (!empty($offer['transfer_notes'])): ?>
                                                <p style="color: var(--text-primary); margin-bottom: 8px;"><strong>Transfer note:</strong> <?php echo htmlspecialchars($offer['transfer_notes']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div style="display: flex; flex-direction: column; gap: 10px; min-width: 240px; align-items: flex-end;">
                                            <?php if ($offer['status'] === 'pending'): ?>
                                                <form method="POST" style="width: 100%;">
                                                    <input type="hidden" name="action" value="respond_offer">
                                                    <input type="hidden" name="offer_id" value="<?php echo $offer['id']; ?>">
                                                    <textarea name="response_message" rows="3" placeholder="Add a reply or handoff note" style="width: 100%; margin-bottom: 10px; padding: 12px; border-radius: 12px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: var(--text-primary);"></textarea>
                                                    <div style="display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end;">
                                                        <button class="btn btn-small" type="submit" name="decision" value="decline" style="background: rgba(239,68,68,0.2); color: #f87171; border: 1px solid rgba(239,68,68,0.3);">Decline</button>
                                                        <button class="btn btn-small" type="submit" name="decision" value="accept">Accept & Transfer</button>
                                                    </div>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <?php if (!empty($offer['messages'])): ?>
                                        <div style="margin-top: 20px; padding: 15px; background: rgba(255,255,255,0.04); border-radius: 16px;">
                                            <h4 style="margin-top: 0; color: var(--text-primary);">Conversation</h4>
                                            <?php foreach ($offer['messages'] as $message): ?>
                                                <div style="margin-bottom: 15px;">
                                                    <div style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 6px;"><strong><?php echo htmlspecialchars($message['username']); ?>:</strong> <?php echo date('M j, Y H:i', strtotime($message['created_at'])); ?></div>
                                                    <div style="padding: 12px; border-radius: 14px; background: rgba(255,255,255,0.06); color: var(--text-primary);">
                                                        <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php elseif ($page === 'publish'): ?>
                    <div style="padding: 30px; flex: 1; overflow-y: auto;">
                        <h2 style="color: var(--text-primary); margin-bottom: 30px;">Publish Center</h2>
                        
                        <?php
                        // Get projects that can be published
                        $stmt = $conn->prepare("
                            SELECT p.*, 
                                   CASE WHEN a.id IS NOT NULL THEN 'published' ELSE 'draft' END as publish_status,
                                   a.id as app_id, a.title as app_title, a.status as app_status
                            FROM projects p
                            LEFT JOIN apps a ON p.id = a.project_id
                            WHERE p.user_id = ? AND p.status = 'published'
                            ORDER BY p.updated_at DESC
                        ");
                        $stmt->bind_param("i", $user['id']);
                        $stmt->execute();
                        $publishable_projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                        
                        // Get categories for publishing
                        $stmt = $conn->prepare("SELECT * FROM categories WHERE is_active = true ORDER BY sort_order");
                        $stmt->execute();
                        $categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                        ?>
                        
                        <?php if (!empty($publishable_projects)): ?>
                            <div class="projects-list">
                                <?php foreach ($publishable_projects as $project): ?>
                                    <div class="project-card" style="margin-bottom: 20px;">
                                        <div style="display: flex; justify-content: space-between; align-items: start;">
                                            <div style="flex: 1;">
                                                <h4 style="margin-bottom: 5px; color: var(--text-primary);">
                                                    <?php echo htmlspecialchars($project['name']); ?>
                                                    <?php if ($project['publish_status'] === 'published'): ?>
                                                        <span style="background: var(--success); color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem; margin-left: 10px;">
                                                            Published
                                                        </span>
                                                    <?php endif; ?>
                                                </h4>
                                                <p style="color: var(--text-secondary); margin-bottom: 10px;">
                                                    <?php echo htmlspecialchars($project['description'] ?? 'No description'); ?>
                                                </p>
                                                <small style="color: var(--text-muted);">
                                                    <i class="fas fa-code"></i> <?php echo htmlspecialchars($project['framework']); ?> • 
                                                    Updated <?php echo date('M j, Y', strtotime($project['updated_at'])); ?>
                                                </small>
                                            </div>
                                            <div style="display: flex; gap: 10px;">
                                                <?php if ($project['publish_status'] === 'published'): ?>
                                                    <button class="btn btn-small" onclick="editApp(<?php echo $project['app_id']; ?>)">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                    <button class="btn btn-small" onclick="viewApp(<?php echo $project['app_id']; ?>)">
                                                        <i class="fas fa-eye"></i> View
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn btn-small" onclick="showPublishModal(<?php echo $project['id']; ?>)">
                                                        <i class="fas fa-rocket"></i> Publish
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state" style="text-align: center; padding: 60px 20px; color: var(--text-secondary);">
                                <i class="fas fa-rocket" style="font-size: 4rem; margin-bottom: 20px; opacity: 0.5;"></i>
                                <h3>No projects ready to publish</h3>
                                <p>Create and complete your projects first, then publish them to the marketplace!</p>
                                <button class="btn" onclick="navigate('projects')" style="margin-top: 20px;">
                                    <i class="fas fa-folder"></i> Go to Projects
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                                    
                                    
                <?php else: ?>
                    <div style="padding: 30px; flex: 1; overflow-y: auto;">
                        <h2 style="color: var(--text-primary);">Welcome to Creator Dashboard</h2>
                        <p style="color: var(--text-secondary); margin-top: 10px;">Select a project from the sidebar or create a new one to get started.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Create Project Modal -->
    <div class="modal" id="create-project-modal">
        <div class="modal-content">
            <h3 style="margin-bottom: 20px;">Create New Project</h3>
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_project">
                
                <div class="form-group">
                    <label for="project_name">Project Name</label>
                    <input type="text" id="project_name" name="project_name" required>
                </div>
                
                <div class="form-group">
                    <label for="project_description">Description</label>
                    <textarea id="project_description" name="project_description" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="framework">Framework</label>
                    <select id="framework" name="framework">
                        <option value="vanilla">Vanilla JS</option>
                        <option value="react">React</option>
                        <option value="vue">Vue.js</option>
                        <option value="angular">Angular</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn">Create Project</button>
                    <button type="button" class="btn" style="background: var(--input-bg);" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    
    <!-- Publish Modal -->
    <div class="modal" id="publish-modal">
        <div class="modal-content" style="max-width: 600px;">
            <h3 style="margin-bottom: 20px;">Publish to Marketplace</h3>
            <form id="publish-form" method="POST" action="">
                <input type="hidden" name="action" value="publish_app">
                <input type="hidden" id="publish-project-id" name="project_id">
                
                <div class="form-group">
                    <label for="app-title">App Title *</label>
                    <input type="text" id="app-title" name="title" required>
                </div>
                
                <div class="form-group">
                    <label for="app-short-description">Short Description *</label>
                    <input type="text" id="app-short-description" name="short_description" maxlength="500" required
                           placeholder="Brief description (max 500 characters)">
                </div>
                
                <div class="form-group">
                    <label for="app-description">Full Description *</label>
                    <textarea id="app-description" name="description" rows="5" required
                              placeholder="Detailed description of your app..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="app-category">Category *</label>
                    <select id="app-category" name="category" required>
                        <option value="">Select a category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category['slug']); ?>">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="app-tags">Tags</label>
                    <input type="text" id="app-tags" name="tags" 
                           placeholder="Comma-separated tags (e.g., game, productivity, fun)">
                </div>
                
                <div class="form-group">
                    <label for="app-thumbnail">Thumbnail URL</label>
                    <input type="url" id="app-thumbnail" name="thumbnail_url" 
                           placeholder="https://example.com/thumbnail.png">
                </div>
                
                <div class="form-group">
                    <label for="app-demo">Demo URL</label>
                    <input type="url" id="app-demo" name="demo_url" 
                           placeholder="https://example.com/demo">
                </div>
                
                <div class="form-group">
                    <label>Pricing</label>
                    <div style="display: flex; gap: 20px; align-items: center;">
                        <label style="display: flex; align-items: center; gap: 8px;">
                            <input type="radio" name="is_free" value="1" checked>
                            <span>Free</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px;">
                            <input type="radio" name="is_free" value="0">
                            <span>Paid</span>
                        </label>
                        <input type="number" id="app-price" name="price" step="0.01" min="0" placeholder="0.00" 
                               style="width: 100px; margin-left: 10px;" disabled>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn">Publish App</button>
                    <button type="button" class="btn" style="background: var(--input-bg);" onclick="closePublishModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
        
    <script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.34.1/min/vs/loader.js"></script>
    <script>
        // Simple memory usage monitor
        function checkMemoryUsage() {
            if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                if (performance.memory) {
                    const memory = performance.memory;
                    const used = (memory.usedJSHeapSize / 1048576).toFixed(2);
                    const total = (memory.totalJSHeapSize / 1048576).toFixed(2);
                    const limit = (memory.jsHeapSizeLimit / 1048576).toFixed(2);
                    
                    console.log(`[Bloxer Memory] Used: ${used}MB, Total: ${total}MB, Limit: ${limit}MB`);
                    
                    // Warn if memory usage is high
                    if (memory.usedJSHeapSize / memory.jsHeapSizeLimit > 0.8) {
                        console.warn('[Bloxer Memory] High memory usage detected!');
                        showNotification('High memory usage - consider refreshing', 'warning');
                    }
                }
            }
        }
        
        // Check memory every 30 seconds
        setInterval(checkMemoryUsage, 30000);
        
        // =============================================
        // CODE TEMPLATES FUNCTION
        // =============================================
        
        function addCodeTemplates() {
            const templates = {
                html: {
                    name: 'HTML Boilerplate',
                    template: '<!DOCTYPE html>\n<html lang="pl">\n<head>\n    <meta charset="UTF-8">\n    <meta name="viewport" content="width=device-width, initial-scale=1.0">\n    <title></title>\n</head>\n<body>\n    \n</body>\n</html>'
                },
                css: {
                    name: 'CSS Reset + Basic',
                    template: '* {\n    margin: 0;\n    padding: 0;\n    box-sizing: border-box;\n}\n\nbody {\n    font-family: Arial, sans-serif;\n    line-height: 1.6;\n}'
                },
                javascript: {
                    name: 'JS Function Template',
                    template: 'function functionName(param1, param2) {\n    // Your code here\n    return result;\n}\n\n// Usage\nconst result = functionName(value1, value2);'
                },
                react: {
                    name: 'React Component',
                    template: 'import React from \"react\";\n\nfunction ComponentName() {\n    const [state, setState] = React.useState(null);\n    \n    return (\n        <div>\n            <h1>Hello World</h1>\n        </div>\n    );\n}\n\nexport default ComponentName;'
                }
            };
            
            // Create templates panel
            const templatesPanel = document.createElement('div');
            templatesPanel.className = 'templates-panel';
            templatesPanel.style.cssText = `
                position: fixed;
                top: 60px;
                right: 20px;
                background: var(--glass-bg);
                border: 1px solid var(--glass-border);
                border-radius: 8px;
                padding: 15px;
                z-index: 1000;
                min-width: 250px;
                max-height: 400px;
                overflow-y: auto;
                display: none;
            `;
            
            templatesPanel.innerHTML = `
                <h4 style="margin: 0 0 10px 0; color: var(--text-primary);">Code Templates</h4>
                ${Object.entries(templates).map(([key, template]) => `
                    <div class="template-item" data-template="${key}" style="
                        padding: 8px 12px;
                        margin: 5px 0;
                        background: var(--bg-hover);
                        border-radius: 4px;
                        cursor: pointer;
                        transition: background 0.2s;
                    ">
                        <div style="font-weight: 500; color: var(--text-primary);">${template.name}</div>
                        <div style="font-size: 0.8em; color: var(--text-secondary);">${key}</div>
                    </div>
                `).join('')}
            `;
            
            document.body.appendChild(templatesPanel);
            
            // Add click handlers
            templatesPanel.querySelectorAll('.template-item').forEach(item => {
                item.addEventListener('click', function() {
                    const templateKey = this.dataset.template;
                    const template = templates[templateKey];
                    
                    if (editor && activeFile) {
                        editor.setValue(template.template);
                        showNotification(`Template "${template.name}" inserted!`, 'success');
                        templatesPanel.style.display = 'none';
                    } else {
                        showNotification('Please open a file first', 'warning');
                    }
                });
            });
            
            // Add toggle button
            const templatesBtn = document.createElement('button');
            templatesBtn.innerHTML = '📋 Templates';
            templatesBtn.className = 'btn btn-secondary';
            templatesBtn.style.cssText = `
                position: fixed;
                top: 20px;
                right: 160px;
                z-index: 1000;
            `;
            
            templatesBtn.addEventListener('click', () => {
                templatesPanel.style.display = templatesPanel.style.display === 'none' ? 'block' : 'none';
            });
            
            document.body.appendChild(templatesBtn);
            
            debugLog('Code templates panel added');
        }
        
        // =============================================
        // AUTO FORMAT FUNCTION
        // =============================================
        
        function addAutoFormat() {
            function formatCode(code, language) {
                switch(language) {
                    case 'html':
                        return formatHTML(code);
                    case 'css':
                        return formatCSS(code);
                    case 'javascript':
                        return formatJS(code);
                    default:
                        return code;
                }
            }
            
            function formatHTML(html) {
                // Simple HTML formatting
                return html
                    .replace(/></g, '>\n')
                    .replace(/\n\s*\n/g, '\n')
                    .replace(/^\s+|\s+$/gm, '');
            }
            
            function formatCSS(css) {
                // Simple CSS formatting
                return css
                    .replace(/\s*\{\s*/g, ' {\n    ')
                    .replace(/\s*\}\s*/g, '\n}\n')
                    .replace(/;\s*/g, ';\n    ');
            }
            
            function formatJS(js) {
                // Simple JS formatting
                return js
                    .replace(/\s*\{\s*/g, ' {\n    ')
                    .replace(/\s*\}\s*/g, '\n}\n')
                    .replace(/;\s*/g, ';\n    ');
            }
            
            // Add format button
            const formatBtn = document.createElement('button');
            formatBtn.innerHTML = '🎨 Format';
            formatBtn.className = 'btn btn-secondary';
            formatBtn.style.cssText = `
                position: fixed;
                top: 20px;
                right: 260px;
                z-index: 1000;
            `;
            
            formatBtn.addEventListener('click', () => {
                if (editor && activeFile) {
                    const currentCode = editor.getValue();
                    const formattedCode = formatCode(currentCode, activeFile.type);
                    editor.setValue(formattedCode);
                    showNotification('Code formatted!', 'success');
                } else {
                    showNotification('Please open a file first', 'warning');
                }
            });
            
            document.body.appendChild(formatBtn);
            
            debugLog('Auto format button added');
        }
        
        // =============================================
        // QUICK SEARCH FUNCTION
        // =============================================
        
        function addQuickSearch() {
            let searchResults = [];
            let searchTimeout;
            
            function searchInFiles(query) {
                if (!query || query.length < 2) {
                    searchResults = [];
                    return;
                }
                
                const results = [];
                const regex = new RegExp(query, 'gi');
                
                // Search in current open files
                Object.entries(currentFiles).forEach(([filePath, fileData]) => {
                    const matches = fileData.content.match(regex);
                    if (matches) {
                        results.push({
                            file: filePath,
                            matches: matches.length,
                            preview: fileData.content.substring(0, 200) + '...'
                        });
                    }
                });
                
                searchResults = results;
                displaySearchResults(results);
            }
            
            function displaySearchResults(results) {
                let searchPanel = document.querySelector('.search-panel');
                
                if (!searchPanel) {
                    searchPanel = document.createElement('div');
                    searchPanel.className = 'search-panel';
                    searchPanel.style.cssText = `
                        position: fixed;
                        top: 60px;
                        left: 20px;
                        background: var(--glass-bg);
                        border: 1px solid var(--glass-border);
                        border-radius: 8px;
                        padding: 15px;
                        z-index: 1000;
                        min-width: 300px;
                        max-height: 400px;
                        overflow-y: auto;
                        display: none;
                    `;
                    document.body.appendChild(searchPanel);
                }
                
                if (results.length === 0) {
                    searchPanel.innerHTML = '<div style="color: var(--text-secondary);">No results found</div>';
                } else {
                    searchPanel.innerHTML = `
                        <h4 style="margin: 0 0 10px 0; color: var(--text-primary);">Search Results (${results.length})</h4>
                        ${results.map(result => `
                            <div class="search-result" style="
                                padding: 8px 12px;
                                margin: 5px 0;
                                background: var(--bg-hover);
                                border-radius: 4px;
                                cursor: pointer;
                                transition: background 0.2s;
                            ">
                                <div style="font-weight: 500; color: var(--text-primary);">${result.file}</div>
                                <div style="font-size: 0.8em; color: var(--text-secondary);">${result.matches} matches</div>
                                <div style="font-size: 0.9em; color: var(--text-muted); margin-top: 4px;">${result.preview}</div>
                            </div>
                        `).join('')}
                    `;
                    
                    // Add click handlers for results
                    searchPanel.querySelectorAll('.search-result').forEach((item, index) => {
                        item.addEventListener('click', () => {
                            const result = results[index];
                            openFile(result.file, currentFiles[result.file].fileType);
                            searchPanel.style.display = 'none';
                        });
                    });
                }
                
                searchPanel.style.display = 'block';
            }
            
            // Create search input
            const searchContainer = document.createElement('div');
            searchContainer.style.cssText = `
                position: fixed;
                top: 20px;
                left: 20px;
                z-index: 1000;
            `;
            
            const searchInput = document.createElement('input');
            searchInput.type = 'text';
            searchInput.placeholder = '🔍 Quick search...';
            searchInput.style.cssText = `
                background: var(--glass-bg);
                border: 1px solid var(--glass-border);
                border-radius: 6px;
                padding: 8px 12px;
                color: var(--text-primary);
                width: 200px;
            `;
            
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    searchInFiles(e.target.value);
                }, 300);
            });
            
            searchInput.addEventListener('blur', () => {
                setTimeout(() => {
                    const searchPanel = document.querySelector('.search-panel');
                    if (searchPanel) {
                        searchPanel.style.display = 'none';
                    }
                }, 200);
            });
            
            searchContainer.appendChild(searchInput);
            document.body.appendChild(searchContainer);
            
            debugLog('Quick search added');
        }
        
        // =============================================
        // FILE TREE EXPORT FUNCTION
        // =============================================
        
        function addFileTreeExport() {
            function buildFileTree(files) {
                const tree = {};
                
                Object.entries(files).forEach(([filePath, fileData]) => {
                    const parts = filePath.split('/').filter(p => p);
                    let current = tree;
                    
                    parts.forEach((part, index) => {
                        if (index === parts.length - 1) {
                            // This is the file
                            current[part] = {
                                type: fileData.fileType,
                                size: fileData.content.length,
                                modified: new Date().toISOString()
                            };
                        } else {
                            // This is a directory
                            if (!current[part]) {
                                current[part] = { type: 'directory', children: {} };
                            }
                            current = current[part].children;
                        }
                    });
                });
                
                return tree;
            }
            
            function downloadJSON(filename, data) {
                const json = JSON.stringify(data, null, 2);
                const blob = new Blob([json], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                
                const a = document.createElement('a');
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            }
            
            function exportFileTree() {
                if (Object.keys(currentFiles).length === 0) {
                    showNotification('No files to export', 'warning');
                    return;
                }
                
                const fileTree = buildFileTree(currentFiles);
                const exportData = {
                    projectName: '<?php echo htmlspecialchars($current_project['name'] ?? 'Project'); ?>',
                    exportedAt: new Date().toISOString(),
                    totalFiles: Object.keys(currentFiles).length,
                    fileTree: fileTree,
                    files: Object.entries(currentFiles).reduce((acc, [path, data]) => {
                        acc[path] = {
                            type: data.fileType,
                            size: data.content.length,
                            preview: data.content.substring(0, 100) + '...'
                        };
                        return acc;
                    }, {})
                };
                
                downloadJSON('file-tree-export.json', exportData);
                showNotification('File tree exported successfully!', 'success');
                debugLog('File tree exported');
            }
            
            // Add export button
            const exportBtn = document.createElement('button');
            exportBtn.innerHTML = '📁 Export Tree';
            exportBtn.className = 'btn btn-secondary';
            exportBtn.style.cssText = `
                position: fixed;
                top: 20px;
                right: 360px;
                z-index: 1000;
            `;
            
            exportBtn.addEventListener('click', exportFileTree);
            document.body.appendChild(exportBtn);
            
            debugLog('File tree export added');
        }
        
        // Initialize all new features
        document.addEventListener('DOMContentLoaded', function() {
            addCodeTemplates();
            addAutoFormat();
            addQuickSearch();
            addFileTreeExport();
        });
        
        // Simple performance monitor
        function measurePerformance(name, fn) {
            if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                const start = performance.now();
                const result = fn();
                const end = performance.now();
                console.log(`[Bloxer Performance] ${name}: ${(end - start).toFixed(2)}ms`);
                return result;
            }
            return fn();
        }
        
        // Simple debug helper
        function debugLog(message) {
            if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                console.log('[Bloxer Debug]', message);
            }
        }
        
        // Simple notification system
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.textContent = message;
            
            // Style the notification
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 12px 20px;
                background: ${type === 'error' ? 'var(--error)' : type === 'success' ? 'var(--success)' : 'var(--accent)'};
                color: white;
                border-radius: 8px;
                z-index: 10000;
                opacity: 0;
                transform: translateX(100%);
                transition: all 0.3s ease;
            `;
            
            document.body.appendChild(notification);
            
            // Animate in
            setTimeout(() => {
                notification.style.opacity = '1';
                notification.style.transform = 'translateX(0)';
            }, 100);
            
            // Remove after 3 seconds
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }
        
        // Simple copy to clipboard helper
        function copyToClipboard(text) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(() => {
                    showNotification('Copied to clipboard!', 'success');
                }).catch(() => {
                    fallbackCopy(text);
                });
            } else {
                fallbackCopy(text);
            }
        }
        
        function fallbackCopy(text) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                document.execCommand('copy');
                showNotification('Copied to clipboard!', 'success');
            } catch (err) {
                showNotification('Failed to copy', 'error');
            }
            
            document.body.removeChild(textArea);
        }
        
        // Simple localStorage helper
        function saveToLocalStorage(key, value) {
            try {
                localStorage.setItem(key, JSON.stringify(value));
                debugLog('Saved to localStorage: ' + key);
            } catch (e) {
                debugLog('Failed to save to localStorage: ' + e.message);
            }
        }
        
        function getFromLocalStorage(key, defaultValue = null) {
            try {
                const item = localStorage.getItem(key);
                return item ? JSON.parse(item) : defaultValue;
            } catch (e) {
                debugLog('Failed to get from localStorage: ' + e.message);
                return defaultValue;
            }
        }
        
        // Initialize with debug info
        debugLog('Dashboard initialized');
        debugLog('Current page: <?php echo $page; ?>');
        debugLog('User type: <?php echo $user['user_type']; ?>');
        let currentFiles = {};
        let activeFile = null;
        
        // Initialize Monaco Editor with performance monitoring
        require.config({ paths: { vs: 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.34.1/min/vs' }});
        
        measurePerformance('Monaco Editor Load', () => {
            require(['vs/editor/editor.main'], function() {
                measurePerformance('Monaco Editor Create', () => {
                    editor = monaco.editor.create(document.getElementById('monaco-editor'), {
                        theme: 'vs-dark',
                        automaticLayout: true,
                        minimap: { enabled: false },
                        scrollBeyondLastLine: false,
                        fontSize: 14,
                        lineNumbers: 'on',
                        roundedSelection: false,
                        cursorStyle: 'line',
                        wordWrap: 'on'
                    });
                    
                    debugLog('Monaco Editor initialized successfully');
                    
                    // Auto-save functionality
                    let saveTimeout;
                    editor.onDidChangeModelContent(() => {
                        clearTimeout(saveTimeout);
                        const saveStatusElement = document.getElementById('save-status');
                        if (saveStatusElement) {
                            saveStatusElement.textContent = 'Modified';
                        }
                        saveTimeout = setTimeout(() => {
                            if (activeFile) {
                                measurePerformance('Auto Save', () => saveFile(activeFile.path, editor.getValue()));
                            }
                        }, 1000);
                    });
                    
                    // Check if there are any files to load
                    const fileItems = document.querySelectorAll('.file-item');
                    if (fileItems.length > 0) {
                        // Auto-select first file
                        selectFile(fileItems[0]);
                    } else {
                        console.log('No files found in project');
                        const saveStatusElement = document.getElementById('save-status');
                        if (saveStatusElement) {
                            saveStatusElement.textContent = 'No files in project';
                        }
                    }
                });
            });
        });
        
        function openFile(filePath, fileType) {
            measurePerformance('Open File: ' + filePath, () => {
                // Remove active class from all files
                document.querySelectorAll('.file-item').forEach(item => {
                    item.classList.remove('active');
                });
                
                // Add active class to clicked file
                const clickedItem = event.target.closest('.file-item');
                if (clickedItem) {
                    clickedItem.classList.add('active');
                }
                
                // Check if file is already open in tabs
                const existingTab = document.querySelector(`[data-file="${filePath}"]`);
                if (existingTab) {
                    existingTab.click();
                    return;
                }
                
                // Show loading state
                const saveStatusElement = document.getElementById('save-status');
                if (saveStatusElement) {
                    saveStatusElement.textContent = 'Loading...';
                    saveStatusElement.style.color = '#f59e0b';
                }
                
                // Load file content
                fetch(`get_file.php?project_id=<?php echo $project_id; ?>&file=${encodeURIComponent(filePath)}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            // Create tab
                            createTab(filePath, fileType, data.content);
                            
                            // Update preview if HTML
                            if (fileType === 'html') {
                                updatePreview(data.content);
                            }
                        } else {
                            console.error('Failed to load file:', data);
                            if (saveStatusElement) {
                                saveStatusElement.textContent = 'Error: ' + (data.error || 'File not found');
                                saveStatusElement.style.color = '#ef4444';
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error loading file:', error);
                        if (saveStatusElement) {
                            saveStatusElement.textContent = 'Connection error';
                            saveStatusElement.style.color = '#ef4444';
                        }
                    });
            });
        }
        
        function createTab(filePath, fileType, content) {
            const tabsContainer = document.getElementById('editor-tabs');
            const fileName = filePath.split('/').pop();
            
            const tab = document.createElement('div');
            tab.className = 'editor-tab';
            tab.dataset.file = filePath;
            tab.innerHTML = `
                <i class="fas fa-file-code"></i>
                <span>${fileName}</span>
                <i class="fas fa-times" onclick="closeTab('${filePath}', event)"></i>
            `;
            
            tab.onclick = (e) => {
                if (!e.target.classList.contains('fa-times')) {
                    switchToFile(filePath);
                }
            };
            
            tabsContainer.appendChild(tab);
            switchToFile(filePath);
            
            // Set content in editor
            if (editor) {
                editor.setValue(content);
                setLanguage(fileType);
            }
            
            currentFiles[filePath] = { content, fileType };
            activeFile = { path: filePath, type: fileType };
        }
        
        // Auto-save user preferences
        function saveUserPreferences() {
            const preferences = {
                lastOpenFile: activeFile ? activeFile.path : null,
                editorTheme: 'vs-dark',
                autoSave: true,
                hotReload: hotReloadEnabled
            };
            saveToLocalStorage('bloxer_preferences', preferences);
        }
        
        // Load user preferences
        function loadUserPreferences() {
            const preferences = getFromLocalStorage('bloxer_preferences', {});
            debugLog('Loaded user preferences:', preferences);
            return preferences;
        }
        
        // Initialize preferences on load
        const userPrefs = loadUserPreferences();
        
        // Auto-save preferences when changing files
        function switchToFile(filePath) {
            // Update active tab
            document.querySelectorAll('.editor-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelector(`[data-file="${filePath}"]`).classList.add('active');
            
            // Update editor content
            if (editor && currentFiles[filePath]) {
                editor.setValue(currentFiles[filePath].content);
                setLanguage(currentFiles[filePath].fileType);
                activeFile = { path: filePath, type: currentFiles[filePath].fileType };
                
                // Save preferences
                saveUserPreferences();
            }
            
            // Update file explorer active state
            document.querySelectorAll('.file-item').forEach(item => {
                item.classList.remove('active');
            });
        }
        
        function closeTab(filePath, event) {
            event.stopPropagation();
            
            const tab = document.querySelector(`[data-file="${filePath}"]`);
            tab.remove();
            
            delete currentFiles[filePath];
            
            if (activeFile && activeFile.path === filePath) {
                // Switch to another open tab
                const remainingTabs = document.querySelectorAll('.editor-tab');
                if (remainingTabs.length > 0) {
                    remainingTabs[0].click();
                } else {
                    activeFile = null;
                    if (editor) editor.setValue('');
                }
            }
        }
        
        function setLanguage(fileType) {
            const languageMap = {
                'html': 'html',
                'css': 'css',
                'js': 'javascript',
                'json': 'json',
                'md': 'markdown'
            };
            
            if (editor && languageMap[fileType]) {
                monaco.editor.setModelLanguage(editor.getModel(), languageMap[fileType]);
            }
        }
        
        // Simple theme switcher
        function switchTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            saveToLocalStorage('bloxer_theme', theme);
            debugLog('Theme switched to: ' + theme);
            
            // Update Monaco theme if editor is initialized
            if (editor) {
                const monacoTheme = theme === 'light' ? 'vs' : 'vs-dark';
                monaco.editor.setTheme(monacoTheme);
            }
        }
        
        // Load saved theme
        const savedTheme = getFromLocalStorage('bloxer_theme', 'dark');
        switchTheme(savedTheme);
        
        // Auto-save indicator
        function updateAutoSaveIndicator(status) {
            const indicator = document.querySelector('.auto-save-indicator');
            if (indicator) {
                indicator.className = 'auto-save-indicator ' + status;
                indicator.textContent = status === 'saving' ? 'Saving...' : status === 'saved' ? 'Saved' : 'Ready';
            }
        }
        
        // Update auto-save indicator when saving
        function saveFile(filePath, content) {
            updateAutoSaveIndicator('saving');
            
            const saveStatusElement = document.getElementById('save-status');
            if (saveStatusElement) {
                saveStatusElement.textContent = 'Saving...';
                saveStatusElement.style.color = '#f59e0b';
            }
            
            fetch('dashboard.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=save_file&project_id=<?php echo $project_id; ?>&file_path=${encodeURIComponent(filePath)}&content=${encodeURIComponent(content)}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    console.log('File saved successfully');
                    showNotification('File saved successfully!', 'success');
                    updateAutoSaveIndicator('saved');
                    
                    if (saveStatusElement) {
                        saveStatusElement.textContent = 'Saved';
                        saveStatusElement.style.color = '#10b981';
                        setTimeout(() => {
                            saveStatusElement.textContent = 'Ready';
                            saveStatusElement.style.color = '';
                            updateAutoSaveIndicator('ready');
                        }, 2000);
                    }
                    
                    if (activeFile && activeFile.type === 'html') {
                        updatePreview(content);
                    }
                } else {
                    console.error('Failed to save file:', data);
                    showNotification('Error: ' + (data.error || 'Unknown error'), 'error');
                    updateAutoSaveIndicator('error');
                    
                    if (saveStatusElement) {
                        saveStatusElement.textContent = 'Error: ' + (data.error || 'Unknown error');
                        saveStatusElement.style.color = '#ef4444';
                    }
                }
            })
            .catch(error => {
                console.error('Error saving file:', error);
                showNotification('Connection error', 'error');
                updateAutoSaveIndicator('error');
                
                if (saveStatusElement) {
                    saveStatusElement.textContent = 'Connection error';
                    saveStatusElement.style.color = '#ef4444';
                }
            });
        }
        
        function updatePreview(htmlContent) {
            const preview = document.getElementById('preview-frame');
            if (preview) {
                preview.srcdoc = htmlContent;
            }
        }
        
        function navigate(page, projectId = null) {
            const url = new URL(window.location);
            url.searchParams.set('page', page);
            if (projectId) {
                url.searchParams.set('project_id', projectId);
            }
            window.location.href = url.toString();
        }
        
        function showCreateProjectModal() {
            const modal = document.getElementById('create-project-modal');
            if (modal) {
                modal.style.display = 'flex';
                // Focus on first input
                setTimeout(() => {
                    const firstInput = modal.querySelector('input');
                    if (firstInput) firstInput.focus();
                }, 100);
                
                // Show notification
                showNotification('Creating new project...', 'info');
            }
        }
        
        function closeModal() {
            const modal = document.getElementById('create-project-modal');
            if (modal) {
                modal.style.display = 'none';
                // Reset form
                const form = modal.querySelector('form');
                if (form) form.reset();
            }
        }
        
        function showPublishModal(projectId) {
            document.getElementById('publish-project-id').value = projectId;
            document.getElementById('publish-modal').style.display = 'flex';
        }
        
        function closePublishModal() {
            document.getElementById('publish-modal').style.display = 'none';
            document.getElementById('publish-form').reset();
        }
        
                
        function editApp(appId) {
            window.location.href = `edit_app.php?id=${appId}`;
        }
        
        function viewApp(appId) {
            window.location.href = `app.php?id=${appId}`;
        }
        
        function deleteProject(event, projectId) {
            event.stopPropagation();
            
            if (confirm('Are you sure you want to delete this project? This action cannot be undone.')) {
                // Add loading state
                const button = event.target.closest('button');
                if (button) {
                    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                    button.disabled = true;
                }
                
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_project">
                    <input type="hidden" name="project_id" value="${projectId}">
                `;
                document.body.appendChild(form);
                
                // Show notification
                showNotification('Deleting project...', 'info');
                
                form.submit();
            }
        }
        
                
        // Pricing toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            const freeRadio = document.querySelector('input[name="is_free"][value="1"]');
            const paidRadio = document.querySelector('input[name="is_free"][value="0"]');
            const priceInput = document.getElementById('app-price');
            
            if (freeRadio && paidRadio && priceInput) {
                freeRadio.addEventListener('change', function() {
                    priceInput.disabled = true;
                    priceInput.value = '';
                });
                
                paidRadio.addEventListener('change', function() {
                    priceInput.disabled = false;
                    priceInput.focus();
                });
            }
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // ESC to close modals
            if (e.key === 'Escape') {
                closeModal();
                closePublishModal();
            }
            
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case 'w':
                        e.preventDefault();
                        closeModal();
                        break;
                }
            }
        });
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const createModal = document.getElementById('create-project-modal');
            const publishModal = document.getElementById('publish-modal');
            
            if (event.target === createModal) {
                closeModal();
            }
            if (event.target === publishModal) {
                closePublishModal();
            }
        }
    </script>
</body>
</html>
