<?php
/**
 * Sandbox Security Configuration
 * 
 * This file contains security policies and configurations for the app sandbox environment.
 */

class SandboxConfig {
    
    /**
     * Content Security Policy for sandboxed apps
     */
    public static function getCSPHeaders(): array {
        return [
            'Content-Security-Policy' => self::buildCSP(),
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => self::buildPermissionsPolicy()
        ];
    }
    
    /**
     * Build Content Security Policy string
     */
    private static function buildCSP(): string {
        $csp = [
            // Default policy: only allow same origin by default
            'default-src' => "'self'",
            
            // Scripts: allow inline scripts and eval for app functionality
            'script-src' => "'self' 'unsafe-inline' 'unsafe-eval'",
            
            // Styles: allow inline styles for dynamic styling
            'style-src' => "'self' 'unsafe-inline'",
            
            // Images: allow data URLs and HTTPS images
            'img-src' => "'self' data: https:",
            
            // Fonts: allow data URLs for custom fonts
            'font-src' => "'self' data:",
            
            // Connections: allow same origin and HTTPS for APIs
            'connect-src' => "'self' https:",
            
            // Media: allow blob URLs for media files
            'media-src' => "'self' blob:",
            
            // Objects: disallow plugins like Flash
            'object-src' => "'none'",
            
            // Base URI: restrict to same origin
            'base-uri' => "'self'",
            
            // Form actions: only allow same origin
            'form-action' => "'self'",
            
            // Frame ancestors: prevent embedding in other sites
            'frame-ancestors' => "'none'",
            
            // Upgrade insecure requests
            'upgrade-insecure-requests' => ''
        ];
        
        return implode('; ', array_filter($csp));
    }
    
    /**
     * Build Permissions Policy string
     */
    private static function buildPermissionsPolicy(): string {
        $permissions = [
            // Allow commonly used permissions
            'accelerometer' => '()',
            'ambient-light-sensor' => '()',
            'autoplay' => '()',
            'battery' => '()',
            'camera' => '()',
            'display-capture' => '()',
            'document-domain' => '()',
            'encrypted-media' => '()',
            'fullscreen' => '()',
            'geolocation' => '()',
            'gyroscope' => '()',
            'magnetometer' => '()',
            'microphone' => '()',
            'midi' => '()',
            'payment' => '()',
            'picture-in-picture' => '()',
            'screen-wake-lock' => '()',
            'web-share' => '()',
            'xr-spatial-tracking' => '()'
        ];
        
        return implode(', ', array_map(
            fn($key, $value) => "$key=$value",
            array_keys($permissions),
            $permissions
        ));
    }
    
    /**
     * Get iframe sandbox attributes
     */
    public static function getSandboxAttributes(): string {
        return 'allow-scripts allow-same-origin allow-forms allow-popups allow-modals';
    }
    
    /**
     * Get iframe allow attributes
     */
    public static function getAllowAttributes(): string {
        return implode(' ', [
            'accelerometer',
            'ambient-light-sensor',
            'autoplay',
            'battery',
            'camera',
            'display-capture',
            'document-domain',
            'encrypted-media',
            'fullscreen',
            'geolocation',
            'gyroscope',
            'magnetometer',
            'microphone',
            'midi',
            'payment',
            'picture-in-picture',
            'screen-wake-lock',
            'web-share',
            'xr-spatial-tracking'
        ]);
    }
    
    /**
     * Validate app content for security
     */
    public static function validateAppContent(string $content): array {
        $issues = [];
        
        // Check for potentially dangerous patterns
        $dangerousPatterns = [
            '/eval\s*\(/i' => 'Use of eval() function',
            '/document\.write\s*\(/i' => 'Use of document.write()',
            '/innerHTML\s*=/i' => 'Direct innerHTML assignment',
            '/outerHTML\s*=/i' => 'Direct outerHTML assignment',
            '/javascript:/i' => 'JavaScript protocol usage',
            '/data:text\/html/i' => 'Data HTML URLs',
            '/vbscript:/i' => 'VBScript protocol usage'
        ];
        
        foreach ($dangerousPatterns as $pattern => $description) {
            if (preg_match($pattern, $content)) {
                $issues[] = $description;
            }
        }
        
        return $issues;
    }
    
    /**
     * Sanitize app content
     */
    public static function sanitizeAppContent(string $content): string {
        // Remove script sources from external domains
        $content = preg_replace('/<script[^>]*src=["\']?(?!https?:\/\/|\/\/|\/)[^"\']*["\']?[^>]*>/i', '', $content);
        
        // Remove iframe sources from external domains
        $content = preg_replace('/<iframe[^>]*src=["\']?(?!https?:\/\/|\/\/|\/)[^"\']*["\']?[^>]*>/i', '', $content);
        
        // Remove object and embed tags
        $content = preg_replace('/<(object|embed)[^>]*>.*?<\/\1>/is', '', $content);
        
        return $content;
    }
    
    /**
     * Get cache control headers
     */
    public static function getCacheControlHeaders(): array {
        return [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => 'Thu, 01 Jan 1970 00:00:00 GMT'
        ];
    }
    
    /**
     * Log sandbox activity
     */
    public static function logActivity(string $action, int $userId, int $appId, array $context = []): void {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'action' => $action,
            'user_id' => $userId,
            'app_id' => $appId,
            'context' => $context,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        error_log('Sandbox Activity: ' . json_encode($logEntry));
    }
}

?>
