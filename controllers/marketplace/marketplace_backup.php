<?php
require_once '../core/mainlogincore.php';

$auth = new AuthCore();

// Users can access marketplace directly

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
    JOIN projects p ON a.project_id = p.id
    JOIN users u ON p.user_id = u.id
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
    JOIN projects p ON a.project_id = p.id
    JOIN users u ON p.user_id = u.id
    LEFT JOIN categories c ON a.category = c.slug
    WHERE a.status = 'featured' 
    ORDER BY a.rating DESC, a.download_count DESC
    LIMIT 6
");
$stmt->execute();
$featured_apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get recommended apps (highly rated with good download counts)
$stmt = $conn->prepare("
    SELECT a.*, u.username as developer_name, c.name as category_name
    FROM apps a
    JOIN projects p ON a.project_id = p.id
    JOIN users u ON p.user_id = u.id
    LEFT JOIN categories c ON a.category = c.slug
    WHERE a.status = 'published' AND a.rating >= 4.0 AND a.download_count >= 50
    ORDER BY (a.rating * 0.6 + (a.download_count / 1000) * 0.4) DESC
    LIMIT 8
");
$stmt->execute();
$recommended_apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get trending apps (recent high activity)
$stmt = $conn->prepare("
    SELECT a.*, u.username as developer_name, c.name as category_name
    FROM apps a
    JOIN projects p ON a.project_id = p.id
    JOIN users u ON p.user_id = u.id
    LEFT JOIN categories c ON a.category = c.slug
    WHERE a.status = 'published' AND a.published_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY (a.download_count / DATEDIFF(NOW(), a.published_at)) DESC, a.rating DESC
    LIMIT 6
");
$stmt->execute();
$trending_apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bloxer - Web Application Marketplace</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/marketplace.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="marketplace-container">
        <!-- Modern Navbar -->
        <nav class="marketplace-nav">
            <div class="nav-brand">
                <a href="../marketplace/marketplace.php"><i class="fas fa-rocket"></i> Bloxer</a>
            </div>
            
            <div class="nav-search-mini">
                <form action="../marketplace/marketplace.php" method="GET">
                    <input type="text" name="search" placeholder="Szukaj..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>
            </div>

            <div class="nav-links">
                <?php if ($auth->isLoggedIn()): ?>
                    <a href="marketplace-settings.php" class="nav-link">
                        <i class="fas fa-cog"></i>
                        <span>Ustawienia</span>
                    </a>
                <?php endif; ?>
            </div>

            <div class="nav-user">
                <?php if ($auth->isLoggedIn()): ?>
                    <div class="user-profile-dropdown">
                        <a href="<?php echo $auth->isDeveloper() ? '../core/dashboard.php' : '../user/profile.php'; ?>" class="user-pill">
                            <div class="user-avatar-mini">
                                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                            </div>
                            <span><?php echo htmlspecialchars($user['username']); ?></span>
                        </a>
                        <div class="dropdown-menu">
                            <a href="<?php echo $auth->isDeveloper() ? '../core/dashboard.php' : '../user/profile.php'; ?>" class="dropdown-item">
                                <i class="fas fa-user"></i> Profil
                            </a>
                            <a href="marketplace-settings.php" class="dropdown-item">
                                <i class="fas fa-cog"></i> Ustawienia
                            </a>
                            <?php if ($auth->isDeveloper()): ?>
                                <a href="projects.php" class="dropdown-item">
                                    <i class="fas fa-folder"></i> Moje Projekty
                                </a>
                                <a href="publish.php" class="dropdown-item">
                                    <i class="fas fa-upload"></i> Publikuj
                                </a>
                            <?php endif; ?>
                            <div class="dropdown-divider"></div>
                            <a href="../controllers/auth/logout.php" class="dropdown-item logout-item">
                                <i class="fas fa-sign-out-alt"></i> Wyloguj
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="../controllers/auth/login.php" class="btn btn-primary btn-small">Zaloguj się</a>
                <?php endif; ?>
            </div>
        </nav>
        
        <!-- Hero Section -->
        <section class="hero-section">
            <div class="hero-bg-blobs">
                <div class="blob blob-1"></div>
                <div class="blob blob-2"></div>
            </div>
            <div class="hero-content">
                <h1 class="hero-title">Odkryj przyszłość <span>aplikacji web</span></h1>
                <p class="hero-subtitle">Najlepsze narzędzia, gry i dashboardy stworzone przez naszą społeczność developerów.</p>
                
                <div class="search-container">
                    <form action="marketplace.php" method="GET" class="search-form">
                        <div class="search-wrapper">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" name="search" class="search-box" placeholder="Czego szukasz dzisiaj?" value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="search-button">Szukaj</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
        
        .filter-tab::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: left 0.5s ease;
        }
        
        .filter-tab:hover {
            background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0.05));
            color: white;
            transform: translateY(-2px);
            border-color: rgba(255,255,255,0.2);
        }
        
        .filter-tab:hover::before {
            left: 100%;
        }
        
        .filter-tab.active {
            background: linear-gradient(135deg, var(--btn-gradient-start), var(--btn-gradient-end));
            color: white;
            border-color: var(--btn-gradient-start);
            box-shadow: 0 4px 15px rgba(99,102,241,0.3);
            transform: translateY(-2px);
        }
        
        .sort-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .sort-dropdown {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .sort-dropdown select {
            padding: 12px 16px;
            border: 2px solid rgba(255,255,255,0.15);
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(255,255,255,0.08), rgba(255,255,255,0.04));
            color: white;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .sort-dropdown select:focus {
            outline: none;
            border-color: var(--btn-gradient-start);
            background: linear-gradient(135deg, rgba(255,255,255,0.12), rgba(255,255,255,0.06));
            box-shadow: 0 0 0 3px rgba(99,102,241,0.15);
        }
        
        .sort-dropdown select option {
            background: var(--deep-black);
            color: white;
            padding: 10px;
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
            background: linear-gradient(135deg, rgba(255,255,255,0.04), rgba(255,255,255,0.02));
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 28px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            height: 100%;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
        }
        
        .app-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at top right, rgba(99,102,241,0.05) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .app-card:hover {
            transform: translateY(-8px) scale(1.02);
            border-color: var(--btn-gradient-start);
            box-shadow: 0 25px 50px rgba(99,102,241,0.25);
        }
        
        .app-card:hover::before {
            opacity: 1;
        }
        
        .app-thumbnail {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, var(--btn-gradient-start), var(--btn-gradient-end));
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(99,102,241,0.2);
        }
        
        .app-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 16px;
            transition: transform 0.3s ease;
        }
        
        .app-card:hover .app-thumbnail img {
            transform: scale(1.05);
        }
        
        .app-thumbnail-placeholder {
            color: white;
            font-size: 3.5rem;
            opacity: 0.9;
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.9; }
            50% { transform: scale(1.05); opacity: 1; }
        }
        
        .app-content {
            padding: 20px;
        }
        
        .app-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 12px;
            color: var(--text-primary);
            line-height: 1.3;
            position: relative;
            z-index: 1;
        }
        
        .app-description {
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 20px;
            flex-grow: 1;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            position: relative;
            z-index: 1;
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
        
        .app-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            font-size: 0.85rem;
            color: var(--text-muted);
            position: relative;
            z-index: 1;
        }
        
        .app-category {
            background: linear-gradient(135deg, rgba(99,102,241,0.2), rgba(99,102,241,0.1));
            padding: 6px 14px;
            border-radius: 25px;
            font-weight: 600;
            border: 1px solid rgba(99,102,241,0.3);
            transition: all 0.3s ease;
        }
        
        .app-category:hover {
            background: linear-gradient(135deg, rgba(99,102,241,0.3), rgba(99,102,241,0.15));
            transform: translateY(-1px);
        }
        
        .app-rating {
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
        }
        
        .app-rating .stars {
            color: #fbbf24;
        }
        
        .app-downloads {
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--text-muted);
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .app-price {
            font-weight: 800;
            color: var(--success);
            font-size: 1.1rem;
            text-shadow: 0 2px 4px rgba(16,185,129,0.3);
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
            gap: 10px;
            padding: 12px 20px;
            border-radius: 25px;
            font-size: 0.95rem;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid transparent;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .btn-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }

        .btn-card.btn-primary {
            background: linear-gradient(135deg, var(--btn-gradient-start), var(--btn-gradient-end));
            color: white;
            box-shadow: 0 4px 15px rgba(99,102,241,0.3);
        }

        .btn-card.btn-secondary {
            background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0.05));
            color: var(--text-primary);
            border-color: rgba(255,255,255,0.2);
        }

        .btn-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(99,102,241,0.4);
        }
        
        .btn-card:hover::before {
            left: 100%;
        }
        
        .btn-card:active {
            transform: translateY(-1px);
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
            gap: 8px;
            margin-top: 50px;
            flex-wrap: wrap;
        }
        
        .pagination a {
            padding: 12px 16px;
            border: 2px solid rgba(255,255,255,0.15);
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(255,255,255,0.05), rgba(255,255,255,0.02));
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-weight: 600;
            min-width: 45px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .pagination a::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: left 0.4s ease;
        }
        
        .pagination a:hover {
            background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0.05));
            color: white;
            transform: translateY(-2px);
            border-color: rgba(255,255,255,0.25);
        }
        
        .pagination a:hover::before {
            left: 100%;
        }
        
        .pagination a.active {
            background: linear-gradient(135deg, var(--btn-gradient-start), var(--btn-gradient-end));
            color: white;
            border-color: var(--btn-gradient-start);
            box-shadow: 0 4px 15px rgba(99,102,241,0.3);
            transform: translateY(-2px);
        }
        
        .pagination .prev,
        .pagination .next {
            padding: 12px 20px;
            font-weight: 700;
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
        
        @media (max-width: 1024px) {
            .apps-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 20px;
            }
            
            .main-content {
                padding: 30px 15px;
            }
            
            .filters-section {
                padding: 25px;
            }
        }
        
        @media (max-width: 768px) {
            .apps-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 20px;
            }
            
            .filter-tabs {
                gap: 8px;
            }
            
            .filter-tab {
                padding: 10px 18px;
                font-size: 0.9rem;
            }
            
            .sort-controls {
                flex-direction: column;
                align-items: stretch;
                gap: 15px;
            }
            
            .search-box {
                padding: 14px 50px 14px 20px;
                font-size: 1rem;
            }
            
            .search-button {
                width: 40px;
                height: 40px;
            }
            
            .app-card {
                padding: 20px;
            }
            
            .app-thumbnail {
                height: 160px;
            }
            
            .pagination {
                gap: 6px;
            }
            
            .pagination a {
                padding: 10px 12px;
                font-size: 0.9rem;
                min-width: 40px;
            }
        }
        
        @media (max-width: 480px) {
            .apps-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .filter-tabs {
                justify-content: center;
            }
            
            .filter-tab {
                padding: 8px 16px;
                font-size: 0.85rem;
            }
            
            .app-card {
                padding: 16px;
            }
            
            .app-thumbnail {
                height: 140px;
            }
            
            .app-title {
                font-size: 1.1rem;
            }
            
            .app-description {
                font-size: 0.9rem;
            }
            
            .btn-card {
                padding: 10px 16px;
                font-size: 0.85rem;
            }
            
            .pagination a {
                padding: 8px 10px;
                font-size: 0.85rem;
                min-width: 35px;
            }
        }
        
        /* User Dropdown Menu */
        .user-profile-dropdown {
            position: relative;
        }
        
        .user-pill {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            color: var(--text-primary);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .user-pill:hover {
            background: var(--bg-hover);
            transform: translateY(-1px);
        }
        
        .user-avatar-mini {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: var(--accent);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: bold;
        }
        
        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 8px;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            backdrop-filter: blur(10px);
            min-width: 200px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 1000;
        }
        
        .user-profile-dropdown:hover .dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: var(--text-primary);
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        
        .dropdown-item:hover {
            background: var(--bg-hover);
            color: var(--accent);
        }
        
        .dropdown-item:first-child {
            border-radius: 12px 12px 0 0;
        }
        
        .dropdown-item:last-child {
            border-radius: 0 0 12px 12px;
        }
        
        .dropdown-divider {
            height: 1px;
            background: var(--glass-border);
            margin: 4px 0;
        }
        
        .logout-item {
            color: var(--error);
        }
        
        .logout-item:hover {
            background: rgba(239, 68, 68, 0.1);
        }
        
        .logout-icon {
            display: none;
        }
        
        .nav-links {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        
        .nav-link:hover {
            background: var(--bg-hover);
            color: var(--accent);
            transform: translateY(-1px);
        }
        
        .nav-link i {
            font-size: 16px;
        }
        
        @media (max-width: 768px) {
            .nav-link span {
                display: none;
            }
            
            .nav-link {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="marketplace-container">
        <!-- Modern Navbar -->
        <nav class="marketplace-nav">
            <div class="nav-brand">
                <a href="../marketplace/marketplace.php"><i class="fas fa-rocket"></i> Bloxer</a>
            </div>
            
            <div class="nav-search-mini">
                <form action="../marketplace/marketplace.php" method="GET">
                    <input type="text" name="search" placeholder="Szukaj aplikacji..." value="<?php echo htmlspecialchars($search); ?>">
                </form>
            </div>

            <div class="nav-user">
                <?php if ($auth->isLoggedIn()): ?>
                    <a href="<?php echo $auth->isDeveloper() ? 'dashboard.php' : 'profile.php'; ?>" class="user-pill">
                        <div class="user-avatar-mini">
                            <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                        </div>
                        <span><?php echo htmlspecialchars($user['username']); ?></span>
                    </a>
                    <a href="../controllers/auth/logout.php" class="logout-icon" title="Wyloguj"><i class="fas fa-sign-out-alt"></i></a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-primary" style="padding: 8px 20px; border-radius: 20px;">Zaloguj się</a>
                <?php endif; ?>
            </div>
        </nav>
        
        <!-- Hero Section -->
        <section class="hero-section">
            <div class="hero-bg-blobs">
                <div class="blob blob-1"></div>
                <div class="blob blob-2"></div>
            </div>
            <div class="hero-content">
                <h1 class="hero-title">Centrum <span>Aplikacji Web</span></h1>
                <p class="hero-subtitle">Przeglądaj, testuj i pobieraj innowacyjne rozwiązania stworzone przez naszą społeczność.</p>
                
                <div class="search-container">
                    <form action="../marketplace/marketplace.php" method="GET">
                        <div class="search-wrapper">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" name="search" class="search-box" placeholder="Czego szukasz dzisiaj?" value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="search-button">Szukaj</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
        
        <!-- Featured Apps Section -->
        <?php if (!empty($featured_apps)): ?>
        <section class="featured-section">
            <div class="container">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-star"></i>
                        Polecane Aplikacje
                    </h2>
                    <p class="section-subtitle">Najlepsze aplikacje wybrane przez naszą społeczność</p>
                </div>
                <div class="featured-grid">
                    <?php foreach ($featured_apps as $app): ?>
                        <div class="featured-card" onclick="location.href='app.php?id=<?php echo $app['id']; ?>'">
                            <div class="featured-thumbnail">
                                <?php if ($app['thumbnail_url']): ?>
                                    <img src="<?php echo htmlspecialchars($app['thumbnail_url']); ?>" alt="<?php echo htmlspecialchars($app['title']); ?>">
                                <?php else: ?>
                                    <div class="thumbnail-placeholder">
                                        <i class="fas fa-rocket"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="featured-badge">
                                    <i class="fas fa-star"></i> Polecane
                                </div>
                            </div>
                            <div class="featured-content">
                                <h3><?php echo htmlspecialchars($app['title']); ?></h3>
                                <p><?php echo htmlspecialchars(substr($app['description'], 0, 100)) . '...'; ?></p>
                                <div class="featured-meta">
                                    <span class="developer">
                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($app['developer_name']); ?>
                                    </span>
                                    <span class="rating">
                                        <i class="fas fa-star"></i> <?php echo number_format($app['rating'], 1); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Filters Section -->
            <div class="filters-section">
                <div class="filter-tabs">
                    <a href="?category=all&search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>" 
                       class="filter-tab <?php echo $category === 'all' ? 'active' : ''; ?>">
                        <i class="fas fa-th-large"></i> Wszystkie
                    </a>
                    <?php foreach ($categories as $cat): ?>
                        <a href="?category=<?php echo urlencode($cat['slug']); ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>" 
                           class="filter-tab <?php echo $category === $cat['slug'] ? 'active' : ''; ?>">
                            <i class="fas fa-<?php echo $cat['icon'] ?? 'folder'; ?>"></i> 
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
                
                <div class="sort-controls">
                    <div class="results-info">
                        Znaleziono <strong><?php echo $total_apps; ?></strong> aplikacji
                    </div>
                    <form method="GET" action="marketplace.php" class="sort-form">
                        <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                        <div class="select-wrapper">
                            <select name="sort" onchange="this.form.submit()">
                                <option value="popular" <?php echo $sort === 'popular' ? 'selected' : ''; ?>>Najpopularniejsze</option>
                                <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Najnowsze</option>
                                <option value="rating" <?php echo $sort === 'rating' ? 'selected' : ''; ?>>Najwyżej oceniane</option>
                                <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Nazwa A-Z</option>
                            </select>
                            <i class="fas fa-sort-amount-down sort-icon"></i>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Apps Grid -->
            <?php if (!empty($apps)): ?>
                <div class="apps-grid">
                    <?php foreach ($apps as $app): ?>
                        <div class="app-card" onclick="location.href='app.php?id=<?php echo $app['id']; ?>'">
                            <div class="app-thumbnail">
                                <?php if ($app['thumbnail_url']): ?>
                                    <img src="<?php echo htmlspecialchars($app['thumbnail_url']); ?>" alt="<?php echo htmlspecialchars($app['title']); ?>" loading="lazy">
                                <?php else: ?>
                                    <div class="thumbnail-placeholder">
                                        <i class="fas fa-rocket"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="app-price-badge <?php echo $app['is_free'] ? 'free' : 'paid'; ?>">
                                    <?php echo $app['is_free'] ? 'DARMOWE' : number_format($app['price'], 2) . ' PLN'; ?>
                                </div>
                            </div>
                            
                            <div class="app-content">
                                <div class="app-meta-top">
                                    <span class="app-category-pill">
                                        <i class="fas fa-<?php echo $app['category_icon'] ?? 'th-large'; ?>"></i>
                                        <?php echo htmlspecialchars($app['category_name'] ?? 'Inne'); ?>
                                    </span>
                                    <div class="app-rating-stars">
                                        <i class="fas fa-star"></i>
                                        <span><?php echo number_format($app['rating'], 1); ?></span>
                                    </div>
                                </div>
                                
                                <h3 class="app-title"><?php echo htmlspecialchars($app['title']); ?></h3>
                                <p class="app-description"><?php echo htmlspecialchars($app['short_description'] ?? substr($app['description'], 0, 80) . '...'); ?></p>
                                
                                <div class="app-footer">
                                    <div class="app-author">
                                        <div class="author-avatar">
                                            <?php echo strtoupper(substr($app['developer_name'], 0, 1)); ?>
                                        </div>
                                        <span><?php echo htmlspecialchars($app['developer_name']); ?></span>
                                    </div>
                                    <div class="app-downloads-count" title="Pobrania">
                                        <i class="fas fa-download"></i>
                                        <?php echo number_format($app['download_count']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination-modern">
                        <?php if ($page > 1): ?>
                            <a href="?category=<?php echo urlencode($category); ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>&page=<?php echo $page - 1; ?>" class="page-nav">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <div class="page-numbers">
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <a href="?category=<?php echo urlencode($category); ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>&page=<?php echo $i; ?>" 
                                   class="page-num <?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?category=<?php echo urlencode($category); ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>&page=<?php echo $page + 1; ?>" class="page-nav">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-search"></i>
                    <h3>Nie znaleziono aplikacji</h3>
                    <p>Spróbuj zmienić kryteria wyszukiwania lub przeglądaj inne kategorie.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="../assets/js/marketplace-enhanced.js"></script>
    <script>
        // Fallback functions for non-enhanced version
        function openApp(appId) {
            window.location.href = `app.php?id=${appId}`;
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
    <script src="../assets/js/marketplace-functions.js"></script>
</body>
</html>
