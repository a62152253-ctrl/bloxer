<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'john@dev.com';
$_SESSION['email'] = 'john@dev.com';
$_SESSION['user_type'] = 'developer';
$_SESSION['last_activity'] = time();

require_once 'bootstrap.php';
$auth = new AuthCore();

echo "Is logged in: " . ($auth->isLoggedIn() ? 'YES' : 'NO') . "\n";
echo "Is developer: " . ($auth->isDeveloper() ? 'YES' : 'NO') . "\n";

$user = $auth->getCurrentUser();
if ($user) {
    echo "Current user: " . htmlspecialchars($user['username']) . "\n";
}
?>
