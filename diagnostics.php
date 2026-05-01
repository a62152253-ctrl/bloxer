<?php
/**
 * Bloxer - System Diagnostic & Path Tester
 * This page helps identify broken paths and missing functions
 */

require_once __DIR__ . '/bootstrap.php';

$diagnostics = [];
$passed = 0;
$failed = 0;

// Test 1: Bootstrap includes
$test = 'Bootstrap Core';
try {
    if (class_exists('AppConfig')) {
        $diagnostics[] = ['test' => $test, 'status' => 'PASS', 'detail' => 'AppConfig class exists'];
        $passed++;
    } else {
        $diagnostics[] = ['test' => $test, 'status' => 'FAIL', 'detail' => 'AppConfig not found'];
        $failed++;
    }
} catch (Exception $e) {
    $diagnostics[] = ['test' => $test, 'status' => 'ERROR', 'detail' => $e->getMessage()];
    $failed++;
}

// Test 2: Auth system
$test = 'Authentication System';
try {
    if (class_exists('AuthCore')) {
        $diagnostics[] = ['test' => $test, 'status' => 'PASS', 'detail' => 'AuthCore class exists'];
        $passed++;
    } else {
        $diagnostics[] = ['test' => $test, 'status' => 'FAIL', 'detail' => 'AuthCore not found'];
        $failed++;
    }
} catch (Exception $e) {
    $diagnostics[] = ['test' => $test, 'status' => 'ERROR', 'detail' => $e->getMessage()];
    $failed++;
}

// Test 3: File paths
$test = 'Critical File Paths';
$paths = [
    'Dashboard' => 'controllers/core/dashboard.php',
    'Projects' => 'controllers/projects/projects.php',
    'CSS Modal Fix' => 'assets/css/modal-fix.css',
    'JS Fix' => 'assets/js/dashboard-fix.js',
];

foreach ($paths as $name => $path) {
    if (file_exists(BLOXER_ROOT . '/' . $path)) {
        $diagnostics[] = ['test' => "File: $name", 'status' => 'PASS', 'detail' => $path];
        $passed++;
    } else {
        $diagnostics[] = ['test' => "File: $name", 'status' => 'FAIL', 'detail' => "Missing: $path"];
        $failed++;
    }
}

// Test 4: Database connection
$test = 'Database Connection';
try {
    $db = DatabaseConfig::getInstance();
    $conn = $db->getConnection();
    if ($conn) {
        $diagnostics[] = ['test' => $test, 'status' => 'PASS', 'detail' => 'Database connected'];
        $passed++;
    } else {
        $diagnostics[] = ['test' => $test, 'status' => 'FAIL', 'detail' => 'No connection'];
        $failed++;
    }
} catch (Exception $e) {
    $diagnostics[] = ['test' => $test, 'status' => 'ERROR', 'detail' => $e->getMessage()];
    $failed++;
}

// Test 5: Session
$test = 'Session Management';
if (session_status() === PHP_SESSION_ACTIVE) {
    $diagnostics[] = ['test' => $test, 'status' => 'PASS', 'detail' => 'Session active'];
    $passed++;
} else {
    $diagnostics[] = ['test' => $test, 'status' => 'FAIL', 'detail' => 'Session not started'];
    $failed++;
}

// Test 6: User session
$test = 'User Authentication';
if (isset($_SESSION['user_id'])) {
    $diagnostics[] = ['test' => $test, 'status' => 'PASS', 'detail' => 'User logged in: ' . ($_SESSION['username'] ?? 'Unknown')];
    $passed++;
} else {
    $diagnostics[] = ['test' => $test, 'status' => 'WARN', 'detail' => 'Not logged in (expected for this test)'];
}

// Test 7: Common include paths
$test = 'Common Include Paths';
$includes = [
    'helpers/security_utils.php' => file_exists(BLOXER_ROOT . '/helpers/security_utils.php'),
    'config/app.php' => file_exists(BLOXER_ROOT . '/config/app.php'),
    'config/database.php' => file_exists(BLOXER_ROOT . '/config/database.php'),
];

foreach ($includes as $path => $exists) {
    if ($exists) {
        $diagnostics[] = ['test' => "Include: $path", 'status' => 'PASS', 'detail' => 'Found'];
        $passed++;
    } else {
        $diagnostics[] = ['test' => "Include: $path", 'status' => 'FAIL', 'detail' => 'Missing'];
        $failed++;
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bloxer - System Diagnostics</title>
    <style>
        * { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; }
        body { 
            background: #f5f5f5; 
            margin: 0; 
            padding: 20px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #007AFF, #0056CC);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 32px;
        }
        .summary {
            display: flex;
            gap: 20px;
            padding: 20px 30px;
            background: #f9f9f9;
            border-bottom: 1px solid #e0e0e0;
        }
        .summary-item {
            flex: 1;
            text-align: center;
        }
        .summary-item strong {
            font-size: 24px;
            display: block;
        }
        .summary-item span {
            color: #666;
            font-size: 14px;
        }
        .diagnostics {
            max-height: 500px;
            overflow-y: auto;
        }
        .diagnostic-row {
            display: flex;
            padding: 16px 30px;
            border-bottom: 1px solid #e0e0e0;
            align-items: center;
        }
        .diagnostic-row:hover {
            background: #f9f9f9;
        }
        .diagnostic-status {
            width: 100px;
            margin-right: 20px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
        }
        .status-pass {
            background: #d4edda;
            color: #155724;
        }
        .status-fail {
            background: #f8d7da;
            color: #721c24;
        }
        .status-error {
            background: #f8d7da;
            color: #721c24;
        }
        .status-warn {
            background: #fff3cd;
            color: #856404;
        }
        .diagnostic-test {
            flex: 1;
            font-weight: 600;
            color: #333;
        }
        .diagnostic-detail {
            flex: 2;
            color: #666;
            font-size: 13px;
        }
        .footer {
            padding: 20px 30px;
            background: #f9f9f9;
            border-top: 1px solid #e0e0e0;
            text-align: center;
            color: #666;
            font-size: 13px;
        }
        .recommendations {
            padding: 20px 30px;
            background: #e3f2fd;
            border-left: 4px solid #007AFF;
            margin: 20px;
            border-radius: 4px;
        }
        .recommendations h3 {
            margin-top: 0;
            color: #0056CC;
        }
        .recommendations ul {
            margin: 0;
            padding-left: 20px;
        }
        .recommendations li {
            margin: 8px 0;
            color: #0056CC;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 Bloxer System Diagnostics</h1>
            <p>Checking application health and configuration</p>
        </div>
        
        <div class="summary">
            <div class="summary-item">
                <strong style="color: #34C759;"><?php echo $passed; ?></strong>
                <span>Passed</span>
            </div>
            <div class="summary-item">
                <strong style="color: #FF3B30;"><?php echo $failed; ?></strong>
                <span>Failed</span>
            </div>
            <div class="summary-item">
                <strong><?php echo count($diagnostics); ?></strong>
                <span>Total Tests</span>
            </div>
        </div>
        
        <div class="diagnostics">
            <?php foreach ($diagnostics as $diag): ?>
            <div class="diagnostic-row">
                <div class="diagnostic-status">
                    <span class="status-badge status-<?php echo strtolower($diag['status']); ?>">
                        <?php echo $diag['status']; ?>
                    </span>
                </div>
                <div class="diagnostic-test"><?php echo htmlspecialchars($diag['test']); ?></div>
                <div class="diagnostic-detail"><?php echo htmlspecialchars($diag['detail']); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($failed > 0): ?>
        <div class="recommendations">
            <h3>⚠️ Recommendations</h3>
            <ul>
                <li>Check that all required files exist and are readable</li>
                <li>Verify database configuration in config/database.php</li>
                <li>Clear browser cache and cookies if assets aren't loading</li>
                <li>Check PHP error logs in logs/ directory</li>
                <li>Ensure proper file permissions (644 for files, 755 for directories)</li>
                <li>Restart your web server if changes don't appear</li>
            </ul>
        </div>
        <?php else: ?>
        <div class="recommendations" style="background: #d4edda; border-left-color: #34C759; color: #155724;">
            <h3 style="color: #155724;">✅ System Check Passed</h3>
            <p>All diagnostics passed successfully. Your Bloxer application should be running properly.</p>
        </div>
        <?php endif; ?>
        
        <div class="footer">
            <p>Last checked: <?php echo date('Y-m-d H:i:s'); ?> | Bloxer v<?php echo BLOXER_VERSION; ?></p>
        </div>
    </div>
</body>
</html>
