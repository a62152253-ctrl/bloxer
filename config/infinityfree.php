<?php
/**
 * InfinityFree Configuration
 * Optimized settings for shared hosting
 */

// Database configuration for InfinityFree
define('DB_HOST', 'sql###.epizy.com');  // Zmień na swoje
define('DB_USER', 'epiz_######');       // Zmień na swoje  
define('DB_PASS', 'password');           // Zmień na swoje
define('DB_NAME', 'epiz_######_bloxer'); // Zmień na swoje

// App configuration
define('APP_ENV', 'production');
define('APP_DEBUG', false);
define('APP_URL', 'https://yourname.infinityfree.app'); // Zmień na swoje

// Security
define('ENCRYPTION_KEY', 'your-secret-key-here'); // Zmień na swoje
define('SESSION_SECURE', true);
define('SESSION_HTTPONLY', true);

// File paths
define('UPLOADS_PATH', __DIR__ . '/../uploads/');
define('LOGS_PATH', __DIR__ . '/../logs/');

// Email (InfinityFree doesn't have SMTP, use mail() function)
define('MAIL_FROM', 'noreply@yourname.infinityfree.app');
define('MAIL_FROM_NAME', 'Bloxer Platform');

// Performance settings for shared hosting
ini_set('memory_limit', '128M');
ini_set('max_execution_time', 30);
ini_set('upload_max_filesize', '32M');
ini_set('post_max_size', '32M');

// Error handling
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Apply settings
if (!file_exists(UPLOADS_PATH)) {
    mkdir(UPLOADS_PATH, 0755, true);
}
if (!file_exists(LOGS_PATH)) {
    mkdir(LOGS_PATH, 0755, true);
}

?>
