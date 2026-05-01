<?php
/**
 * Bootstrap File for Bloxer Platform
 * Application initialization and startup
 */

// Define application constants
define('BLOXER_START', microtime(true));
define('BLOXER_ROOT', dirname(__FILE__));
define('BLOXER_VERSION', '1.0.0');

// Error reporting and debugging
if (file_exists(BLOXER_ROOT . '/.env')) {
    $envContent = file_get_contents(BLOXER_ROOT . '/.env');
    $isDev = strpos($envContent, 'APP_ENV=development') !== false;
    
    if ($isDev) {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        ini_set('log_errors', 1);
        ini_set('error_log', BLOXER_ROOT . '/logs/php_errors.log');
    } else {
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
        ini_set('display_errors', 0);
        ini_set('log_errors', 1);
        ini_set('error_log', BLOXER_ROOT . '/logs/php_errors.log');
    }
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load configuration files
require_once BLOXER_ROOT . '/config/app.php';
require_once BLOXER_ROOT . '/config/database.php';
require_once BLOXER_ROOT . '/config/security.php';
require_once BLOXER_ROOT . '/config/validation.php';
require_once BLOXER_ROOT . '/config/sandbox.php';
require_once BLOXER_ROOT . '/config/middleware.php';
require_once BLOXER_ROOT . '/config/routes.php';

// Load core authentication
require_once BLOXER_ROOT . '/controllers/core/mainlogincore.php';

// Initialize application
$app = AppConfig::getInstance();
$db = DatabaseConfig::getInstance();

// Set timezone
date_default_timezone_set($app->get('app.timezone', 'UTC'));

// Set charset
mb_internal_encoding($app->get('app.charset', 'UTF-8'));

// Initialize security headers
$securityMiddleware = new SecurityHeadersMiddleware();
$securityMiddleware->handle();

// Check maintenance mode
$maintenanceMiddleware = new MaintenanceMiddleware();
$maintenanceMiddleware->handle();

// Create necessary directories
$directories = [
    $app->get('paths.uploads'),
    $app->get('paths.logs'),
    $app->get('paths.cache')
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Load helper functions
require_once BLOXER_ROOT . '/helpers/notification_helper.php';
require_once BLOXER_ROOT . '/helpers/recommendation_engine.php';
require_once BLOXER_ROOT . '/helpers/security_utils.php';
require_once BLOXER_ROOT . '/helpers/missing_functions.php';

// Initialize validation patterns
if (class_exists('ValidationPatterns')) {
    // ValidationPatterns class is ready to use
}

// Initialize sandbox configuration
if (class_exists('SandboxConfig')) {
    // SandboxConfig class is ready to use
}

// Global error handler
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return;
    }
    
    switch ($severity) {
        case E_ERROR: $errorType = 'Error'; break;
        case E_WARNING: $errorType = 'Warning'; break;
        case E_PARSE: $errorType = 'Parse Error'; break;
        case E_NOTICE: $errorType = 'Notice'; break;
        case E_CORE_ERROR: $errorType = 'Core Error'; break;
        case E_CORE_WARNING: $errorType = 'Core Warning'; break;
        case E_COMPILE_ERROR: $errorType = 'Compile Error'; break;
        case E_COMPILE_WARNING: $errorType = 'Compile Warning'; break;
        case E_USER_ERROR: $errorType = 'User Error'; break;
        case E_USER_WARNING: $errorType = 'User Warning'; break;
        case E_USER_NOTICE: $errorType = 'User Notice'; break;
        case E_STRICT: $errorType = 'Strict Notice'; break;
        case E_RECOVERABLE_ERROR: $errorType = 'Recoverable Error'; break;
        case E_DEPRECATED: $errorType = 'Deprecated'; break;
        case E_USER_DEPRECATED: $errorType = 'User Deprecated'; break;
        default: $errorType = 'Unknown Error'; break;
    }
    
    $logMessage = "[$errorType] $message in $file on line $line";
    error_log($logMessage);
    
    $appConfig = AppConfig::getInstance();
    if ($appConfig->isDebug()) {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 10px; margin: 10px; border: 1px solid #f5c6cb; border-radius: 4px;'>";
        echo "<strong>$errorType:</strong> $message<br>";
        echo "<small>in $file on line $line</small>";
        echo "</div>";
    }
});

// Global exception handler
set_exception_handler(function($exception) {
    $logMessage = "Uncaught exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine();
    error_log($logMessage);
    
    $appConfig = AppConfig::getInstance();
    if ($appConfig->isDebug()) {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 10px; margin: 10px; border: 1px solid #f5c6cb; border-radius: 4px;'>";
        echo "<strong>Uncaught Exception:</strong> " . $exception->getMessage() . "<br>";
        echo "<small>in " . $exception->getFile() . " on line " . $exception->getLine() . "</small>";
        echo "<pre>" . $exception->getTraceAsString() . "</pre>";
        echo "</div>";
    } else {
        include 'controllers/errors/500.php';
    }
});

// Performance monitoring
register_shutdown_function(function() {
    $executionTime = microtime(true) - BLOXER_START;
    $memoryUsage = memory_get_peak_usage(true);
    
    $appConfig = AppConfig::getInstance();
    if ($appConfig->isDebug()) {
        echo "<!-- Execution time: " . number_format($executionTime, 4) . " seconds -->";
        echo "<!-- Memory usage: " . formatBytes($memoryUsage) . " -->";
    }
    
    // Log performance data
    if ($executionTime > 2.0) { // Log slow requests
        $logData = [
            'time' => date('Y-m-d H:i:s'),
            'url' => $_SERVER['REQUEST_URI'],
            'method' => $_SERVER['REQUEST_METHOD'],
            'execution_time' => $executionTime,
            'memory_usage' => $memoryUsage,
            'user_id' => getCurrentUserId() ?? 'guest'
        ];
        
        $logFile = $appConfig->getLogsPath() . '/performance.log';
        file_put_contents($logFile, json_encode($logData) . "\n", FILE_APPEND | LOCK_EX);
    }
});

function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

// Auto-load helper functions
spl_autoload_register(function($class) {
    $file = BLOXER_ROOT . '/helpers/' . strtolower($class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Initialize application services
function initializeApp() {
    global $app, $db;
    
    // Check database connection
    try {
        $connection = $db->getConnection();
        if (!$connection) {
            throw new Exception("Database connection failed");
        }
    } catch (Exception $e) {
        if ($app->isDebug()) {
            die("Database connection failed: " . $e->getMessage());
        } else {
            include 'controllers/errors/503.php';
            exit;
        }
    }
    
    // Initialize user session
    if (isLoggedIn()) {
        try {
            $auth = new AuthCore();
            $user = $auth->getCurrentUser();
            if (!$user) {
                // Clear invalid session
                session_destroy();
                SecurityUtils::safeRedirect('controllers/auth/login.php', 302, 'Session expired');
            }
        } catch (Exception $e) {
            error_log("Session validation error: " . $e->getMessage());
        }
    }
    
    return true;
}

// Initialize the application
initializeApp();
?>
