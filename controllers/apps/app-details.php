<?php
require_once '../core/mainlogincore.php';

$auth = new AuthCore();

$app_id = intval($_GET['id'] ?? 0);

if ($app_id === 0) {
    header('Location: ../marketplace/marketplace.php');
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
    header('Location: ../marketplace/marketplace.php');
    exit();
}

// Get screenshots
$stmt = $conn->prepare("SELECT * FROM app_screenshots WHERE app_id = ? ORDER BY sort_order");
$stmt->bind_param("i", $app_id);
$stmt->execute();
$screenshots = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get ratings and reviews
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$stmt = $conn->prepare("
    SELECT ar.*, u.username, u.avatar_url,
           CASE WHEN ar.user_id = ? THEN 1 ELSE 0 END as is_own_rating
    FROM app_ratings ar
    JOIN users u ON ar.user_id = u.id
    WHERE ar.app_id = ?
    ORDER BY ar.created_at DESC
    LIMIT ? OFFSET ?
");
$user_id = $user ? $user['id'] : 0;
$stmt->bind_param("iiii", $user_id, $app_id, $limit, $offset);
$stmt->execute();
$reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get rating breakdown
$stmt = $conn->prepare("
    SELECT rating, COUNT(*) as count
    FROM app_ratings
    WHERE app_id = ?
    GROUP BY rating
    ORDER BY rating DESC
");
$stmt->bind_param("i", $app_id);
$stmt->execute();
$rating_breakdown = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get total rating count
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM app_ratings WHERE app_id = ?");
$stmt->bind_param("i", $app_id);
$stmt->execute();
$total_ratings = $stmt->get_result()->fetch_assoc()['total'];

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
    <link rel="stylesheet" href="../../assets/css/marketplace.css">
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
            font-size: 2.8rem;
            font-weight: 800;
            margin-bottom: 20px;
            background: linear-gradient(135deg, var(--text-primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .app-developer {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            padding: 15px 20px;
            background: linear-gradient(135deg, rgba(255,255,255,0.05), rgba(255,255,255,0.02));
            border-radius: 15px;
            border: 1px solid var(--glass-border);
            transition: all 0.3s ease;
        }
        
        .app-developer:hover {
            background: linear-gradient(135deg, rgba(255,255,255,0.08), rgba(255,255,255,0.04));
            transform: translateY(-2px);
        }
        
        .developer-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 2px solid var(--accent);
            box-shadow: 0 4px 15px rgba(99,102,241,0.3);
        }
        
        .developer-info {
            display: flex;
            flex-direction: column;
        }
        
        .developer-name {
            font-weight: 700;
            color: var(--text-primary);
            font-size: 1.1rem;
        }
        
        .developer-label {
            font-size: 0.85rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .app-description {
            color: var(--text-secondary);
            line-height: 1.7;
            margin-bottom: 30px;
            font-size: 1.1rem;
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
        
        .rating-overview {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .rating-summary {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        
        .rating-average {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
        
        .rating-number {
            font-size: 3rem;
            font-weight: 800;
            color: var(--text-primary);
        }
        
        .rating-info {
            text-align: center;
        }
        
        .rating-count {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
        }
        
        .rating-text {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .rating-breakdown {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .rating-bar {
            display: grid;
            grid-template-columns: 60px 1fr 30px;
            align-items: center;
            gap: 10px;
        }
        
        .rating-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .bar-container {
            height: 8px;
            background: var(--glass-border);
            border-radius: 4px;
            overflow: hidden;
        }
        
        .bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--btn-gradient-start) 0%, var(--btn-gradient-end) 100%);
            transition: width 0.3s ease;
        }
        
        .rating-stars {
            display: flex;
            gap: 3px;
        }
        
        .rating-stars .star {
            color: #ffd700;
            font-size: 1.2rem;
        }
        
        .rating-stars .star.empty {
            color: var(--glass-border);
        }
        
        .user-review-section {
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            padding: 25px;
            margin-top: 20px;
        }
        
        .user-review-section h3 {
            color: var(--text-primary);
            margin-bottom: 20px;
        }
        
        .existing-rating {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .rating-input {
            margin-bottom: 20px;
        }
        
        .rating-input label {
            display: block;
            color: var(--text-primary);
            font-weight: 500;
            margin-bottom: 10px;
        }
        
        .review-input {
            margin-bottom: 20px;
        }
        
        .review-input label {
            display: block;
            color: var(--text-primary);
            font-weight: 500;
            margin-bottom: 10px;
        }
        
        .review-input textarea {
            width: 100%;
            padding: 12px;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 8px;
            color: var(--text-primary);
            font-family: inherit;
            resize: vertical;
        }
        
        .review-input textarea:focus {
            outline: none;
            border-color: var(--input-focus-border);
            box-shadow: 0 0 0 3px var(--input-focus-shadow);
        }
        
        .review-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-submit-review, .btn-edit-review, .btn-delete-review {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-submit-review {
            background: var(--accent);
            color: white;
        }
        
        .btn-submit-review:hover {
            background: var(--accent-hover);
        }
        
        .btn-edit-review {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--glass-border);
        }
        
        .btn-delete-review {
            background: var(--error);
            color: white;
        }
        
        .btn-edit-review:hover, .btn-delete-review:hover {
            opacity: 0.8;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .reviews-count {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .review-actions {
            display: flex;
            gap: 8px;
        }
        
        .btn-edit, .btn-delete {
            padding: 4px 8px;
            border: none;
            border-radius: 4px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-edit {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }
        
        .btn-delete {
            background: var(--error);
            color: white;
        }
        
        .btn-edit:hover, .btn-delete:hover {
            opacity: 0.8;
        }
        
        .no-reviews {
            text-align: center;
            padding: 40px;
            color: var(--text-secondary);
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
        }
        
        .page-link {
            padding: 8px 12px;
            background: var(--bg-secondary);
            border: 1px solid var(--glass-border);
            border-radius: 6px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .page-link:hover {
            background: var(--bg-hover);
            color: var(--text-primary);
        }
        
        .page-link.active {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
        }
        
        /* Comments Section */
        .comment-form {
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .comment-form textarea {
            width: 100%;
            padding: 12px;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 8px;
            color: var(--text-primary);
            font-family: inherit;
            resize: vertical;
            margin-bottom: 15px;
        }
        
        .comment-form textarea:focus {
            outline: none;
            border-color: var(--input-focus-border);
            box-shadow: 0 0 0 3px var(--input-focus-shadow);
        }
        
        .btn-submit-comment {
            background: var(--accent);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-submit-comment:hover {
            background: var(--accent-hover);
        }
        
        .login-prompt {
            text-align: center;
            padding: 20px;
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            margin-bottom: 30px;
        }
        
        .login-prompt a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-prompt a:hover {
            text-decoration: underline;
        }
        
        .comments-container {
            margin-top: 20px;
        }
        
        .loading-comments {
            text-align: center;
            padding: 40px;
            color: var(--text-secondary);
        }
        
        .loading-comments i {
            font-size: 2rem;
            margin-bottom: 15px;
        }
        
        .comment {
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
        }
        
        .comment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .comment-author {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .comment-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
        }
        
        .comment-name {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .comment-date {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        .comment-actions {
            display: flex;
            gap: 8px;
        }
        
        .btn-delete-comment {
            background: var(--error);
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-delete-comment:hover {
            opacity: 0.8;
        }
        
        .comment-text {
            color: var(--text-secondary);
            line-height: 1.5;
            margin-bottom: 15px;
            white-space: pre-wrap;
        }
        
        .comment-replies {
            margin-left: 20px;
            border-left: 2px solid var(--glass-border);
            padding-left: 15px;
        }
        
        .reply {
            margin-top: 10px;
            padding: 15px;
            background: rgba(255,255,255,0.01);
            border-radius: 8px;
        }
        
        .reply-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }
        
        .reply-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
        }
        
        .reply-name {
            font-weight: 500;
            color: var(--text-primary);
            font-size: 0.9rem;
        }
        
        .reply-date {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
        
        .reply-text {
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.4;
            white-space: pre-wrap;
        }
        
        .comment-reply-form {
            margin-top: 10px;
        }
        
        .comment-reply-form textarea {
            width: 100%;
            padding: 8px;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 6px;
            color: var(--text-primary);
            font-family: inherit;
            resize: vertical;
            font-size: 0.9rem;
        }
        
        .comment-reply-actions {
            display: flex;
            gap: 8px;
            margin-top: 8px;
        }
        
        .btn-reply {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--glass-border);
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-reply:hover {
            background: var(--bg-hover);
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
                    <div class="rating-overview">
                        <div class="rating-summary">
                            <div class="rating-average">
                                <span class="rating-number"><?php echo number_format($app['rating'], 1); ?></span>
                                <div class="rating-stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star star <?php echo $i <= round($app['rating']) ? '' : 'empty'; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div class="rating-info">
                                <div class="rating-count"><?php echo $total_ratings; ?> ratings</div>
                                <div class="rating-text">Based on user reviews</div>
                            </div>
                        </div>
                        
                        <div class="rating-breakdown">
                            <?php foreach ($rating_breakdown as $breakdown): ?>
                                <div class="rating-bar">
                                    <span class="rating-label"><?php echo $breakdown['rating']; ?> stars</span>
                                    <div class="bar-container">
                                        <div class="bar-fill" style="width: <?php echo $total_ratings > 0 ? ($breakdown['count'] / $total_ratings) * 100 : 0; ?>%"></div>
                                    </div>
                                    <span class="rating-count"><?php echo $breakdown['count']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <?php if ($auth->isLoggedIn() && $is_installed): ?>
                        <div class="user-review-section">
                            <h3>Your Review</h3>
                            <?php if ($user_rating > 0): ?>
                                <div class="existing-rating">
                                    <div class="rating-stars">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star star <?php echo $i <= $user_rating ? 'active' : ''; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <button class="btn-edit-review" onclick="showReviewForm()">Edit Review</button>
                                </div>
                            <?php endif; ?>
                            
                            <div class="review-form" id="reviewForm" style="<?php echo $user_rating > 0 ? 'display:none;' : ''; ?>">
                                <div class="rating-input">
                                    <label>Your Rating:</label>
                                    <div class="rating-stars" id="userRatingStars">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star star" data-rating="<?php echo $i; ?>" onclick="setRating(<?php echo $i; ?>)"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <div class="review-input">
                                    <label for="reviewText">Your Review:</label>
                                    <textarea id="reviewText" placeholder="Share your experience with this app..." rows="4" maxlength="1000"></textarea>
                                    <div class="review-actions">
                                        <button class="btn-submit-review" onclick="submitReview()">Submit Review</button>
                                        <?php if ($user_rating > 0): ?>
                                            <button class="btn-delete-review" onclick="deleteReview()">Delete Review</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
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
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-star"></i>
                        User Reviews
                    </h2>
                    <span class="reviews-count"><?php echo $total_ratings; ?> reviews</span>
                </div>
                
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
                                            <i class="fas fa-star" style="color: <?php echo $i <= $review['rating'] ? '#ffd700' : 'var(--glass-border)'; ?>; font-size: 0.9rem;"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <?php if ($review['is_own_rating']): ?>
                                        <div class="review-actions">
                                            <button class="btn-edit" onclick="showReviewForm()">Edit</button>
                                            <button class="btn-delete" onclick="deleteReview()">Delete</button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($review['review'])): ?>
                                    <div class="review-text">
                                        <?php echo htmlspecialchars($review['review']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php
                    $total_pages = ceil($total_ratings / $limit);
                    if ($total_pages > 1):
                    ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?id=<?php echo $app_id; ?>&page=<?php echo $page - 1; ?>" class="page-link">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <a href="?id=<?php echo $app_id; ?>&page=<?php echo $i; ?>" 
                                   class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?id=<?php echo $app_id; ?>&page=<?php echo $page + 1; ?>" class="page-link">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="no-reviews">
                        <i class="fas fa-comment-slash" style="font-size: 3rem; color: var(--text-secondary); margin-bottom: 15px;"></i>
                        <p>No reviews yet. Be the first to review this app!</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Comments Section -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-comments"></i>
                        Comments
                    </h2>
                    <span class="comments-count" id="comments-count">Loading...</span>
                </div>
                
                <?php if ($auth->isLoggedIn()): ?>
                    <div class="comment-form">
                        <textarea id="commentText" placeholder="Share your thoughts about this app..." rows="3" maxlength="1000"></textarea>
                        <div class="comment-actions">
                            <button class="btn-submit-comment" onclick="submitComment()">Post Comment</button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="login-prompt">
                        <p><a href="login.php">Login</a> to post comments</p>
                    </div>
                <?php endif; ?>
                
                <div class="comments-container" id="comments-container">
                    <div class="loading-comments">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Loading comments...</p>
                    </div>
                </div>
                
                <!-- Comments Pagination -->
                <div class="pagination" id="comments-pagination" style="display: none;">
                </div>
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
        
        let selectedRating = 0;
        
        function setRating(rating) {
            selectedRating = rating;
            const stars = document.querySelectorAll('#userRatingStars .star');
            stars.forEach((star, index) => {
                star.classList.toggle('active', index < rating);
            });
        }
        
        function showReviewForm() {
            const form = document.getElementById('reviewForm');
            const existing = document.querySelector('.existing-rating');
            
            if (existing) {
                existing.style.display = 'none';
            }
            form.style.display = 'block';
        }
        
        async function submitReview() {
            const reviewText = document.getElementById('reviewText').value.trim();
            
            if (selectedRating === 0) {
                alert('Please select a rating');
                return;
            }
            
            if (reviewText.length > 1000) {
                alert('Review must be less than 1000 characters');
                return;
            }
            
            try {
                const response = await fetch('ratings.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=submit_rating&app_id=<?php echo $app_id; ?>&rating=${selectedRating}&review=${encodeURIComponent(reviewText)}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    location.reload();
                } else {
                    alert(result.error || 'Failed to submit review');
                }
            } catch (error) {
                console.error('Error submitting review:', error);
                alert('An error occurred while submitting your review');
            }
        }
        
        async function deleteReview() {
            if (!confirm('Are you sure you want to delete your review?')) {
                return;
            }
            
            try {
                const response = await fetch('ratings.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete_rating&app_id=<?php echo $app_id; ?>`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    location.reload();
                } else {
                    alert(result.error || 'Failed to delete review');
                }
            } catch (error) {
                console.error('Error deleting review:', error);
                alert('An error occurred while deleting your review');
            }
        }
        
        function toggleFavorite() {
            const btn = document.querySelector('.btn-favorite');
            btn.classList.toggle('favorited');
        }
        
        function openApp(appId) {
            window.location.href = `app-details.php?id=${appId}`;
        }
        
        // Comments System
        let currentCommentPage = 1;
        
        async function loadComments(page = 1) {
            try {
                const response = await fetch('comments.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=get_comments&app_id=<?php echo $app_id; ?>&page=${page}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    displayComments(result.comments);
                    updateCommentsPagination(result.pages, page);
                    updateCommentsCount(result.total);
                    currentCommentPage = page;
                } else {
                    console.error('Failed to load comments:', result);
                }
            } catch (error) {
                console.error('Error loading comments:', error);
            }
        }
        
        function displayComments(comments) {
            const container = document.getElementById('comments-container');
            
            if (comments.length === 0) {
                container.innerHTML = `
                    <div class="no-comments">
                        <i class="fas fa-comments" style="font-size: 2rem; color: var(--text-secondary); margin-bottom: 15px;"></i>
                        <p>No comments yet. Be the first to share your thoughts!</p>
                    </div>
                `;
                return;
            }
            
            let html = '';
            comments.forEach(comment => {
                html += createCommentHTML(comment);
            });
            
            container.innerHTML = html;
        }
        
        function createCommentHTML(comment) {
            const date = new Date(comment.created_at);
            const dateStr = date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
            
            let repliesHTML = '';
            if (comment.replies && comment.replies.length > 0) {
                repliesHTML = '<div class="comment-replies">';
                comment.replies.forEach(reply => {
                    const replyDate = new Date(reply.created_at);
                    const replyDateStr = replyDate.toLocaleDateString() + ' ' + replyDate.toLocaleTimeString();
                    
                    repliesHTML += `
                        <div class="reply">
                            <div class="reply-header">
                                <img src="${reply.avatar_url || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(reply.username) + '&background=6366f1&color=fff'}" 
                                     alt="${reply.username}" class="reply-avatar">
                                <span class="reply-name">${reply.username}</span>
                                <span class="reply-date">${replyDateStr}</span>
                            </div>
                            <div class="reply-text">${reply.comment_text}</div>
                        </div>
                    `;
                });
                repliesHTML += '</div>';
            }
            
            return `
                <div class="comment" data-comment-id="${comment.id}">
                    <div class="comment-header">
                        <div class="comment-author">
                            <img src="${comment.avatar_url || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(comment.username) + '&background=6366f1&color=fff'}" 
                                 alt="${comment.username}" class="comment-avatar">
                            <span class="comment-name">${comment.username}</span>
                        </div>
                        <div class="comment-actions">
                            <span class="comment-date">${dateStr}</span>
                            ${comment.is_own_comment ? `<button class="btn-delete-comment" onclick="deleteComment(${comment.id})">Delete</button>` : ''}
                        </div>
                    </div>
                    <div class="comment-text">${comment.comment_text}</div>
                    ${comment.is_own_comment ? `
                        <div class="comment-reply-form">
                            <textarea id="reply-${comment.id}" placeholder="Write a reply..." rows="2"></textarea>
                            <div class="comment-reply-actions">
                                <button class="btn-reply" onclick="submitReply(${comment.id})">Reply</button>
                            </div>
                        </div>
                    ` : ''}
                    ${repliesHTML}
                </div>
            `;
        }
        
        async function submitComment() {
            const commentText = document.getElementById('commentText').value.trim();
            
            if (!commentText) {
                alert('Please enter a comment');
                return;
            }
            
            if (commentText.length > 1000) {
                alert('Comment must be less than 1000 characters');
                return;
            }
            
            try {
                const response = await fetch('comments.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=submit_comment&app_id=<?php echo $app_id; ?>&comment=${encodeURIComponent(commentText)}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    document.getElementById('commentText').value = '';
                    loadComments(1); // Reload comments
                } else {
                    alert(result.error || 'Failed to post comment');
                }
            } catch (error) {
                console.error('Error posting comment:', error);
                alert('An error occurred while posting your comment');
            }
        }
        
        async function submitReply(parentId) {
            const replyText = document.getElementById(`reply-${parentId}`).value.trim();
            
            if (!replyText) {
                alert('Please enter a reply');
                return;
            }
            
            try {
                const response = await fetch('comments.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=submit_comment&app_id=<?php echo $app_id; ?>&parent_id=${parentId}&comment=${encodeURIComponent(replyText)}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    loadComments(currentCommentPage); // Reload comments
                } else {
                    alert(result.error || 'Failed to post reply');
                }
            } catch (error) {
                console.error('Error posting reply:', error);
                alert('An error occurred while posting your reply');
            }
        }
        
        async function deleteComment(commentId) {
            if (!confirm('Are you sure you want to delete this comment and all its replies?')) {
                return;
            }
            
            try {
                const response = await fetch('comments.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete_comment&comment_id=${commentId}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    loadComments(currentCommentPage); // Reload comments
                } else {
                    alert(result.error || 'Failed to delete comment');
                }
            } catch (error) {
                console.error('Error deleting comment:', error);
                alert('An error occurred while deleting the comment');
            }
        }
        
        function updateCommentsCount(total) {
            const countElement = document.getElementById('comments-count');
            if (countElement) {
                countElement.textContent = `${total} comment${total !== 1 ? 's' : ''}`;
            }
        }
        
        function updateCommentsPagination(totalPages, currentPage) {
            const paginationContainer = document.getElementById('comments-pagination');
            
            if (totalPages <= 1) {
                paginationContainer.style.display = 'none';
                return;
            }
            
            paginationContainer.style.display = 'flex';
            
            let html = '';
            
            // Previous button
            if (currentPage > 1) {
                html += `<a href="#" class="page-link" onclick="loadComments(${currentPage - 1})">Previous</a>`;
            }
            
            // Page numbers
            const startPage = Math.max(1, currentPage - 2);
            const endPage = Math.min(totalPages, currentPage + 2);
            
            for (let i = startPage; i <= endPage; i++) {
                html += `<a href="#" class="page-link ${i === currentPage ? 'active' : ''}" onclick="loadComments(${i})">${i}</a>`;
            }
            
            // Next button
            if (currentPage < totalPages) {
                html += `<a href="#" class="page-link" onclick="loadComments(${currentPage + 1})">Next</a>`;
            }
            
            paginationContainer.innerHTML = html;
        }
        
        // Load comments when page loads
        document.addEventListener('DOMContentLoaded', function() {
            loadComments(1);
        });
    </script>
</body>
</html>
