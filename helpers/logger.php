<?php
/**
 * Advanced Logging System - Bloxer Platform
 * Comprehensive debug and logging functionality
 */

class Logger {
    private static $instance;
    private $log_file;
    private $error_log_file;
    private $debug_log_file;
    private $security_log_file;
    private $performance_log_file;
    private $log_level;
    private $is_debug_mode;
    
    // Log levels
    const EMERGENCY = 'emergency';
    const ALERT = 'alert';
    const CRITICAL = 'critical';
    const ERROR = 'error';
    const WARNING = 'warning';
    const NOTICE = 'notice';
    const INFO = 'info';
    const DEBUG = 'debug';
    
    private function __construct() {
        $this->initializeLogger();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function initializeLogger() {
        // Get configuration
        $app = AppConfig::getInstance();
        $this->is_debug_mode = $app->isDebug();
        $this->log_level = $app->get('logging.level', 'info');
        
        // Set log file paths
        $logs_dir = $app->getLogsPath();
        $this->ensureLogDirectory($logs_dir);
        
        $this->log_file = $logs_dir . '/application.log';
        $this->error_log_file = $logs_dir . '/error.log';
        $this->debug_log_file = $logs_dir . '/debug.log';
        $this->security_log_file = $logs_dir . '/security.log';
        $this->performance_log_file = $logs_dir . '/performance.log';
        
        // Register shutdown function for fatal errors
        register_shutdown_function([$this, 'handleFatalError']);
        
        // Set error handler
        set_error_handler([$this, 'handleError']);
        
        // Set exception handler
        set_exception_handler([$this, 'handleException']);
    }
    
    private function ensureLogDirectory($dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Create .htaccess to protect log files
        $htaccess_file = $dir . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            file_put_contents($htaccess_file, "Deny from all\n");
        }
    }
    
    /**
     * Log emergency message
     */
    public function emergency($message, $context = []) {
        $this->log(self::EMERGENCY, $message, $context);
    }
    
    /**
     * Log alert message
     */
    public function alert($message, $context = []) {
        $this->log(self::ALERT, $message, $context);
    }
    
    /**
     * Log critical message
     */
    public function critical($message, $context = []) {
        $this->log(self::CRITICAL, $message, $context);
    }
    
    /**
     * Log error message
     */
    public function error($message, $context = []) {
        $this->log(self::ERROR, $message, $context);
        $this->writeToFile($this->error_log_file, self::ERROR, $message, $context);
    }
    
    /**
     * Log warning message
     */
    public function warning($message, $context = []) {
        $this->log(self::WARNING, $message, $context);
    }
    
    /**
     * Log notice message
     */
    public function notice($message, $context = []) {
        $this->log(self::NOTICE, $message, $context);
    }
    
    /**
     * Log info message
     */
    public function info($message, $context = []) {
        $this->log(self::INFO, $message, $context);
    }
    
    /**
     * Log debug message
     */
    public function debug($message, $context = []) {
        if ($this->is_debug_mode) {
            $this->log(self::DEBUG, $message, $context);
            $this->writeToFile($this->debug_log_file, self::DEBUG, $message, $context);
        }
    }
    
    /**
     * Log security event
     */
    public function security($message, $context = []) {
        $this->log(self::WARNING, $message, $context);
        $this->writeToFile($this->security_log_file, self::WARNING, $message, $context);
    }
    
    /**
     * Log performance data
     */
    public function performance($message, $context = []) {
        $this->writeToFile($this->performance_log_file, self::INFO, $message, $context);
    }
    
    /**
     * Main logging method
     */
    public function log($level, $message, $context = []) {
        if (!$this->shouldLog($level)) {
            return;
        }
        
        $log_entry = $this->formatLogEntry($level, $message, $context);
        $this->writeToFile($this->log_file, $level, $message, $context);
        
        // Also write to system error log for critical errors
        if (in_array($level, [self::EMERGENCY, self::ALERT, self::CRITICAL])) {
            error_log($log_entry);
        }
    }
    
    /**
     * Check if log level should be logged
     */
    private function shouldLog($level) {
        $levels = [
            self::EMERGENCY => 0,
            self::ALERT => 1,
            self::CRITICAL => 2,
            self::ERROR => 3,
            self::WARNING => 4,
            self::NOTICE => 5,
            self::INFO => 6,
            self::DEBUG => 7
        ];
        
        return $levels[$level] <= $levels[$this->log_level] ?? 6;
    }
    
    /**
     * Format log entry
     */
    private function formatLogEntry($level, $message, $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        $context_str = !empty($context) ? ' | ' . json_encode($context) : '';
        
        return "[{$timestamp}] [{$level}] {$message}{$context_str}";
    }
    
    /**
     * Write to log file
     */
    private function writeToFile($file, $level, $message, $context = []) {
        $log_entry = $this->formatLogEntry($level, $message, $context);
        
        // Rotate log if it's too large (>10MB)
        if (file_exists($file) && filesize($file) > 10 * 1024 * 1024) {
            $this->rotateLog($file);
        }
        
        file_put_contents($file, $log_entry . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Rotate log file
     */
    private function rotateLog($file) {
        $backup_file = str_replace('.log', '.' . date('Y-m-d-H-i-s') . '.log', $file);
        rename($file, $backup_file);
        
        // Keep only last 10 log files
        $pattern = str_replace('.log', '.*.log', $file);
        $log_files = glob($pattern);
        if (count($log_files) > 10) {
            usort($log_files, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            $files_to_delete = array_slice($log_files, 10);
            foreach ($files_to_delete as $file_to_delete) {
                unlink($file_to_delete);
            }
        }
    }
    
    /**
     * Handle PHP errors
     */
    public function handleError($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $error_types = [
            E_ERROR => self::ERROR,
            E_WARNING => self::WARNING,
            E_PARSE => self::CRITICAL,
            E_NOTICE => self::NOTICE,
            E_CORE_ERROR => self::CRITICAL,
            E_CORE_WARNING => self::WARNING,
            E_COMPILE_ERROR => self::CRITICAL,
            E_COMPILE_WARNING => self::WARNING,
            E_USER_ERROR => self::ERROR,
            E_USER_WARNING => self::WARNING,
            E_USER_NOTICE => self::NOTICE,
            E_STRICT => self::NOTICE,
            E_RECOVERABLE_ERROR => self::ERROR,
            E_DEPRECATED => self::NOTICE,
            E_USER_DEPRECATED => self::NOTICE
        ];
        
        $level = $error_types[$severity] ?? self::WARNING;
        
        $this->log($level, "PHP Error: {$message}", [
            'file' => $file,
            'line' => $line,
            'severity' => $severity
        ]);
        
        return true;
    }
    
    /**
     * Handle exceptions
     */
    public function handleException($exception) {
        $this->critical("Uncaught Exception: " . $exception->getMessage(), [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]);
        
        if (!$this->is_debug_mode) {
            // Show user-friendly error page
            include 'controllers/errors/500.php';
        }
        
        exit;
    }
    
    /**
     * Handle fatal errors
     */
    public function handleFatalError() {
        $error = error_get_last();
        
        if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
            $this->critical("Fatal Error: {$error['message']}", [
                'file' => $error['file'],
                'line' => $error['line'],
                'type' => $error['type']
            ]);
        }
    }
    
    /**
     * Log database query
     */
    public function logQuery($sql, $params, $execution_time, $affected_rows = null) {
        $context = [
            'sql' => $sql,
            'params' => $params,
            'execution_time' => $execution_time,
            'affected_rows' => $affected_rows
        ];
        
        if ($execution_time > 0.1) {
            $this->warning("Slow database query detected", $context);
        } else {
            $this->debug("Database query executed", $context);
        }
    }
    
    /**
     * Log API request
     */
    public function logApiRequest($endpoint, $method, $params, $response_code, $execution_time) {
        $this->info("API Request: {$method} {$endpoint}", [
            'method' => $method,
            'endpoint' => $endpoint,
            'params' => $params,
            'response_code' => $response_code,
            'execution_time' => $execution_time,
            'user_id' => getCurrentUserId() ?? 'guest',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    }
    
    /**
     * Log user action
     */
    public function logUserAction($action, $details = []) {
        $context = array_merge([
            'action' => $action,
            'user_id' => getCurrentUserId() ?? 'guest',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'timestamp' => time()
        ], $details);
        
        $this->info("User Action: {$action}", $context);
    }
    
    /**
     * Get log statistics
     */
    public function getLogStats() {
        $stats = [];
        
        $log_files = [
            'application' => $this->log_file,
            'error' => $this->error_log_file,
            'debug' => $this->debug_log_file,
            'security' => $this->security_log_file,
            'performance' => $this->performance_log_file
        ];
        
        foreach ($log_files as $type => $file) {
            if (file_exists($file)) {
                $stats[$type] = [
                    'size' => filesize($file),
                    'lines' => count(file($file)),
                    'modified' => filemtime($file)
                ];
            } else {
                $stats[$type] = [
                    'size' => 0,
                    'lines' => 0,
                    'modified' => null
                ];
            }
        }
        
        return $stats;
    }
    
    /**
     * Clear old logs
     */
    public function clearOldLogs($days = 30) {
        $cutoff_time = time() - ($days * 24 * 60 * 60);
        $cleared = 0;
        
        $log_files = [
            $this->log_file,
            $this->error_log_file,
            $this->debug_log_file,
            $this->security_log_file,
            $this->performance_log_file
        ];
        
        foreach ($log_files as $file) {
            if (file_exists($file) && filemtime($file) < $cutoff_time) {
                unlink($file);
                $cleared++;
            }
        }
        
        // Also clear rotated logs
        $logs_dir = dirname($this->log_file);
        $pattern = $logs_dir . '/*.log';
        $rotated_logs = glob($pattern);
        
        foreach ($rotated_logs as $log_file) {
            if (filemtime($log_file) < $cutoff_time) {
                unlink($log_file);
                $cleared++;
            }
        }
        
        $this->info("Cleared {$cleared} old log files older than {$days} days");
        
        return $cleared;
    }
    
    /**
     * Export logs to JSON
     */
    public function exportLogs($type = 'application', $lines = 1000) {
        $file = $this->log_file;
        
        switch ($type) {
            case 'error':
                $file = $this->error_log_file;
                break;
            case 'debug':
                $file = $this->debug_log_file;
                break;
            case 'security':
                $file = $this->security_log_file;
                break;
            case 'performance':
                $file = $this->performance_log_file;
                break;
        }
        
        if (!file_exists($file)) {
            return [];
        }
        
        $all_lines = file($file);
        $recent_lines = array_slice($all_lines, -$lines);
        
        $logs = [];
        foreach ($recent_lines as $line) {
            if (trim($line)) {
                $logs[] = [
                    'timestamp' => substr($line, 1, 19),
                    'level' => substr($line, 22, strpos($line, ']') - 22),
                    'message' => substr($line, strpos($line, ']') + 3)
                ];
            }
        }
        
        return $logs;
    }
}

// Global helper functions for backward compatibility
function log_message($level, $message, $context = []) {
    $logger = Logger::getInstance();
    $logger->log($level, $message, $context);
}

function log_error($message, $context = []) {
    $logger = Logger::getInstance();
    $logger->error($message, $context);
}

function log_info($message, $context = []) {
    $logger = Logger::getInstance();
    $logger->info($message, $context);
}

function log_debug($message, $context = []) {
    $logger = Logger::getInstance();
    $logger->debug($message, $context);
}

function log_security($message, $context = []) {
    $logger = Logger::getInstance();
    $logger->security($message, $context);
}

function log_performance($message, $context = []) {
    $logger = Logger::getInstance();
    $logger->performance($message, $context);
}
?>
