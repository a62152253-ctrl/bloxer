<?php
require_once 'mainlogincore.php';

$auth = new AuthCore();

// Redirect regular users to user dashboard
if ($auth->isLoggedIn() && !$auth->isDeveloper()) {
    header('Location: user_dashboard.php');
    exit();
}

$user = $auth->getCurrentUser();

// Get query parameters
$category = $_GET['category'] ?? 'all';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'popular';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

// Build query
$conn = $auth->getConnection();
$where_conditions = ["a.status = 'published'"];
$params = [];
$types = '';

if ($category !== 'all') {
    $where_conditions[] = "a.category = ?";
    $params[] = $category;
    $types .= 's';
}

if (!empty($search)) {
    $where_conditions[] = "(MATCH(a.title, a.description, a.short_description) AGAINST(?) OR a.title LIKE ? OR a.description LIKE ?)";
    $params[] = $search;
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'sss';
}

$where_clause = "WHERE " . implode(' AND ', $where_conditions);

// Sort options
$sort_options = [
    'popular' => 'a.download_count DESC, a.rating DESC',
    'newest' => 'a.published_at DESC',
    'rating' => 'a.rating DESC, a.download_count DESC',
    'name' => 'a.title ASC'
];
$order_by = $sort_options[$sort] ?? $sort_options['popular'];

// Get total count
$count_sql = "
    SELECT COUNT(*) as total 
    FROM apps a 
    $where_clause
";
$stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_apps = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_apps / $limit);

// Get apps
$apps_sql = "
    SELECT a.*, u.username as developer_name, u.avatar_url as developer_avatar,
           c.name as category_name, c.icon as category_icon
    FROM apps a
    JOIN users u ON a.project_id IN (SELECT id FROM projects WHERE user_id = u.id)
    LEFT JOIN categories c ON a.category = c.slug
    $where_clause
    ORDER BY $order_by
    LIMIT ? OFFSET ?
";
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($apps_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get categories
$stmt = $conn->prepare("SELECT * FROM categories WHERE is_active = true ORDER BY sort_order");
$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get featured apps
$stmt = $conn->prepare("
    SELECT a.*, u.username as developer_name, c.name as category_name
    FROM apps a
    JOIN users u ON a.project_id IN (SELECT id FROM projects WHERE user_id = u.id)
    LEFT JOIN categories c ON a.category = c.slug
    WHERE a.status = 'featured' 
    ORDER BY a.rating DESC, a.download_count DESC
    LIMIT 6
");
$stmt->execute();
$featured_apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bloxer - Web Application Marketplace</title>
    <link rel="stylesheet" href="marketplace.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .marketplace-container {
            min-height: 100vh;
            background: var(--deep-black);
            background: linear-gradient(180deg, var(--deep-black) 0%, var(--deep-black-gradient) 100%);
        }
        
        .hero-section {
            background: linear-gradient(135deg, var(--btn-gradient-start) 0%, var(--btn-gradient-end) 100%);
            padding: 80px 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIzMDAiIGhlaWdodD0iMzAwIj48ZmlsdGVyIGlkPSJhIj48ZmVUdXJidWxlbmNlIHR5cGU9ImZyYWN0YWxOb2lzZSIgYmFzZUZyZXF1ZW5jeT0iLjc1IiBudW1PY3RhdmVzPSI0IiAvPjxmZUNvbG9yTWF0cml4IHR5cGU9InNhdHVyYXRlIiB2YWx1ZXM9IjAiLz48ZmVDb21wb3NpdGUgb3BlcmF0b3I9ImluIi8+PC9maWx0ZXI+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsdGVyPSJ1cmwoI2EpIiBvcGFjaXR5PSIwLjAyIi8+PC9zdmc+');
            opacity: 0.1;
        }
        
        .hero-content {
            position: relative;
            z-index: 1;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 20px;
            text-shadow: 0 0 30px rgba(255, 255, 255, 0.3);
        }
        
        .hero-subtitle {
            font-size: 1.3rem;
            margin-bottom: 40px;
            opacity: 0.9;
        }
        
        .search-container {
            max-width: 600px;
            margin: 0 auto;
            position: relative;
        }
        
        .search-box {
            width: 100%;
            padding: 20px 60px 20px 25px;
            background: rgba(255,255,255,0.1);
            border: 2px solid rgba(255,255,255,0.2);
            border-radius: 50px;
            font-size: 1.1rem;
            color: white;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }
        
        .search-box::placeholder {
            color: rgba(255,255,255,0.7);
        }
        
        .search-box:focus {
            outline: none;
            border-color: rgba(255,255,255,0.4);
            background: rgba(255,255,255,0.15);
        }
        
        .search-button {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255,255,255,0.2);
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .search-button:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .filters-section {
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 40px;
            backdrop-filter: blur(20px);
        }
        
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-tab {
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
        
        .filter-tab:hover {
            background: rgba(255,255,255,0.08);
            color: var(--text-primary);
        }
        
        .filter-tab.active {
            background: var(--btn-gradient-start);
            color: var(--text-primary);
            border-color: var(--btn-gradient-start);
        }
        
        .sort-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .sort-dropdown {
            padding: 10px 20px;
            background: rgba(255,255,255,0.06);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            color: var(--text-primary);
            cursor: pointer;
        }
        
        .results-info {
            color: var(--text-secondary);
        }
        
        .apps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .app-card {
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.3s ease;
            cursor: pointer;
            backdrop-filter: blur(10px);
        }
        
        .app-card:hover {
            transform: translateY(-5px);
            background: rgba(255,255,255,0.06);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .app-thumbnail {
            width: 100%;
            height: 200px;
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
            font-size: 3rem;
            color: rgba(255,255,255,0.8);
        }
        
        .app-content {
            padding: 20px;
        }
        
        .app-title {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text-primary);
        }
        
        .app-description {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 15px;
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
            margin-bottom: 15px;
        }
        
        .app-category {
            display: flex;
            align-items: center;
            gap: 5px;
            color: var(--text-muted);
            font-size: 0.8rem;
        }
        
        .app-rating {
            display: flex;
            align-items: center;
            gap: 5px;
            color: var(--btn-gradient-start);
            font-size: 0.9rem;
        }
        
        .app-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid var(--glass-border);
            gap: 10px;
        }
        
        .app-downloads {
            display: flex;
            align-items: center;
            gap: 5px;
            color: var(--text-muted);
            font-size: 0.8rem;
        }
        
        .app-price {
            font-weight: 700;
            color: var(--success);
        }

        .app-card-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 18px;
        }

        .btn-card {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 16px;
            border-radius: 999px;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid transparent;
            transition: all 0.25s ease;
        }

        .btn-card.btn-primary {
            background: var(--btn-gradient-start);
            color: white;
        }

        .btn-card.btn-secondary {
            background: rgba(255,255,255,0.08);
            color: var(--text-primary);
            border-color: rgba(255,255,255,0.14);
        }

        .btn-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 18px rgba(0, 0, 0, 0.18);
        }
        
        .app-developer {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 15px;
        }
        
        .developer-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
        }
        
        .developer-name {
            color: var(--text-secondary);
            font-size: 0.85rem;
        }
        
        .featured-section {
            margin-bottom: 60px;
        }
        
        .section-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 30px;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .featured-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
        }
        
        .featured-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.08) 0%, rgba(255,255,255,0.04) 100%);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 30px;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(20px);
        }
        
        .featured-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--btn-gradient-start) 0%, var(--btn-gradient-end) 100%);
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 40px;
        }
        
        .page-link {
            padding: 10px 15px;
            background: rgba(255,255,255,0.06);
            border: 1px solid var(--glass-border);
            border-radius: 8px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .page-link:hover {
            background: rgba(255,255,255,0.08);
            color: var(--text-primary);
        }
        
        .page-link.active {
            background: var(--btn-gradient-start);
            color: var(--text-primary);
            border-color: var(--btn-gradient-start);
        }
        
        .no-results {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }
        
        .no-results i {
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
            border: 1px solid var(--glass-border);
            border-radius: 50px;
            padding: 10px 20px;
            color: var(--text-primary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }
        
        .user-button:hover {
            background: rgba(255,255,255,0.15);
        }
        
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .apps-grid {
                grid-template-columns: 1fr;
            }
            
            .featured-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-tabs {
                justify-content: center;
            }
            
            .sort-controls {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <div class="marketplace-container">
        <!-- User Menu -->
        <div class="user-menu">
            <?php if ($auth->isLoggedIn()): ?>
                <a href="<?php echo $auth->isDeveloper() ? 'dashboard.php' : 'profile.php'; ?>" class="user-button">
                    <img src="<?php echo $user['avatar_url'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($user['username']) . '&background=6366f1&color=fff'; ?>" 
                         alt="Avatar" style="width: 24px; height: 24px; border-radius: 50%;">
                    <span><?php echo htmlspecialchars($user['username']); ?></span>
                    <i class="fas fa-<?php echo $auth->isDeveloper() ? 'code' : 'user'; ?>"></i>
                </a>
                <a href="logout.php" class="user-button" style="margin-left: 10px; background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.3);" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            <?php else: ?>
                <a href="login.php" class="user-button">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Login</span>
                </a>
            <?php endif; ?>
        </div>
        
        <!-- Hero Section -->
        <section class="hero-section">
            <div class="hero-content">
                <h1 class="hero-title">Bloxer</h1>
                <p class="hero-subtitle">Discover ready-made offers, preview live demos and contact developers in one sleek marketplace.</p>
                
                <div class="search-container">
                    <form method="GET" action="marketplace.php">
                        <input type="text" name="search" class="search-box" 
                               placeholder="Search for apps, games, tools..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="search-button">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
            </div>
        </section>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Featured Apps -->
            <?php if (!empty($featured_apps) && $category === 'all' && empty($search)): ?>
                <div class="featured-section">
                    <h2 class="section-title">
                        <i class="fas fa-star"></i>
                        Featured Applications
                    </h2>
                    <div class="featured-grid">
                        <?php foreach ($featured_apps as $app): ?>
                            <div class="featured-card" onclick="openApp(<?php echo $app['id']; ?>)">
                                <div class="app-developer">
                                    <img src="<?php echo $app['developer_avatar'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($app['developer_name']) . '&background=6366f1&color=fff'; ?>" 
                                         alt="Developer" class="developer-avatar">
                                    <span class="developer-name"><?php echo htmlspecialchars($app['developer_name']); ?></span>
                                </div>
                                <h3 class="app-title"><?php echo htmlspecialchars($app['title']); ?></h3>
                                <p class="app-description"><?php echo htmlspecialchars($app['short_description'] ?? substr($app['description'], 0, 100) . '...'); ?></p>
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
                                <div class="app-stats">
                                    <div class="app-downloads">
                                        <i class="fas fa-download"></i>
                                        <?php echo number_format($app['download_count']); ?> downloads
                                    </div>
                                    <div class="app-price">
                                        <?php echo $app['is_free'] ? 'Free' : '$' . number_format($app['price'], 2); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Filters Section -->
            <div class="filters-section">
                <div class="filter-tabs">
                    <a href="?category=all&search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>" 
                       data-category="all"
                       class="filter-tab <?php echo $category === 'all' ? 'active' : ''; ?>">
                        <i class="fas fa-th"></i>
                        All Apps
                    </a>
                    <?php foreach ($categories as $cat): ?>
                        <a href="?category=<?php echo urlencode($cat['slug']); ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>" 
                           data-category="<?php echo htmlspecialchars($cat['slug']); ?>"
                           class="filter-tab <?php echo $category === $cat['slug'] ? 'active' : ''; ?>">
                            <i class="fas fa-<?php echo $cat['icon'] ?? 'folder'; ?>"></i>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                
                <div class="sort-controls">
                    <div class="results-info">
                        <?php echo $total_apps; ?> applications found
                    </div>
                    <form method="GET" action="marketplace.php" style="display: flex; gap: 10px;">
                        <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                        <select name="sort" class="sort-dropdown" onchange="this.form.submit()">
                            <option value="popular" <?php echo $sort === 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                            <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="rating" <?php echo $sort === 'rating' ? 'selected' : ''; ?>>Highest Rated</option>
                            <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Name (A-Z)</option>
                        </select>
                    </form>
                </div>
            </div>
            
            <!-- Apps Grid -->
            <?php if (!empty($apps)): ?>
                <div class="apps-grid">
                    <?php foreach ($apps as $app): ?>
                        <div class="app-card" onclick="openApp(<?php echo $app['id']; ?>)">
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
                                <p class="app-description"><?php echo htmlspecialchars($app['short_description'] ?? substr($app['description'], 0, 100) . '...'); ?></p>
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
                                <div class="app-stats">
                                    <div class="app-downloads">
                                        <i class="fas fa-download"></i>
                                        <?php echo number_format($app['download_count']); ?>
                                    </div>
                                    <div class="app-price">
                                        <?php echo $app['is_free'] ? 'Free' : '$' . number_format($app['price'], 2); ?>
                                    </div>
                                </div>

                                <div class="app-card-actions">
                                    <a href="app.php?id=<?php echo $app['id']; ?>" class="btn-card btn-secondary" onclick="event.stopPropagation();">
                                        <i class="fas fa-eye"></i>
                                        View offer
                                    </a>
                                    <?php if (!empty($app['demo_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($app['demo_url']); ?>" target="_blank" rel="noreferrer noopener" class="btn-card btn-primary" onclick="event.stopPropagation();">
                                            <i class="fas fa-play-circle"></i>
                                            Live preview
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?category=<?php echo urlencode($category); ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>&page=<?php echo $page - 1; ?>" 
                               class="page-link">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <a href="?category=<?php echo urlencode($category); ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>&page=<?php echo $i; ?>" 
                               class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?category=<?php echo urlencode($category); ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>&page=<?php echo $page + 1; ?>" 
                               class="page-link">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-search"></i>
                    <h3>No applications found</h3>
                    <p>Try adjusting your search terms or browse different categories.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="marketplace-enhanced.js"></script>
    <script>
        // Fallback functions for non-enhanced version
        function openApp(appId) {
            window.location.href = `app-details.php?id=${appId}`;
        }
        
        // Auto-search functionality
        let searchTimeout;
        const searchBox = document.querySelector('.search-box');
        if (searchBox) {
            searchBox.addEventListener('input', function(e) {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    if (e.target.value.length >= 2 || e.target.value.length === 0) {
                        e.target.form.submit();
                    }
                }, 500);
            });
        }
        
        // Add smooth scroll behavior
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });
        
        // Initialize enhanced features if available
        if (typeof MarketplaceEnhanced !== 'undefined') {
            // Enhanced features will be initialized automatically
        }
    </script>
</body>
</html>
