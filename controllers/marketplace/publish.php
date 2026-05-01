<?php
require_once __DIR__ . '/../../bootstrap.php';

$auth = new AuthCore();

if (!$auth->isLoggedIn()) {
    SecurityUtils::safeRedirect('../auth/login.php', 302, 'Unauthorized access attempt');
}

if (!$auth->isDeveloper()) {
    SecurityUtils::safeRedirect('../marketplace/marketplace.php', 302, 'Developer access required');
}

$user = $auth->getCurrentUser();

// Handle app publishing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'publish_app') {
    $project_id = $_POST['project_id'] ?? null;
    $title = trim($_POST['title'] ?? '');
    $short_description = trim($_POST['short_description'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? '';
    $price = 0; // All apps are now free
    $tags = $_POST['tags'] ?? '';
    $thumbnail_url = trim($_POST['thumbnail_url'] ?? '');
    $demo_url = trim($_POST['demo_url'] ?? '');
    $zip_url = trim($_POST['zip_url'] ?? '');
    
    if ($project_id && !empty($title) && !empty($description) && !empty($category) && !empty($demo_url) && !empty($zip_url)) {
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
                    category, tags, thumbnail_url, demo_url, zip_url, price, is_free, 
                    status, published_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'published', NOW())
            ");
            
            $is_free = true; // All apps are now free
            $tags_array = !empty($tags) ? json_encode(explode(',', $tags)) : null;
            
            $stmt->bind_param("issssssssddi", 
                $project_id, $title, $slug, $description, $short_description,
                $category, $tags_array, $thumbnail_url, $demo_url, $zip_url, $price, $is_free
            );
            
            if ($stmt->execute()) {
                $app_id = $stmt->insert_id;
                
                // Update project status
                $stmt = $conn->prepare("UPDATE projects SET status = 'published' WHERE id = ?");
                $stmt->bind_param("i", $project_id);
                $stmt->execute();
                
                SecurityUtils::safeRedirect('publish.php?success=1', 302, 'App published successfully');
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
    <link rel="stylesheet" href="../../assets/css/publish.css">
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
            padding: 12px 24px;
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            font-weight: 600;
            position: relative;
        }
        
        .tab::before {
            content: '';
            position: absolute;
            bottom: -3px;
            left: 0;
            width: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--accent), var(--accent-hover));
            transition: width 0.3s ease;
        }
        
        .tab:hover {
            color: var(--text-primary);
            transform: translateY(-2px);
        }
        
        .tab.active {
            color: var(--accent);
            border-bottom-color: var(--accent);
        }
        
        .tab.active::before {
            width: 100%;
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
            background: linear-gradient(135deg, var(--bg-secondary), var(--bg-tertiary));
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            backdrop-filter: blur(20px);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: var(--text-primary);
            font-weight: 600;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid var(--glass-border);
            border-radius: 12px;
            background: linear-gradient(135deg, var(--bg-primary), rgba(255,255,255,0.02));
            color: var(--text-primary);
            font-size: 1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(99,102,241,0.15);
            transform: translateY(-2px);
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
            <a href="projects.php" class="user-button">
                <i class="fas fa-folder"></i>
                <span>Projects</span>
            </a>
            <a href="../core/dashboard.php?page=workspace" class="user-button">
                <i class="fas fa-code"></i>
                <span>Workspace</span>
            </a>
            <a href="../controllers/auth/logout.php" class="user-button" style="background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.3);">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
        
        <!-- Header -->
        <div class="publish-header">
            <div class="publish-header-content">
                <div class="publish-header-main">
                    <div class="publish-header-icon">
                        <i class="fas fa-rocket"></i>
                    </div>
                    <div class="publish-header-text">
                        <h1>Publish Center</h1>
                        <p>Manage your app publications and reach the marketplace</p>
                    </div>
                </div>
                <div class="publish-header-stats">
                    <div class="publish-stat">
                        <span class="publish-stat-number"><?php echo count($published_apps); ?></span>
                        <span class="publish-stat-label">Published</span>
                    </div>
                    <div class="publish-stat">
                        <span class="publish-stat-number"><?php echo count($projects); ?></span>
                        <span class="publish-stat-label">Available</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Success Message -->
        <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
            <div class="publish-success-message">
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="success-content">
                    <h3>App Published Successfully!</h3>
                    <p>Your app is now live on the marketplace</p>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Tabs -->
        <div class="publish-tabs">
            <div class="publish-tab active" onclick="switchTab('publish')">
                <i class="fas fa-rocket"></i>
                <span>Publish New App</span>
            </div>
            <div class="publish-tab" onclick="switchTab('published')">
                <i class="fas fa-store"></i>
                <span>Published Apps</span>
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
                    
                    <div class="form-group">
                        <label for="demo_url">Demo URL *</label>
                        <input type="url" id="demo_url" name="demo_url" required
                               placeholder="https://example.com/demo">
                        <small>Link do działającej demonstracji aplikacji</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="zip_url">Download ZIP URL *</label>
                        <input type="url" id="zip_url" name="zip_url" required
                               placeholder="https://example.com/app.zip">
                        <small>Link do pliku ZIP z kodem źródłowym aplikacji</small>
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
                <div class="publish-filters">
                    <div class="publish-filter-group">
                        <div class="publish-filter-label">Filter by Status:</div>
                        <div class="publish-filter-pills">
                            <button class="publish-filter-pill active" data-filter="all">
                                <i class="fas fa-th"></i>
                                All (<?php echo count($published_apps); ?>)
                            </button>
                            <button class="publish-filter-pill" data-filter="published">
                                <i class="fas fa-check-circle"></i>
                                Published
                            </button>
                            <button class="publish-filter-pill" data-filter="draft">
                                <i class="fas fa-edit"></i>
                                Draft
                            </button>
                        </div>
                    </div>
                    <div class="publish-filter-group">
                        <div class="publish-filter-label">Sort by:</div>
                        <select class="publish-sort-select" id="sortSelect">
                            <option value="date">Date Published</option>
                            <option value="name">App Name</option>
                            <option value="downloads">Downloads</option>
                            <option value="rating">Rating</option>
                        </select>
                    </div>
                </div>
                
                <div class="publish-apps-grid">
                    <?php foreach ($published_apps as $app): ?>
                        <?php 
                        // Determine project type and icon
                        $project_type = 'Web App';
                        $project_icon = 'fa-globe';
                        if (stripos($app['category_name'] ?? '', 'game') !== false) {
                            $project_type = 'Game';
                            $project_icon = 'fa-gamepad';
                        } elseif (stripos($app['category_name'] ?? '', 'productivity') !== false) {
                            $project_type = 'Productivity';
                            $project_icon = 'fa-tasks';
                        } elseif (stripos($app['category_name'] ?? '', 'social') !== false) {
                            $project_type = 'Social';
                            $project_icon = 'fa-users';
                        } elseif (stripos($app['category_name'] ?? '', 'entertainment') !== false) {
                            $project_type = 'Entertainment';
                            $project_icon = 'fa-play-circle';
                        }
                        
                        // Status styling
                        $status_class = 'status-published';
                        $status_text = 'Published';
                        $status_color = '#34C759';
                        if ($app['status'] === 'draft') {
                            $status_class = 'status-draft';
                            $status_text = 'Draft';
                            $status_color = '#FF9500';
                        }
                        ?>
                        <article class="publish-app-card" data-status="<?php echo htmlspecialchars($app['status']); ?>" data-app-data='<?php echo htmlspecialchars(json_encode($app)); ?>'>
                            <div class="publish-app-header">
                                <div class="publish-app-icon">
                                    <i class="fas <?php echo $project_icon; ?>"></i>
                                </div>
                                <div class="publish-app-info">
                                    <h3 class="publish-app-title"><?php echo htmlspecialchars($app['title']); ?></h3>
                                    <div class="publish-app-meta">
                                        <span class="publish-app-type">
                                            <i class="fas fa-folder"></i>
                                            <?php echo $project_type; ?>
                                        </span>
                                        <span class="publish-app-category">
                                            <?php echo htmlspecialchars($app['category_name'] ?: 'Other'); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="publish-app-actions">
                                    <span class="publish-app-status <?php echo $status_class; ?>" style="color: <?php echo $status_color; ?>;">
                                        <i class="fas fa-circle"></i>
                                        <?php echo $status_text; ?>
                                    </span>
                                    <button class="publish-app-edit" onclick="editApp(<?php echo $app['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                        Edit
                                    </button>
                                </div>
                            </div>
                            
                            <div class="publish-app-content">
                                <p class="publish-app-description">
                                    <?php echo htmlspecialchars(substr($app['description'], 0, 120)) . '...'; ?>
                                </p>
                                
                                <div class="publish-app-stats">
                                    <div class="publish-stat">
                                        <div class="publish-stat-icon">
                                            <i class="fas fa-download"></i>
                                        </div>
                                        <div class="publish-stat-content">
                                            <span class="publish-stat-number"><?php echo number_format($app['download_count']); ?></span>
                                            <span class="publish-stat-label">Downloads</span>
                                        </div>
                                    </div>
                                    <div class="publish-stat">
                                        <div class="publish-stat-icon">
                                            <i class="fas fa-eye"></i>
                                        </div>
                                        <div class="publish-stat-content">
                                            <span class="publish-stat-number"><?php echo number_format($app['view_count']); ?></span>
                                            <span class="publish-stat-label">Views</span>
                                        </div>
                                    </div>
                                    <div class="publish-stat">
                                        <div class="publish-stat-icon">
                                            <i class="fas fa-star"></i>
                                        </div>
                                        <div class="publish-stat-content">
                                            <span class="publish-stat-number"><?php echo number_format($app['rating'], 1); ?></span>
                                            <span class="publish-stat-label">Rating</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="publish-app-footer">
                                <div class="publish-app-date">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo date('M j, Y', strtotime($app['created_at'])); ?>
                                </div>
                                <div class="publish-app-actions-footer">
                                    <a href="app.php?id=<?php echo $app['id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-eye"></i>
                                        View App
                                    </a>
                                    <a href="../marketplace/marketplace.php" class="btn btn-secondary">
                                        <i class="fas fa-store"></i>
                                        Marketplace
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="publish-empty-state">
                    <div class="publish-empty-icon">
                        <i class="fas fa-store"></i>
                    </div>
                    <h3>No Published Apps Yet</h3>
                    <p>Publish your first app to see it here in the marketplace</p>
                    <button class="btn btn-primary" onclick="switchTab('publish')">
                        <i class="fas fa-rocket"></i>
                        Publish First App
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function switchTab(tabName) {
            // Update tab buttons
            document.querySelectorAll('.publish-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            event.target.closest('.publish-tab').classList.add('active');
            
            // Update tab content
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.getElementById(tabName + '-tab').classList.add('active');
        }
        
        // Filter functionality
        document.addEventListener('DOMContentLoaded', function() {
            const filterPills = document.querySelectorAll('.publish-filter-pill');
            const appCards = document.querySelectorAll('.publish-app-card');
            
            filterPills.forEach(pill => {
                pill.addEventListener('click', function() {
                    // Update active pill
                    filterPills.forEach(p => p.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Filter cards
                    const filter = this.dataset.filter;
                    appCards.forEach(card => {
                        if (filter === 'all' || card.dataset.status === filter) {
                            card.style.display = 'block';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                });
            });
            
            // Sort functionality
            const sortSelect = document.getElementById('sortSelect');
            if (sortSelect) {
                sortSelect.addEventListener('change', function() {
                    const sortBy = this.value;
                    const container = document.querySelector('.publish-apps-grid');
                    const cards = Array.from(container.querySelectorAll('.publish-app-card'));
                    
                    cards.sort((a, b) => {
                        const aData = JSON.parse(a.dataset.appData || '{}');
                        const bData = JSON.parse(b.dataset.appData || '{}');
                        
                        switch(sortBy) {
                            case 'name':
                                return aData.title.localeCompare(bData.title);
                            case 'downloads':
                                return (bData.download_count || 0) - (aData.download_count || 0);
                            case 'rating':
                                return (bData.rating || 0) - (aData.rating || 0);
                            case 'date':
                            default:
                                return new Date(bData.created_at) - new Date(aData.created_at);
                        }
                    });
                    
                    cards.forEach(card => container.appendChild(card));
                });
            }
        });
        
        // Edit app function
        function editApp(appId) {
            // Redirect to edit page or open modal
            window.location.href = `edit_app.php?id=${appId}`;
        }
    </script>
</body>
</html>
