<?php
/**
 * Server Debug Script for Bloxer Platform
 * Helps diagnose 500 errors on the server
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Bloxer Server Debug</h1>";
echo "<pre>";

// Check PHP version
echo "PHP Version: " . PHP_VERSION . "\n";

// Check required extensions
$required_extensions = ['pdo', 'pdo_mysql', 'mysqli', 'json', 'mbstring', 'session'];
echo "\n=== Required Extensions ===\n";
foreach ($required_extensions as $ext) {
    $status = extension_loaded($ext) ? "✓" : "✗";
    echo "$status $ext\n";
}

// Check file permissions
echo "\n=== File Permissions ===\n";
$important_files = [
    '.env' => 'readable',
    'bootstrap.php' => 'readable',
    'config/database.php' => 'readable',
    'controllers/core/mainlogincore.php' => 'readable',
    'helpers/security_utils.php' => 'readable'
];

foreach ($important_files as $file => $required_permission) {
    $filepath = __DIR__ . '/' . $file;
    if (file_exists($filepath)) {
        $readable = is_readable($filepath) ? "✓" : "✗";
        $writable = is_writable($filepath) ? "✓" : "✗";
        echo "$readable $file (readable) $writable (writable)\n";
    } else {
        echo "✗ $file (missing)\n";
    }
}

// Check directory permissions
echo "\n=== Directory Permissions ===\n";
$important_dirs = [
    'cache',
    'logs',
    'uploads'
];

foreach ($important_dirs as $dir) {
    $dirpath = __DIR__ . '/' . $dir;
    if (is_dir($dirpath)) {
        $readable = is_readable($dirpath) ? "✓" : "✗";
        $writable = is_writable($dirpath) ? "✓" : "✗";
        echo "$readable $dir/ (readable) $writable (writable)\n";
    } else {
        echo "? $dir/ (missing - will try to create)\n";
        if (mkdir($dirpath, 0755, true)) {
            echo "  ✓ Created $dir/\n";
        } else {
            echo "  ✗ Failed to create $dir/\n";
        }
    }
}

// Test .env file
echo "\n=== Environment Configuration ===\n";
$env_file = __DIR__ . '/.env';
if (file_exists($env_file)) {
    echo "✓ .env file exists\n";
    $env_content = file_get_contents($env_file);
    if (strpos($env_content, 'DB_HOST') !== false) {
        echo "✓ DB_HOST found\n";
    } else {
        echo "✗ DB_HOST missing\n";
    }
    if (strpos($env_content, 'DB_USER') !== false) {
        echo "✓ DB_USER found\n";
    } else {
        echo "✗ DB_USER missing\n";
    }
} else {
    echo "✗ .env file missing\n";
}

// Test database connection
echo "\n=== Database Connection Test ===\n";
try {
    require_once __DIR__ . '/bootstrap.php';
    $db = DatabaseConfig::getInstance();
    $conn = $db->getConnection();
    
    if ($conn) {
        echo "✓ Database connection successful\n";
        
        // Test a simple query
        $stmt = $conn->query("SELECT 1");
        if ($stmt) {
            echo "✓ Database query test passed\n";
        } else {
            echo "✗ Database query test failed\n";
        }
    } else {
        echo "✗ Database connection failed\n";
    }
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
}

// Test authentication class
echo "\n=== Authentication Test ===\n";
try {
    require_once __DIR__ . '/bootstrap.php';
    $auth = new AuthCore();
    echo "✓ AuthCore class loaded successfully\n";
    
    // Test CSRF token generation
    $token = $auth->getCSRFToken();
    if (!empty($token)) {
        echo "✓ CSRF token generation works\n";
    } else {
        echo "✗ CSRF token generation failed\n";
    }
} catch (Exception $e) {
    echo "✗ Authentication error: " . $e->getMessage() . "\n";
}

// Check .htaccess
echo "\n=== .htaccess Check ===\n";
$htaccess_file = __DIR__ . '/.htaccess';
if (file_exists($htaccess_file)) {
    echo "✓ .htaccess exists\n";
    $htaccess_content = file_get_contents($htaccess_file);
    if (strpos($htaccess_content, 'RewriteEngine') !== false) {
        echo "✓ Rewrite rules found\n";
    } else {
        echo "? No rewrite rules found\n";
    }
} else {
    echo "? .htaccess missing (may be needed for pretty URLs)\n";
}

// Memory and execution limits
echo "\n=== PHP Limits ===\n";
echo "Memory limit: " . ini_get('memory_limit') . "\n";
echo "Max execution time: " . ini_get('max_execution_time') . "s\n";
echo "Upload max filesize: " . ini_get('upload_max_filesize') . "\n";
echo "Post max size: " . ini_get('post_max_size') . "\n";

// Error log check
echo "\n=== Error Log ===\n";
$error_log = ini_get('error_log');
echo "Error log location: $error_log\n";
if (file_exists($error_log)) {
    $last_lines = array_slice(file($error_log), -10);
    echo "Last 10 error log entries:\n";
    foreach ($last_lines as $line) {
        echo trim($line) . "\n";
    }
} else {
    echo "? Error log file not found\n";
}

echo "\n=== Test Loading Common Pages ===\n";

// Test loading login page
try {
    ob_start();
    include __DIR__ . '/controllers/auth/login.php';
    $output = ob_get_clean();
    if (strpos($output, '<!DOCTYPE html') !== false) {
        echo "✓ Login page loads successfully\n";
    } else {
        echo "✗ Login page output is invalid\n";
    }
} catch (Exception $e) {
    echo "✗ Login page error: " . $e->getMessage() . "\n";
} catch (Error $e) {
    echo "✗ Login page fatal error: " . $e->getMessage() . "\n";
}

echo "\n=== Recommendations ===\n";
echo "1. Ensure all required PHP extensions are installed\n";
echo "2. Check file/directory permissions (755 for dirs, 644 for files)\n";
echo "3. Verify .env configuration is correct\n";
echo "4. Check server error logs for specific error details\n";
echo "5. Ensure database server is accessible\n";

echo "\nDebug complete!\n";
echo "</pre>";

// Add a simple test form
?>
<form method="post">
    <input type="hidden" name="test" value="1">
    <button type="submit">Test POST Request</button>
</form>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test'])) {
    echo "<h3>POST Test Successful</h3>";
    echo "POST data received successfully\n";
}
?>
