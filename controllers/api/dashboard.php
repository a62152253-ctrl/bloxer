<?php
require_once __DIR__ . '/../../bootstrap.php';

header('Content-Type: application/json');

$auth = new AuthCore();

if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user = $auth->getCurrentUser();
$page = $_GET['page'] ?? '';

try {
    switch ($page) {
        case 'publish':
            // Handle publish page data
            $conn = $auth->getConnection();
            
            // Get user's projects
            $stmt = $conn->prepare("
                SELECT p.*, COUNT(pf.id) as file_count 
                FROM projects p 
                LEFT JOIN project_files pf ON p.id = pf.project_id 
                WHERE p.user_id = ? AND p.status = 'draft'
                GROUP BY p.id 
                ORDER BY p.updated_at DESC
            ");
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
            $projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            // Get categories
            $stmt = $conn->prepare("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order");
            $stmt->execute();
            $categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'projects' => $projects,
                    'categories' => $categories
                ]
            ]);
            break;
            
        case 'workspace':
            // Handle workspace page data
            $project_id = $_GET['project_id'] ?? null;
            
            if (!$project_id) {
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'project' => null,
                        'files' => [],
                        'message' => 'No project selected'
                    ]
                ]);
                exit();
            }
            
            $conn = $auth->getConnection();
            
            // Get project details
            $stmt = $conn->prepare("
                SELECT p.*, COUNT(pf.id) as file_count 
                FROM projects p 
                LEFT JOIN project_files pf ON p.id = pf.project_id 
                WHERE p.id = ? AND p.user_id = ?
                GROUP BY p.id
            ");
            $stmt->bind_param("ii", $project_id, $user['id']);
            $stmt->execute();
            $project = $stmt->get_result()->fetch_assoc();
            
            if (!$project) {
                http_response_code(404);
                echo json_encode(['error' => 'Project not found']);
                exit();
            }
            
            // Get project files
            $stmt = $conn->prepare("
                SELECT * FROM project_files 
                WHERE project_id = ? 
                ORDER BY type, name
            ");
            $stmt->bind_param("i", $project_id);
            $stmt->execute();
            $files = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'project' => $project,
                    'files' => $files
                ]
            ]);
            break;
            
        case 'projects':
            // Handle projects page data
            $conn = $auth->getConnection();
            
            // Get user's projects with stats
            $stmt = $conn->prepare("
                SELECT p.*, 
                       COUNT(pf.id) as file_count,
                       COUNT(DISTINCT v.id) as version_count,
                       SUM(v.download_count) as total_downloads
                FROM projects p 
                LEFT JOIN project_files pf ON p.id = pf.project_id 
                LEFT JOIN app_versions v ON p.id = v.project_id
                WHERE p.user_id = ? 
                GROUP BY p.id 
                ORDER BY p.updated_at DESC
            ");
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
            $projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            // Get project statistics
            $stats = [
                'total_projects' => count($projects),
                'published_projects' => count(array_filter($projects, fn($p) => $p['status'] === 'published')),
                'draft_projects' => count(array_filter($projects, fn($p) => $p['status'] === 'draft')),
                'total_downloads' => array_sum(array_column($projects, 'total_downloads'))
            ];
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'projects' => $projects,
                    'stats' => $stats
                ]
            ]);
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Page not found']);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}
?>
