<?php
require_once 'mainlogincore.php';

$auth = new AuthCore();

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

if (!$auth->isDeveloper()) {
    header('Location: index.php');
    exit();
}

$user = $auth->getCurrentUser();

// Handle app publishing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'publish_app') {
    $project_id = $_POST['project_id'] ?? null;
    $title = trim($_POST['title'] ?? '');
    $short_description = trim($_POST['short_description'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? '';
    $price = floatval($_POST['price'] ?? 0);
    $tags = $_POST['tags'] ?? '';
    $thumbnail_url = trim($_POST['thumbnail_url'] ?? '');
    
    if ($project_id && !empty($title) && !empty($description) && !empty($category)) {
        $conn = $auth->getConnection();
        
        // Check if project exists and belongs to user
        $stmt = $conn->prepare("SELECT id, name FROM projects WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $project_id, $user['id']);
        $stmt->execute();
        $project = $stmt->get_result()->fetch_assoc();
        
        if ($project) {
            // Generate slug
            $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $title));
            $slug = rtrim($slug, '-');
            
            // Check if slug already exists
            $stmt = $conn->prepare("SELECT id FROM apps WHERE slug = ?");
            $stmt->bind_param("s", $slug);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $slug .= '-' . time();
            }
            
            // Insert app
            $stmt = $conn->prepare("
                INSERT INTO apps (
                    project_id, title, slug, description, short_description, 
                    category, tags, thumbnail_url, price, is_free, is_public, 
                    status, published_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'published', NOW())
            ");
            
            $is_free = $price <= 0;
            $is_public = true;
            $tags_array = !empty($tags) ? json_encode(explode(',', $tags)) : null;
            
            $stmt->bind_param("issssssddii", 
                $project_id, $title, $slug, $description, $short_description,
                $category, $tags_array, $thumbnail_url, $price, $is_free, $is_public
            );
            
            if ($stmt->execute()) {
                $app_id = $stmt->insert_id;
                
                // Create initial version
                $stmt = $conn->prepare("
                    INSERT INTO app_versions (app_id, version, changelog, is_current) 
                    VALUES (?, '1.0.0', 'Initial release', TRUE)
                ");
                $stmt->bind_param("i", $app_id);
                $stmt->execute();
                
                // Update project status
                $stmt = $conn->prepare("UPDATE projects SET status = 'published' WHERE id = ?");
                $stmt->bind_param("i", $project_id);
                $stmt->execute();
                
                header('Location: publish.php?success=1');
                exit();
            }
        }
    }
}

// Get user's projects that aren't published yet
$conn = $auth->getConnection();
$stmt = $conn->prepare("
    SELECT p.*, a.id as app_id 
    FROM projects p 
    LEFT JOIN apps a ON p.id = a.project_id
    WHERE p.user_id = ? AND p.status != 'published'
    ORDER BY p.updated_at DESC
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get categories
$stmt = $conn->prepare("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order");
$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get published apps
$stmt = $conn->prepare("
    SELECT a.*, p.name as project_name, c.name as category_name
    FROM apps a
    JOIN projects p ON a.project_id = p.id
    LEFT JOIN categories c ON a.category = c.slug
    WHERE p.user_id = ?
    ORDER BY a.published_at DESC
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$published_apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Publish Center - Bloxer</title>
    <link rel="stylesheet" href="publish.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        .publish-container {
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
        
        .tabs {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            padding: 0 20px;
            border-bottom: 1px solid var(--glass-border);
        }
        
        .tab {
            padding: 15px 0;
            color: var(--text-secondary);
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.2s ease;
        }
        
        .tab.active {
            color: var(--accent);
            border-bottom-color: var(--accent);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .publish-form {
            max-width: 800px;
            margin: 0 auto;
            background: var(--bg-secondary);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 30px;
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
            min-height: 120px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
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
        
        .apps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .app-card {
            background: var(--bg-secondary);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
        }
        
        .app-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .app-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        
        .app-title {
            font-size: 1.1em;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0 0 5px 0;
        }
        
        .app-status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: 500;
        }
        
        .status-published {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
        }
        
        .status-draft {
            background: rgba(156, 163, 175, 0.2);
            color: #9ca3af;
        }
        
        .app-description {
            color: var(--text-secondary);
            margin-bottom: 15px;
            line-height: 1.5;
            font-size: 0.9em;
        }
        
        .app-stats {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            font-size: 0.85em;
            color: var(--text-secondary);
        }
        
        .stat {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .app-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 0.85em;
        }
        
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
            border: 1px solid rgba(34, 197, 94, 0.3);
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
        
        .project-select {
            margin-bottom: 20px;
        }
        
        .price-input {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .price-input input {
            flex: 1;
        }
        
        .price-input span {
            color: var(--text-secondary);
        }
    </style>
</head>
<body>
    <div class="publish-container">
        <!-- Breadcrumbs -->
        <div class="back-button">
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </div>
        
        <!-- User Menu -->
        <div class="user-menu">
            <a href="dashboard.php" class="user-button">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="projects.php" class="user-button">
                <i class="fas fa-folder"></i>
                <span>Projects</span>
            </a>
            <a href="editor.php" class="user-button">
                <i class="fas fa-code"></i>
                <span>Editor</span>
            </a>
            <a href="logout.php" class="user-button" style="background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.3);">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
        
        <!-- Header -->
        <div class="header">
            <h1>Publish Center</h1>
        </div>
        
        <!-- Success Message -->
        <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
            <div class="alert alert-success" style="max-width: 800px; margin: 0 auto 20px;">
                <i class="fas fa-check-circle"></i>
                Your app has been published successfully!
            </div>
        <?php endif; ?>
        
        <!-- Tabs -->
        <div class="tabs">
            <div class="tab active" onclick="switchTab('publish')">
                <i class="fas fa-rocket"></i>
                <span style="margin-left: 8px;">Publish New App</span>
            </div>
            <div class="tab" onclick="switchTab('published')">
                <i class="fas fa-store"></i>
                <span style="margin-left: 8px;">Published Apps</span>
            </div>
        </div>
        
        <!-- Publish New App Tab -->
        <div id="publish-tab" class="tab-content active">
            <?php if (!empty($projects)): ?>
                <form method="POST" class="publish-form">
                    <input type="hidden" name="action" value="publish_app">
                    
                    <div class="project-select">
                        <label for="project_id">Select Project *</label>
                        <select id="project_id" name="project_id" required>
                            <option value="">Choose a project...</option>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?php echo $project['id']; ?>">
                                    <?php echo htmlspecialchars($project['name']); ?>
                                    (<?php echo htmlspecialchars($project['framework']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="title">App Title *</label>
                        <input type="text" id="title" name="title" required 
                               placeholder="My Awesome App">
                    </div>
                    
                    <div class="form-group">
                        <label for="short_description">Short Description</label>
                        <input type="text" id="short_description" name="short_description" 
                               placeholder="A brief description (max 255 chars)"
                               maxlength="255">
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Full Description *</label>
                        <textarea id="description" name="description" required 
                                  placeholder="Describe your app in detail..."></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="category">Category *</label>
                            <select id="category" name="category" required>
                                <option value="">Select category...</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category['slug']); ?>">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="price">Price</label>
                            <div class="price-input">
                                <input type="number" id="price" name="price" 
                                       min="0" step="0.01" value="0">
                                <span>USD (0 = Free)</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="tags">Tags</label>
                        <input type="text" id="tags" name="tags" 
                               placeholder="tag1, tag2, tag3">
                    </div>
                    
                    <div class="form-group">
                        <label for="thumbnail_url">Thumbnail URL</label>
                        <input type="url" id="thumbnail_url" name="thumbnail_url" 
                               placeholder="https://example.com/thumbnail.jpg">
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-rocket"></i>
                            Publish App
                        </button>
                        <a href="projects.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            Back to Projects
                        </a>
                    </div>
                </form>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <h2>No projects to publish</h2>
                    <p>Create and complete a project first before publishing</p>
                    <a href="projects.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Create Project
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Published Apps Tab -->
        <div id="published-tab" class="tab-content">
            <?php if (!empty($published_apps)): ?>
                <div class="apps-grid">
                    <?php foreach ($published_apps as $app): ?>
                        <div class="app-card">
                            <div class="app-header">
                                <div>
                                    <h3 class="app-title"><?php echo htmlspecialchars($app['title']); ?></h3>
                                    <p style="color: var(--text-secondary); font-size: 0.9em;">
                                        <?php echo htmlspecialchars($app['category_name'] ?: $app['category']); ?>
                                    </p>
                                </div>
                                <span class="app-status status-<?php echo htmlspecialchars($app['status']); ?>">
                                    <?php echo ucfirst(htmlspecialchars($app['status'])); ?>
                                </span>
                            </div>
                            
                            <p class="app-description">
                                <?php echo htmlspecialchars(substr($app['description'], 0, 100)) . '...'; ?>
                            </p>
                            
                            <div class="app-stats">
                                <div class="stat">
                                    <i class="fas fa-download"></i>
                                    <span><?php echo number_format($app['download_count']); ?></span>
                                </div>
                                <div class="stat">
                                    <i class="fas fa-eye"></i>
                                    <span><?php echo number_format($app['view_count']); ?></span>
                                </div>
                                <div class="stat">
                                    <i class="fas fa-star"></i>
                                    <span><?php echo number_format($app['rating'], 1); ?></span>
                                </div>
                            </div>
                            
                            <div class="app-actions">
                                <a href="app.php?id=<?php echo $app['id']; ?>" class="btn btn-primary btn-small">
                                    <i class="fas fa-eye"></i>
                                    View
                                </a>
                                <a href="marketplace.php" class="btn btn-secondary btn-small">
                                    <i class="fas fa-store"></i>
                                    Marketplace
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-store"></i>
                    <h2>No published apps yet</h2>
                    <p>Publish your first app to see it here</p>
                    <button class="btn btn-primary" onclick="switchTab('publish')">
                        <i class="fas fa-rocket"></i>
                        Publish App
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function switchTab(tabName) {
            // Update tab buttons
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            event.target.closest('.tab').classList.add('active');
            
            // Update tab content
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.getElementById(tabName + '-tab').classList.add('active');
        }
    </script>
</body>
</html>
