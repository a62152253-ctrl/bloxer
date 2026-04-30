<?php
// Marketplace API Handler
require_once '../controllers/core/mainlogincore.php';

$auth = new AuthCore();

header('Content-Type: application/json');

// Handle API requests
$action = $_GET['action'] ?? '';
$user = $auth->getCurrentUser();

switch ($action) {
    case 'get_apps':
        getApps();
        break;
        
    case 'get_featured':
        getFeaturedApps();
        break;
        
    case 'get_categories':
        getCategories();
        break;
        
    case 'search':
        searchApps();
        break;
        
    case 'get_app_details':
        getAppDetails();
        break;
        
    case 'install_app':
        installApp();
        break;
        
    case 'rate_app':
        rateApp();
        break;
        
    case 'get_reviews':
        getReviews();
        break;
        
    case 'add_review':
        addReview();
        break;
        
    case 'publish_app':
        publishApp();
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action specified']);
}

function getApps() {
    global $auth;
    
    $category = $_GET['category'] ?? 'all';
    $sort = $_GET['sort'] ?? 'popular';
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = 12;
    $offset = ($page - 1) * $limit;
    
    $conn = $auth->getConnection();
    $where_conditions = ["a.status = 'published'"];
    $params = [];
    $types = '';
    
    if ($category !== 'all') {
        $where_conditions[] = "a.category = ?";
        $params[] = $category;
        $types .= 's';
    }
    
    $where_clause = "WHERE " . implode(' AND ', $where_conditions);
    
    $sort_options = [
        'popular' => 'a.download_count DESC, a.rating DESC',
        'newest' => 'a.published_at DESC',
        'rating' => 'a.rating DESC, a.download_count DESC',
        'name' => 'a.title ASC',
        'price_asc' => 'a.price ASC',
        'price_desc' => 'a.price DESC',
        'updated' => 'a.updated_at DESC'
    ];
    $order_by = $sort_options[$sort] ?? $sort_options['popular'];
    
    $sql = "
        SELECT a.*, u.username as developer_name, u.avatar_url as developer_avatar,
               c.name as category_name, c.icon as category_icon
        FROM apps a
        JOIN projects p ON a.project_id = p.id
        JOIN users u ON p.user_id = u.id
        LEFT JOIN categories c ON a.category = c.slug
        $where_clause
        ORDER BY $order_by
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode(['success' => true, 'apps' => $apps]);
}

function getFeaturedApps() {
    global $auth;
    
    $conn = $auth->getConnection();
    $stmt = $conn->prepare("
        SELECT a.*, u.username as developer_name, u.avatar_url as developer_avatar, u.email as developer_email,
               c.name as category_name, c.icon as category_icon
        FROM apps a
        JOIN projects p ON a.project_id = p.id
        JOIN users u ON p.user_id = u.id
        LEFT JOIN categories c ON a.category = c.slug
        WHERE a.status = 'featured' 
        ORDER BY a.rating DESC, a.download_count DESC
        LIMIT 6
    ");
    $stmt->execute();
    $apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode(['success' => true, 'apps' => $apps]);
}

function getCategories() {
    global $auth;
    
    $conn = $auth->getConnection();
    $stmt = $conn->prepare("SELECT * FROM categories WHERE is_active = true ORDER BY sort_order");
    $stmt->execute();
    $categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode(['success' => true, 'categories' => $categories]);
}

function searchApps() {
    global $auth;
    
    $query = $_GET['q'] ?? '';
    $category = $_GET['category'] ?? 'all';
    $sort = $_GET['sort'] ?? 'popular';
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = 12;
    $offset = ($page - 1) * $limit;
    
    if (empty($query)) {
        echo json_encode(['success' => false, 'error' => 'Search query is required']);
        return;
    }
    
    $conn = $auth->getConnection();
    $where_conditions = ["a.status = 'published'"];
    $params = [];
    $types = '';
    
    if ($category !== 'all') {
        $where_conditions[] = "a.category = ?";
        $params[] = $category;
        $types .= 's';
    }
    
    $where_conditions[] = "(MATCH(a.title, a.description, a.short_description) AGAINST(?) OR a.title LIKE ? OR a.description LIKE ?)";
    $params[] = $query;
    $params[] = "%$query%";
    $params[] = "%$query%";
    $types .= 'sss';
    
    $where_clause = "WHERE " . implode(' AND ', $where_conditions);
    
    $sort_options = [
        'relevant' => "MATCH(a.title, a.description, a.short_description) AGAINST(?) DESC",
        'popular' => 'a.download_count DESC, a.rating DESC',
        'newest' => 'a.published_at DESC',
        'rating' => 'a.rating DESC, a.download_count DESC'
    ];
    $order_by = $sort_options[$sort] ?? $sort_options['popular'];
    
    if ($sort === 'relevant') {
        $params[] = $query;
        $types .= 's';
    }
    
    $sql = "
        SELECT a.*, u.username as developer_name, u.avatar_url as developer_avatar, u.email as developer_email,
               c.name as category_name, c.icon as category_icon
        FROM apps a
        JOIN users u ON a.project_id IN (SELECT id FROM projects WHERE user_id = u.id)
        LEFT JOIN categories c ON a.category = c.slug
        $where_clause
        ORDER BY $order_by
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode(['success' => true, 'apps' => $apps]);
}

function getAppDetails() {
    global $auth;
    
    $app_id = intval($_GET['id'] ?? 0);
    
    if ($app_id === 0) {
        echo json_encode(['success' => false, 'error' => 'App ID is required']);
        return;
    }
    
    $conn = $auth->getConnection();
    $stmt = $conn->prepare("
        SELECT a.*, u.username as developer_name, u.avatar_url as developer_avatar,
               c.name as category_name, c.icon as category_icon
        FROM apps a
        JOIN users u ON a.project_id IN (SELECT id FROM projects WHERE user_id = u.id)
        LEFT JOIN categories c ON a.category = c.slug
        WHERE a.id = ? AND a.status IN ('published', 'featured')
    ");
    $stmt->bind_param("i", $app_id);
    $stmt->execute();
    $app = $stmt->get_result()->fetch_assoc();
    
    if (!$app) {
        echo json_encode(['success' => false, 'error' => 'App not found']);
        return;
    }
    
    // Get screenshots
    $stmt = $conn->prepare("SELECT * FROM app_screenshots WHERE app_id = ? ORDER BY sort_order");
    $stmt->bind_param("i", $app_id);
    $stmt->execute();
    $screenshots = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get version history
    $stmt = $conn->prepare("SELECT * FROM app_versions WHERE app_id = ? ORDER BY version_date DESC LIMIT 5");
    $stmt->bind_param("i", $app_id);
    $stmt->execute();
    $versions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Check if user has installed this app
    $is_installed = false;
    if ($auth->isLoggedIn()) {
        $stmt = $conn->prepare("SELECT id FROM user_apps WHERE user_id = ? AND app_id = ?");
        $stmt->bind_param("ii", $user['id'], $app_id);
        $stmt->execute();
        $is_installed = $stmt->get_result()->num_rows > 0;
    }
    
    $app['screenshots'] = $screenshots;
    $app['versions'] = $versions;
    $app['is_installed'] = $is_installed;
    
    echo json_encode(['success' => true, 'app' => $app]);
}

function installApp() {
    global $auth;
    
    if (!$auth->isLoggedIn()) {
        echo json_encode(['success' => false, 'error' => 'You must be logged in to install apps']);
        return;
    }
    
    $app_id = intval($_POST['app_id'] ?? 0);
    $user = $auth->getCurrentUser();
    
    if ($app_id === 0) {
        echo json_encode(['success' => false, 'error' => 'App ID is required']);
        return;
    }
    
    $conn = $auth->getConnection();
    
    // Check if app exists and is published
    $stmt = $conn->prepare("SELECT id, title FROM apps WHERE id = ? AND status IN ('published', 'featured')");
    $stmt->bind_param("i", $app_id);
    $stmt->execute();
    $app = $stmt->get_result()->fetch_assoc();
    
    if (!$app) {
        echo json_encode(['success' => false, 'error' => 'App not found']);
        return;
    }
    
    // Check if already installed
    $stmt = $conn->prepare("SELECT id FROM user_apps WHERE user_id = ? AND app_id = ?");
    $stmt->bind_param("ii", $user['id'], $app_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'App already installed']);
        return;
    }
    
    // Install app
    $stmt = $conn->prepare("INSERT INTO user_apps (user_id, app_id, installed_at) VALUES (?, ?, NOW())");
    $stmt->bind_param("ii", $user['id'], $app_id);
    
    if ($stmt->execute()) {
        // Update download count
        $stmt = $conn->prepare("UPDATE apps SET download_count = download_count + 1 WHERE id = ?");
        $stmt->bind_param("i", $app_id);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'App installed successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to install app']);
    }
}

function rateApp() {
    global $auth;
    
    if (!$auth->isLoggedIn()) {
        echo json_encode(['success' => false, 'error' => 'You must be logged in to rate apps']);
        return;
    }
    
    $app_id = intval($_POST['app_id'] ?? 0);
    $rating = intval($_POST['rating'] ?? 0);
    $user = $auth->getCurrentUser();
    
    if ($app_id === 0 || $rating < 1 || $rating > 5) {
        echo json_encode(['success' => false, 'error' => 'Invalid app ID or rating']);
        return;
    }
    
    $conn = $auth->getConnection();
    
    // Check if user has installed the app
    $stmt = $conn->prepare("SELECT id FROM user_apps WHERE user_id = ? AND app_id = ?");
    $stmt->bind_param("ii", $user['id'], $app_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'You must install the app before rating']);
        return;
    }
    
    // Check if already rated
    $stmt = $conn->prepare("SELECT rating FROM app_ratings WHERE user_id = ? AND app_id = ?");
    $stmt->bind_param("ii", $user['id'], $app_id);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    
    if ($existing) {
        // Update existing rating
        $stmt = $conn->prepare("UPDATE app_ratings SET rating = ?, rated_at = NOW() WHERE user_id = ? AND app_id = ?");
        $stmt->bind_param("iii", $rating, $user['id'], $app_id);
    } else {
        // Add new rating
        $stmt = $conn->prepare("INSERT INTO app_ratings (user_id, app_id, rating, rated_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iii", $user['id'], $app_id, $rating);
    }
    
    if ($stmt->execute()) {
        // Update app average rating
        $stmt = $conn->prepare("UPDATE apps SET rating = (SELECT AVG(rating) FROM app_ratings WHERE app_id = ?) WHERE id = ?");
        $stmt->bind_param("ii", $app_id, $app_id);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Rating submitted successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to submit rating']);
    }
}

function getReviews() {
    $app_id = intval($_GET['app_id'] ?? 0);
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    if ($app_id === 0) {
        echo json_encode(['success' => false, 'error' => 'App ID is required']);
        return;
    }
    
    global $auth;
    $conn = $auth->getConnection();
    
    $stmt = $conn->prepare("
        SELECT r.*, u.username, u.avatar_url
        FROM app_ratings r
        JOIN users u ON r.user_id = u.id
        WHERE r.app_id = ?
        ORDER BY r.rated_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("iii", $app_id, $limit, $offset);
    $stmt->execute();
    $reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode(['success' => true, 'reviews' => $reviews]);
}

function addReview() {
    global $auth;
    
    if (!$auth->isLoggedIn()) {
        echo json_encode(['success' => false, 'error' => 'You must be logged in to review apps']);
        return;
    }
    
    $app_id = intval($_POST['app_id'] ?? 0);
    $rating = intval($_POST['rating'] ?? 0);
    $review_text = trim($_POST['review_text'] ?? '');
    $user = $auth->getCurrentUser();
    
    if ($app_id === 0 || $rating < 1 || $rating > 5) {
        echo json_encode(['success' => false, 'error' => 'Invalid app ID or rating']);
        return;
    }
    
    if (empty($review_text)) {
        echo json_encode(['success' => false, 'error' => 'Review text is required']);
        return;
    }
    
    $conn = $auth->getConnection();
    
    // Check if user has installed the app
    $stmt = $conn->prepare("SELECT id FROM user_apps WHERE user_id = ? AND app_id = ?");
    $stmt->bind_param("ii", $user['id'], $app_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'You must install the app before reviewing']);
        return;
    }
    
    // Add review
    $stmt = $conn->prepare("
        INSERT INTO app_reviews (user_id, app_id, rating, review_text, created_at) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("iiis", $user['id'], $app_id, $rating, $review_text);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Review submitted successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to submit review']);
    }
}

function publishApp() {
    global $auth;
    
    if (!$auth->isLoggedIn()) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        return;
    }
    
    if (!$auth->isDeveloper()) {
        echo json_encode(['success' => false, 'error' => 'Not a developer']);
        return;
    }
    
    $user = $auth->getCurrentUser();
    $project_id = $_POST['project_id'] ?? null;
    $app_name = $_POST['app_name'] ?? null;
    $description = $_POST['description'] ?? null;
    $category = $_POST['category'] ?? 'tools';
    $tags = $_POST['tags'] ?? '';
    $is_free = isset($_POST['is_free']) ? 1 : 0;
    $content = $_POST['content'] ?? '';
    
    if (!$project_id || !$app_name || !$description) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return;
    }
    
    try {
        $conn = $auth->getConnection();
        
        // Verify project ownership
        $stmt = $conn->prepare("SELECT id FROM projects WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $project_id, $user['id']);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows === 0) {
            echo json_encode(['success' => false, 'error' => 'Project not found']);
            return;
        }
        
        // Check if app already exists for this project
        $stmt = $conn->prepare("SELECT id FROM apps WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'error' => 'App already published for this project']);
            return;
        }
        
        // Generate slug from app name
        $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $app_name));
        $slug = rtrim($slug, '-');
        
        // Insert app into marketplace
        $stmt = $conn->prepare("
            INSERT INTO apps (
                project_id, title, slug, description, category, 
                tags, is_free, demo_url, status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'draft', NOW(), NOW())
        ");
        
        $stmt->bind_param("isssssis", $project_id, $app_name, $slug, $description, 
                         $category, $tags, $is_free, $content);
        
        if ($stmt->execute()) {
            $app_id = $conn->insert_id;
            
            echo json_encode([
                'success' => true,
                'app_id' => $app_id,
                'message' => 'App submitted for review and will be published soon'
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to publish app']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Database error during apps fetch: ' . $e->getMessage()]);
    }
}
?>
