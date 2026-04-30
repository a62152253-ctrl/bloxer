<?php
/**
 * Rate Limiter for Bloxer Platform
 * Simple file-based rate limiting for API endpoints
 */

class RateLimiter {
    private static $instance = null;
    private $config;
    private $storagePath;
    
    private function __construct() {
        $this->config = [
            'api' => [
                'requests_per_minute' => 60,
                'requests_per_hour' => 1000,
                'burst_limit' => 10
            ],
            'auth' => [
                'requests_per_minute' => 10,
                'requests_per_hour' => 100,
                'burst_limit' => 3
            ],
            'upload' => [
                'requests_per_minute' => 20,
                'requests_per_hour' => 200,
                'burst_limit' => 5
            ]
        ];
        
        $this->storagePath = dirname(__DIR__) . '/cache/rate_limits';
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function checkLimit($identifier, $type = 'api') {
        $limits = $this->config[$type] ?? $this->config['api'];
        $now = time();
        $window = $now - ($now % 60); // Current minute window
        
        $file = $this->storagePath . '/' . md5($identifier) . '.json';
        $data = $this->loadRateData($file);
        
        // Clean old entries
        $this->cleanupOldData($data, $now);
        
        // Check minute limit
        $minuteKey = $window;
        if (!isset($data[$minuteKey])) {
            $data[$minuteKey] = 0;
        }
        
        // Check burst limit (requests in quick succession)
        $recentRequests = $this->getRecentRequests($data, $now, 5); // Last 5 seconds
        if ($recentRequests >= $limits['burst_limit']) {
            $this->saveRateData($file, $data);
            return [
                'allowed' => false,
                'limit' => $limits['burst_limit'],
                'remaining' => 0,
                'reset_time' => $now + 5,
                'reason' => 'burst_limit_exceeded'
            ];
        }
        
        // Check minute limit
        $minuteRequests = $this->getRequestsInWindow($data, $window, 60);
        if ($minuteRequests >= $limits['requests_per_minute']) {
            $this->saveRateData($file, $data);
            return [
                'allowed' => false,
                'limit' => $limits['requests_per_minute'],
                'remaining' => 0,
                'reset_time' => $window + 60,
                'reason' => 'minute_limit_exceeded'
            ];
        }
        
        // Check hour limit
        $hourWindow = $now - ($now % 3600);
        $hourRequests = $this->getRequestsInWindow($data, $hourWindow, 3600);
        if ($hourRequests >= $limits['requests_per_hour']) {
            $this->saveRateData($file, $data);
            return [
                'allowed' => false,
                'limit' => $limits['requests_per_hour'],
                'remaining' => 0,
                'reset_time' => $hourWindow + 3600,
                'reason' => 'hour_limit_exceeded'
            ];
        }
        
        // Allow request
        $data[$minuteKey]++;
        $this->saveRateData($file, $data);
        
        return [
            'allowed' => true,
            'limit' => $limits['requests_per_minute'],
            'remaining' => $limits['requests_per_minute'] - $minuteRequests - 1,
            'reset_time' => $window + 60,
            'reason' => 'allowed'
        ];
    }
    
    private function loadRateData($file) {
        if (!file_exists($file)) {
            return [];
        }
        
        $content = file_get_contents($file);
        if ($content === false) {
            return [];
        }
        
        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }
    
    private function saveRateData($file, $data) {
        $json = json_encode($data);
        file_put_contents($file, $json, LOCK_EX);
    }
    
    private function cleanupOldData(&$data, $now) {
        // Remove entries older than 1 hour
        $cutoff = $now - 3600;
        foreach ($data as $timestamp => $count) {
            if ($timestamp < $cutoff) {
                unset($data[$timestamp]);
            }
        }
    }
    
    private function getRequestsInWindow($data, $windowStart, $windowSize) {
        $total = 0;
        for ($t = $windowStart; $t < $windowStart + $windowSize; $t += 60) {
            $total += $data[$t] ?? 0;
        }
        return $total;
    }
    
    private function getRecentRequests($data, $now, $seconds) {
        $total = 0;
        for ($t = $now - $seconds; $t <= $now; $t++) {
            $window = $t - ($t % 60);
            $total += $data[$window] ?? 0;
        }
        return $total;
    }
    
    public function getClientIdentifier() {
        // Use IP + User Agent if not logged in, or user ID if logged in
        if (isset($_SESSION['user_id'])) {
            return 'user_' . $_SESSION['user_id'];
        }
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        return 'ip_' . md5($ip . $ua);
    }
    
    public function enforceLimit($type = 'api') {
        $identifier = $this->getClientIdentifier();
        $result = $this->checkLimit($identifier, $type);
        
        if (!$result['allowed']) {
            header('HTTP/1.1 429 Too Many Requests');
            header('Retry-After: ' . ($result['reset_time'] - time()));
            header('Content-Type: application/json');
            
            echo json_encode([
                'error' => 'Rate limit exceeded',
                'message' => 'Too many requests. Please try again later.',
                'limit' => $result['limit'],
                'reset_time' => $result['reset_time'],
                'reason' => $result['reason']
            ]);
            
            SecurityUtils::safeExit('', 429, 'warning');
        }
        
        return $result;
    }
}

// Helper function for easy usage
function enforceRateLimit($type = 'api') {
    $limiter = RateLimiter::getInstance();
    return $limiter->enforceLimit($type);
}
?>
