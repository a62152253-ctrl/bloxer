<?php
require_once __DIR__ . '/../../bootstrap.php';

$auth = new AuthCore();

if (!$auth->isLoggedIn()) {
    SecurityUtils::safeRedirect('../auth/login.php', 302, 'Unauthorized access attempt');
}

$user = $auth->getCurrentUser();
$developer_id = $_GET['id'] ?? $user['id'];

// Get developer profile
$conn = $auth->getConnection();
$stmt = $conn->prepare("
    SELECT dp.*, u.username, u.email, u.created_at as user_created_at,
           COUNT(DISTINCT df.follower_id) as followers_count,
           COUNT(DISTINCT a.id) as apps_count,
           COUNT(DISTINCT ua.id) as total_downloads
    FROM developer_profiles dp
    JOIN users u ON dp.user_id = u.id
    LEFT JOIN developer_follows df ON dp.user_id = df.developer_id
    LEFT JOIN apps a ON dp.user_id = (SELECT p.user_id FROM projects p WHERE p.id = a.project_id LIMIT 1)
    LEFT JOIN user_apps ua ON a.id = ua.app_id
    WHERE dp.user_id = ?
    GROUP BY dp.id
");
$stmt->bind_param("i", $developer_id);
$stmt->execute();
$developer = $stmt->get_result()->fetch_assoc();

if (!$developer) {
    // Create default profile if doesn't exist
    $stmt = $conn->prepare("
        INSERT INTO developer_profiles (user_id, bio, skills, experience_years)
        VALUES (?, 'Passionate developer creating amazing apps', '[]', 0)
    ");
    $stmt->bind_param("i", $developer_id);
    $stmt->execute();
    
    // Get the profile again
    $stmt->bind_param("i", $developer_id);
    $stmt->execute();
    $developer = $stmt->get_result()->fetch_assoc();
}

// Check if current user is following this developer
$is_following = false;
if ($user['id'] != $developer_id) {
    $stmt = $conn->prepare("SELECT id FROM developer_follows WHERE follower_id = ? AND developer_id = ?");
    $stmt->bind_param("ii", $user['id'], $developer_id);
    $stmt->execute();
    $is_following = $stmt->get_result()->num_rows > 0;
}

// Get developer's apps
$stmt = $conn->prepare("
    SELECT a.*, p.name as project_name
    FROM apps a
    JOIN projects p ON a.project_id = p.id
    WHERE p.user_id = ? AND a.status = 'published'
    ORDER BY a.published_at DESC
");
$stmt->bind_param("i", $developer_id);
$stmt->execute();
$apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get developer's roadmap
$stmt = $conn->prepare("
    SELECT * FROM developer_roadmaps 
    WHERE developer_id = ? 
    ORDER BY priority DESC, target_date ASC
");
$stmt->bind_param("i", $developer_id);
$stmt->execute();
$roadmap = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get developer's changelog
$stmt = $conn->prepare("
    SELECT dc.*, a.title as app_title
    FROM developer_changelog dc
    LEFT JOIN apps a ON dc.related_app_id = a.id
    WHERE dc.developer_id = ? AND dc.is_public = TRUE
    ORDER BY dc.created_at DESC
    LIMIT 10
");
$stmt->bind_param("i", $developer_id);
$stmt->execute();
$changelog = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get developer's activity feed
$stmt = $conn->prepare("
    SELECT daf.*, a.title as app_title
    FROM developer_activity_feed daf
    LEFT JOIN apps a ON daf.related_id = a.id AND daf.related_type = 'app'
    WHERE daf.developer_id = ? AND daf.is_public = TRUE
    ORDER BY daf.created_at DESC
    LIMIT 20
");
$stmt->bind_param("i", $developer_id);
$stmt->execute();
$activity_feed = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get recent followers
$stmt = $conn->prepare("
    SELECT u.username, u.avatar_url, df.created_at
    FROM developer_follows df
    JOIN users u ON df.follower_id = u.id
    WHERE df.developer_id = ?
    ORDER BY df.created_at DESC
    LIMIT 8
");
$stmt->bind_param("i", $developer_id);
$stmt->execute();
$recent_followers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle follow/unfollow
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'toggle_follow') {
    if ($user['id'] != $developer_id) {
        if ($is_following) {
            // Unfollow
            $stmt = $conn->prepare("DELETE FROM developer_follows WHERE follower_id = ? AND developer_id = ?");
            $stmt->bind_param("ii", $user['id'], $developer_id);
            $stmt->execute();
            
            // Add to activity feed
            $stmt = $conn->prepare("
                INSERT INTO developer_activity_feed (developer_id, activity_type, title, description, related_id, related_type)
                VALUES (?, 'milestone_reached', 'Lost a follower', ?, ?, NULL, NULL)
            ");
            $follow_message = "User {$user['username']} stopped following";
            $stmt->bind_param("isss", $developer_id, $follow_message, $user['id']);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'following' => false]);
        } else {
            // Follow
            $stmt = $conn->prepare("INSERT INTO developer_follows (follower_id, developer_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $user['id'], $developer_id);
            $stmt->execute();
            
            // Add to activity feed
            $stmt = $conn->prepare("
                INSERT INTO developer_activity_feed (developer_id, activity_type, title, description, related_id, related_type)
                VALUES (?, 'subscriber_milestone', 'New follower!', ?, ?, NULL, NULL)
            ");
            $follow_message = "User {$user['username']} started following";
            $stmt->bind_param("isss", $developer_id, $follow_message, $user['id']);
            $stmt->execute();
            
            // Add to user's follow feed
            $activity_id = $conn->insert_id;
            $stmt = $conn->prepare("
                INSERT INTO user_follow_feed (user_id, developer_id, activity_id)
                VALUES (?, ?, ?)
            ");
            $stmt->bind_param("iii", $user['id'], $developer_id, $activity_id);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'following' => true]);
        }
    }
    exit();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'update_profile') {
    if ($user['id'] == $developer_id) {
        $bio = trim($_POST['bio'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $github_url = trim($_POST['github_url'] ?? '');
        $twitter_url = trim($_POST['twitter_url'] ?? '');
        $linkedin_url = trim($_POST['linkedin_url'] ?? '');
        $discord_url = trim($_POST['discord_url'] ?? '');
        $youtube_url = trim($_POST['youtube_url'] ?? '');
        $company_name = trim($_POST['company_name'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $skills = json_decode($_POST['skills'] ?? '[]');
        $specialties = json_decode($_POST['specialties'] ?? '[]');
        $experience_years = intval($_POST['experience_years'] ?? 0);
        
        $stmt = $conn->prepare("
            UPDATE developer_profiles 
            SET bio = ?, website = ?, github_url = ?, twitter_url = ?, linkedin_url = ?, 
                discord_url = ?, youtube_url = ?, company_name = ?, location = ?, 
                skills = ?, specialties = ?, experience_years = ?, updated_at = NOW()
            WHERE user_id = ?
        ");
        $skills_json = json_encode($skills);
        $specialties_json = json_encode($specialties);
        $stmt->bind_param("ssssssssssssi", 
            $bio, $website, $github_url, $twitter_url, $linkedin_url,
            $discord_url, $youtube_url, $company_name, $location,
            $skills_json, $specialties_json, $experience_years, $developer_id
        );
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Profile updated successfully!";
        } else {
            $_SESSION['form_errors'] = ['Failed to update profile'];
        }
        
        header("Location: ../controllers/user/developer_profile.php?id=$developer_id");
        exit();
    }
}

// Handle roadmap item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add_roadmap') {
    if ($user['id'] == $developer_id) {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priority = $_POST['priority'] ?? 'medium';
        $target_date = $_POST['target_date'] ?? null;
        
        if ($title && $description) {
            $stmt = $conn->prepare("
                INSERT INTO developer_roadmaps (developer_id, title, description, priority, target_date)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("issss", $developer_id, $title, $description, $priority, $target_date);
            $stmt->execute();
            
            // Add to activity feed
            $roadmap_id = $conn->insert_id;
            $stmt = $conn->prepare("
                INSERT INTO developer_activity_feed (developer_id, activity_type, title, description, related_id, related_type)
                VALUES (?, 'roadmap_added', 'New roadmap item', ?, ?, ?, 'roadmap')
            ");
            $stmt->bind_param("isssi", $developer_id, $description, $roadmap_id);
            $stmt->execute();
            
            $_SESSION['success_message'] = "Roadmap item added successfully!";
        }
        
        header("Location: developer_profile.php?id=$developer_id#roadmap");
        exit();
    }
}

// Handle changelog post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add_changelog') {
    if ($user['id'] == $developer_id) {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $type = $_POST['type'] ?? 'announcement';
        $version = trim($_POST['version'] ?? '');
        $related_app_id = $_POST['related_app_id'] ?? null;
        
        if ($title && $content) {
            $stmt = $conn->prepare("
                INSERT INTO developer_changelog (developer_id, title, content, type, version, related_app_id)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("issssi", $developer_id, $title, $content, $type, $version, $related_app_id);
            $stmt->execute();
            
            // Add to activity feed
            $changelog_id = $conn->insert_id;
            $stmt = $conn->prepare("
                INSERT INTO developer_activity_feed (developer_id, activity_type, title, description, related_id, related_type)
                VALUES (?, 'changelog_posted', 'New changelog post', ?, ?, ?, 'changelog')
            ");
            $stmt->bind_param("isssi", $developer_id, $title, $changelog_id);
            $stmt->execute();
            
            $_SESSION['success_message'] = "Changelog post added successfully!";
        }
        
        header("Location: developer_profile.php?id=$developer_id#changelog");
        exit();
    }
}

// Helper function to format date
function formatDate($date) {
    if (!$date) return 'No date set';
    $timestamp = strtotime($date);
    $now = time();
    $diff = $now - $timestamp;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' hours ago';
    if ($diff < 86400) return floor($diff / 86400) . ' days ago';
    if ($diff < 2592000) return floor($diff / 86400) . ' days ago';
    
    return date('M j, Y', $timestamp);
}

// Helper function to get activity icon
function getActivityIcon($type) {
    $icons = [
        'app_published' => 'fa-rocket',
        'app_updated' => 'fa-sync',
        'roadmap_added' => 'fa-map',
        'changelog_posted' => 'fa-newspaper',
        'milestone_reached' => 'fa-trophy',
        'subscriber_milestone' => 'fa-users'
    ];
    return $icons[$type] ?? 'fa-info-circle';
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($developer['username']); ?> - Developer Profile</title>
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
                <p>Developer Profile</p>
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
                <div class="nav-item" onclick="window.location.href='../controllers/user/personalized_feed.php'">
                    <i class="fas fa-home"></i>
                    <span>For You</span>
                </div>
                <?php if ($user['id'] == $developer_id): ?>
                    <div class="nav-item active" onclick="window.location.href='../controllers/user/developer_profile.php'">
                        <i class="fas fa-user"></i>
                        <span>My Profile</span>
                    </div>
                <?php endif; ?>
            </nav>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-banner">
                    <?php if ($developer['banner_url']): ?>
                        <img src="<?php echo htmlspecialchars($developer['banner_url']); ?>" alt="Banner">
                    <?php else: ?>
                        <div class="default-banner"></div>
                    <?php endif; ?>
                </div>
                
                <div class="profile-info">
                    <div class="profile-avatar">
                        <?php if ($developer['avatar_url']): ?>
                            <img src="<?php echo htmlspecialchars($developer['avatar_url']); ?>" alt="Avatar">
                        <?php else: ?>
                            <div class="default-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                        <?php endif; ?>
                        <?php if ($developer['is_verified']): ?>
                            <div class="verified-badge">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="profile-details">
                        <h1><?php echo htmlspecialchars($developer['username']); ?></h1>
                        <p class="profile-title"><?php echo htmlspecialchars($developer['company_name'] ?: 'Independent Developer'); ?></p>
                        <p class="profile-bio"><?php echo htmlspecialchars($developer['bio']); ?></p>
                        
                        <div class="profile-stats">
                            <div class="stat">
                                <span class="stat-number"><?php echo $developer['followers_count']; ?></span>
                                <span class="stat-label">Followers</span>
                            </div>
                            <div class="stat">
                                <span class="stat-number"><?php echo $developer['apps_count']; ?></span>
                                <span class="stat-label">Apps</span>
                            </div>
                            <div class="stat">
                                <span class="stat-number"><?php echo number_format($developer['total_downloads']); ?></span>
                                <span class="stat-label">Downloads</span>
                            </div>
                            <div class="stat">
                                <span class="stat-number"><?php echo $developer['experience_years']; ?></span>
                                <span class="stat-label">Years Exp</span>
                            </div>
                        </div>
                        
                        <div class="profile-actions">
                            <?php if ($user['id'] != $developer_id): ?>
                                <button id="follow-btn" onclick="toggleFollow()" class="btn <?php echo $is_following ? 'btn-secondary' : 'btn-primary'; ?>">
                                    <i class="fas fa-<?php echo $is_following ? 'user-minus' : 'user-plus'; ?>"></i>
                                    <?php echo $is_following ? 'Following' : 'Follow'; ?>
                                </button>
                            <?php else: ?>
                                <button onclick="showEditProfile()" class="btn btn-primary">
                                    <i class="fas fa-edit"></i> Edit Profile
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
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
                
                <!-- Activity Feed -->
                <div class="section">
                    <div class="section-header">
                        <h2>
                            <i class="fas fa-stream"></i>
                            Activity Feed
                        </h2>
                        <span class="section-subtitle">Latest updates and announcements</span>
                    </div>
                    
                    <div class="activity-feed">
                        <?php if (!empty($activity_feed)): ?>
                            <?php foreach ($activity_feed as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas <?php echo getActivityIcon($activity['activity_type']); ?>"></i>
                                    </div>
                                    <div class="activity-content">
                                        <h3><?php echo htmlspecialchars($activity['title']); ?></h3>
                                        <p><?php echo htmlspecialchars($activity['description']); ?></p>
                                        <?php if ($activity['app_title']): ?>
                                            <span class="related-app"><?php echo htmlspecialchars($activity['app_title']); ?></span>
                                        <?php endif; ?>
                                        <span class="activity-time"><?php echo formatDate($activity['created_at']); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-stream"></i>
                                <h3>No recent activity</h3>
                                <p>This developer hasn't posted any updates yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Apps -->
                <div class="section" id="apps">
                    <div class="section-header">
                        <h2>
                            <i class="fas fa-mobile-alt"></i>
                            Published Apps
                        </h2>
                        <span class="section-subtitle"><?php echo count($apps); ?> apps published</span>
                    </div>
                    
                    <div class="apps-grid">
                        <?php if (!empty($apps)): ?>
                            <?php foreach ($apps as $app): ?>
                                <div class="app-card">
                                    <div class="app-thumbnail">
                                        <img src="<?php echo htmlspecialchars($app['thumbnail_url'] ?: '../assets/images/default-app.png'); ?>" alt="<?php echo htmlspecialchars($app['title']); ?>">
                                    </div>
                                    <div class="app-info">
                                        <h3><?php echo htmlspecialchars($app['title']); ?></h3>
                                        <p class="app-category"><?php echo htmlspecialchars(ucfirst($app['category'])); ?></p>
                                        <div class="app-stats">
                                            <span class="rating">
                                                <i class="fas fa-star"></i>
                                                <?php echo number_format($app['rating'] ?? 0, 1); ?>
                                            </span>
                                            <span class="downloads">
                                                <i class="fas fa-download"></i>
                                                <?php echo number_format($app['total_downloads'] ?? 0); ?>
                                            </span>
                                        </div>
                                        <button onclick="window.location.href='app.php?id=<?php echo $app['id']; ?>'" class="btn btn-small btn-primary">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-mobile-alt"></i>
                                <h3>No apps published yet</h3>
                                <p>This developer hasn't published any applications.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Roadmap -->
                <div class="section" id="roadmap">
                    <div class="section-header">
                        <h2>
                            <i class="fas fa-map"></i>
                            Roadmap
                        </h2>
                        <span class="section-subtitle">What's coming next</span>
                        <?php if ($user['id'] == $developer_id): ?>
                            <button onclick="showAddRoadmap()" class="btn btn-small btn-primary">
                                <i class="fas fa-plus"></i> Add Item
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <div class="roadmap-timeline">
                        <?php if (!empty($roadmap)): ?>
                            <?php foreach ($roadmap as $item): ?>
                                <div class="roadmap-item <?php echo $item['status']; ?>">
                                    <div class="roadmap-marker">
                                        <?php if ($item['status'] === 'completed'): ?>
                                            <i class="fas fa-check"></i>
                                        <?php else: ?>
                                            <i class="fas fa-circle"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="roadmap-content">
                                        <h3><?php echo htmlspecialchars($item['title']); ?></h3>
                                        <p><?php echo htmlspecialchars($item['description']); ?></p>
                                        <div class="roadmap-meta">
                                            <span class="priority <?php echo $item['priority']; ?>">
                                                <?php echo ucfirst($item['priority']); ?>
                                            </span>
                                            <?php if ($item['target_date']): ?>
                                                <span class="target-date">
                                                    <i class="fas fa-calendar"></i>
                                                    <?php echo date('M j, Y', strtotime($item['target_date'])); ?>
                                                </span>
                                            <?php endif; ?>
                                            <span class="status <?php echo $item['status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $item['status'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-map"></i>
                                <h3>No roadmap items</h3>
                                <p>This developer hasn't shared their roadmap yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Changelog -->
                <div class="section" id="changelog">
                    <div class="section-header">
                        <h2>
                            <i class="fas fa-newspaper"></i>
                            Changelog
                        </h2>
                        <span class="section-subtitle">Updates and announcements</span>
                        <?php if ($user['id'] == $developer_id): ?>
                            <button onclick="showAddChangelog()" class="btn btn-small btn-primary">
                                <i class="fas fa-plus"></i> Add Post
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <div class="changelog-feed">
                        <?php if (!empty($changelog)): ?>
                            <?php foreach ($changelog as $post): ?>
                                <div class="changelog-item">
                                    <div class="changelog-header">
                                        <h3><?php echo htmlspecialchars($post['title']); ?></h3>
                                        <div class="changelog-meta">
                                            <span class="type <?php echo $post['type']; ?>">
                                                <?php echo ucfirst($post['type']); ?>
                                            </span>
                                            <?php if ($post['version']): ?>
                                                <span class="version">v<?php echo htmlspecialchars($post['version']); ?></span>
                                            <?php endif; ?>
                                            <?php if ($post['app_title']): ?>
                                                <span class="related-app"><?php echo htmlspecialchars($post['app_title']); ?></span>
                                            <?php endif; ?>
                                            <span class="date"><?php echo formatDate($post['created_at']); ?></span>
                                        </div>
                                    </div>
                                    <div class="changelog-content">
                                        <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-newspaper"></i>
                                <h3>No changelog posts</h3>
                                <p>This developer hasn't posted any updates yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Followers -->
                <?php if (!empty($recent_followers)): ?>
                <div class="section">
                    <div class="section-header">
                        <h2>
                            <i class="fas fa-users"></i>
                            Recent Followers
                        </h2>
                        <span class="section-subtitle">Latest people who followed</span>
                    </div>
                    
                    <div class="followers-grid">
                        <?php foreach ($recent_followers as $follower): ?>
                            <div class="follower-card">
                                <div class="follower-avatar">
                                    <?php if ($follower['avatar_url']): ?>
                                        <img src="<?php echo htmlspecialchars($follower['avatar_url']); ?>" alt="<?php echo htmlspecialchars($follower['username']); ?>">
                                    <?php else: ?>
                                        <div class="default-avatar">
                                            <i class="fas fa-user"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="follower-info">
                                    <h4><?php echo htmlspecialchars($follower['username']); ?></h4>
                                    <span class="follow-date"><?php echo formatDate($follower['created_at']); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modals -->
    <!-- Edit Profile Modal -->
    <div id="edit-profile-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Profile</h2>
                <button onclick="closeModal('edit-profile-modal')" class="btn-close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" action="../controllers/user/developer_profile.php?id=<?php echo $developer_id; ?>">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="form-group">
                    <label for="bio">Bio</label>
                    <textarea id="bio" name="bio" rows="4"><?php echo htmlspecialchars($developer['bio']); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="website">Website</label>
                    <input type="url" id="website" name="website" value="<?php echo htmlspecialchars($developer['website']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="github_url">GitHub</label>
                    <input type="url" id="github_url" name="github_url" value="<?php echo htmlspecialchars($developer['github_url']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="twitter_url">Twitter</label>
                    <input type="url" id="twitter_url" name="twitter_url" value="<?php echo htmlspecialchars($developer['twitter_url']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="company_name">Company</label>
                    <input type="text" id="company_name" name="company_name" value="<?php echo htmlspecialchars($developer['company_name']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="location">Location</label>
                    <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($developer['location']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="experience_years">Experience (years)</label>
                    <input type="number" id="experience_years" name="experience_years" value="<?php echo $developer['experience_years']; ?>" min="0">
                </div>
                
                <div class="modal-actions">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <button type="button" onclick="closeModal('edit-profile-modal')" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Add Road Modal -->
    <div id="add-roadmap-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add Roadmap Item</h2>
                <button onclick="closeModal('add-roadmap-modal')" class="btn-close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" action="../controllers/user/developer_profile.php?id=<?php echo $developer_id; ?>">
                <input type="hidden" name="action" value="add_roadmap">
                
                <div class="form-group">
                    <label for="roadmap-title">Title</label>
                    <input type="text" id="roadmap-title" name="title" required>
                </div>
                
                <div class="form-group">
                    <label for="roadmap-description">Description</label>
                    <textarea id="roadmap-description" name="description" rows="4" required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="roadmap-priority">Priority</label>
                    <select id="roadmap-priority" name="priority">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="roadmap-target-date">Target Date</label>
                    <input type="date" id="roadmap-target-date" name="target_date">
                </div>
                
                <div class="modal-actions">
                    <button type="submit" class="btn btn-primary">Add Item</button>
                    <button type="button" onclick="closeModal('add-roadmap-modal')" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Add Changelog Modal -->
    <div id="add-changelog-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add Changelog Post</h2>
                <button onclick="closeModal('add-changelog-modal')" class="btn-close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" action="../controllers/user/developer_profile.php?id=<?php echo $developer_id; ?>">
                <input type="hidden" name="action" value="add_changelog">
                
                <div class="form-group">
                    <label for="changelog-title">Title</label>
                    <input type="text" id="changelog-title" name="title" required>
                </div>
                
                <div class="form-group">
                    <label for="changelog-content">Content</label>
                    <textarea id="changelog-content" name="content" rows="6" required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="changelog-type">Type</label>
                    <select id="changelog-type" name="type">
                        <option value="announcement">Announcement</option>
                        <option value="update">Update</option>
                        <option value="milestone">Milestone</option>
                        <option value="feature">Feature</option>
                        <option value="fix">Fix</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="changelog-version">Version (optional)</label>
                    <input type="text" id="changelog-version" name="version" placeholder="1.0.0">
                </div>
                
                <div class="form-group">
                    <label for="changelog-app">Related App (optional)</label>
                    <select id="changelog-app" name="related_app_id">
                        <option value="">Select app...</option>
                        <?php foreach ($apps as $app): ?>
                            <option value="<?php echo $app['id']; ?>"><?php echo htmlspecialchars($app['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="modal-actions">
                    <button type="submit" class="btn btn-primary">Add Post</button>
                    <button type="button" onclick="closeModal('add-changelog-modal')" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <style>
        .profile-header {
            position: relative;
            margin-bottom: 30px;
        }
        
        .profile-banner {
            height: 200px;
            border-radius: 16px;
            overflow: hidden;
            position: relative;
        }
        
        .profile-banner img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .default-banner {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--accent-color), #3b82f6);
        }
        
        .profile-info {
            display: flex;
            gap: 30px;
            padding: 0 30px;
            margin-top: -60px;
            position: relative;
        }
        
        .profile-avatar {
            position: relative;
            width: 120px;
            height: 120px;
            border-radius: 50%;
            overflow: hidden;
            border: 4px solid var(--glass-bg);
            background: var(--input-bg);
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .default-avatar {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3em;
            color: var(--text-secondary);
        }
        
        .verified-badge {
            position: absolute;
            bottom: 5px;
            right: 5px;
            width: 30px;
            height: 30px;
            background: #10b981;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8em;
        }
        
        .profile-details {
            flex: 1;
        }
        
        .profile-details h1 {
            margin: 0 0 5px 0;
            color: var(--text-primary);
            font-size: 2em;
        }
        
        .profile-title {
            margin: 0 0 10px 0;
            color: var(--accent-color);
            font-weight: 500;
        }
        
        .profile-bio {
            margin: 0 0 20px 0;
            color: var(--text-secondary);
            line-height: 1.6;
        }
        
        .profile-stats {
            display: flex;
            gap: 30px;
            margin-bottom: 20px;
        }
        
        .stat {
            text-align: center;
        }
        
        .stat-number {
            display: block;
            font-size: 1.5em;
            font-weight: bold;
            color: var(--text-primary);
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: 0.9em;
        }
        
        .profile-actions {
            margin-top: 20px;
        }
        
        .section {
            margin-bottom: 40px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .section-header h2 {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0 0 5px 0;
            color: var(--text-primary);
        }
        
        .section-subtitle {
            color: var(--text-secondary);
            font-size: 0.9em;
        }
        
        .activity-feed {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .activity-item {
            display: flex;
            gap: 15px;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
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
            flex-shrink: 0;
        }
        
        .activity-content h3 {
            margin: 0 0 5px 0;
            color: var(--text-primary);
        }
        
        .activity-content p {
            margin: 0 0 10px 0;
            color: var(--text-secondary);
        }
        
        .related-app {
            background: var(--input-bg);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            color: var(--accent-color);
        }
        
        .activity-time {
            color: var(--text-secondary);
            font-size: 0.9em;
        }
        
        .apps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .app-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            overflow: hidden;
        }
        
        .app-thumbnail {
            height: 150px;
            position: relative;
        }
        
        .app-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .app-info {
            padding: 20px;
        }
        
        .app-info h3 {
            margin: 0 0 10px 0;
            color: var(--text-primary);
        }
        
        .app-category {
            color: var(--text-secondary);
            font-size: 0.9em;
            margin-bottom: 10px;
        }
        
        .app-stats {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .rating, .downloads {
            display: flex;
            align-items: center;
            gap: 5px;
            color: var(--text-secondary);
            font-size: 0.9em;
        }
        
        .roadmap-timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .roadmap-timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--glass-border);
        }
        
        .roadmap-item {
            position: relative;
            margin-bottom: 30px;
        }
        
        .roadmap-marker {
            position: absolute;
            left: -25px;
            top: 5px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--glass-bg);
            border: 2px solid var(--glass-border);
            color: var(--text-secondary);
            font-size: 0.8em;
        }
        
        .roadmap-item.completed .roadmap-marker {
            background: var(--accent-color);
            color: white;
            border-color: var(--accent-color);
        }
        
        .roadmap-content {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 20px;
        }
        
        .roadmap-content h3 {
            margin: 0 0 10px 0;
            color: var(--text-primary);
        }
        
        .roadmap-content p {
            margin: 0 0 15px 0;
            color: var(--text-secondary);
        }
        
        .roadmap-meta {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .priority, .target-date, .status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 500;
        }
        
        .priority.low { background: rgba(107, 114, 128, 0.1); color: #6b7280; }
        .priority.medium { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .priority.high { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        .priority.critical { background: rgba(220, 38, 38, 0.1); color: #dc2626; }
        
        .target-date {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }
        
        .status.planned { background: rgba(107, 114, 128, 0.1); color: #6b7280; }
        .status.in_progress { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .status.completed { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .status.cancelled { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        
        .changelog-feed {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .changelog-item {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 20px;
        }
        
        .changelog-header {
            margin-bottom: 15px;
        }
        
        .changelog-header h3 {
            margin: 0 0 10px 0;
            color: var(--text-primary);
        }
        
        .changelog-meta {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .type, .version, .related-app, .date {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 500;
        }
        
        .type.announcement { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .type.update { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .type.milestone { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .type.feature { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }
        .type.fix { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        .type.other { background: rgba(107, 114, 128, 0.1); color: #6b7280; }
        
        .version {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }
        
        .related-app {
            background: var(--input-bg);
            color: var(--accent-color);
        }
        
        .date {
            background: var(--input-bg);
            color: var(--text-secondary);
        }
        
        .changelog-content {
            color: var(--text-secondary);
            line-height: 1.6;
        }
        
        .followers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .follower-card {
            display: flex;
            align-items: center;
            gap: 15px;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 15px;
        }
        
        .follower-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            overflow: hidden;
        }
        
        .follower-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .follower-info h4 {
            margin: 0 0 5px 0;
            color: var(--text-primary);
        }
        
        .follow-date {
            color: var(--text-secondary);
            font-size: 0.9em;
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
        
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
        }
        
        .modal-content {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 30px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .modal-header h2 {
            margin: 0;
            color: var(--text-primary);
        }
        
        .btn-close {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 1.2em;
            cursor: pointer;
            padding: 8px;
            border-radius: 6px;
        }
        
        .btn-close:hover {
            background: var(--input-bg);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--glass-border);
            border-radius: 8px;
            background: var(--input-bg);
            color: var(--text-primary);
        }
        
        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid var(--glass-border);
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
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const btn = document.getElementById('follow-btn');
                    if (data.following) {
                        btn.className = 'btn btn-secondary';
                        btn.innerHTML = '<i class="fas fa-user-minus"></i> Following';
                    } else {
                        btn.className = 'btn btn-primary';
                        btn.innerHTML = '<i class="fas fa-user-plus"></i> Follow';
                    }
                    location.reload();
                }
            });
        }
        
        function showEditProfile() {
            document.getElementById('edit-profile-modal').style.display = 'flex';
        }
        
        function showAddRoadmap() {
            document.getElementById('add-roadmap-modal').style.display = 'flex';
        }
        
        function showAddChangelog() {
            document.getElementById('add-changelog-modal').style.display = 'flex';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
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
