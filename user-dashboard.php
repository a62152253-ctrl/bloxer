<?php
require_once 'mainlogincore.php';

$auth = new AuthCore();

// Redirect developers to developer dashboard
if ($auth->isLoggedIn() && $auth->isDeveloper()) {
    header('Location: dashboard.php');
    exit();
}

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user = $auth->getCurrentUser();

// Get user's installed apps
$conn = $auth->getConnection();
$stmt = $conn->prepare("
    SELECT a.*, ua.installed_at, u.username as developer_name,
           c.name as category_name, c.icon as category_icon
    FROM user_apps ua
    JOIN apps a ON ua.app_id = a.id
    JOIN users u ON a.project_id IN (SELECT id FROM projects WHERE user_id = u.id)
    LEFT JOIN categories c ON a.category = c.slug
    WHERE ua.user_id = ?
    ORDER BY ua.installed_at DESC
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$installed_apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get user's reviews
$stmt = $conn->prepare("
    SELECT ar.*, a.title as app_title, a.thumbnail_url as app_thumbnail
    FROM app_reviews ar
    JOIN apps a ON ar.app_id = a.id
    WHERE ar.user_id = ?
    ORDER BY ar.created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$user_reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get recommended apps based on user's installed apps
$recommended_apps = [];
if (!empty($installed_apps)) {
    $categories = array_unique(array_column($installed_apps, 'category'));
    $placeholders = str_repeat('?,', count($categories));
    $placeholders = rtrim($placeholders, ',');
    
    $stmt = $conn->prepare("
        SELECT a.*, u.username as developer_name, c.name as category_name
        FROM apps a
        JOIN users u ON a.project_id IN (SELECT id FROM projects WHERE user_id = u.id)
        LEFT JOIN categories c ON a.category = c.slug
        WHERE a.category IN ($placeholders) AND a.status = 'published'
        AND a.id NOT IN (SELECT app_id FROM user_apps WHERE user_id = ?)
        ORDER BY a.download_count DESC
        LIMIT 6
    ");
    
    $params = array_merge($categories, [$user['id']]);
    $types = str_repeat('s', count($categories)) . 'i';
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $recommended_apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get recent activity
$recent_activity = [];
if (!empty($installed_apps)) {
    $stmt = $conn->prepare("
        SELECT 'installed' as activity_type, a.title, a.thumbnail_url, ua.installed_at as activity_date
        FROM user_apps ua
        JOIN apps a ON ua.app_id = a.id
        WHERE ua.user_id = ? AND ua.installed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        
        UNION ALL
        
        SELECT 'reviewed' as activity_type, a.title, a.thumbnail_url, ar.created_at as activity_date
        FROM app_reviews ar
        JOIN apps a ON ar.app_id = a.id
        WHERE ar.user_id = ? AND ar.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        
        ORDER BY activity_date DESC
        LIMIT 10
    ");
    
    $stmt->bind_param("ii", $user['id'], $user['id']);
    $stmt->execute();
    $recent_activity = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - Bloxer</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .dashboard-container {
            min-height: 100vh;
            background: var(--deep-black);
            background: linear-gradient(180deg, var(--deep-black) 0%, var(--deep-black-gradient) 100%);
            padding: 20px;
        }
        
        .dashboard-header {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
            text-align: center;
        }
        
        .user-welcome {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 3px solid var(--accent);
        }
        
        .welcome-text h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 5px;
            color: var(--text-primary);
        }
        
        .welcome-text p {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            max-width: 800px;
            margin: 0 auto 40px;
        }
        
        .stat-card {
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            backdrop-filter: blur(20px);
        }
        
        .stat-icon {
            font-size: 2rem;
            color: var(--accent);
            margin-bottom: 10px;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .section {
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            backdrop-filter: blur(20px);
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 25px;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .apps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .app-card {
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .app-card:hover {
            transform: translateY(-3px);
            background: rgba(255,255,255,0.04);
        }
        
        .app-thumbnail {
            width: 100%;
            height: 150px;
            background: linear-gradient(135deg, var(--btn-gradient-start) 0%, var(--btn-gradient-end) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .app-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .app-content {
            padding: 15px;
        }
        
        .app-title {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        .app-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .app-category {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        .app-rating {
            display: flex;
            align-items: center;
            gap: 3px;
            font-size: 0.8rem;
            color: #ffd700;
        }
        
        .app-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-small {
            padding: 8px 15px;
            border: none;
            border-radius: 20px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-primary {
            background: var(--accent);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--accent-hover);
        }
        
        .btn-secondary {
            background: rgba(255,255,255,0.1);
            border: 1px solid var(--glass-border);
            color: var(--text-primary);
        }
        
        .btn-secondary:hover {
            background: rgba(255,255,255,0.15);
        }
        
        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: rgba(255,255,255,0.02);
            border-radius: 10px;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }
        
        .activity-icon.install {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }
        
        .activity-icon.review {
            background: rgba(251, 146, 60, 0.2);
            color: #fb923c;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 3px;
        }
        
        .activity-date {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        .navigation-menu {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 100;
            display: flex;
            gap: 10px;
        }
        
        .nav-button {
            background: rgba(255,255,255,0.1);
            border: 1px solid var(--glass-border);
            border-radius: 50px;
            padding: 10px 20px;
            color: var(--text-primary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }
        
        .nav-button:hover {
            background: rgba(255,255,255,0.15);
        }
        
        .nav-button.logout {
            background: rgba(239, 68, 68, 0.1);
            border-color: rgba(239, 68, 68, 0.3);
        }
        
        @media (max-width: 768px) {
            .user-welcome {
                flex-direction: column;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .apps-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Navigation Menu -->
        <div class="navigation-menu">
            <a href="marketplace.php" class="nav-button">
                <i class="fas fa-store"></i>
                <span>Marketplace</span>
            </a>
            <a href="profile.php" class="nav-button">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
            <a href="logout.php" class="nav-button logout">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
        
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="user-welcome">
                <img src="<?php echo $user['avatar_url'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($user['username']) . '&background=6366f1&color=fff'; ?>" 
                     alt="User Avatar" class="user-avatar">
                <div class="welcome-text">
                    <h1>Welcome back, <?php echo htmlspecialchars($user['username']); ?>!</h1>
                    <p>Discover and manage your web applications</p>
                </div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-download"></i>
                    </div>
                    <div class="stat-number"><?php echo count($installed_apps); ?></div>
                    <div class="stat-label">Installed Apps</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-number"><?php echo count($user_reviews); ?></div>
                    <div class="stat-label">Reviews</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-number"><?php echo count($recent_activity); ?></div>
                    <div class="stat-label">Recent Activity</div>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Installed Apps -->
            <div class="section">
                <h2 class="section-title">
                    <i class="fas fa-download"></i>
                    My Installed Applications
                </h2>
                
                <?php if (!empty($installed_apps)): ?>
                    <div class="apps-grid">
                        <?php foreach ($installed_apps as $app): ?>
                            <div class="app-card" onclick="openApp(<?php echo $app['id']; ?>)">
                                <div class="app-thumbnail">
                                    <?php if ($app['thumbnail_url']): ?>
                                        <img src="<?php echo htmlspecialchars($app['thumbnail_url']); ?>" alt="<?php echo htmlspecialchars($app['title']); ?>">
                                    <?php else: ?>
                                        <i class="fas fa-rocket" style="font-size: 2rem; color: rgba(255,255,255,0.8);"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="app-content">
                                    <h3 class="app-title"><?php echo htmlspecialchars($app['title']); ?></h3>
                                    <div class="app-meta">
                                        <span class="app-category">
                                            <i class="fas fa-<?php echo $app['category_icon'] ?? 'folder'; ?>"></i>
                                            <?php echo htmlspecialchars($app['category_name'] ?? 'General'); ?>
                                        </span>
                                        <div class="app-rating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star" style="font-size: 0.7rem; color: <?php echo $i <= round($app['rating']) ? '#ffd700' : 'var(--glass-border)'; ?>;"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <div class="app-actions">
                                        <button class="btn-small btn-primary" onclick="openApp(<?php echo $app['id']; ?>, event)">
                                            <i class="fas fa-play"></i>
                                            Launch
                                        </button>
                                        <button class="btn-small btn-secondary" onclick="uninstallApp(<?php echo $app['id']; ?>, event)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-download"></i>
                        <h3>No installed apps yet</h3>
                        <p>Browse the marketplace to discover and install amazing applications</p>
                        <a href="marketplace.php" class="btn-primary" style="margin-top: 20px;">
                            <i class="fas fa-store"></i>
                            Browse Marketplace
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Recommended Apps -->
            <?php if (!empty($recommended_apps)): ?>
                <div class="section">
                    <h2 class="section-title">
                        <i class="fas fa-magic"></i>
                        Recommended for You
                    </h2>
                    
                    <div class="apps-grid">
                        <?php foreach ($recommended_apps as $app): ?>
                            <div class="app-card" onclick="openApp(<?php echo $app['id']; ?>)">
                                <div class="app-thumbnail">
                                    <?php if ($app['thumbnail_url']): ?>
                                        <img src="<?php echo htmlspecialchars($app['thumbnail_url']); ?>" alt="<?php echo htmlspecialchars($app['title']); ?>">
                                    <?php else: ?>
                                        <i class="fas fa-rocket" style="font-size: 2rem; color: rgba(255,255,255,0.8);"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="app-content">
                                    <h3 class="app-title"><?php echo htmlspecialchars($app['title']); ?></h3>
                                    <p style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 10px;">
                                        by <?php echo htmlspecialchars($app['developer_name']); ?>
                                    </p>
                                    <div class="app-actions">
                                        <button class="btn-small btn-primary" onclick="installApp(<?php echo $app['id']; ?>, event)">
                                            <i class="fas fa-download"></i>
                                            Install
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Recent Activity -->
            <?php if (!empty($recent_activity)): ?>
                <div class="section">
                    <h2 class="section-title">
                        <i class="fas fa-history"></i>
                        Recent Activity
                    </h2>
                    
                    <div class="activity-list">
                        <?php foreach ($recent_activity as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon <?php echo $activity['activity_type']; ?>">
                                    <i class="fas fa-<?php echo $activity['activity_type'] === 'installed' ? 'download' : 'star'; ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title">
                                        <?php if ($activity['activity_type'] === 'installed'): ?>
                                            Installed "<?php echo htmlspecialchars($activity['title']); ?>"
                                        <?php else: ?>
                                            Reviewed "<?php echo htmlspecialchars($activity['title']); ?>"
                                        <?php endif; ?>
                                    </div>
                                    <div class="activity-date">
                                        <?php echo date('M j, Y g:i A', strtotime($activity['activity_date'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function openApp(appId, event) {
            if (event) {
                event.stopPropagation();
            }
            window.location.href = `app-details.php?id=${appId}`;
        }
        
        async function installApp(appId, event) {
            if (event) {
                event.stopPropagation();
            }
            
            try {
                const response = await fetch('marketplace-api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=install_app&app_id=${appId}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    location.reload();
                } else {
                    alert(result.error || 'Failed to install app');
                }
            } catch (error) {
                console.error('Error installing app:', error);
                alert('An error occurred while installing the app');
            }
        }
        
        async function uninstallApp(appId, event) {
            if (event) {
                event.stopPropagation();
            }
            
            if (!confirm('Are you sure you want to uninstall this app?')) {
                return;
            }
            
            // Implementation needed for uninstall functionality
            alert('Uninstall functionality coming soon!');
        }
    </script>
</body>
</html>
