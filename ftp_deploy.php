<?php
/**
 * FTP Deployment Script for Bloxer Platform
 * Uploads all necessary files to the FTP server
 */

require_once 'config/ftp.php';

class FTPDeploy {
    private $ftp;
    private $localPath;
    private $remotePath;
    
    public function __construct() {
        $this->ftp = FTPConfig::getInstance();
        $this->localPath = __DIR__;
        $this->remotePath = '/';
    }
    
    public function deploy() {
        try {
            echo "Connecting to FTP server...\n";
            $this->ftp->connect();
            echo "Connected successfully!\n\n";
            
            echo "Starting deployment...\n";
            $this->uploadDirectory($this->localPath, $this->remotePath);
            echo "\nDeployment completed successfully!\n";
            
        } catch (Exception $e) {
            echo "Deployment failed: " . $e->getMessage() . "\n";
            return false;
        } finally {
            $this->ftp->disconnect();
        }
        
        return true;
    }
    
    private function uploadDirectory($localDir, $remoteDir) {
        // Create remote directory if it doesn't exist
        try {
            $this->ftp->createDirectory($remoteDir);
        } catch (Exception $e) {
            // Directory might already exist
        }
        
        $items = scandir($localDir);
        
        foreach ($items as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            
            $localPath = $localDir . '/' . $item;
            $remotePath = $remoteDir . '/' . $item;
            
            // Skip certain files and directories
            if ($this->shouldSkip($item, $localPath)) {
                echo "Skipping: $item\n";
                continue;
            }
            
            if (is_dir($localPath)) {
                echo "Uploading directory: $item\n";
                $this->uploadDirectory($localPath, $remotePath);
            } else {
                echo "Uploading file: $item\n";
                $this->ftp->uploadFile($localPath, $remotePath);
            }
        }
    }
    
    private function shouldSkip($item, $localPath) {
        $skipPatterns = [
            '.git',
            '.gitignore',
            'node_modules',
            'vendor',
            '.DS_Store',
            'Thumbs.db',
            '*.log',
            '.env.local',
            '.env.development',
            'composer.lock',
            'package-lock.json',
            'ftp_deploy.php',
            'README.md',
            'FIX_SUMMARY.txt',
            'QUICK_START.txt',
            'test.html',
            'query',
            'devmode.php',
            'check_projects.php'
        ];
        
        foreach ($skipPatterns as $pattern) {
            if (fnmatch($pattern, $item)) {
                return true;
            }
        }
        
        return false;
    }
}

// Run deployment
if (php_sapi_name() === 'cli') {
    $deploy = new FTPDeploy();
    $deploy->deploy();
} else {
    echo "<pre>";
    $deploy = new FTPDeploy();
    $deploy->deploy();
    echo "</pre>";
}
?>
