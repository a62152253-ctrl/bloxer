<?php
/**
 * Developer Tools Dashboard for Bloxer
 */

require_once '../controllers/core/mainlogincore.php';
require_once '../config/security.php';

$auth = new AuthCore();

// Only allow developers
if (!$auth->isLoggedIn() || !$auth->isDeveloper()) {
    SecurityUtils::safeExit('Access denied. Developer access required.', 403, 'warning');
}

echo "<!DOCTYPE html>
<html lang='pl'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Developer Tools - Bloxer</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
            color: #333;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 30px;
        }
        .tools-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .tool-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .tool-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.2);
        }
        .tool-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        .tool-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }
        .tool-description {
            color: #666;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        .tool-button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
            font-weight: 500;
            transition: opacity 0.2s;
        }
        .tool-button:hover {
            opacity: 0.9;
        }
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .status-ok { background: #4caf50; }
        .status-warning { background: #ff9800; }
        .status-error { background: #f44336; }
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .back-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            display: inline-block;
            margin-bottom: 20px;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <a href='../index.php' class='back-link'>← Back to Bloxer</a>
    
    <div class='header'>
        <h1>🛠️ Developer Tools</h1>
        <p>Advanced debugging and analysis tools for Bloxer development</p>
    </div>

    <div class='info-box'>
        <strong>📊 Development Status:</strong> All tools are ready for use. These tools help maintain code quality, security, and performance.
    </div>

    <div class='tools-grid'>
        <div class='tool-card'>
            <div class='tool-icon'>🔍</div>
            <div class='tool-title'>Static Analysis</div>
            <div class='tool-description'>
                Comprehensive code quality analysis including security vulnerabilities, deprecated functions, and code smells.
            </div>
            <div>
                <span class='status-indicator status-ok'></span>
                <strong>Status:</strong> Ready
            </div>
            <br><br>
            <a href='static_analysis.php' class='tool-button'>Run Analysis</a>
        </div>

        <div class='tool-card'>
            <div class='tool-icon'>🐛</div>
            <div class='tool-title'>Debug Information</div>
            <div class='tool-description'>
                System diagnostics including PHP configuration, database status, filesystem info, and recent errors.
            </div>
            <div>
                <span class='status-indicator status-ok'></span>
                <strong>Status:</strong> Ready
            </div>
            <br><br>
            <a href='debug_info.php' class='tool-button'>View Debug Info</a>
        </div>

        <div class='tool-card'>
            <div class='tool-icon'>🔒</div>
            <div class='tool-title'>Security Testing</div>
            <div class='tool-description'>
                Security vulnerability scanner checking for XSS, SQL injection, CSRF, file permissions, and other security issues.
            </div>
            <div>
                <span class='status-indicator status-ok'></span>
                <strong>Status:</strong> Ready
            </div>
            <br><br>
            <a href='security_tester.php' class='tool-button'>Run Security Tests</a>
        </div>

        <div class='tool-card'>
            <div class='tool-icon'>📈</div>
            <div class='tool-title'>Performance Monitor</div>
            <div class='tool-description'>
                Real-time performance monitoring including memory usage, execution time, and database query analysis.
            </div>
            <div>
                <span class='status-indicator status-warning'></span>
                <strong>Status:</strong> Coming Soon
            </div>
            <br><br>
            <button class='tool-button' disabled>Coming Soon</button>
        </div>

        <div class='tool-card'>
            <div class='tool-icon'>📝</div>
            <div class='tool-title'>Code Profiler</div>
            <div class='tool-description'>
                Advanced profiling tool to identify performance bottlenecks and optimize code execution.
            </div>
            <div>
                <span class='status-indicator status-warning'></span>
                <strong>Status:</strong> Coming Soon
            </div>
            <br><br>
            <button class='tool-button' disabled>Coming Soon</button>
        </div>

        <div class='tool-card'>
            <div class='tool-icon'>🔥</div>
            <div class='tool-title'>Error Logger</div>
            <div class='tool-description'>
                Centralized error logging and analysis with real-time monitoring and alerting capabilities.
            </div>
            <div>
                <span class='status-indicator status-warning'></span>
                <strong>Status:</strong> Coming Soon
            </div>
            <br><br>
            <button class='tool-button' disabled>Coming Soon</button>
        </div>
    </div>

    <div class='info-box'>
        <h3>🚀 Quick Start Guide</h3>
        <ol>
            <li><strong>Static Analysis:</strong> Run this first to identify code quality issues and security vulnerabilities.</li>
            <li><strong>Debug Information:</strong> Check system configuration and environment status.</li>
            <li><strong>Security Testing:</strong> Perform comprehensive security scans.</li>
            <li><strong>Monitor Performance:</strong> Use upcoming tools for performance optimization.</li>
        </ol>
    </div>

    <div class='info-box'>
        <h3>📋 Tool Levels</h3>
        <p><strong>✅ Level 1 - Status Endpoints:</strong> Basic health checks and monitoring</p>
        <p><strong>✅ Level 2 - Logs PHP:</strong> Error logging and analysis (available)</p>
        <p><strong>🔥 Level 3 - Static Analysis:</strong> PHPStan-quality code analysis (implemented)</p>
        <p><strong>🔥 Level 4 - Debugger:</strong> Xdebug integration (planned)</p>
        <p><strong>🔥 Level 5 - Profiling:</strong> Blackfire-style performance analysis (planned)</p>
        <p><strong>🔥 Level 6 - Observability:</strong> Sentry-style error tracking (planned)</p>
        <p><strong>🔥 Level 7 - Security Testing:</strong> OWASP security scanning (implemented)</p>
    </div>

    <script>
        // Add some interactivity
        document.addEventListener('DOMContentLoaded', function() {
            // Animate cards on load
            const cards = document.querySelectorAll('.tool-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'opacity 0.5s, transform 0.5s';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>";
?>
