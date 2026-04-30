<?php
require_once __DIR__ . '/../../bootstrap.php';

$auth = new AuthCore();

if (!$auth->isLoggedIn()) {
    SecurityUtils::safeRedirect('../auth/login.php', 302, 'Unauthorized access attempt');
}

$user = $auth->getCurrentUser();
$conn = $auth->getConnection();

// Initialize recommendation engine
$recommendationEngine = new RecommendationEngine($conn, $user['id']);

// Track user behavior
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'track_behavior') {
    $event_type = $_POST['event_type'] ?? '';
    $data = json_decode($_POST['data'] ?? '{}', true) ?? [];
    
    if ($event_type && $data) {
        $recommendationEngine->trackBehavior($event_type, $data);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
    }
    exit();
}

// Get personalized recommendations
$recommendations = $recommendationEngine->getRecommendations(12);

// Get trending categories
$trending_categories = $recommendationEngine->getTrendingCategories(6);

// Get popular apps
$popular_apps = $recommendationEngine->getPopularApps(8);

// Get user's installed apps for comparison
$stmt = $conn->prepare("
    SELECT a.id, a.title, a.category, a.thumbnail_url, ua.installed_at
    FROM user_apps ua
    JOIN apps a ON ua.app_id = a.id
    WHERE ua.user_id = ?
    ORDER BY ua.installed_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$installed_apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get user's recent activity
$stmt = $conn->prepare("
    SELECT ub.*, a.title as app_title, c.name as category_name
    FROM user_behavior ub
    LEFT JOIN apps a ON ub.app_id = a.id
    LEFT JOIN categories c ON ub.category_id = c.id
    WHERE ub.user_id = ? AND ub.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY ub.created_at DESC
    LIMIT 10
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$recent_activity = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Helper function for category icons
function getCategoryIconPHP($slug) {
    $icons = [
        'games' => 'fa-gamepad',
        'productivity' => 'fa-briefcase',
        'social' => 'fa-users',
        'entertainment' => 'fa-film',
        'education' => 'fa-graduation-cap',
        'utilities' => 'fa-tools',
        'business' => 'fa-briefcase',
        'lifestyle' => 'fa-heart',
        'health' => 'fa-heartbeat',
        'finance' => 'fa-dollar-sign'
    ];
    return $icons[$slug] ?? 'fa-folder';
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personalized Feed - Bloxer</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="logo">
                <h1>Bloxer</h1>
                <p>Personalized Feed</p>
            </div>
            
            <nav>
                <div class="nav-item" onclick="window.location.href='../controllers/user/personalized_feed.php'">
                    <i class="fas fa-home"></i>
                    <span>For You</span>
                </div>
                <div class="nav-item" onclick="window.location.href='../marketplace/marketplace.php'">
                    <i class="fas fa-store"></i>
                    <span>Marketplace</span>
                </div>
                <div class="nav-item" onclick="window.location.href='../core/dashboard.php'">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </div>
                <div class="nav-item" onclick="window.location.href='../controllers/user/notifications.php'">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
                </div>
            </nav>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <header class="main-header">
                <div class="header-content">
                    <h1>For You</h1>
                    <p class="feed-description">Personalized recommendations based on your interests</p>
                </div>
                <div class="header-actions">
                    <button onclick="refreshRecommendations()" class="btn btn-secondary">
                        <i class="fas fa-sync"></i> Refresh
                    </button>
                </div>
            </header>
            
            <div class="content-area">
                <!-- Welcome Section -->
                <div class="welcome-section">
                    <div class="welcome-content">
                        <h2>Welcome back, <?php echo htmlspecialchars($user['username']); ?>!</h2>
                        <p>Here's what we think you'll love today</p>
                    </div>
                    <div class="quick-stats">
                        <div class="stat">
                            <span class="stat-number"><?php echo count($installed_apps); ?></span>
                            <span class="stat-label">Apps Installed</span>
                        </div>
                        <div class="stat">
                            <span class="stat-number"><?php echo count($trending_categories); ?></span>
                            <span class="stat-label">Trending Categories</span>
                        </div>
                    </div>
                </div>
                
                <!-- Personalized Recommendations -->
                <div class="section">
                    <div class="section-header">
                        <h2>
                            <i class="fas fa-magic"></i>
                            Recommended for You
                        </h2>
                        <span class="section-subtitle">Based on your preferences and activity</span>
                    </div>
                    
                    <div class="apps-grid recommendations-grid">
                        <?php if (!empty($recommendations)): ?>
                            <?php foreach ($recommendations as $index => $app): ?>
                                <div class="app-card recommendation-card" data-app-id="<?php echo $app['id']; ?>" data-position="<?php echo $index + 1; ?>">
                                    <div class="app-thumbnail">
                                        <img src="<?php echo htmlspecialchars($app['thumbnail_url'] ?: '../assets/images/default-app.png'); ?>" alt="<?php echo htmlspecialchars($app['title']); ?>">
                                        <div class="recommendation-badge">
                                            <i class="fas fa-star"></i>
                                            <?php echo ucfirst($app['recommendation_type']); ?>
                                        </div>
                                    </div>
                                    <div class="app-info">
                                        <h3><?php echo htmlspecialchars($app['title']); ?></h3>
                                        <p class="app-category"><?php echo htmlspecialchars(ucfirst($app['category'])); ?></p>
                                        <p class="app-description"><?php echo htmlspecialchars(substr($app['short_description'], 0, 100)) . '...'; ?></p>
                                        <div class="app-stats">
                                            <span class="rating">
                                                <i class="fas fa-star"></i>
                                                <?php echo number_format($app['rating'] ?? 0, 1); ?>
                                            </span>
                                            <span class="downloads">
                                                <i class="fas fa-download"></i>
                                                <?php echo number_format($app['total_downloads'] ?? 0); ?>
                                            </span>
                                        </div>
                                        <div class="app-actions">
                                            <button onclick="viewApp(<?php echo $app['id']; ?>)" class="btn btn-small">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <button onclick="installApp(<?php echo $app['id']; ?>)" class="btn btn-small btn-primary">
                                                <i class="fas fa-download"></i> Install
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-recommendations">
                                <i class="fas fa-magic"></i>
                                <h3>No recommendations yet</h3>
                                <p>Start exploring apps to get personalized recommendations!</p>
                                <button onclick="window.location.href='../marketplace/marketplace.php'" class="btn btn-primary">
                                    <i class="fas fa-store"></i> Browse Marketplace
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Trending Categories -->
                <div class="section">
                    <div class="section-header">
                        <h2>
                            <i class="fas fa-fire"></i>
                            Trending Categories
                        </h2>
                        <span class="section-subtitle">What's popular right now</span>
                    </div>
                    
                    <div class="categories-trending">
                        <?php foreach ($trending_categories as $category): ?>
                            <div class="category-trend-card" onclick="exploreCategory(<?php echo $category['id']; ?>)">
                                <div class="category-icon">
                                    <i class="fas fa-<?php echo getCategoryIconPHP($category['slug']); ?>"></i>
                                </div>
                                <div class="category-info">
                                    <h3><?php echo htmlspecialchars($category['name']); ?></h3>
                                    <p><?php echo number_format($category['trending_score'], 2); ?> trending score</p>
                                </div>
                                <div class="trend-indicator">
                                    <i class="fas fa-arrow-up"></i>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Popular Apps -->
                <div class="section">
                    <div class="section-header">
                        <h2>
                            <i class="fas fa-fire"></i>
                            Popular Apps
                        </h2>
                        <span class="section-subtitle">Most installed this week</span>
                    </div>
                    
                    <div class="apps-grid popular-grid">
                        <?php foreach ($popular_apps as $app): ?>
                            <div class="app-card popular-card">
                                <div class="app-thumbnail">
                                    <img src="<?php echo htmlspecialchars($app['thumbnail_url'] ?: '../assets/images/default-app.png'); ?>" alt="<?php echo htmlspecialchars($app['title']); ?>">
                                    <div class="popularity-badge">
                                        <i class="fas fa-fire"></i>
                                        Popular
                                    </div>
                                </div>
                                <div class="app-info">
                                    <h3><?php echo htmlspecialchars($app['title']); ?></h3>
                                    <p class="app-category"><?php echo htmlspecialchars(ucfirst($app['category'])); ?></p>
                                    <div class="app-stats">
                                        <span class="rating">
                                            <i class="fas fa-star"></i>
                                            <?php echo number_format($app['rating'] ?? 0, 1); ?>
                                        </span>
                                        <span class="downloads">
                                            <i class="fas fa-download"></i>
                                            <?php echo number_format($app['total_downloads'] ?? 0); ?>
                                        </span>
                                    </div>
                                    <button onclick="viewApp(<?php echo $app['id']; ?>)" class="btn btn-small btn-primary">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Recently Installed -->
                <?php if (!empty($installed_apps)): ?>
                <div class="section">
                    <div class="section-header">
                        <h2>
                            <i class="fas fa-history"></i>
                            Recently Installed
                        </h2>
                        <span class="section-subtitle">Your latest additions</span>
                    </div>
                    
                    <div class="recently-installed">
                        <?php foreach ($installed_apps as $app): ?>
                            <div class="installed-app">
                                <img src="<?php echo htmlspecialchars($app['thumbnail_url'] ?: '../assets/images/default-app.png'); ?>" alt="<?php echo htmlspecialchars($app['title']); ?>">
                                <div class="installed-info">
                                    <h4><?php echo htmlspecialchars($app['title']); ?></h4>
                                    <p><?php echo htmlspecialchars(ucfirst($app['category'])); ?></p>
                                    <span class="install-date"><?php echo date('M j, Y', strtotime($app['installed_at'])); ?></span>
                                </div>
                                <button onclick="openApp(<?php echo $app['id']; ?>)" class="btn btn-small">
                                    <i class="fas fa-play"></i> Open
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <style>
        .welcome-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, var(--accent-color), #3b82f6);
            color: white;
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 30px;
        }
        
        .welcome-content h2 {
            margin: 0 0 10px 0;
            font-size: 1.8em;
        }
        
        .welcome-content p {
            margin: 0;
            opacity: 0.9;
        }
        
        .quick-stats {
            display: flex;
            gap: 30px;
        }
        
        .stat {
            text-align: center;
        }
        
        .stat-number {
            display: block;
            font-size: 2em;
            font-weight: bold;
        }
        
        .stat-label {
            font-size: 0.9em;
            opacity: 0.9;
        }
        
        .feed-description {
            color: var(--text-secondary);
            font-size: 1.1em;
        }
        
        .section {
            margin-bottom: 40px;
        }
        
        .section-header {
            margin-bottom: 20px;
        }
        
        .section-header h2 {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0 0 5px 0;
            color: var(--text-primary);
            font-size: 1.5em;
        }
        
        .section-subtitle {
            color: var(--text-secondary);
            font-size: 0.9em;
        }
        
        .recommendations-grid,
        .popular-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .recommendation-card {
            position: relative;
            transition: transform 0.3s ease;
        }
        
        .recommendation-card:hover {
            transform: translateY(-5px);
        }
        
        .recommendation-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--accent-color);
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7em;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .popularity-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #ef4444;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7em;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .categories-trending {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .category-trend-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .category-trend-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        .category-icon {
            width: 50px;
            height: 50px;
            background: var(--accent-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2em;
        }
        
        .category-info {
            flex: 1;
        }
        
        .category-info h3 {
            margin: 0 0 5px 0;
            color: var(--text-primary);
        }
        
        .category-info p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 0.9em;
        }
        
        .trend-indicator {
            color: #10b981;
            font-size: 1.2em;
        }
        
        .recently-installed {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .installed-app {
            display: flex;
            align-items: center;
            gap: 15px;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 15px;
        }
        
        .installed-app img {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
        }
        
        .installed-info {
            flex: 1;
        }
        
        .installed-info h4 {
            margin: 0 0 5px 0;
            color: var(--text-primary);
        }
        
        .installed-info p {
            margin: 0 0 5px 0;
            color: var(--text-secondary);
            font-size: 0.9em;
        }
        
        .install-date {
            color: var(--text-secondary);
            font-size: 0.8em;
        }
        
        .empty-recommendations {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }
        
        .empty-recommendations i {
            font-size: 3em;
            margin-bottom: 20px;
            opacity: 0.5;
        }
    </style>
    
    <script>
        // Track user behavior
        function trackBehavior(eventType, data) {
            fetch('personalized_feed.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=track_behavior&event_type=${eventType}&data=${JSON.stringify(data)}`
            });
        }
        
        // Track app clicks
        function viewApp(appId) {
            trackBehavior('view_app', { app_id: appId });
            window.location.href = `../controllers/apps/app.php?id=${appId}`;
        }
        
        function installApp(appId) {
            trackBehavior('install_app', { app_id: appId });
            // Implement install logic
            window.location.href = `../controllers/apps/app.php?id=${appId}`;
        }
        
        function openApp(appId) {
            trackBehavior('app_open', { app_id: appId });
            // Implement open logic
            window.location.href = `../controllers/core/run_app.php?id=${appId}`;
        }
        
        function exploreCategory(categoryId) {
            trackBehavior('category_click', { category_id: categoryId });
            window.location.href = `../marketplace/marketplace.php?category=${categoryId}`;
        }
        
        function refreshRecommendations() {
            location.reload();
        }
        
        // Track recommendation clicks
        document.addEventListener('DOMContentLoaded', function() {
            // Track clicks on recommendation cards
            document.querySelectorAll('.recommendation-card').forEach(card => {
                card.addEventListener('click', function(e) {
                    if (!e.target.closest('button')) {
                        const appId = this.dataset.appId;
                        const position = this.dataset.position;
                        
                        fetch('personalized_feed.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `action=track_behavior&event_type=recommendation_click&data=${JSON.stringify({
                                app_id: appId,
                                position: position,
                                recommendation_type: 'personalized'
                            })}`
                        });
                    }
                });
            });
            
            // Track page view
            trackBehavior('page_view', { page: 'personalized_feed' });
        });
        
        function getCategoryIcon(slug) {
            const icons = {
                'games': 'fa-gamepad',
                'productivity': 'fa-briefcase',
                'social': 'fa-users',
                'entertainment': 'fa-film',
                'education': 'fa-graduation-cap',
                'utilities': 'fa-tools',
                'business': 'fa-briefcase',
                'lifestyle': 'fa-heart',
                'health': 'fa-heartbeat',
                'finance': 'fa-dollar-sign'
            };
            return icons[slug] || 'fa-folder';
        }
        
            </script>
</body>
</html>
