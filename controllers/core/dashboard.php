<?php
require_once __DIR__ . '/../../bootstrap.php';

$auth = new AuthCore();

if (!$auth->isLoggedIn()) {
    SecurityUtils::safeRedirect('login.php', 403, 'Authentication required');
}

if (!$auth->isDeveloper()) {
    SecurityUtils::safeRedirect('marketplace.php', 403, 'Developer access required');
}

$user = $auth->getCurrentUser();
$conn = $auth->getConnection();
$page = $_GET['page'] ?? 'workspace';
$project_id = $_GET['project_id'] ?? null;
$errors = [];

// Handle AJAX requests for chat
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'get_chat_messages') {
    $offer_id = SecurityUtils::validateInput($_GET['offer_id'] ?? null, 'int');
    if (!$offer_id) {
        SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'Invalid offer ID'], 400);
        SecurityUtils::safeExit('', 400, 'Invalid offer ID');
    }

    // Verify this offer belongs to the current developer
    $stmt = $conn->prepare("
        SELECT o.id 
        FROM offers o
        JOIN apps a ON o.app_id = a.id
        JOIN projects p ON a.project_id = p.id
        WHERE o.id = ? AND p.user_id = ?
    ");
    $stmt->bind_param("ii", $offer_id, $user['id']);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'Unauthorized'], 403);
        SecurityUtils::safeExit('', 403, 'Unauthorized');
    }

    // Get messages
    $stmt = $conn->prepare("
        SELECT m.*, u.username, 
               CASE WHEN m.sender_id = ? THEN 1 ELSE 0 END as is_sender
        FROM offer_messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.offer_id = ?
        ORDER BY m.created_at ASC
    ");
    $stmt->bind_param("ii", $user['id'], $offer_id);
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    SecurityUtils::sendJSONResponse(['success' => true, 'messages' => $messages]);
    SecurityUtils::safeExit('', 200, 'Chat messages retrieved');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($_POST['action'] ?? '') {
        case 'send_chat_message':
            $offer_id = SecurityUtils::validateInput($_POST['offer_id'] ?? null, 'int');
            $message = trim($_POST['message'] ?? '');
            
            if (!$offer_id || empty($message)) {
                SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'Missing required fields'], 400);
                SecurityUtils::safeExit('', 400, 'Missing required fields');
            }

            // Verify this offer belongs to the current developer
            $stmt = $conn->prepare("
                SELECT o.*, o.buyer_id 
                FROM offers o
                JOIN apps a ON o.app_id = a.id
                JOIN projects p ON a.project_id = p.id
                WHERE o.id = ? AND p.user_id = ?
            ");
            $stmt->bind_param("ii", $offer_id, $user['id']);
            $stmt->execute();
            $offer = $stmt->get_result()->fetch_assoc();
            
            if (!$offer) {
                SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'Unauthorized'], 403);
                SecurityUtils::safeExit('', 403, 'Unauthorized');
            }

            // Insert message
            $stmt = $conn->prepare("
                INSERT INTO offer_messages (offer_id, sender_id, message)
                VALUES (?, ?, ?)
            ");
            $stmt->bind_param("iis", $offer_id, $user['id'], $message);
            
            if ($stmt->execute()) {
                SecurityUtils::sendJSONResponse(['success' => true]);
            } else {
                SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'Failed to send message'], 500);
            }
            SecurityUtils::safeExit('', 200, 'Message sent');

        case 'create_project':
            $name = trim($_POST['project_name'] ?? '');
            $description = trim($_POST['project_description'] ?? '');
            $project_type = $_POST['project_type'] ?? 'blank';
            $framework = 'vanilla';

            if ($name === '' || strlen($name) < 2 || strlen($name) > 100) {
                $errors[] = 'Project name must be between 2 and 100 characters';
            }

            if (!empty($description) && strlen($description) > 500) {
                $errors[] = 'Description must not exceed 500 characters';
            }

            $uploaded_files = [];

            if ($project_type === 'upload' && isset($_FILES['project_files'])) {
                foreach ($_FILES['project_files']['tmp_name'] as $key => $tmp_name) {
                    $file_error = $_FILES['project_files']['error'][$key];
                    $file_name = $_FILES['project_files']['name'][$key];

                    if ($file_error !== UPLOAD_ERR_OK) {
                        $errors[] = "Upload error for $file_name: $file_error";
                        continue;
                    }

                    $file_content = file_get_contents($tmp_name);
                    if ($file_content === false) {
                        $errors[] = "Failed to read file: $file_name";
                        continue;
                    }

                    $file_type = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $file_path = '/' . $file_name;
                    $uploaded_files[] = [
                        'file_path' => $file_path,
                        'file_name' => $file_name,
                        'file_type' => $file_type,
                        'content' => $file_content
                    ];
                }
            } elseif ($project_type === 'folder' && isset($_FILES['project_folder'])) {
                foreach ($_FILES['project_folder']['tmp_name'] as $key => $tmp_name) {
                    $file_error = $_FILES['project_folder']['error'][$key];
                    $file_name = $_FILES['project_folder']['name'][$key];

                    if ($file_error !== UPLOAD_ERR_OK) {
                        $errors[] = "Upload error for $file_name: $file_error";
                        continue;
                    }

                    $file_content = file_get_contents($tmp_name);
                    if ($file_content === false) {
                        $errors[] = "Failed to read file: $file_name";
                        continue;
                    }

                    $file_type = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $file_path = '/' . $file_name;
                    $uploaded_files[] = [
                        'file_path' => $file_path,
                        'file_name' => basename($file_name),
                        'file_type' => $file_type,
                        'content' => $file_content
                    ];
                }
            }

            if (empty($errors)) {
                $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $name));
                $slug = rtrim($slug, '-');
                if ($slug === '') {
                    $slug = 'project-' . time();
                }
                
                // Check for duplicate slug and make unique
                $original_slug = $slug;
                $counter = 1;
                while (true) {
                    $check_stmt = $conn->prepare("SELECT id FROM projects WHERE slug = ? AND user_id = ? LIMIT 1");
                    $check_stmt->bind_param("si", $slug, $user['id']);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    if ($check_result->num_rows === 0) {
                        break; // Slug is unique
                    }
                    $slug = $original_slug . '-' . $counter;
                    $counter++;
                }

                $stmt = $conn->prepare("
                    INSERT INTO projects (user_id, name, description, slug, framework)
                    VALUES (?, ?, ?, ?, ?)
                ");

                if (!$stmt) {
                    $errors[] = 'Database error occurred';
                } else {
                    $stmt->bind_param("issss", $user['id'], $name, $description, $slug, $framework);
                    if (!$stmt->execute()) {
                        $errors[] = 'Failed to create project';
                    } else {
                        $new_project_id = $stmt->insert_id;

                        if (!empty($uploaded_files)) {
                            // Limit number of files to prevent upload issues
                            $uploaded_files = array_slice($uploaded_files, 0, 55);
                            
                            foreach ($uploaded_files as $file) {
                                $file_stmt = $conn->prepare("
                                    INSERT IGNORE INTO project_files (project_id, file_path, file_name, file_type, content)
                                    VALUES (?, ?, ?, ?, ?)
                                ");
                                if ($file_stmt) {
                                    $file_stmt->bind_param("issss", $new_project_id, $file['file_path'], $file['file_name'], $file['file_type'], $file['content']);
                                    $file_stmt->execute();
                                }
                            }
                        } else {
                            $default_files = [
                                'index.html' => "<!DOCTYPE html>\n<html>\n<head>\n    <title>" . htmlspecialchars($name) . "</title>\n</head>\n<body>\n    <h1>Welcome to " . htmlspecialchars($name) . "</h1>\n</body>\n</html>",
                                'style.css' => "body {\n    font-family: Arial, sans-serif;\n    margin: 0;\n    padding: 20px;\n}",
                                'script.js' => "// Welcome to your new project!\n// Use this file for your JavaScript code\n\nfunction init() {\n    // Your code here\n}\n\n// Initialize when DOM is ready\ndocument.addEventListener('DOMContentLoaded', init);"
                            ];

                            foreach ($default_files as $file_name => $content) {
                                $file_type = pathinfo($file_name, PATHINFO_EXTENSION);
                                $file_stmt = $conn->prepare("
                                    INSERT IGNORE INTO project_files (project_id, file_path, file_name, file_type, content)
                                    VALUES (?, ?, ?, ?, ?)
                                ");
                                if ($file_stmt) {
                                    $file_path = '/' . $file_name;
                                    $file_stmt->bind_param("issss", $new_project_id, $file_path, $file_name, $file_type, $content);
                                    $file_stmt->execute();
                                }
                            }
                        }

                        $_SESSION['success_message'] = 'Project created successfully';
                        SecurityUtils::safeRedirect('dashboard.php?page=projects', 302, 'Project created successfully');
                    }
                }
            }

            if (!empty($errors)) {
                $_SESSION['form_errors'] = $errors;
            }
            break;
    }
}

$projects_stmt = $conn->prepare("
    SELECT p.*,
           COUNT(pf.id) as file_count
    FROM projects p
    LEFT JOIN project_files pf ON p.id = pf.project_id
    WHERE p.user_id = ?
    GROUP BY p.id
    ORDER BY p.updated_at DESC
");
$projects_stmt->bind_param("i", $user['id']);
$projects_stmt->execute();
$projects = $projects_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$current_project = null;
if ($project_id) {
    $project_stmt = $conn->prepare("
        SELECT * FROM projects
        WHERE id = ? AND user_id = ?
        LIMIT 1
    ");
    $project_stmt->bind_param("ii", $project_id, $user['id']);
    $project_stmt->execute();
    $current_project = $project_stmt->get_result()->fetch_assoc();
}

$offers = [];
$offers_stmt = $conn->prepare("
    SELECT o.*, a.title as app_title, u.username as buyer_name, u.email as buyer_email
    FROM offers o
    JOIN apps a ON o.app_id = a.id
    JOIN projects p ON a.project_id = p.id
    JOIN users u ON o.buyer_id = u.id
    WHERE p.user_id = ?
    ORDER BY o.created_at DESC
");
if ($offers_stmt) {
    $offers_stmt->bind_param("i", $user['id']);
    $offers_stmt->execute();
    $offers = $offers_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

foreach ($offers as &$offer) {
    $messages_stmt = $conn->prepare("
        SELECT m.*, u.username
        FROM offer_messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.offer_id = ?
        ORDER BY m.created_at ASC
    ");
    if ($messages_stmt) {
        $messages_stmt->bind_param("i", $offer['id']);
        $messages_stmt->execute();
        $offer['messages'] = $messages_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        $offer['messages'] = [];
    }
}
unset($offer);

$publish_stmt = $conn->prepare("
    SELECT p.*,
           CASE WHEN a.id IS NOT NULL THEN 'published' ELSE 'draft' END as publish_status,
           a.id as app_id,
           a.title as app_title,
           a.status as app_status
    FROM projects p
    LEFT JOIN apps a ON p.id = a.project_id
    WHERE p.user_id = ?
    ORDER BY p.updated_at DESC
");
$publish_stmt->bind_param("i", $user['id']);
$publish_stmt->execute();
$publishable_projects = $publish_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitles = [
    'workspace' => 'Workspace',
    'projects' => 'Projects',
    'teams' => 'Team Management',
    'offers' => 'Offers',
    'reviews' => 'Code Review',
    'deploy' => 'Deploy Queue',
    'publish' => 'Publish Center',
    'version-control' => 'Version Control',
    'analytics' => 'Analytics',
    'notifications' => 'Notifications',
    'settings' => 'Settings'
];

$total_files = array_sum(array_map(fn($project) => (int) ($project['file_count'] ?? 0), $projects));
$published_count = count(array_filter($publishable_projects, fn($project) => ($project['publish_status'] ?? '') === 'published'));
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Creator Dashboard - Bloxer</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/reboot.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="app-studio">
    <div class="studio-shell">
        <aside class="studio-sidebar sidebar" id="sidebar">
            <div class="studio-brand">
                <span class="brand-mark"><i class="fas fa-laptop-code"></i></span>
                <div class="studio-brand-copy">
                    <strong>Bloxer Studio</strong>
                    <span>Panel developera</span>
                </div>
            </div>

            <nav class="studio-nav">
                <div class="studio-nav-item nav-item <?php echo $page === 'workspace' ? 'active' : ''; ?>" onclick="window.location.href='?page=workspace'">
                    <div class="studio-nav-icon">
                        <i class="fas fa-code"></i>
                    </div>
                    <div class="studio-nav-content">
                        <span class="studio-nav-title">Workspace</span>
                        <span class="studio-nav-desc">Code & develop</span>
                    </div>
                </div>
                <div class="studio-nav-item nav-item <?php echo $page === 'projects' ? 'active' : ''; ?>" onclick="window.location.href='?page=projects'">
                    <div class="studio-nav-icon">
                        <i class="fas fa-folder-open"></i>
                    </div>
                    <div class="studio-nav-content">
                        <span class="studio-nav-title">Projects</span>
                        <span class="studio-nav-desc">Manage apps</span>
                    </div>
                </div>
                <div class="studio-nav-item nav-item <?php echo $page === 'teams' ? 'active' : ''; ?>" onclick="window.location.href='?page=teams'">
                    <div class="studio-nav-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="studio-nav-content">
                        <span class="studio-nav-title">Teams</span>
                        <span class="studio-nav-desc">Collaborate</span>
                    </div>
                </div>
                <div class="studio-nav-item nav-item <?php echo $page === 'offers' ? 'active' : ''; ?>" onclick="window.location.href='?page=offers'">
                    <div class="studio-nav-icon">
                        <i class="fas fa-handshake"></i>
                    </div>
                    <div class="studio-nav-content">
                        <span class="studio-nav-title">Offers</span>
                        <span class="studio-nav-desc">Deals & sales</span>
                    </div>
                </div>
                <div class="studio-nav-item nav-item <?php echo $page === 'reviews' ? 'active' : ''; ?>" onclick="window.location.href='?page=reviews'">
                    <div class="studio-nav-icon">
                        <i class="fas fa-code-branch"></i>
                    </div>
                    <div class="studio-nav-content">
                        <span class="studio-nav-title">Code Review</span>
                        <span class="studio-nav-desc">Quality check</span>
                    </div>
                </div>
                <div class="studio-nav-item nav-item <?php echo $page === 'deploy' ? 'active' : ''; ?>" onclick="window.location.href='?page=deploy'">
                    <div class="studio-nav-icon">
                        <i class="fas fa-rocket"></i>
                    </div>
                    <div class="studio-nav-content">
                        <span class="studio-nav-title">Deploy Queue</span>
                        <span class="studio-nav-desc">Release management</span>
                    </div>
                </div>
                <div class="studio-nav-item nav-item <?php echo $page === 'publish' ? 'active' : ''; ?>" onclick="window.location.href='?page=publish'">
                    <div class="studio-nav-icon">
                        <i class="fas fa-rocket"></i>
                    </div>
                    <div class="studio-nav-content">
                        <span class="studio-nav-title">Publish</span>
                        <span class="studio-nav-desc">Release apps</span>
                    </div>
                </div>
                <div class="studio-nav-item nav-item <?php echo $page === 'version-control' ? 'active' : ''; ?>" onclick="window.location.href='?page=version-control'">
                    <div class="studio-nav-icon">
                        <i class="fas fa-code-branch"></i>
                    </div>
                    <div class="studio-nav-content">
                        <span class="studio-nav-title">Version Control</span>
                        <span class="studio-nav-desc">Track changes</span>
                    </div>
                </div>
                <div class="studio-nav-item nav-item <?php echo $page === 'analytics' ? 'active' : ''; ?>" onclick="window.location.href='?page=analytics'">
                    <div class="studio-nav-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="studio-nav-content">
                        <span class="studio-nav-title">Analytics</span>
                        <span class="studio-nav-desc">View stats</span>
                    </div>
                </div>
                <div class="studio-nav-item nav-item <?php echo $page === 'notifications' ? 'active' : ''; ?>" onclick="window.location.href='?page=notifications'">
                    <div class="studio-nav-icon">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div class="studio-nav-content">
                        <span class="studio-nav-title">Notifications</span>
                        <span class="studio-nav-desc">Stay updated</span>
                    </div>
                </div>
                <div class="studio-nav-item nav-item <?php echo $page === 'settings' ? 'active' : ''; ?>" onclick="window.location.href='?page=settings'">
                    <div class="studio-nav-icon">
                        <i class="fas fa-cog"></i>
                    </div>
                    <div class="studio-nav-content">
                        <span class="studio-nav-title">Settings</span>
                        <span class="studio-nav-desc">Preferences</span>
                    </div>
                </div>
            </nav>

            <div class="studio-nav-foot">
                <?php if (isset($_SESSION['form_errors'])): ?>
                    <div class="studio-flash studio-flash-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo htmlspecialchars($_SESSION['form_errors'][0]); ?>
                    </div>
                    <?php unset($_SESSION['form_errors']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="studio-flash studio-flash-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <div class="studio-nav-item nav-item" onclick="window.location.href='../auth/logout.php'">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </div>
            </div>
        </aside>

        <div class="studio-main-wrap">
            <header class="studio-header top-bar">
                <div class="studio-header-main">
                    <button class="btn btn-small studio-menu-toggle" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div>
                        <h1><?php echo htmlspecialchars($pageTitles[$page] ?? 'Dashboard'); ?></h1>
                    </div>
                </div>

                <div class="studio-header-meta">
                    <div class="studio-status" id="save-status">Ready</div>
                    <div class="studio-user-chip">
                        <span class="studio-user-avatar"><?php echo strtoupper(substr($user['username'], 0, 1)); ?></span>
                        <span><?php echo htmlspecialchars($user['username']); ?></span>
                    </div>
                </div>
            </header>

            <main class="studio-main workspace-area">
                <?php if ($page === 'workspace' && $current_project): ?>
                    <section class="studio-welcome-card">
                        <div class="studio-section-head">
                            <div>
                                <h2><?php echo htmlspecialchars($current_project['name']); ?></h2>
                                <p><?php echo htmlspecialchars($current_project['description'] ?? 'No description for this project yet.'); ?></p>
                            </div>
                            <div class="studio-inline-actions">
                                <a href="../marketplace/publish.php" class="btn btn-primary">
                                    <i class="fas fa-rocket"></i>
                                    Publish
                                </a>
                                <a href="../projects/projects.php" class="btn btn-secondary">
                                    <i class="fas fa-folder-open"></i>
                                    Projects
                                </a>
                            </div>
                        </div>

                        <div class="studio-surface" style="margin-top: 18px;">
                            <div class="studio-project-head">
                                <div class="studio-icon-box">
                                    <i class="fas fa-cube"></i>
                                </div>
                                <span class="studio-tag"><?php echo htmlspecialchars($current_project['framework'] ?? 'vanilla'); ?></span>
                            </div>
                            <p class="studio-card-copy">
                                Tu możesz rozwijać projekt, potem go wypchnąć do publish center albo przejść do pełnej listy projektów.
                            </p>
                            <div class="studio-meta-row">
                                <div class="studio-inline-meta">
                                    <span><i class="fas fa-clock"></i> Updated <?php echo date('M j, Y', strtotime($current_project['updated_at'])); ?></span>
                                    <span><i class="fas fa-hashtag"></i> ID <?php echo (int) $current_project['id']; ?></span>
                                </div>
                            </div>
                        </div>
                    </section>

                <?php elseif ($page === 'projects'): ?>
                    <section class="studio-section">
                        <div class="studio-section-head">
                            <div>
                                <h2>Your Projects</h2>
                                <p>Manage your project library, files, and publishing readiness.</p>
                            </div>
                            <button class="btn btn-primary btn-small" onclick="showCreateProjectModal()">
                                <i class="fas fa-plus"></i> New Project
                            </button>
                        </div>

                        <div class="studio-stats-row">
                            <div class="studio-stat-card-large">
                                <div class="studio-stat-icon">
                                    <i class="fas fa-folder"></i>
                                </div>
                                <div class="studio-stat-content">
                                    <strong><?php echo count($projects); ?></strong>
                                    <span>Total Projects</span>
                                </div>
                            </div>
                            <div class="studio-stat-card-large">
                                <div class="studio-stat-icon">
                                    <i class="fas fa-file-code"></i>
                                </div>
                                <div class="studio-stat-content">
                                    <strong><?php echo $total_files; ?></strong>
                                    <span>Total Files</span>
                                </div>
                            </div>
                            <div class="studio-stat-card-large">
                                <div class="studio-stat-icon">
                                    <i class="fas fa-rocket"></i>
                                </div>
                                <div class="studio-stat-content">
                                    <strong><?php echo $published_count; ?></strong>
                                    <span>Published Products</span>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($projects)): ?>
                            <div class="studio-projects-grid">
                                <?php foreach ($projects as $project): ?>
                                    <article class="studio-project-card" onclick="navigate('workspace', <?php echo $project['id']; ?>)">
                                        <div class="studio-project-header">
                                            <div class="studio-project-icon">
                                                <?php 
                                                // Determine project type based on framework or name
                                                $project_type = 'Web App';
                                                $icon = 'fa-globe';
                                                if (stripos($project['framework'] ?? '', 'react') !== false) {
                                                    $project_type = 'React App';
                                                    $icon = 'fa-react';
                                                } elseif (stripos($project['framework'] ?? '', 'vue') !== false) {
                                                    $project_type = 'Vue App';
                                                    $icon = 'fa-vuejs';
                                                } elseif (stripos($project['framework'] ?? '', 'angular') !== false) {
                                                    $project_type = 'Angular App';
                                                    $icon = 'fa-angular';
                                                } elseif (stripos($project['name'] ?? '', 'game') !== false) {
                                                    $project_type = 'Game';
                                                    $icon = 'fa-gamepad';
                                                } elseif (stripos($project['name'] ?? '', 'template') !== false) {
                                                    $project_type = 'Template';
                                                    $icon = 'fa-layer-group';
                                                } else {
                                                    $project_type = 'Web App';
                                                    $icon = 'fa-code';
                                                }
                                                ?>
                                                <i class="fas <?php echo $icon; ?>"></i>
                                            </div>
                                            <div class="studio-project-info">
                                                <h3 class="studio-project-title"><?php echo htmlspecialchars($project['name']); ?></h3>
                                                <div class="studio-project-meta">
                                                    <span class="studio-project-files">
                                                        <i class="fas fa-file"></i>
                                                        <?php echo (int) $project['file_count']; ?> files
                                                    </span>
                                                    <span class="studio-project-date">
                                                        <i class="fas fa-calendar"></i>
                                                        <?php echo date('M j, Y', strtotime($project['updated_at'])); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="studio-project-actions">
                                                <span class="studio-project-type"><?php echo $project_type; ?></span>
                                                <span class="studio-project-status">Active</span>
                                                <button class="studio-project-open">
                                                    <i class="fas fa-arrow-right"></i>
                                                    Open
                                                </button>
                                            </div>
                                        </div>
                                        <div class="studio-project-description">
                                            <p><?php echo htmlspecialchars($project['description'] ?: 'No description provided for this project.'); ?></p>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="studio-empty">
                                <div class="studio-empty-icon">
                                    <i class="fas fa-folder-open"></i>
                                </div>
                                <h3>No projects yet</h3>
                                <p>Create your first project and build your own application in the new studio.</p>
                                <button class="btn btn-primary" onclick="showCreateProjectModal()">
                                    <i class="fas fa-plus"></i> Create Your First Project
                                </button>
                            </div>
                        <?php endif; ?>
                    </section>

                <?php elseif ($page === 'offers'): ?>
                    <section class="studio-section">
                        <div class="studio-section-head">
                            <div>
                                <h2>Incoming offers</h2>
                                <p>Kontroluj propozycje zakupu i prowadź rozmowy z kupującymi w jednym widoku.</p>
                            </div>
                        </div>

                        <?php if (empty($offers)): ?>
                            <div class="studio-empty">
                                <i class="fas fa-comments"></i>
                                <h3>Brak ofert</h3>
                                <p>Gdy użytkownicy zaczną wysyłać oferty, pojawią się tutaj.</p>
                            </div>
                        <?php else: ?>
                            <div class="studio-card-grid" style="grid-template-columns: 1fr;">
                                <?php foreach ($offers as $offer): ?>
                                    <article class="studio-offer-card project-card">
                                        <div class="studio-offer-head">
                                            <div>
                                                <h3 class="studio-card-title"><?php echo htmlspecialchars($offer['app_title']); ?></h3>
                                                <p class="studio-card-copy" style="margin-bottom: 0;">
                                                    Buyer: <?php echo htmlspecialchars($offer['buyer_name']); ?> · <?php echo htmlspecialchars($offer['buyer_email']); ?>
                                                </p>
                                            </div>
                                            <span class="studio-pill-neutral"><?php echo htmlspecialchars(ucfirst($offer['status'])); ?></span>
                                        </div>

                                        <div class="studio-inline-meta" style="margin-bottom: 16px;">
                                            <span><i class="fas fa-wallet"></i> $<?php echo number_format($offer['amount'], 2); ?></span>
                                            <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($offer['phone_number'] ?: 'Not provided'); ?></span>
                                        </div>

                                        <?php if (!empty($offer['transfer_notes'])): ?>
                                            <div class="studio-surface" style="padding: 18px; border-radius: 22px; margin-bottom: 16px;">
                                                <strong>Transfer note</strong>
                                                <p class="studio-card-copy" style="margin-top: 8px; margin-bottom: 0;"><?php echo htmlspecialchars($offer['transfer_notes']); ?></p>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (($offer['status'] ?? '') === 'pending'): ?>
                                            <form method="POST" class="studio-form-stack" style="margin-bottom: 10px;">
                                                <input type="hidden" name="action" value="respond_offer">
                                                <input type="hidden" name="offer_id" value="<?php echo $offer['id']; ?>">
                                                <textarea name="response_message" rows="3" class="studio-textarea" placeholder="Dodaj odpowiedź albo notatkę do handoffu"></textarea>
                                                <div class="studio-modal-actions" style="margin-top: 0; justify-content: flex-start;">
                                                    <button class="btn btn-small btn-danger" type="submit" name="decision" value="decline">Decline</button>
                                                    <button class="btn btn-small btn-primary" type="submit" name="decision" value="accept">Accept & Transfer</button>
                                                </div>
                                            </form>
                                        <?php endif; ?>

                                        <?php if (!empty($offer['messages'])): ?>
                                            <div class="studio-message-thread">
                                                <?php foreach ($offer['messages'] as $message): ?>
                                                    <div class="studio-message">
                                                        <div class="studio-message-meta">
                                                            <?php echo htmlspecialchars($message['username']); ?> · <?php echo date('M j, Y H:i', strtotime($message['created_at'])); ?>
                                                        </div>
                                                        <div><?php echo nl2br(htmlspecialchars($message['message'])); ?></div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (($offer['status'] ?? '') === 'accepted'): ?>
                                        <div class="studio-chat-actions" style="margin-top: 16px; padding: 12px; background: rgba(16, 185, 129, 0.1); border-radius: 8px; border: 1px solid rgba(16, 185, 129, 0.3);">
                                            <p style="margin: 0 0 8px 0; color: #10b981; font-weight: 600; font-size: 0.9rem;">
                                                <i class="fas fa-check-circle"></i> Deal Accepted - Start Chat
                                            </p>
                                            <a href="../chat2/chat.php?offer_id=<?php echo $offer['id']; ?>" target="_blank" class="btn btn-primary">
                                                <i class="fas fa-comments"></i> Chat with Buyer
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                <?php elseif ($page === 'publish'): ?>
                    <section class="studio-section">
                        <div class="studio-section-head">
                            <div>
                                <h2>Publish center</h2>
                                <p>Wybieraj projekty gotowe do pokazania użytkownikom i utrzymuj katalog swoich produktów.</p>
                            </div>
                        </div>

                        <?php if (!empty($publishable_projects)): ?>
                            <div class="studio-card-grid">
                                <?php foreach ($publishable_projects as $project): ?>
                                    <article class="studio-publish-card project-card">
                                        <div class="studio-publish-head">
                                            <div class="studio-icon-box">
                                                <i class="fas fa-rocket"></i>
                                            </div>
                                            <?php if ($project['publish_status'] === 'published'): ?>
                                                <span class="studio-pill-success">Published</span>
                                            <?php else: ?>
                                                <span class="studio-pill-neutral">Draft</span>
                                            <?php endif; ?>
                                        </div>

                                        <h3 class="studio-card-title"><?php echo htmlspecialchars($project['name']); ?></h3>
                                        <p class="studio-card-copy"><?php echo htmlspecialchars($project['description'] ?? 'No description'); ?></p>

                                        <div class="studio-meta-row">
                                            <div class="studio-inline-meta">
                                                <span><i class="fas fa-code"></i> <?php echo htmlspecialchars($project['framework']); ?></span>
                                                <span><i class="fas fa-clock"></i> <?php echo date('M j, Y', strtotime($project['updated_at'])); ?></span>
                                            </div>
                                        </div>

                                        <div class="studio-inline-actions" style="margin-top: 16px;">
                                            <?php if ($project['publish_status'] === 'published'): ?>
                                                <button class="btn btn-small btn-secondary" onclick="editApp(<?php echo (int) $project['app_id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                    Edit
                                                </button>
                                                <button class="btn btn-small btn-primary" onclick="viewApp(<?php echo (int) $project['app_id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                    View
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-small btn-primary" onclick="showPublishModal(<?php echo (int) $project['id']; ?>)">
                                                    <i class="fas fa-upload"></i>
                                                    Publish
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="studio-empty">
                                <i class="fas fa-rocket"></i>
                                <h3>Brak projektów do publikacji</h3>
                                <p>Stwórz projekt albo dokończ istniejący, a potem wróć tutaj.</p>
                            </div>
                        <?php endif; ?>
                    </section>

                <?php elseif ($page === 'version-control'): ?>
                    <section class="studio-section">
                        <div class="studio-section-head">
                            <div>
                                <h2>Version Control</h2>
                                <p>Track changes, manage versions, and maintain your project history.</p>
                            </div>
                        </div>
                        <div class="studio-empty">
                            <i class="fas fa-code-branch"></i>
                            <h3>Version Control System</h3>
                            <p>Manage your project versions and track changes over time.</p>
                            <a href="../api/version-control.php" class="btn btn-primary">
                                <i class="fas fa-external-link-alt"></i> Open Version Control
                            </a>
                        </div>
                    </section>

                <?php elseif ($page === 'analytics'): ?>
                    <section class="studio-section">
                        <div class="studio-section-head">
                            <div>
                                <h2>Analytics</h2>
                                <p>View detailed statistics about your apps and user engagement.</p>
                            </div>
                        </div>
                        <div class="studio-empty">
                            <i class="fas fa-chart-line"></i>
                            <h3>Analytics Dashboard</h3>
                            <p>Track downloads, user engagement, and revenue metrics.</p>
                            <a href="../tools/overview.php" class="btn btn-primary">
                                <i class="fas fa-external-link-alt"></i> Open Analytics
                            </a>
                        </div>
                    </section>

                <?php elseif ($page === 'notifications'): ?>
                    <section class="studio-section">
                        <div class="studio-section-head">
                            <div>
                                <h2>Notifications</h2>
                                <p>Stay updated with your latest notifications and messages.</p>
                            </div>
                        </div>
                        <div class="studio-empty">
                            <i class="fas fa-bell"></i>
                            <h3>Notification Center</h3>
                            <p>Manage your notifications and stay connected with your users.</p>
                            <a href="../user/notifications.php" class="btn btn-primary">
                                <i class="fas fa-external-link-alt"></i> Open Notifications
                            </a>
                        </div>
                    </section>

                <?php elseif ($page === 'settings'): ?>
                    <section class="studio-section">
                        <div class="studio-section-head">
                            <div>
                                <h2>Settings</h2>
                                <p>Manage your account settings and preferences.</p>
                            </div>
                        </div>
                        <div class="studio-empty">
                            <i class="fas fa-cog"></i>
                            <h3>Account Settings</h3>
                            <p>Update your profile, security settings, and preferences.</p>
                            <a href="../user/profile.php" class="btn btn-primary">
                                <i class="fas fa-external-link-alt"></i> Open Settings
                            </a>
                        </div>
                    </section>

                <?php else: ?>
                    <section class="studio-welcome-card">
                        <div class="studio-section-head">
                            <div>
                                <h2>Welcome to Bloxer Studio</h2>
                                <p>Wybierz sekcję z lewego menu i przejdź do działania.</p>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <div class="studio-modal modal" id="create-project-modal">
        <div class="studio-modal-card modal-content">
            <h3>Create new project</h3>
            <form id="create-project-form" method="POST" action="" enctype="multipart/form-data" class="studio-form-stack" onsubmit="return validateFolderUpload()">
                <input type="hidden" name="action" value="create_project">

                <div class="studio-two-col">
                    <div class="form-group">
                        <label for="project_name">Project name</label>
                        <input type="text" id="project_name" name="project_name" required>
                    </div>
                    <div class="form-group">
                        <label for="project_type">Project type</label>
                        <select id="project_type" name="project_type" onchange="toggleProjectType()">
                            <option value="blank">Blank project</option>
                            <option value="upload">Upload files</option>
                            <option value="folder">Upload folder</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="project_description">Description</label>
                    <textarea id="project_description" name="project_description" rows="4"></textarea>
                </div>

                <div id="upload-section" style="display: none;" class="studio-form-stack">
                    <div class="form-group">
                        <label for="project_files">Upload files</label>
                        <input type="file" id="project_files" name="project_files[]" multiple>
                        <div class="studio-field-note">Możesz wrzucić zestaw plików projektu (max 55 plików) i zbudować workspace od razu.</div>
                    </div>
                </div>

                <div id="folder-section" style="display: none;" class="studio-form-stack">
                    <div class="form-group">
                        <label for="project_folder">Upload project folder</label>
                        <input type="file" id="project_folder" name="project_folder[]" webkitdirectory directory multiple>
                        <div class="studio-field-note">Wrzuć cały katalog projektu wraz z podfolderami (max 55 plików).</div>
                    </div>
                </div>

                <div class="studio-modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create project</button>
                </div>
            </form>
        </div>
    </div>

    <div class="studio-modal" id="publish-modal">
        <div class="studio-modal-card">
            <h3>Publish app to marketplace</h3>
            <form id="publish-form" method="POST" action="dashboard.php" class="studio-form-stack">
                <input type="hidden" name="action" value="publish_app">
                <input type="hidden" id="publish-project-id" name="project_id">

                <div class="form-group">
                    <label for="app-title">App title</label>
                    <input type="text" id="app-title" name="title" required>
                </div>

                <div class="form-group">
                    <label for="app-short-description">Short description</label>
                    <input type="text" id="app-short-description" name="short_description" maxlength="500" required>
                </div>

                <div class="form-group">
                    <label for="app-description">Full description</label>
                    <textarea id="app-description" name="description" rows="4" required></textarea>
                </div>

                <div class="studio-two-col">
                    <div class="form-group">
                        <label for="app-category">Category</label>
                        <select id="app-category" name="category" required>
                            <option value="">Select category</option>
                            <option value="games">Games</option>
                            <option value="productivity">Productivity</option>
                            <option value="social">Social</option>
                            <option value="entertainment">Entertainment</option>
                            <option value="education">Education</option>
                            <option value="utilities">Utilities</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="app-tags">Tags</label>
                        <input type="text" id="app-tags" name="tags" placeholder="dashboard, ai, utility">
                    </div>
                </div>

                <input type="hidden" name="is_free" value="1">
                <input type="hidden" name="price" value="0">

                <div class="studio-modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closePublishModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Publish app</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Chat Modal -->
    <div class="studio-modal" id="chat-modal">
        <div class="studio-modal-card" style="max-width: 600px;">
            <div class="studio-modal-header">
                <h3 id="chat-modal-title">Chat with Buyer</h3>
                <button class="studio-modal-close" onclick="closeChatModal()">&times;</button>
            </div>
            <div class="chat-container" style="height: 400px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin-bottom: 16px; background: #f9fafb;">
                <div id="chat-messages"></div>
            </div>
            <form id="chat-form" class="studio-form-stack">
                <input type="hidden" id="chat-offer-id" name="offer_id">
                <div class="form-group">
                    <textarea id="chat-message" name="message" rows="3" placeholder="Type your message..." required></textarea>
                </div>
                <div class="studio-modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeChatModal()">Close</button>
                    <button type="submit" class="btn btn-primary">Send Message</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('collapsed');
        }

        function navigate(page, projectId = null) {
            const url = new URL(window.location);
            url.searchParams.set('page', page);
            if (projectId) {
                url.searchParams.set('project_id', projectId);
            } else {
                url.searchParams.delete('project_id');
            }
            window.location.href = url.toString();
        }

        function showCreateProjectModal() {
            const modal = document.getElementById('create-project-modal');
            if (modal) {
                modal.style.display = 'flex';
                modal.classList.add('show');
                setTimeout(() => {
                    const firstInput = modal.querySelector('input');
                    if (firstInput) firstInput.focus();
                }, 100);
            }
        }

        function closeModal() {
            const modal = document.getElementById('create-project-modal');
            if (modal) {
                modal.style.display = 'none';
                modal.classList.remove('show');
            }
        }

        function toggleProjectType() {
            const projectType = document.getElementById('project_type').value;
            const uploadSection = document.getElementById('upload-section');
            const folderSection = document.getElementById('folder-section');

            if (projectType === 'upload') {
                uploadSection.style.display = 'block';
                folderSection.style.display = 'none';
            } else if (projectType === 'folder') {
                uploadSection.style.display = 'none';
                folderSection.style.display = 'block';
            } else {
                uploadSection.style.display = 'none';
                folderSection.style.display = 'none';
            }
        }

        function validateFolderUpload() {
            const folderInput = document.getElementById('project_folder');
            const projectType = document.getElementById('project_type').value;

            if (projectType === 'folder') {
                if (!folderInput.files || folderInput.files.length === 0) {
                    alert('Please select a folder to upload.');
                    return false;
                }

                if (folderInput.files.length > 55) {
                    alert('Too many files. Maximum 55 files allowed per folder.');
                    return false;
                }
            }

            return true;
        }

        function validatePublishForm() {
            const title = document.getElementById('app-title').value.trim();
            const shortDesc = document.getElementById('app-short-description').value.trim();
            const description = document.getElementById('app-description').value.trim();
            const category = document.getElementById('app-category').value;

            if (!title || title.length < 3) {
                alert('App title must be at least 3 characters long.');
                return false;
            }

            if (!shortDesc || shortDesc.length > 500) {
                alert('Short description is required and must not exceed 500 characters.');
                return false;
            }

            if (!description || description.length < 10) {
                alert('Full description must be at least 10 characters long.');
                return false;
            }

            if (!category) {
                alert('Please select a category.');
                return false;
            }

            return true;
        }

        function showPublishModal(projectId) {
            const modal = document.getElementById('publish-modal');
            const projectIdInput = document.getElementById('publish-project-id');

            if (modal && projectIdInput) {
                projectIdInput.value = projectId;
                modal.style.display = 'flex';
                modal.classList.add('show');

                setTimeout(() => {
                    const titleInput = document.getElementById('app-title');
                    if (titleInput) titleInput.focus();
                }, 100);
            }
        }

        function closePublishModal() {
            const modal = document.getElementById('publish-modal');
            if (modal) {
                modal.style.display = 'none';
                modal.classList.remove('show');
            }
            const form = document.getElementById('publish-form');
            if (form) {
                form.reset();
            }
        }

        function editApp(appId) {
            window.location.href = `../apps/edit_app.php?id=${appId}`;
        }

        function viewApp(appId) {
            window.location.href = `../apps/app.php?id=${appId}`;
        }

        function deleteProject(event, projectId) {
            event.stopPropagation();

            if (confirm('Are you sure you want to delete this project? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_project">
                    <input type="hidden" name="project_id" value="${projectId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
                closePublishModal();
            }
        });

        window.onclick = function(event) {
            const createModal = document.getElementById('create-project-modal');
            const publishModal = document.getElementById('publish-modal');

            if (event.target === createModal) {
                closeModal();
            }
            if (event.target === publishModal) {
                closePublishModal();
            }
        };

        const publishForm = document.getElementById('publish-form');
        if (publishForm) {
            publishForm.addEventListener('submit', function(e) {
                if (!validatePublishForm()) {
                    e.preventDefault();
                }
            });
        }

        // Chat functions
        function openChatModal(offerId, buyerName) {
            const modal = document.getElementById('chat-modal');
            const title = document.getElementById('chat-modal-title');
            const offerIdInput = document.getElementById('chat-offer-id');
            const messagesContainer = document.getElementById('chat-messages');

            if (modal && title && offerIdInput && messagesContainer) {
                title.textContent = `Chat with ${buyerName}`;
                offerIdInput.value = offerId;
                
                // Load existing messages
                loadChatMessages(offerId);
                
                modal.style.display = 'flex';
                modal.classList.add('show');
                
                setTimeout(() => {
                    const messageInput = document.getElementById('chat-message');
                    if (messageInput) messageInput.focus();
                }, 100);
            }
        }

        function closeChatModal() {
            const modal = document.getElementById('chat-modal');
            if (modal) {
                modal.style.display = 'none';
                modal.classList.remove('show');
            }
            const form = document.getElementById('chat-form');
            if (form) {
                form.reset();
            }
        }

        function loadChatMessages(offerId) {
            const messagesContainer = document.getElementById('chat-messages');
            if (!messagesContainer) return;

            // Fetch messages via AJAX
            fetch(`dashboard.php?action=get_chat_messages&offer_id=${offerId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayChatMessages(data.messages);
                    }
                })
                .catch(error => {
                    console.error('Error loading chat messages:', error);
                });
        }

        function displayChatMessages(messages) {
            const messagesContainer = document.getElementById('chat-messages');
            if (!messagesContainer) return;

            messagesContainer.innerHTML = '';
            
            if (messages.length === 0) {
                messagesContainer.innerHTML = '<div style="text-align: center; color: #6b7280; padding: 20px;">No messages yet. Start the conversation!</div>';
                return;
            }

            messages.forEach(message => {
                const messageDiv = document.createElement('div');
                messageDiv.className = 'chat-message';
                messageDiv.style.cssText = `
                    margin-bottom: 12px;
                    padding: 8px 12px;
                    border-radius: 8px;
                    background: ${message.is_sender ? '#6366f1' : '#f3f4f6'};
                    color: ${message.is_sender ? 'white' : '#1f2937'};
                    align-self: ${message.is_sender ? 'flex-end' : 'flex-start'};
                    max-width: 80%;
                `;
                
                messageDiv.innerHTML = `
                    <div style="font-size: 0.75rem; opacity: 0.7; margin-bottom: 4px;">
                        ${message.username} · ${new Date(message.created_at).toLocaleString()}
                    </div>
                    <div>${message.message}</div>
                `;
                
                messagesContainer.appendChild(messageDiv);
            });

            // Scroll to bottom
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        // Handle chat form submission
        const chatForm = document.getElementById('chat-form');
        if (chatForm) {
            chatForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(chatForm);
                formData.append('action', 'send_chat_message');
                
                fetch('dashboard.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Clear message input
                        document.getElementById('chat-message').value = '';
                        // Reload messages
                        loadChatMessages(formData.get('offer_id'));
                    } else {
                        alert('Error sending message: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error sending message:', error);
                    alert('Error sending message. Please try again.');
                });
            });
        }
    </script>
    <script src="../assets/js/beta-banner.js"></script>
</body>
</html>