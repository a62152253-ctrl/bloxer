<?php
/**
 * Middleware Configuration for Bloxer Platform
 * Security and request filtering middleware
 */

abstract class Middleware {
    abstract public function handle();
}

class AuthMiddleware extends Middleware {
    public function handle() {
        if (!isLoggedIn()) {
            SecurityUtils::safeRedirect('controllers/auth/login.php', 302, 'Authentication required');
            return false;
        }
        return true;
    }
}

class DeveloperMiddleware extends Middleware {
    public function handle() {
        if (!isLoggedIn()) {
            SecurityUtils::safeRedirect('controllers/auth/login.php', 302, 'Authentication required');
            return false;
        }
        
        if (!isDeveloper()) {
            SecurityUtils::safeRedirect('controllers/marketplace/marketplace.php', 403, 'Developer access required');
            return false;
        }
        
        return true;
    }
}

class GuestMiddleware extends Middleware {
    public function handle() {
        if (isLoggedIn()) {
            $redirect = isDeveloper() ? 'controllers/core/dashboard.php' : 'controllers/marketplace/marketplace.php';
            SecurityUtils::safeRedirect($redirect, 302, 'Already logged in');
            return false;
        }
        return true;
    }
}

class CsrfMiddleware extends Middleware {
    public function handle() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? '';
            if (!SecurityUtils::validateCsrfToken($token)) {
                SecurityUtils::safeExit('CSRF token validation failed', 403, 'error');
                return false;
            }
        }
        return true;
    }
}

class RateLimitMiddleware extends Middleware {
    private $maxRequests = 100;
    private $timeWindow = 3600; // 1 hour
    
    public function handle() {
        $ip = $_SERVER['REMOTE_ADDR'];
        $key = "rate_limit:$ip";
        
        $current = $this->getCurrentRequests($key);
        
        if ($current >= $this->maxRequests) {
            SecurityUtils::safeExit('Rate limit exceeded', 429, 'warning');
            return false;
        }
        
        $this->incrementRequests($key);
        return true;
    }
    
    private function getCurrentRequests($key) {
        $data = $this->getRateLimitData();
        return $data[$key] ?? 0;
    }
    
    private function incrementRequests($key) {
        $data = $this->getRateLimitData();
        $data[$key] = ($data[$key] ?? 0) + 1;
        $this->saveRateLimitData($data);
    }
    
    private function getRateLimitData() {
        $file = AppConfig::getInstance()->getLogsPath() . '/rate_limit.json';
        if (!file_exists($file)) {
            return [];
        }
        
        $content = file_get_contents($file);
        $data = json_decode($content, true);
        
        // Clean old entries
        $now = time();
        $data = array_filter($data, function($timestamp) use ($now) {
            return ($now - $timestamp) < $this->timeWindow;
        });
        
        return $data;
    }
    
    private function saveRateLimitData($data) {
        $file = AppConfig::getInstance()->getLogsPath() . '/rate_limit.json';
        $now = time();
        
        // Add current timestamp to all entries
        foreach ($data as $key => &$value) {
            if (is_numeric($value)) {
                $value = $now;
            }
        }
        
        file_put_contents($file, json_encode($data));
    }
}

class SecurityHeadersMiddleware extends Middleware {
    public function handle() {
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin'
        ];
        
        if (AppConfig::getInstance()->isProduction()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }
        
        foreach ($headers as $name => $value) {
            header("$name: $value");
        }
        
        return true;
    }
}

class MaintenanceMiddleware extends Middleware {
    public function handle() {
        $maintenanceFile = AppConfig::getInstance()->getPath('root') . '/.maintenance';
        
        if (file_exists($maintenanceFile)) {
            $allowedIps = ['127.0.0.1', '::1']; // Allow localhost
            $userIp = $_SERVER['REMOTE_ADDR'];
            
            if (!in_array($userIp, $allowedIps)) {
                http_response_code(503);
                include 'controllers/errors/503.php';
                return false;
            }
        }
        
        return true;
    }
}

class ValidationMiddleware extends Middleware {
    private $rules = [];
    
    public function __construct($rules = []) {
        $this->rules = $rules;
    }
    
    public function handle() {
        foreach ($this->rules as $field => $rule) {
            $value = $_POST[$field] ?? $_GET[$field] ?? null;
            
            if (!$this->validateField($value, $rule)) {
                SecurityUtils::safeExit("Validation failed for field: $field", 400, 'warning');
                return false;
            }
        }
        
        return true;
    }
    
    private function validateField($value, $rule) {
        if (strpos($rule, 'required') !== false && empty($value)) {
            return false;
        }
        
        if (strpos($rule, 'email') !== false && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        if (preg_match('/min:(\d+)/', $rule, $matches) && strlen($value) < $matches[1]) {
            return false;
        }
        
        if (preg_match('/max:(\d+)/', $rule, $matches) && strlen($value) > $matches[1]) {
            return false;
        }
        
        return true;
    }
}

// Helper functions
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isDeveloper() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'developer';
}

function getCurrentUser() {
    return $_SESSION['user'] ?? null;
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}
?>
