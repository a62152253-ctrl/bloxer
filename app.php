<?php
require_once 'mainlogincore.php';

$auth = new AuthCore();
$user = $auth->getCurrentUser();

$app_id = $_GET['id'] ?? null;

if (!$app_id) {
    header('Location: marketplace.php');
    exit();
}

// Get app details
$conn = $auth->getConnection();
$stmt = $conn->prepare("
    SELECT a.*, u.id as developer_id, u.username as developer_name, u.avatar_url as developer_avatar, u.email as developer_email, u.bio as developer_bio,
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
    header('Location: marketplace.php');
    exit();
}

// Get app versions
$stmt = $conn->prepare("
    SELECT * FROM app_versions WHERE app_id = ? ORDER BY version DESC
");
$stmt->bind_param("i", $app_id);
$stmt->execute();
$app_versions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get app reviews
$stmt = $conn->prepare("
    SELECT r.*, u.username, u.avatar_url
    FROM app_reviews r
    JOIN users u ON r.user_id = u.id
    WHERE r.app_id = ?
    ORDER BY r.created_at DESC
    LIMIT 20
");
$stmt->bind_param("i", $app_id);
$stmt->execute();
$reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Check if user has installed/saved this app
$user_app_status = null;
$offer = null;
$offer_messages = [];
if ($user) {
    $stmt = $conn->prepare("
        SELECT status FROM user_apps WHERE user_id = ? AND app_id = ?
    ");
    $stmt->bind_param("ii", $user['id'], $app_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user_app_status = $result->fetch_assoc()['status'];
    }

    if ($user['id'] !== $app['developer_id']) {
        $stmt = $conn->prepare("SELECT o.* FROM offers o WHERE o.app_id = ? AND o.buyer_id = ? ORDER BY o.created_at DESC LIMIT 1");
        $stmt->bind_param("ii", $app_id, $user['id']);
        $stmt->execute();
        $offer = $stmt->get_result()->fetch_assoc();

        if ($offer) {
            $stmt = $conn->prepare("SELECT om.*, u.username FROM offer_messages om JOIN users u ON om.sender_id = u.id WHERE om.offer_id = ? ORDER BY om.created_at ASC");
            $stmt->bind_param("i", $offer['id']);
            $stmt->execute();
            $offer_messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
    }
}

// Increment view count
$stmt = $conn->prepare("UPDATE apps SET view_count = view_count + 1 WHERE id = ?");
$stmt->bind_param("i", $app_id);
$stmt->execute();
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($app['title']); ?> - Bloxer</title>
    <link rel="stylesheet" href="app.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .app-container {
            min-height: 100vh;
            background: var(--deep-black);
            background: linear-gradient(180deg, var(--deep-black) 0%, var(--deep-black-gradient) 100%);
        }
        
        .app-header {
            background: linear-gradient(135deg, var(--btn-gradient-start) 0%, var(--btn-gradient-end) 100%);
            padding: 60px 20px;
            position: relative;
            overflow: hidden;
        }
        
        .app-header::before {
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
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 40px;
            align-items: center;
        }
        
        .app-info {
            color: white;
        }
        
        .app-title {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 20px;
            text-shadow: 0 0 30px rgba(255, 255, 255, 0.3);
        }
        
        .app-description {
            font-size: 1.2rem;
            margin-bottom: 30px;
            opacity: 0.9;
            line-height: 1.6;
        }
        
        .app-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1rem;
        }
        
        .app-actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .btn-large {
            padding: 15px 30px;
            font-size: 1.1rem;
            border-radius: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-weight: 600;
        }
        
        .btn-primary {
            background: white;
            color: var(--btn-gradient-start);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(255, 255, 255, 0.3);
        }
        
        .btn-secondary {
            background: rgba(255,255,255,0.1);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .btn-secondary:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .app-thumbnail {
            width: 100%;
            height: 300px;
            background: rgba(255,255,255,0.1);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .app-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .app-thumbnail i {
            font-size: 4rem;
            color: rgba(255,255,255,0.8);
        }
        
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 40px;
        }
        
        .section {
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 30px;
            backdrop-filter: blur(20px);
            margin-bottom: 30px;
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
        
        .screenshots {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .screenshot {
            width: 100%;
            height: 150px;
            background: rgba(255,255,255,0.06);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .screenshot:hover {
            transform: scale(1.05);
        }
        
        .screenshot img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .demo-frame {
            width: 100%;
            height: 500px;
            background: white;
            border-radius: 12px;
            border: none;
        }
        
        .developer-info {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px;
            background: rgba(255,255,255,0.06);
            border-radius: 15px;
            margin-bottom: 20px;
        }
        
        .developer-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
        }
        
        .developer-details {
            flex: 1;
        }
        
        .developer-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
        }
        
        .developer-bio {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .stat-item {
            text-align: center;
            padding: 15px;
            background: rgba(255,255,255,0.06);
            border-radius: 12px;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--btn-gradient-start);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .review {
            padding: 20px;
            background: rgba(255,255,255,0.06);
            border-radius: 15px;
            margin-bottom: 15px;
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        
        .reviewer-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .reviewer-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
        }
        
        .reviewer-name {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .review-rating {
            display: flex;
            gap: 2px;
            color: var(--btn-gradient-start);
        }
        
        .review-text {
            color: var(--text-secondary);
            line-height: 1.5;
            margin-bottom: 10px;
        }
        
        .review-date {
            color: var(--text-muted);
            font-size: 0.8rem;
        }
        
        .rating-summary {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .rating-large {
            font-size: 3rem;
            font-weight: 800;
            color: var(--btn-gradient-start);
        }
        
        .rating-details {
            flex: 1;
        }
        
        .rating-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 5px;
        }
        
        .rating-bar-label {
            width: 60px;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        .rating-bar-track {
            flex: 1;
            height: 8px;
            background: rgba(255,255,255,0.1);
            border-radius: 4px;
            overflow: hidden;
        }
        
        .rating-bar-fill {
            height: 100%;
            background: var(--btn-gradient-start);
            border-radius: 4px;
            transition: width 0.3s ease;
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
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            backdrop-filter: blur(20px);
            text-align: center;
        }
        
        @media (max-width: 768px) {
            .header-content {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .app-title {
                font-size: 2rem;
            }
            
            .app-actions {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
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
        
        <!-- App Header -->
        <header class="app-header">
            <div class="header-content">
                <div class="app-info">
                    <h1 class="app-title"><?php echo htmlspecialchars($app['title']); ?></h1>
                    <p class="app-description"><?php echo htmlspecialchars($app['description']); ?></p>
                    
                    <div class="app-meta">
                        <div class="meta-item">
                            <i class="fas fa-<?php echo $app['category_icon'] ?? 'folder'; ?>"></i>
                            <span><?php echo htmlspecialchars($app['category_name'] ?? 'General'); ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-star"></i>
                            <span><?php echo number_format($app['rating'], 1); ?> (<?php echo $app['rating_count']; ?> reviews)</span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-download"></i>
                            <span><?php echo number_format($app['download_count']); ?> downloads</span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-tag"></i>
                            <span><?php echo $app['is_free'] ? 'Free' : '$' . number_format($app['price'], 2); ?></span>
                        </div>
                    </div>
                    
                    <div class="app-actions">
                        <?php if ($user): ?>
                            <?php if ($user_app_status === 'installed'): ?>
                                <button class="btn-large btn-primary" onclick="openApp()">
                                    <i class="fas fa-play"></i>
                                    Open App
                                </button>
                            <?php else: ?>
                                <button class="btn-large btn-primary" onclick="installApp()">
                                    <i class="fas fa-download"></i>
                                    <?php echo $user_app_status === 'saved' ? 'Install' : 'Install'; ?>
                                </button>
                            <?php endif; ?>
                        <?php else: ?>
                            <a href="login.php" class="btn-large btn-primary">
                                <i class="fas fa-sign-in-alt"></i>
                                Login to Install
                            </a>
                        <?php endif; ?>

                        <?php if (!empty($app['demo_url'])): ?>
                            <a href="<?php echo htmlspecialchars($app['demo_url']); ?>" target="_blank" rel="noreferrer noopener" class="btn-large btn-secondary">
                                <i class="fas fa-play-circle"></i>
                                Live Preview
                            </a>
                        <?php endif; ?>

                        <?php if (!empty($app['developer_email'])): ?>
                            <a href="mailto:<?php echo htmlspecialchars($app['developer_email']); ?>?subject=<?php echo urlencode('Zapytanie o ' . $app['title']); ?>" class="btn-large btn-secondary">
                                <i class="fas fa-envelope"></i>
                                Contact Developer
                            </a>
                        <?php endif; ?>

                        <a href="marketplace.php" class="btn-large btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            Back to Marketplace
                        </a>
                    </div>
                </div>
                
                <div class="app-thumbnail">
                    <?php if ($app['thumbnail_url']): ?>
                        <img src="<?php echo htmlspecialchars($app['thumbnail_url']); ?>" alt="<?php echo htmlspecialchars($app['title']); ?>">
                    <?php else: ?>
                        <i class="fas fa-rocket"></i>
                    <?php endif; ?>
                </div>
            </div>
        </header>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="content-grid">
                <div class="main-column">
                    <!-- Screenshots -->
                    <?php if ($app['screenshots']): ?>
                        <div class="section">
                            <h2 class="section-title">
                                <i class="fas fa-images"></i>
                                Screenshots
                            </h2>
                            <div class="screenshots">
                                <?php 
                                $screenshots = json_decode($app['screenshots'], true) ?? [];
                                foreach ($screenshots as $screenshot): 
                                ?>
                                    <div class="screenshot" onclick="openScreenshot('<?php echo htmlspecialchars($screenshot); ?>')">
                                        <img src="<?php echo htmlspecialchars($screenshot); ?>" alt="Screenshot">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Live Demo -->
                    <?php if ($app['demo_url']): ?>
                        <div class="section">
                            <h2 class="section-title">
                                <i class="fas fa-play"></i>
                                Try Live Demo
                            </h2>
                            <iframe src="<?php echo htmlspecialchars($app['demo_url']); ?>" class="demo-frame"></iframe>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Reviews -->
                    <div class="section">
                        <h2 class="section-title">
                            <i class="fas fa-star"></i>
                            Reviews & Ratings
                        </h2>
                        
                        <div class="rating-summary">
                            <div class="rating-large"><?php echo number_format($app['rating'], 1); ?></div>
                            <div class="rating-details">
                                <div class="rating-bar">
                                    <span class="rating-bar-label">5 stars</span>
                                    <div class="rating-bar-track">
                                        <div class="rating-bar-fill" style="width: 70%;"></div>
                                    </div>
                                </div>
                                <div class="rating-bar">
                                    <span class="rating-bar-label">4 stars</span>
                                    <div class="rating-bar-track">
                                        <div class="rating-bar-fill" style="width: 20%;"></div>
                                    </div>
                                </div>
                                <div class="rating-bar">
                                    <span class="rating-bar-label">3 stars</span>
                                    <div class="rating-bar-track">
                                        <div class="rating-bar-fill" style="width: 7%;"></div>
                                    </div>
                                </div>
                                <div class="rating-bar">
                                    <span class="rating-bar-label">2 stars</span>
                                    <div class="rating-bar-track">
                                        <div class="rating-bar-fill" style="width: 2%;"></div>
                                    </div>
                                </div>
                                <div class="rating-bar">
                                    <span class="rating-bar-label">1 star</span>
                                    <div class="rating-bar-track">
                                        <div class="rating-bar-fill" style="width: 1%;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($user && $user_app_status): ?>
                            <button class="btn btn-primary" onclick="showReviewModal()" style="margin-bottom: 20px;">
                                <i class="fas fa-pen"></i>
                                Write a Review
                            </button>
                        <?php endif; ?>
                        
                        <?php foreach ($reviews as $review): ?>
                            <div class="review">
                                <div class="review-header">
                                    <div class="reviewer-info">
                                        <img src="<?php echo $review['avatar_url'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($review['username']) . '&background=6366f1&color=fff'; ?>" 
                                             alt="Reviewer" class="reviewer-avatar">
                                        <div>
                                            <div class="reviewer-name"><?php echo htmlspecialchars($review['username']); ?></div>
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
                    </div>
                </div>
                
                <div class="sidebar">
                    <!-- Developer Info -->
                    <div class="section">
                        <h3 class="section-title">
                            <i class="fas fa-user"></i>
                            Developer
                        </h3>
                        <div class="developer-info">
                            <img src="<?php echo $app['developer_avatar'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($app['developer_name']) . '&background=6366f1&color=fff'; ?>" 
                                 alt="Developer" class="developer-avatar">
                            <div class="developer-details">
                                <div class="developer-name"><?php echo htmlspecialchars($app['developer_name']); ?></div>
                                <div class="developer-bio"><?php echo htmlspecialchars($app['developer_bio'] ?? 'Web application developer'); ?></div>
                            </div>
                        </div>
                    </div>

                    <?php if ($user && !$auth->isDeveloper() && $user['id'] !== $app['developer_id']): ?>
                        <div class="section">
                            <h3 class="section-title">
                                <i class="fas fa-handshake"></i>
                                Deal Offer
                            </h3>

                            <?php if ($offer): ?>
                                <div style="margin-bottom: 15px; padding: 15px; background: rgba(99,102,241,0.08); border-radius: 14px;">
                                    <div style="display: flex; justify-content: space-between; gap: 10px; flex-wrap: wrap;">
                                        <div>
                                            <strong>Status:</strong>
                                            <span style="color: <?php echo $offer['status'] === 'accepted' ? '#4ade80' : ($offer['status'] === 'declined' ? '#f87171' : '#facc15'); ?>;">
                                                <?php echo ucfirst($offer['status']); ?>
                                            </span>
                                        </div>
                                        <div>
                                            <strong>Amount:</strong> $<?php echo number_format($offer['amount'], 2); ?>
                                        </div>
                                    </div>
                                    <div style="margin-top: 10px; color: var(--text-secondary);">
                                        <strong>Phone:</strong> <?php echo htmlspecialchars($offer['phone_number'] ?: 'Not provided'); ?>
                                    </div>
                                    <?php if (!empty($offer['subject'])): ?>
                                        <div style="margin-top: 10px; color: var(--text-secondary);">
                                            <strong>Message:</strong>
                                            <div style="margin-top: 5px; color: var(--text-primary);">
                                                <?php echo nl2br(htmlspecialchars($offer['subject'])); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($offer['transfer_notes'])): ?>
                                        <div style="margin-top: 12px; padding: 12px; background: rgba(16,185,129,0.08); border-radius: 12px;">
                                            <strong>Project transfer note:</strong>
                                            <div style="margin-top: 6px; color: var(--text-secondary);">
                                                <?php echo nl2br(htmlspecialchars($offer['transfer_notes'])); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($offer_messages)): ?>
                                    <div style="margin-bottom: 20px; max-height: 280px; overflow-y: auto; padding-right: 5px;">
                                        <?php foreach ($offer_messages as $message): ?>
                                            <div style="margin-bottom: 10px; display: flex; flex-direction: column; align-items: <?php echo $message['sender_id'] === $user['id'] ? 'flex-end' : 'flex-start'; ?>;">
                                                <div style="max-width: 100%; padding: 12px 15px; border-radius: 18px; background: <?php echo $message['sender_id'] === $user['id'] ? 'rgba(99,102,241,0.14)' : 'rgba(255,255,255,0.08)'; ?>; color: var(--text-primary);">
                                                    <div style="font-size: 0.85rem; margin-bottom: 6px; color: var(--text-secondary);">
                                                        <?php echo htmlspecialchars($message['username']); ?>
                                                    </div>
                                                    <div><?php echo nl2br(htmlspecialchars($message['message'])); ?></div>
                                                    <div style="margin-top: 8px; font-size: 0.8rem; color: var(--text-muted);">
                                                        <?php echo date('M j, Y H:i', strtotime($message['created_at'])); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($offer['status'] === 'pending'): ?>
                                    <form id="offer-message-form">
                                        <div class="form-group">
                                            <label for="offer-message">Add a message</label>
                                            <textarea id="offer-message" name="message" rows="4" placeholder="Send a quick follow-up or ask for next steps"></textarea>
                                        </div>
                                        <button type="button" class="btn btn-primary" onclick="sendOfferMessage()">
                                            <i class="fas fa-paper-plane"></i>
                                            Send Message
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <div style="margin-top: 10px; color: var(--text-secondary);">
                                        Your offer has been <?php echo $offer['status']; ?>. Check your email or developer chat to continue.
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <form id="deal-form" style="display: grid; gap: 15px;">
                                    <div class="form-group">
                                        <label for="deal-amount">Offer Amount</label>
                                        <input type="number" id="deal-amount" name="amount" min="1" step="0.01" placeholder="50.00">
                                    </div>
                                    <div class="form-group">
                                        <label for="deal-phone">Phone Number</label>
                                        <input type="text" id="deal-phone" name="phone_number" placeholder="+48 123 456 789">
                                    </div>
                                    <div class="form-group">
                                        <label for="deal-message">Message</label>
                                        <textarea id="deal-message" name="message" rows="4" placeholder="Share your project goal or handoff details..."></textarea>
                                    </div>
                                    <button type="button" class="btn btn-primary" onclick="sendOffer()">
                                        <i class="fas fa-gavel"></i>
                                        Send Deal Offer
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($auth->isDeveloper() && $user['id'] === $app['developer_id']): ?>
                        <div class="section" style="background: rgba(255,255,255,0.04);">
                            <h3 class="section-title">
                                <i class="fas fa-comments"></i>
                                Deal requests
                            </h3>
                            <p style="color: var(--text-secondary); margin: 0;">Manage incoming offers and chat with buyers from your dashboard.</p>
                        </div>
                    <?php endif; ?>

                    <!-- Statistics -->
                    <div class="section">
                        <h3 class="section-title">
                            <i class="fas fa-chart-bar"></i>
                            Statistics
                        </h3>
                        <div class="stats-grid">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo number_format($app['download_count']); ?></div>
                                <div class="stat-label">Downloads</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo number_format($app['view_count']); ?></div>
                                <div class="stat-label">Views</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo number_format($app['rating'], 1); ?></div>
                                <div class="stat-label">Rating</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $app['rating_count']; ?></div>
                                <div class="stat-label">Reviews</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Version Info -->
                    <?php if (!empty($app_versions)): ?>
                        <div class="section">
                            <h3 class="section-title">
                                <i class="fas fa-code-branch"></i>
                                Versions
                            </h3>
                            <?php foreach ($app_versions as $version): ?>
                                <div style="padding: 15px; background: rgba(255,255,255,0.06); border-radius: 12px; margin-bottom: 10px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                                        <strong>v<?php echo htmlspecialchars($version['version']); ?></strong>
                                        <?php if ($version['is_current']): ?>
                                            <span style="background: var(--btn-gradient-start); color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem;">Current</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($version['changelog']): ?>
                                        <div style="color: var(--text-secondary); font-size: 0.9rem;">
                                            <?php echo htmlspecialchars($version['changelog']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div style="color: var(--text-muted); font-size: 0.8rem; margin-top: 5px;">
                                        Released <?php echo date('M j, Y', strtotime($version['created_at'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Review Modal -->
    <div class="modal" id="review-modal">
        <div class="modal-content">
            <h3 style="margin-bottom: 20px;">Write a Review</h3>
            <form id="review-form">
                <div class="form-group">
                    <label>Rating</label>
                    <div style="display: flex; gap: 10px; justify-content: center; margin: 20px 0;">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <button type="button" class="star-rating" data-rating="<?php echo $i; ?>" onclick="setRating(<?php echo $i; ?>)">
                                <i class="fas fa-star" style="font-size: 2rem; color: var(--text-muted); cursor: pointer;"></i>
                            </button>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="review-text">Your Review</label>
                    <textarea id="review-text" name="review" rows="4" placeholder="Share your experience with this app..."></textarea>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn">Submit Review</button>
                    <button type="button" class="btn" style="background: var(--input-bg);" onclick="closeReviewModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        let selectedRating = 0;
        
        function installApp() {
            fetch('app_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=install&app_id=<?php echo $app_id; ?>`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }
        
        function toggleSave() {
            fetch('app_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=toggle_save&app_id=<?php echo $app_id; ?>`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }
        
        function openApp() {
            window.open(`run_app.php?id=<?php echo $app_id; ?>`, '_blank');
        }
        
        function showReviewModal() {
            document.getElementById('review-modal').style.display = 'flex';
        }
        
        function closeReviewModal() {
            document.getElementById('review-modal').style.display = 'none';
        }
        
        function setRating(rating) {
            selectedRating = rating;
            document.querySelectorAll('.star-rating i').forEach((star, index) => {
                star.style.color = index < rating ? 'var(--btn-gradient-start)' : 'var(--text-muted)';
            });
        }
        
        document.getElementById('review-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (selectedRating === 0) {
                alert('Please select a rating');
                return;
            }
            
            fetch('app_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=review&app_id=<?php echo $app_id; ?>&rating=${selectedRating}&review=${encodeURIComponent(document.getElementById('review-text').value)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        });
        
        function openScreenshot(src) {
            window.open(src, '_blank');
        }

        function sendOffer() {
            const amount = document.getElementById('deal-amount').value;
            const phoneNumber = document.getElementById('deal-phone').value;
            const message = document.getElementById('deal-message').value;

            fetch('app_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=send_offer&app_id=<?php echo $app_id; ?>&amount=${encodeURIComponent(amount)}&phone_number=${encodeURIComponent(phoneNumber)}&message=${encodeURIComponent(message)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.error || 'Unable to send offer.');
                }
            });
        }

        function sendOfferMessage() {
            const message = document.getElementById('offer-message').value;

            fetch('app_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=send_offer_message&offer_id=<?php echo $offer ? $offer['id'] : 0; ?>&message=${encodeURIComponent(message)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.error || 'Unable to send message.');
                }
            });
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('review-modal');
            if (event.target === modal) {
                closeReviewModal();
            }
        }
    </script>
</body>
</html>
