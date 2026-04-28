<?php
require_once 'mainlogincore.php';

$auth = new AuthCore();

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$app_id = $_GET['id'] ?? null;
$user = $auth->getCurrentUser();

if (!$app_id) {
    header('Location: user_dashboard.php');
    exit();
}

$conn = $auth->getConnection();

// Verify user has installed this app
$stmt = $conn->prepare("
    SELECT ul.*, a.title, a.project_id, p.slug as project_slug,
           COALESCE(ul.usage_count, 0) as usage_count
    FROM user_library ul
    JOIN apps a ON ul.app_id = a.id
    JOIN projects p ON a.project_id = p.id
    WHERE ul.user_id = ? AND ul.app_id = ? AND a.status = 'published'
");
$stmt->bind_param("ii", $user['id'], $app_id);
$stmt->execute();
$app_installation = $stmt->get_result()->fetch_assoc();

if (!$app_installation) {
    header('Location: user_dashboard.php');
    exit();
}

// Update usage statistics
$stmt = $conn->prepare("
    UPDATE user_library 
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
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 50px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .app-title {
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .app-controls {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .control-btn {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .control-btn:hover {
            background: rgba(255,255,255,0.3);
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
            <div class="app-content" id="app-content">
                <?php echo $html_content; ?>
            </div>
        </div>
        <div class="app-info">
            Running on Bloxer • Used <?php echo $app_installation['usage_count'] ?? 0; ?> times
        </div>
    </div>
    
    <script>
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
            location.reload();
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
    </script>
</body>
</html>
