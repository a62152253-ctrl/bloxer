<?php
require_once '../core/mainlogincore.php';

$auth = new AuthCore();
$user = $auth->getCurrentUser();

$category = $_GET['category'] ?? 'all';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'popular';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 6; // Zmniejszone z 12 na 6 żeby pokazać paginację
$offset = ($page - 1) * $limit;

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

$sort_options = [
    'popular' => 'a.download_count DESC, a.rating DESC',
    'newest' => 'a.published_at DESC',
    'rating' => 'a.rating DESC, a.download_count DESC',
    'name' => 'a.title ASC'
];
$order_by = $sort_options[$sort] ?? $sort_options['popular'];

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
$total_pages = max(1, ceil($total_apps / $limit));

$apps_sql = "
    SELECT a.*, u.username as developer_name, u.avatar_url as developer_avatar, u.id as developer_id,
           c.name as category_name, c.icon as category_icon,
           a.demo_url, a.zip_url
    FROM apps a
    JOIN projects p ON a.project_id = p.id
    JOIN users u ON p.user_id = u.id
    LEFT JOIN categories c ON a.category = c.slug
    $where_clause
    ORDER BY $order_by
    LIMIT ? OFFSET ?
";
$params_with_pagination = $params;
$params_with_pagination[] = $limit;
$params_with_pagination[] = $offset;
$types_with_pagination = $types . 'ii';

$stmt = $conn->prepare($apps_sql);
if (!empty($params_with_pagination)) {
    $stmt->bind_param($types_with_pagination, ...$params_with_pagination);
}
$stmt->execute();
$apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = $conn->prepare("SELECT * FROM categories WHERE is_active = true ORDER BY sort_order");
$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = $conn->prepare("
    SELECT a.*, u.username as developer_name, c.name as category_name
    FROM apps a
    JOIN projects p ON a.project_id = p.id
    JOIN users u ON p.user_id = u.id
    LEFT JOIN categories c ON a.category = c.slug
    WHERE a.status = 'featured'
    ORDER BY a.rating DESC, a.download_count DESC
    LIMIT 3
");
$stmt->execute();
$featured_apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$hero_stats = [
    ['value' => number_format($total_apps), 'label' => 'aplikacji na żywo'],
    ['value' => number_format(count($categories)), 'label' => 'aktywnych kategorii'],
    ['value' => number_format(array_sum(array_map(fn($app) => (int) ($app['download_count'] ?? 0), $apps))), 'label' => 'łącznych pobrań tej strony']
];
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bloxer - Marketplace aplikacji web</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <style>
        /* CSS Variables - Premium Color Palette */
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
            background: var(--bg-main) !important;
            margin: 0;
            padding: 0;
            color: var(--text-primary);
        }

        /* Header */
        .market-topbar {
            background: var(--bg-card) !important;
            border-bottom: 1px solid var(--border-light) !important;
            padding: 16px 32px !important;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 12px var(--shadow-subtle);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .market-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--text-primary) !important;
            font-weight: 600;
            font-size: 18px;
        }

        .market-brand i {
            font-size: 20px;
            color: var(--accent);
        }

        .market-search-mini form {
            display: flex;
            background: var(--border-lighter);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-large);
            overflow: hidden;
            max-width: 400px;
        }

        .market-search-mini input {
            flex: 1;
            padding: 12px 16px;
            border: none;
            outline: none;
            font-size: 14px;
            background: transparent;
            color: var(--text-primary);
        }

        .market-search-mini button {
            padding: 12px 16px;
            border: none;
            background: var(--accent);
            color: white;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.2s ease;
        }

        .market-search-mini button:hover {
            background: var(--accent-hover);
        }

        /* Hero Section */
        .market-hero {
            padding: 80px 32px 60px !important;
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            gap: 60px;
            align-items: flex-start;
        }

        .market-hero-panel {
            flex: 1;
        }

        .market-eyebrow {
            color: var(--accent) !important;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
        }

        .market-hero h1 {
            font-size: 42px !important;
            font-weight: 700 !important;
            line-height: 1.2;
            margin: 0 0 20px 0;
            color: var(--text-primary) !important;
        }

        .market-hero p {
            font-size: 18px !important;
            color: var(--text-secondary) !important;
            line-height: 1.5;
            margin: 0 0 32px 0;
            max-width: 600px;
        }

        .market-hero-search form {
            display: flex;
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-large);
            overflow: hidden;
            box-shadow: 0 4px 20px var(--shadow-subtle);
            max-width: 500px;
        }

        .market-hero-search input {
            flex: 1;
            padding: 16px 20px;
            border: none;
            outline: none;
            font-size: 16px;
            background: transparent;
            color: var(--text-primary);
        }

        .market-hero-search button {
            padding: 16px 20px;
            border: none;
            background: var(--accent);
            color: white;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.2s ease;
        }

        .market-hero-search button:hover {
            background: var(--accent-hover);
        }

        /* Stats Cards */
        .market-stats-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-top: 48px;
        }

        .market-stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-large);
            padding: 32px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 2px 12px var(--shadow-subtle);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .market-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px var(--shadow-medium);
        }

        .market-stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-medium);
            background: var(--accent);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .market-stat-content strong {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-primary);
            display: block;
            line-height: 1;
        }

        .market-stat-content span {
            font-size: 14px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* Toolbar */
        .market-toolbar {
            max-width: 1200px;
            margin: 0 auto 32px;
            padding: 0 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .market-filter-pills {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .market-pill {
            padding: 8px 16px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .market-pill.active {
            background: var(--accent);
            color: white;
        }

        .market-pill:not(.active) {
            background: var(--bg-card);
            color: var(--text-secondary);
            border: 1px solid var(--border-light);
        }

        .market-pill:not(.active):hover {
            background: var(--border-lighter);
        }

        .market-toolbar-meta {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .market-results-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-medium);
        }

        .results-count {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .results-label {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .market-sort select {
            padding: 8px 12px;
            border: 1px solid var(--border-light);
            border-radius: var(--radius-small);
            background: var(--bg-card);
            color: var(--text-primary);
            font-size: 14px;
        }

        /* App Grid */
        .market-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
            padding: 0 32px 80px;
        }

        .market-card {
            background: var(--bg-card) !important;
            border: 1px solid var(--border-light) !important;
            border-radius: var(--radius-large) !important;
            overflow: hidden;
            transition: all 0.2s ease;
            cursor: pointer;
            box-shadow: 0 2px 12px var(--shadow-subtle);
            padding: 0 !important;
        }

        .market-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px var(--shadow-medium);
        }

        .market-card-media {
            position: relative;
            height: 200px;
            overflow: hidden;
        }

        .market-card-media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .market-card-placeholder {
            width: 100%;
            height: 100%;
            background: var(--border-lighter);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
        }

        .market-card-placeholder i {
            font-size: 48px;
        }

        .market-badge {
            position: absolute;
            top: 12px;
            left: 12px;
            padding: 4px 12px;
            background: rgba(52, 199, 89, 0.1);
            color: #34C759;
            border: 1px solid rgba(52, 199, 89, 0.3);
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .market-card-content {
            padding: 24px;
        }

        .market-card-content h3 {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0 0 8px 0;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .market-card-content p {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.5;
            margin: 0 0 16px 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .market-card-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 12px;
            border-top: 1px solid var(--border-lighter);
        }

        .market-category-pill {
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--text-secondary);
            font-size: 12px;
            font-weight: 500;
            padding: 4px 8px;
            background: var(--border-lighter);
            border-radius: var(--radius-small);
            transition: all 0.2s ease;
        }

        .market-category-pill:hover {
            background: var(--border-light);
            color: var(--text-primary);
        }

        .market-score {
            display: flex;
            align-items: center;
            gap: 4px;
            color: var(--text-secondary);
            font-size: 12px;
            font-weight: 500;
        }

        .market-score i {
            color: #FF9500;
        }

        /* Enhanced App Card Details */
        .market-card-stats {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 16px;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-lighter);
        }

        .market-stat-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: var(--text-secondary);
        }

        .market-stat-item i {
            font-size: 14px;
            color: var(--accent);
        }

        .market-card-footer {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 12px;
        }

        .market-author {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: var(--text-secondary);
        }

        .market-author-avatar {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--accent);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 600;
        }

        .market-price-tag {
            margin-left: auto;
            padding: 4px 8px;
            background: rgba(52, 199, 89, 0.1);
            color: #34C759;
            border: 1px solid rgba(52, 199, 89, 0.3);
            border-radius: var(--radius-small);
            font-size: 12px;
            font-weight: 600;
        }

        .market-price-tag.paid {
            background: rgba(255, 149, 0, 0.1);
            color: #FF9500;
            border-color: rgba(255, 149, 0, 0.3);
        }

        /* Hover Effects */
        .market-card:hover .market-category-pill {
            background: var(--accent);
            color: white;
        }

        .market-card:hover .market-stat-item i {
            color: var(--accent-hover);
        }

        .market-card:hover .market-author-avatar {
            transform: scale(1.1);
        }

        .market-card-actions {
            padding: 20px 24px;
            display: flex;
            gap: 12px;
        }

        .btn {
            padding: 12px 20px;
            border-radius: var(--radius-medium);
            font-size: 14px;
            font-weight: 500;
            border: none;
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
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: transparent;
            color: var(--accent);
            border: 1px solid var(--accent);
        }

        .btn-secondary:hover {
            background: var(--accent);
            color: white;
        }

        /* Pagination */
        .allegro-pagination {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 32px 80px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
        }

        .pagination-btn {
            padding: 10px 16px;
            border: 1px solid var(--border-light);
            background: var(--bg-card);
            color: var(--text-primary);
            border-radius: var(--radius-medium);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .pagination-btn:hover:not(.disabled) {
            background: var(--border-lighter);
        }

        .pagination-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination-btn.active {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
        }

        .pagination-number {
            padding: 10px 16px;
            border: 1px solid var(--border-light);
            background: var(--bg-card);
            color: var(--text-primary);
            border-radius: var(--radius-medium);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .pagination-number:hover:not(.active) {
            background: var(--border-lighter);
        }

        .pagination-number.active {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
        }

        /* Hide old elements */
        .pagination-info,
        .market-hero-aside,
        .market-featured-strip,
        .market-nav-links,
        .market-user-slot {
            display: none !important;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .market-hero {
                flex-direction: column;
                padding: 60px 20px 40px !important;
            }

            .market-hero h1 {
                font-size: 32px !important;
            }

            .market-stats-cards {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .market-toolbar {
                flex-direction: column;
                gap: 16px;
                align-items: stretch;
            }

            .market-filter-pills {
                flex-wrap: wrap;
            }

            .market-grid {
                grid-template-columns: 1fr;
                padding: 0 20px 60px;
            }
        }
    </style>
</head>
<body class="app-market">
    <div class="market-shell">
        <header class="market-topbar">
            <a href="marketplace.php" class="market-brand">
                <span class="brand-mark"><i class="fas fa-store"></i></span>
                <span>Bloxer</span>
            </a>

            <div class="market-search-mini">
                <form action="marketplace.php" method="GET">
                    <input type="text" name="search" placeholder="Szukaj aplikacji, narzędzi, dashboardów..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>
            </div>

            <nav class="market-nav-links">
                <?php if ($auth->isLoggedIn()): ?>
                    <a href="personalized_feed.php" class="market-nav-link">
                        <i class="fas fa-home"></i>
                        <span>For You</span>
                    </a>
                    <a href="follow_feed.php" class="market-nav-link">
                        <i class="fas fa-users"></i>
                        <span>Following</span>
                    </a>
                    <a href="dashboard.php" class="market-nav-link">
                        <i class="fas fa-grid-2"></i>
                        <span>Studio</span>
                    </a>
                    <a href="marketplace-settings.php" class="market-nav-link">
                        <i class="fas fa-sliders-h"></i>
                        <span>Ustawienia</span>
                    </a>
                <?php endif; ?>
            </nav>

            <div class="market-user-slot">
                <?php if ($auth->isLoggedIn()): ?>
                    <a href="<?php echo $auth->isDeveloper() ? '../core/dashboard.php' : '../user/profile.php'; ?>" class="market-user-pill">
                        <span class="market-avatar"><?php echo strtoupper(substr($user['username'], 0, 1)); ?></span>
                        <span><?php echo htmlspecialchars($user['username']); ?></span>
                    </a>
                <?php else: ?>
                    <a href="../controllers/auth/login.php" class="btn btn-primary btn-small">Zaloguj się</a>
                <?php endif; ?>
            </div>
        </header>

        <section class="market-hero">
            <div class="market-hero-panel">
                <span class="market-eyebrow">
                    <i class="fas fa-fire"></i>
                    Discover powerful web apps
                </span>
                <h1>Find tools, dashboards and utilities built by creators.</h1>
                <p>
                    Browse a curated collection of modern web applications.
                    From productivity tools to entertainment, discover what creators have built.
                </p>
                <div class="market-hero-search">
                    <form action="marketplace.php" method="GET">
                        <input type="text" name="search" placeholder="Search for apps, tools, dashboards..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </form>
                </div>
                <div class="market-stats-cards">
                    <?php foreach ($hero_stats as $index => $stat): ?>
                        <div class="market-stat-card">
                            <div class="market-stat-icon">
                                <i class="fas <?php echo $index === 0 ? 'fa-cube' : ($index === 1 ? 'fa-layer-group' : 'fa-download'); ?>"></i>
                            </div>
                            <div class="market-stat-content">
                                <strong><?php echo $stat['value']; ?></strong>
                                <span><?php echo htmlspecialchars($stat['label']); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <aside class="market-hero-aside">
                <div class="market-spotlight-card">
                    <span><i class="fas fa-star"></i> wyróżnione</span>
                    <h3>Najmocniejsze appki tygodnia</h3>
                    <p>Przeglądaj najbardziej aktywne projekty i produkty najlepiej oceniane przez społeczność.</p>
                </div>
                <div class="market-mini-list">
                    <?php foreach (array_slice($featured_apps, 0, 3) as $app): ?>
                        <a class="market-mini-item" href="app.php?id=<?php echo $app['id']; ?>">
                            <div>
                                <strong><?php echo htmlspecialchars($app['title']); ?></strong>
                                <span><?php echo htmlspecialchars($app['developer_name']); ?></span>
                            </div>
                            <span class="market-mini-score"><?php echo number_format($app['rating'], 1); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </aside>
        </section>

        <?php if (!empty($featured_apps)): ?>
            <section class="market-featured-strip">
                <div class="market-section-head">
                    <div>
                        <h2>Polecane dzisiaj</h2>
                        <p>Ręcznie wybrane aplikacje, które aktualnie robią największe wrażenie.</p>
                    </div>
                </div>
                <div class="market-featured-grid">
                    <?php foreach ($featured_apps as $app): ?>
                        <a class="market-featured-card" href="app.php?id=<?php echo $app['id']; ?>">
                            <div>
                                <span class="market-featured-meta">
                                    <i class="fas fa-badge-check"></i>
                                    featured
                                </span>
                                <h3><?php echo htmlspecialchars($app['title']); ?></h3>
                                <p><?php echo htmlspecialchars($app['short_description'] ?? substr($app['description'], 0, 110) . '...'); ?></p>
                            </div>
                            <div class="market-featured-meta">
                                <span><?php echo htmlspecialchars($app['developer_name']); ?></span>
                                <span>★ <?php echo number_format($app['rating'], 1); ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="market-toolbar">
            <div class="market-filter-pills">
                <a href="?category=all&sort=<?php echo urlencode($sort); ?>&search=<?php echo urlencode($search); ?>" class="market-pill <?php echo $category === 'all' ? 'active' : ''; ?>">All</a>
                <?php foreach ($categories as $cat): ?>
                    <a
                        href="?category=<?php echo urlencode($cat['slug']); ?>&sort=<?php echo urlencode($sort); ?>&search=<?php echo urlencode($search); ?>"
                        class="market-pill <?php echo $category === $cat['slug'] ? 'active' : ''; ?>"
                    >
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="market-toolbar-meta">
                <div class="market-results-badge">
                    <span class="results-count"><?php echo number_format($total_apps); ?></span>
                    <span class="results-label">apps found</span>
                </div>
                <form action="marketplace.php" method="GET" class="market-sort">
                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                    <select class="market-sort-select" name="sort" id="sort-select">
                        <option value="popular" <?php echo $sort === 'popular' ? 'selected' : ''; ?>>Popular</option>
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>New</option>
                        <option value="rating" <?php echo $sort === 'rating' ? 'selected' : ''; ?>>Top Rated</option>
                        <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>A-Z</option>
                    </select>
                </form>
            </div>
        </section>

        <?php if (!empty($apps)): ?>
            <section class="market-grid">
                <?php foreach ($apps as $app): ?>
                    <article class="market-card" onclick="location.href='app.php?id=<?php echo $app['id']; ?>'">
                        <div class="market-card-media">
                            <?php if (!empty($app['thumbnail_url'])): ?>
                                <img src="<?php echo htmlspecialchars($app['thumbnail_url']); ?>" alt="<?php echo htmlspecialchars($app['title']); ?>" loading="lazy">
                            <?php else: ?>
                                <div class="market-card-placeholder">
                                    <i class="fas fa-cubes"></i>
                                </div>
                            <?php endif; ?>
                            <span class="market-badge">Darmowe</span>
                        </div>

                        <div class="market-card-content">
                            <h3><?php echo htmlspecialchars($app['title']); ?></h3>
                            <p><?php echo htmlspecialchars(substr($app['description'], 0, 120) . '...'); ?></p>
                            
                            <div class="market-card-meta">
                                <span class="market-category-pill">
                                    <i class="fas fa-<?php echo htmlspecialchars($app['category_icon'] ?? 'th-large'); ?>"></i>
                                    <?php echo htmlspecialchars($app['category_name'] ?? 'Other'); ?>
                                </span>
                                <span class="market-score">
                                    <i class="fas fa-star"></i>
                                    <?php echo number_format($app['rating'], 1); ?>
                                </span>
                            </div>
                        </div>

                        <div class="market-card-actions">
                            <?php if (!empty($app['demo_url'])): ?>
                                <a href="<?php echo htmlspecialchars($app['demo_url']); ?>" target="_blank" class="btn btn-primary">
                                    <i class="fas fa-external-link-alt"></i>
                                    Demo
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($app['zip_url'])): ?>
                                <a href="<?php echo htmlspecialchars($app['zip_url']); ?>" target="_blank" class="btn btn-secondary">
                                    <i class="fas fa-download"></i>
                                    Download ZIP
                                </a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
                
                <?php if ($total_pages > 1): ?>
                <nav class="allegro-pagination">
                    <div class="pagination-info">
                        <span class="results-count">Page <?php echo $page; ?> of <?php echo $total_pages; ?> (<?php echo $total_apps; ?> apps)</span>
                    </div>
                    
                    <div class="pagination-controls">
                        <!-- Previous page -->
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&category=<?php echo urlencode($category); ?>&sort=<?php echo urlencode($sort); ?>&search=<?php echo urlencode($search); ?>" class="pagination-btn">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>

                        <!-- Page numbers -->
                        <div class="pagination-numbers">
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <a href="?page=<?php echo $i; ?>&category=<?php echo urlencode($category); ?>&sort=<?php echo urlencode($sort); ?>&search=<?php echo urlencode($search); ?>" 
                                   class="pagination-number <?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                        </div>

                        <!-- Next page -->
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&category=<?php echo urlencode($category); ?>&sort=<?php echo urlencode($sort); ?>&search=<?php echo urlencode($search); ?>" class="pagination-btn">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <section class="market-empty">
                <i class="fas fa-search"></i>
                <h2>Nie znaleziono aplikacji</h2>
                <p>Spróbuj zmienić kategorię, frazę wyszukiwania albo sortowanie wyników.</p>
            </section>
        <?php endif; ?>

        <?php if ($auth->isLoggedIn()): ?>
            <div class="market-dropdown" style="margin-top: 18px;">
                <a href="<?php echo $auth->isDeveloper() ? '../core/dashboard.php' : '../user/profile.php'; ?>">
                    <i class="fas fa-user"></i>
                    Profil
                </a>
                <a href="projects.php">
                    <i class="fas fa-folder-open"></i>
                    Projekty
                </a>
                <a href="../controllers/auth/logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    Wyloguj
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.getElementById('sort-select').addEventListener('change', function() {
            this.form.submit();
        });

        const searchInput = document.querySelector('.market-hero-search input');
        let searchTimeout;

        if (searchInput) {
            searchInput.addEventListener('input', function(e) {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    if (e.target.value.length >= 2 || e.target.value.length === 0) {
                        e.target.form.submit();
                    }
                }, 500);
            });
        }
    </script>

    <script>
        // Install app function
        function installApp(event, appId) {
            event.stopPropagation();
            
            if (!<?php echo $auth->isLoggedIn() ? 'true' : 'false'; ?>) {
                alert('Musisz być zalogowany, aby zainstalować aplikację');
                return;
            }
            
            // Here you would typically make an AJAX call to install the app
            // For now, just show a message
            const appTitle = event.target.closest('.market-card').querySelector('h3').textContent;
            
            if (confirm(`Czy na pewno chcesz zainstalować aplikację "${appTitle}"?`)) {
                // Simulate installation
                event.target.innerHTML = '<i class="fas fa-check"></i> Zainstalowano';
                event.target.disabled = true;
                event.target.classList.remove('btn-primary');
                event.target.classList.add('btn-success');
                
                // Here you would make an actual API call
                // fetch('/api/install-app.php', { method: 'POST', body: JSON.stringify({app_id: appId}) })
            }
        }

        // Use app function
        function useApp(event, appId) {
            event.stopPropagation();
            
            if (!<?php echo $auth->isLoggedIn() ? 'true' : 'false'; ?>) {
                alert('Musisz być zalogowany, aby użyć aplikacji');
                return;
            }
            
            // Redirect to app usage page or open in sandbox
            window.open(`run_app.php?id=${appId}`, '_blank');
        }
    </script>
    <script src="../assets/js/beta-banner.js"></script>
    <script src="../assets/js/marketplace-functions.js"></script>
</body>
</html>