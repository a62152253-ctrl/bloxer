<?php
/**
 * Enhanced Security Utils for Bloxer Platform
 */

class SecurityUtils {
    private static $csrf_token_length = 32;
    private static $session_timeout = 3600; // 1 hour
    private static $rate_limit_window = 300; // 5 minutes
    private static $rate_limit_max_requests = 100;
    
    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token']) || !self::validateCSRFToken($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(self::$csrf_token_length));
            $_SESSION['csrf_token_expires'] = time() + self::$session_timeout;
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate CSRF token
     */
    public static function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_expires'])) {
            return false;
        }
        
        if (time() > $_SESSION['csrf_token_expires']) {
            unset($_SESSION['csrf_token']);
            unset($_SESSION['csrf_token_expires']);
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Get CSRF token for forms
     */
    public static function getCSRFToken() {
        return self::generateCSRFToken();
    }
    
    /**
     * Validate input against patterns
     */
    public static function validateInput($input, $type = 'string', $max_length = 1000) {
        if ($input === null) {
            return null;
        }
        
        $input = trim($input);
        
        // Length validation
        if (strlen($input) > $max_length) {
            return false;
        }
        
        // Type-specific validation
        switch ($type) {
            case 'email':
                return filter_var($input, FILTER_VALIDATE_EMAIL);
            case 'url':
                return filter_var($input, FILTER_VALIDATE_URL);
            case 'int':
                return filter_var($input, FILTER_VALIDATE_INT);
            case 'float':
                return filter_var($input, FILTER_VALIDATE_FLOAT);
            case 'boolean':
                return filter_var($input, FILTER_VALIDATE_BOOLEAN);
            case 'alpha':
                return preg_match('/^[a-zA-Z]+$/', $input);
            case 'alphanumeric':
                return preg_match('/^[a-zA-Z0-9]+$/', $input);
            case 'slug':
                return preg_match('/^[a-z0-9-]+$/', $input);
            case 'json':
                json_decode($input);
                return json_last_error() === JSON_ERROR_NONE;
            case 'html':
                // Basic HTML sanitization
                return strip_tags($input);
            case 'filename':
                return preg_match('/^[a-zA-Z0-9._-]+$/', $input);
            case 'username':
                return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $input);
            default:
                // Sanitize string for XSS
                return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        }
    }
    
    /**
     * Sanitize input array
     */
    public static function sanitizeInputArray($data, $rules = []) {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            $rule = $rules[$key] ?? 'string';
            $max_length = $rules[$key . '_max_length'] ?? 1000;
            
            if (is_array($value)) {
                $sanitized[$key] = self::sanitizeInputArray($value, $rules);
            } else {
                $sanitized[$key] = self::validateInput($value, $rule, $max_length);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Rate limiting check
     */
    public static function checkRateLimit($identifier, $max_requests = null, $window = null) {
        $max_requests = $max_requests ?? self::$rate_limit_max_requests;
        $window = $window ?? self::$rate_limit_window;
        
        if (!isset($_SESSION['rate_limit'][$identifier])) {
            $_SESSION['rate_limit'][$identifier] = [
                'requests' => [],
                'window_start' => time()
            ];
        }
        
        $rate_data = &$_SESSION['rate_limit'][$identifier];
        $current_time = time();
        
        // Reset window if expired
        if ($current_time - $rate_data['window_start'] > $window) {
            $rate_data['requests'] = [];
            $rate_data['window_start'] = $current_time;
        }
        
        // Add current request
        $rate_data['requests'][] = $current_time;
        
        // Check limit
        if (count($rate_data['requests']) > $max_requests) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get client IP address
     */
    public static function getClientIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            return $_SERVER['HTTP_X_REAL_IP'];
        }
        return $_SERVER['REMOTE_ADDR'];
    }
    
    /**
     * Validate file upload
     */
    public static function validateFileUpload($file, $allowed_types = [], $max_size = 10485760) {
        if (!isset($file['error']) || is_array($file['error'])) {
            return false;
        }
        
        // Check upload error
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }
        
        // Check file size
        if ($file['size'] > $max_size) {
            return false;
        }
        
        // Check file type
        if (!empty($allowed_types) && !in_array(strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)), $allowed_types)) {
            return false;
        }
        
        // Check for dangerous file names
        $filename = basename($file['name']);
        if (preg_match('/\.(php|phtml|phtml|php3|php4|php5|php6|php7|php8|phar|sh|bat|cmd|exe|com|scr|vbs|js|jar|app|deb|rpm|dmg|pkg|zip|tar|gz)$/i', $filename)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Generate secure random string
     */
    public static function generateRandomString($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Hash password securely
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    /**
     * Verify password
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Escape SQL
     */
    public static function escapeSQL($conn, $string) {
        return mysqli_real_escape_string($conn, $string);
    }
    
    /**
     * Generate secure headers
     */
    public static function getSecureHeaders() {
        return [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
            'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'self';",
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()'
        ];
    }
    
    /**
     * Send secure JSON response
     */
    public static function sendJSONResponse($data, $status_code = 200) {
        if (!headers_sent()) {
            http_response_code($status_code);
            header('Content-Type: application/json');
            header('Cache-Control: no-cache, must-revalidate');
            
            // Add security headers
            foreach (self::getSecureHeaders() as $header => $value) {
                header("$header: $value");
            }
        }
        
        echo json_encode($data);
        exit();
    }
    
    /**
     * Validate session
     */
    public static function validateSession() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        
        // Check session expiration
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > self::$session_timeout) {
            session_destroy();
            return false;
        }
        
        // Update last activity
        $_SESSION['last_activity'] = time();
        
        return true;
    }
    
    /**
     * Check if request is AJAX
     */
    public static function isAJAXRequest() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Get request method
     */
    public static function getRequestMethod() {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }
    
    /**
     * Validate request method
     */
    public static function validateRequestMethod($expected_method) {
        return self::getRequestMethod() === $expected_method;
    }
    
    /**
     * Sanitize URL for output
     */
    public static function sanitizeURL($url) {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
    
    /**
     * Validate JSON input
     */
    public static function validateJSON($json_string) {
        $data = json_decode($json_string, true);
        return $data !== null && json_last_error() === JSON_ERROR_NONE;
    }
    
    /**
     * Log security event
     */
    public static function logSecurityEvent($event, $details = '', $level = 'warning') {
        $log_entry = date('Y-m-d H:i:s') . " [$level] $event";
        if (!empty($details)) {
            $log_entry .= " - $details";
        }
        $log_entry .= "\n";
        
        error_log($log_entry);
        
        // Also log to security log file if exists
        $security_log = __DIR__ . '/../logs/security.log';
        if (file_exists($security_log) && is_writable($security_log)) {
            file_put_contents($security_log, $log_entry, FILE_APPEND | LOCK_EX);
        }
    }
    
    /**
     * Check for common attack patterns
     */
    public static function detectAttackPatterns($input) {
        $patterns = [
            '/<script\b[^<]*>(.*?<\/script>)/is',
            '/<iframe\b[^<]*>(.*?<\/iframe>)/is',
            '/<object\b[^<]*>(.*?<\/object>)/is',
            '/<embed\b[^<]*>(.*?<\/embed>)/is',
            '/<link\b[^<]*>/is',
            '/<meta\b[^<]*>/is',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload\s*=/i',
            '/onerror\s*=/i',
            '/onclick\s*=/i',
            '/eval\s*\(/i',
            '/document\./i',
            '/window\./i',
            '/alert\s*\(/i',
            '/confirm\s*\(/i',
            '/prompt\s*\(/i',
            '/settimeout\s*\(/i',
            '/setinterval\s*\(/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Clean input for SQL injection
     */
    public static function cleanInput($input) {
        if (is_array($input)) {
            return array_map([self::class, 'cleanInput'], $input);
        }
        
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate and sanitize request data
     */
    public static function validateRequest($rules = []) {
        $method = self::getRequestMethod();
        
        // Validate CSRF for POST requests
        if ($method === 'POST' && self::isAJAXRequest()) {
            $csrf_token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            if (!self::validateCSRFToken($csrf_token)) {
                self::logSecurityEvent('CSRF token validation failed', 'IP: ' . self::getClientIP());
                self::sendJSONResponse(['error' => 'Invalid CSRF token'], 403);
            }
        }
        
        // Validate request method
        if (isset($rules['method']) && !self::validateRequestMethod($rules['method'])) {
            self::logSecurityEvent('Invalid request method', 'Method: ' . $method . ', IP: ' . self::getClientIP());
            self::sendJSONResponse(['error' => 'Method not allowed'], 405);
        }
        
        // Check rate limiting
        if (isset($rules['rate_limit'])) {
            $identifier = self::getClientIP();
            if (!self::checkRateLimit($identifier, $rules['rate_limit']['max'], $rules['rate_limit']['window'])) {
                self::logSecurityEvent('Rate limit exceeded', 'IP: ' . $identifier);
                self::sendJSONResponse(['error' => 'Too many requests'], 429);
            }
        }
        
        // Sanitize input based on rules
        $sanitized = [];
        $input_source = ($method === 'POST') ? $_POST : $_GET;
        
        foreach ($rules as $key => $rule) {
            $value = $input_source[$key] ?? null;
            if ($value !== null) {
                $sanitized[$key] = self::validateInput($value, $rule['type'] ?? 'string', $rule['max_length'] ?? 1000);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Send CORS headers
     */
    public static function sendCORSHeaders($origin = '*') {
        if (!headers_sent()) {
            header("Access-Control-Allow-Origin: $origin");
            header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
            header("Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, Authorization");
            header("Access-Control-Allow-Credentials: true");
            header("Access-Control-Max-Age: 3600");
        }
    }
    
    /**
     * Handle preflight requests
     */
    public static function handlePreflightRequest() {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            self::sendCORSHeaders();
            exit(0);
        }
    }
    
    /**
     * Generate secure session ID
     */
    public static function generateSessionID() {
        return bin2hex(random_bytes(16));
    }
    
    /**
     * Regenerate session ID
     */
    public static function regenerateSessionID() {
        session_regenerate_id(true);
        $_SESSION['csrf_token'] = self::generateCSRFToken();
        $_SESSION['csrf_token_expires'] = time() + self::$session_timeout;
    }
    
    /**
     * Check if user is admin
     */
    public static function isAdmin() {
        return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
    }
    
    /**
     * Check if user is developer
     */
    public static function isDeveloper() {
        return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'developer';
    }
    
    /**
     * Check if user can access resource
     */
    public static function canAccess($resource, $owner_id = null) {
        switch ($resource) {
            case 'admin':
                return self::isAdmin();
            case 'developer':
                return self::isDeveloper();
            case 'user_profile':
                return isset($_SESSION['user_id']) && $_SESSION['user_id'] == $owner_id;
            case 'own_content':
                return isset($_SESSION['user_id']) && $_SESSION['user_id'] == $owner_id;
            default:
                return true; // Allow by default
        }
    }
    
    /**
     * Escape HTML for output
     */
    public static function escape($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate email format
     */
    public static function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate URL format
     */
    public static function isValidURL($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * Get user agent
     */
    public static function getUserAgent() {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    }
    
    /**
     * Check if request is from mobile device
     */
    public static function isMobile() {
        $user_agent = self::getUserAgent();
        return preg_match('/Mobile|Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i', $user_agent);
    }
    
    /**
     * Get browser info
     */
    public static function getBrowserInfo() {
        $user_agent = self::getUserAgent();
        
        $browsers = [
            'Chrome' => '/Chrome\/([0-9.]+)/',
            'Firefox' => '/Firefox\/([0-9.]+)/',
            'Safari' => '/Version\/([0-9.]+)/',
            'Edge' => '/Edge\/([0-9.]+)/',
            'Opera' => '/Opera\/([0-9.]+)/',
            'MSIE' => '/MSIE ([0-9.]+)/'
        ];
        
        foreach ($browsers as $browser => $pattern) {
            if (preg_match($pattern, $user_agent, $matches)) {
                return [
                    'name' => $browser,
                    'version' => $matches[1]
                ];
            }
        }
        
        return ['name' => 'Unknown', 'version' => 'Unknown'];
    }
    
    /**
     * Log request for debugging
     */
    public static function logRequest($endpoint, $data = []) {
        $log_entry = date('Y-m-d H:i:s') . " " . $_SERVER['REQUEST_METHOD'] . " $endpoint";
        
        if (!empty($data)) {
            $log_entry .= " - " . json_encode($data);
        }
        
        $log_entry .= "\n";
        
        error_log($log_entry);
    }
    
    /**
     * Safe redirect with proper headers and logging
     */
    public static function safeRedirect($url, $statusCode = 302, $logMessage = '') {
        if ($logMessage) {
            self::logSecurityEvent('Redirect', "Redirecting to: $url - $logMessage");
        }
        
        if (!headers_sent()) {
            http_response_code($statusCode);
            header("Location: $url");
            exit;
        } else {
            self::sendJSONResponse(['error' => "Headers already sent. Cannot redirect to $url."], 500);
        }
    }
    
    /**
     * Safe exit with proper logging and headers
     */
    public static function safeExit($message = '', $statusCode = 200, $logLevel = 'info') {
        if (!headers_sent()) {
            http_response_code($statusCode);
        }
        
        if ($message) {
            self::logSecurityEvent('Exit', $message, $logLevel);
            echo $message;
        }
        
        exit;
    }
}
