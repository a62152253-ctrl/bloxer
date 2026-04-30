<?php
require_once __DIR__ . '/../../bootstrap.php';

$auth = new AuthCore();

if (!$auth->isLoggedIn()) {
    SecurityUtils::safeRedirect('../auth/login.php', 302, 'Unauthorized access attempt');
}

$app_id = SecurityUtils::validateInput($_GET['id'] ?? null, 'int');
if (!$app_id) {
    SecurityUtils::safeRedirect('../marketplace/marketplace.php', 302, 'Invalid app ID provided');
}

$user = $auth->getCurrentUser();

$conn = $auth->getConnection();

// Verify user has installed this app
$stmt = $conn->prepare("
    SELECT ua.*, a.title, a.project_id, p.slug as project_slug,
           COALESCE(ua.usage_count, 0) as usage_count
    FROM user_apps ua
    JOIN apps a ON ua.app_id = a.id
    JOIN projects p ON a.project_id = p.id
    WHERE ua.user_id = ? AND ua.app_id = ? AND a.status = 'published'
");
$stmt->bind_param("ii", $user['id'], $app_id);
$stmt->execute();
$app_installation = $stmt->get_result()->fetch_assoc();

if (!$app_installation) {
    SecurityUtils::safeRedirect('marketplace.php', 302, 'App installation not found');
}

// Update usage statistics
$stmt = $conn->prepare("
    UPDATE user_apps 
    SET usage_count = COALESCE(usage_count, 0) + 1, last_used_at = NOW() 
    WHERE user_id = ? AND app_id = ?
");
$stmt->bind_param("ii", $user['id'], $app_id);
$stmt->execute();

// Record analytics for developer
$stmt = $conn->prepare("
    INSERT INTO developer_analytics (user_id, app_id, date, unique_users, views)
    SELECT p.user_id, ?, CURDATE(), 1, 1
    FROM projects p
    WHERE p.id = ?
    ON DUPLICATE KEY UPDATE unique_users = unique_users + 1, views = views + 1
");
$stmt->bind_param("ii", $app_id, $app_installation['project_id']);
$stmt->execute();

// Get project files to run the app
$stmt = $conn->prepare("
    SELECT file_path, content, file_type
    FROM project_files
    WHERE project_id = ?
    ORDER BY file_path
");
$stmt->bind_param("i", $app_installation['project_id']);
$stmt->execute();
$files = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Build the app content
$html_content = '';
$css_content = '';
$js_content = '';

foreach ($files as $file) {
    switch ($file['file_type']) {
        case 'html':
            $html_content = $file['content'];
            break;
        case 'css':
            $css_content .= $file['content'] . "\n";
            break;
        case 'js':
            $js_content .= $file['content'] . "\n";
            break;
    }
}

// If no HTML file found, create a basic one
if (empty($html_content)) {
    $html_content = '<!DOCTYPE html>
<html>
<head>
    <title>' . htmlspecialchars($app_installation['title']) . '</title>
    <style>' . $css_content . '</style>
</head>
<body>
    <div id="app">
        <h1>Welcome to ' . htmlspecialchars($app_installation['title']) . '</h1>
        <p>This app is running in the Bloxer environment.</p>
    </div>
    <script>' . $js_content . '</script>
</body>
</html>';
} else {
    // Inject CSS and JS into HTML
    $html_content = str_replace('</head>', '<style>' . $css_content . '</style></head>', $html_content);
    $html_content = str_replace('</body>', '<script>' . $js_content . '</script></body>', $html_content);
}

?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($app_installation['title']); ?> - Running on Bloxer</title>
    <style>
        .app-runner {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: white;
            z-index: 1000;
        }
        
        .app-header {
            background: linear-gradient(135deg, var(--btn-gradient-start) 0%, var(--btn-gradient-end) 100%);
            color: white;
            padding: 15px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 60px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            position: relative;
            overflow: hidden;
        }
        
        .app-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            animation: shimmer 3s ease-in-out infinite;
        }
        
        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }
        
        .app-title {
            font-weight: 700;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
            z-index: 1;
        }
        
        .app-title i {
            animation: rocketFloat 2s ease-in-out infinite;
        }
        
        @keyframes rocketFloat {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-2px) rotate(5deg); }
        }
        
        .app-controls {
            display: flex;
            gap: 12px;
            align-items: center;
            position: relative;
            z-index: 1;
        }
        
        .app-controls button {
            background: linear-gradient(135deg, rgba(255,255,255,0.2), rgba(255,255,255,0.1));
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 0.85rem;
            font-weight: 600;
            position: relative;
            overflow: hidden;
        }
        
        .app-controls button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.4s ease;
        }
        
        .app-controls button:hover {
            background: linear-gradient(135deg, rgba(255,255,255,0.3), rgba(255,255,255,0.2));
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255,255,255,0.2);
        }
        
        .app-controls button:hover::before {
            left: 100%;
        }
        
        .app-frame {
            position: absolute;
            top: 50px;
            left: 0;
            right: 0;
            bottom: 0;
            background: white;
        }
        
        .app-content {
            width: 100%;
            height: 100%;
            border: none;
            background: white;
            border-radius: 0;
        }
        
        .app-content:error {
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .app-content:error::before {
            content: '🔒 App failed to load in sandbox';
            font-size: 1.1rem;
            padding: 20px;
            text-align: center;
        }
        
        .fullscreen-mode .app-header {
            display: none;
        }
        
        .fullscreen-mode .app-frame {
            top: 0;
        }
        
        .app-info {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 10px 15px;
            border-radius: 25px;
            font-size: 0.8rem;
            z-index: 1001;
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <div class="app-runner" id="app-runner">
        <div class="app-header">
            <div class="app-title">
                <i class="fas fa-rocket"></i>
                <?php echo htmlspecialchars($app_installation['title']); ?>
            </div>
            <div class="app-controls">
                <button class="control-btn" onclick="toggleFullscreen()">
                    <i class="fas fa-expand"></i> Fullscreen
                </button>
                <button class="control-btn" onclick="reloadApp()">
                    <i class="fas fa-sync"></i> Reload
                </button>
                <a href="app.php?id=<?php echo $app_id; ?>" class="control-btn">
                    <i class="fas fa-info-circle"></i> App Info
                </a>
                <a href="profile.php" class="control-btn">
                    <i class="fas fa-times"></i> Close
                </a>
            </div>
        </div>
        <div class="app-frame">
            <iframe 
                class="app-content" 
                id="app-iframe"
                src="sandbox.php?id=<?php echo $app_id; ?>"
                sandbox="<?php echo SandboxConfig::getSandboxAttributes(); ?>"
                allow="<?php echo SandboxConfig::getAllowAttributes(); ?>"
                loading="lazy"
                referrerpolicy="strict-origin-when-cross-origin"
                importance="high">
            </iframe>
        </div>
        <div class="app-info">
            Running on Bloxer • Used <?php echo $app_installation['usage_count'] ?? 0; ?> times
        </div>
    </div>
    
    <script src="../assets/js/sandbox-bridge.js"></script>
    <script>
        // Listen for messages from iframe
        window.addEventListener('message', function(event) {
            // Verify origin for security
            if (event.origin !== window.location.origin) {
                return;
            }
            
            const data = event.data;
            if (!data.source || data.source !== 'sandbox-bridge') return;
            
            if (data.type === 'app-ready') {
                if (window.appDebugMode) console.log('App is ready in sandbox', data.features);
            } else if (data.type === 'app-error') {
                console.error('App reported error:', data);
                // Optionally show user-friendly error message
                showErrorNotification('App encountered an error: ' + data.message);
            } else if (data.type === 'app-action') {
                // Handle app actions (e.g., fullscreen requests)
                if (data.action === 'request-fullscreen') {
                    toggleFullscreen();
                }
            } else if (data.type === 'app-console') {
                // Forward console messages to parent console
                console[data.level](...data.args);
            } else if (data.type === 'app-background-detected') {
                // Adjust iframe background based on app theme
                const iframe = document.getElementById('app-iframe');
                if (iframe) {
                    if (data.isDark) {
                        iframe.style.background = '#050816'; // Dark background for dark apps
                    } else {
                        iframe.style.background = '#ffffff'; // White background for light apps
                    }
                }
                if (window.appDebugMode) console.log('App background detected:', data.isDark ? 'dark' : 'light');
            }
        });
        
        function showErrorNotification(message) {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 70px;
                right: 20px;
                background: #dc3545;
                color: white;
                padding: 15px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 20px rgba(220,53,69,0.3);
                z-index: 1002;
                max-width: 300px;
                font-size: 0.9rem;
                animation: slideIn 0.3s ease;
            `;
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }, 5000);
        }
        
        // Add slide animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
        
        let isFullscreen = false;
        
        function toggleFullscreen() {
            const runner = document.getElementById('app-runner');
            isFullscreen = !isFullscreen;
            
            if (isFullscreen) {
                runner.classList.add('fullscreen-mode');
                if (runner.requestFullscreen) {
                    runner.requestFullscreen();
                } else if (runner.webkitRequestFullscreen) {
                    runner.webkitRequestFullscreen();
                } else if (runner.msRequestFullscreen) {
                    runner.msRequestFullscreen();
                }
            } else {
                runner.classList.remove('fullscreen-mode');
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                } else if (document.msExitFullscreen) {
                    document.msExitFullscreen();
                }
            }
        }
        
        function reloadApp() {
            const iframe = document.getElementById('app-iframe');
            if (iframe) {
                iframe.src = iframe.src;
            } else {
                location.reload();
            }
        }
        
        function handleIframeLoad() {
            if (window.appDebugMode) {
                console.log('App sandbox loaded successfully');
            }
            // Send initialization message to iframe
            const iframe = document.getElementById('app-iframe');
            if (iframe && iframe.contentWindow) {
                iframe.contentWindow.postMessage({
                    type: 'sandbox-init',
                    appId: <?php echo $app_id; ?>,
                    timestamp: Date.now()
                }, '*');
            }
        }
        
        function handleIframeError() {
            console.error('App sandbox failed to load');
            const iframe = document.getElementById('app-iframe');
            if (iframe) {
                iframe.style.background = '#f8f9fa';
                iframe.innerHTML = '<div style="display: flex; align-items: center; justify-content: center; height: 100vh; color: #6c757d; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif;"><div><h3>🔒 Sandbox Error</h3><p>App failed to load in secure environment</p><button onclick="reloadApp()" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">Retry</button></div></div>';
            }
        }
        
        // Handle ESC key to exit fullscreen
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && isFullscreen) {
                toggleFullscreen();
            }
        });
        
        // Handle fullscreen change events
        document.addEventListener('fullscreenchange', function() {
            if (!document.fullscreenElement && isFullscreen) {
                isFullscreen = false;
                document.getElementById('app-runner').classList.remove('fullscreen-mode');
            }
        });
        
        document.addEventListener('webkitfullscreenchange', function() {
            if (!document.webkitFullscreenElement && isFullscreen) {
                isFullscreen = false;
                document.getElementById('app-runner').classList.remove('fullscreen-mode');
            }
        });
        
        // Auto-hide app info after 5 seconds
        setTimeout(function() {
            const info = document.querySelector('.app-info');
            if (info) {
                info.style.transition = 'opacity 0.5s ease';
                info.style.opacity = '0';
                setTimeout(function() {
                    info.style.display = 'none';
                }, 500);
            }
        }, 5000);
        
        // Setup iframe event listeners
        document.addEventListener('DOMContentLoaded', function() {
            const iframe = document.getElementById('app-iframe');
            if (iframe) {
                iframe.addEventListener('load', handleIframeLoad);
                iframe.addEventListener('error', handleIframeError);
            }
        });
        
        // Listen for messages from iframe
        window.addEventListener('message', function(event) {
            // Verify origin for security
            if (event.origin !== window.location.origin) {
                return;
            }
            
            const data = event.data;
            if (!data.source || data.source !== 'sandbox-bridge') return;
            
            if (data.type === 'app-ready') {
                if (window.appDebugMode) console.log('App is ready in sandbox', data.features);
            } else if (data.type === 'app-error') {
                console.error('App reported error:', data);
                // Optionally show user-friendly error message
                showErrorNotification('App encountered an error: ' + data.message);
            } else if (data.type === 'app-action') {
                // Handle app actions (e.g., fullscreen requests)
                if (data.action === 'request-fullscreen') {
                    toggleFullscreen();
                }
            } else if (data.type === 'app-console') {
                // Forward console messages to parent console
                console[data.level](...data.args);
            } else if (data.type === 'app-background-detected') {
                // Adjust iframe background based on app theme
                const iframe = document.getElementById('app-iframe');
                if (iframe) {
                    if (data.isDark) {
                        iframe.style.background = '#050816'; // Dark background for dark apps
                    } else {
                        iframe.style.background = '#ffffff'; // White background for light apps
                    }
                }
                if (window.appDebugMode) console.log('App background detected:', data.isDark ? 'dark' : 'light');
            }
        });
        
        function showErrorNotification(message) {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 70px;
                right: 20px;
                background: #dc3545;
                color: white;
                padding: 15px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 20px rgba(220,53,69,0.3);
                z-index: 1002;
                max-width: 300px;
                font-size: 0.9rem;
                animation: slideIn 0.3s ease;
            `;
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }, 5000);
        }
        
        // Add slide animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
