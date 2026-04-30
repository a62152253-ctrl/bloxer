<?php
require_once 'bootstrap.php';

$auth = new AuthCore();
if ($auth->isLoggedIn()) {
    $user = $auth->getCurrentUser();
    $conn = $auth->getConnection();
    
    echo "<h3>User: " . htmlspecialchars($user['username']) . " (ID: " . $user['id'] . ")</h3>";
    
    // Check all projects
    $stmt = $conn->prepare("SELECT id, name, status, framework, created_at FROM projects WHERE user_id = ? ORDER BY updated_at DESC");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo "<h4>All Projects (" . count($projects) . "):</h4>";
    if (!empty($projects)) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Name</th><th>Status</th><th>Framework</th><th>Created</th></tr>";
        foreach ($projects as $project) {
            echo "<tr>";
            echo "<td>" . $project['id'] . "</td>";
            echo "<td>" . htmlspecialchars($project['name']) . "</td>";
            echo "<td>" . htmlspecialchars($project['status']) . "</td>";
            echo "<td>" . htmlspecialchars($project['framework']) . "</td>";
            echo "<td>" . $project['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No projects found.</p>";
    }
    
    // Check unpublished projects specifically
    $stmt = $conn->prepare("SELECT id, name, status FROM projects WHERE user_id = ? AND status != 'published'");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $unpublished = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo "<h4>Unpublished Projects (" . count($unpublished) . "):</h4>";
    if (!empty($unpublished)) {
        foreach ($unpublished as $project) {
            echo "<p>- " . htmlspecialchars($project['name']) . " (Status: " . htmlspecialchars($project['status']) . ")</p>";
        }
    } else {
        echo "<p>No unpublished projects available for publishing.</p>";
    }
    
    // Check if there are any sample projects to create
    echo "<h4>Actions:</h4>";
    echo "<p><a href='../controllers/projects/projects.php'>Go to Projects</a> | <a href='../controllers/core/dashboard.php?page=workspace'>Create New Project</a></p>";
    
} else {
    echo "<p>Not logged in. <a href='../controllers/auth/login.php'>Login here</a></p>";
}
?>
