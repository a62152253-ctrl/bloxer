<?php
/**
 * Railway Health Check Endpoint
 * This endpoint is used by Railway to monitor application health
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

try {
    // Check database connection
    $database_healthy = false;
    $database_message = 'Not connected';
    
    try {
        require_once __DIR__ . '/../bootstrap.php';
        $auth = new AuthCore();
        $conn = $auth->getConnection();
        
        if ($conn && $conn->ping()) {
            $database_healthy = true;
            $database_message = 'Connected';
        }
    } catch (Exception $e) {
        $database_message = 'Connection failed: ' . $e->getMessage();
    }
    
    // Check file system
    $filesystem_healthy = is_writable(__DIR__ . '/../uploads/');
    $filesystem_message = $filesystem_healthy ? 'Writable' : 'Not writable';
    
    // Check memory usage
    $memory_usage = memory_get_usage(true);
    $memory_limit = ini_get('memory_limit');
    $memory_healthy = $memory_usage < 100 * 1024 * 1024; // Less than 100MB
    $memory_message = round($memory_usage / 1024 / 1024, 2) . 'MB used';
    
    // Check session
    $session_healthy = session_status() === PHP_SESSION_ACTIVE || session_start();
    $session_message = session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive';
    
    // Overall health
    $overall_healthy = $database_healthy && $filesystem_healthy && $memory_healthy && $session_healthy;
    
    $response = [
        'status' => $overall_healthy ? 'healthy' : 'unhealthy',
        'timestamp' => date('c'),
        'version' => '1.0.0',
        'environment' => [
            'name' => $_ENV['RAILWAY_ENVIRONMENT'] ?? 'unknown',
            'php_version' => PHP_VERSION,
            'memory_limit' => $memory_limit,
            'max_execution_time' => ini_get('max_execution_time')
        ],
        'checks' => [
            'database' => [
                'healthy' => $database_healthy,
                'message' => $database_message
            ],
            'filesystem' => [
                'healthy' => $filesystem_healthy,
                'message' => $filesystem_message
            ],
            'memory' => [
                'healthy' => $memory_healthy,
                'message' => $memory_message
            ],
            'session' => [
                'healthy' => $session_healthy,
                'message' => $session_message
            ]
        ],
        'metrics' => [
            'uptime' => time() - filemtime(__FILE__),
            'memory_usage' => $memory_usage,
            'peak_memory' => memory_get_peak_usage(true)
        ]
    ];
    
    // Set HTTP status code
    http_response_code($overall_healthy ? 200 : 503);
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(503);
    echo json_encode([
        'status' => 'unhealthy',
        'timestamp' => date('c'),
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
