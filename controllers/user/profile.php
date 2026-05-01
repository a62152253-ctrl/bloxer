<?php
require_once __DIR__ . '/../../bootstrap.php';

$auth = new AuthCore();

if (!$auth->isLoggedIn()) {
    SecurityUtils::safeRedirect('../auth/login.php', 302, 'User not logged in, redirecting to login');
}

if ($auth->isDeveloper()) {
    SecurityUtils::safeRedirect('../core/dashboard.php', 302, 'Developer accessing user profile, redirecting to dashboard');
}

$user = $auth->getCurrentUser();
$page = SecurityUtils::validateInput($_GET['page'] ?? 'installed', 'page', ['installed', 'favorites', 'developers', 'reviews']);
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

// Get user's favorite developers (if table exists)
$favorite_developers = [];
$check_table = $conn->query("SHOW TABLES LIKE 'developer_follows'");
if ($check_table->num_rows > 0) {
    $stmt = $conn->prepare("
        SELECT u.id, u.username, u.avatar_url, u.bio, u.github_url, u.twitter_url,
               COUNT(DISTINCT a.id) as app_count,
               AVG(ar.rating) as avg_rating,
               df.created_at
        FROM developer_follows df
        JOIN users u ON df.developer_id = u.id
        LEFT JOIN projects p ON u.id = p.user_id
        LEFT JOIN apps a ON p.id = a.project_id AND a.status = 'published'
        LEFT JOIN app_reviews ar ON a.id = ar.app_id AND ar.status = 'published'
        WHERE df.follower_id = ? AND u.status = 'active'
        GROUP BY u.id, u.username, u.avatar_url, u.bio, u.github_url, u.twitter_url, df.created_at
        ORDER BY df.created_at DESC
        LIMIT 12
    ");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $favorite_developers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

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
        COALESCE(AVG(ar.rating), 0) as avg_rating,
        COUNT(DISTINCT CASE WHEN o.status = 'accepted' THEN o.id END) as total_deals
    FROM users u
    LEFT JOIN user_apps ua ON u.id = ua.user_id
    LEFT JOIN apps a ON ua.app_id = a.id AND a.status = 'published'
    LEFT JOIN app_reviews ar ON u.id = ar.user_id AND ar.status = 'published'
    LEFT JOIN offers o ON u.id = o.buyer_id AND o.status = 'accepted'
    WHERE u.id = ?
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Add favorite developers count if table exists
if ($check_table->num_rows > 0) {
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT developer_id) as favorite_devs
        FROM developer_follows
        WHERE follower_id = ?
    ");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $fav_devs_result = $stmt->get_result()->fetch_assoc();
    $stats['favorite_developers'] = $fav_devs_result['favorite_devs'];
} else {
    $stats['favorite_developers'] = 0;
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - Bloxer</title>
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
            background: var(--bg-main);
            margin: 0;
            padding: 0;
            color: var(--text-primary);
        }

        .profile-layout {
            display: flex;
            min-height: 100vh;
        }

        /* Left Sidebar */
        .profile-sidebar {
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

        .profile-sidebar-header {
            padding: 24px;
            border-bottom: 1px solid var(--border-light);
        }

        .profile-sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--text-primary);
            font-weight: 600;
            font-size: 18px;
        }

        .profile-sidebar-brand i {
            font-size: 20px;
            color: var(--accent);
        }

        .profile-sidebar-nav {
            flex: 1;
            padding: 8px;
        }

        .profile-nav-item {
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

        .profile-nav-item:hover {
            background: var(--border-lighter);
            color: var(--text-primary);
        }

        .profile-nav-item.active {
            background: var(--accent);
            color: white;
        }

        .profile-nav-item i {
            width: 16px;
            text-align: center;
        }

        .profile-nav-item span {
            flex: 1;
        }

        /* Main Content */
        .profile-main {
            flex: 1;
            margin-left: 280px;
            padding: 32px;
            overflow-y: auto;
        }

        /* Profile Header */
        .profile-header {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-large);
            padding: 32px;
            margin-bottom: 32px;
            box-shadow: 0 2px 12px var(--shadow-subtle);
        }

        .profile-header-content {
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--accent);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            overflow: hidden;
            flex-shrink: 0;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-info {
            flex: 1;
        }

        .profile-name {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0 0 4px 0;
        }

        .profile-email {
            font-size: 16px;
            color: var(--text-secondary);
            margin: 0 0 24px 0;
        }

        .profile-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 16px;
        }

        .profile-stat {
            text-align: center;
            padding: 16px;
            background: var(--border-lighter);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-medium);
        }

        .profile-stat-value {
            display: block;
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1;
        }

        .profile-stat-label {
            font-size: 12px;
            color: var(--text-secondary);
            font-weight: 500;
            margin-top: 4px;
        }

        /* Settings Sections */
        .settings-section {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-large);
            padding: 32px;
            margin-bottom: 32px;
            box-shadow: 0 2px 12px var(--shadow-subtle);
        }

        .settings-section-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
        }

        .settings-section-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-medium);
            background: var(--accent);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .settings-section-content {
            flex: 1;
        }

        .settings-section h3 {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0 0 8px 0;
        }

        .settings-section p {
            font-size: 14px;
            color: var(--text-secondary);
            margin: 0 0 16px 0;
            line-height: 1.5;
        }

        /* Form Groups */
        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border-light);
            border-radius: var(--radius-medium);
            background: var(--bg-card);
            color: var(--text-primary);
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        /* Toggle Switch */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 48px;
            height: 24px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-switch .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--border-light);
            transition: 0.4s;
            border-radius: 24px;
        }

        .toggle-switch .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: 0.4s;
            border-radius: 50%;
        }

        .toggle-switch input:checked + .slider {
            background: var(--accent);
        }

        .toggle-switch input:checked + .slider:before {
            transform: translateX(24px);
        }

        /* Navigation Tabs */
        .profile-nav {
            display: flex;
            gap: 8px;
            border-bottom: 1px solid var(--border-light);
            margin-bottom: 32px;
            padding: 0 32px;
        }

        .profile-nav-tab {
            padding: 12px 20px;
            border: none;
            background: transparent;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            border-radius: var(--radius-medium);
            transition: all 0.2s ease;
        }

        .profile-nav-tab:hover {
            background: var(--border-lighter);
            color: var(--text-primary);
        }

        .profile-nav-tab.active {
            color: var(--accent);
            background: rgba(0, 122, 255, 0.1);
            border: 1px solid rgba(0, 122, 255, 0.3);
        }

        .profile-nav-tab i {
            margin-right: 8px;
        }

        /* App Grid */
        .apps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .app-card {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-large);
            overflow: hidden;
            transition: all 0.2s ease;
            cursor: pointer;
            box-shadow: 0 2px 12px var(--shadow-subtle);
        }

        .app-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px var(--shadow-medium);
        }

        .app-thumbnail {
            position: relative;
            height: 160px;
            overflow: hidden;
        }

        .app-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .app-thumbnail .app-placeholder {
            width: 100%;
            height: 100%;
            background: var(--border-lighter);
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
        }

        .app-content {
            padding: 20px;
        }

        .app-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0 0 8px 0;
            line-height: 1.2;
        }

        .app-developer {
            font-size: 14px;
            color: var(--text-secondary);
            margin: 0 0 12px 0;
        }

        .app-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 12px;
        }

        .app-rating {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 14px;
            color: var(--text-secondary);
        }

        .app-favorite {
            color: #FF3B30;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-large);
            box-shadow: 0 2px 12px var(--shadow-subtle);
        }

        .empty-state i {
            font-size: 48px;
            color: var(--text-secondary);
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0 0 8px 0;
        }

        .empty-state p {
            font-size: 14px;
            color: var(--text-secondary);
            margin: 0;
            line-height: 1.5;
        }

        .empty-state .btn {
            margin-top: 20px;
        }

        /* Developer Cards */
        .developers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .developer-card {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-large);
            padding: 24px;
            transition: all 0.2s ease;
            cursor: pointer;
            box-shadow: 0 2px 12px var(--shadow-subtle);
        }

        .developer-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px var(--shadow-medium);
        }

        .developer-header {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 16px;
        }

        .developer-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
        }

        .developer-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .developer-avatar i {
            width: 100%;
            height: 100%;
            background: var(--border-lighter);
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .developer-info {
            flex: 1;
        }

        .developer-name {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0 0 4px 0;
        }

        .developer-bio {
            font-size: 14px;
            color: var(--text-secondary);
            margin: 0 0 12px 0;
            line-height: 1.5;
        }

        .developer-stats {
            display: flex;
            gap: 16px;
            margin-bottom: 16px;
        }

        .dev-stat {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: var(--text-secondary);
        }

        .dev-stat i {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .developer-social {
            display: flex;
            gap: 12px;
            margin-bottom: 16px;
        }

        .social-link {
            width: 32px;
            height: 32px;
            border: 1px solid var(--border-light);
            border-radius: var(--radius-small);
            background: var(--bg-card);
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .social-link:hover {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
        }

        .follow-btn {
            padding: 8px 16px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: var(--radius-medium);
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        /* Review Cards */
        .review-item {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-large);
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 2px 12px var(--shadow-subtle);
        }

        .review-header {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 16px;
        }

        .review-app-info {
            flex: 1;
        }

        .review-app-thumbnail {
            width: 60px;
            height: 60px;
            border-radius: var(--radius-medium);
            overflow: hidden;
            flex-shrink: 0;
        }

        .review-app-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .review-app-placeholder {
            width: 100%;
            height: 100%;
            background: var(--border-lighter);
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .review-app-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0 0 4px 0;
        }

        .review-rating {
            display: flex;
            align-items: center;
            gap: 2px;
            font-size: 12px;
            color: var(--text-secondary);
        }

        .review-date {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 8px;
        }

        .review-text {
            font-size: 14px;
            color: var(--text-primary);
            line-height: 1.5;
            margin-top: 12px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .profile-layout {
                flex-direction: column;
            }

            .profile-sidebar {
                width: 100%;
                position: relative;
                height: auto;
            }

            .profile-main {
                margin-left: 0;
                padding: 20px;
            }

            .profile-header-content {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }

            .profile-stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .apps-grid,
            .developers-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="profile-layout">
        <!-- Left Sidebar -->
        <aside class="profile-sidebar">
            <div class="profile-sidebar-header">
                <a href="../marketplace/marketplace.php" class="profile-sidebar-brand">
                    <i class="fas fa-store"></i>
                    <span>Bloxer</span>
                </a>
                <a href="../marketplace/marketplace.php" class="btn btn-secondary" style="margin-top: 12px; width: 100%; text-align: center; font-size: 12px; padding: 8px;">
                    <i class="fas fa-arrow-left"></i>
                    Powrót do Marketplace
                </a>
            </div>
            
            <nav class="profile-sidebar-nav">
                <a href="?page=profile" class="profile-nav-item active">
                    <i class="fas fa-user"></i>
                    <span>Profil</span>
                </a>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="profile-main">
            <?php if ($page === 'profile'): ?>
                <!-- Profile Information Section -->
                <section class="settings-section">
                    <div class="settings-section-header">
                        <div class="settings-section-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="settings-section-content">
                            <h3>Profile Information</h3>
                            <p>Manage your personal information and account details</p>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="bio">Bio</label>
                        <textarea id="bio" name="bio" rows="4" placeholder="Tell us about yourself..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="website">Website</label>
                        <input type="url" id="website" name="website" placeholder="https://example.com" value="<?php echo htmlspecialchars($user['website'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Save Profile
                        </button>
                    </div>
                </section>
                
                                
                                
                <!-- Delete Account Section -->
                <section class="settings-section" style="border-color: #e74c3c; background: #fdf2f2;">
                    <div class="settings-section-header">
                        <div class="settings-section-icon" style="background: #e74c3c;">
                            <i class="fas fa-trash-alt"></i>
                        </div>
                        <div class="settings-section-content">
                            <h3 style="color: #e74c3c;">Delete Account</h3>
                            <p>Permanently delete your account and all associated data</p>
                        </div>
                    </div>
                    
                    <div style="background: #fff5f5; border: 1px solid #fed7d7; border-radius: 8px; padding: 16px; margin-bottom: 24px;">
                        <h4 style="color: #e53e3e; margin: 0 0 12px 0;">⚠️ Warning: This action cannot be undone!</h4>
                        <p style="margin: 0 0 8px 0; color: #742a2a;">Deleting your account will permanently remove:</p>
                        <ul style="margin: 0; padding-left: 20px; color: #742a2a;">
                            <li>Your profile information and settings</li>
                            <li>All your installed apps and preferences</li>
                            <li>Your reviews and ratings</li>
                            <li>Your projects and associated files</li>
                            <li>Your notifications and activity history</li>
                        </ul>
                    </div>
                    
                    <div class="form-group">
                        <label for="delete_confirmation" style="font-weight: 500; color: #e74c3c;">
                            Type "DELETE" to confirm account deletion:
                        </label>
                        <input type="text" id="delete_confirmation" name="delete_confirmation" 
                               placeholder="Type DELETE" style="border-color: #e74c3c;">
                    </div>
                    
                    <div class="form-group">
                        <label for="delete_password" style="font-weight: 500;">Confirm your password:</label>
                        <input type="password" id="delete_password" name="delete_password" 
                               placeholder="Enter your password" style="border-color: #e74c3c;">
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" id="delete_account_btn" 
                                class="btn" style="background: #e74c3c; border-color: #e74c3c;" disabled>
                            <i class="fas fa-trash-alt"></i>
                            Delete My Account
                        </button>
                    </div>
                </section>
                
                <!-- Back to Marketplace -->
                <div class="form-actions" style="margin-top: 32px;">
                    <a href="../marketplace/marketplace.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Powrót do Marketplace
                    </a>
                </div>
                
                            <?php endif; ?>
            
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
                            <a href="../marketplace/marketplace.php" class="btn" style="margin-top: 20px;">
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
                
            <?php elseif ($page === 'developers'): ?>
                <div>
                    <?php if (!empty($favorite_developers)): ?>
                        <div class="developers-grid">
                            <?php foreach ($favorite_developers as $dev): ?>
                                <div class="developer-card" onclick="viewDeveloper(<?php echo $dev['id']; ?>)">
                                    <div class="developer-avatar">
                                        <?php if ($dev['avatar_url']): ?>
                                            <img src="<?php echo htmlspecialchars($dev['avatar_url']); ?>" alt="<?php echo htmlspecialchars($dev['username']); ?>">
                                        <?php else: ?>
                                            <i class="fas fa-user-tie"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="developer-info">
                                        <h3 class="developer-name"><?php echo htmlspecialchars($dev['username']); ?></h3>
                                        <?php if ($dev['bio']): ?>
                                            <p class="developer-bio"><?php echo htmlspecialchars(substr($dev['bio'], 0, 100)); ?>...</p>
                                        <?php endif; ?>
                                        <div class="developer-stats">
                                            <div class="dev-stat">
                                                <i class="fas fa-rocket"></i>
                                                <span><?php echo $dev['app_count']; ?> Apps</span>
                                            </div>
                                            <?php if ($dev['avg_rating'] > 0): ?>
                                                <div class="dev-stat">
                                                    <i class="fas fa-star"></i>
                                                    <span><?php echo number_format($dev['avg_rating'], 1); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="developer-social">
                                            <?php if ($dev['github_url']): ?>
                                                <a href="<?php echo htmlspecialchars($dev['github_url']); ?>" target="_blank" class="social-link">
                                                    <i class="fab fa-github"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($dev['twitter_url']): ?>
                                                <a href="<?php echo htmlspecialchars($dev['twitter_url']); ?>" target="_blank" class="social-link">
                                                    <i class="fab fa-twitter"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="follow-btn">
                                        <i class="fas fa-check"></i>
                                        Following
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-user-tie"></i>
                            <h3>No followed developers yet</h3>
                            <p>Follow your favorite developers to stay updated with their latest apps!</p>
                        </div>
                    <?php endif; ?>
                </div>
                
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function openApp(appId) {
            window.location.href = `../controllers/user/user-appview.php?id=${appId}`;
        }
        
        function viewDeveloper(devId) {
            window.location.href = `../controllers/user/developer_profile.php?id=${devId}`;
        }
        
        // Delete account functionality
        document.addEventListener('DOMContentLoaded', function() {
            const deleteConfirmation = document.getElementById('delete_confirmation');
            const deletePassword = document.getElementById('delete_password');
            const deleteBtn = document.getElementById('delete_account_btn');
            
            // Enable/disable delete button based on input
            function updateDeleteButton() {
                const confirmationCorrect = deleteConfirmation.value.trim() === 'DELETE';
                const passwordEntered = deletePassword.value.trim().length > 0;
                deleteBtn.disabled = !(confirmationCorrect && passwordEntered);
            }
            
            deleteConfirmation.addEventListener('input', updateDeleteButton);
            deletePassword.addEventListener('input', updateDeleteButton);
            
            // Handle delete button click
            deleteBtn.addEventListener('click', function() {
                if (confirm('Are you absolutely sure you want to delete your account? This action cannot be undone!')) {
                    if (confirm('This is your final warning. All your data will be permanently deleted. Continue?')) {
                        // Show loading state
                        deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
                        deleteBtn.disabled = true;
                        
                        // Send delete request
                        fetch('delete_account.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'password=' + encodeURIComponent(deletePassword.value)
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Your account has been deleted successfully. You will be redirected to the homepage.');
                                window.location.href = '../index.php';
                            } else {
                                alert('Error deleting account: ' + data.message);
                                deleteBtn.innerHTML = '<i class="fas fa-trash-alt"></i> Delete My Account';
                                updateDeleteButton();
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred while deleting your account. Please try again.');
                            deleteBtn.innerHTML = '<i class="fas fa-trash-alt"></i> Delete My Account';
                            updateDeleteButton();
                        });
                    }
                }
            });
        });
    </script>
</body>
</html>
