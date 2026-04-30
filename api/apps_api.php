<?php
/**
 * Apps API - Unified endpoint for app-related operations
 * Extends APIBase for security and common functionality
 */

require_once 'api_base.php';

class AppsAPI extends APIBase {
    
    public function __construct() {
        parent::__construct();
        $this->init();
    }
    
    /**
     * Route requests
     */
    public function route() {
        $action = $this->getAction();
        
        switch ($action) {
            // Public endpoints
            case 'get_apps':
                $this->getApps();
                break;
            case 'get_featured':
                $this->getFeaturedApps();
                break;
            case 'get_categories':
                $this->getCategories();
                break;
            case 'search':
                $this->searchApps();
                break;
            case 'get_app_details':
                $this->getAppDetails();
                break;
            case 'get_reviews':
                $this->getReviews();
                break;
                
            // Authenticated endpoints
            case 'install_app':
                $this->installApp();
                break;
            case 'rate_app':
                $this->rateApp();
                break;
            case 'add_review':
                $this->addReview();
                break;
            case 'publish_app':
                $this->publishApp();
                break;
            case 'toggle_save':
                $this->toggleSave();
                break;
            case 'send_offer':
                $this->sendOffer();
                break;
            case 'send_offer_message':
                $this->sendOfferMessage();
                break;
                
            default:
                $this->sendJSONResponse(['success' => false, 'error' => 'Invalid action'], 400);
        }
    }
    
    /**
     * Get apps with filtering and pagination
     */
    private function getApps() {
        $category = $this->validateInput($_GET['category'] ?? 'all', 'slug');
        $sort = $this->validateInput($_GET['sort'] ?? 'popular', 'string');
        $pagination = $this->getPagination(12, 50);
        
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
        
        $stmt = $this->conn->prepare($sql);
        $types .= 'ii';
        $params[] = $pagination['limit'];
        $params[] = $pagination['offset'];
        
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
        
        $count_stmt = $this->conn->prepare($count_sql);
        if ($category !== 'all') {
            $count_stmt->bind_param('s', $category);
        }
        $count_stmt->execute();
        $total = $count_stmt->get_result()->fetch_assoc()['total'];
        
        $this->sendJSONResponse([
            'success' => true,
            'apps' => $apps,
            'pagination' => $this->buildPaginationResponse($total, $pagination['page'], $pagination['limit'])
        ]);
    }
    
    /**
     * Get featured apps
     */
    private function getFeaturedApps() {
        $stmt = $this->conn->prepare("
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
        
        $this->sendJSONResponse([
            'success' => true,
            'apps' => $apps
        ]);
    }
    
    /**
     * Get categories
     */
    private function getCategories() {
        $stmt = $this->conn->prepare("
            SELECT c.*, COUNT(a.id) as app_count
            FROM categories c
            LEFT JOIN apps a ON c.slug = a.category AND a.status = 'published'
            GROUP BY c.id
            ORDER BY c.name
        ");
        
        $stmt->execute();
        $categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $this->sendJSONResponse([
            'success' => true,
            'categories' => $categories
        ]);
    }
    
    /**
     * Search apps
     */
    private function searchApps() {
        $query = $this->validateInput($_GET['q'] ?? '', 'string', 100);
        $category = $this->validateInput($_GET['category'] ?? 'all', 'slug');
        $pagination = $this->getPagination(12, 50);
        
        if (empty($query)) {
            $this->sendJSONResponse(['success' => false, 'error' => 'Search query required'], 400);
        }
        
        if ($this->detectAttackPatterns($query)) {
            $this->logSecurityEvent('Potential XSS attack in search', 'Query: ' . $query);
            $this->sendJSONResponse(['success' => false, 'error' => 'Invalid search query'], 400);
        }
        
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
                   c.name as category_name, c.icon as category_icon
            FROM apps a
            JOIN projects p ON a.project_id = p.id
            JOIN users u ON p.user_id = u.id
            LEFT JOIN categories c ON a.category = c.slug
            $where_clause
            ORDER BY a.total_downloads DESC, a.rating DESC
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $this->conn->prepare($sql);
        $params[] = $pagination['limit'];
        $params[] = $pagination['offset'];
        $types .= 'ii';
        
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $this->sendJSONResponse([
            'success' => true,
            'apps' => $apps,
            'query' => $query,
            'pagination' => $this->buildPaginationResponse(count($apps), $pagination['page'], $pagination['limit'])
        ]);
    }
    
    /**
     * Get app details
     */
    private function getAppDetails() {
        $app_id = $this->validateInput($_GET['id'] ?? '', 'int');
        
        if (!$app_id) {
            $this->sendJSONResponse(['success' => false, 'error' => 'App ID required'], 400);
        }
        
        $stmt = $this->conn->prepare("
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
            $this->sendJSONResponse(['success' => false, 'error' => 'App not found'], 404);
        }
        
        $this->sendJSONResponse([
            'success' => true,
            'app' => $app
        ]);
    }
    
    /**
     * Get reviews for an app
     */
    private function getReviews() {
        $app_id = $this->validateInput($_GET['app_id'] ?? '', 'int');
        $pagination = $this->getPagination(10, 50);
        
        if (!$app_id) {
            $this->sendJSONResponse(['success' => false, 'error' => 'App ID required'], 400);
        }
        
        $stmt = $this->conn->prepare("
            SELECT ar.*, u.username, u.avatar_url
            FROM app_reviews ar
            JOIN users u ON ar.user_id = u.id
            WHERE ar.app_id = ? AND ar.status = 'published'
            ORDER BY ar.created_at DESC
            LIMIT ? OFFSET ?
        ");
        
        $stmt->bind_param("iii", $app_id, $pagination['limit'], $pagination['offset']);
        $stmt->execute();
        $reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $this->sendJSONResponse([
            'success' => true,
            'reviews' => $reviews
        ]);
    }
    
    /**
     * Install app
     */
    private function installApp() {
        $this->requireAuth();
        $this->validateCSRF();
        
        $app_id = $this->validateInput($_POST['app_id'] ?? '', 'int');
        
        if (!$app_id) {
            $this->sendJSONResponse(['success' => false, 'error' => 'App ID required'], 400);
        }
        
        // Check if app exists and is published
        $stmt = $this->conn->prepare("
            SELECT a.id, p.user_id AS developer_id 
            FROM apps a 
            JOIN projects p ON a.project_id = p.id 
            WHERE a.id = ? AND a.status = 'published'
        ");
        $stmt->bind_param("i", $app_id);
        $stmt->execute();
        $app = $stmt->get_result()->fetch_assoc();
        
        if (!$app) {
            $this->sendJSONResponse(['success' => false, 'error' => 'App not found'], 404);
        }
        
        // Check if already installed
        $stmt = $this->conn->prepare("SELECT id FROM user_apps WHERE user_id = ? AND app_id = ?");
        $stmt->bind_param("ii", $this->user['id'], $app_id);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            $this->sendJSONResponse(['success' => false, 'error' => 'App already installed'], 400);
        }
        
        // Install app
        $stmt = $this->conn->prepare("
            INSERT INTO user_apps (user_id, app_id, installed_at) 
            VALUES (?, ?, NOW())
        ");
        $stmt->bind_param("ii", $this->user['id'], $app_id);
        
        if ($stmt->execute()) {
            // Update download count
            $stmt = $this->conn->prepare("UPDATE apps SET total_downloads = total_downloads + 1 WHERE id = ?");
            $stmt->bind_param("i", $app_id);
            $stmt->execute();
            
            $this->sendJSONResponse(['success' => true, 'message' => 'App installed successfully']);
        } else {
            $this->sendJSONResponse(['success' => false, 'error' => 'Failed to install app'], 500);
        }
    }
    
    /**
     * Rate app
     */
    private function rateApp() {
        $this->requireAuth();
        $this->validateCSRF();
        
        $app_id = $this->validateInput($_POST['app_id'] ?? '', 'int');
        $rating = $this->validateInput($_POST['rating'] ?? '', 'int');
        
        if (!$app_id || !$rating || $rating < 1 || $rating > 5) {
            $this->sendJSONResponse(['success' => false, 'error' => 'Invalid rating data'], 400);
        }
        
        // Check if app exists
        $stmt = $this->conn->prepare("SELECT id FROM apps WHERE id = ? AND status = 'published'");
        $stmt->bind_param("i", $app_id);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows === 0) {
            $this->sendJSONResponse(['success' => false, 'error' => 'App not found'], 404);
        }
        
        // Update or insert rating
        $stmt = $this->conn->prepare("
            INSERT INTO app_ratings (user_id, app_id, rating) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE rating = VALUES(rating)
        ");
        $stmt->bind_param("iii", $this->user['id'], $app_id, $rating);
        
        if ($stmt->execute()) {
            // Update app average rating
            $stmt = $this->conn->prepare("
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
            
            $this->sendJSONResponse(['success' => true, 'message' => 'Rating submitted successfully']);
        } else {
            $this->sendJSONResponse(['success' => false, 'error' => 'Failed to submit rating'], 500);
        }
    }
    
    /**
     * Add review
     */
    private function addReview() {
        $this->requireAuth();
        $this->validateCSRF();
        
        $app_id = $this->validateInput($_POST['app_id'] ?? '', 'int');
        $rating = $this->validateInput($_POST['rating'] ?? '', 'int');
        $review_text = $this->validateInput($_POST['review'] ?? '', 'html', 1000);
        
        if (!$app_id || !$rating || !$review_text || $rating < 1 || $rating > 5) {
            $this->sendJSONResponse(['success' => false, 'error' => 'Invalid review data'], 400);
        }
        
        if ($this->detectAttackPatterns($review_text)) {
            $this->logSecurityEvent('Potential XSS attack in review', 'App ID: ' . $app_id);
            $this->sendJSONResponse(['success' => false, 'error' => 'Invalid review content'], 400);
        }
        
        // Check if app exists
        $stmt = $this->conn->prepare("SELECT id FROM apps WHERE id = ? AND status = 'published'");
        $stmt->bind_param("i", $app_id);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows === 0) {
            $this->sendJSONResponse(['success' => false, 'error' => 'App not found'], 404);
        }
        
        // Add review
        $stmt = $this->conn->prepare("
            INSERT INTO app_reviews (user_id, app_id, rating, review, status) 
            VALUES (?, ?, ?, ?, 'published')
        ");
        $stmt->bind_param("iiss", $this->user['id'], $app_id, $rating, $review_text);
        
        if ($stmt->execute()) {
            // Update app average rating
            $stmt = $this->conn->prepare("
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
            
            $this->sendJSONResponse(['success' => true, 'message' => 'Review submitted successfully']);
        } else {
            $this->sendJSONResponse(['success' => false, 'error' => 'Failed to submit review'], 500);
        }
    }
    
    /**
     * Publish app
     */
    private function publishApp() {
        $this->requireAuth();
        $this->validateCSRF();
        
        $project_id = $this->validateInput($_POST['project_id'] ?? '', 'int');
        $title = $this->validateInput($_POST['title'] ?? '', 'string', 255);
        $description = $this->validateInput($_POST['description'] ?? '', 'html', 5000);
        $category = $this->validateInput($_POST['category'] ?? '', 'slug');
        $price = $this->validateInput($_POST['price'] ?? '', 'float');
        
        if (!$project_id || !$title || !$description || !$category) {
            $this->sendJSONResponse(['success' => false, 'error' => 'Required fields missing'], 400);
        }
        
        if ($this->detectAttackPatterns($title) || $this->detectAttackPatterns($description)) {
            $this->logSecurityEvent('Potential XSS attack in publish', 'Project ID: ' . $project_id);
            $this->sendJSONResponse(['success' => false, 'error' => 'Invalid content'], 400);
        }
        
        // Check if user owns project
        $stmt = $this->conn->prepare("SELECT id FROM projects WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $project_id, $this->user['id']);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows === 0) {
            $this->sendJSONResponse(['success' => false, 'error' => 'Project not found or access denied'], 403);
        }
        
        // Generate slug
        $slug = $this->generateSlug($title);
        
        // Check if slug exists
        $stmt = $this->conn->prepare("SELECT id FROM apps WHERE slug = ?");
        $stmt->bind_param("s", $slug);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            $slug .= '-' . time();
        }
        
        // Create app
        $stmt = $this->conn->prepare("
            INSERT INTO apps (project_id, title, slug, description, category, price, status, published_at) 
            VALUES (?, ?, ?, ?, ?, ?, 'published', NOW())
        ");
        $stmt->bind_param("issssd", $project_id, $title, $slug, $description, $category, $price);
        
        if ($stmt->execute()) {
            $this->sendJSONResponse(['success' => true, 'message' => 'App published successfully']);
        } else {
            $this->sendJSONResponse(['success' => false, 'error' => 'Failed to publish app'], 500);
        }
    }
    
    /**
     * Toggle save app
     */
    private function toggleSave() {
        $this->requireAuth();
        $this->validateCSRF();
        
        $app_id = $this->validateInput($_POST['app_id'] ?? '', 'int');
        
        if (!$app_id) {
            $this->sendJSONResponse(['success' => false, 'error' => 'App ID required'], 400);
        }
        
        // Check if already saved
        $stmt = $this->conn->prepare("SELECT id FROM saved_apps WHERE user_id = ? AND app_id = ?");
        $stmt->bind_param("ii", $this->user['id'], $app_id);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            // Remove from saved
            $stmt = $this->conn->prepare("DELETE FROM saved_apps WHERE user_id = ? AND app_id = ?");
            $stmt->bind_param("ii", $this->user['id'], $app_id);
            $stmt->execute();
            
            $this->sendJSONResponse(['success' => true, 'saved' => false, 'message' => 'App removed from saved']);
        } else {
            // Add to saved
            $stmt = $this->conn->prepare("INSERT INTO saved_apps (user_id, app_id, saved_at) VALUES (?, ?, NOW())");
            $stmt->bind_param("ii", $this->user['id'], $app_id);
            $stmt->execute();
            
            $this->sendJSONResponse(['success' => true, 'saved' => true, 'message' => 'App saved successfully']);
        }
    }
    
    /**
     * Send offer
     */
    private function sendOffer() {
        $this->requireAuth();
        $this->validateCSRF();
        
        $app_id = $this->validateInput($_POST['app_id'] ?? '', 'int');
        $amount = $this->validateInput($_POST['amount'] ?? '', 'float');
        $phone_number = $this->validateInput($_POST['phone_number'] ?? '', 'string', 20);
        $message = $this->validateInput($_POST['message'] ?? '', 'html', 1000);
        
        if (!$app_id || !$amount || !$phone_number) {
            $this->sendJSONResponse(['success' => false, 'error' => 'Required fields missing'], 400);
        }
        
        if ($this->detectAttackPatterns($message)) {
            $this->logSecurityEvent('Potential XSS attack in offer', 'App ID: ' . $app_id);
            $this->sendJSONResponse(['success' => false, 'error' => 'Invalid message content'], 400);
        }
        
        // Get app details
        $stmt = $this->conn->prepare("
            SELECT a.id, p.user_id AS developer_id 
            FROM apps a 
            JOIN projects p ON a.project_id = p.id 
            WHERE a.id = ? AND a.status = 'published'
        ");
        $stmt->bind_param("i", $app_id);
        $stmt->execute();
        $app = $stmt->get_result()->fetch_assoc();
        
        if (!$app) {
            $this->sendJSONResponse(['success' => false, 'error' => 'App not found'], 404);
        }
        
        if ($app['developer_id'] == $this->user['id']) {
            $this->sendJSONResponse(['success' => false, 'error' => 'Cannot send offer to your own app'], 400);
        }
        
        // Create offer
        $stmt = $this->conn->prepare("
            INSERT INTO offers (app_id, buyer_id, amount, phone_number, message, status, created_at) 
            VALUES (?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->bind_param("iidss", $app_id, $this->user['id'], $amount, $phone_number, $message);
        
        if ($stmt->execute()) {
            $this->sendJSONResponse(['success' => true, 'message' => 'Offer sent successfully']);
        } else {
            $this->sendJSONResponse(['success' => false, 'error' => 'Failed to send offer'], 500);
        }
    }
    
    /**
     * Send offer message
     */
    private function sendOfferMessage() {
        $this->requireAuth();
        $this->validateCSRF();
        
        $offer_id = $this->validateInput($_POST['offer_id'] ?? '', 'int');
        $message = $this->validateInput($_POST['message'] ?? '', 'html', 1000);
        
        if (!$offer_id || !$message) {
            $this->sendJSONResponse(['success' => false, 'error' => 'Required fields missing'], 400);
        }
        
        if ($this->detectAttackPatterns($message)) {
            $this->logSecurityEvent('Potential XSS attack in offer message', 'Offer ID: ' . $offer_id);
            $this->sendJSONResponse(['success' => false, 'error' => 'Invalid message content'], 400);
        }
        
        // Get offer details
        $stmt = $this->conn->prepare("
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
            $this->sendJSONResponse(['success' => false, 'error' => 'Offer not found'], 404);
        }
        
        // Check if user is part of the offer
        if ($offer['buyer_id'] != $this->user['id'] && $offer['developer_id'] != $this->user['id']) {
            $this->sendJSONResponse(['success' => false, 'error' => 'Access denied'], 403);
        }
        
        // Add message
        $stmt = $this->conn->prepare("
            INSERT INTO offer_messages (offer_id, sender_id, message, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->bind_param("iis", $offer_id, $this->user['id'], $message);
        
        if ($stmt->execute()) {
            $this->sendJSONResponse(['success' => true, 'message' => 'Message sent successfully']);
        } else {
            $this->sendJSONResponse(['success' => false, 'error' => 'Failed to send message'], 500);
        }
    }
}

// Route the request
$api = new AppsAPI();
$api->route();
?>
