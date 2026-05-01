<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simulate web environment
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['SERVER_NAME'] = 'localhost';
$_POST['username'] = 'john@dev.com';
$_POST['password'] = 'Dev123456';
$_POST['csrf_token'] = 'test';

// Start session properly
if (session_status() === PHP_SESSION_NONE || session_status() === PHP_SESSION_DISABLED) {
    session_start();
}

require_once 'bootstrap.php';

$auth = new AuthCore();
$result = $auth->login('john@dev.com', 'Dev123456');

echo "Login result: " . ($result['success'] ? 'SUCCESS' : 'FAILED') . "\n";
if (!$result['success']) {
    echo "Errors: " . implode(', ', $result['errors']) . "\n";
} else {
    $user = $auth->getCurrentUser();
    echo "User: " . htmlspecialchars($user['username']) . "\n";
    echo "Is logged in: " . ($auth->isLoggedIn() ? 'YES' : 'NO') . "\n";
    echo "Is developer: " . ($auth->isDeveloper() ? 'YES' : 'NO') . "\n";
}
?>
