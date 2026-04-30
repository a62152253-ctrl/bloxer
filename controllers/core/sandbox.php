<?php
require_once __DIR__ . '/../../bootstrap.php';

$auth = new AuthCore();

if (!$auth->isLoggedIn()) {
    SecurityUtils::safeExit('Unauthorized access attempt', 403);
}

$app_id = SecurityUtils::validateInput($_GET['id'] ?? null, 'int');
if (!$app_id) {
    SecurityUtils::safeExit('Invalid app ID provided', 400);
}

$user = $auth->getCurrentUser();
$conn = $auth->getConnection();

// Verify user has installed this app
$stmt = $conn->prepare("
    SELECT ua.*, a.title, a.project_id, p.slug as project_slug
    FROM user_apps ua
    JOIN apps a ON ua.app_id = a.id
    JOIN projects p ON a.project_id = p.id
    WHERE ua.user_id = ? AND ua.app_id = ? AND a.status = 'published'
");
$stmt->bind_param("ii", $user['id'], $app_id);
$stmt->execute();
$app_installation = $stmt->get_result()->fetch_assoc();

if (!$app_installation) {
    SecurityUtils::safeExit('App installation not found', 404);
}

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

// Build the app content with proper file structure
$html_content = '';
$css_files = [];
$js_files = [];
$other_files = [];

foreach ($files as $file) {
    switch ($file['file_type']) {
        case 'html':
            $html_content = $file['content'];
            break;
        case 'css':
            $css_files[$file['file_path']] = $file['content'];
            break;
        case 'js':
            $js_files[$file['file_path']] = $file['content'];
            break;
        default:
            $other_files[$file['file_path']] = $file['content'];
            break;
    }
}

// If no HTML file found, create a basic one
if (empty($html_content)) {
    $css_links = '';
    $js_scripts = '';
    
    // Generate CSS links
    foreach ($css_files as $path => $content) {
        $css_links .= '<link rel="stylesheet" href="' . htmlspecialchars($path) . '">' . "\n";
    }
    
    // Generate JS scripts
    foreach ($js_files as $path => $content) {
        $js_scripts .= '<script src="' . htmlspecialchars($path) . '"></script>' . "\n";
    }
    
    $html_content = '<!DOCTYPE html>
<html>
<head>
    <title>' . htmlspecialchars($app_installation['title']) . '</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    ' . $css_links . '
</head>
<body>
    <div id="app">
        <h1>Welcome to ' . htmlspecialchars($app_installation['title']) . '</h1>
        <p>This app is running in the Bloxer sandbox environment.</p>
    </div>
    ' . $js_scripts . '
    <script src="../assets/js/sandbox-bridge.js"></script>
</body>
</html>';
} else {
    // Ensure proper HTML structure
    if (!preg_match('/<!DOCTYPE html>/i', $html_content)) {
        $html_content = '<!DOCTYPE html>' . $html_content;
    }
    
    // Create virtual file system endpoints
    $virtual_files = '';
    
    // Add CSS files as virtual endpoints
    foreach ($css_files as $path => $content) {
        $virtual_files .= '<script>window.virtualFiles = window.virtualFiles || {}; window.virtualFiles[' . json_encode($path) . '] = ' . json_encode($content) . ';</script>' . "\n";
    }
    
    // Add JS files as virtual endpoints
    foreach ($js_files as $path => $content) {
        $virtual_files .= '<script>window.virtualFiles = window.virtualFiles || {}; window.virtualFiles[' . json_encode($path) . '] = ' . json_encode($content) . ';</script>' . "\n";
    }
    
    // Inject virtual file system and bridge
    $html_content = str_replace('</head>', $virtual_files . '</head>', $html_content);
    
    // Add file loader script
    $file_loader_script = <<<JAVASCRIPT
<script>
(function() {
    // Create virtual file system loader
    function loadVirtualFile(path) {
        if (window.virtualFiles && window.virtualFiles[path]) {
            return window.virtualFiles[path];
        }
        return null;
    }
    
    // Detect app background color and adjust iframe background
    function detectAppBackgroundColor() {
        // Check if app has dark background by analyzing CSS
        const hasDarkBackground = function() {
            // Check body background
            const bodyStyle = window.getComputedStyle(document.body);
            const bodyBg = bodyStyle.backgroundColor;
            
            // Check for common dark colors
            if (bodyBg && (bodyBg.includes('rgb(0,') || bodyBg.includes('rgb(5,') || bodyBg.includes('rgb(10,') || 
                bodyBg.includes('rgb(15,') || bodyBg.includes('rgb(20,') || bodyBg.includes('#0') || 
                bodyBg.includes('#1') || bodyBg.includes('black') || bodyBg.includes('dark'))) {
                return true;
            }
            
            // Check main container backgrounds
            const containers = document.querySelectorAll('div, section, main, article');
            for (let i = 0; i < Math.min(containers.length, 10); i++) {
                const style = window.getComputedStyle(containers[i]);
                const bg = style.backgroundColor;
                if (bg && (bg.includes('rgb(0,') || bg.includes('rgb(5,') || bg.includes('rgb(10,') || 
                    bg.includes('rgb(15,') || bg.includes('rgb(20,') || bg.includes('#0') || 
                    bg.includes('#1') || bg.includes('black') || bg.includes('dark'))) {
                    return true;
                }
            }
            
            return false;
        };
        
        // Send background info to parent
        const isDark = hasDarkBackground();
        window.parent.postMessage({
            source: 'sandbox-bridge',
            type: 'app-background-detected',
            isDark: isDark,
            timestamp: Date.now()
        }, '*');
        
        return isDark;
    }
    
    // Intercept XHR requests for virtual files
    const originalXHR = window.XMLHttpRequest;
    window.XMLHttpRequest = function() {
        const xhr = new originalXHR();
        const originalOpen = xhr.open;
        
        xhr.open = function(method, url, async, user, password) {
            // Check if this is a request for a virtual file
            if (window.virtualFiles && window.virtualFiles[url]) {
                // Override the response with virtual file content
                Object.defineProperty(xhr, "response", {
                    get: function() { return window.virtualFiles[url]; }
                });
                Object.defineProperty(xhr, "responseText", {
                    get: function() { return window.virtualFiles[url]; }
                });
                Object.defineProperty(xhr, "status", { value: 200 });
                Object.defineProperty(xhr, "readyState", { value: 4 });
                
                // Simulate async loading
                setTimeout(function() {
                    if (xhr.onload) xhr.onload();
                    if (xhr.onreadystatechange) xhr.onreadystatechange();
                }, 0);
                
                return;
            }
            
            return originalOpen.call(this, method, url, async, user, password);
        };
        
        return xhr;
    };
    
    // Intercept fetch requests for virtual files
    const originalFetch = window.fetch;
    window.fetch = function(url, options) {
        if (window.virtualFiles && window.virtualFiles[url]) {
            return Promise.resolve(new Response(window.virtualFiles[url], {
                status: 200,
                statusText: "OK",
                headers: { "Content-Type": url.endsWith(".css") ? "text/css" : "application/javascript" }
            }));
        }
        return originalFetch.call(this, url, options);
    };
    
    // Auto-load CSS and JS files referenced in HTML
    document.addEventListener("DOMContentLoaded", function() {
        // Load CSS files
        document.querySelectorAll("link[rel='stylesheet']").forEach(function(link) {
            const href = link.getAttribute("href");
            if (window.virtualFiles && window.virtualFiles[href]) {
                const style = document.createElement("style");
                style.textContent = window.virtualFiles[href];
                document.head.appendChild(style);
                link.remove(); // Remove the original link
            }
        });
        
        // Load JS files
        document.querySelectorAll("script[src]").forEach(function(script) {
            const src = script.getAttribute("src");
            if (window.virtualFiles && window.virtualFiles[src]) {
                const newScript = document.createElement("script");
                newScript.textContent = window.virtualFiles[src];
                script.parentNode.replaceChild(newScript, script);
            }
        });
        
        // Detect background color after everything is loaded
        setTimeout(function() {
            detectAppBackgroundColor();
        }, 100);
    });
})();
</script>
JAVASCRIPT;
    
    $html_content = str_replace('</body>', $file_loader_script . '<script src="../assets/js/sandbox-bridge.js"></script></body>', $html_content);
}

// Set security headers using SandboxConfig
foreach (SandboxConfig::getCSPHeaders() as $header => $value) {
    header("$header: $value");
}

// Set cache control headers
foreach (SandboxConfig::getCacheControlHeaders() as $header => $value) {
    header("$header: $value");
}

// Validate and sanitize app content
$contentIssues = SandboxConfig::validateAppContent($html_content);
if (!empty($contentIssues)) {
    SandboxConfig::logActivity('content_validation_warning', $user['id'], $app_id, ['issues' => $contentIssues]);
}

$html_content = SandboxConfig::sanitizeAppContent($html_content);

// Log sandbox access for security monitoring
SandboxConfig::logActivity('sandbox_access', $user['id'], $app_id);

echo $html_content;
?>
