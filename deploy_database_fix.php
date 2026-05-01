<?php
/**
 * Deploy database fix to production server
 * Uploads and executes the login_attempts table creation script on production
 */

require_once 'config/ftp.php';

echo "Deploying database fix to production server...\n";

try {
    $ftp = FTPConfig::getInstance();
    
    echo "Connecting to FTP server...\n";
    $ftp->connect();
    echo "✓ Connected to FTP server\n\n";
    
    // Upload the deployment script
    $localScript = __DIR__ . '/deploy_login_attempts_table.php';
    $remoteScript = '/deploy_login_attempts_table.php';
    
    echo "Uploading database deployment script...\n";
    $ftp->uploadFile($localScript, $remoteScript);
    echo "✓ Script uploaded to production\n\n";
    
    echo "Database fix deployment completed!\n";
    echo "The script has been uploaded to the production server.\n";
    echo "To execute it on production, visit:\n";
    echo "https://bloxer.eskp.pl/deploy_login_attempts_table.php\n\n";
    echo "IMPORTANT: Delete the script from production after execution for security.\n";
    
    $ftp->disconnect();
    
} catch (Exception $e) {
    echo "✗ Deployment failed: " . $e->getMessage() . "\n";
}

// Also create a simple SQL script for manual execution
echo "\nCreating SQL backup script...\n";

$sqlContent = "-- Create login_attempts table for brute force protection
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    attempt_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_ip_address (ip_address),
    INDEX idx_attempt_time (attempt_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

file_put_contents(__DIR__ . '/production_login_attempts_fix.sql', $sqlContent);
echo "✓ SQL script created: production_login_attempts_fix.sql\n";

echo "\n=== DEPLOYMENT OPTIONS ===\n";
echo "1. Visit https://bloxer.eskp.pl/deploy_login_attempts_table.php to auto-fix\n";
echo "2. Manually execute the SQL script via phpMyAdmin or MySQL client\n";
echo "3. Contact your hosting provider to run the SQL script\n";
?>
