<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Test starting...\n";

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['username'] = 'john@dev.com';
$_POST['password'] = 'Dev123456';
$_POST['csrf_token'] = 'test';
$_SESSION = [];

echo "Loading bootstrap...\n";
require_once 'bootstrap.php';

echo "Creating AuthCore...\n";
$auth = new AuthCore();

echo "Testing login...\n";
$result = $auth->login('john@dev.com', 'Dev123456');

echo "Login result: " . ($result['success'] ? 'SUCCESS' : 'FAILED') . "\n";
if (!$result['success']) {
    echo "Errors: " . implode(', ', $result['errors']) . "\n";
}

echo "Test completed.\n";
?>
