<?php
require_once __DIR__ . '/bootstrap.php';

$auth = new AuthCore();

if (!$auth->isLoggedIn()) {
    SecurityUtils::safeRedirect('auth/login.php', 302, 'Unauthorized access attempt');
}

$user = $auth->getCurrentUser();

// Check if user is admin
$stmt = $auth->getConnection()->prepare("SELECT * FROM admin_users WHERE user_id = ? AND is_active = TRUE");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$admin_user = $stmt->get_result()->fetch_assoc();

if (!$admin_user) {
    SecurityUtils::safeRedirect('../index.php', 403, 'Admin access required');
}

$page = $_GET['page'] ?? 'dashboard';
$action = $_POST['action'] ?? '';

// Handle admin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'resolve_report':
            $report_id = $_POST['report_id'] ?? null;
            $action_taken = $_POST['action_taken'] ?? 'none';
            $moderator_notes = trim($_POST['moderator_notes'] ?? '');
            
            if ($report_id) {
                $conn = $auth->getConnection();
                
                // Get report details
                $stmt = $conn->prepare("SELECT * FROM reports WHERE id = ?");
                $stmt->bind_param("i", $report_id);
                $stmt->execute();
                $report = $stmt->get_result()->fetch_assoc();
                
                if ($report) {
                    // Update report
                    $stmt = $conn->prepare("
                        UPDATE reports 
                        SET status = 'resolved', moderator_id = ?, moderator_notes = ?, 
                        action_taken = ?, resolved_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->bind_param("isssi", $user['id'], $moderator_notes, $action_taken, $report_id);
                    $stmt->execute();
                    
                    // Log moderation action
                    $stmt = $conn->prepare("
                        INSERT INTO moderation_log (moderator_id, action_type, target_type, target_id, reason, details)
                        VALUES (?, 'report_reviewed', ?, ?, ?, ?)
                    ");
                    $details = json_encode(['action_taken' => $action_taken, 'report_reason' => $report['reason']]);
                    $stmt->bind_param("isssss", $user['id'], $report['reported_type'], $report['reported_id'], $moderator_notes, $details);
                    $stmt->execute();
                    
                    // Take action based on action_taken
                    if ($action_taken === 'content_removed') {
                        removeContent($report['reported_type'], $report['reported_id'], $conn);
                    } elseif ($action_taken === 'user_warned') {
                        sendWarning($report['reported_id'], $moderator_notes, $conn);
                    } elseif ($action_taken === 'user_suspended') {
                        suspendUser($report['reported_id'], $moderator_notes, 7, $user['id'], $conn);
                    } elseif ($action_taken === 'user_banned') {
                        suspendUser($report['reported_id'], $moderator_notes, null, $user['id'], $conn);
                    } elseif ($action_taken === 'app_removed') {
                        removeApp($report['reported_id'], $conn);
                    }
                    
                    $_SESSION['success_message'] = "Report resolved successfully";
                }
            }
            header("Location: admin.php?page=reports");
            exit();
            
        case 'suspend_user':
            $user_id = $_POST['user_id'] ?? null;
            $reason = trim($_POST['reason'] ?? '');
            $duration_days = $_POST['duration_days'] ?? null;
            
            if ($user_id && $reason) {
                suspendUser($user_id, $reason, $duration_days, $user['id'], $auth->getConnection());
                $_SESSION['success_message'] = "User suspended successfully";
            }
            header("Location: admin.php?page=users");
            exit();
            
        case 'remove_content':
            $content_type = $_POST['content_type'] ?? null;
            $content_id = $_POST['content_id'] ?? null;
            $reason = trim($_POST['reason'] ?? '');
            
            if ($content_type && $content_id && $reason) {
                removeContent($content_type, $content_id, $auth->getConnection());
                $_SESSION['success_message'] = "Content removed successfully";
            }
            header("Location: admin.php?page=reports");
            exit();
    }
}

// Get statistics
$conn = $auth->getConnection();

$stats = [
    'total_reports' => 0,
    'pending_reports' => 0,
    'resolved_reports' => 0,
    'total_users' => 0,
    'suspended_users' => 0,
    'total_apps' => 0,
    'removed_apps' => 0
];

// Get report statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM reports");
$stmt->execute();
$stats['total_reports'] = $stmt->get_result()->fetch_row()[0];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM reports WHERE status = 'pending'");
$stmt->execute();
$stats['pending_reports'] = $stmt->get_result()->fetch_row()[0];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM reports WHERE status = 'resolved'");
$stmt->execute();
$stats['resolved_reports'] = $stmt->get_result()->fetch_row()[0];

// Get user statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE user_type = 'user'");
$stmt->execute();
$stats['total_users'] = $stmt->get_result()->fetch_row()[0];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_suspensions WHERE is_active = TRUE");
$stmt->execute();
$stats['suspended_users'] = $stmt->get_result()->fetch_row()[0];

// Get app statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM apps");
$stmt->execute();
$stats['total_apps'] = $stmt->get_result()->fetch_row()[0];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM apps WHERE status = 'removed'");
$stmt->execute();
$stats['removed_apps'] = $stmt->get_result()->fetch_row()[0];

// Get recent reports
$recent_reports = [];
$stmt = $conn->prepare("
    SELECT r.*, u.username as reporter_name, 
           CASE r.reported_type
               WHEN 'app' THEN (SELECT title FROM apps WHERE id = r.reported_id)
               WHEN 'review' THEN (SELECT CONCAT('Review by ', u2.username) FROM app_reviews ar JOIN users u2 ON ar.user_id = u2.id WHERE ar.id = r.reported_id)
               WHEN 'user' THEN (SELECT username FROM users WHERE id = r.reported_id)
               ELSE 'Unknown'
           END as reported_item
    FROM reports r
    JOIN users u ON r.reporter_id = u.id
    ORDER BY r.created_at DESC
    LIMIT 10
");
$stmt->execute();
$recent_reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Helper functions
function removeContent($type, $id, $conn) {
    switch ($type) {
        case 'app':
            $stmt = $conn->prepare("UPDATE apps SET status = 'removed' WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            break;
        case 'review':
            $stmt = $conn->prepare("UPDATE app_reviews SET status = 'hidden' WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            break;
        case 'comment':
            // Remove comment implementation
            break;
    }
}

function suspendUser($user_id, $reason, $duration_days, $moderator_id, $conn) {
    $is_permanent = is_null($duration_days);
    $ends_at = $duration_days ? date('Y-m-d H:i:s', strtotime("+$duration_days days")) : null;
    
    // Deactivate existing suspensions
    $stmt = $conn->prepare("UPDATE user_suspensions SET is_active = FALSE WHERE user_id = ? AND is_active = TRUE");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // Create new suspension
    $stmt = $conn->prepare("
        INSERT INTO user_suspensions (user_id, moderator_id, reason, duration_days, is_permanent, ends_at)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iisiis", $user_id, $moderator_id, $reason, $duration_days, $is_permanent, $ends_at);
    $stmt->execute();
    
    // Log action
    $stmt = $conn->prepare("
        INSERT INTO moderation_log (moderator_id, action_type, target_type, target_id, reason)
        VALUES (?, 'user_suspended', 'user', ?, ?)
    ");
    $stmt->bind_param("iis", $moderator_id, $user_id, $reason);
    $stmt->execute();
}

function sendWarning($user_id, $message, $conn) {
    // Create notification for user
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, type, title, message, is_important, icon)
        VALUES (?, 'system', 'Warning from Moderation', ?, TRUE, 'fa-exclamation-triangle')
    ");
    $stmt->bind_param("is", $user_id, $message);
    $stmt->execute();
}

function removeApp($app_id, $conn) {
    $stmt = $conn->prepare("UPDATE apps SET status = 'removed' WHERE id = ?");
    $stmt->bind_param("i", $app_id);
    $stmt->execute();
}

// Page content
$page_content = '';

switch ($page) {
    case 'dashboard':
        $page_content = include 'admin/dashboard.php';
        break;
    case 'reports':
        $page_content = include 'admin/reports.php';
        break;
    case 'users':
        $page_content = include 'admin/users.php';
        break;
    case 'apps':
        $page_content = include 'admin/apps.php';
        break;
    case 'logs':
        $page_content = include 'admin/logs.php';
        break;
    default:
        $page_content = include 'admin/dashboard.php';
}

?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Bloxer</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="logo">
                <h1>Bloxer</h1>
                <p>Admin Panel</p>
                <div class="admin-badge">
                    <i class="fas fa-shield-alt"></i>
                    <span><?php echo htmlspecialchars($admin_user['role']); ?></span>
                </div>
            </div>
            
            <nav>
                <div class="nav-item <?php echo $page === 'dashboard' ? 'active' : ''; ?>" onclick="window.location.href='admin.php?page=dashboard'">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </div>
                <div class="nav-item <?php echo $page === 'reports' ? 'active' : ''; ?>" onclick="window.location.href='admin.php?page=reports'">
                    <i class="fas fa-flag"></i>
                    <span>Reports</span>
                    <?php if ($stats['pending_reports'] > 0): ?>
                        <span class="notification-badge"><?php echo $stats['pending_reports']; ?></span>
                    <?php endif; ?>
                </div>
                <div class="nav-item <?php echo $page === 'users' ? 'active' : ''; ?>" onclick="window.location.href='admin.php?page=users'">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                </div>
                <div class="nav-item <?php echo $page === 'apps' ? 'active' : ''; ?>" onclick="window.location.href='admin.php?page=apps'">
                    <i class="fas fa-mobile-alt"></i>
                    <span>Apps</span>
                </div>
                <div class="nav-item <?php echo $page === 'logs' ? 'active' : ''; ?>" onclick="window.location.href='admin.php?page=logs'">
                    <i class="fas fa-file-alt"></i>
                    <span>Moderation Log</span>
                </div>
                <div class="nav-item" onclick="window.location.href='dashboard.php'">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Dashboard</span>
                </div>
            </nav>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <header class="main-header">
                <div class="header-content">
                    <h1>Admin Panel</h1>
                    <div class="admin-info">
                        <span class="admin-role"><?php echo htmlspecialchars($admin_user['role']); ?></span>
                        <span class="admin-name"><?php echo htmlspecialchars($user['username']); ?></span>
                    </div>
                </div>
                <div class="header-actions">
                    <button onclick="window.location.href='index.php'" class="btn btn-secondary">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </button>
                </div>
            </header>
            
            <div class="content-area">
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="success-message">
                        <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                        <?php unset($_SESSION['success_message']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['form_errors'])): ?>
                    <div class="error-message">
                        <?php foreach ($_SESSION['form_errors'] as $error): ?>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        <?php endforeach; ?>
                        <?php unset($_SESSION['form_errors']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($page === 'dashboard'): ?>
                    <!-- Dashboard Statistics -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon reports">
                                <i class="fas fa-flag"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo $stats['total_reports']; ?></h3>
                                <p>Total Reports</p>
                                <span class="stat-change pending"><?php echo $stats['pending_reports']; ?> pending</span>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon users">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo $stats['total_users']; ?></h3>
                                <p>Total Users</p>
                                <span class="stat-change suspended"><?php echo $stats['suspended_users']; ?> suspended</span>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon apps">
                                <i class="fas fa-mobile-alt"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo $stats['total_apps']; ?></h3>
                                <p>Total Apps</p>
                                <span class="stat-change removed"><?php echo $stats['removed_apps']; ?> removed</span>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon resolved">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo $stats['resolved_reports']; ?></h3>
                                <p>Resolved</p>
                                <span class="stat-change success">Moderated</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Reports -->
                    <div class="card">
                        <h2>Recent Reports</h2>
                        <div class="reports-list">
                            <?php if (!empty($recent_reports)): ?>
                                <?php foreach ($recent_reports as $report): ?>
                                    <div class="report-item">
                                        <div class="report-header">
                                            <span class="report-type"><?php echo ucfirst($report['reported_type']); ?></span>
                                            <span class="report-status <?php echo $report['status']; ?>"><?php echo ucfirst($report['status']); ?></span>
                                            <span class="report-time"><?php echo date('M j, Y H:i', strtotime($report['created_at'])); ?></span>
                                        </div>
                                        <div class="report-content">
                                            <p><strong>Reported:</strong> <?php echo htmlspecialchars($report['reported_item']); ?></p>
                                            <p><strong>Reason:</strong> <?php echo htmlspecialchars($report['reason']); ?></p>
                                            <p><strong>By:</strong> <?php echo htmlspecialchars($report['reporter_name']); ?></p>
                                        </div>
                                        <div class="report-actions">
                                            <a href="admin.php?page=reports&id=<?php echo $report['id']; ?>" class="btn btn-small">
                                                <i class="fas fa-eye"></i> Review
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="no-data">No recent reports</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <style>
        .admin-badge {
            background: var(--accent-color);
            color: white;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-top: 10px;
        }
        
        .admin-info {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .admin-role {
            background: rgba(16, 185, 129, 0.1);
            color: var(--accent-color);
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.9em;
            font-weight: 500;
        }
        
        .admin-name {
            color: var(--text-secondary);
            font-size: 0.9em;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 25px;
            display: flex;
            align-items: center;
            gap: 20px;
            backdrop-filter: blur(20px);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5em;
            color: white;
        }
        
        .stat-icon.reports { background: #ef4444; }
        .stat-icon.users { background: #3b82f6; }
        .stat-icon.apps { background: #8b5cf6; }
        .stat-icon.resolved { background: #10b981; }
        
        .stat-content h3 {
            font-size: 2em;
            margin: 0 0 5px 0;
            color: var(--text-primary);
        }
        
        .stat-content p {
            margin: 0 0 5px 0;
            color: var(--text-secondary);
        }
        
        .stat-change {
            font-size: 0.9em;
            padding: 2px 8px;
            border-radius: 12px;
        }
        
        .stat-change.pending { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        .stat-change.suspended { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .stat-change.removed { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        .stat-change.success { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        
        .reports-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .report-item {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 20px;
        }
        
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .report-type {
            background: var(--accent-color);
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 500;
        }
        
        .report-status {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 500;
        }
        
        .report-status.pending { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .report-status.under_review { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .report-status.resolved { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .report-status.dismissed { background: rgba(107, 114, 128, 0.1); color: #6b7280; }
        
        .report-time {
            color: var(--text-secondary);
            font-size: 0.9em;
        }
        
        .report-content p {
            margin: 5px 0;
            color: var(--text-secondary);
        }
        
        .report-actions {
            margin-top: 15px;
        }
        
        .no-data {
            text-align: center;
            color: var(--text-secondary);
            font-style: italic;
            padding: 40px;
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
