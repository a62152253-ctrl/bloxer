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

// Helper function for time ago
function getTimeAgo($datetime) {
    if (!$datetime) return 'nigdy';
    
    try {
        $now = new DateTime();
        $past = new DateTime($datetime);
        $interval = $now->diff($past);
        
        if ($interval->days < 1) {
            if ($interval->h < 1) {
                return $interval->i <= 1 ? 'teraz' : $interval->i . ' min temu';
            }
            return $interval->h . 'h temu';
        } elseif ($interval->days < 7) {
            return $interval->days . ' dni temu';
        } elseif ($interval->days < 30) {
            $weeks = floor($interval->days / 7);
            return $weeks . ' tyg temu';
        } elseif ($interval->days < 365) {
            return $past->format('d M');
        } else {
            return $past->format('M Y');
        }
    } catch (Exception $e) {
        return 'nieznany czas';
    }
}

// Get new apps (published in last 7 days)
$conn = $auth->getConnection();
$stmt = $conn->prepare("
    SELECT a.*, u.username as developer_name, u.avatar_url as developer_avatar,
           c.name as category_name, c.icon as category_icon,
           CASE WHEN ul.app_id IS NOT NULL THEN 1 ELSE 0 END as is_installed,
           CASE WHEN uf.app_id IS NOT NULL THEN 1 ELSE 0 END as is_favorite
    FROM apps a
    JOIN users u ON a.developer_id = u.id
    LEFT JOIN categories c ON a.category_id = c.id
    LEFT JOIN user_apps ul ON a.id = ul.app_id AND ul.user_id = ?
    LEFT JOIN user_favorites uf ON a.id = uf.app_id AND uf.user_id = ?
    WHERE a.status = 'published' AND a.published_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY a.published_at DESC
    LIMIT 8
");
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    $new_apps = [];
} else {
    $stmt->bind_param("ii", $user['id'], $user['id']);
    if ($stmt->execute()) {
        $new_apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        error_log("Execute failed: " . $stmt->error);
        $new_apps = [];
    }
}

// Get recommended apps (highly rated and popular)
$stmt = $conn->prepare("
    SELECT a.*, u.username as developer_name, u.avatar_url as developer_avatar,
           c.name as category_name, c.icon as category_icon,
           CASE WHEN ul.app_id IS NOT NULL THEN 1 ELSE 0 END as is_installed,
           CASE WHEN uf.app_id IS NOT NULL THEN 1 ELSE 0 END as is_favorite
    FROM apps a
    JOIN users u ON a.project_id IN (SELECT id FROM projects WHERE user_id = u.id)
    LEFT JOIN categories c ON a.category = c.slug
    LEFT JOIN user_library ul ON a.id = ul.app_id AND ul.user_id = ?
    LEFT JOIN user_favorites uf ON a.id = uf.app_id AND uf.user_id = ?
    WHERE a.status = 'published' AND a.rating >= 4.0 AND a.download_count >= 50
    ORDER BY a.rating DESC, a.download_count DESC
    LIMIT 6
");
$stmt->bind_param("ii", $user['id'], $user['id']);
$stmt->execute();
$recommended_apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get user's installed apps
$stmt = $conn->prepare("
    SELECT a.*, u.username as developer_name, u.avatar_url as developer_avatar,
           c.name as category_name, c.icon as category_icon,
           ul.installed_at, ul.last_used_at
    FROM user_library ul
    JOIN apps a ON ul.app_id = a.id
    JOIN users u ON a.project_id IN (SELECT id FROM projects WHERE user_id = u.id)
    LEFT JOIN categories c ON a.category = c.slug
    WHERE ul.user_id = ?
    ORDER BY ul.last_used_at DESC, ul.installed_at DESC
    LIMIT 6
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$installed_apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get user's favorite apps
$stmt = $conn->prepare("
    SELECT a.*, u.username as developer_name, u.avatar_url as developer_avatar,
           c.name as category_name, c.icon as category_icon,
           uf.created_at as favorited_at
    FROM user_favorites uf
    JOIN apps a ON uf.app_id = a.id
    JOIN users u ON a.project_id IN (SELECT id FROM projects WHERE user_id = u.id)
    LEFT JOIN categories c ON a.category = c.slug
    WHERE uf.user_id = ?
    ORDER BY uf.created_at DESC
    LIMIT 6
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$favorite_apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get categories
$stmt = $conn->prepare("SELECT * FROM categories WHERE is_active = true ORDER BY sort_order LIMIT 8");
$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get user stats
$stmt = $conn->prepare("
    SELECT 
        COUNT(DISTINCT ul.app_id) as installed_count,
        COUNT(DISTINCT uf.app_id) as favorite_count,
        COUNT(DISTINCT ar.app_id) as rated_count
    FROM users u
    LEFT JOIN user_library ul ON u.id = ul.user_id
    LEFT JOIN user_favorites uf ON u.id = uf.user_id
    LEFT JOIN app_ratings ar ON u.id = ar.user_id
    WHERE u.id = ?
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$user_stats = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - Bloxer</title>
    <link rel="stylesheet" href="user_dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .dashboard-container {
            min-height: 100vh;
            background: var(--deep-black);
            background: linear-gradient(180deg, var(--deep-black) 0%, var(--deep-black-gradient) 100%);
        }
        
        .header {
            background: rgba(255,255,255,0.04);
            border-bottom: 1px solid var(--glass-border);
            padding: 20px;
            backdrop-filter: blur(20px);
        }
        
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text-primary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .logo::before {
            content: '⬡';
            font-size: 2.5rem;
            opacity: 0.8;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid var(--glass-border);
        }
        
        .user-menu {
            display: flex;
            gap: 10px;
        }
        
        .nav-button {
            padding: 10px 20px;
            background: rgba(255,255,255,0.06);
            border: 1px solid var(--glass-border);
            border-radius: 25px;
            color: var(--text-primary);
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .nav-button:hover {
            background: rgba(255,255,255,0.1);
            transform: translateY(-1px);
        }
        
        .nav-button.active {
            background: var(--btn-gradient-start);
            border-color: var(--btn-gradient-start);
        }
        
        .main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .welcome-section {
            text-align: center;
            margin-bottom: 50px;
        }
        
        .welcome-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 10px;
            background: linear-gradient(135deg, var(--btn-gradient-start) 0%, var(--btn-gradient-end) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .welcome-subtitle {
            color: var(--text-secondary);
            font-size: 1.2rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 50px;
        }
        
        .stat-card {
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 25px;
            text-align: center;
            backdrop-filter: blur(20px);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            background: rgba(255,255,255,0.06);
            transform: translateY(-2px);
        }
        
        .stat-icon {
            font-size: 2rem;
            margin-bottom: 15px;
            color: var(--btn-gradient-start);
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--text-primary);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: var(--text-secondary);
        }
        
        .section {
            margin-bottom: 50px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .section-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-link {
            color: var(--btn-gradient-start);
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }
        
        .section-link:hover {
            gap: 10px;
        }
        
        .apps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .app-card {
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.3s ease;
            cursor: pointer;
            backdrop-filter: blur(10px);
            position: relative;
        }
        
        .app-card:hover {
            transform: translateY(-5px);
            background: rgba(255,255,255,0.06);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .app-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--success);
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            z-index: 1;
        }
        
        .app-badge.new {
            background: var(--btn-gradient-start);
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
        
        .app-content {
            padding: 20px;
        }
        
        .app-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text-primary);
        }
        
        .app-description {
            color: var(--text-secondary);
            font-size: 0.85rem;
            margin-bottom: 12px;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .app-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .app-category {
            display: flex;
            align-items: center;
            gap: 5px;
            color: var(--text-muted);
            font-size: 0.75rem;
        }
        
        .app-rating {
            display: flex;
            align-items: center;
            gap: 5px;
            color: var(--btn-gradient-start);
            font-size: 0.8rem;
        }
        
        .app-developer {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
        }
        
        .developer-avatar {
            width: 20px;
            height: 20px;
            border-radius: 50%;
        }
        
        .developer-name {
            color: var(--text-secondary);
            font-size: 0.8rem;
        }
        
        .app-actions {
            display: flex;
            gap: 8px;
        }
        
        .app-btn {
            flex: 1;
            padding: 8px 12px;
            border: none;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        .app-btn-primary {
            background: var(--btn-gradient-start);
            color: white;
        }
        
        .app-btn-primary:hover {
            background: var(--btn-gradient-end);
        }
        
        .app-btn-secondary {
            background: rgba(255,255,255,0.06);
            color: var(--text-primary);
            border: 1px solid var(--glass-border);
        }
        
        .app-btn-secondary:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .app-btn.installed {
            background: var(--success);
            color: white;
        }
        
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }
        
        .category-card {
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: var(--text-primary);
        }
        
        .category-card:hover {
            background: rgba(255,255,255,0.06);
            transform: translateY(-2px);
        }
        
        .category-icon {
            font-size: 2rem;
            margin-bottom: 10px;
            color: var(--btn-gradient-start);
        }
        
        .category-name {
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
            
            .welcome-title {
                font-size: 2rem;
            }
            
            .apps-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <a href="user_dashboard.php" class="logo">
                    Bloxer
                </a>
                
                <div class="user-info">
                    <div class="user-menu">
                        <a href="user_dashboard.php" class="nav-button active">
                            <i class="fas fa-home"></i>
                            <span>Dashboard</span>
                        </a>
                        <a href="marketplace.php" class="nav-button">
                            <i class="fas fa-store"></i>
                            <span>Marketplace</span>
                        </a>
                        <a href="profile.php" class="nav-button">
                            <i class="fas fa-user"></i>
                            <span>Profile</span>
                        </a>
                        <a href="logout.php" class="nav-button" style="background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.3);">
                            <i class="fas fa-sign-out-alt"></i>
                        </a>
                    </div>
                    
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <img src="<?php echo $user['avatar_url'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($user['username']) . '&background=6366f1&color=fff'; ?>" 
                             alt="Avatar" class="user-avatar">
                        <span style="color: var(--text-primary); font-weight: 600;"><?php echo htmlspecialchars($user['username']); ?></span>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Welcome Section -->
            <section class="welcome-section">
                <h1 class="welcome-title">Witaj z powrotem, <?php echo htmlspecialchars($user['username']); ?>!</h1>
                <p class="welcome-subtitle">Odkrywaj nowe aplikacje i zarządzaj swoimi ulubionymi</p>
            </section>
            
            <!-- User Stats -->
            <section class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-download"></i>
                    </div>
                    <div class="stat-value"><?php echo $user_stats['installed_count']; ?></div>
                    <div class="stat-label">Zainstalowane aplikacje</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                    <div class="stat-value"><?php echo $user_stats['favorite_count']; ?></div>
                    <div class="stat-label">Ulubione</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-value"><?php echo $user_stats['rated_count']; ?></div>
                    <div class="stat-label">Ocenione</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-value">24h</div>
                    <div class="stat-label">Ostatnia aktywność</div>
                </div>
            </section>
            
            <!-- New Apps -->
            <?php if (!empty($new_apps)): ?>
                <section class="section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-sparkles"></i>
                            Nowe aplikacje
                        </h2>
                        <a href="marketplace.php?sort=newest" class="section-link">
                            Zobacz wszystkie
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    
                    <div class="apps-grid">
                        <?php foreach ($new_apps as $app): ?>
                            <div class="app-card" onclick="openApp(<?php echo $app['id']; ?>)">
                                <div class="app-badge new">NOWE</div>
                                <div class="app-thumbnail">
                                    <?php if ($app['thumbnail_url']): ?>
                                        <img src="<?php echo htmlspecialchars($app['thumbnail_url']); ?>" alt="<?php echo htmlspecialchars($app['title']); ?>">
                                    <?php else: ?>
                                        <i class="fas fa-rocket"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="app-content">
                                    <div class="app-developer">
                                        <img src="<?php echo $app['developer_avatar'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($app['developer_name']) . '&background=6366f1&color=fff'; ?>" 
                                             alt="Developer" class="developer-avatar">
                                        <span class="developer-name"><?php echo htmlspecialchars($app['developer_name']); ?></span>
                                    </div>
                                    <h3 class="app-title"><?php echo htmlspecialchars($app['title']); ?></h3>
                                    <p class="app-description"><?php echo htmlspecialchars($app['short_description'] ?? substr($app['description'], 0, 80) . '...'); ?></p>
                                    <div class="app-meta">
                                        <div class="app-category">
                                            <i class="fas fa-<?php echo $app['category_icon'] ?? 'folder'; ?>"></i>
                                            <?php echo htmlspecialchars($app['category_name'] ?? 'General'); ?>
                                        </div>
                                        <div class="app-rating">
                                            <i class="fas fa-star"></i>
                                            <?php echo number_format($app['rating'], 1); ?>
                                        </div>
                                    </div>
                                    <div class="app-actions">
                                        <?php if ($app['is_installed']): ?>
                                            <button class="app-btn installed" onclick="event.stopPropagation(); useApp(<?php echo $app['id']; ?>)">
                                                <i class="fas fa-play"></i>
                                                Uruchom
                                            </button>
                                        <?php else: ?>
                                            <button class="app-btn app-btn-primary" onclick="event.stopPropagation(); installApp(<?php echo $app['id']; ?>)">
                                                <i class="fas fa-download"></i>
                                                Zainstaluj
                                            </button>
                                        <?php endif; ?>
                                        <button class="app-btn app-btn-secondary" onclick="event.stopPropagation(); toggleFavorite(<?php echo $app['id']; ?>, <?php echo $app['is_favorite']; ?>)">
                                            <i class="fas fa-heart<?php echo $app['is_favorite'] ? '' : '-o'; ?>"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
            
            <!-- Recommended Apps -->
            <?php if (!empty($recommended_apps)): ?>
                <section class="section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-fire"></i>
                            Polecane aplikacje
                        </h2>
                        <a href="marketplace.php?sort=rating" class="section-link">
                            Zobacz więcej
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    
                    <div class="apps-grid">
                        <?php foreach ($recommended_apps as $app): ?>
                            <div class="app-card" onclick="openApp(<?php echo $app['id']; ?>)">
                                <div class="app-thumbnail">
                                    <?php if ($app['thumbnail_url']): ?>
                                        <img src="<?php echo htmlspecialchars($app['thumbnail_url']); ?>" alt="<?php echo htmlspecialchars($app['title']); ?>">
                                    <?php else: ?>
                                        <i class="fas fa-star"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="app-content">
                                    <div class="app-developer">
                                        <img src="<?php echo $app['developer_avatar'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($app['developer_name']) . '&background=6366f1&color=fff'; ?>" 
                                             alt="Developer" class="developer-avatar">
                                        <span class="developer-name"><?php echo htmlspecialchars($app['developer_name']); ?></span>
                                    </div>
                                    <h3 class="app-title"><?php echo htmlspecialchars($app['title']); ?></h3>
                                    <p class="app-description"><?php echo htmlspecialchars($app['short_description'] ?? substr($app['description'], 0, 80) . '...'); ?></p>
                                    <div class="app-meta">
                                        <div class="app-category">
                                            <i class="fas fa-<?php echo $app['category_icon'] ?? 'folder'; ?>"></i>
                                            <?php echo htmlspecialchars($app['category_name'] ?? 'General'); ?>
                                        </div>
                                        <div class="app-rating">
                                            <i class="fas fa-star"></i>
                                            <?php echo number_format($app['rating'], 1); ?>
                                        </div>
                                    </div>
                                    <div class="app-actions">
                                        <?php if ($app['is_installed']): ?>
                                            <button class="app-btn installed" onclick="event.stopPropagation(); useApp(<?php echo $app['id']; ?>)">
                                                <i class="fas fa-play"></i>
                                                Uruchom
                                            </button>
                                        <?php else: ?>
                                            <button class="app-btn app-btn-primary" onclick="event.stopPropagation(); installApp(<?php echo $app['id']; ?>)">
                                                <i class="fas fa-download"></i>
                                                Zainstaluj
                                            </button>
                                        <?php endif; ?>
                                        <button class="app-btn app-btn-secondary" onclick="event.stopPropagation(); toggleFavorite(<?php echo $app['id']; ?>, <?php echo $app['is_favorite']; ?>)">
                                            <i class="fas fa-heart<?php echo $app['is_favorite'] ? '' : '-o'; ?>"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
            
            <!-- Categories -->
            <section class="section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-th-large"></i>
                        Kategorie
                    </h2>
                    <a href="marketplace.php" class="section-link">
                        Wszystkie kategorie
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                
                <div class="categories-grid">
                    <?php foreach ($categories as $category): ?>
                        <a href="marketplace.php?category=<?php echo urlencode($category['slug']); ?>" class="category-card">
                            <div class="category-icon">
                                <i class="fas fa-<?php echo $category['icon'] ?? 'folder'; ?>"></i>
                            </div>
                            <div class="category-name"><?php echo htmlspecialchars($category['name']); ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
            
            <!-- Recently Used -->
            <?php if (!empty($installed_apps)): ?>
                <section class="section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-history"></i>
                            Ostatnio używane
                        </h2>
                        <a href="profile.php" class="section-link">
                            Biblioteka
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    
                    <div class="apps-grid">
                        <?php foreach ($installed_apps as $app): ?>
                            <div class="app-card" onclick="openApp(<?php echo $app['id']; ?>)">
                                <div class="app-thumbnail">
                                    <?php if ($app['thumbnail_url']): ?>
                                        <img src="<?php echo htmlspecialchars($app['thumbnail_url']); ?>" alt="<?php echo htmlspecialchars($app['title']); ?>">
                                    <?php else: ?>
                                        <i class="fas fa-play"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="app-content">
                                    <div class="app-developer">
                                        <img src="<?php echo $app['developer_avatar'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($app['developer_name']) . '&background=6366f1&color=fff'; ?>" 
                                             alt="Developer" class="developer-avatar">
                                        <span class="developer-name"><?php echo htmlspecialchars($app['developer_name']); ?></span>
                                    </div>
                                    <h3 class="app-title"><?php echo htmlspecialchars($app['title']); ?></h3>
                                    <p class="app-description"><?php echo htmlspecialchars($app['short_description'] ?? substr($app['description'], 0, 80) . '...'); ?></p>
                                    <div class="app-meta">
                                        <div class="app-category">
                                            <i class="fas fa-<?php echo $app['category_icon'] ?? 'folder'; ?>"></i>
                                            <?php echo htmlspecialchars($app['category_name'] ?? 'General'); ?>
                                        </div>
                                        <small style="color: var(--text-muted);">
                                            Użyto <?php echo getTimeAgo($app['last_used_at'] ?? $app['installed_at']); ?>
                                        </small>
                                    </div>
                                    <div class="app-actions">
                                        <button class="app-btn installed" onclick="event.stopPropagation(); useApp(<?php echo $app['id']; ?>)">
                                            <i class="fas fa-play"></i>
                                            Uruchom
                                        </button>
                                        <button class="app-btn app-btn-secondary" onclick="event.stopPropagation(); toggleFavorite(<?php echo $app['id']; ?>, <?php echo $app['is_favorite'] ?? 0; ?>)">
                                            <i class="fas fa-heart<?php echo $app['is_favorite'] ? '' : '-o'; ?>"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
        function openApp(appId) {
            window.location.href = `app.php?id=${appId}`;
        }
        
        function installApp(appId) {
            fetch('user_dashboard.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'install_app',
                    app_id: appId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }
        
        function useApp(appId) {
            window.location.href = `run_app.php?id=${appId}`;
        }
        
        function toggleFavorite(appId, isFavorite) {
            const action = isFavorite ? 'unfavorite' : 'favorite';
            
            fetch('user_dashboard.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: action,
                    app_id: appId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }
        
        function time_ago(date) {
            const now = new Date();
            const past = new Date(date);
            const seconds = Math.floor((now - past) / 1000);
            
            if (seconds < 60) return 'teraz';
            if (seconds < 3600) return Math.floor(seconds / 60) + ' min temu';
            if (seconds < 86400) return Math.floor(seconds / 3600) + 'h temu';
            if (seconds < 604800) return Math.floor(seconds / 86400) + ' dni temu';
            return Math.floor(seconds / 604800) + ' tyg temu';
        }
    </script>
</body>
</html>

<?php
// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $app_id = $_POST['app_id'] ?? null;
    
    if ($app_id) {
        $conn = $auth->getConnection();
        
        switch ($action) {
            case 'install_app':
                $stmt = $conn->prepare("INSERT IGNORE INTO user_library (user_id, app_id) VALUES (?, ?)");
                $stmt->bind_param("ii", $user['id'], $app_id);
                $stmt->execute();
                
                // Update download count
                $stmt = $conn->prepare("UPDATE apps SET download_count = download_count + 1 WHERE id = ?");
                $stmt->bind_param("i", $app_id);
                $stmt->execute();
                
                echo json_encode(['success' => true]);
                exit();
                
            case 'favorite':
                $stmt = $conn->prepare("INSERT IGNORE INTO user_favorites (user_id, app_id) VALUES (?, ?)");
                $stmt->bind_param("ii", $user['id'], $app_id);
                $stmt->execute();
                echo json_encode(['success' => true]);
                exit();
                
            case 'unfavorite':
                $stmt = $conn->prepare("DELETE FROM user_favorites WHERE user_id = ? AND app_id = ?");
                $stmt->bind_param("ii", $user['id'], $app_id);
                $stmt->execute();
                echo json_encode(['success' => true]);
                exit();
        }
    }
    
    echo json_encode(['success' => false]);
    exit();
}
?>
