<?php
/**
 * Simple Entry Point for Railway Deployment
 */

// Basic error reporting
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Include bootstrap
require_once __DIR__ . '/../bootstrap.php';

// Simple routing
$request_uri = $_SERVER['REQUEST_URI'] ?? '/';

if ($request_uri === '/' || $request_uri === '/index.php') {
    // Load main index
    require_once __DIR__ . '/../index.php';
} elseif (strpos($request_uri, '/api/') === 0) {
    // API routes
    $api_file = __DIR__ . '/../api' . str_replace('/api', '', $request_uri) . '.php';
    if (file_exists($api_file)) {
        require_once $api_file;
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'API endpoint not found']);
    }
} else {
    // Static files or 404
    $file_path = __DIR__ . '/../' . ltrim($request_uri, '/');
    if (file_exists($file_path) && is_file($file_path)) {
        $mime_types = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon'
        ];
        
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        if (isset($mime_types[$ext])) {
            header('Content-Type: ' . $mime_types[$ext]);
            readfile($file_path);
        } else {
            http_response_code(404);
            echo 'File not found';
        }
    } else {
        http_response_code(404);
        echo 'Page not found';
    }
}
