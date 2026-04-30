<?php
require_once __DIR__ . '/../../bootstrap.php';

$auth = new AuthCore();

if (!$auth->isLoggedIn()) {
    SecurityUtils::safeRedirect('../auth/login.php', 302, 'User not logged in, redirecting to login');
}

$app_id = SecurityUtils::validateInput($_GET['id'] ?? null, 'id');

if (!$app_id) {
    SecurityUtils::safeRedirect('../marketplace/marketplace.php', 302, 'No app ID provided');
}

// Get current user
$user = $auth->getCurrentUser();
$conn = $auth->getConnection();

// Get app details
$stmt = $conn->prepare("
    SELECT a.*, u.username as developer_name, u.avatar_url as developer_avatar, u.email as developer_email, u.bio as developer_bio,
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
    SecurityUtils::safeRedirect('../marketplace/marketplace.php', 302, 'App not found');
}

// Check if user has installed this app
$stmt = $conn->prepare("SELECT id, installed_at, usage_count, is_favorite FROM user_apps WHERE user_id = ? AND app_id = ?");
$stmt->bind_param("ii", $user['id'], $app_id);
$stmt->execute();
$user_app = $stmt->get_result()->fetch_assoc();

$is_installed = $user_app ? true : false;
$is_favorite = $user_app ? $user_app['is_favorite'] : false;

// Get app screenshots from JSON field
$screenshots = [];
if ($app['screenshots']) {
    $screenshots_data = json_decode($app['screenshots'], true);
    if (is_array($screenshots_data)) {
        $screenshots = $screenshots_data;
    }
}

// Get app reviews
$stmt = $conn->prepare("
    SELECT ar.*, u.username, u.avatar_url
    FROM app_reviews ar
    JOIN users u ON ar.user_id = u.id
    WHERE ar.app_id = ? AND ar.status = 'published'
    ORDER BY ar.created_at DESC
    LIMIT 10
");
$stmt->bind_param("i", $app_id);
$stmt->execute();
$reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

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

// Handle app actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = SecurityUtils::validateInput($_POST['action'] ?? '', 'action');
    
    if ($action === 'install') {
        if (!$is_installed) {
            $stmt = $conn->prepare("INSERT INTO user_apps (user_id, app_id, installed_at) VALUES (?, ?, NOW())");
            $stmt->bind_param("ii", $user['id'], $app_id);
            $stmt->execute();
            $is_installed = true;
            
            // Update app downloads
            $stmt = $conn->prepare("UPDATE apps SET download_count = download_count + 1 WHERE id = ?");
            $stmt->bind_param("i", $app_id);
            $stmt->execute();
        }
    } elseif ($action === 'uninstall') {
        if ($is_installed) {
            $stmt = $conn->prepare("DELETE FROM user_apps WHERE user_id = ? AND app_id = ?");
            $stmt->bind_param("ii", $user['id'], $app_id);
            $stmt->execute();
            $is_installed = false;
            $is_favorite = false;
        }
    } elseif ($action === 'favorite') {
        if ($is_installed) {
            $new_favorite = !$is_favorite;
            $stmt = $conn->prepare("UPDATE user_apps SET is_favorite = ? WHERE user_id = ? AND app_id = ?");
            $stmt->bind_param("iii", $new_favorite, $user['id'], $app_id);
            $stmt->execute();
            $is_favorite = $new_favorite;
        }
    } elseif ($action === 'use_app') {
        if ($is_installed) {
            // Update usage count and last used
            $stmt = $conn->prepare("UPDATE user_apps SET usage_count = usage_count + 1, last_used_at = NOW() WHERE user_id = ? AND app_id = ?");
            $stmt->bind_param("ii", $user['id'], $app_id);
            $stmt->execute();
            
            // Redirect to app runner
            SecurityUtils::safeRedirect("run_app.php?id={$app_id}", 302, 'Opening app');
        }
    }
    
    // Refresh user app data
    $stmt = $conn->prepare("SELECT installed_at, usage_count, is_favorite FROM user_apps WHERE user_id = ? AND app_id = ?");
    $stmt->bind_param("ii", $user['id'], $app_id);
    $stmt->execute();
    $user_app = $stmt->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($app['title']); ?> - Bloxer</title>
    <link rel="stylesheet" href="../../assets/css/app.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <!-- App Header -->
        <header class="app-header">
            <div class="back-button">
                <a href="../controllers/user/profile.php">
                    <i class="fas fa-arrow-left"></i>
                    Back to Profile
                </a>
            </div>
            
            <div class="app-icon">
                <?php if ($app['thumbnail_url']): ?>
                    <img src="<?php echo htmlspecialchars($app['thumbnail_url']); ?>" alt="<?php echo htmlspecialchars($app['title']); ?>">
                <?php else: ?>
                    <div class="app-icon-placeholder">
                        <i class="fas fa-rocket"></i>
                    </div>
                <?php endif; ?>
            </div>
            
            <h1 class="app-title"><?php echo htmlspecialchars($app['title']); ?></h1>
            <div class="app-developer">by <?php echo htmlspecialchars($app['developer_name']); ?></div>
            
            <div class="app-actions">
                <form method="POST" style="display: inline;">
                    <?php if ($is_installed): ?>
                        <button type="submit" name="action" value="use_app" class="btn btn-primary">
                            <i class="fas fa-play"></i>
                            Use App
                        </button>
                        <button type="submit" name="action" value="favorite" class="btn btn-secondary">
                            <i class="fas fa-heart" style="<?php echo $is_favorite ? 'color: #e74c3c;' : ''; ?>"></i>
                            <?php echo $is_favorite ? 'Favorited' : 'Favorite'; ?>
                        </button>
                        <button type="submit" name="action" value="uninstall" class="btn btn-danger">
                            <i class="fas fa-trash"></i>
                            Uninstall
                        </button>
                    <?php else: ?>
                        <button type="submit" name="action" value="install" class="btn btn-success">
                            <i class="fas fa-download"></i>
                            Install
                        </button>
                    <?php endif; ?>
                </form>
            </div>
        </header>
        
        <!-- App Content -->
        <div class="app-content">
            <div class="app-main">
                <!-- App Preview/Run Area -->
                <div class="app-frame-container">
                    <?php if ($is_installed): ?>
                        <iframe src="run_app.php?id=<?php echo $app_id; ?>" class="app-frame" allowfullscreen></iframe>
                    <?php else: ?>
                        <div style="height: 600px; display: flex; align-items: center; justify-content: center; background: #f8f9fa; border-radius: 20px;">
                            <div style="text-align: center;">
                                <i class="fas fa-lock" style="font-size: 4rem; color: #64748b; margin-bottom: 20px;"></i>
                                <h3 style="color: #0f172a; margin-bottom: 10px;">Install this app to use it</h3>
                                <p style="color: #64748b;">Click the Install button above to get started</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- App Description -->
                <div class="app-description">
                    <h3>About this app</h3>
                    <p><?php echo htmlspecialchars($app['description'] ?? 'No description available'); ?></p>
                    <?php if ($app['project_description']): ?>
                        <p><?php echo htmlspecialchars($app['project_description']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="app-sidebar">
                <!-- App Info -->
                <div class="sidebar-section">
                    <h3><i class="fas fa-info-circle"></i> App Info</h3>
                    <div class="app-stats">
                        <div class="stat-item">
                            <span class="stat-label">Category</span>
                            <span class="stat-value"><?php echo htmlspecialchars($app['category_name'] ?? 'Uncategorized'); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Rating</span>
                            <span class="stat-value"><?php echo number_format($app['rating'] ?? 0, 1); ?> ⭐</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Downloads</span>
                            <span class="stat-value"><?php echo number_format($app['download_count'] ?? 0); ?></span>
                        </div>
                        <?php if ($is_installed): ?>
                            <div class="stat-item">
                                <span class="stat-label">Your Usage</span>
                                <span class="stat-value"><?php echo $user_app['usage_count']; ?> times</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Installed</span>
                                <span class="stat-value"><?php echo date('M j, Y', strtotime($user_app['installed_at'])); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Developer Info -->
                <div class="sidebar-section">
                    <h3><i class="fas fa-user"></i> Developer</h3>
                    <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                        <div style="width: 50px; height: 50px; border-radius: 50%; background: #f3f7fe; display: flex; align-items: center; justify-content: center;">
                            <?php if ($app['developer_avatar']): ?>
                                <img src="<?php echo htmlspecialchars($app['developer_avatar']); ?>" alt="<?php echo htmlspecialchars($app['developer_name']); ?>" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                            <?php else: ?>
                                <i class="fas fa-user" style="color: #1769ff;"></i>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div style="font-weight: 600; color: #0f172a;"><?php echo htmlspecialchars($app['developer_name']); ?></div>
                            <div style="font-size: 0.85rem; color: #64748b;">
                                <?php echo number_format($developer_stats['total_apps'] ?? 0); ?> apps
                                <?php if ($developer_stats['avg_rating'] > 0): ?>
                                    • <?php echo number_format($developer_stats['avg_rating'], 1); ?> ⭐
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php if ($app['developer_bio']): ?>
                        <p style="color: #64748b; font-size: 0.9rem; line-height: 1.4;">
                            <?php echo htmlspecialchars(substr($app['developer_bio'], 0, 150)); ?>...
                        </p>
                    <?php endif; ?>
                </div>
                
                <!-- Tags -->
                <?php if ($app['tags']): ?>
                    <div class="sidebar-section">
                        <h3><i class="fas fa-tags"></i> Tags</h3>
                        <div class="app-tags">
                            <?php foreach (explode(',', $app['tags']) as $tag): ?>
                                <span class="tag"><?php echo htmlspecialchars(trim($tag)); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Screenshots -->
                <?php if (!empty($screenshots)): ?>
                    <div class="sidebar-section">
                        <h3><i class="fas fa-images"></i> Screenshots</h3>
                        <div style="display: grid; gap: 10px;">
                            <?php foreach ($screenshots as $screenshot): ?>
                                <?php 
                                $image_url = is_string($screenshot) ? $screenshot : ($screenshot['url'] ?? $screenshot['image_url'] ?? '');
                                if ($image_url): 
                                ?>
                                    <img src="<?php echo htmlspecialchars($image_url); ?>" 
                                         alt="Screenshot" 
                                         style="width: 100%; border-radius: 8px; cursor: pointer;"
                                         onclick="window.open(this.src, '_blank')">
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
