<?php
/**
 * Database Setup Script for Bloxer Platform
 * Run this script to create missing tables and fix database issues
 */

require_once 'bootstrap.php';

echo "<h2>Bloxer Database Setup</h2>";

try {
    $auth = new AuthCore();
    $conn = $auth->getConnection();
    
    echo "<h3>Creating missing tables...</h3>";
    
    // Read and execute the SQL file
    $sql_file = 'database/create_missing_tables.sql';
    if (file_exists($sql_file)) {
        $sql = file_get_contents($sql_file);
        
        // Split SQL into individual statements
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                try {
                    $result = $conn->query($statement);
                    if ($result) {
                        echo "<p style='color: green;'>✓ Successfully executed: " . substr($statement, 0, 50) . "...</p>";
                    }
                } catch (Exception $e) {
                    echo "<p style='color: orange;'>⚠ Warning: " . $e->getMessage() . "</p>";
                }
            }
        }
    } else {
        echo "<p style='color: red;'>✗ SQL file not found: $sql_file</p>";
    }
    
    // Verify tables were created
    echo "<h3>Verifying tables...</h3>";
    $tables_to_check = ['categories', 'notifications', 'notification_preferences', 'app_versions', 'user_activity', 'visitor_tracking'];
    
    foreach ($tables_to_check as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows > 0) {
            echo "<p style='color: green;'>✓ Table '$table' exists</p>";
            
            // Show table info
            $info = $conn->query("SELECT COUNT(*) as count FROM $table");
            $count = $info->fetch_assoc()['count'];
            echo "<p style='color: blue;'>  - Records: $count</p>";
            
            // Check for download_count column in app_versions
            if ($table === 'app_versions') {
                $column_check = $conn->query("SHOW COLUMNS FROM app_versions LIKE 'download_count'");
                if ($column_check->num_rows > 0) {
                    echo "<p style='color: green;'>  ✓ download_count column exists</p>";
                } else {
                    echo "<p style='color: orange;'>  ⚠ download_count column missing</p>";
                }
            }
        } else {
            echo "<p style='color: red;'>✗ Table '$table' does not exist</p>";
        }
    }
    
    echo "<h3>Setup Complete!</h3>";
    echo "<p><a href='index.php'>Go to Bloxer Homepage</a></p>";
    echo "<p><a href='controllers/core/dashboard.php'>Go to Dashboard</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
