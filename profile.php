<?php
require_once 'mainlogincore.php';

$auth = new AuthCore();

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

if ($auth->isDeveloper()) {
    header('Location: dashboard.php');
    exit();
}

$user = $auth->getCurrentUser();
$page = $_GET['page'] ?? 'installed';
$conn = $auth->getConnection();

// Get user's installed apps
$stmt = $conn->prepare("
    SELECT a.*, u.username as developer_name, u.avatar_url as developer_avatar,
           ua.installed_at, ua.last_used_at, ua.usage_count, ua.is_favorite
    FROM user_apps ua
    JOIN apps a ON ua.app_id = a.id
    JOIN projects p ON a.project_id = p.id
    JOIN users u ON p.user_id = u.id
    WHERE ua.user_id = ? AND a.status = 'published'
    ORDER BY ua.is_favorite DESC, ua.last_used_at DESC, ua.installed_at DESC
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$installed_apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get user's favorite apps separately
$stmt = $conn->prepare("
    SELECT a.*, u.username as developer_name, u.avatar_url as developer_avatar
    FROM user_apps ua
    JOIN apps a ON ua.app_id = a.id
    JOIN projects p ON a.project_id = p.id
    JOIN users u ON p.user_id = u.id
    WHERE ua.user_id = ? AND ua.is_favorite = 1 AND a.status = 'published'
    ORDER BY ua.installed_at DESC
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$favorite_apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get user's reviews
$stmt = $conn->prepare("
    SELECT ar.*, a.title as app_title, a.thumbnail_url as app_thumbnail
    FROM app_reviews ar
    JOIN apps a ON ar.app_id = a.id
    WHERE ar.user_id = ? AND ar.status = 'published'
    ORDER BY ar.created_at DESC
    LIMIT 10
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$user_reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get user statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(DISTINCT ua.app_id) as total_installed,
        COUNT(DISTINCT CASE WHEN ua.is_favorite = 1 THEN ua.app_id END) as total_favorites,
        COUNT(DISTINCT ar.app_id) as total_reviews,
        COALESCE(AVG(ar.rating), 0) as avg_rating
    FROM users u
    LEFT JOIN user_apps ua ON u.id = ua.user_id
    LEFT JOIN app_reviews ar ON u.id = ar.user_id AND ar.status = 'published'
    WHERE u.id = ?
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - Bloxer</title>
    <link rel="stylesheet" href="profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .profile-container {
            min-height: 100vh;
            background: var(--deep-black);
            background: linear-gradient(180deg, var(--deep-black) 0%, var(--deep-black-gradient) 100%);
        }
        
        .profile-header {
            background: linear-gradient(135deg, var(--btn-gradient-start) 0%, var(--btn-gradient-end) 100%);
            padding: 60px 20px;
            position: relative;
            overflow: hidden;
        }
        
        .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIzMDAiIGhlaWdodD0iMzAwIj48ZmlsdGVyIGlkPSJhIj48ZmVUdXJidWxlbmNlIHR5cGU9ImZyYWN0YWxOb2lzZSIgYmFzZUZyZXF1ZW5jeT0iLjc1IiBudW1PY3RhdmVzPSI0IiAvPjxmZUNvbG9yTWF0cml4IHR5cGU9InNhdHVyYXRlIiB2YWx1ZXM9IjAiLz48ZmVDb21wb3NpdGUgb3BlcmF0b3I9ImluIi8+PC9maWx0ZXI+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsdGVyPSJ1cmwoI2EpIiBvcGFjaXR5PSIwLjAyIi8+PC9zdmc+');
            opacity: 0.1;
        }
        
        .header-content {
            position: relative;
            z-index: 1;
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: 40px;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid rgba(255,255,255,0.3);
            background: rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .profile-info {
            flex: 1;
            color: white;
        }
        
        .profile-name {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 10px;
            text-shadow: 0 0 30px rgba(255, 255, 255, 0.3);
        }
        
        .profile-email {
            font-size: 1.1rem;
            opacity: 0.8;
            margin-bottom: 20px;
        }
        
        .profile-stats {
            display: flex;
            gap: 30px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            display: block;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .nav-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 1px solid var(--glass-border);
            padding-bottom: 15px;
        }
        
        .nav-tab {
            padding: 12px 24px;
            background: rgba(255,255,255,0.06);
            border: 1px solid var(--glass-border);
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            color: var(--text-secondary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .nav-tab:hover {
            background: rgba(255,255,255,0.08);
            color: var(--text-primary);
        }
        
        .nav-tab.active {
            background: var(--btn-gradient-start);
            color: var(--text-primary);
            border-color: var(--btn-gradient-start);
        }
        
        .apps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .app-card {
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            cursor: pointer;
            backdrop-filter: blur(10px);
        }
        
        .app-card:hover {
            transform: translateY(-3px);
            background: rgba(255,255,255,0.06);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }
        
        .app-thumbnail {
            width: 100%;
            height: 160px;
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
        
        .app-thumbnail i {
            font-size: 2.5rem;
            color: rgba(255,255,255,0.8);
        }
        
        .favorite-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(255,255,255,0.9);
            color: #f39c12;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .app-content {
            padding: 15px;
        }
        
        .app-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .app-developer {
            color: var(--text-secondary);
            font-size: 0.85rem;
            margin-bottom: 10px;
        }
        
        .app-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        
        .app-rating {
            display: flex;
            align-items: center;
            gap: 3px;
            color: var(--btn-gradient-start);
        }
        
        .review-item {
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            backdrop-filter: blur(10px);
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        
        .review-app-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .review-app-thumbnail {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: linear-gradient(135deg, var(--btn-gradient-start) 0%, var(--btn-gradient-end) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .review-app-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .review-app-title {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .review-rating {
            display: flex;
            gap: 2px;
            color: var(--btn-gradient-start);
            margin-bottom: 10px;
        }
        
        .review-text {
            color: var(--text-secondary);
            line-height: 1.5;
        }
        
        .review-date {
            color: var(--text-muted);
            font-size: 0.8rem;
            margin-top: 10px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .user-menu {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 100;
        }
        
        .user-button {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 50px;
            padding: 10px 20px;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }
        
        .user-button:hover {
            background: rgba(255,255,255,0.2);
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }
            
            .profile-stats {
                justify-content: center;
            }
            
            .apps-grid {
                grid-template-columns: 1fr;
            }
            
            .nav-tabs {
                justify-content: center;
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <div class="profile-container">
        <!-- User Menu -->
        <div class="user-menu">
            <a href="marketplace.php" class="user-button">
                <i class="fas fa-arrow-left"></i>
                <span>Back to Marketplace</span>
            </a>
            <a href="logout.php" class="user-button" style="margin-left: 10px; background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.3);" title="Logout">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
        
        <!-- Profile Header -->
        <header class="profile-header">
            <div class="header-content">
                <div class="profile-avatar">
                    <?php if ($user['avatar_url']): ?>
                        <img src="<?php echo htmlspecialchars($user['avatar_url']); ?>" alt="Avatar">
                    <?php else: ?>
                        <i class="fas fa-user"></i>
                    <?php endif; ?>
                </div>
                
                <div class="profile-info">
                    <h1 class="profile-name"><?php echo htmlspecialchars($user['username']); ?></h1>
                    <div class="profile-email"><?php echo htmlspecialchars($user['email']); ?></div>
                    
                    <div class="profile-stats">
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $stats['total_installed']; ?></span>
                            <span class="stat-label">Installed Apps</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $stats['total_favorites']; ?></span>
                            <span class="stat-label">Favorites</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $stats['total_reviews']; ?></span>
                            <span class="stat-label">Reviews</span>
                        </div>
                        <?php if ($stats['avg_rating'] > 0): ?>
                            <div class="stat-item">
                                <span class="stat-value"><?php echo number_format($stats['avg_rating'], 1); ?></span>
                                <span class="stat-label">Avg Rating</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Navigation Tabs -->
            <div class="nav-tabs">
                <a href="?page=installed" class="nav-tab <?php echo $page === 'installed' ? 'active' : ''; ?>">
                    <i class="fas fa-download"></i>
                    Installed Apps
                </a>
                <a href="?page=favorites" class="nav-tab <?php echo $page === 'favorites' ? 'active' : ''; ?>">
                    <i class="fas fa-heart"></i>
                    Favorites
                </a>
                <a href="?page=reviews" class="nav-tab <?php echo $page === 'reviews' ? 'active' : ''; ?>">
                    <i class="fas fa-star"></i>
                    My Reviews
                </a>
            </div>
            
            <!-- Page Content -->
            <?php if ($page === 'installed'): ?>
                <div>
                    <?php if (!empty($installed_apps)): ?>
                        <div class="apps-grid">
                            <?php foreach ($installed_apps as $app): ?>
                                <div class="app-card" onclick="openApp(<?php echo $app['id']; ?>)">
                                    <div class="app-thumbnail">
                                        <?php if ($app['thumbnail_url']): ?>
                                            <img src="<?php echo htmlspecialchars($app['thumbnail_url']); ?>" alt="<?php echo htmlspecialchars($app['title']); ?>">
                                        <?php else: ?>
                                            <i class="fas fa-rocket"></i>
                                        <?php endif; ?>
                                        <?php if ($app['is_favorite']): ?>
                                            <div class="favorite-badge">
                                                <i class="fas fa-heart"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="app-content">
                                        <h3 class="app-title"><?php echo htmlspecialchars($app['title']); ?></h3>
                                        <div class="app-developer">by <?php echo htmlspecialchars($app['developer_name']); ?></div>
                                        <div class="app-meta">
                                            <div class="app-rating">
                                                <i class="fas fa-star"></i>
                                                <?php echo number_format($app['rating'], 1); ?>
                                            </div>
                                            <div>
                                                Used <?php echo $app['usage_count']; ?> times
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-download"></i>
                            <h3>No installed apps yet</h3>
                            <p>Browse the marketplace and install some amazing apps!</p>
                            <a href="marketplace.php" class="btn" style="margin-top: 20px;">
                                <i class="fas fa-store"></i>
                                Browse Marketplace
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                
            <?php elseif ($page === 'favorites'): ?>
                <div>
                    <?php if (!empty($favorite_apps)): ?>
                        <div class="apps-grid">
                            <?php foreach ($favorite_apps as $app): ?>
                                <div class="app-card" onclick="openApp(<?php echo $app['id']; ?>)">
                                    <div class="app-thumbnail">
                                        <?php if ($app['thumbnail_url']): ?>
                                            <img src="<?php echo htmlspecialchars($app['thumbnail_url']); ?>" alt="<?php echo htmlspecialchars($app['title']); ?>">
                                        <?php else: ?>
                                            <i class="fas fa-heart"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="app-content">
                                        <h3 class="app-title"><?php echo htmlspecialchars($app['title']); ?></h3>
                                        <div class="app-developer">by <?php echo htmlspecialchars($app['developer_name']); ?></div>
                                        <div class="app-meta">
                                            <div class="app-rating">
                                                <i class="fas fa-star"></i>
                                                <?php echo number_format($app['rating'], 1); ?>
                                            </div>
                                            <div>
                                                <i class="fas fa-heart" style="color: #e74c3c;"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-heart"></i>
                            <h3>No favorite apps yet</h3>
                            <p>Start adding apps to your favorites to see them here!</p>
                        </div>
                    <?php endif; ?>
                </div>
                
            <?php elseif ($page === 'reviews'): ?>
                <div>
                    <?php if (!empty($user_reviews)): ?>
                        <?php foreach ($user_reviews as $review): ?>
                            <div class="review-item">
                                <div class="review-header">
                                    <div class="review-app-info">
                                        <div class="review-app-thumbnail">
                                            <?php if ($review['app_thumbnail']): ?>
                                                <img src="<?php echo htmlspecialchars($review['app_thumbnail']); ?>" alt="<?php echo htmlspecialchars($review['app_title']); ?>">
                                            <?php else: ?>
                                                <i class="fas fa-rocket" style="color: rgba(255,255,255,0.8); font-size: 1rem;"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="review-app-title"><?php echo htmlspecialchars($review['app_title']); ?></div>
                                            <div class="review-rating">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star" style="<?php echo $i <= $review['rating'] ? 'color: var(--btn-gradient-start);' : 'color: var(--text-muted);'; ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="review-date">
                                        <?php echo date('M j, Y', strtotime($review['created_at'])); ?>
                                    </div>
                                </div>
                                <?php if ($review['review']): ?>
                                    <div class="review-text"><?php echo htmlspecialchars($review['review']); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-star"></i>
                            <h3>No reviews yet</h3>
                            <p>Install and use apps, then share your experience with the community!</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function openApp(appId) {
            window.location.href = `app.php?id=${appId}`;
        }
    </script>
</body>
</html>
