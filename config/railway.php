<?php
/**
 * Railway Configuration
 * Optimized settings for Railway deployment
 */

return [
    // Environment detection
    'is_railway' => function() {
        return getenv('RAILWAY_ENVIRONMENT') === 'production' || 
               getenv('RAILWAY_PUBLIC_DOMAIN') !== false;
    },
    
    // Database configuration for Railway
    'database' => [
        'host' => getenv('RAILWAY_DB_HOST') ?: getenv('DB_HOST') ?: 'localhost',
        'port' => getenv('DB_PORT') ?: '5432',
        'name' => getenv('RAILWAY_DB_NAME') ?: getenv('DB_NAME') ?: 'bloxer_db',
        'user' => getenv('RAILWAY_DB_USER') ?: getenv('DB_USER') ?: 'postgres',
        'password' => getenv('RAILWAY_DB_PASSWORD') ?: getenv('DB_PASS') ?: '',
        'sslmode' => 'require',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci'
    ],
    
    // Performance optimizations
    'performance' => [
        'opcache' => [
            'enable' => true,
            'memory_consumption' => 256,
            'max_accelerated_files' => 4000,
            'revalidate_freq' => 0,
            'validate_timestamps' => false,
            'save_comments' => true,
            'enable_file_override' => false
        ],
        'session' => [
            'save_handler' => 'files',
            'save_path' => '/tmp',
            'gc_maxlifetime' => 1440,
            'cookie_httponly' => true,
            'cookie_secure' => true,
            'cookie_samesite' => 'Strict'
        ],
        'memory' => [
            'limit' => '512M',
            'max_execution_time' => 300,
            'max_input_vars' => 3000,
            'upload_max_filesize' => '64M',
            'post_max_size' => '64M'
        ]
    ],
    
    // Security settings
    'security' => [
        'force_https' => true,
        'hsts' => [
            'max_age' => 31536000,
            'include_subdomains' => true,
            'preload' => true
        ],
        'csp' => [
            'default-src' => "'self'",
            'script-src' => "'self' 'unsafe-inline' https://cdnjs.cloudflare.com",
            'style-src' => "'self' 'unsafe-inline' https://cdnjs.cloudflare.com",
            'img-src' => "'self' data: https:",
            'font-src' => "'self' https://cdnjs.cloudflare.com",
            'connect-src' => "'self'"
        ],
        'headers' => [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin'
        ]
    ],
    
    // Logging configuration
    'logging' => [
        'level' => 'info',
        'file' => '/tmp/php-error.log',
        'max_files' => 5,
        'max_size' => '10M',
        'format' => '[%datetime%] %level_name%: %message% %context% %extra%'
    ],
    
    // Caching configuration
    'cache' => [
        'driver' => 'file',
        'path' => '/tmp/cache',
        'prefix' => 'bloxer_',
        'default_ttl' => 3600,
        'max_size' => '100M'
    ],
    
    // Rate limiting
    'rate_limiting' => [
        'enabled' => true,
        'requests_per_minute' => 60,
        'burst_size' => 10,
        'whitelist' => [
            '127.0.0.1',
            '::1'
        ]
    ],
    
    // Monitoring and metrics
    'monitoring' => [
        'health_check_interval' => 15,
        'metrics_enabled' => true,
        'performance_tracking' => true,
        'error_tracking' => true
    ],
    
    // CDN and assets
    'assets' => [
        'cdn_url' => null,
        'version_busting' => true,
        'minify_css' => true,
        'minify_js' => true,
        'compress_images' => true
    ],
    
    // Email configuration
    'email' => [
        'driver' => 'smtp',
        'host' => getenv('MAIL_HOST') ?: 'localhost',
        'port' => getenv('MAIL_PORT') ?: 587,
        'username' => getenv('MAIL_USERNAME') ?: '',
        'password' => getenv('MAIL_PASSWORD') ?: '',
        'encryption' => getenv('MAIL_ENCRYPTION') ?: 'tls',
        'from_address' => 'noreply@bloxer.app',
        'from_name' => 'Bloxer Platform'
    ]
];

/**
 * Apply Railway-specific configurations
 */
function applyRailwayConfig() {
    $config = include __DIR__ . '/railway.php';
    
    if (!$config['is_railway']()) {
        return;
    }
    
    // Set error reporting
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', $config['logging']['file']);
    
    // Set performance settings
    $perf = $config['performance'];
    ini_set('memory_limit', $perf['memory']['limit']);
    ini_set('max_execution_time', $perf['memory']['max_execution_time']);
    ini_set('max_input_vars', $perf['memory']['max_input_vars']);
    ini_set('upload_max_filesize', $perf['memory']['upload_max_filesize']);
    ini_set('post_max_size', $perf['memory']['post_max_size']);
    
    // Set session settings
    $session = $perf['session'];
    ini_set('session.save_handler', $session['save_handler']);
    ini_set('session.save_path', $session['save_path']);
    ini_set('session.gc_maxlifetime', $session['gc_maxlifetime']);
    ini_set('session.cookie_httponly', $session['cookie_httponly']);
    ini_set('session.cookie_secure', $session['cookie_secure']);
    ini_set('session.cookie_samesite', $session['cookie_samesite']);
    
    // Set security headers
    if ($config['security']['force_https'] && !isset($_SERVER['HTTPS'])) {
        $https_url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        header("Location: $https_url");
        exit;
    }
    
    // Apply security headers
    $headers = $config['security']['headers'];
    foreach ($headers as $name => $value) {
        header("$name: $value");
    }
    
    // Set HSTS
    $hsts = $config['security']['hsts'];
    $hsts_value = "max-age={$hsts['max_age']}";
    if ($hsts['include_subdomains']) $hsts_value .= "; includeSubDomains";
    if ($hsts['preload']) $hsts_value .= "; preload";
    header("Strict-Transport-Security: $hsts_value");
}

// Auto-apply Railway config if we're on Railway
if (function_exists('applyRailwayConfig')) {
    applyRailwayConfig();
}
