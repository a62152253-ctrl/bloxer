<?php
require_once __DIR__ . '/../../bootstrap.php';

$auth = new AuthCore();

if (!$auth->isLoggedIn()) {
    SecurityUtils::safeRedirect('../auth/login.php', 302, 'Unauthorized access attempt');
}

$user = $auth->getCurrentUser();
$conn = $auth->getConnection();

// Get user's follow feed
$stmt = $conn->prepare("
    SELECT daf.*, u.username as developer_name, u.avatar_url as developer_avatar,
           a.title as app_title, a.thumbnail_url as app_thumbnail
    FROM user_follow_feed uff
    JOIN developer_activity_feed daf ON uff.activity_id = daf.id
    JOIN users u ON daf.developer_id = u.id
    LEFT JOIN apps a ON daf.related_id = a.id AND daf.related_type = 'app'
    WHERE uff.user_id = ? AND uff.is_read = FALSE
    ORDER BY daf.created_at DESC
    LIMIT 20
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$follow_feed = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get developers user follows
$stmt = $conn->prepare("
    SELECT u.id, u.username, u.avatar_url, dp.bio, dp.company_name,
           COUNT(DISTINCT daf.id) as recent_activities
    FROM developer_follows df
    JOIN users u ON df.developer_id = u.id
    LEFT JOIN developer_profiles dp ON dp.user_id = u.id
    LEFT JOIN developer_activity_feed daf ON daf.developer_id = u.id AND daf.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    WHERE df.follower_id = ?
    GROUP BY u.id
    ORDER BY recent_activities DESC, u.username
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$following = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Mark notifications as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'mark_read') {
    $activity_id = $_POST['activity_id'] ?? null;
    
    if ($activity_id) {
        $stmt = $conn->prepare("UPDATE user_follow_feed SET is_read = TRUE WHERE user_id = ? AND activity_id = ?");
        $stmt->bind_param("ii", $user['id'], $activity_id);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
        exit();
    }
}

// Get recommended developers (based on user's interests)
$stmt = $conn->prepare("
    SELECT u.id, u.username, u.avatar_url, dp.bio, dp.company_name,
           dp.total_apps, dp.total_downloads,
           COUNT(DISTINCT df.follower_id) as followers
    FROM users u
    JOIN developer_profiles dp ON dp.user_id = u.id
    LEFT JOIN developer_follows df ON dp.user_id = df.developer_id
    WHERE u.user_type = 'developer' AND u.id != ?
    GROUP BY u.id
    ORDER BY dp.total_downloads DESC, followers DESC
    LIMIT 10
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$recommended_developers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Follow Feed - Bloxer</title>
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
                <p>Follow Feed</p>
            </div>
            
            <nav>
                <div class="nav-item active" onclick="window.location.href='../controllers/user/follow_feed.php'">
                    <i class="fas fa-users"></i>
                    <span>Following</span>
                </div>
                <div class="nav-item" onclick="window.location.href='../controllers/user/personalized_feed.php'">
                    <i class="fas fa-home"></i>
                    <span>For You</span>
                </div>
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
                    <h1>Following Feed</h1>
                    <p class="feed-description">Updates from developers you follow</p>
                </div>
                <div class="header-actions">
                    <button onclick="markAllRead()" class="btn btn-secondary">
                        <i class="fas fa-check-double"></i> Mark All Read
                    </button>
                </div>
            </header>
            
            <div class="content-area">
                <!-- Following Stats -->
                <div class="following-stats">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo count($following); ?></h3>
                            <p>Following</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-stream"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo count($follow_feed); ?></h3>
                            <p>Unread Updates</p>
                        </div>
                    </div>
                </div>
                
                <!-- Follow Feed -->
                <div class="section">
                    <div class="section-header">
                        <h2>
                            <i class="fas fa-stream"></i>
                            Recent Updates
                        </h2>
                        <span class="section-subtitle">From developers you follow</span>
                    </div>
                    
                    <div class="follow-feed">
                        <?php if (!empty($follow_feed)): ?>
                            <?php foreach ($follow_feed as $activity): ?>
                                <div class="feed-item" data-activity-id="<?php echo $activity['id']; ?>">
                                    <div class="feed-header">
                                        <div class="developer-info">
                                            <img src="<?php echo htmlspecialchars($activity['developer_avatar'] ?: '../assets/images/default-avatar.png'); ?>" alt="<?php echo htmlspecialchars($activity['developer_name']); ?>" class="developer-avatar">
                                            <div class="developer-details">
                                                <h4><?php echo htmlspecialchars($activity['developer_name']); ?></h4>
                                                <span class="activity-time"><?php echo formatDate($activity['created_at']); ?></span>
                                            </div>
                                        </div>
                                        <button onclick="markAsRead(<?php echo $activity['id']; ?>)" class="mark-read-btn">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </div>
                                    
                                    <div class="feed-content">
                                        <div class="activity-icon">
                                            <i class="fas <?php echo getActivityIcon($activity['activity_type']); ?>"></i>
                                        </div>
                                        <div class="activity-details">
                                            <h3><?php echo htmlspecialchars($activity['title']); ?></h3>
                                            <p><?php echo htmlspecialchars($activity['description']); ?></p>
                                            
                                            <?php if ($activity['app_title']): ?>
                                                <div class="related-app">
                                                    <img src="<?php echo htmlspecialchars($activity['app_thumbnail'] ?: '../assets/images/default-app.png'); ?>" alt="<?php echo htmlspecialchars($activity['app_title']); ?>">
                                                    <div class="app-info">
                                                        <h4><?php echo htmlspecialchars($activity['app_title']); ?></h4>
                                                        <button onclick="window.location.href='../controllers/apps/app.php?id=<?php echo $activity['related_id']; ?>'" class="btn btn-small">
                                                            <i class="fas fa-eye"></i> View
                                                        </button>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-stream"></i>
                                <h3>No recent updates</h3>
                                <p>Follow some developers to see their updates here!</p>
                                <button onclick="showDiscoverDevelopers()" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Discover Developers
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Following List -->
                <div class="section">
                    <div class="section-header">
                        <h2>
                            <i class="fas fa-users"></i>
                            Following
                        </h2>
                        <span class="section-subtitle"><?php echo count($following); ?> developers</span>
                    </div>
                    
                    <div class="following-grid">
                        <?php foreach ($following as $dev): ?>
                            <div class="developer-card">
                                <div class="developer-header">
                                    <img src="<?php echo htmlspecialchars($dev['avatar_url'] ?: '../assets/images/default-avatar.png'); ?>" alt="<?php echo htmlspecialchars($dev['username']); ?>" class="developer-avatar">
                                    <div class="developer-info">
                                        <h4><?php echo htmlspecialchars($dev['username']); ?></h4>
                                        <p><?php echo htmlspecialchars($dev['company_name'] ?: 'Independent Developer'); ?></p>
                                    </div>
                                    <button onclick="unfollowDeveloper(<?php echo $dev['id']; ?>)" class="btn btn-small btn-secondary">
                                        <i class="fas fa-user-minus"></i> Unfollow
                                    </button>
                                </div>
                                
                                <div class="developer-stats">
                                    <div class="stat">
                                        <span class="stat-number"><?php echo $dev['followers']; ?></span>
                                        <span class="stat-label">Followers</span>
                                    </div>
                                    <div class="stat">
                                        <span class="stat-number"><?php echo $dev['total_apps']; ?></span>
                                        <span class="stat-label">Apps</span>
                                    </div>
                                    <div class="stat">
                                        <span class="stat-number"><?php echo number_format($dev['total_downloads']); ?></span>
                                        <span class="stat-label">Downloads</span>
                                    </div>
                                </div>
                                
                                <div class="developer-bio">
                                    <p><?php echo htmlspecialchars(substr($dev['bio'], 0, 150)) . '...'; ?></p>
                                </div>
                                
                                <div class="developer-actions">
                                    <button onclick="window.location.href='../controllers/user/developer_profile.php?id=<?php echo $dev['id']; ?>'" class="btn btn-small">
                                        <i class="fas fa-user"></i> Profile
                                    </button>
                                    <button onclick="window.location.href='../marketplace/marketplace.php?developer=<?php echo $dev['id']; ?>'" class="btn btn-small btn-primary">
                                        <i class="fas fa-store"></i> Apps
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Recommended Developers -->
                <?php if (!empty($recommended_developers)): ?>
                <div class="section">
                    <div class="section-header">
                        <h2>
                            <i class="fas fa-star"></i>
                            Recommended Developers
                        </h2>
                        <span class="section-subtitle">Popular developers you might like</span>
                    </div>
                    
                    <div class="recommended-grid">
                        <?php foreach ($recommended_developers as $dev): ?>
                            <div class="developer-card recommended">
                                <div class="developer-header">
                                    <img src="<?php echo htmlspecialchars($dev['avatar_url'] ?: '../assets/images/default-avatar.png'); ?>" alt="<?php echo htmlspecialchars($dev['username']); ?>" class="developer-avatar">
                                    <div class="developer-info">
                                        <h4><?php echo htmlspecialchars($dev['username']); ?></h4>
                                        <p><?php echo htmlspecialchars($dev['company_name'] ?: 'Independent Developer'); ?></p>
                                        <?php if ($dev['followers'] > 100): ?>
                                            <span class="trending-badge">
                                                <i class="fas fa-fire"></i> Trending
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <button onclick="followDeveloper(<?php echo $dev['id']; ?>)" class="btn btn-small btn-primary">
                                        <i class="fas fa-user-plus"></i> Follow
                                    </button>
                                </div>
                                
                                <div class="developer-stats">
                                    <div class="stat">
                                        <span class="stat-number"><?php echo $dev['followers']; ?></span>
                                        <span class="stat-label">Followers</span>
                                    </div>
                                    <div class="stat">
                                        <span class="stat-number"><?php echo $dev['total_apps']; ?></span>
                                        <span class="stat-label">Apps</span>
                                    </div>
                                    <div class="stat">
                                        <span class="stat-number"><?php echo number_format($dev['total_downloads']); ?></span>
                                        <span class="stat-label">Downloads</span>
                                    </div>
                                </div>
                                
                                <div class="developer-bio">
                                    <p><?php echo htmlspecialchars(substr($dev['bio'], 0, 150)) . '...'; ?></p>
                                </div>
                                
                                <div class="developer-actions">
                                    <button onclick="window.location.href='../controllers/user/developer_profile.php?id=<?php echo $dev['id']; ?>'" class="btn btn-small">
                                        <i class="fas fa-user"></i> Profile
                                    </button>
                                    <button onclick="window.location.href='../marketplace/marketplace.php?developer=<?php echo $dev['id']; ?>'" class="btn btn-small btn-primary">
                                        <i class="fas fa-store"></i> Apps
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <style>
        .following-stats {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--accent-color);
            color: white;
            display: feed;
            align-items: center;
            justify-content: center;
            font-size: 1.2em;
        }
        
        .stat-content h3 {
            margin: 0;
            font-size: 1.5em;
            color: var(--text-primary);
        }
        
        .stat-content p {
            margin: 0;
            color: var(--text-secondary);
        }
        
        .feed-item {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .feed-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: var(--input-bg);
        }
        
        .developer-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .developer-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .default-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--glass-border);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
        }
        
        .developer-details h4 {
            margin: 0 0 5px 0;
            color: var(--text-primary);
        }
        
        .activity-time {
            color: var(--text-secondary);
            font-size: 0.9em;
        }
        
        .mark-read-btn {
            background: none;
            border: 1px solid var(--glass-border);
            color: var(--text-secondary);
            padding: 8px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .mark-read-btn:hover {
            background: var(--accent-color);
            color: white;
            border-color: var(--accent-color);
        }
        
        .feed-content {
            padding: 20px;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--accent-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            float: left;
            margin-right: 15px;
        }
        
        .activity-details {
            overflow: hidden;
        }
        
        .activity-details h3 {
            margin: 0 0 10px 0;
            color: var(--text-primary);
        }
        
        .activity-details p {
            margin: 0 0 15px 0;
            color: var(--text-secondary);
            line-height: 1.5;
        }
        
        .related-app {
            display: flex;
            gap: 15px;
            background: var(--input-bg);
            border-radius: 8px;
            padding: 15px;
        }
        
        .related-app img {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
        }
        
        .app-info h4 {
            margin: 0 0 5px 0;
            color: var(--text-primary);
        }
        
        .following-grid,
        .recommended-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .developer-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        
        .developer-card:hover {
            transform: translateY(-5px);
        }
        
        .developer-card.recommended {
            border-color: var(--accent-color);
        }
        
        .developer-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: var(--input-bg);
        }
        
        .trending-badge {
            background: var(--accent-color);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .developer-stats {
            display: flex;
            gap: 15px;
            padding: 20px;
            background: var(--input-bg);
        }
        
        .developer-stats .stat {
            text-align: center;
        }
        
        .developer-stats .stat-number {
            display: block;
            font-weight: bold;
            color: var(--text-primary);
        }
        
        .developer-stats .stat-label {
            color: var(--text-secondary);
            font-size: 0.9em;
        }
        
        .developer-bio {
            padding: 20px;
            color: var(--text-secondary);
            line-height: 1.5;
        }
        
        .developer-actions {
            display: flex;
            gap: 10px;
            padding: 20px;
            background: var(--input-bg);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 3em;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        #discover-modal {
            display: none;
        }
        
        .discover-modal-content {
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .discover-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }
    </style>
    
    <script>
        function markAsRead(activityId) {
            fetch('follow_feed.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=mark_read&activity_id=${activityId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const item = document.querySelector(`[data-activity-id="${activityId}"]`);
                    item.style.opacity = '0.5';
                    setTimeout(() => item.remove(), 300);
                }
            });
        }
        
        function markAllRead() {
            const items = document.querySelectorAll('.feed-item');
            const activityIds = Array.from(items).map(item => item.dataset.activityId);
            
            if (activityIds.length === 0) return;
            
            Promise.all(activityIds.map(id => 
                fetch('follow_feed.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=mark_read&activity_id=${id}`
                })
            )).then(() => {
                location.reload();
            });
        }
        
        function followDeveloper(developerId) {
            fetch('../controllers/user/developer_profile.php?id=' + developerId, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=toggle_follow'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }
        
        function unfollowDeveloper(developerId) {
            if (confirm('Are you sure you want to unfollow this developer?')) {
                followDeveloper(developerId);
            }
        }
        
        function showDiscoverDevelopers() {
            document.getElementById('discover-modal').style.display = 'flex';
        }
        
        function getActivityIcon(type) {
            const icons = {
                'app_published': 'fa-rocket',
                'app_updated': 'fa-sync',
                'roadmap_added': 'fa-map',
                'changelog_posted': 'fa-newspaper',
                'milestone_reached': 'fa-trophy',
                'subscriber_milestone' => 'fa-users'
            };
            return icons[type] ?? 'fa-info-circle';
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diff = now - date;
            
            if (diff < 60000) return 'Just now';
            if (diff < 3600000) return Math.floor(diff / 60000) + ' hours ago';
            if (diff < 86400000) return Math.floor(diff / 3600000) + ' days ago';
            if (diff < 604800000) return Math.floor(diff / 86400000) + ' weeks ago';
            
            return date.toLocaleDateString();
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>
