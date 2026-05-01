<?php
require_once '../controllers/core/mainlogincore.php';
require_once '../config/security.php';

header('Content-Type: application/json');

$auth = new AuthCore();

if (!$auth->isLoggedIn()) {
    SecurityUtils::safeExit(['success' => false, 'error' => 'Authentication required - please log in'], 401, 'warning');
}

if (!$auth->isDeveloper()) {
    SecurityUtils::safeExit(['success' => false, 'error' => 'Developer privileges required'], 403, 'warning');
}

// Rate limiting check
if (!SecurityUtils::checkRateLimit('api_get_projects', 60, 60)) { // 60 requests per minute
    SecurityUtils::logSecurityEvent('Rate limit exceeded', "Get projects API");
    SecurityUtils::safeExit(['success' => false, 'error' => 'Rate limit exceeded'], 429, 'warning');
}

try {
    $user = $auth->getCurrentUser();
    $conn = $auth->getConnection();
    
    // Get only safe project information
    $stmt = $conn->prepare("
        SELECT p.id, p.name, p.description, p.status, p.created_at, p.updated_at, 
               COUNT(pf.id) as file_count 
        FROM projects p 
        LEFT JOIN project_files pf ON p.id = pf.project_id 
        WHERE p.user_id = ? 
        GROUP BY p.id 
        ORDER BY p.updated_at DESC
        LIMIT 50
    ");
    
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Sanitize output
    $safe_projects = [];
    foreach ($projects as $project) {
        $safe_projects[] = [
            'id' => (int)$project['id'],
            'name' => htmlspecialchars($project['name']),
            'description' => htmlspecialchars($project['description']),
            'status' => htmlspecialchars($project['status']),
            'created_at' => $project['created_at'],
            'updated_at' => $project['updated_at'],
            'file_count' => (int)$project['file_count']
        ];
    }
    
    SecurityUtils::safeExit([
        'success' => true,
        'projects' => $safe_projects,
        'count' => count($safe_projects)
    ], 200, 'success');
    
} catch (Exception $e) {
    SecurityUtils::logSecurityEvent('Database error in get_projects', "Error: " . $e->getMessage());
    SecurityUtils::safeExit([
        'success' => false,
        'error' => 'Database error occurred'
    ], 500, 'error');
}
?>
