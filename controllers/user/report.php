<?php
require_once __DIR__ . '/../../bootstrap.php';

$auth = new AuthCore();

if (!$auth->isLoggedIn()) {
    SecurityUtils::safeRedirect('../auth/login.php', 302, 'Unauthorized access attempt');
}

$user = $auth->getCurrentUser();

// Handle report submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'submit_report') {
    $reported_type = SecurityUtils::validateInput($_POST['reported_type'] ?? '', 'string', 20);
    $reported_id = SecurityUtils::validateInput($_POST['reported_id'] ?? null, 'int');
    $reason = SecurityUtils::validateInput($_POST['reason'] ?? '', 'string', 50);
    $description = SecurityUtils::validateInput(trim($_POST['description'] ?? ''), 'string', 1000);
    
    if ($reported_type && $reported_id && $reason && $description) {
        $conn = $auth->getConnection();
        
        // Check if user already reported this item
        $stmt = $conn->prepare("
            SELECT id FROM reports 
            WHERE reporter_id = ? AND reported_type = ? AND reported_id = ? AND status != 'dismissed'
        ");
        $stmt->bind_param("isi", $user['id'], $reported_type, $reported_id);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows === 0) {
            // Create new report
            $stmt = $conn->prepare("
                INSERT INTO reports (reporter_id, reported_type, reported_id, reason, description)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("issss", $user['id'], $reported_type, $reported_id, $reason, $description);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Report submitted successfully. Our moderators will review it shortly.";
            } else {
                $_SESSION['form_errors'] = ['Failed to submit report. Please try again.'];
            }
        } else {
            $_SESSION['form_errors'] = ['You have already reported this item.'];
        }
        
        // Redirect back
        $redirect_url = SecurityUtils::validateInput($_POST['redirect_url'] ?? '../marketplace/marketplace.php', 'url', 500);
        SecurityUtils::safeRedirect($redirect_url, 302, 'Report submitted');
    }

// Get item details for reporting
$reported_type = SecurityUtils::validateInput($_GET['type'] ?? '', 'string', 20);
$reported_id = SecurityUtils::validateInput($_GET['id'] ?? null, 'int');

$item_details = null;
if ($reported_type && $reported_id) {
    $conn = $auth->getConnection();
    
    switch ($reported_type) {
        case 'app':
            $stmt = $conn->prepare("
                SELECT a.*, u.username as developer_name 
                FROM apps a 
                JOIN projects p ON a.project_id = p.id 
                JOIN users u ON p.user_id = u.id 
                WHERE a.id = ?
            ");
            $stmt->bind_param("i", $reported_id);
            $stmt->execute();
            $item_details = $stmt->get_result()->fetch_assoc();
            break;
            
        case 'review':
            $stmt = $conn->prepare("
                SELECT r.*, a.title as app_title, u.username as reviewer_name 
                FROM app_reviews r 
                JOIN apps a ON r.app_id = a.id 
                JOIN users u ON r.user_id = u.id 
                WHERE r.id = ?
            ");
            $stmt->bind_param("i", $reported_id);
            $stmt->execute();
            $item_details = $stmt->get_result()->fetch_assoc();
            break;
            
        case 'user':
            $stmt = $conn->prepare("SELECT id, username, email, avatar_url, bio, user_type, created_at FROM users WHERE id = ?");
            $stmt->bind_param("i", $reported_id);
            $stmt->execute();
            $item_details = $stmt->get_result()->fetch_assoc();
            break;
    }
}

if (!$item_details) {
    $_SESSION['form_errors'] = ['Invalid item for reporting'];
    SecurityUtils::safeRedirect('../marketplace/marketplace.php', 404, 'Invalid item for reporting');
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Content - Bloxer</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="logo">
                <h1>Bloxer</h1>
                <p>Report Content</p>
            </div>
            
            <nav>
                <div class="nav-item" onclick="window.location.href='../marketplace/marketplace.php'">
                    <i class="fas fa-store"></i>
                    <span>Marketplace</span>
                </div>
                <div class="nav-item" onclick="window.location.href='../core/dashboard.php'">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </div>
            </nav>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <header class="main-header">
                <div class="header-content">
                    <h1>Report Content</h1>
                </div>
                <button onclick="history.back()" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
            </header>
            
            <div class="content-area">
                <?php if (isset($_SESSION['form_errors'])): ?>
                    <div class="error-message">
                        <?php foreach ($_SESSION['form_errors'] as $error): ?>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        <?php endforeach; ?>
                        <?php unset($_SESSION['form_errors']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="success-message">
                        <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                        <?php unset($_SESSION['success_message']); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Item Being Reported -->
                <div class="card reported-item">
                    <h2>Content Being Reported</h2>
                    <div class="item-preview">
                        <?php if ($reported_type === 'app'): ?>
                            <div class="app-preview">
                                <h3><?php echo htmlspecialchars($item_details['title']); ?></h3>
                                <p class="developer">by <?php echo htmlspecialchars($item_details['developer_name']); ?></p>
                                <p class="category">Category: <?php echo htmlspecialchars($item_details['category']); ?></p>
                                <p class="description"><?php echo htmlspecialchars(substr($item_details['description'], 0, 200)) . '...'; ?></p>
                            </div>
                        <?php elseif ($reported_type === 'review'): ?>
                            <div class="review-preview">
                                <h3>Review for: <?php echo htmlspecialchars($item_details['app_title']); ?></h3>
                                <p class="reviewer">by <?php echo htmlspecialchars($item_details['reviewer_name']); ?></p>
                                <div class="rating">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?php echo $i <= $item_details['rating'] ? 'active' : ''; ?>"></i>
                                    <?php endfor; ?>
                                    <span><?php echo $item_details['rating']; ?>/5</span>
                                </div>
                                <p class="review-text"><?php echo htmlspecialchars($item_details['review']); ?></p>
                            </div>
                        <?php elseif ($reported_type === 'user'): ?>
                            <div class="user-preview">
                                <h3><?php echo htmlspecialchars($item_details['username']); ?></h3>
                                <p class="user-type">Type: <?php echo ucfirst($item_details['user_type']); ?></p>
                                <p class="joined">Joined: <?php echo date('M j, Y', strtotime($item_details['created_at'])); ?></p>
                                <?php if ($item_details['bio']): ?>
                                    <p class="bio"><?php echo htmlspecialchars(substr($item_details['bio'], 0, 200)) . '...'; ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Report Form -->
                <div class="card report-form">
                    <h2>Report Details</h2>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="submit_report">
                        <input type="hidden" name="reported_type" value="<?php echo htmlspecialchars($reported_type); ?>">
                        <input type="hidden" name="reported_id" value="<?php echo htmlspecialchars($reported_id); ?>">
                        <input type="hidden" name="redirect_url" value="<?php echo htmlspecialchars($_SERVER['HTTP_REFERER']); ?>">
                        
                        <div class="form-group">
                            <label for="reason">Reason for Report *</label>
                            <select id="reason" name="reason" required>
                                <option value="">Select a reason...</option>
                                <option value="inappropriate_content">Inappropriate Content</option>
                                <option value="spam">Spam</option>
                                <option value="harassment">Harassment</option>
                                <option value="copyright">Copyright Violation</option>
                                <option value="fake_app">Fake/Misleading App</option>
                                <option value="malware">Malware/Virus</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description *</label>
                            <textarea id="description" name="description" rows="6" required
                                      placeholder="Please provide detailed information about why you are reporting this content."></textarea>
                        </div>
                        
                        <div class="guidelines">
                            <h3>Reporting Guidelines</h3>
                            <ul>
                                <li>Be specific and provide as much detail as possible</li>
                                <li>Only report content that violates our terms of service</li>
                                <li>Do not use the report system for personal disputes</li>
                                <li>False reports may result in account suspension</li>
                            </ul>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-flag"></i> Submit Report
                            </button>
                            <button type="button" onclick="history.back()" class="btn btn-secondary">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <style>
        .reported-item {
            border-left: 4px solid #ef4444;
            background: rgba(239, 68, 68, 0.05);
        }
        
        .item-preview {
            background: var(--input-bg);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .app-preview h3,
        .review-preview h3,
        .user-preview h3 {
            margin: 0 0 10px 0;
            color: var(--text-primary);
        }
        
        .app-preview .developer,
        .review-preview .reviewer,
        .user-preview .user-type {
            color: var(--text-secondary);
            font-size: 0.9em;
            margin-bottom: 5px;
        }
        
        .app-preview .category,
        .user-preview .joined {
            color: var(--text-secondary);
            font-size: 0.9em;
            margin-bottom: 10px;
        }
        
        .app-preview .description,
        .review-preview .review-text,
        .user-preview .bio {
            color: var(--text-secondary);
            line-height: 1.5;
        }
        
        .review-preview .rating {
            display: flex;
            align-items: center;
            gap: 5px;
            margin: 10px 0;
        }
        
        .review-preview .rating .fa-star {
            color: #fbbf24;
        }
        
        .review-preview .rating .fa-star.active {
            color: #f59e0b;
        }
        
        .guidelines {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .guidelines h3 {
            margin: 0 0 15px 0;
            color: #3b82f6;
        }
        
        .guidelines ul {
            margin: 0;
            padding-left: 20px;
            color: var(--text-secondary);
        }
        
        .guidelines li {
            margin-bottom: 8px;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
            border: none;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        .success-message {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 20px;
            color: #10b981;
        }
        
        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 20px;
            color: #ef4444;
        }
    </style>
</body>
</html>
<?php
}
?>
