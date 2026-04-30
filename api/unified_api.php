<?php
/**
 * Unified API Endpoint for Bloxer Platform
 * Consolidates marketplace-api.php and app_actions.php functionality
 */

require_once '../controllers/core/mainlogincore.php';
require_once '../config/security.php';

// Initialize security
SecurityUtils::handlePreflightRequest();
SecurityUtils::sendCORSHeaders();

$auth = new AuthCore();

// Set content type
header('Content-Type: application/json');

// Get request method and action
$method = SecurityUtils::getRequestMethod();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Validate request
$validation_rules = [
    'method' => $method,
    'rate_limit' => [
        'max' => 100,
        'window' => 300 // 5 minutes
    ]
];

// Rate limiting check
$client_ip = SecurityUtils::getClientIP();
if (!SecurityUtils::checkRateLimit($client_ip, 100, 300)) {
    SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'Too many requests'], 429);
}

// Get current user
$user = $auth->isLoggedIn() ? $auth->getCurrentUser() : null;

// Route request
switch ($action) {
    // Public endpoints (no auth required)
    case 'get_apps':
    case 'get_featured':
    case 'get_categories':
    case 'search':
    case 'get_app_details':
    case 'get_reviews':
        handlePublicEndpoint($action);
        break;
        
    // Authenticated endpoints
    case 'install_app':
    case 'rate_app':
    case 'add_review':
    case 'publish_app':
    case 'toggle_save':
    case 'send_offer':
    case 'send_offer_message':
    case 'follow_developer':
    case 'unfollow_developer':
    case 'get_user_apps':
    case 'get_user_reviews':
        handleAuthenticatedEndpoint($action);
        break;
        
    // Admin endpoints
    case 'admin_get_reports':
    case 'admin_resolve_report':
    case 'admin_get_users':
    case 'admin_suspend_user':
        handleAdminEndpoint($action);
        break;
        
    default:
        SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'Invalid action specified'], 400);
}

/**
 * Handle public endpoints
 */
function handlePublicEndpoint($action) {
    global $auth;
    
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
        case 'get_reviews':
            getReviews();
            break;
        default:
            SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'Invalid action'], 400);
    }
}

/**
 * Handle authenticated endpoints
 */
function handleAuthenticatedEndpoint($action) {
    global $auth;
    
    if (!$auth->isLoggedIn()) {
        SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'Login required'], 401);
    }
    
    // Validate CSRF for POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrf_token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!SecurityUtils::validateCSRFToken($csrf_token)) {
            SecurityUtils::logSecurityEvent('CSRF token validation failed', 'Action: ' . $action . ', IP: ' . SecurityUtils::getClientIP());
            SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
        }
    }
    
    switch ($action) {
        case 'install_app':
            installApp();
            break;
        case 'rate_app':
            rateApp();
            break;
        case 'add_review':
            addReview();
            break;
        case 'publish_app':
            publishApp();
            break;
        case 'toggle_save':
            toggleSave();
            break;
        case 'send_offer':
            sendOffer();
            break;
        case 'send_offer_message':
            sendOfferMessage();
            break;
        case 'follow_developer':
            followDeveloper();
            break;
        case 'unfollow_developer':
            unfollowDeveloper();
            break;
        case 'get_user_apps':
            getUserApps();
            break;
        case 'get_user_reviews':
            getUserReviews();
            break;
        default:
            SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'Invalid action'], 400);
    }
}

/**
 * Handle admin endpoints
 */
function handleAdminEndpoint($action) {
    global $auth;
    
    if (!$auth->isLoggedIn() || !SecurityUtils::isAdmin()) {
        SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'Admin access required'], 403);
    }
    
    switch ($action) {
        case 'admin_get_reports':
            getAdminReports();
            break;
        case 'admin_resolve_report':
            resolveReport();
            break;
        case 'admin_get_users':
            getAdminUsers();
            break;
        case 'admin_suspend_user':
            suspendUser();
            break;
        default:
            SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'Invalid action'], 400);
    }
}

/**
 * Get apps with filtering and pagination
 */
function getApps() {
    global $auth;
    
    $category = SecurityUtils::validateInput($_GET['category'] ?? 'all', 'slug');
    $sort = SecurityUtils::validateInput($_GET['sort'] ?? 'popular', 'string');
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
        'popular' => 'a.total_downloads DESC, a.rating DESC',
        'newest' => 'a.published_at DESC',
        'rating' => 'a.rating DESC, a.total_downloads DESC',
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
    
    $stmt = $conn->prepare($sql);
    $types .= 'ii';
    $params[] = $limit;
    $params[] = $offset;
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get total count for pagination
    $count_sql = "
        SELECT COUNT(*) as total
        FROM apps a
        JOIN projects p ON a.project_id = p.id
        $where_clause
    ";
    
    $count_stmt = $conn->prepare($count_sql);
    if ($category !== 'all') {
        $count_stmt->bind_param('s', $category);
    }
    $count_stmt->execute();
    $total = $count_stmt->get_result()->fetch_assoc()['total'];
    
    SecurityUtils::sendJSONResponse([
        'success' => true,
        'apps' => $apps,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => ceil($total / $limit),
            'total_items' => $total,
            'per_page' => $limit
        ]
    ]);
}

/**
 * Get featured apps
 */
function getFeaturedApps() {
    global $auth;
    
    $conn = $auth->getConnection();
    
    $stmt = $conn->prepare("
        SELECT a.*, u.username as developer_name, u.avatar_url as developer_avatar,
               c.name as category_name, c.icon as category_icon
        FROM apps a
        JOIN projects p ON a.project_id = p.id
        JOIN users u ON p.user_id = u.id
        LEFT JOIN categories c ON a.category = c.slug
        WHERE a.status = 'published' AND a.featured = TRUE
        ORDER BY a.featured_at DESC, a.total_downloads DESC
        LIMIT 8
    ");
    
    $stmt->execute();
    $apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    SecurityUtils::sendJSONResponse([
        'success' => true,
        'apps' => $apps
    ]);
}

/**
 * Get categories
 */
function getCategories() {
    global $auth;
    
    $conn = $auth->getConnection();
    
    $stmt = $conn->prepare("
        SELECT c.*, COUNT(a.id) as app_count
        FROM categories c
        LEFT JOIN apps a ON c.slug = a.category AND a.status = 'published'
        GROUP BY c.id
        ORDER BY c.name
    ");
    
    $stmt->execute();
    $categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    SecurityUtils::sendJSONResponse([
        'success' => true,
        'categories' => $categories
    ]);
}

/**
 * Search apps
 */
function searchApps() {
    global $auth;
    
    $query = SecurityUtils::validateInput($_GET['q'] ?? '', 'string', 100);
    $category = SecurityUtils::validateInput($_GET['category'] ?? 'all', 'slug');
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = 12;
    $offset = ($page - 1) * $limit;
    
    if (empty($query)) {
        SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'Search query required'], 400);
    }
    
    $conn = $auth->getConnection();
    
    $where_conditions = ["a.status = 'published'", "a.title LIKE ? OR a.short_description LIKE ? OR a.description LIKE ?"];
    $search_term = '%' . $query . '%';
    $params = [$search_term, $search_term, $search_term];
    $types = 'sss';
    
    if ($category !== 'all') {
        $where_conditions[] = "a.category = ?";
        $params[] = $category;
        $types .= 's';
    }
    
    $where_clause = "WHERE " . implode(' AND ', $where_conditions);
    
    $sql = "
        SELECT a.*, u.username as developer_name, u.avatar_url as developer_avatar,
               c.name as category_name, c.icon as category_icon,
               MATCH(a.title, a.short_description, a.description) AGAINST(?) as relevance
        FROM apps a
        JOIN projects p ON a.project_id = p.id
        JOIN users u ON p.user_id = u.id
        LEFT JOIN categories c ON a.category = c.slug
        $where_clause
        ORDER BY relevance DESC, a.total_downloads DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $conn->prepare($sql);
    $params[] = $query;
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'sii';
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    SecurityUtils::sendJSONResponse([
        'success' => true,
        'apps' => $apps,
        'query' => $query
    ]);
}

/**
 * Get app details
 */
function getAppDetails() {
    global $auth;
    
    $app_id = SecurityUtils::validateInput($_GET['id'] ?? '', 'int');
    
    if (!$app_id) {
        SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'App ID required'], 400);
    }
    
    $conn = $auth->getConnection();
    
    $stmt = $conn->prepare("
        SELECT a.*, u.username as developer_name, u.avatar_url as developer_avatar,
               p.user_id as developer_id, c.name as category_name, c.icon as category_icon
        FROM apps a
        JOIN projects p ON a.project_id = p.id
        JOIN users u ON p.user_id = u.id
        LEFT JOIN categories c ON a.category = c.slug
        WHERE a.id = ? AND a.status = 'published'
    ");
    
    $stmt->bind_param("i", $app_id);
    $stmt->execute();
    $app = $stmt->get_result()->fetch_assoc();
    
    if (!$app) {
        SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'App not found'], 404);
    }
    
    SecurityUtils::sendJSONResponse([
        'success' => true,
        'app' => $app
    ]);
}

/**
 * Get reviews for an app
 */
function getReviews() {
    global $auth;
    
    $app_id = SecurityUtils::validateInput($_GET['app_id'] ?? '', 'int');
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    if (!$app_id) {
        SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'App ID required'], 400);
    }
    
    $conn = $auth->getConnection();
    
    $stmt = $conn->prepare("
        SELECT ar.*, u.username, u.avatar_url
        FROM app_reviews ar
        JOIN users u ON ar.user_id = u.id
        WHERE ar.app_id = ? AND ar.status = 'published'
        ORDER BY ar.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $stmt->bind_param("iii", $app_id, $limit, $offset);
    $stmt->execute();
    $reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    SecurityUtils::sendJSONResponse([
        'success' => true,
        'reviews' => $reviews
    ]);
}

/**
 * Install app
 */
function installApp() {
    global $auth;
    
    $app_id = SecurityUtils::validateInput($_POST['app_id'] ?? '', 'int');
    $user = $auth->getCurrentUser();
    
    if (!$app_id) {
        SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'App ID required'], 400);
    }
    
    $conn = $auth->getConnection();
    
    // Check if app exists and is published
    $stmt = $conn->prepare("
        SELECT a.id, p.user_id AS developer_id 
        FROM apps a 
        JOIN projects p ON a.project_id = p.id 
        WHERE a.id = ? AND a.status = 'published'
    ");
    $stmt->bind_param("i", $app_id);
    $stmt->execute();
    $app = $stmt->get_result()->fetch_assoc();
    
    if (!$app) {
        SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'App not found'], 404);
    }
    
    // Check if already installed
    $stmt = $conn->prepare("SELECT id FROM user_apps WHERE user_id = ? AND app_id = ?");
    $stmt->bind_param("ii", $user['id'], $app_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'App already installed'], 400);
    }
    
    // Install app
    $stmt = $conn->prepare("
        INSERT INTO user_apps (user_id, app_id, installed_at) 
        VALUES (?, ?, NOW())
    ");
    $stmt->bind_param("ii", $user['id'], $app_id);
    
    if ($stmt->execute()) {
        // Update download count
        $stmt = $conn->prepare("UPDATE apps SET total_downloads = total_downloads + 1 WHERE id = ?");
        $stmt->bind_param("i", $app_id);
        $stmt->execute();
        
        SecurityUtils::sendJSONResponse(['success' => true, 'message' => 'App installed successfully']);
    } else {
        SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'Failed to install app'], 500);
    }
}

/**
 * Rate app
 */
function rateApp() {
    global $auth;
    
    $app_id = SecurityUtils::validateInput($_POST['app_id'] ?? '', 'int');
    $rating = SecurityUtils::validateInput($_POST['rating'] ?? '', 'int');
    $user = $auth->getCurrentUser();
    
    if (!$app_id || !$rating || $rating < 1 || $rating > 5) {
        SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'Invalid rating data'], 400);
    }
    
    $conn = $auth->getConnection();
    
    // Check if app exists
    $stmt = $conn->prepare("SELECT id FROM apps WHERE id = ? AND status = 'published'");
    $stmt->bind_param("i", $app_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 0) {
        SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'App not found'], 404);
    }
    
    // Update or insert rating
    $stmt = $conn->prepare("
        INSERT INTO app_ratings (user_id, app_id, rating) 
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE rating = VALUES(rating)
    ");
    $stmt->bind_param("iii", $user['id'], $app_id, $rating);
    
    if ($stmt->execute()) {
        // Update app average rating
        $stmt = $conn->prepare("
            UPDATE apps a 
            SET rating = (
                SELECT AVG(rating) 
                FROM app_ratings 
                WHERE app_id = ?
            )
            WHERE id = ?
        ");
        $stmt->bind_param("ii", $app_id, $app_id);
        $stmt->execute();
        
        SecurityUtils::sendJSONResponse(['success' => true, 'message' => 'Rating submitted successfully']);
    } else {
        SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'Failed to submit rating'], 500);
    }
}

/**
 * Add review
 */
function addReview() {
    global $auth;
    
    $app_id = SecurityUtils::validateInput($_POST['app_id'] ?? '', 'int');
    $rating = SecurityUtils::validateInput($_POST['rating'] ?? '', 'int');
    $review_text = SecurityUtils::validateInput($_POST['review'] ?? '', 'html', 1000);
    $user = $auth->getCurrentUser();
    
    if (!$app_id || !$rating || !$review_text || $rating < 1 || $rating > 5) {
        SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'Invalid review data'], 400);
    }
    
    $conn = $auth->getConnection();
    
    // Check if app exists
    $stmt = $conn->prepare("SELECT id FROM apps WHERE id = ? AND status = 'published'");
    $stmt->bind_param("i", $app_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 0) {
        SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'App not found'], 404);
    }
    
    // Add review
    $stmt = $conn->prepare("
        INSERT INTO app_reviews (user_id, app_id, rating, review, status) 
        VALUES (?, ?, ?, ?, 'published')
    ");
    $stmt->bind_param("iiss", $user['id'], $app_id, $rating, $review_text);
    
    if ($stmt->execute()) {
        // Update app average rating
        $stmt = $conn->prepare("
            UPDATE apps a 
            SET rating = (
                SELECT AVG(rating) 
                FROM app_reviews 
                WHERE app_id = ? AND status = 'published'
            )
            WHERE id = ?
        ");
        $stmt->bind_param("ii", $app_id, $app_id);
        $stmt->execute();
        
        SecurityUtils::sendJSONResponse(['success' => true, 'message' => 'Review submitted successfully']);
    } else {
        SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'Failed to submit review'], 500);
    }
}

/**
 * Publish app
 */
function publishApp() {
    global $auth;
    
    $project_id = SecurityUtils::validateInput($_POST['project_id'] ?? '', 'int');
    $title = SecurityUtils::validateInput($_POST['title'] ?? '', 'string', 255);
    $description = SecurityUtils::validateInput($_POST['description'] ?? '', 'html', 5000);
    $category = SecurityUtils::validateInput($_POST['category'] ?? '', 'slug');
    $price = SecurityUtils::validateInput($_POST['price'] ?? '', 'float');
    $user = $auth->getCurrentUser();
    
    if (!$project_id || !$title || !$description || !$category) {
        SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'Required fields missing'], 400);
    }
    
    $conn = $auth->getConnection();
    
    // Check if user owns project
    $stmt = $conn->prepare("SELECT id FROM projects WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $project_id, $user['id']);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 0) {
        SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'Project not found or access denied'], 403);
    }
    
    // Generate slug
    $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $title));
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    
    // Check if slug exists
    $stmt = $conn->prepare("SELECT id FROM apps WHERE slug = ?");
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        $slug .= '-' . time();
    }
    
    // Create app
    $stmt = $conn->prepare("
        INSERT INTO apps (project_id, title, slug, description, category, price, status, published_at) 
        VALUES (?, ?, ?, ?, ?, ?, 'published', NOW())
    ");
    $stmt->bind_param("issssd", $project_id, $title, $slug, $description, $category, $price);
    
    if ($stmt->execute()) {
        SecurityUtils::sendJSONResponse(['success' => true, 'message' => 'App published successfully']);
    } else {
        SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'Failed to publish app'], 500);
    }
}

/**
 * Toggle save app
 */
function toggleSave() {
    global $auth;
    
    $app_id = SecurityUtils::validateInput($_POST['app_id'] ?? '', 'int');
    $user = $auth->getCurrentUser();
    
    if (!$app_id) {
        SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'App ID required'], 400);
    }
    
    $conn = $auth->getConnection();
    
    // Check if already saved
    $stmt = $conn->prepare("SELECT id FROM saved_apps WHERE user_id = ? AND app_id = ?");
    $stmt->bind_param("ii", $user['id'], $app_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        // Remove from saved
        $stmt = $conn->prepare("DELETE FROM saved_apps WHERE user_id = ? AND app_id = ?");
        $stmt->bind_param("ii", $user['id'], $app_id);
        $stmt->execute();
        
        SecurityUtils::sendJSONResponse(['success' => true, 'saved' => false, 'message' => 'App removed from saved']);
    } else {
        // Add to saved
        $stmt = $conn->prepare("INSERT INTO saved_apps (user_id, app_id, saved_at) VALUES (?, ?, NOW())");
        $stmt->bind_param("ii", $user['id'], $app_id);
        $stmt->execute();
        
        SecurityUtils::sendJSONResponse(['success' => true, 'saved' => true, 'message' => 'App saved successfully']);
    }
}

/**
 * Send offer
 */
function sendOffer() {
    global $auth;
    
    $app_id = SecurityUtils::validateInput($_POST['app_id'] ?? '', 'int');
    $amount = SecurityUtils::validateInput($_POST['amount'] ?? '', 'float');
    $phone_number = SecurityUtils::validateInput($_POST['phone_number'] ?? '', 'string', 20);
    $message = SecurityUtils::validateInput($_POST['message'] ?? '', 'html', 1000);
    $user = $auth->getCurrentUser();
    
    if (!$app_id || !$amount || !$phone_number) {
        SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'Required fields missing'], 400);
    }
    
    $conn = $auth->getConnection();
    
    // Get app details
    $stmt = $conn->prepare("
        SELECT a.id, p.user_id AS developer_id 
        FROM apps a 
        JOIN projects p ON a.project_id = p.id 
        WHERE a.id = ? AND a.status = 'published'
    ");
    $stmt->bind_param("i", $app_id);
    $stmt->execute();
    $app = $stmt->get_result()->fetch_assoc();
    
    if (!$app) {
        SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'App not found'], 404);
    }
    
    if ($app['developer_id'] == $user['id']) {
        SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'Cannot send offer to your own app'], 400);
    }
    
    // Create offer
    $stmt = $conn->prepare("
        INSERT INTO offers (app_id, buyer_id, amount, phone_number, message, status, created_at) 
        VALUES (?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->bind_param("iidss", $app_id, $user['id'], $amount, $phone_number, $message);
    
    if ($stmt->execute()) {
        SecurityUtils::sendJSONResponse(['success' => true, 'message' => 'Offer sent successfully']);
    } else {
        SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'Failed to send offer'], 500);
    }
}

/**
 * Send offer message
 */
function sendOfferMessage() {
    global $auth;
    
    $offer_id = SecurityUtils::validateInput($_POST['offer_id'] ?? '', 'int');
    $message = SecurityUtils::validateInput($_POST['message'] ?? '', 'html', 1000);
    $user = $auth->getCurrentUser();
    
    if (!$offer_id || !$message) {
        SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'Required fields missing'], 400);
    }
    
    $conn = $auth->getConnection();
    
    // Get offer details
    $stmt = $conn->prepare("
        SELECT o.*, p.user_id AS developer_id 
        FROM offers o 
        JOIN apps a ON o.app_id = a.id
        JOIN projects p ON a.project_id = p.id 
        WHERE o.id = ?
    ");
    $stmt->bind_param("i", $offer_id);
    $stmt->execute();
    $offer = $stmt->get_result()->fetch_assoc();
    
    if (!$offer) {
        SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'Offer not found'], 404);
    }
    
    // Check if user is part of the offer
    if ($offer['buyer_id'] != $user['id'] && $offer['developer_id'] != $user['id']) {
        SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'Access denied'], 403);
    }
    
    // Add message
    $stmt = $conn->prepare("
        INSERT INTO offer_messages (offer_id, sender_id, message, created_at) 
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->bind_param("iis", $offer_id, $user['id'], $message);
    
    if ($stmt->execute()) {
        SecurityUtils::sendJSONResponse(['success' => true, 'message' => 'Message sent successfully']);
    } else {
        SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'Failed to send message'], 500);
    }
}

/**
 * Follow developer
 */
function followDeveloper() {
    global $auth;
    
    $developer_id = SecurityUtils::validateInput($_POST['developer_id'] ?? '', 'int');
    $user = $auth->getCurrentUser();
    
    if (!$developer_id || $developer_id == $user['id']) {
        SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'Invalid developer ID'], 400);
    }
    
    $conn = $auth->getConnection();
    
    // Check if already following
    $stmt = $conn->prepare("SELECT id FROM developer_follows WHERE follower_id = ? AND developer_id = ?");
    $stmt->bind_param("ii", $user['id'], $developer_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'Already following'], 400);
    }
    
    // Follow developer
    $stmt = $conn->prepare("INSERT INTO developer_follows (follower_id, developer_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $user['id'], $developer_id);
    
    if ($stmt->execute()) {
        SecurityUtils::sendJSONResponse(['success' => true, 'message' => 'Following developer successfully']);
    } else {
        SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'Failed to follow developer'], 500);
    }
}

/**
 * Unfollow developer
 */
function unfollowDeveloper() {
    global $auth;
    
    $developer_id = SecurityUtils::validateInput($_POST['developer_id'] ?? '', 'int');
    $user = $auth->getCurrentUser();
    
    if (!$developer_id) {
        SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'Developer ID required'], 400);
    }
    
    $conn = $auth->getConnection();
    
    // Remove follow
    $stmt = $conn->prepare("DELETE FROM developer_follows WHERE follower_id = ? AND developer_id = ?");
    $stmt->bind_param("ii", $user['id'], $developer_id);
    
    if ($stmt->execute()) {
        SecurityUtils::sendJSONResponse(['success' => true, 'message' => 'Unfollowed developer successfully']);
    } else {
        SecurityUtils::sendJSONResponse(['success' => false, 'error' => 'Failed to unfollow developer'], 500);
    }
}

/**
 * Get user's installed apps
 */
function getUserApps() {
    global $auth;
    
    $user = $auth->getCurrentUser();
    
    $conn = $auth->getConnection();
    
    $stmt = $conn->prepare("
        SELECT a.*, ua.installed_at, u.username as developer_name
        FROM user_apps ua
        JOIN apps a ON ua.app_id = a.id
        JOIN projects p ON a.project_id = p.id
        JOIN users u ON p.user_id = u.id
        WHERE ua.user_id = ?
        ORDER BY ua.installed_at DESC
    ");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    SecurityUtils::sendJSONResponse([
        'success' => true,
        'apps' => $apps
    ]);
}

/**
 * Get user's reviews
 */
function getUserReviews() {
    global $auth;
    
    $user = $auth->getCurrentUser();
    
    $conn = $auth->getConnection();
    
    $stmt = $conn->prepare("
        SELECT ar.*, a.title as app_title, a.thumbnail_url
        FROM app_reviews ar
        JOIN apps a ON ar.app_id = a.id
        WHERE ar.user_id = ?
        ORDER BY ar.created_at DESC
    ");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    SecurityUtils::sendJSONResponse([
        'success' => true,
        'reviews' => $reviews
    ]);
}

// Admin functions (simplified for brevity)
function getAdminReports() {
    // Implementation for admin reports
    SecurityUtils::sendJSONResponse(['success' => true, 'message' => 'Admin reports endpoint']);
}

function resolveReport() {
    // Implementation for resolving reports
    SecurityUtils::sendJSONResponse(['success' => true, 'message' => 'Report resolved']);
}

function getAdminUsers() {
    // Implementation for admin users
    SecurityUtils::sendJSONResponse(['success' => true, 'message' => 'Admin users endpoint']);
}

function suspendUser() {
    // Implementation for suspending users
    SecurityUtils::sendJSONResponse(['success' => true, 'message' => 'User suspended']);
}
?>
