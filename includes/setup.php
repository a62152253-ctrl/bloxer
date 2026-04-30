<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection for setup
$host = 'localhost';
$user = 'root';
$pass = '';

try {
    // Connect to MySQL server (without specifying database)
    $conn = new mysqli($host, $user, $pass);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    echo "<h2>Bloxer Database Setup</h2>";
    
    // Create database
    $sql = "CREATE DATABASE IF NOT EXISTS bloxer_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if ($conn->query($sql)) {
        echo "<p style='color: green;'>✓ Database 'bloxer_db' created successfully</p>";
    } else {
        echo "<p style='color: red;'>✗ Error creating database: " . $conn->error . "</p>";
    }
    
    // Select the database
    $conn->select_db('bloxer_db');
    
    // Create users table
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        reset_token VARCHAR(64) NULL,
        reset_expiry DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_username (username),
        INDEX idx_email (email),
        INDEX idx_reset_token (reset_token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql)) {
        echo "<p style='color: green;'>✓ Users table created successfully</p>";
    } else {
        echo "<p style='color: red;'>✗ Error creating users table: " . $conn->error . "</p>";
    }
    
    echo "<h3>Setup Complete!</h3>";
    echo "<p><a href='../controllers/auth/login.php' style='color: #2563eb; text-decoration: none; font-weight: bold;'>Go to Login Page</a></p>";
    echo "<p><a href='../controllers/auth/register.php' style='color: #2563eb; text-decoration: none; font-weight: bold;'>Go to Register Page</a></p>";
    
    $conn->close();
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Database setup error: " . $e->getMessage() . "</p>";
    echo "<p>Please make sure MySQL/XAMPP is running and the credentials are correct.</p>";
}
?>

<style>
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #0f1419;
    color: #ffffff;
    padding: 40px;
    line-height: 1.6;
}
h2, h3 {
    color: #2563eb;
    margin-bottom: 20px;
}
p {
    margin-bottom: 10px;
}
</style>
