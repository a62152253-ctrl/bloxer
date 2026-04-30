<?php
/**
 * Security Testing Tool for Bloxer
 * Performs basic security checks and vulnerability scanning
 */

require_once '../controllers/core/mainlogincore.php';
require_once '../../config/security.php';

$auth = new AuthCore();

// Only allow developers
if (!$auth->isLoggedIn() || !$auth->isDeveloper()) {
    SecurityUtils::safeExit('Access denied. Developer access required.', 403, 'warning');
}

echo "<h1>🔒 Security Testing - Bloxer</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 8px; }
    .test-result { margin: 10px 0; padding: 10px; border-radius: 5px; }
    .pass { background: #e8f5e8; border-left: 4px solid #4caf50; }
    .fail { background: #ffebee; border-left: 4px solid #f44336; }
    .warning { background: #fff3e0; border-left: 4px solid #ff9800; }
    .info { background: #e3f2fd; border-left: 4px solid #2196f3; }
    .code { background: #f5f5f5; padding: 10px; border-radius: 5px; font-family: monospace; overflow-x: auto; }
    .severity-high { color: #f44336; font-weight: bold; }
    .severity-medium { color: #ff9800; font-weight: bold; }
    .severity-low { color: #2196f3; font-weight: bold; }
</style>";

class SecurityTester {
    private $rootPath;
    private $results = [];
    
    public function __construct($rootPath) {
        $this->rootPath = $rootPath;
    }
    
    public function runTests() {
        $this->testFilePermissions();
        $this->testConfigurationSecurity();
        $this->testInputValidation();
        $this->testSQLInjection();
        $this->testXSS();
        $this->testCSRF();
        $this->testFileUploads();
        $this->testSessionSecurity();
        
        return $this->generateReport();
    }
    
    private function testFilePermissions() {
        $this->results['file_permissions'] = [
            'name' => 'File Permissions',
            'tests' => []
        ];
        
        // Test sensitive files
        $sensitiveFiles = [
            '.env',
            'config/database.php',
            'composer.json',
            'package.json'
        ];
        
        foreach ($sensitiveFiles as $file) {
            $filePath = $this->rootPath . '/' . $file;
            if (file_exists($filePath)) {
                $perms = fileperms($filePath);
                $worldWritable = ($perms & 0x0002) !== 0;
                
                $this->results['file_permissions']['tests'][] = [
                    'name' => "World-writable check: $file",
                    'status' => $worldWritable ? 'fail' : 'pass',
                    'message' => $worldWritable ? 
                        'File is world-writable (security risk)' : 
                        'File permissions are secure',
                    'severity' => $worldWritable ? 'high' : 'low'
                ];
            }
        }
        
        // Test directory permissions
        $importantDirs = ['logs', 'uploads', 'cache', 'temp'];
        foreach ($importantDirs as $dir) {
            $dirPath = $this->rootPath . '/' . $dir;
            if (is_dir($dirPath)) {
                $perms = fileperms($dirPath);
                $worldWritable = ($perms & 0x0002) !== 0;
                
                $this->results['file_permissions']['tests'][] = [
                    'name' => "Directory permissions: $dir",
                    'status' => $worldWritable ? 'warning' : 'pass',
                    'message' => $worldWritable ? 
                        'Directory is world-writable (potential risk)' : 
                        'Directory permissions are acceptable',
                    'severity' => $worldWritable ? 'medium' : 'low'
                ];
            }
        }
    }
    
    private function testConfigurationSecurity() {
        $this->results['config_security'] = [
            'name' => 'Configuration Security',
            'tests' => []
        ];
        
        // Test error display
        $displayErrors = ini_get('display_errors');
        $this->results['config_security']['tests'][] = [
            'name' => 'Error Display',
            'status' => $displayErrors ? 'fail' : 'pass',
            'message' => $displayErrors ? 
                'Errors are displayed to users (security risk)' : 
                'Errors are properly hidden',
            'severity' => $displayErrors ? 'high' : 'low'
        ];
        
        // Test error logging
        $logErrors = ini_get('log_errors');
        $this->results['config_security']['tests'][] = [
            'name' => 'Error Logging',
            'status' => $logErrors ? 'pass' : 'warning',
            'message' => $logErrors ? 
                'Errors are being logged' : 
                'Error logging is disabled',
            'severity' => $logErrors ? 'low' : 'medium'
        ];
        
        // Test register globals (if applicable)
        $registerGlobals = ini_get('register_globals');
        $this->results['config_security']['tests'][] = [
            'name' => 'Register Globals',
            'status' => $registerGlobals ? 'fail' : 'pass',
            'message' => $registerGlobals ? 
                'Register globals is enabled (high risk)' : 
                'Register globals is disabled',
            'severity' => $registerGlobals ? 'high' : 'low'
        ];
    }
    
    private function testInputValidation() {
        $this->results['input_validation'] = [
            'name' => 'Input Validation',
            'tests' => []
        ];
        
        // Look for common input validation patterns
        $phpFiles = $this->findFiles('*.php');
        $hasValidation = false;
        $hasSanitization = false;
        
        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            
            if (preg_match('/filter_var|filter_input|preg_match.*\$_/', $content)) {
                $hasValidation = true;
            }
            
            if (preg_match('/htmlspecialchars|htmlentities|strip_tags/', $content)) {
                $hasSanitization = true;
            }
        }
        
        $this->results['input_validation']['tests'][] = [
            'name' => 'Input Validation Found',
            'status' => $hasValidation ? 'pass' : 'warning',
            'message' => $hasValidation ? 
                'Input validation patterns detected' : 
                'No input validation patterns found',
            'severity' => $hasValidation ? 'low' : 'medium'
        ];
        
        $this->results['input_validation']['tests'][] = [
            'name' => 'Output Sanitization Found',
            'status' => $hasSanitization ? 'pass' : 'warning',
            'message' => $hasSanitization ? 
                'Output sanitization patterns detected' : 
                'No output sanitization patterns found',
            'severity' => $hasSanitization ? 'low' : 'medium'
        ];
    }
    
    private function testSQLInjection() {
        $this->results['sql_injection'] = [
            'name' => 'SQL Injection Protection',
            'tests' => []
        ];
        
        $phpFiles = $this->findFiles('*.php');
        $hasPreparedStatements = false;
        $hasRiskyQueries = false;
        
        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            
            // Check for prepared statements
            if (preg_match('/prepare\(|bind_param|execute\(\)/', $content)) {
                $hasPreparedStatements = true;
            }
            
            // Check for risky direct queries
            if (preg_match('/(mysql_query|mysqli_query)\s*\(\s*\$/', $content)) {
                $hasRiskyQueries = true;
            }
        }
        
        $this->results['sql_injection']['tests'][] = [
            'name' => 'Prepared Statements Usage',
            'status' => $hasPreparedStatements ? 'pass' : 'warning',
            'message' => $hasPreparedStatements ? 
                'Prepared statements detected' : 
                'No prepared statements found',
            'severity' => $hasPreparedStatements ? 'low' : 'medium'
        ];
        
        $this->results['sql_injection']['tests'][] = [
            'name' => 'Risky SQL Queries',
            'status' => $hasRiskyQueries ? 'fail' : 'pass',
            'message' => $hasRiskyQueries ? 
                'Potentially risky direct SQL queries found' : 
                'No risky SQL queries detected',
            'severity' => $hasRiskyQueries ? 'high' : 'low'
        ];
    }
    
    private function testXSS() {
        $this->results['xss'] = [
            'name' => 'XSS Protection',
            'tests' => []
        ];
        
        $phpFiles = $this->findFiles('*.php');
        $hasOutputEscaping = false;
        $hasUnescapedEcho = false;
        
        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            
            // Check for output escaping
            if (preg_match('/htmlspecialchars|htmlentities/', $content)) {
                $hasOutputEscaping = true;
            }
            
            // Check for unescaped echo
            if (preg_match('/echo\s*\$[a-zA-Z_]\w*(?!\s*\.\s*htmlspecialchars)/', $content)) {
                $hasUnescapedEcho = true;
            }
        }
        
        $this->results['xss']['tests'][] = [
            'name' => 'Output Escaping',
            'status' => $hasOutputEscaping ? 'pass' : 'warning',
            'message' => $hasOutputEscaping ? 
                'Output escaping functions found' : 
                'No output escaping functions found',
            'severity' => $hasOutputEscaping ? 'low' : 'medium'
        ];
        
        $this->results['xss']['tests'][] = [
            'name' => 'Unescaped Output',
            'status' => $hasUnescapedEcho ? 'warning' : 'pass',
            'message' => $hasUnescapedEcho ? 
                'Potentially unescaped output found' : 
                'No obvious unescaped output',
            'severity' => $hasUnescapedEcho ? 'medium' : 'low'
        ];
    }
    
    private function testCSRF() {
        $this->results['csrf'] = [
            'name' => 'CSRF Protection',
            'tests' => []
        ];
        
        $phpFiles = $this->findFiles('*.php');
        $hasCSRF = false;
        
        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            
            // Check for CSRF tokens
            if (preg_match('/csrf|token.*session|session.*token/', $content)) {
                $hasCSRF = true;
            }
        }
        
        $this->results['csrf']['tests'][] = [
            'name' => 'CSRF Protection',
            'status' => $hasCSRF ? 'pass' : 'warning',
            'message' => $hasCSRF ? 
                'CSRF protection patterns detected' : 
                'No CSRF protection patterns found',
            'severity' => $hasCSRF ? 'low' : 'medium'
        ];
    }
    
    private function testFileUploads() {
        $this->results['file_uploads'] = [
            'name' => 'File Upload Security',
            'tests' => []
        ];
        
        // Test upload directory
        $uploadDir = $this->rootPath . '/uploads';
        if (is_dir($uploadDir)) {
            $hasWebAccess = file_exists($uploadDir . '/.htaccess');
            
            $this->results['file_uploads']['tests'][] = [
                'name' => 'Upload Directory Protection',
                'status' => $hasWebAccess ? 'pass' : 'warning',
                'message' => $hasWebAccess ? 
                    'Upload directory has .htaccess protection' : 
                    'Upload directory may lack web access protection',
                'severity' => $hasWebAccess ? 'low' : 'medium'
            ];
        }
        
        // Look for file upload validation
        $phpFiles = $this->findFiles('*.php');
        $hasValidation = false;
        
        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            
            if (preg_match('/\$_FILES.*move_uploaded_file|getimagesize|finfo_file/', $content)) {
                $hasValidation = true;
            }
        }
        
        $this->results['file_uploads']['tests'][] = [
            'name' => 'File Upload Validation',
            'status' => $hasValidation ? 'pass' : 'warning',
            'message' => $hasValidation ? 
                'File upload validation found' : 
                'No file upload validation found',
            'severity' => $hasValidation ? 'low' : 'medium'
        ];
    }
    
    private function testSessionSecurity() {
        $this->results['session_security'] = [
            'name' => 'Session Security',
            'tests' => []
        ];
        
        // Test session cookie settings
        $sessionCookieParams = session_get_cookie_params();
        
        $this->results['session_security']['tests'][] = [
            'name' => 'Session Cookie HTTP Only',
            'status' => $sessionCookieParams['httponly'] ? 'pass' : 'warning',
            'message' => $sessionCookieParams['httponly'] ? 
                'Session cookies are HTTP only' : 
                'Session cookies are not HTTP only',
            'severity' => $sessionCookieParams['httponly'] ? 'low' : 'medium'
        ];
        
        $this->results['session_security']['tests'][] = [
            'name' => 'Session Cookie Secure',
            'status' => $sessionCookieParams['secure'] ? 'pass' : 'warning',
            'message' => $sessionCookieParams['secure'] ? 
                'Session cookies are secure (HTTPS only)' : 
                'Session cookies are not secure (works on HTTP)',
            'severity' => $sessionCookieParams['secure'] ? 'low' : 'medium'
        ];
        
        // Look for session regeneration
        $phpFiles = $this->findFiles('*.php');
        $hasRegeneration = false;
        
        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            
            if (preg_match('/session_regenerate_id/', $content)) {
                $hasRegeneration = true;
            }
        }
        
        $this->results['session_security']['tests'][] = [
            'name' => 'Session Regeneration',
            'status' => $hasRegeneration ? 'pass' : 'info',
            'message' => $hasRegeneration ? 
                'Session regeneration found' : 
                'No session regeneration found',
            'severity' => $hasRegeneration ? 'low' : 'low'
        ];
    }
    
    private function findFiles($pattern) {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->rootPath));
        
        $excludeDirs = ['vendor', 'node_modules', '.git'];
        
        foreach ($iterator as $file) {
            if ($file->isFile() && fnmatch($pattern, $file->getFilename())) {
                $path = $file->getPathname();
                
                $skip = false;
                foreach ($excludeDirs as $excludeDir) {
                    if (strpos($path, '/' . $excludeDir . '/') !== false) {
                        $skip = true;
                        break;
                    }
                }
                
                if (!$skip) {
                    $files[] = $path;
                }
            }
        }
        
        return $files;
    }
    
    private function generateReport() {
        $html = '';
        
        foreach ($this->results as $category => $data) {
            $html .= '<div class="section">';
            $html .= '<h2>' . $data['name'] . '</h2>';
            
            $passCount = 0;
            $failCount = 0;
            $warningCount = 0;
            
            foreach ($data['tests'] as $test) {
                $class = $test['status'];
                $html .= '<div class="test-result ' . $class . '">';
                $html .= '<strong>' . htmlspecialchars($test['name']) . '</strong>';
                $html .= '<span class="severity-' . $test['severity'] . '"> [' . strtoupper($test['severity']) . ']</span>';
                $html .= '<br>';
                $html .= htmlspecialchars($test['message']);
                $html .= '</div>';
                
                switch ($test['status']) {
                    case 'pass': $passCount++; break;
                    case 'fail': $failCount++; break;
                    case 'warning': $warningCount++; break;
                }
            }
            
            $html .= '<div style="margin-top: 10px; font-weight: bold;">';
            $html .= "✓ Passed: $passCount | ❌ Failed: $failCount | ⚠️ Warnings: $warningCount";
            $html .= '</div>';
            $html .= '</div>';
        }
        
        return $html;
    }
}

// Run security tests
$securityTester = new SecurityTester(__DIR__ . '/..');
echo $securityTester->runTests();

echo "<p><a href='../index.php'>← Back to Bloxer</a></p>";
?>
