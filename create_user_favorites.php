<?php
// Database connection
$host = 'localhost';
$user = 'root';
$password = '';
$database = 'bloxer_db';

try {
    $conn = new mysqli($host, $user, $password, $database);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Create user_favorites table
    $sql = "CREATE TABLE user_favorites (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        app_id INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (app_id) REFERENCES apps(id) ON DELETE CASCADE,
        UNIQUE KEY unique_user_favorite (user_id, app_id),
        INDEX idx_user_id (user_id),
        INDEX idx_app_id (app_id)
    )";
    
    if ($conn->query($sql)) {
        echo "Table user_favorites created successfully";
    } else {
        echo "Error creating table: " . $conn->error;
    }
    
    $conn->close();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
