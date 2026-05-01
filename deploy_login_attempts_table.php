<?php
/**
 * Deploy login_attempts table to production server
 * This script creates the missing table on the production database
 */

// Production database configuration (will be loaded from .env)
require_once 'config/database.php';

echo "Deploying login_attempts table to production...\n";

try {
    $db = DatabaseConfig::getInstance();
    $conn = $db->getConnection();
    
    echo "Connected to production database.\n";
    echo "Database: " . $db->getConfig()['database'] . "\n";
    echo "Host: " . $db->getConfig()['host'] . "\n\n";
    
    // Check if table already exists
    $checkSql = "SHOW TABLES LIKE 'login_attempts'";
    $result = $db->fetch($checkSql);
    
    if ($result) {
        echo "✓ Table 'login_attempts' already exists on production.\n";
        
        // Verify table structure
        $structure = $db->fetchAll("DESCRIBE login_attempts");
        echo "\nCurrent table structure:\n";
        foreach ($structure as $column) {
            echo "- {$column['Field']} ({$column['Type']}) {$column['Null']} {$column['Key']}\n";
        }
    } else {
        echo "Creating login_attempts table on production...\n";
        
        // Create the table with proper engine and charset
        $createSql = "
        CREATE TABLE login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            attempt_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            
            INDEX idx_ip_address (ip_address),
            INDEX idx_attempt_time (attempt_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        try {
            $db->execute($createSql);
            echo "✓ Table 'login_attempts' created successfully on production.\n";
            
            // Verify creation
            $result = $db->fetch("SHOW TABLES LIKE 'login_attempts'");
            if ($result) {
                echo "✓ Table creation verified.\n";
            } else {
                echo "✗ Table creation verification failed.\n";
            }
            
        } catch (Exception $e) {
            echo "✗ Error creating table: " . $e->getMessage() . "\n";
            
            // Try alternative approach without ENGINE specification
            echo "Trying simplified table creation...\n";
            $simpleSql = "
            CREATE TABLE login_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                attempt_time DATETIME DEFAULT CURRENT_TIMESTAMP
            )
            ";
            
            try {
                $db->execute($simpleSql);
                echo "✓ Simple table created successfully.\n";
                
                // Add indexes separately
                try {
                    $db->execute("CREATE INDEX idx_ip_address ON login_attempts (ip_address)");
                    echo "✓ Added ip_address index.\n";
                } catch (Exception $indexError) {
                    echo "⚠ Warning: Could not create ip_address index: " . $indexError->getMessage() . "\n";
                }
                
                try {
                    $db->execute("CREATE INDEX idx_attempt_time ON login_attempts (attempt_time)");
                    echo "✓ Added attempt_time index.\n";
                } catch (Exception $indexError) {
                    echo "⚠ Warning: Could not create attempt_time index: " . $indexError->getMessage() . "\n";
                }
                
            } catch (Exception $simpleError) {
                echo "✗ Simple table creation also failed: " . $simpleError->getMessage() . "\n";
            }
        }
    }
    
    // Test the functionality that was failing
    echo "\nTesting the failing functionality...\n";
    try {
        $testIp = '127.0.0.1';
        
        // Test DELETE statement (the one that was failing)
        $stmt = $db->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
        if ($stmt) {
            echo "✓ Can prepare DELETE statement successfully.\n";
            
            // Test INSERT statement
            $stmt = $db->prepare("INSERT INTO login_attempts (ip_address) VALUES (?)");
            if ($stmt) {
                echo "✓ Can prepare INSERT statement successfully.\n";
            } else {
                echo "✗ Cannot prepare INSERT statement.\n";
            }
            
        } else {
            echo "✗ Cannot prepare DELETE statement.\n";
        }
        
    } catch (Exception $e) {
        echo "✗ Functionality test failed: " . $e->getMessage() . "\n";
    }
    
    echo "\nDeployment completed!\n";
    
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
    echo "Please check your database configuration in .env file.\n";
}
?>
