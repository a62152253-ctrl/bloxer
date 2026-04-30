<?php
require_once __DIR__ . '/../../bootstrap.php';

$auth = new AuthCore();

if (!$auth->isLoggedIn()) {
    SecurityUtils::safeExit('Unauthorized access', 401, 'warning');
}

$user = $auth->getCurrentUser();
$app_id = $_GET['id'] ?? null;

if (!$app_id) {
    SecurityUtils::safeExit('App ID required', 400, 'warning');
}

$conn = $auth->getConnection();

// Get app details and check if user has it installed
$stmt = $conn->prepare("
    SELECT a.*, ua.installed_at, ua.installed_version 
    FROM apps a 
    LEFT JOIN user_apps ua ON a.id = ua.app_id AND ua.user_id = ? 
    WHERE a.id = ?
");
$stmt->bind_param("ii", $user['id'], $app_id);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();

if (!$app) {
    SecurityUtils::safeExit('App not found', 404, 'warning');
}

// Check if user has the app installed
$is_installed = $app['installed_at'] !== null;

if (!$is_installed) {
    SecurityUtils::safeExit('You need to install this app first', 403, 'warning');
}

// Get available versions
$stmt = $conn->prepare("SELECT * FROM app_versions WHERE app_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $app_id);
$stmt->execute();
$versions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get current version
$current_version = null;
foreach ($versions as $version) {
    if ($version['is_current']) {
        $current_version = $version;
        break;
    }
}

// Get user's installed version
$installed_version = null;
if ($app['installed_version']) {
    foreach ($versions as $version) {
        if ($version['version'] === $app['installed_version']) {
            $installed_version = $version;
            break;
        }
    }
}

// Handle update request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'update_app') {
    $version_id = $_POST['version_id'] ?? null;
    
    if ($version_id) {
        // Verify version belongs to this app
        $stmt = $conn->prepare("SELECT * FROM app_versions WHERE id = ? AND app_id = ?");
        $stmt->bind_param("ii", $version_id, $app_id);
        $stmt->execute();
        $target_version = $stmt->get_result()->fetch_assoc();
        
        if ($target_version) {
            // Update user's installed version
            $stmt = $conn->prepare("
                UPDATE user_apps 
                SET installed_version = ?, updated_at = NOW() 
                WHERE user_id = ? AND app_id = ?
            ");
            $stmt->bind_param("sii", $target_version['version'], $user['id'], $app_id);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "App updated to version {$target_version['version']} successfully!";
                header("Location: app_updates.php?id=$app_id");
                exit();
            } else {
                $_SESSION['form_errors'] = ['Failed to update app: ' . $stmt->error];
            }
        } else {
            $_SESSION['form_errors'] = ['Version not found'];
        }
    } else {
        $_SESSION['form_errors'] = ['Version ID required'];
    }
}

// Handle install request (for first-time installation)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'install_app') {
    $version_id = $_POST['version_id'] ?? null;
    
    if ($version_id) {
        // Verify version belongs to this app
        $stmt = $conn->prepare("SELECT * FROM app_versions WHERE id = ? AND app_id = ?");
        $stmt->bind_param("ii", $version_id, $app_id);
        $stmt->execute();
        $target_version = $stmt->get_result()->fetch_assoc();
        
        if ($target_version) {
            // Check if app is already installed
            $check_stmt = $conn->prepare("SELECT id FROM user_apps WHERE user_id = ? AND app_id = ?");
            $check_stmt->bind_param("ii", $user['id'], $app_id);
            $check_stmt->execute();
            $existing = $check_stmt->get_result()->fetch_assoc();
            
            if ($existing) {
                $_SESSION['form_errors'] = ['App is already installed. Use the update option instead.'];
            } else {
                // Install app for user
                $stmt = $conn->prepare("
                    INSERT INTO user_apps (user_id, app_id, installed_version) 
                    VALUES (?, ?, ?)
                ");
                $stmt->bind_param("iis", $user['id'], $app_id, $target_version['version']);
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "App installed successfully!";
                    header("Location: app_updates.php?id=$app_id");
                    exit();
                } else {
                    $_SESSION['form_errors'] = ['Failed to install app: ' . $stmt->error];
                }
            }
        } else {
            $_SESSION['form_errors'] = ['Version not found'];
        }
    } else {
        $_SESSION['form_errors'] = ['Version ID required'];
    }
}

// Check for updates available
$updates_available = false;
if ($current_version && $installed_version) {
    $updates_available = version_compare($current_version['version'], $installed_version['version'], '>');
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>App Updates - <?php echo htmlspecialchars($app['title']); ?></title>
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
                <p>App Updates</p>
            </div>
            
            <nav>
                <div class="nav-item" onclick="window.location.href='marketplace.php'">
                    <i class="fas fa-store"></i>
                    <span>Marketplace</span>
                </div>
                <div class="nav-item" onclick="window.location.href='dashboard.php'">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </div>
                <div class="nav-item active" onclick="window.location.href='app_updates.php?id=<?php echo $app_id; ?>'">
                    <i class="fas fa-sync"></i>
                    <span>Updates</span>
                </div>
            </nav>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <header class="main-header">
                <div class="app-info">
                    <h1><?php echo htmlspecialchars($app['title']); ?></h1>
                    <div class="version-info">
                        <?php if ($installed_version): ?>
                            <span class="installed-version">Installed: v<?php echo htmlspecialchars($installed_version['version']); ?></span>
                            <?php if ($current_version && version_compare($current_version['version'], $installed_version['version'], '>')): ?>
                                <span class="update-available">Update available: v<?php echo htmlspecialchars($current_version['version']); ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="not-installed">Not installed</span>
                        <?php endif; ?>
                    </div>
                </div>
                <button onclick="window.location.href='app.php?id=<?php echo $app_id; ?>'" class="btn">
                    <i class="fas fa-arrow-left"></i> Back to App
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
                
                <!-- Current Version Info -->
                <?php if ($current_version): ?>
                    <div class="card current-version">
                        <h2>
                            <i class="fas fa-star"></i> Current Version
                            <span class="version-number">v<?php echo htmlspecialchars($current_version['version']); ?></span>
                        </h2>
                        <div class="version-meta">
                            <span class="release-date">Released: <?php echo date('M j, Y', strtotime($current_version['created_at'])); ?></span>
                            <?php if ($current_version['file_size'] > 0): ?>
                                <span class="file-size">Size: <?php echo round($current_version['file_size'] / 1024 / 1024, 2); ?> MB</span>
                            <?php endif; ?>
                        </div>
                        <div class="changelog">
                            <h3>What's New</h3>
                            <div class="changelog-content">
                                <?php echo nl2br(htmlspecialchars($current_version['changelog'])); ?>
                            </div>
                        </div>
                        
                        <?php if ($installed_version && version_compare($current_version['version'], $installed_version['version'], '>')): ?>
                            <form method="POST" action="" class="update-form">
                                <input type="hidden" name="action" value="update_app">
                                <input type="hidden" name="version_id" value="<?php echo $current_version['id']; ?>">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-download"></i> Update to v<?php echo htmlspecialchars($current_version['version']); ?>
                                </button>
                            </form>
                        <?php elseif (!$installed_version): ?>
                            <form method="POST" action="" class="install-form">
                                <input type="hidden" name="action" value="install_app">
                                <input type="hidden" name="version_id" value="<?php echo $current_version['id']; ?>">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-download"></i> Install v<?php echo htmlspecialchars($current_version['version']); ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="up-to-date">
                                <i class="fas fa-check-circle"></i> You have the latest version
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Version History -->
                <div class="card version-history">
                    <h2>
                        <i class="fas fa-history"></i> Version History
                    </h2>
                    
                    <?php if (!empty($versions)): ?>
                        <div class="versions-timeline">
                            <?php foreach ($versions as $index => $version): ?>
                                <div class="version-item <?php echo $version['is_current'] ? 'current' : ''; ?> <?php echo ($installed_version && $version['version'] === $installed_version['version']) ? 'installed' : ''; ?>">
                                    <div class="version-marker">
                                        <?php if ($version['is_current']): ?>
                                            <i class="fas fa-star"></i>
                                        <?php else: ?>
                                            <i class="fas fa-circle"></i>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="version-content">
                                        <div class="version-header">
                                            <span class="version-number">v<?php echo htmlspecialchars($version['version']); ?></span>
                                            <?php if ($version['is_current']): ?>
                                                <span class="current-badge">Latest</span>
                                            <?php endif; ?>
                                            <?php if ($installed_version && $version['version'] === $installed_version['version']): ?>
                                                <span class="installed-badge">Installed</span>
                                            <?php endif; ?>
                                            <span class="release-date"><?php echo date('M j, Y', strtotime($version['created_at'])); ?></span>
                                        </div>
                                        
                                        <div class="changelog">
                                            <?php echo nl2br(htmlspecialchars($version['changelog'])); ?>
                                        </div>
                                        
                                        <?php if (!$version['is_current'] && $installed_version && version_compare($version['version'], $installed_version['version'], '>')): ?>
                                            <form method="POST" action="" class="version-action">
                                                <input type="hidden" name="action" value="update_app">
                                                <input type="hidden" name="version_id" value="<?php echo $version['id']; ?>">
                                                <button type="submit" class="btn btn-small">
                                                    <i class="fas fa-download"></i> Update
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="no-versions">No versions available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <style>
        .app-info {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .version-info {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .installed-version {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.9em;
        }
        
        .update-available {
            background: rgba(251, 146, 60, 0.1);
            color: #fb923c;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.9em;
        }
        
        .not-installed {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.9em;
        }
        
        .current-version {
            border: 2px solid var(--accent-color);
            background: rgba(16, 185, 129, 0.05);
        }
        
        .current-version h2 {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--accent-color);
        }
        
        .version-number {
            background: var(--accent-color);
            color: white;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.8em;
        }
        
        .version-meta {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
            color: var(--text-secondary);
            font-size: 0.9em;
        }
        
        .changelog h3 {
            margin-bottom: 10px;
            color: var(--text-primary);
        }
        
        .changelog-content {
            background: var(--input-bg);
            padding: 15px;
            border-radius: 8px;
            line-height: 1.6;
            color: var(--text-secondary);
        }
        
        .update-form, .install-form {
            margin-top: 20px;
        }
        
        .up-to-date {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
            color: var(--accent-color);
        .versions-timeline::-webkit-scrollbar-track {
            background: var(--glass-border);
            border-radius: 4px;
        }
        
        .versions-timeline::-webkit-scrollbar-thumb {
            background: var(--accent-color);
            border-radius: 4px;
        }
        
        .versions-timeline::-webkit-scrollbar-thumb:hover {
            background: var(--accent-color);
            opacity: 0.8;
        }
        
        .versions-timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--glass-border);
        }
        
        .version-item {
            position: relative;
            margin-bottom: 30px;
        }
        
        .version-marker {
            position: absolute;
            left: -25px;
            top: 5px;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--glass-bg);
            border-radius: 50%;
            color: var(--text-secondary);
        }
        
        .version-item.current .version-marker {
            color: var(--accent-color);
        }
        
        .version-content {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 10px;
            padding: 20px;
        }
        
        .version-item.current .version-content {
            border-color: var(--accent-color);
            background: rgba(16, 185, 129, 0.05);
        }
        
        .version-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .current-badge {
            background: var(--accent-color);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8em;
        }
        
        .installed-badge {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8em;
        }
        
        .release-date {
            color: var(--text-secondary);
            font-size: 0.9em;
            margin-left: auto;
        }
        
        .version-action {
            margin-top: 15px;
        }
        
        .no-versions {
            color: var(--text-secondary);
            font-style: italic;
            text-align: center;
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
        
        .card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
            backdrop-filter: blur(20px);
        }
        
        .card h2 {
            margin-bottom: 20px;
            color: var(--text-primary);
        }
        
        .version-history {
            min-height: 500px;
        }
        
        .version-history h2::after {
            content: " (scroll down for more)";
            font-size: 0.8em;
            color: var(--text-secondary);
            font-weight: normal;
            margin-left: 10px;
        }
    </style>
</body>
</html>
