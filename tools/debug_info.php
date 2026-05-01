<?php
/**
 * Debug Information Tool for Bloxer
 * Provides comprehensive debugging information
 */

require_once '../controllers/core/mainlogincore.php';
require_once '../../config/security.php';

$auth = new AuthCore();

// Only allow developers
if (!$auth->isLoggedIn() || !$auth->isDeveloper()) {
    SecurityUtils::safeExit('Access denied. Developer access required.', 403, 'warning');
}

echo "<h1>🐛 Debug Information - Bloxer</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 8px; }
    .debug-info { background: #f5f5f5; padding: 10px; border-radius: 5px; font-family: monospace; }
    .error { color: #f44336; }
    .warning { color: #ff9800; }
    .success { color: #4caf50; }
    .info { color: #2196f3; }
    table { width: 100%; border-collapse: collapse; margin: 10px 0; }
    th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
    th { background: #f2f2f2; }
    .status-ok { color: #4caf50; font-weight: bold; }
    .status-error { color: #f44336; font-weight: bold; }
    .status-warning { color: #ff9800; font-weight: bold; }
</style>";

class DebugInfo {
    private $rootPath;
    
    public function __construct($rootPath) {
        $this->rootPath = $rootPath;
    }
    
    public function generateReport() {
        $html = '';
        
        $html .= $this->getSystemInfo();
        $html .= $this->getPhpInfo();
        $html .= $this->getDatabaseInfo();
        $html .= $this->getFilesystemInfo();
        $html .= $this->getRecentErrors();
        $html .= $this->getPerformanceInfo();
        
        return $html;
    }
    
    private function getSystemInfo() {
        $html = '<div class="section">';
        $html .= '<h2>🖥️ System Information</h2>';
        $html .= '<table>';
        
        $html .= '<tr><th>Item</th><th>Value</th><th>Status</th></tr>';
        
        // PHP Version
        $phpVersion = PHP_VERSION;
        $status = version_compare($phpVersion, '8.0', '>=') ? 'ok' : 'warning';
        $html .= '<tr><td>PHP Version</td><td>' . $phpVersion . '</td><td class="status-' . $status . '">' . ucfirst($status) . '</td></tr>';
        
        // Web Server
        $server = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
        $html .= '<tr><td>Web Server</td><td>' . $server . '</td><td class="status-ok">OK</td></tr>';
        
        // Memory Limit
        $memoryLimit = ini_get('memory_limit');
        $html .= '<tr><td>Memory Limit</td><td>' . $memoryLimit . '</td><td class="status-ok">OK</td></tr>';
        
        // Max Execution Time
        $maxTime = ini_get('max_execution_time');
        $html .= '<tr><td>Max Execution Time</td><td>' . $maxTime . 's</td><td class="status-ok">OK</td></tr>';
        
        // Upload Max Filesize
        $uploadSize = ini_get('upload_max_filesize');
        $html .= '<tr><td>Upload Max Filesize</td><td>' . $uploadSize . '</td><td class="status-ok">OK</td></tr>';
        
        $html .= '</table>';
        $html .= '</div>';
        
        return $html;
    }
    
    private function getPhpInfo() {
        $html = '<div class="section">';
        $html .= '<h2>🐘 PHP Configuration</h2>';
        $html .= '<table>';
        
        // Only show safe, non-sensitive configuration
        $importantSettings = [
            'display_errors' => 'Should be Off in production',
            'log_errors' => 'Should be On',
            'post_max_size' => 'POST data limit',
            'max_input_vars' => 'Input variables limit',
            'opcache.enable' => 'OPcache status'
        ];
        
        foreach ($importantSettings as $setting => $description) {
            $value = ini_get($setting);
            $displayValue = $value === '' ? 'Off' : $value;
            $status = 'ok';
            
            if ($setting === 'display_errors' && $value === '1') {
                $status = 'warning';
            } elseif ($setting === 'log_errors' && $value === '') {
                $status = 'error';
            }
            
            $html .= '<tr>';
            $html .= '<td>' . $setting . '</td>';
            $html .= '<td>' . $displayValue . '</td>';
            $html .= '<td><small>' . $description . '</small></td>';
            $html .= '<td class="status-' . $status . '">' . ucfirst($status) . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</table>';
        $html .= '</div>';
        
        return $html;
    }
    
    private function getDatabaseInfo() {
        $html = '<div class="section">';
        $html .= '<h2>🗄️ Database Information</h2>';
        
        try {
            require_once '../controllers/core/mainlogincore.php';
            $auth = new AuthCore();
            $conn = $auth->getConnection();
            
            if ($conn) {
                $html .= '<table>';
                $html .= '<tr><th>Item</th><th>Value</th><th>Status</th></tr>';
                
                // Connection Status
                $html .= '<tr><td>Connection</td><td>Connected</td><td class="status-ok">OK</td></tr>';
                
                // MySQL Version
                $result = $conn->query("SELECT VERSION() as version");
                $version = $result->fetch_assoc()['version'];
                $html .= '<tr><td>MySQL Version</td><td>' . $version . '</td><td class="status-ok">OK</td></tr>';
                
                // Database Name
                $result = $conn->query("SELECT DATABASE() as db_name");
                $dbName = $result->fetch_assoc()['db_name'];
                $html .= '<tr><td>Database</td><td>' . $dbName . '</td><td class="status-ok">OK</td></tr>';
                
                // Table Count
                $result = $conn->query("SHOW TABLES");
                $tableCount = $result->num_rows;
                $html .= '<tr><td>Tables</td><td>' . $tableCount . '</td><td class="status-ok">OK</td></tr>';
                
                // Important Tables Check
                $importantTables = ['users', 'apps', 'projects', 'notifications', 'app_versions'];
                $missingTables = [];
                
                foreach ($importantTables as $table) {
                    $result = $conn->query("SHOW TABLES LIKE '$table'");
                    if ($result->num_rows === 0) {
                        $missingTables[] = $table;
                    }
                }
                
                if (empty($missingTables)) {
                    $html .= '<tr><td>Important Tables</td><td>All present</td><td class="status-ok">OK</td></tr>';
                } else {
                    $html .= '<tr><td>Missing Tables</td><td>' . implode(', ', $missingTables) . '</td><td class="status-error">ERROR</td></tr>';
                }
                
                $html .= '</table>';
            } else {
                $html .= '<p class="error">❌ Database connection failed</p>';
            }
        } catch (Exception $e) {
            $html .= '<p class="error">❌ Database error: ' . $e->getMessage() . '</p>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    private function getFilesystemInfo() {
        $html = '<div class="section">';
        $html .= '<h2>📁 Filesystem Information</h2>';
        $html .= '<table>';
        
        // Project Size
        $totalSize = $this->getDirectorySize($this->rootPath);
        $html .= '<tr><td>Project Size</td><td>' . $this->formatBytes($totalSize) . '</td><td class="status-ok">OK</td></tr>';
        
        // Writable Directories
        $importantDirs = ['logs', 'uploads', 'uploads/version_files'];
        foreach ($importantDirs as $dir) {
            $dirPath = $this->rootPath . '/' . $dir;
            $isWritable = is_dir($dirPath) && is_writable($dirPath);
            $status = $isWritable ? 'ok' : 'error';
            $statusText = $isWritable ? 'Writable' : 'Not writable';
            $html .= '<tr><td>' . $dir . '</td><td>' . $statusText . '</td><td class="status-' . $status . '">' . strtoupper($status) . '</td></tr>';
        }
        
        // .htaccess
        $htaccessPath = $this->rootPath . '/.htaccess';
        $htaccessExists = file_exists($htaccessPath);
        $status = $htaccessExists ? 'ok' : 'warning';
        $statusText = $htaccessExists ? 'Exists' : 'Missing';
        $html .= '<tr><td>.htaccess</td><td>' . $statusText . '</td><td class="status-' . $status . '">' . strtoupper($status) . '</td></tr>';
        
        $html .= '</table>';
        $html .= '</div>';
        
        return $html;
    }
    
    private function getRecentErrors() {
        $html = '<div class="section">';
        $html .= '<h2>📋 Recent Errors</h2>';
        
        $errorLog = $this->rootPath . '/logs/php_errors.log';
        
        if (file_exists($errorLog)) {
            $errors = file_get_contents($errorLog);
            $recentErrors = array_slice(explode("\n", $errors), -10);
            
            if (!empty($recentErrors) && $recentErrors[0] !== '') {
                $html .= '<div class="debug-info">';
                foreach ($recentErrors as $error) {
                    if (!empty(trim($error))) {
                        $html .= htmlspecialchars($error) . '<br>';
                    }
                }
                $html .= '</div>';
            } else {
                $html .= '<p class="success">✅ No recent errors</p>';
            }
        } else {
            $html .= '<p class="info">ℹ️ Error log file not found</p>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    private function getPerformanceInfo() {
        $html = '<div class="section">';
        $html .= '<h2>⚡ Performance Information</h2>';
        $html .= '<table>';
        
        // Current Memory Usage
        $memoryUsage = memory_get_usage(true);
        $html .= '<tr><td>Current Memory Usage</td><td>' . $this->formatBytes($memoryUsage) . '</td><td class="status-ok">OK</td></tr>';
        
        // Peak Memory Usage
        $peakMemory = memory_get_peak_usage(true);
        $html .= '<tr><td>Peak Memory Usage</td><td>' . $this->formatBytes($peakMemory) . '</td><td class="status-ok">OK</td></tr>';
        
        // OPcache Status
        if (function_exists('opcache_get_status')) {
            $opcacheStatus = opcache_get_status();
            if ($opcacheStatus) {
                $opcacheEnabled = $opcacheStatus['opcache_enabled'] ? 'Enabled' : 'Disabled';
                $status = $opcacheStatus['opcache_enabled'] ? 'ok' : 'warning';
                $html .= '<tr><td>OPcache</td><td>' . $opcacheEnabled . '</td><td class="status-' . $status . '">' . strtoupper($status) . '</td></tr>';
                
                if ($opcacheStatus['opcache_enabled']) {
                    $hitRate = round($opcacheStatus['opcache_statistics']['opcache_hit_rate'], 2);
                    $html .= '<tr><td>OPcache Hit Rate</td><td>' . $hitRate . '%</td><td class="status-ok">OK</td></tr>';
                }
            }
        }
        
        $html .= '</table>';
        $html .= '</div>';
        
        return $html;
    }
    
    private function getDirectorySize($path) {
        $totalSize = 0;
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
        
        foreach ($files as $file) {
            if ($file->isFile()) {
                $totalSize += $file->getSize();
            }
        }
        
        return $totalSize;
    }
    
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

// Generate debug report
$debugInfo = new DebugInfo(__DIR__ . '/..');
echo $debugInfo->generateReport();

echo "<p><a href='../index.php'>← Back to Bloxer</a></p>";
?>
