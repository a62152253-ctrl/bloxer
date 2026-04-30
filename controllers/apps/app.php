<?php
require_once '../core/mainlogincore.php';

$auth = new AuthCore();
$user = $auth->getCurrentUser();

$app_id = $_GET['id'] ?? null;

if (!$app_id) {
    header('Location: ../marketplace/marketplace.php');
    exit();
}

// Get app details
$conn = $auth->getConnection();
$stmt = $conn->prepare("
    SELECT a.*, u.id as developer_id, u.username as developer_name, u.avatar_url as developer_avatar, u.email as developer_email, u.bio as developer_bio,
           c.name as category_name, c.icon as category_icon,
           p.description as project_description
    FROM apps a
    JOIN projects p ON a.project_id = p.id
    JOIN users u ON p.user_id = u.id
    LEFT JOIN categories c ON a.category = c.slug
    WHERE a.id = ? AND a.status = 'published'
");
$stmt->bind_param("i", $app_id);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();

if (!$app) {
    header('Location: marketplace.php');
    exit();
}

// Get developer stats
$stmt = $conn->prepare("
    SELECT COUNT(*) as total_apps, AVG(ar.rating) as avg_rating
    FROM apps a
    LEFT JOIN app_reviews ar ON a.id = ar.app_id
    WHERE a.project_id = ?
");
$stmt->bind_param("i", $app['project_id']);
$stmt->execute();
$developer_stats = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($app['title']); ?> - Bloxer</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/reboot.css">
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
                <div class="studio-nav-item nav-item" onclick="window.location.href='dashboard.php?page=workspace'">
                    <i class="fas fa-code"></i>
                    <span>Workspace</span>
                </div>
                <div class="studio-nav-item nav-item" onclick="window.location.href='dashboard.php?page=projects'">
                    <i class="fas fa-folder-open"></i>
                    <span>Projects</span>
                </div>
                <div class="studio-nav-item nav-item" onclick="window.location.href='dashboard.php?page=offers'">
                    <i class="fas fa-handshake"></i>
                    <span>Offers</span>
                </div>
                <div class="studio-nav-item nav-item" onclick="window.location.href='dashboard.php?page=publish'">
                    <i class="fas fa-rocket"></i>
                    <span>Publish</span>
                </div>
                <div class="studio-nav-item nav-item" onclick="window.location.href='marketplace.php'">
                    <i class="fas fa-store"></i>
                    <span>Marketplace</span>
                </div>
                <div class="studio-nav-item nav-item" onclick="window.location.href='logout.php'">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </div>
            </nav>
        </aside>

        <div class="studio-main-wrap">
            <header class="studio-header top-bar">
                <div class="studio-header-main">
                    <button class="btn btn-small studio-menu-toggle" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div>
                        <h1><?php echo htmlspecialchars($app['title']); ?></h1>
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
                <section class="studio-welcome-card">
                    <div class="studio-section-head">
                        <div>
                            <h2><?php echo htmlspecialchars($app['title']); ?></h2>
                            <p><?php echo htmlspecialchars($app['description'] ?? 'No description available'); ?></p>
                        </div>
                        <div class="studio-inline-actions">
                            <a href="marketplace.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i>
                                Back to Marketplace
                            </a>
                            <?php if ($app['demo_url']): ?>
                                <a href="<?php echo htmlspecialchars($app['demo_url']); ?>" target="_blank" class="btn btn-primary">
                                    <i class="fas fa-external-link-alt"></i>
                                    Live Demo
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="studio-surface" style="margin-top: 18px;">
                        <div class="studio-project-head">
                            <div class="studio-icon-box">
                                <i class="fas fa-<?php echo $app['category_icon'] ?? 'cube'; ?>"></i>
                            </div>
                            <span class="studio-tag"><?php echo htmlspecialchars($app['category_name'] ?? 'General'); ?></span>
                        </div>
                        <p class="studio-card-copy">
                            <?php echo htmlspecialchars($app['long_description'] ?? $app['description'] ?? 'No detailed description available.'); ?>
                        </p>
                        <div class="studio-meta-row">
                            <div class="studio-inline-meta">
                                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($app['developer_name']); ?></span>
                                <span><i class="fas fa-star"></i> <?php echo number_format($app['rating'] ?? 0, 1); ?></span>
                                <span><i class="fas fa-download"></i> <?php echo number_format($app['downloads'] ?? 0); ?> installs</span>
                                <span><i class="fas fa-calendar"></i> <?php echo date('M j, Y', strtotime($app['updated_at'])); ?></span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Demo Section -->
                <?php if ($app['demo_url']): ?>
                    <section class="studio-section">
                        <div class="studio-section-head">
                            <div>
                                <h2>Live Demo</h2>
                                <p>Try this app directly in your browser</p>
                            </div>
                        </div>
                        
                        <div class="studio-surface">
                            <iframe src="<?php echo htmlspecialchars($app['demo_url']); ?>" class="demo-iframe" style="width: 100%; height: 500px; border: 1px solid #e5e7eb; border-radius: 8px;"></iframe>
                        </div>
                    </section>
                <?php endif; ?>
                
                <!-- Developer Info -->
                <section class="studio-section">
                    <div class="studio-section-head">
                        <div>
                            <h2>Developer</h2>
                            <p>About the creator of this app</p>
                        </div>
                    </div>
                    
                    <div class="studio-surface">
                        <div class="studio-project-head">
                            <div class="studio-icon-box">
                                <i class="fas fa-user"></i>
                            </div>
                            <span class="studio-tag">Developer</span>
                        </div>
                        <h3><?php echo htmlspecialchars($app['developer_name']); ?></h3>
                        <p class="studio-card-copy">
                            <?php echo htmlspecialchars($app['developer_bio'] ?? 'No bio available'); ?>
                        </p>
                        <div class="studio-meta-row">
                            <div class="studio-inline-meta">
                                <span><i class="fas fa-code"></i> <?php echo number_format($developer_stats['total_apps'] ?? 0); ?> apps</span>
                                <span><i class="fas fa-star"></i> <?php echo number_format($developer_stats['avg_rating'] ?? 0, 1); ?> rating</span>
                            </div>
                        </div>
                        
                        <?php if ($app['developer_id'] !== $user['id']): ?>
                            <div class="studio-inline-actions" style="margin-top: 16px;">
                                <a href="mailto:<?php echo htmlspecialchars($app['developer_email']); ?>" class="btn btn-secondary">
                                    <i class="fas fa-envelope"></i>
                                    Contact Developer
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('collapsed');
        }
    </script>
</body>
</html>
