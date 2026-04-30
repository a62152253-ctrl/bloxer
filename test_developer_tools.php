<?php
/**
 * Test script for Developer Tools functionality
 * This will help diagnose what's not working
 */

require_once 'bootstrap.php';

echo "<h2>Developer Tools Diagnostic</h2>";

try {
    $auth = new AuthCore();
    
    // Check if user is logged in
    if (!$auth->isLoggedIn()) {
        echo "<p style='color: red;'>✗ User not logged in</p>";
        echo "<p><a href='controllers/auth/login.php'>Please login first</a></p>";
        exit();
    }
    
    $user = $auth->getCurrentUser();
    echo "<p style='color: green;'>✓ User logged in: " . htmlspecialchars($user['username']) . "</p>";
    
    // Check if user is developer
    if (!$auth->isDeveloper()) {
        echo "<p style='color: red;'>✗ User is not a developer</p>";
        echo "<p>Current user type: " . htmlspecialchars($user['user_type'] ?? 'unknown') . "</p>";
        exit();
    }
    
    echo "<p style='color: green;'>✓ User is developer</p>";
    
    $conn = $auth->getConnection();
    
    // Check if user has projects
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM projects WHERE user_id = ?");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $project_count = $stmt->get_result()->fetch_assoc()['count'];
    
    echo "<p>Projects owned: $project_count</p>";
    
    if ($project_count > 0) {
        echo "<p style='color: green;'>✓ User has projects</p>";
        
        // Get first project for testing
        $stmt = $conn->prepare("SELECT id, name FROM projects WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $project = $stmt->get_result()->fetch_assoc();
        
        echo "<p>Test project: " . htmlspecialchars($project['name']) . " (ID: " . $project['id'] . ")</p>";
        
        // Test Developer Tools URL
        $tools_url = "controllers/core/tools.php?project_id=" . $project['id'];
        echo "<p><a href='$tools_url' target='_blank'>Test Developer Tools</a></p>";
    } else {
        echo "<p style='color: orange;'>⚠ No projects found - create a project first</p>";
        echo "<p><a href='controllers/core/dashboard.php?page=workspace'>Create Project</a></p>";
    }
    
    // Check required tables
    echo "<h3>Required Tables Check:</h3>";
    $required_tables = ['projects', 'project_files', 'user_activity', 'visitor_tracking'];
    
    foreach ($required_tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows > 0) {
            echo "<p style='color: green;'>✓ Table '$table' exists</p>";
        } else {
            echo "<p style='color: red;'>✗ Table '$table' missing</p>";
        }
    }
    
    echo "<h3>Test Actions:</h3>";
    echo "<p><a href='setup_database_fixes.php' target='_blank'>Run Database Setup</a></p>";
    echo "<p><a href='controllers/core/tools.php' target='_blank'>Open Developer Tools</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
