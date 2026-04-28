<?php
require_once 'mainlogincore.php';

$auth = new AuthCore();

$app_id = intval($_GET['id'] ?? 0);

if ($app_id === 0) {
    header('Location: marketplace.php');
    exit();
}

// Get app details
$conn = $auth->getConnection();
$stmt = $conn->prepare("
    SELECT a.*, u.username as developer_name, u.avatar_url as developer_avatar,
           c.name as category_name, c.icon as category_icon
    FROM apps a
    JOIN users u ON a.project_id IN (SELECT id FROM projects WHERE user_id = u.id)
    LEFT JOIN categories c ON a.category = c.slug
    WHERE a.id = ? AND a.status IN ('published', 'featured')
");
$stmt->bind_param("i", $app_id);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();

if (!$app) {
    header('Location: marketplace.php');
    exit();
}

// Get screenshots
$stmt = $conn->prepare("SELECT * FROM app_screenshots WHERE app_id = ? ORDER BY sort_order");
$stmt->bind_param("i", $app_id);
$stmt->execute();
$screenshots = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get reviews
$stmt = $conn->prepare("
    SELECT r.*, u.username, u.avatar_url
    FROM app_reviews r
    JOIN users u ON r.user_id = u.id
    WHERE r.app_id = ?
    ORDER BY r.created_at DESC
    LIMIT 10
");
$stmt->bind_param("i", $app_id);
$stmt->execute();
$reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get related apps
$stmt = $conn->prepare("
    SELECT a.*, u.username as developer_name
    FROM apps a
    JOIN users u ON a.project_id IN (SELECT id FROM projects WHERE user_id = u.id)
    WHERE a.category = ? AND a.id != ? AND a.status = 'published'
    ORDER BY a.download_count DESC
    LIMIT 4
");
$stmt->bind_param("si", $app['category'], $app_id);
$stmt->execute();
$related_apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Check if user has installed/rated this app
$is_installed = false;
$user_rating = 0;
if ($auth->isLoggedIn()) {
    $user = $auth->getCurrentUser();
    
    $stmt = $conn->prepare("SELECT id FROM user_apps WHERE user_id = ? AND app_id = ?");
    $stmt->bind_param("ii", $user['id'], $app_id);
    $stmt->execute();
    $is_installed = $stmt->get_result()->num_rows > 0;
    
    $stmt = $conn->prepare("SELECT rating FROM app_ratings WHERE user_id = ? AND app_id = ?");
    $stmt->bind_param("ii", $user['id'], $app_id);
    $stmt->execute();
    $rating_result = $stmt->get_result()->fetch_assoc();
    $user_rating = $rating_result['rating'] ?? 0;
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($app['title']); ?> - Bloxer</title>
    <link rel="stylesheet" href="marketplace.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .app-details-container {
            min-height: 100vh;
            background: var(--deep-black);
            background: linear-gradient(180deg, var(--deep-black) 0%, var(--deep-black-gradient) 100%);
            padding: 20px;
        }
        
        .back-button {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 100;
        }
        
        .back-button a {
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
        
        .back-button a:hover {
            background: rgba(255,255,255,0.15);
        }
        
        .app-header {
            max-width: 1200px;
            margin: 0 auto;
            padding: 80px 20px 40px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: center;
        }
        
        .app-preview {
            position: relative;
        }
        
        .main-screenshot {
            width: 100%;
            height: 400px;
            background: linear-gradient(135deg, var(--btn-gradient-start) 0%, var(--btn-gradient-end) 100%);
            border-radius: 20px;
            overflow: hidden;
            position: relative;
        }
        
        .main-screenshot img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .screenshot-thumbnails {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            overflow-x: auto;
        }
        
        .thumbnail {
            width: 80px;
            height: 60px;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }
        
        .thumbnail:hover,
        .thumbnail.active {
            border-color: var(--accent);
        }
        
        .thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .app-info {
            color: var(--text-primary);
        }
        
        .app-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 15px;
        }
        
        .app-developer {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .developer-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
        }
        
        .developer-info {
            display: flex;
            flex-direction: column;
        }
        
        .developer-name {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .developer-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        .app-description {
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 25px;
        }
        
        .app-meta {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
        }
        
        .meta-item i {
            color: var(--accent);
        }
        
        .app-actions {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .btn-install {
            background: linear-gradient(135deg, var(--btn-gradient-start) 0%, var(--btn-gradient-end) 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-install:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.3);
        }
        
        .btn-install.installed {
            background: var(--success);
        }
        
        .btn-favorite {
            background: rgba(255,255,255,0.1);
            border: 1px solid var(--glass-border);
            color: var(--text-primary);
            padding: 15px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-favorite:hover {
            background: rgba(255,255,255,0.15);
        }
        
        .btn-favorite.favorited {
            color: var(--error);
        }
        
        .rating-section {
            margin-bottom: 25px;
        }
        
        .rating-display {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
        }
        
        .rating-stars {
            display: flex;
            gap: 5px;
        }
        
        .rating-stars .star {
            color: #ffd700;
            font-size: 1.2rem;
        }
        
        .rating-stars .star.empty {
            color: var(--glass-border);
        }
        
        .rating-text {
            color: var(--text-secondary);
        }
        
        .user-rating {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-rating .star {
            cursor: pointer;
            color: var(--glass-border);
            transition: color 0.3s ease;
        }
        
        .user-rating .star:hover,
        .user-rating .star.active {
            color: #ffd700;
        }
        
        .content-sections {
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
            margin-bottom: 20px;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .reviews-grid {
            display: grid;
            gap: 20px;
        }
        
        .review-card {
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 20px;
        }
        
        .review-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .review-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
        }
        
        .review-info {
            flex: 1;
        }
        
        .review-name {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .review-date {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        .review-rating {
            display: flex;
            gap: 2px;
        }
        
        .review-text {
            color: var(--text-secondary);
            line-height: 1.5;
        }
        
        .related-apps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .related-app-card {
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .related-app-card:hover {
            transform: translateY(-2px);
            background: rgba(255,255,255,0.04);
        }
        
        .related-app-title {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 10px;
        }
        
        .related-app-developer {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        @media (max-width: 768px) {
            .app-header {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .app-title {
                font-size: 2rem;
            }
            
            .app-meta {
                grid-template-columns: 1fr;
            }
            
            .app-actions {
                flex-direction: column;
            }
            
            .related-apps {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="app-details-container">
        <!-- Back Button -->
        <div class="back-button">
            <a href="marketplace.php">
                <i class="fas fa-arrow-left"></i>
                Back to Marketplace
            </a>
        </div>
        
        <!-- App Header -->
        <div class="app-header">
            <div class="app-preview">
                <div class="main-screenshot">
                    <?php if (!empty($screenshots)): ?>
                        <img id="mainScreenshot" src="<?php echo htmlspecialchars($screenshots[0]['image_url']); ?>" alt="<?php echo htmlspecialchars($app['title']); ?>">
                    <?php else: ?>
                        <div style="display: flex; align-items: center; justify-content: center; height: 100%;">
                            <i class="fas fa-rocket" style="font-size: 4rem; color: rgba(255,255,255,0.8);"></i>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (count($screenshots) > 1): ?>
                    <div class="screenshot-thumbnails">
                        <?php foreach ($screenshots as $index => $screenshot): ?>
                            <div class="thumbnail <?php echo $index === 0 ? 'active' : ''; ?>" onclick="changeScreenshot(<?php echo $index; ?>)">
                                <img src="<?php echo htmlspecialchars($screenshot['image_url']); ?>" alt="Screenshot <?php echo $index + 1; ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="app-info">
                <h1 class="app-title"><?php echo htmlspecialchars($app['title']); ?></h1>
                
                <div class="app-developer">
                    <img src="<?php echo $app['developer_avatar'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($app['developer_name']) . '&background=6366f1&color=fff'; ?>" 
                         alt="Developer" class="developer-avatar">
                    <div class="developer-info">
                        <span class="developer-name"><?php echo htmlspecialchars($app['developer_name']); ?></span>
                        <span class="developer-label">Developer</span>
                    </div>
                </div>
                
                <p class="app-description"><?php echo htmlspecialchars($app['description']); ?></p>
                
                <div class="app-meta">
                    <div class="meta-item">
                        <i class="fas fa-folder"></i>
                        <span><?php echo htmlspecialchars($app['category_name'] ?? 'General'); ?></span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-download"></i>
                        <span><?php echo number_format($app['download_count']); ?> downloads</span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-calendar"></i>
                        <span><?php echo date('M j, Y', strtotime($app['published_at'])); ?></span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-code-branch"></i>
                        <span>Version <?php echo htmlspecialchars($app['version'] ?? '1.0.0'); ?></span>
                    </div>
                </div>
                
                <div class="app-actions">
                    <?php if ($auth->isLoggedIn()): ?>
                        <?php if ($is_installed): ?>
                            <button class="btn-install installed" disabled>
                                <i class="fas fa-check"></i>
                                Installed
                            </button>
                        <?php else: ?>
                            <button class="btn-install" onclick="installApp(<?php echo $app_id; ?>)">
                                <i class="fas fa-download"></i>
                                Install
                            </button>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="login.php" class="btn-install">
                            <i class="fas fa-sign-in-alt"></i>
                            Login to Install
                        </a>
                    <?php endif; ?>
                    
                    <button class="btn-favorite" onclick="toggleFavorite()">
                        <i class="fas fa-heart"></i>
                    </button>
                </div>
                
                <div class="rating-section">
                    <div class="rating-display">
                        <div class="rating-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star star <?php echo $i <= round($app['rating']) ? '' : 'empty'; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <span class="rating-text"><?php echo number_format($app['rating'], 1); ?> out of 5</span>
                    </div>
                    
                    <?php if ($auth->isLoggedIn() && $is_installed): ?>
                        <div class="user-rating">
                            <span>Your rating:</span>
                            <div class="rating-stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star star <?php echo $i <= $user_rating ? 'active' : ''; ?>" 
                                       onclick="rateApp(<?php echo $app_id; ?>, <?php echo $i; ?>)"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Content Sections -->
        <div class="content-sections">
            <!-- Reviews Section -->
            <div class="section">
                <h2 class="section-title">
                    <i class="fas fa-star"></i>
                    Reviews
                </h2>
                
                <?php if (!empty($reviews)): ?>
                    <div class="reviews-grid">
                        <?php foreach ($reviews as $review): ?>
                            <div class="review-card">
                                <div class="review-header">
                                    <img src="<?php echo $review['avatar_url'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($review['username']) . '&background=6366f1&color=fff'; ?>" 
                                         alt="User" class="review-avatar">
                                    <div class="review-info">
                                        <div class="review-name"><?php echo htmlspecialchars($review['username']); ?></div>
                                        <div class="review-date"><?php echo date('M j, Y', strtotime($review['created_at'])); ?></div>
                                    </div>
                                    <div class="review-rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star" style="color: <?php echo $i <= $review['rating'] ? '#ffd700' : 'var(--glass-border)'; ?>; font-size: 0.8rem;"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <div class="review-text">
                                    <?php echo htmlspecialchars($review['review_text']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: var(--text-secondary);">No reviews yet. Be the first to review this app!</p>
                <?php endif; ?>
            </div>
            
            <!-- Related Apps Section -->
            <?php if (!empty($related_apps)): ?>
                <div class="section">
                    <h2 class="section-title">
                        <i class="fas fa-th"></i>
                        Related Applications
                    </h2>
                    
                    <div class="related-apps">
                        <?php foreach ($related_apps as $related_app): ?>
                            <div class="related-app-card" onclick="openApp(<?php echo $related_app['id']; ?>)">
                                <h3 class="related-app-title"><?php echo htmlspecialchars($related_app['title']); ?></h3>
                                <p class="related-app-developer">by <?php echo htmlspecialchars($related_app['developer_name']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function changeScreenshot(index) {
            const screenshots = <?php echo json_encode($screenshots); ?>;
            const mainImg = document.getElementById('mainScreenshot');
            const thumbnails = document.querySelectorAll('.thumbnail');
            
            if (mainImg && screenshots[index]) {
                mainImg.src = screenshots[index].image_url;
            }
            
            thumbnails.forEach((thumb, i) => {
                thumb.classList.toggle('active', i === index);
            });
        }
        
        async function installApp(appId) {
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
                    const btn = document.querySelector('.btn-install');
                    btn.classList.add('installed');
                    btn.innerHTML = '<i class="fas fa-check"></i> Installed';
                    btn.disabled = true;
                    
                    // Show rating section
                    location.reload();
                } else {
                    alert(result.error || 'Failed to install app');
                }
            } catch (error) {
                console.error('Error installing app:', error);
                alert('An error occurred while installing the app');
            }
        }
        
        async function rateApp(appId, rating) {
            try {
                const response = await fetch('marketplace-api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=rate_app&app_id=${appId}&rating=${rating}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    location.reload();
                } else {
                    alert(result.error || 'Failed to submit rating');
                }
            } catch (error) {
                console.error('Error rating app:', error);
                alert('An error occurred while submitting your rating');
            }
        }
        
        function toggleFavorite() {
            const btn = document.querySelector('.btn-favorite');
            btn.classList.toggle('favorited');
        }
        
        function openApp(appId) {
            window.location.href = `app-details.php?id=${appId}`;
        }
    </script>
</body>
</html>
