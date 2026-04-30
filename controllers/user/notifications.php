<?php
require_once __DIR__ . '/../../bootstrap.php';

$auth = new AuthCore();

if (!$auth->isLoggedIn()) {
    SecurityUtils::safeRedirect('../auth/login.php', 302, 'Unauthorized access attempt');
}

$user = $auth->getCurrentUser();

// Handle notification actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'mark_read') {
        $notification_id = $_POST['notification_id'] ?? null;
        
        if ($notification_id) {
            $conn = $auth->getConnection();
            $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE, read_at = NOW() WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $notification_id, $user['id']);
            $stmt->execute();
            
            echo json_encode(['success' => true]);
            exit();
        }
    } elseif ($action === 'mark_all_read') {
        $conn = $auth->getConnection();
        $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE, read_at = NOW() WHERE user_id = ? AND is_read = FALSE");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
        exit();
    } elseif ($action === 'delete_notification') {
        $notification_id = $_POST['notification_id'] ?? null;
        
        if ($notification_id) {
            $conn = $auth->getConnection();
            $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $notification_id, $user['id']);
            $stmt->execute();
            
            echo json_encode(['success' => true]);
            exit();
        }
    } elseif ($action === 'update_preferences') {
        $preferences = [
            'offer_notifications' => $_POST['offer_notifications'] ?? 'off',
            'message_notifications' => $_POST['message_notifications'] ?? 'off',
            'review_notifications' => $_POST['review_notifications'] ?? 'off',
            'update_notifications' => $_POST['update_notifications'] ?? 'off',
            'system_notifications' => $_POST['system_notifications'] ?? 'off',
            'email_notifications' => $_POST['email_notifications'] ?? 'off',
            'push_notifications' => $_POST['push_notifications'] ?? 'off'
        ];
        
        $conn = $auth->getConnection();
        
        // Check if preferences exist
        $stmt = $conn->prepare("SELECT id FROM notification_preferences WHERE user_id = ?");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            // Update existing
            $set_clauses = [];
            $types = '';
            $values = [];
            
            foreach ($preferences as $key => $value) {
                $set_clauses[] = "$key = ?";
                $types .= 's';
                $values[] = ($value === 'on' ? 1 : 0);
            }
            $values[] = $user['id'];
            $types .= 'i';
            
            $sql = "UPDATE notification_preferences SET " . implode(', ', $set_clauses) . " WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            call_user_func_array([$stmt, 'bind_param'], array_merge([$types], $values));
        } else {
            // Insert new
            $stmt = $conn->prepare("
                INSERT INTO notification_preferences (
                    user_id, offer_notifications, message_notifications, review_notifications, 
                    update_notifications, system_notifications, email_notifications, push_notifications
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $offer_notif = $preferences['offer_notifications'] === 'on' ? 1 : 0;
            $message_notif = $preferences['message_notifications'] === 'on' ? 1 : 0;
            $review_notif = $preferences['review_notifications'] === 'on' ? 1 : 0;
            $update_notif = $preferences['update_notifications'] === 'on' ? 1 : 0;
            $system_notif = $preferences['system_notifications'] === 'on' ? 1 : 0;
            $email_notif = $preferences['email_notifications'] === 'on' ? 1 : 0;
            $push_notif = $preferences['push_notifications'] === 'on' ? 1 : 0;
            
            $stmt->bind_param("iiiiiiii", 
                $user['id'],
                $offer_notif,
                $message_notif,
                $review_notif,
                $update_notif,
                $system_notif,
                $email_notif,
                $push_notif
            );
        }
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Notification preferences updated successfully!";
        } else {
            $_SESSION['form_errors'] = ['Failed to update preferences'];
        }
        
        header('Location: ../controllers/user/notifications.php');
        exit();
    }
}

// Get notifications
$conn = $auth->getConnection();

// Get notification counts
$stmt = $conn->prepare("SELECT COUNT(*) as total, COUNT(CASE WHEN is_read = FALSE THEN 1 END) as unread FROM notifications WHERE user_id = ?");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$counts = $stmt->get_result()->fetch_assoc();

// Get notifications with pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$stmt = $conn->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? 
    ORDER BY is_read ASC, created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->bind_param("iii", $user['id'], $per_page, $offset);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get user preferences
$stmt = $conn->prepare("SELECT * FROM notification_preferences WHERE user_id = ?");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$preferences = $stmt->get_result()->fetch_assoc() ?: [
    'offer_notifications' => 1,
    'message_notifications' => 1,
    'review_notifications' => 1,
    'update_notifications' => 1,
    'system_notifications' => 1,
    'email_notifications' => 0,
    'push_notifications' => 1
];

// Generate notifications from existing data (offers, reviews, messages)
function generateNotificationsFromExistingData($conn, $user_id) {
    // Check for new offers
    $stmt = $conn->prepare("
        SELECT o.*, a.title as app_title 
        FROM offers o 
        JOIN apps a ON o.app_id = a.id 
        WHERE o.developer_id = ? AND o.status = 'pending'
        AND o.id NOT IN (
            SELECT related_id FROM notifications 
            WHERE user_id = ? AND type = 'offer' AND related_id = o.id
        )
    ");
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $new_offers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($new_offers as $offer) {
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, type, title, message, related_id, related_type, action_url, icon, is_important)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $notification_type = "offer";
        $notification_title = "New Offer Received";
        $notification_message = "You received a new offer for your app '{$offer['app_title']}'";
        $notification_related_type = 'offer';
        $notification_action_url = "../core/dashboard.php?page=offers";
        $notification_icon = 'fa-handshake';
        $notification_is_important = 1;
        
        $stmt->bind_param("isssisssii", 
            $user_id,
            $notification_type,
            $notification_title,
            $notification_message,
            $offer['amount'],
            $notification_related_type,
            $notification_action_url,
            $notification_icon,
            $notification_is_important
        );
        $stmt->execute();
    }
    
    // Check for new reviews
    $stmt = $conn->prepare("
        SELECT r.*, a.title as app_title 
        FROM app_reviews r 
        JOIN apps a ON r.app_id = a.id 
        JOIN projects p ON a.project_id = p.id 
        WHERE p.user_id = ? AND r.status = 'published'
        AND r.id NOT IN (
            SELECT related_id FROM notifications 
            WHERE user_id = ? AND type = 'review' AND related_id = r.id
        )
    ");
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $new_reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($new_reviews as $review) {
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, type, title, message, related_id, related_type, action_url, icon)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $notification_type = "review";
        $notification_title = "New Review";
        $notification_message = "Your app '{$review['app_title']}' received a {$review['rating']}-star review";
        $notification_related_type = 'review';
        $notification_action_url = "app.php?id={$review['app_id']}#reviews";
        $notification_icon = 'fa-star';
        
        $stmt->bind_param("isssisss", 
            $user_id,
            $notification_type,
            $notification_title,
            $notification_message,
            $review['id'],
            $notification_related_type,
            $notification_action_url,
            $notification_icon
        );
        $stmt->execute();
    }
    
    // Check for app updates
    $stmt = $conn->prepare("
        SELECT av.*, a.title as app_title
        FROM app_versions av
        JOIN apps a ON av.app_id = a.id
        JOIN user_apps ua ON a.id = ua.app_id
        WHERE ua.user_id = ? AND av.is_current = TRUE
        AND av.id NOT IN (
            SELECT related_id FROM notifications 
            WHERE user_id = ? AND type = 'update' AND related_id = av.id
        )
    ");
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $app_updates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($app_updates as $update) {
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, type, title, message, related_id, related_type, action_url, icon)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $notification_type = "update";
        $notification_title = "App Update Available";
        $notification_message = "Update '{$update['app_title']}' from v{$update['installed_version']} to v{$update['version']}";
        $notification_related_type = 'app_version';
        $notification_action_url = "app_updates.php?id={$update['app_id']}";
        $notification_icon = 'fa-download';
        
        $stmt->bind_param("isssisss", 
            $user_id,
            $notification_type,
            $notification_title,
            $notification_message,
            $update['id'],
            $notification_related_type,
            $notification_action_url,
            $notification_icon
        );
        $stmt->execute();
    }
}

// Generate notifications from existing data
generateNotificationsFromExistingData($conn, $user['id']);

// Re-get notifications after generation
$stmt = $conn->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? 
    ORDER BY is_read ASC, created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->bind_param("iii", $user['id'], $per_page, $offset);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get updated counts
$stmt = $conn->prepare("SELECT COUNT(*) as total, COUNT(CASE WHEN is_read = FALSE THEN 1 END) as unread FROM notifications WHERE user_id = ?");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$counts = $stmt->get_result()->fetch_assoc();

// Calculate pagination
$total_pages = ceil($counts['total'] / $per_page);
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Bloxer</title>
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
                <p>Notifications</p>
            </div>
            
            <nav>
                <div class="nav-item" onclick="window.location.href='../core/dashboard.php'">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </div>
                <div class="nav-item" onclick="window.location.href='../marketplace/marketplace.php'">
                    <i class="fas fa-store"></i>
                    <span>Marketplace</span>
                </div>
                <div class="nav-item active" onclick="window.location.href='../controllers/user/notifications.php'">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
                    <?php if ($counts['unread'] > 0): ?>
                        <span class="notification-badge"><?php echo $counts['unread']; ?></span>
                    <?php endif; ?>
                </div>
                <div class="nav-item" onclick="window.location.href='../core/dashboard.php?page=projects'">
                    <i class="fas fa-folder"></i>
                    <span>Projects</span>
                </div>
            </nav>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <header class="notifications-header">
                <div class="notifications-header-content">
                    <div class="notifications-header-main">
                        <div class="notifications-header-icon">
                            <i class="fas fa-bell"></i>
                        </div>
                        <div class="notifications-header-text">
                            <h1>Notifications</h1>
                            <p>Stay updated with your latest activities and updates</p>
                        </div>
                    </div>
                    <div class="notifications-header-stats">
                        <div class="notifications-total">
                            <span class="notifications-count"><?php echo $counts['total']; ?></span>
                            <span class="notifications-label">Total</span>
                        </div>
                        <?php if ($counts['unread'] > 0): ?>
                            <div class="notifications-unread">
                                <span class="notifications-count"><?php echo $counts['unread']; ?></span>
                                <span class="notifications-label">Unread</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="notifications-header-actions">
                    <?php if ($counts['unread'] > 0): ?>
                        <button onclick="markAllRead()" class="btn btn-primary">
                            <i class="fas fa-check"></i> Mark All Read
                        </button>
                    <?php endif; ?>
                    <button onclick="showPreferences()" class="btn btn-secondary">
                        <i class="fas fa-cog"></i> Preferences
                    </button>
                </div>
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
                
                <!-- Notifications List -->
                <div class="notifications-grid">
                    <?php if (!empty($notifications)): ?>
                        <?php foreach ($notifications as $notification): ?>
                            <?php 
                            // Determine notification color based on type
                            $color_class = 'notification-blue';
                            if ($notification['type'] === 'review') {
                                $color_class = 'notification-green';
                            } elseif ($notification['type'] === 'offer') {
                                $color_class = 'notification-yellow';
                            } elseif ($notification['type'] === 'error' || $notification['type'] === 'alert') {
                                $color_class = 'notification-red';
                            } elseif ($notification['type'] === 'update') {
                                $color_class = 'notification-blue';
                            }
                            ?>
                            <article class="notification-card <?php echo $color_class; ?> <?php echo $notification['is_read'] ? 'read' : 'unread'; ?> <?php echo $notification['is_important'] ? 'important' : ''; ?>" data-id="<?php echo $notification['id']; ?>">
                                <div class="notification-card-header">
                                    <div class="notification-card-icon">
                                        <i class="<?php echo $notification['icon'] ?: 'fa-info-circle'; ?>"></i>
                                    </div>
                                    <div class="notification-card-info">
                                        <h3 class="notification-card-title"><?php echo htmlspecialchars($notification['title']); ?></h3>
                                        <span class="notification-card-time">
                                            <?php echo formatTimeAgo($notification['created_at']); ?>
                                        </span>
                                    </div>
                                    <div class="notification-card-controls">
                                        <?php if (!$notification['is_read']): ?>
                                            <button onclick="markAsRead(<?php echo $notification['id']; ?>)" class="notification-card-btn" title="Mark as read">
                                                <i class="fas fa-envelope"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button onclick="deleteNotification(<?php echo $notification['id']; ?>)" class="notification-card-btn" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="notification-card-content">
                                    <p class="notification-card-message">
                                        <?php echo htmlspecialchars($notification['message']); ?>
                                    </p>
                                    
                                    <?php if ($notification['action_url']): ?>
                                        <div class="notification-card-actions">
                                            <a href="<?php echo htmlspecialchars($notification['action_url']); ?>" class="btn btn-small btn-primary">
                                                <?php echo htmlspecialchars($notification['action_text'] ?: 'View'); ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="notifications-empty">
                            <div class="notifications-empty-icon">
                                <i class="fas fa-bell-slash"></i>
                            </div>
                            <h3>No notifications</h3>
                            <p>You're all caught up! Check back later for new updates.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>" class="pagination-link">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php endif; ?>
                        
                        <span class="pagination-info">
                            Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                        </span>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>" class="pagination-link">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Preferences Modal -->
    <div id="preferences-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Notification Preferences</h2>
                <button onclick="closePreferences()" class="btn-close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_preferences">
                
                <div class="preference-group">
                    <h3>Notification Types</h3>
                    
                    <label class="preference-item">
                        <input type="checkbox" name="offer_notifications" <?php echo $preferences['offer_notifications'] ? 'checked' : ''; ?>>
                        <span class="checkmark"></span>
                        <div class="preference-info">
                            <strong>Offers</strong>
                            <p>Get notified about new purchase offers</p>
                        </div>
                    </label>
                    
                    <label class="preference-item">
                        <input type="checkbox" name="message_notifications" <?php echo $preferences['message_notifications'] ? 'checked' : ''; ?>>
                        <span class="checkmark"></span>
                        <div class="preference-info">
                            <strong>Messages</strong>
                            <p>Receive direct messages from users</p>
                        </div>
                    </label>
                    
                    <label class="preference-item">
                        <input type="checkbox" name="review_notifications" <?php echo $preferences['review_notifications'] ? 'checked' : ''; ?>>
                        <span class="checkmark"></span>
                        <div class="preference-info">
                            <strong>Reviews</strong>
                            <p>Get notified when users review your apps</p>
                        </div>
                    </label>
                    
                    <label class="preference-item">
                        <input type="checkbox" name="update_notifications" <?php echo $preferences['update_notifications'] ? 'checked' : ''; ?>>
                        <span class="checkmark"></span>
                        <div class="preference-info">
                            <strong>App Updates</strong>
                            <p>Be notified when installed apps have updates</p>
                        </div>
                    </label>
                    
                    <label class="preference-item">
                        <input type="checkbox" name="system_notifications" <?php echo $preferences['system_notifications'] ? 'checked' : ''; ?>>
                        <span class="checkmark"></span>
                        <div class="preference-info">
                            <strong>System</strong>
                            <p>Important system announcements</p>
                        </div>
                    </label>
                </div>
                
                <div class="preference-group">
                    <h3>Delivery Methods</h3>
                    
                    <label class="preference-item">
                        <input type="checkbox" name="email_notifications" <?php echo $preferences['email_notifications'] ? 'checked' : ''; ?>>
                        <span class="checkmark"></span>
                        <div class="preference-info">
                            <strong>Email Notifications</strong>
                            <p>Receive notifications via email</p>
                        </div>
                    </label>
                    
                    <label class="preference-item">
                        <input type="checkbox" name="push_notifications" <?php echo $preferences['push_notifications'] ? 'checked' : ''; ?>>
                        <span class="checkmark"></span>
                        <div class="preference-info">
                            <strong>Push Notifications</strong>
                            <p>Browser push notifications</p>
                        </div>
                    </label>
                </div>
                
                <div class="modal-actions">
                    <button type="submit" class="btn btn-primary">Save Preferences</button>
                    <button type="button" onclick="closePreferences()" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
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
            --status-active: #E8F0FE;
            --status-active-text: #1E40AF;
            --radius-medium: 14px;
            --radius-large: 18px;
            --radius-small: 8px;
        }

        /* Base Layout */
        .dashboard-container {
            background: var(--bg-main) !important;
            min-height: 100vh;
            display: flex;
        }

        .sidebar {
            background: var(--bg-card) !important;
            border-right: 1px solid var(--border-light) !important;
            width: 280px;
            padding: 24px;
            box-shadow: 4px 0 20px var(--shadow-subtle);
        }

        .sidebar .logo h1 {
            color: var(--text-primary) !important;
            font-size: 18px;
            font-weight: 600;
            margin: 0 0 4px 0;
        }

        .sidebar .logo p {
            color: var(--text-secondary) !important;
            font-size: 12px;
            margin: 0;
        }

        .sidebar .nav-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 14px 20px;
            border-radius: var(--radius-medium);
            cursor: pointer;
            transition: all 0.2s ease;
            margin-bottom: 4px;
            color: #8E8E93 !important;
            font-weight: 500;
            font-size: 14px;
        }

        .sidebar .nav-item:hover {
            background: var(--border-lighter) !important;
            color: var(--text-primary) !important;
        }

        .sidebar .nav-item.active {
            background: rgba(0, 122, 255, 0.08) !important;
            color: var(--accent) !important;
            font-weight: 600;
        }

        .main-content {
            flex: 1;
            background: var(--bg-main) !important;
            overflow-y: auto;
        }

        .main-header {
            background: var(--bg-card) !important;
            border-bottom: 1px solid var(--border-light) !important;
            padding: 20px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 12px var(--shadow-subtle);
        }

        .main-header h1 {
            color: var(--text-primary) !important;
            font-size: 24px;
            font-weight: 600;
            margin: 0;
        }

        /* Notification Badge */
        .notification-badge {
            background: #FF3B30;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
            margin-left: auto;
        }

        .header-content {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .notification-stats {
            display: flex;
            gap: 12px;
        }

        .total-count, .unread-count {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
        }

        .total-count {
            background: var(--border-lighter);
            color: var(--text-secondary);
        }

        .unread-count {
            background: rgba(255, 59, 48, 0.1);
            color: #FF3B30;
        }

        .header-actions {
            display: flex;
            gap: 12px;
        }

        /* Content Area */
        .content-area {
            padding: 32px;
        }

        .notifications-container {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        /* Notification Items */
        .notification-item {
            display: flex;
            gap: 16px;
            padding: 24px;
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-large);
            transition: all 0.2s ease;
            box-shadow: 0 2px 12px var(--shadow-subtle);
        }

        .notification-item.unread {
            border-left: 4px solid var(--accent);
            background: rgba(0, 122, 255, 0.02);
        }

        .notification-item.important {
            border-left: 4px solid #FF3B30;
            background: rgba(255, 59, 48, 0.02);
        }

        .notification-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px var(--shadow-medium);
            border-color: var(--accent);
        }

        .notification-icon {
            width: 44px;
            height: 44px;
            border-radius: var(--radius-medium);
            background: var(--accent);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 16px;
        }

        .notification-item.important .notification-icon {
            background: #FF3B30;
        }

        .notification-content {
            flex: 1;
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }

        .notification-header h3 {
            margin: 0;
            color: var(--text-primary);
            font-size: 16px;
            font-weight: 600;
        }

        .notification-time {
            color: var(--text-secondary);
            font-size: 13px;
            font-weight: 400;
        }

        .notification-message {
            color: var(--text-secondary);
            line-height: 1.5;
            margin-bottom: 12px;
            font-size: 14px;
        }

        .notification-actions {
            display: flex;
            gap: 12px;
        }

        .notification-controls {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .btn-icon {
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 8px;
            border-radius: var(--radius-small);
            transition: all 0.2s ease;
            font-size: 14px;
        }

        .btn-icon:hover {
            background: var(--border-lighter);
            color: var(--text-primary);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: var(--text-secondary);
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-large);
            margin-top: 32px;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .empty-state h3 {
            font-size: 20px;
            font-weight: 600;
            margin: 0 0 8px 0;
            color: var(--text-primary);
        }

        .empty-state p {
            font-size: 14px;
            margin: 0;
            line-height: 1.5;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 40px;
            padding: 20px 0;
        }

        .pagination-link {
            color: var(--accent);
            text-decoration: none;
            padding: 10px 20px;
            border: 1px solid var(--accent);
            border-radius: var(--radius-medium);
            transition: all 0.2s ease;
            font-weight: 500;
            font-size: 14px;
        }

        .pagination-link:hover {
            background: var(--accent);
            color: white;
            transform: translateY(-1px);
        }

        .pagination-info {
            color: var(--text-secondary);
            font-size: 14px;
        }

        /* Preferences Modal */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
        }

        .modal-content {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-large);
            padding: 32px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px var(--shadow-medium);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }

        .modal-header h2 {
            margin: 0;
            color: var(--text-primary);
            font-size: 20px;
            font-weight: 600;
        }

        .btn-close {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 18px;
            cursor: pointer;
            padding: 8px;
            border-radius: var(--radius-small);
            transition: all 0.2s ease;
        }

        .btn-close:hover {
            background: var(--border-lighter);
            color: var(--text-primary);
        }

        .preference-group {
            margin-bottom: 32px;
        }

        .preference-group h3 {
            margin-bottom: 20px;
            color: var(--text-primary);
            font-size: 16px;
            font-weight: 600;
            border-bottom: 1px solid var(--border-light);
            padding-bottom: 12px;
        }

        .preference-item {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 20px;
            cursor: pointer;
            padding: 16px;
            border-radius: var(--radius-medium);
            transition: all 0.2s ease;
        }

        .preference-item:hover {
            background: var(--border-lighter);
        }

        .preference-item input[type="checkbox"] {
            display: none;
        }

        .checkmark {
            width: 24px;
            height: 24px;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-small);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .preference-item input[type="checkbox"]:checked + .checkmark {
            background: var(--accent);
            border-color: var(--accent);
        }

        .preference-item input[type="checkbox"]:checked + .checkmark::after {
            content: '✓';
            color: white;
            font-size: 14px;
            font-weight: 600;
        }

        .preference-info {
            flex: 1;
        }

        .preference-info strong {
            display: block;
            margin-bottom: 4px;
            color: var(--text-primary);
            font-size: 15px;
            font-weight: 500;
        }

        .preference-info p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 13px;
            line-height: 1.4;
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid var(--border-light);
        }

        /* Messages */
        .success-message {
            background: rgba(52, 199, 89, 0.1);
            border: 1px solid rgba(52, 199, 89, 0.3);
            border-radius: var(--radius-medium);
            padding: 16px;
            margin-bottom: 24px;
            color: #34C759;
            font-size: 14px;
            font-weight: 500;
        }

        .error-message {
            background: rgba(255, 59, 48, 0.1);
            border: 1px solid rgba(255, 59, 48, 0.3);
            border-radius: var(--radius-medium);
            padding: 16px;
            margin-bottom: 24px;
            color: #FF3B30;
            font-size: 14px;
            font-weight: 500;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                height: auto;
                flex-direction: row;
                padding: 16px;
                border-right: none;
                border-bottom: 1px solid var(--border-light);
            }

            .sidebar nav {
                display: flex;
                gap: 8px;
                overflow-x: auto;
            }

            .sidebar .nav-item {
                margin: 0;
                white-space: nowrap;
            }

            .content-area {
                padding: 20px;
            }

            .notification-item {
                padding: 20px;
            }

            .modal-content {
                margin: 20px;
                padding: 24px;
            }
        }
    </style>
    
    <script>
        function markAsRead(notificationId) {
            fetch('notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=mark_read&notification_id=${notificationId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const element = document.querySelector(`[data-id="${notificationId}"]`);
                    element.classList.remove('unread');
                    element.classList.add('read');
                    location.reload();
                }
            });
        }
        
        function markAllRead() {
            if (confirm('Mark all notifications as read?')) {
                fetch('notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=mark_all_read'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    }
                });
            }
        }
        
        function deleteNotification(notificationId) {
            if (confirm('Delete this notification?')) {
                fetch('notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete_notification&notification_id=${notificationId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const element = document.querySelector(`[data-id="${notificationId}"]`);
                        element.remove();
                    }
                });
            }
        }
        
        function showPreferences() {
            document.getElementById('preferences-modal').style.display = 'flex';
        }
        
        function closePreferences() {
            document.getElementById('preferences-modal').style.display = 'none';
        }
        
        function formatTimeAgo(datetime) {
            const now = new Date();
            const date = new Date(datetime);
            const seconds = Math.floor((now - date) / 1000);
            
            if (seconds < 60) return 'just now';
            if (seconds < 3600) return Math.floor(seconds / 60) + ' minutes ago';
            if (seconds < 86400) return Math.floor(seconds / 3600) + ' hours ago';
            if (seconds < 604800) return Math.floor(seconds / 86400) + ' days ago';
            return date.toLocaleDateString();
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('preferences-modal');
            if (event.target === modal) {
                closePreferences();
            }
        }
    </script>
</body>
</html>

<?php
function formatTimeAgo($datetime) {
    $now = new DateTime();
    $date = new DateTime($datetime);
    $interval = $now->diff($date);
    
    if ($interval->days > 7) {
        return $date->format('M j, Y');
    } elseif ($interval->days > 0) {
        return $interval->days . ' days ago';
    } elseif ($interval->h > 0) {
        return $interval->h . ' hours ago';
    } elseif ($interval->i > 0) {
        return $interval->i . ' minutes ago';
    } else {
        return 'just now';
    }
}
?>
