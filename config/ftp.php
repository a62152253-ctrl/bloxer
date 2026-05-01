<?php
/**
 * FTP Configuration for Bloxer Platform
 * Centralized FTP connection and file management
 */

class FTPConfig {
    private static $instance = null;
    private $connection;
    private $config;
    
    private function __construct() {
        $this->loadConfig();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function loadConfig() {
        // Load environment variables
        $env_file = __DIR__ . '/../.env';
        if (file_exists($env_file)) {
            $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '#') === 0) continue;
                if (strpos($line, '=') === false) continue;
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
        
        $this->config = [
            'host' => $_ENV['FTP_HOST'] ?? '',
            'port' => intval($_ENV['FTP_PORT'] ?? 21),
            'username' => $_ENV['FTP_USERNAME'] ?? '',
            'password' => $_ENV['FTP_PASSWORD'] ?? '',
            'passive_mode' => filter_var($_ENV['FTP_PASSIVE_MODE'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
            'timeout' => intval($_ENV['FTP_TIMEOUT'] ?? 30),
            'root_path' => $_ENV['FTP_ROOT_PATH'] ?? '/',
            'ssl' => filter_var($_ENV['FTP_SSL'] ?? 'false', FILTER_VALIDATE_BOOLEAN)
        ];
    }
    
    public function connect() {
        if ($this->connection) {
            return true;
        }
        
        if (empty($this->config['host']) || empty($this->config['username'])) {
            throw new Exception('FTP configuration is incomplete. Please set FTP_HOST, FTP_USERNAME, and FTP_PASSWORD in your .env file.');
        }
        
        try {
            if ($this->config['ssl']) {
                $this->connection = ftp_ssl_connect($this->config['host'], $this->config['port'], $this->config['timeout']);
            } else {
                $this->connection = ftp_connect($this->config['host'], $this->config['port'], $this->config['timeout']);
            }
            
            if (!$this->connection) {
                throw new Exception('Failed to connect to FTP server');
            }
            
            $login_result = ftp_login($this->connection, $this->config['username'], $this->config['password']);
            if (!$login_result) {
                throw new Exception('FTP login failed');
            }
            
            if ($this->config['passive_mode']) {
                ftp_pasv($this->connection, true);
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->connection = null;
            throw new Exception('FTP connection error: ' . $e->getMessage());
        }
    }
    
    public function disconnect() {
        if ($this->connection) {
            ftp_close($this->connection);
            $this->connection = null;
        }
    }
    
    public function uploadFile($localFile, $remoteFile) {
        $this->connect();
        
        if (!file_exists($localFile)) {
            throw new Exception("Local file does not exist: $localFile");
        }
        
        $uploadResult = ftp_put($this->connection, $remoteFile, $localFile, FTP_BINARY);
        
        if (!$uploadResult) {
            throw new Exception("Failed to upload file: $localFile to $remoteFile");
        }
        
        return true;
    }
    
    public function downloadFile($remoteFile, $localFile) {
        $this->connect();
        
        $downloadResult = ftp_get($this->connection, $localFile, $remoteFile, FTP_BINARY);
        
        if (!$downloadResult) {
            throw new Exception("Failed to download file: $remoteFile to $localFile");
        }
        
        return true;
    }
    
    public function deleteFile($remoteFile) {
        $this->connect();
        
        $deleteResult = ftp_delete($this->connection, $remoteFile);
        
        if (!$deleteResult) {
            throw new Exception("Failed to delete file: $remoteFile");
        }
        
        return true;
    }
    
    public function createDirectory($remoteDir) {
        $this->connect();
        
        if (ftp_mkdir($this->connection, $remoteDir)) {
            return true;
        }
        
        // Check if directory already exists
        if ($this->directoryExists($remoteDir)) {
            return true;
        }
        
        throw new Exception("Failed to create directory: $remoteDir");
    }
    
    public function deleteDirectory($remoteDir) {
        $this->connect();
        
        // First, try to remove as empty directory
        if (@ftp_rmdir($this->connection, $remoteDir)) {
            return true;
        }
        
        // If not empty, recursively delete contents
        $this->deleteDirectoryRecursive($remoteDir);
        
        // Now try to remove the directory itself
        if (@ftp_rmdir($this->connection, $remoteDir)) {
            return true;
        }
        
        throw new Exception("Failed to delete directory: $remoteDir");
    }
    
    private function deleteDirectoryRecursive($remoteDir) {
        $this->connect();
        
        $files = ftp_nlist($this->connection, $remoteDir);
        
        if ($files === false) {
            return;
        }
        
        foreach ($files as $file) {
            if ($file == '.' || $file == '..') {
                continue;
            }
            
            $fullPath = $remoteDir . '/' . basename($file);
            
            if ($this->directoryExists($fullPath)) {
                $this->deleteDirectoryRecursive($fullPath);
                @ftp_rmdir($this->connection, $fullPath);
            } else {
                @ftp_delete($this->connection, $fullPath);
            }
        }
    }
    
    public function directoryExists($remoteDir) {
        $this->connect();
        
        $currentDir = ftp_pwd($this->connection);
        $exists = @ftp_chdir($this->connection, $remoteDir);
        ftp_chdir($this->connection, $currentDir);
        
        return $exists;
    }
    
    public function listFiles($remoteDir = '.') {
        $this->connect();
        
        $files = ftp_nlist($this->connection, $remoteDir);
        
        if ($files === false) {
            return [];
        }
        
        return $files;
    }
    
    public function getFileSize($remoteFile) {
        $this->connect();
        
        $size = ftp_size($this->connection, $remoteFile);
        
        if ($size == -1) {
            throw new Exception("Failed to get file size: $remoteFile");
        }
        
        return $size;
    }
    
    public function getLastModified($remoteFile) {
        $this->connect();
        
        $time = ftp_mdtm($this->connection, $remoteFile);
        
        if ($time == -1) {
            throw new Exception("Failed to get last modified time: $remoteFile");
        }
        
        return $time;
    }
    
    public function changeDirectory($remoteDir) {
        $this->connect();
        
        if (!ftp_chdir($this->connection, $remoteDir)) {
            throw new Exception("Failed to change directory: $remoteDir");
        }
        
        return true;
    }
    
    public function getCurrentDirectory() {
        $this->connect();
        
        $currentDir = ftp_pwd($this->connection);
        
        if ($currentDir === false) {
            throw new Exception("Failed to get current directory");
        }
        
        return $currentDir;
    }
    
    public function rename($oldName, $newName) {
        $this->connect();
        
        if (!ftp_rename($this->connection, $oldName, $newName)) {
            throw new Exception("Failed to rename: $oldName to $newName");
        }
        
        return true;
    }
    
    public function chmod($remoteFile, $permissions) {
        $this->connect();
        
        if (!ftp_chmod($this->connection, $permissions, $remoteFile)) {
            throw new Exception("Failed to change permissions: $remoteFile to $permissions");
        }
        
        return true;
    }
    
    public function getConfig() {
        return $this->config;
    }
    
    public function isConnected() {
        return $this->connection !== null;
    }
    
    public function __destruct() {
        $this->disconnect();
    }
}

// Helper function for backward compatibility
function getFTPConnection() {
    return FTPConfig::getInstance();
}
?>
