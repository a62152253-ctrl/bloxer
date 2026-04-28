<?php
// Clear all cookies
if (isset($_SERVER['HTTP_COOKIE'])) {
    $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
    foreach($cookies as $cookie) {
        $parts = explode('=', $cookie);
        $name = trim($parts[0]);
        setcookie($name, '', time() - 1000);
        setcookie($name, '', time() - 1000, '/');
    }
}

// Destroy session
session_start();
session_destroy();

// Redirect to login
header('Location: login.php');
exit();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Cleaning Cookies...</title>
</head>
<body>
    <p>Clearing cookies and redirecting...</p>
    <script>
        setTimeout(function() {
            window.location.href = 'login.php';
        }, 1000);
    </script>
</body>
</html>
