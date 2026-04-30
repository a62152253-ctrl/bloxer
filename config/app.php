<?php
/**
 * Application Configuration for Bloxer Platform
 * Centralized app settings and configuration management
 */

class AppConfig {
    private static $instance = null;
    private $config;
    
    private function __construct() {
        $this->loadConfig();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function loadConfig() {
        // Load environment variables
        $env_file = __DIR__ . '/../.env';
        if (file_exists($env_file)) {
            $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '#') === 0) continue;
                if (strpos($line, '=') === false) continue;
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
        
        $this->config = [
            'app' => [
                'name' => 'Bloxer',
                'url' => $_ENV['APP_URL'] ?? 'http://localhost/bloxer',
                'env' => $_ENV['APP_ENV'] ?? 'development',
                'debug' => filter_var($_ENV['ENABLE_DEBUG'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
                'timezone' => 'UTC',
                'charset' => 'UTF-8'
            ],
            
            'database' => [
                'host' => $_ENV['DB_HOST'] ?? 'localhost',
                'name' => $_ENV['DB_NAME'] ?? 'bloxer_db',
                'username' => $_ENV['DB_USER'] ?? 'root',
                'password' => $_ENV['DB_PASS'] ?? '',
                'charset' => 'utf8mb4'
            ],
            
            'security' => [
                'csrf_protection' => filter_var($_ENV['CSRF_PROTECTION'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
                'rate_limit_enabled' => filter_var($_ENV['RATE_LIMIT_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
                'session_lifetime' => intval($_ENV['SESSION_LIFETIME'] ?? 3600),
                'password_min_length' => 8,
                'max_login_attempts' => 5,
                'login_lockout_time' => 900
            ],
            
            'upload' => [
                'max_file_size' => intval($_ENV['MAX_FILE_SIZE'] ?? 5242880),
                'allowed_types' => explode(',', $_ENV['ALLOWED_FILE_TYPES'] ?? 'image/jpeg,image/png,image/gif,text/html,text/css,application/javascript'),
                'upload_path' => $_ENV['UPLOAD_PATH'] ?? 'uploads/',
                'thumbnail_size' => [300, 200],
                'preview_size' => [800, 600]
            ],
            
            'email' => [
                'host' => $_ENV['MAIL_HOST'] ?? '',
                'port' => intval($_ENV['MAIL_PORT'] ?? 587),
                'username' => $_ENV['MAIL_USERNAME'] ?? '',
                'password' => $_ENV['MAIL_PASSWORD'] ?? '',
                'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
                'from_email' => 'noreply@bloxer.com',
                'from_name' => 'Bloxer Platform'
            ],
            
            'websocket' => [
                'host' => $_ENV['WEBSOCKET_HOST'] ?? 'localhost',
                'port' => intval($_ENV['WEBSOCKET_PORT'] ?? 8080),
                'enabled' => true
            ],
            
            'paths' => [
                'root' => dirname(__DIR__, 2),
                'app' => dirname(__DIR__, 2) . '/controllers',
                'views' => dirname(__DIR__, 2) . '/views',
                'assets' => dirname(__DIR__, 2) . '/assets',
                'uploads' => dirname(__DIR__, 2) . '/uploads',
                'logs' => dirname(__DIR__, 2) . '/logs',
                'cache' => dirname(__DIR__, 2) . '/cache'
            ],
            
            'features' => [
                'marketplace' => true,
                'chat' => true,
                'notifications' => true,
                'analytics' => true,
                'recommendations' => true,
                'version_control' => true,
                'sandbox' => true
            ],
            
            'limits' => [
                'max_projects_per_user' => 50,
                'max_apps_per_project' => 10,
                'max_file_size_per_project' => 104857600, // 100MB
                'max_upload_files_per_session' => 20
            ]
        ];
    }
    
    public function get($key, $default = null) {
        $keys = explode('.', $key);
        $value = $this->config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
    
    public function set($key, $value) {
        $keys = explode('.', $key);
        $config = &$this->config;
        
        foreach ($keys as $k) {
            if (!isset($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }
        
        $config = $value;
    }
    
    public function getAll() {
        return $this->config;
    }
    
    public function isDevelopment() {
        return $this->get('app.env') === 'development';
    }
    
    public function isProduction() {
        return $this->get('app.env') === 'production';
    }
    
    public function isDebug() {
        return $this->get('app.debug', false);
    }
    
    public function getAppUrl() {
        return $this->get('app.url');
    }
    
    public function getUploadPath() {
        return $this->get('paths.root') . '/' . $this->get('upload.upload_path');
    }
    
    public function getLogsPath() {
        return $this->get('paths.logs');
    }
    
    public function getMaxFileSize() {
        return $this->get('upload.max_file_size');
    }
    
    public function getAllowedFileTypes() {
        return $this->get('upload.allowed_types');
    }
    
    public function isFeatureEnabled($feature) {
        return $this->get("features.$feature", false);
    }
    
    public function getLimit($limit) {
        return $this->get("limits.$limit");
    }
    
    public function getWebSocketConfig() {
        return $this->get('websocket');
    }
    
    public function getEmailConfig() {
        return $this->get('email');
    }
    
    public function getSecurityConfig() {
        return $this->get('security');
    }
    
    public function getDatabaseConfig() {
        return $this->get('database');
    }
    
    public function getPath($path) {
        return $this->get("paths.$path");
    }
}

// Helper functions for backward compatibility
function config($key, $default = null) {
    return AppConfig::getInstance()->get($key, $default);
}

function app_url($path = '') {
    $base = AppConfig::getInstance()->getAppUrl();
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

function asset_url($path) {
    return app_url('assets/' . ltrim($path, '/'));
}

function upload_url($path) {
    return app_url('uploads/' . ltrim($path, '/'));
}
?>
