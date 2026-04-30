<?php
/**
 * Base API Class for Bloxer Platform
 * Provides common functionality for all API endpoints
 */

class APIBase {
    protected $auth;
    protected $conn;
    protected $user;
    
    public function __construct() {
        require_once '../controllers/core/mainlogincore.php';
        $this->auth = new AuthCore();
        $this->conn = $this->auth->getConnection();
        $this->user = $this->auth->isLoggedIn() ? $this->auth->getCurrentUser() : null;
    }
    
    /**
     * Initialize API with security headers
     */
    public function init() {
        // Set content type
        header('Content-Type: application/json');
        
        // Send security headers
        $this->sendSecurityHeaders();
        
        // Handle CORS
        $this->handleCORS();
        
        // Rate limiting
        $this->checkRateLimit();
    }
    
    /**
     * Send security headers
     */
    protected function sendSecurityHeaders() {
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
            'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'self';",
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()'
        ];
        
        foreach ($headers as $header => $value) {
            header("$header: $value");
        }
    }
    
    /**
     * Handle CORS
     */
    protected function handleCORS() {
        $allowed_origins = ['http://localhost', 'http://127.0.0.1', 'https://bloxer.com'];
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        if (in_array($origin, $allowed_origins) || in_array('*', $allowed_origins)) {
            header("Access-Control-Allow-Origin: $origin");
        }
        
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, Authorization");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Max-Age: 3600");
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            exit(0);
        }
    }
    
    /**
     * Check rate limiting
     */
    protected function checkRateLimit() {
        require_once '../config/rate_limiter.php';
        $limiter = RateLimiter::getInstance();
        $limiter->enforceLimit('api');
    }
    
    /**
     * Get client IP address
     */
    protected function getClientIP() {
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
     * Generate CSRF token
     */
    protected function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_expires'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_expires'] = time() + 3600; // 1 hour
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate CSRF token
     */
    protected function validateCSRFToken($token) {
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
     * Validate input
     */
    protected function validateInput($input, $type = 'string', $max_length = 1000) {
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
                return strip_tags($input);
            case 'filename':
                return preg_match('/^[a-zA-Z0-9._-]+$/', $input);
            case 'username':
                return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $input);
            default:
                return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        }
    }
    
    /**
     * Send JSON response
     */
    protected function sendJSONResponse($data, $status_code = 200) {
        if (!headers_sent()) {
            http_response_code($status_code);
        }
        
        echo json_encode($data);
        exit();
    }
    
    /**
     * Require authentication
     */
    protected function requireAuth() {
        if (!$this->auth->isLoggedIn()) {
            $this->sendJSONResponse(['success' => false, 'error' => 'Login required'], 401);
        }
    }
    
    /**
     * Require admin access
     */
    protected function requireAdmin() {
        $this->requireAuth();
        
        if (!isset($this->user['user_type']) || $this->user['user_type'] !== 'admin') {
            $this->sendJSONResponse(['success' => false, 'error' => 'Admin access required'], 403);
        }
    }
    
    /**
     * Validate CSRF for POST requests
     */
    protected function validateCSRF() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $csrf_token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            
            if (!$this->validateCSRFToken($csrf_token)) {
                $this->sendJSONResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
            }
        }
    }
    
    /**
     * Check if user owns resource
     */
    protected function checkOwnership($resource_type, $resource_id) {
        $this->requireAuth();
        
        switch ($resource_type) {
            case 'app':
                $stmt = $this->conn->prepare("
                    SELECT p.user_id 
                    FROM apps a 
                    JOIN projects p ON a.project_id = p.id 
                    WHERE a.id = ?
                ");
                $stmt->bind_param("i", $resource_id);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                
                if (!$result || $result['user_id'] != $this->user['id']) {
                    $this->sendJSONResponse(['success' => false, 'error' => 'Access denied'], 403);
                }
                break;
                
            case 'project':
                $stmt = $this->conn->prepare("SELECT user_id FROM projects WHERE id = ?");
                $stmt->bind_param("i", $resource_id);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                
                if (!$result || $result['user_id'] != $this->user['id']) {
                    $this->sendJSONResponse(['success' => false, 'error' => 'Access denied'], 403);
                }
                break;
                
            default:
                $this->sendJSONResponse(['success' => false, 'error' => 'Invalid resource type'], 400);
        }
    }
    
    /**
     * Log security event
     */
    protected function logSecurityEvent($event, $details = '') {
        $log_entry = date('Y-m-d H:i:s') . " [SECURITY] $event";
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
     * Get request method
     */
    protected function getMethod() {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }
    
    /**
     * Get request action
     */
    protected function getAction() {
        return $_GET['action'] ?? $_POST['action'] ?? '';
    }
    
    /**
     * Get pagination parameters
     */
    protected function getPagination($default_limit = 20, $max_limit = 100) {
        $page = max(1, intval($_GET['page'] ?? $_POST['page'] ?? 1));
        $limit = min($max_limit, max(1, intval($_GET['limit'] ?? $_POST['limit'] ?? $default_limit)));
        $offset = ($page - 1) * $limit;
        
        return [
            'page' => $page,
            'limit' => $limit,
            'offset' => $offset
        ];
    }
    
    /**
     * Build pagination response
     */
    protected function buildPaginationResponse($total_items, $page, $limit) {
        return [
            'current_page' => $page,
            'total_pages' => ceil($total_items / $limit),
            'total_items' => $total_items,
            'per_page' => $limit,
            'has_next' => $page < ceil($total_items / $limit),
            'has_prev' => $page > 1
        ];
    }
    
    /**
     * Validate required parameters
     */
    protected function validateRequiredParams($params, $required) {
        foreach ($required as $param) {
            if (!isset($params[$param]) || empty($params[$param])) {
                $this->sendJSONResponse(['success' => false, 'error' => "Parameter '$param' is required"], 400);
            }
        }
    }
    
    /**
     * Sanitize array of inputs
     */
    protected function sanitizeInputs($inputs, $rules = []) {
        $sanitized = [];
        
        foreach ($inputs as $key => $value) {
            $rule = $rules[$key] ?? 'string';
            $max_length = $rules[$key . '_max_length'] ?? 1000;
            
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeInputs($value, $rules);
            } else {
                $sanitized[$key] = $this->validateInput($value, $rule, $max_length);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Check for attack patterns
     */
    protected function detectAttackPatterns($input) {
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
     * Generate slug from string
     */
    protected function generateSlug($string) {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $string));
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }
    
    /**
     * Check if string contains only allowed characters
     */
    protected function isAllowedChars($string, $allowed = 'a-zA-Z0-9-_ ') {
        return preg_match('/^[' . $allowed . ']+$/', $string);
    }
    
    /**
     * Get file extension
     */
    protected function getFileExtension($filename) {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }
    
    /**
     * Validate file type
     */
    protected function validateFileType($filename, $allowed_types = []) {
        $extension = $this->getFileExtension($filename);
        return in_array($extension, $allowed_types);
    }
    
    /**
     * Format date for API response
     */
    protected function formatDate($date) {
        if (!$date) return null;
        return date('Y-m-d H:i:s', strtotime($date));
    }
    
    /**
     * Get database connection
     */
    protected function getDB() {
        return $this->conn;
    }
    
    /**
     * Get current user
     */
    protected function getCurrentUser() {
        return $this->user;
    }
    
    /**
     * Get auth instance
     */
    protected function getAuth() {
        return $this->auth;
    }
}
?>
