<?php
// Bloxer Application Router
require_once 'bootstrap.php';

$auth = new AuthCore();

if ($auth->isLoggedIn()) {
    if ($auth->isDeveloper()) {
        SecurityUtils::safeRedirect('controllers/core/dashboard.php');
    } else {
        SecurityUtils::safeRedirect('controllers/marketplace/marketplace.php');
    }
} else {
    SecurityUtils::safeRedirect('controllers/auth/login.php');
}
return;
?>
