<?php
/**
 * App Installation Handler - Bloxer Platform
 * Handles application installation, integration, and setup
 */

require_once 'bootstrap.php';

// Authentication check
$auth = new AuthCore();
if (!$auth->isLoggedIn()) {
    SecurityUtils::safeRedirect('controllers/auth/login.php', 403, 'Login required for app installation');
}

$user = $auth->getCurrentUser();
$app_id = $_POST['app_id'] ?? $_GET['app_id'] ?? null;
$action = $_POST['action'] ?? 'install';

// Validate app ID
if (!$app_id || !is_numeric($app_id)) {
    SecurityUtils::safeRedirect('controllers/marketplace/marketplace.php', 400, 'Invalid app ID');
}

// Get database connection
$conn = $auth->getConnection();

// Get app details
$stmt = $conn->prepare("
    SELECT a.*, p.user_id as developer_id, p.description as project_description,
           u.username as developer_name, u.email as developer_email
    FROM apps a
    JOIN projects p ON a.project_id = p.id
    JOIN users u ON p.user_id = u.id
    WHERE a.id = ? AND a.status = 'published'
");
$stmt->bind_param("i", $app_id);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();

if (!$app) {
    SecurityUtils::safeRedirect('controllers/marketplace/marketplace.php', 404, 'App not found');
}

// Handle different installation actions
switch ($action) {
    case 'install':
        handleAppInstallation($conn, $user, $app);
        break;
        
    case 'uninstall':
        handleAppUninstallation($conn, $user, $app);
        break;
        
    case 'update':
        handleAppUpdate($conn, $user, $app);
        break;
        
    case 'configure':
        handleAppConfiguration($conn, $user, $app, $auth);
        break;
        
    default:
        SecurityUtils::safeRedirect('controllers/marketplace/marketplace.php', 400, 'Invalid action');
}

/**
 * Handle app installation
 */
function handleAppInstallation($conn, $user, $app) {
    // Check if already installed
    $stmt = $conn->prepare("SELECT id FROM user_apps WHERE user_id = ? AND app_id = ?");
    $stmt->bind_param("ii", $user['id'], $app['id']);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        // App already installed, redirect to app runner
        SecurityUtils::safeRedirect("controllers/core/run_app.php?id={$app['id']}", 302, 'App already installed');
    }
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Install app
        $stmt = $conn->prepare("INSERT INTO user_apps (user_id, app_id, installed_at, is_favorite) VALUES (?, ?, NOW(), FALSE)");
        $stmt->bind_param("ii", $user['id'], $app['id']);
        $stmt->execute();
        
        // Increment download count
        $stmt = $conn->prepare("UPDATE apps SET download_count = download_count + 1 WHERE id = ?");
        $stmt->bind_param("i", $app['id']);
        $stmt->execute();
        
        // Record analytics
        $stmt = $conn->prepare("
            INSERT INTO developer_analytics (user_id, app_id, date, downloads, unique_users) 
            VALUES (?, ?, CURDATE(), 1, 1)
            ON DUPLICATE KEY UPDATE downloads = downloads + 1, unique_users = unique_users + 1
        ");
        $stmt->bind_param("ii", $app['developer_id'], $app['id']);
        $stmt->execute();
        
        // Create app configuration
        createAppConfiguration($conn, $user['id'], $app['id']);
        
        // Send notification to developer
        sendInstallationNotification($conn, $app['developer_id'], $user, $app);
        
        // Log installation
        SecurityUtils::logSecurityEvent('APP_INSTALLATION', "User {$user['id']} installed app {$app['id']}", 'info');
        
        $conn->commit();
        
        // Redirect to app runner
        SecurityUtils::safeRedirect("controllers/core/run_app.php?id={$app['id']}", 302, 'App installed successfully');
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("App installation failed: " . $e->getMessage());
        SecurityUtils::safeRedirect('controllers/marketplace/marketplace.php', 500, 'Installation failed');
    }
}

/**
 * Handle app uninstallation
 */
function handleAppUninstallation($conn, $user, $app) {
    // Check if app is installed
    $stmt = $conn->prepare("SELECT id FROM user_apps WHERE user_id = ? AND app_id = ?");
    $stmt->bind_param("ii", $user['id'], $app['id']);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 0) {
        SecurityUtils::safeRedirect('controllers/marketplace/marketplace.php', 404, 'App not installed');
    }
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Remove app installation
        $stmt = $conn->prepare("DELETE FROM user_apps WHERE user_id = ? AND app_id = ?");
        $stmt->bind_param("ii", $user['id'], $app['id']);
        $stmt->execute();
        
        // Remove app configuration
        $stmt = $conn->prepare("DELETE FROM app_configurations WHERE user_id = ? AND app_id = ?");
        $stmt->bind_param("ii", $user['id'], $app['id']);
        $stmt->execute();
        
        // Remove app data
        $stmt = $conn->prepare("DELETE FROM app_data WHERE user_id = ? AND app_id = ?");
        $stmt->bind_param("ii", $user['id'], $app['id']);
        $stmt->execute();
        
        // Update analytics
        $stmt = $conn->prepare("
            UPDATE developer_analytics 
            SET downloads = GREATEST(0, downloads - 1) 
            WHERE user_id = ? AND app_id = ? AND date = CURDATE()
        ");
        $stmt->bind_param("ii", $app['developer_id'], $app['id']);
        $stmt->execute();
        
        // Log uninstallation
        SecurityUtils::logSecurityEvent('APP_UNINSTALLATION', "User {$user['id']} uninstalled app {$app['id']}", 'info');
        
        $conn->commit();
        
        SecurityUtils::safeRedirect('controllers/marketplace/marketplace.php', 302, 'App uninstalled successfully');
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("App uninstallation failed: " . $e->getMessage());
        SecurityUtils::safeRedirect('controllers/marketplace/marketplace.php', 500, 'Uninstallation failed');
    }
}

/**
 * Handle app update
 */
function handleAppUpdate($conn, $user, $app) {
    // Check if app is installed
    $stmt = $conn->prepare("SELECT id, installed_at FROM user_apps WHERE user_id = ? AND app_id = ?");
    $stmt->bind_param("ii", $user['id'], $app['id']);
    $stmt->execute();
    $installation = $stmt->get_result()->fetch_assoc();
    
    if (!$installation) {
        SecurityUtils::safeRedirect('controllers/marketplace/marketplace.php', 404, 'App not installed');
    }
    
    // Check if there's a newer version
    $stmt = $conn->prepare("
        SELECT version, updated_at 
        FROM apps 
        WHERE id = ? AND updated_at > ?
    ");
    $stmt->bind_param("is", $app['id'], $installation['installed_at']);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 0) {
        SecurityUtils::safeRedirect("controllers/core/run_app.php?id={$app['id']}", 302, 'App is up to date');
    }
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Update installation timestamp
        $stmt = $conn->prepare("UPDATE user_apps SET installed_at = NOW() WHERE user_id = ? AND app_id = ?");
        $stmt->bind_param("ii", $user['id'], $app['id']);
        $stmt->execute();
        
        // Clear app cache
        $stmt = $conn->prepare("DELETE FROM app_cache WHERE user_id = ? AND app_id = ?");
        $stmt->bind_param("ii", $user['id'], $app['id']);
        $stmt->execute();
        
        // Log update
        SecurityUtils::logSecurityEvent('APP_UPDATE', "User {$user['id']} updated app {$app['id']}", 'info');
        
        $conn->commit();
        
        SecurityUtils::safeRedirect("controllers/core/run_app.php?id={$app['id']}", 302, 'App updated successfully');
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("App update failed: " . $e->getMessage());
        SecurityUtils::safeRedirect('controllers/marketplace/marketplace.php', 500, 'Update failed');
    }
}

/**
 * Handle app configuration
 */
function handleAppConfiguration($conn, $user, $app, $auth) {
    // Get configuration data
    $config_data = $_POST['config'] ?? [];
    
    if (empty($config_data)) {
        // Show configuration form
        showConfigurationForm($conn, $user, $app, $auth);
        return;
    }
    
    // Validate configuration data
    $validated_config = validateConfiguration($config_data, $app);
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Update configuration
        $config_json = json_encode($validated_config);
        $stmt = $conn->prepare("
            INSERT INTO app_configurations (user_id, app_id, config_data, updated_at) 
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE config_data = VALUES(config_data), updated_at = VALUES(updated_at)
        ");
        $stmt->bind_param("iis", $user['id'], $app['id'], $config_json);
        $stmt->execute();
        
        // Log configuration change
        SecurityUtils::logSecurityEvent('APP_CONFIGURATION', "User {$user['id']} configured app {$app['id']}", 'info');
        
        $conn->commit();
        
        SecurityUtils::safeRedirect("controllers/core/run_app.php?id={$app['id']}", 302, 'Configuration saved');
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("App configuration failed: " . $e->getMessage());
        SecurityUtils::safeRedirect('controllers/marketplace/marketplace.php', 500, 'Configuration failed');
    }
}

/**
 * Create default app configuration
 */
function createAppConfiguration($conn, $user_id, $app_id) {
    $default_config = [
        'theme' => 'default',
        'notifications' => true,
        'auto_save' => true,
        'language' => 'en'
    ];
    
    $config_json = json_encode($default_config);
    $stmt = $conn->prepare("
        INSERT INTO app_configurations (user_id, app_id, config_data, created_at) 
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->bind_param("iis", $user_id, $app_id, $config_json);
    $stmt->execute();
}

/**
 * Send installation notification to developer
 */
function sendInstallationNotification($conn, $developer_id, $user, $app) {
    $notification_title = "New Installation: {$app['title']}";
    $notification_message = "{$user['username']} has installed your app '{$app['title']}'";
    
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, title, message, type, created_at) 
        VALUES (?, ?, ?, 'installation', NOW())
    ");
    $stmt->bind_param("iss", $developer_id, $notification_title, $notification_message);
    $stmt->execute();
}

/**
 * Validate configuration data
 */
function validateConfiguration($config_data, $app) {
    $validated = [];
    
    // Define allowed configuration keys based on app type
    $allowed_keys = [
        'theme', 'notifications', 'auto_save', 'language', 
        'privacy', 'sharing', 'backup', 'sync'
    ];
    
    foreach ($config_data as $key => $value) {
        if (in_array($key, $allowed_keys)) {
            // Sanitize and validate each value
            $validated[$key] = SecurityUtils::validateInput($value, 'string', 255);
        }
    }
    
    return $validated;
}

/**
 * Show configuration form
 */
function showConfigurationForm($conn, $user, $app, $auth) {
    // Get current configuration
    $stmt = $conn->prepare("SELECT config_data FROM app_configurations WHERE user_id = ? AND app_id = ?");
    $stmt->bind_param("ii", $user['id'], $app['id']);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    $current_config = $result ? json_decode($result['config_data'], true) : [];
    
    ?>
    <!DOCTYPE html>
    <html lang="pl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Configure <?php echo htmlspecialchars($app['title']); ?> - Bloxer</title>
        <link rel="stylesheet" href="assets/css/style.css">
        <link rel="stylesheet" href="assets/css/ui-enhancements.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    </head>
    <body class="app-studio">
        <div class="studio-shell">
            <div class="studio-main-wrap">
                <header class="studio-header">
                    <div class="studio-header-main">
                        <h1>Configure <?php echo htmlspecialchars($app['title']); ?></h1>
                        <p>Customize your app settings and preferences</p>
                    </div>
                </header>
                
                <main class="studio-content">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">App Configuration</h2>
                        </div>
                        
                        <form method="POST" action="appinstal.php" class="config-form">
                            <input type="hidden" name="action" value="configure">
                            <input type="hidden" name="app_id" value="<?php echo $app['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo $auth->getCSRFToken(); ?>">
                            
                            <div class="form-group">
                                <label class="form-label">Theme</label>
                                <select name="config[theme]" class="form-control">
                                    <option value="default" <?php echo ($current_config['theme'] ?? '') === 'default' ? 'selected' : ''; ?>>Default</option>
                                    <option value="dark" <?php echo ($current_config['theme'] ?? '') === 'dark' ? 'selected' : ''; ?>>Dark</option>
                                    <option value="light" <?php echo ($current_config['theme'] ?? '') === 'light' ? 'selected' : ''; ?>>Light</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Language</label>
                                <select name="config[language]" class="form-control">
                                    <option value="en" <?php echo ($current_config['language'] ?? '') === 'en' ? 'selected' : ''; ?>>English</option>
                                    <option value="pl" <?php echo ($current_config['language'] ?? '') === 'pl' ? 'selected' : ''; ?>>Polski</option>
                                    <option value="es" <?php echo ($current_config['language'] ?? '') === 'es' ? 'selected' : ''; ?>>Español</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="custom-checkbox">
                                    <input type="checkbox" name="config[notifications]" value="true" 
                                           <?php echo ($current_config['notifications'] ?? true) ? 'checked' : ''; ?>>
                                    <div class="checkbox-box"><i class="fas fa-check"></i></div>
                                    <span>Enable notifications</span>
                                </label>
                            </div>
                            
                            <div class="form-group">
                                <label class="custom-checkbox">
                                    <input type="checkbox" name="config[auto_save]" value="true"
                                           <?php echo ($current_config['auto_save'] ?? true) ? 'checked' : ''; ?>>
                                    <div class="checkbox-box"><i class="fas fa-check"></i></div>
                                    <span>Auto-save data</span>
                                </label>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i>
                                    Save Configuration
                                </button>
                                <a href="controllers/core/run_app.php?id=<?php echo $app['id']; ?>" class="btn btn-secondary">
                                    <i class="fas fa-times"></i>
                                    Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </main>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}
?>
