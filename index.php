<?php
require_once 'mainlogincore.php';

$auth = new AuthCore();

if ($auth->isLoggedIn()) {
    if ($auth->isDeveloper()) {
        header('Location: dashboard.php');
    } else {
        header('Location: marketplace.php');
    }
} else {
    header('Location: login.php');
}
exit();
?>
