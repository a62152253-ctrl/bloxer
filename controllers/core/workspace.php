<?php
require_once __DIR__ . '/../../bootstrap.php';

$auth = new AuthCore();
if (!$auth->isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit;
}

$user = $auth->getCurrentUser();
$conn = $auth->getConnection();

// Get current project
$project_id = $_GET['id'] ?? null;
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

// Get all projects for navigation
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
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workspace - Bloxer</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <style>
        :root {
            --bg-main: #F7F7F8;
            --bg-card: #FFFFFF;
            --text-primary: #1C1C1E;
            --text-secondary: #6E6E73;
            --accent: #007AFF;
            --accent-hover: #0056CC;
            --shadow-subtle: rgba(0, 0, 0, 0.04);
            --shadow-medium: rgba(0, 0, 0, 0.08);
            --border-light: #E5E5EA;
            --border-lighter: #F2F2F7;
            --radius-medium: 14px;
            --radius-large: 18px;
            --radius-small: 8px;
        }

        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        body {
            background: var(--bg-main);
            margin: 0;
            padding: 0;
            color: var(--text-primary);
        }

        .workspace-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .workspace-sidebar {
            width: 280px;
            background: var(--bg-card);
            border-right: 1px solid var(--border-light);
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
        }

        .workspace-sidebar-header {
            padding: 24px;
            border-bottom: 1px solid var(--border-light);
        }

        .workspace-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--text-primary);
            font-weight: 600;
            font-size: 18px;
        }

        .workspace-brand i {
            font-size: 20px;
            color: var(--accent);
        }

        .workspace-nav {
            flex: 1;
            padding: 8px;
        }

        .workspace-nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: var(--radius-medium);
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
            margin-bottom: 4px;
        }

        .workspace-nav-item:hover {
            background: var(--border-lighter);
            color: var(--text-primary);
        }

        .workspace-nav-item.active {
            background: var(--accent);
            color: white;
        }

        .workspace-nav-item i {
            width: 16px;
            text-align: center;
        }

        /* Main Content */
        .workspace-main {
            flex: 1;
            margin-left: 280px;
            padding: 32px;
            overflow-y: auto;
        }

        .workspace-header {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-large);
            padding: 32px;
            margin-bottom: 32px;
            box-shadow: 0 2px 12px var(--shadow-subtle);
        }

        .workspace-header-content {
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .workspace-icon {
            width: 64px;
            height: 64px;
            border-radius: var(--radius-large);
            background: var(--accent);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }

        .workspace-title h1 {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0 0 8px 0;
        }

        .workspace-title p {
            font-size: 16px;
            color: var(--text-secondary);
            margin: 0;
        }

        /* Project Selector */
        .project-selector {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-large);
            padding: 24px;
            margin-bottom: 32px;
            box-shadow: 0 2px 12px var(--shadow-subtle);
        }

        .project-selector h3 {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0 0 16px 0;
        }

        .project-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
        }

        .project-card {
            background: var(--border-lighter);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-medium);
            padding: 20px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .project-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px var(--shadow-medium);
            background: var(--bg-card);
        }

        .project-card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .project-card-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-small);
            background: var(--accent);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .project-card-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }

        .project-card-meta {
            font-size: 12px;
            color: var(--text-secondary);
            margin: 0;
        }

        /* Editor Area */
        .editor-area {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-large);
            overflow: hidden;
            box-shadow: 0 2px 12px var(--shadow-subtle);
            min-height: 600px;
        }

        .editor-tabs {
            display: flex;
            background: var(--border-lighter);
            border-bottom: 1px solid var(--border-light);
        }

        .editor-tab {
            padding: 12px 20px;
            border: none;
            background: transparent;
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            border-bottom: 2px solid transparent;
        }

        .editor-tab:hover {
            color: var(--text-primary);
        }

        .editor-tab.active {
            color: var(--accent);
            border-bottom-color: var(--accent);
        }

        .editor-content {
            padding: 24px;
            min-height: 500px;
        }

        .empty-workspace {
            text-align: center;
            padding: 80px 20px;
            color: var(--text-secondary);
        }

        .empty-workspace i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-workspace h3 {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0 0 8px 0;
        }

        .empty-workspace p {
            font-size: 14px;
            margin: 0 0 20px 0;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: var(--radius-medium);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
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

        @media (max-width: 768px) {
            .workspace-sidebar {
                width: 100%;
                position: relative;
                height: auto;
            }

            .workspace-main {
                margin-left: 0;
                padding: 20px;
            }

            .workspace-header-content {
                flex-direction: column;
                text-align: center;
                gap: 16px;
            }

            .project-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="workspace-container">
        <!-- Sidebar -->
        <aside class="workspace-sidebar">
            <div class="workspace-sidebar-header">
                <a href="../core/dashboard.php" class="workspace-brand">
                    <span>[DEV]</span>
                    <span>Bloxer Studio</span>
                </a>
            </div>
            
            <nav class="workspace-nav">
                <a href="workspace.php" class="workspace-nav-item active">
                    <span>[CODE]</span>
                    <span>Workspace</span>
                </a>
                <a href="projects.php" class="workspace-nav-item">
                    <span>[FOLDER]</span>
                    <span>Projects</span>
                </a>
                <a href="offers.php" class="workspace-nav-item">
                    <span>[DEAL]</span>
                    <span>Offers</span>
                </a>
                <a href="publish.php" class="workspace-nav-item">
                    <span>[ROCKET]</span>
                    <span>Publish</span>
                </a>
                <a href="version-control.php" class="workspace-nav-item">
                    <span>[GIT]</span>
                    <span>Version Control</span>
                </a>
                <a href="analytics.php" class="workspace-nav-item">
                    <span>[CHART]</span>
                    <span>Analytics</span>
                </a>
                <a href="notifications.php" class="workspace-nav-item">
                    <span>[BELL]</span>
                    <span>Notifications</span>
                </a>
                <a href="settings.php" class="workspace-nav-item">
                    <span>[SET]</span>
                    <span>Settings</span>
                </a>
                <a href="../controllers/auth/logout.php" class="workspace-nav-item">
                    <span>[OUT]</span>
                    <span>Logout</span>
                </a>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="workspace-main">
            <div class="workspace-header">
                <div class="workspace-header-content">
                    <div class="workspace-icon">
                        <span>[CODE]</span>
                    </div>
                    <div class="workspace-title">
                        <h1>Workspace</h1>
                        <p>Code, develop, and build your applications</p>
                    </div>
                </div>
            </div>

            <?php if ($current_project): ?>
                <!-- Current Project Editor -->
                <div class="editor-area">
                    <div class="editor-tabs">
                        <div class="editor-tab active">
                            <span>[HTML]</span>
                            index.html
                        </div>
                        <div class="editor-tab">
                            <span>[CSS]</span>
                            style.css
                        </div>
                        <div class="editor-tab">
                            <span>[JS]</span>
                            script.js
                        </div>
                    </div>
                    <div class="editor-content">
                        <div class="empty-workspace">
                            <span>[CODE]</span>
                            <h3><?php echo htmlspecialchars($current_project['name']); ?></h3>
                            <p><?php echo htmlspecialchars($current_project['description'] ?? 'No description'); ?></p>
                            <p>Editor will be loaded here...</p>
                            <a href="editor.php?id=<?php echo $current_project['id']; ?>" class="btn btn-primary">
                                <span>[EDIT]</span>
                                Open in Editor
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Project Selector -->
                <div class="project-selector">
                    <h3>Select a Project</h3>
                    <div class="project-grid">
                        <?php foreach ($projects as $project): ?>
                            <div class="project-card" onclick="window.location.href='workspace.php?id=<?php echo $project['id']; ?>'">
                                <div class="project-card-header">
                                    <div class="project-card-icon">
                                        <span>[FOLDER]</span>
                                    </div>
                                    <div class="project-card-title">
                                        <?php echo htmlspecialchars($project['name']); ?>
                                    </div>
                                </div>
                                <div class="project-card-meta">
                                    <?php echo $project['file_count']; ?> files • 
                                    Updated <?php echo date('M j, Y', strtotime($project['updated_at'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (empty($projects)): ?>
                        <div class="empty-workspace">
                            <span>[FOLDER]</span>
                            <h3>No Projects Yet</h3>
                            <p>Create your first project to start building</p>
                            <a href="projects.php" class="btn btn-primary">
                                <span>[PLUS]</span>
                                Create Project
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
