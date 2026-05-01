<?php
require_once 'bootstrap.php';

echo "Testing database connection...\n";

try {
    $db = DatabaseConfig::getInstance();
    $conn = $db->getConnection();
    
    if ($conn) {
        echo "Database connection: SUCCESS\n";
        
        // Test if users table exists
        $stmt = $conn->query("SHOW TABLES LIKE 'users'");
        if ($stmt->rowCount() > 0) {
            echo "Users table: EXISTS\n";
            
            // Check table structure
            $stmt = $conn->query("DESCRIBE users");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "Users table columns:\n";
            foreach ($columns as $column) {
                echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
            }
            
            // Check if there are any users
            $stmt = $conn->query("SELECT COUNT(*) as count FROM users");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "Number of users: " . $result['count'] . "\n";
            
        } else {
            echo "Users table: MISSING\n";
        }
        
        // Test if login_attempts table exists
        $stmt = $conn->query("SHOW TABLES LIKE 'login_attempts'");
        if ($stmt->rowCount() > 0) {
            echo "Login attempts table: EXISTS\n";
        } else {
            echo "Login attempts table: MISSING\n";
        }
        
    } else {
        echo "Database connection: FAILED\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
