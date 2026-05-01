<?php
/**
 * Complete Deployment and Setup Script for Bloxer Platform
 * Uploads files to FTP and sets up the database
 */

echo "<h2>Bloxer Platform Deployment & Setup</h2>";
echo "<pre>";

// Step 1: FTP Deployment
echo "=== Step 1: FTP Deployment ===\n";
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
            'deploy_and_setup.php',
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

// Step 2: Database Setup
echo "\n=== Step 2: Database Setup ===\n";
require_once 'config/database.php';

class DatabaseSetup {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
    }
    
    public function setup() {
        try {
            echo "Setting up database...\n";
            
            // Read the complete database schema
            $schemaFile = __DIR__ . '/complete_database_schema.sql';
            if (!file_exists($schemaFile)) {
                throw new Exception("Database schema file not found: $schemaFile");
            }
            
            $schema = file_get_contents($schemaFile);
            
            // Split into individual statements
            $statements = array_filter(array_map('trim', explode(';', $schema)));
            
            echo "Creating database tables...\n";
            
            foreach ($statements as $statement) {
                if (!empty($statement) && !preg_match('/^--/', $statement)) {
                    try {
                        $this->db->execute($statement);
                        echo "✓ Executed: " . substr($statement, 0, 50) . "...\n";
                    } catch (Exception $e) {
                        echo "✗ Error in statement: " . $e->getMessage() . "\n";
                        echo "Statement: " . substr($statement, 0, 100) . "...\n";
                    }
                }
            }
            
            echo "\nDatabase setup completed successfully!\n";
            
            // Verify tables were created
            $this->verifyTables();
            
        } catch (Exception $e) {
            echo "Database setup failed: " . $e->getMessage() . "\n";
            return false;
        }
        
        return true;
    }
    
    private function verifyTables() {
        echo "\nVerifying created tables...\n";
        
        $expectedTables = [
            'users',
            'projects',
            'project_files',
            'apps',
            'app_reviews',
            'app_ratings',
            'user_apps',
            'saved_apps',
            'offers',
            'offer_messages',
            'notifications',
            'notification_preferences',
            'developer_wallet',
            'categories',
            'popular_apps',
            'user_preferences',
            'version_files',
            'project_versions',
            'comments',
            'collaboration_invites',
            'collaboration_members',
            'chat_rooms',
            'chat_messages',
            'chat_participants'
        ];
        
        foreach ($expectedTables as $table) {
            if ($this->db->tableExists($table)) {
                echo "✓ Table '$table' exists\n";
            } else {
                echo "✗ Table '$table' missing\n";
            }
        }
    }
}

// Execute deployment and setup
$ftpDeploy = new FTPDeploy();
$ftpSuccess = $ftpDeploy->deploy();

if ($ftpSuccess) {
    echo "\n";
    $dbSetup = new DatabaseSetup();
    $dbSuccess = $dbSetup->setup();
    
    if ($dbSuccess) {
        echo "\n=== Setup Complete! ===\n";
        echo "Your Bloxer platform has been successfully deployed to:\n";
        echo "https://bloxer.eskp.pl/\n";
        echo "\nYou can now access your application.\n";
    } else {
        echo "\nDatabase setup failed. Please check the error messages above.\n";
    }
} else {
    echo "\nFTP deployment failed. Please check the error messages above.\n";
}

echo "</pre>";
?>
