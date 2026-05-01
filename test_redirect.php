<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simulate successful login
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'john@dev.com';
$_SESSION['email'] = 'john@dev.com';
$_SESSION['user_type'] = 'developer';
$_SESSION['last_activity'] = time();
$_SESSION['ip_address'] = '127.0.0.1';
$_SESSION['user_agent'] = 'Test';

require_once 'bootstrap.php';

$auth = new AuthCore();

echo "Is logged in: " . ($auth->isLoggedIn() ? 'YES' : 'NO') . "\n";
echo "Is developer: " . ($auth->isDeveloper() ? 'YES' : 'NO') . "\n";

$user = $auth->getCurrentUser();
echo "Current user: " . htmlspecialchars($user['username']) . "\n";

// Test redirect paths
echo "Current directory: " . __DIR__ . "\n";
echo "Login script location: controllers/auth/login.php\n";
echo "Dashboard target: ../core/dashboard.php\n";
echo "Marketplace target: ../marketplace/marketplace.php\n";

// Check if files exist
echo "Dashboard exists: " . (file_exists('controllers/core/dashboard.php') ? 'YES' : 'NO') . "\n";
echo "Marketplace exists: " . (file_exists('controllers/marketplace/marketplace.php') ? 'YES' : 'NO') . "\n";
?>
