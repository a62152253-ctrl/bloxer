<?php
require_once 'bootstrap.php';

echo "Creating test user and missing tables...\n";

try {
    $auth = new AuthCore();
    
    // Create missing tables
    echo "Creating missing tables...\n";
    if ($auth->createTables()) {
        echo "Tables created successfully\n";
    } else {
        echo "Failed to create tables\n";
    }
    
    // Create a test user
    echo "Creating test user...\n";
    $result = $auth->register('testuser', 'test@example.com', 'password123', 'password123', 'user');
    
    if ($result['success']) {
        echo "Test user created successfully\n";
        echo "Username: testuser\n";
        echo "Password: password123\n";
        echo "Email: test@example.com\n";
    } else {
        echo "Failed to create test user: " . implode(", ", $result['errors']) . "\n";
    }
    
    // Create a test developer
    echo "Creating test developer...\n";
    $result = $auth->register('devuser', 'dev@example.com', 'password123', 'password123', 'developer');
    
    if ($result['success']) {
        echo "Test developer created successfully\n";
        echo "Username: devuser\n";
        echo "Password: password123\n";
        echo "Email: dev@example.com\n";
    } else {
        echo "Failed to create test developer: " . implode(", ", $result['errors']) . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
