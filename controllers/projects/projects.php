<?php
require_once __DIR__ . '/../../bootstrap.php';

$auth = new AuthCore();

if (!$auth->isLoggedIn()) {
    SecurityUtils::safeRedirect('../auth/login.php', 302, 'User not logged in');
}

if (!$auth->isDeveloper()) {
    SecurityUtils::safeRedirect('../marketplace/marketplace.php', 302, 'Developer access required');
}

$user = $auth->getCurrentUser();
$page = $_GET['page'] ?? 'projects';
$project_id = $_GET['project_id'] ?? null;
$errors = [];

// Handle project actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_project') {
        $project_name = trim($_POST['project_name'] ?? '');
        $project_description = trim($_POST['project_description'] ?? '');
        $project_type = $_POST['project_type'] ?? 'blank';
        
        if (empty($project_name)) {
            $errors[] = 'Project name is required';
        } else {
            $conn = $auth->getConnection();
            
            $stmt = $conn->prepare("INSERT INTO projects (user_id, name, description, framework) VALUES (?, ?, ?, ?)");
            $framework = $project_type === 'blank' ? 'vanilla' : $project_type;
            $stmt->bind_param("isss", $user['id'], $project_name, $project_description, $framework);
            
            if ($stmt->execute()) {
                $new_project_id = $stmt->insert_id;
                
                // Create default files
                $default_files = [
                    'index.html' => "<!DOCTYPE html>\n<html>\n<head>\n    <title>$project_name</title>\n    <link rel=\"stylesheet\" href=\"style.css\">\n</head>\n<body>\n    <h1>Welcome to $project_name</h1>\n    <script src=\"script.js\"></script>\n</body>\n</html>",
                    'style.css' => "body {\n    font-family: Arial, sans-serif;\n    margin: 0;\n    padding: 20px;\n    background: #f5f5f5;\n}\n\nh1 {\n    color: #333;\n    text-align: center;\n}",
                    'script.js' => "// Welcome to your new project!\n// Add your JavaScript code here"
                ];
                
                foreach ($default_files as $filename => $content) {
                    $stmt = $conn->prepare("INSERT INTO project_files (project_id, filename, content, file_type) VALUES (?, ?, ?, ?)");
                    $file_type = pathinfo($filename, PATHINFO_EXTENSION);
                    $stmt->bind_param("isss", $new_project_id, $filename, $content, $file_type);
                    $stmt->execute();
                }
                
                SecurityUtils::safeRedirect("projects.php?page=workspace&project_id=$new_project_id", 302, 'Project created successfully');
            } else {
                $errors[] = 'Failed to create project';
            }
        }
    }
    
    if ($action === 'delete_project' && $project_id) {
        $conn = $auth->getConnection();
        
        // Verify ownership
        $stmt = $conn->prepare("SELECT id FROM projects WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $project_id, $user['id']);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            // Delete project and files
            $stmt = $conn->prepare("DELETE FROM project_files WHERE project_id = ?");
            $stmt->bind_param("i", $project_id);
            $stmt->execute();
            
            $stmt = $conn->prepare("DELETE FROM projects WHERE id = ?");
            $stmt->bind_param("i", $project_id);
            $stmt->execute();
            
            SecurityUtils::safeRedirect('projects.php?page=projects', 302, 'Project deleted');
        }
    }
}

// Get projects
$conn = $auth->getConnection();
$projects = [];
$projects_stmt = $conn->prepare("
    SELECT p.*, 
           (SELECT COUNT(*) FROM project_files WHERE project_id = p.id) as file_count,
           (SELECT COUNT(*) FROM apps WHERE project_id = p.id AND status = 'published') as published_count
    FROM projects p 
    WHERE p.user_id = ? 
    ORDER BY p.updated_at DESC
");
if ($projects_stmt) {
    $projects_stmt->bind_param("i", $user['id']);
    $projects_stmt->execute();
    $projects = $projects_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$total_files = array_sum(array_column($projects, 'file_count'));
$published_count = array_sum(array_column($projects, 'published_count'));

// Get current project for workspace
$current_project = null;
if ($page === 'workspace' && $project_id) {
    $stmt = $conn->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $project_id, $user['id']);
    $stmt->execute();
    $current_project = $stmt->get_result()->fetch_assoc();
    
    if (!$current_project) {
        SecurityUtils::safeRedirect('projects.php?page=projects', 404, 'Project not found');
    }
}

// Get publishable projects
$publishable_projects = [];
$publish_stmt = $conn->prepare("
    SELECT p.*, a.id as app_id, a.status as publish_status
    FROM projects p
    LEFT JOIN apps a ON p.id = a.project_id
    WHERE p.user_id = ?
    ORDER BY p.updated_at DESC
");
if ($publish_stmt) {
    $publish_stmt->bind_param("i", $user['id']);
    $publish_stmt->execute();
    $publishable_projects = $publish_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$pageTitles = [
    'workspace' => 'Workspace',
    'projects' => 'Projects',
    'publish' => 'Publish Center'
];
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitles[$page] ?? 'Projects'); ?> - Bloxer</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/reboot.css">
</head>
<body class="app-studio">
    <div class="studio-shell">
        <aside class="studio-sidebar sidebar" id="sidebar">
            <div class="studio-brand">
                <span class="brand-mark">[DEV]</span>
                <div class="studio-brand-copy">
                    <strong>Bloxer Studio</strong>
                    <span>Panel developera</span>
                </div>
            </div>

            <nav class="studio-nav">
                <div class="studio-nav-item nav-item <?php echo $page === 'workspace' ? 'active' : ''; ?>" onclick="window.location.href='projects.php?page=workspace<?php echo $project_id ? '&project_id=' . $project_id : ''; ?>'">
                    <span>[CODE]</span>
                    <span>Workspace</span>
                </div>
                <div class="studio-nav-item nav-item <?php echo $page === 'projects' ? 'active' : ''; ?>" onclick="window.location.href='projects.php?page=projects'">
                    <span>[FOLDER]</span>
                    <span>Projects</span>
                </div>
                <div class="studio-nav-item nav-item <?php echo $page === 'offers' ? 'active' : ''; ?>" onclick="window.location.href='../core/dashboard.php?page=offers'">
                    <span>[DEAL]</span>
                    <span>Offers</span>
                </div>
                <div class="studio-nav-item nav-item <?php echo $page === 'publish' ? 'active' : ''; ?>" onclick="window.location.href='projects.php?page=publish'">
                    <span>[ROCKET]</span>
                    <span>Publish</span>
                </div>
                <div class="studio-nav-item nav-item" onclick="window.location.href='version-control.php'">
                    <span>[GIT]</span>
                    <span>Version Control</span>
                </div>
                <div class="studio-nav-item nav-item" onclick="window.location.href='notifications.php'">
                    <span>[BELL]</span>
                    <span>Notifications</span>
                </div>
                <div class="studio-nav-item nav-item" onclick="window.location.href='marketplace-settings.php'">
                    <span>[SET]</span>
                    <span>Settings</span>
                </div>
            </nav>

            <div class="studio-nav-foot">
                <?php if (isset($_SESSION['form_errors'])): ?>
                    <div class="studio-flash studio-flash-error">
                        <span>[!]</span>
                        <?php echo htmlspecialchars($_SESSION['form_errors'][0]); ?>
                    </div>
                    <?php unset($_SESSION['form_errors']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="studio-flash studio-flash-success">
                        <span>[✓]</span>
                        <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

            <a href="../controllers/auth/logout.php" class="studio-nav-item nav-item">
                <span>[OUT]</span>
                <span>Logout</span>
            </div>
            </div>
        </aside>

        <div class="studio-main-wrap">
            <header class="studio-header top-bar">
                <div class="studio-header-main">
                    <button class="btn btn-small studio-menu-toggle" onclick="toggleSidebar()">
                        <span>[☰]</span>
                    </button>
                    <div>
                        <h1><?php echo htmlspecialchars($pageTitles[$page] ?? 'Projects'); ?></h1>
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
                                <a href="publish.php" class="btn btn-primary">
                                    <i class="fas fa-rocket"></i>
                                    Publish
                                </a>
                                <a href="projects.php?page=projects" class="btn btn-secondary">
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
                                <h2>Your projects</h2>
                                <p>Zarządzaj biblioteką projektów, plikami i gotowością do publikacji.</p>
                            </div>
                            <button class="btn btn-primary btn-small" onclick="showCreateProjectModal()">
                                <i class="fas fa-plus"></i>
                                New project
                            </button>
                        </div>

                        <div class="studio-stat-grid stats-grid">
                            <div class="studio-stat-card stat-card">
                                <strong><?php echo count($projects); ?></strong>
                                <span>Total projects</span>
                            </div>
                            <div class="studio-stat-card stat-card">
                                <strong><?php echo $total_files; ?></strong>
                                <span>Total files</span>
                            </div>
                            <div class="studio-stat-card stat-card">
                                <strong><?php echo $published_count; ?></strong>
                                <span>Published products</span>
                            </div>
                        </div>

                        <?php if (!empty($projects)): ?>
                            <div class="studio-card-grid">
                                <?php foreach ($projects as $project): ?>
                                    <article class="studio-project-card project-card" onclick="navigate('workspace', <?php echo $project['id']; ?>)">
                                        <div class="studio-project-head">
                                            <div class="studio-icon-box">
                                                <i class="fas fa-cube"></i>
                                            </div>
                                            <button class="btn btn-small btn-danger" onclick="deleteProject(event, <?php echo $project['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                        <h3 class="studio-card-title"><?php echo htmlspecialchars($project['name']); ?></h3>
                                        <p class="studio-card-copy"><?php echo htmlspecialchars($project['description'] ?: 'No description provided for this project.'); ?></p>
                                        <div class="studio-meta-row">
                                            <div class="studio-inline-meta">
                                                <span><i class="fas fa-file-code"></i> <?php echo (int) $project['file_count']; ?> files</span>
                                                <span><i class="fas fa-clock"></i> <?php echo date('M j', strtotime($project['updated_at'])); ?></span>
                                            </div>
                                            <span class="studio-tag"><?php echo htmlspecialchars($project['framework']); ?></span>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="studio-empty">
                                <i class="fas fa-folder-open"></i>
                                <h3>Nie masz jeszcze projektów</h3>
                                <p>Stwórz pierwszy projekt i zbuduj własną aplikację w nowym studio.</p>
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

    <!-- Create Project Modal -->
    <div class="studio-modal modal" id="create-project-modal">
        <div class="studio-modal-card modal-content">
            <h3>Create new project</h3>
            <form id="create-project-form" method="POST" action="projects.php?page=projects" class="studio-form-stack">
                <input type="hidden" name="action" value="create_project">

                <div class="studio-two-col">
                    <div class="form-group">
                        <label for="project_name">Project name</label>
                        <input type="text" id="project_name" name="project_name" required>
                    </div>
                    <div class="form-group">
                        <label for="project_type">Project type</label>
                        <select id="project_type" name="project_type">
                            <option value="blank">Blank project</option>
                            <option value="react">React</option>
                            <option value="vue">Vue.js</option>
                            <option value="vanilla">Vanilla JS</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="project_description">Description</label>
                    <textarea id="project_description" name="project_description" rows="4"></textarea>
                </div>

                <div class="studio-modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create project</button>
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
            const url = `projects.php?page=${page}`;
            if (projectId) {
                window.location.href = `${url}&project_id=${projectId}`;
            } else {
                window.location.href = url;
            }
        }

        function showCreateProjectModal() {
            document.getElementById('create-project-modal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('create-project-modal').style.display = 'none';
        }

        function deleteProject(event, projectId) {
            event.stopPropagation();
            
            if (confirm('Are you sure you want to delete this project? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'projects.php?page=projects';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_project';
                
                const projectInput = document.createElement('input');
                projectInput.type = 'hidden';
                projectInput.name = 'project_id';
                projectInput.value = projectId;
                
                form.appendChild(actionInput);
                form.appendChild(projectInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        function showPublishModal(projectId) {
            window.location.href = `publish.php?project_id=${projectId}`;
        }

        function editApp(appId) {
            window.location.href = `app.php?id=${appId}`;
        }

        function viewApp(appId) {
            window.location.href = `app.php?id=${appId}`;
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('create-project-modal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
