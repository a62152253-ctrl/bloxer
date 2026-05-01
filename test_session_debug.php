<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Session status: " . session_status() . "\n";
echo "Session ID: " . session_id() . "\n";

$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'john@dev.com';
$_SESSION['email'] = 'john@dev.com';
$_SESSION['user_type'] = 'developer';
$_SESSION['last_activity'] = time();

// Session debug info removed for security

require_once 'bootstrap.php';
$auth = new AuthCore();

// Session debug info removed for security

echo "Is logged in: " . ($auth->isLoggedIn() ? 'YES' : 'NO') . "\n";
echo "Is developer: " . ($auth->isDeveloper() ? 'YES' : 'NO') . "\n";
?>
