<?php
/**
 * Notification Helper - Centralized notification system
 */

class NotificationHelper {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Create a new notification
     */
    public function create($user_id, $type, $title, $message, $options = []) {
        $sql = "
            INSERT INTO notifications (
                user_id, type, title, message, related_id, related_type, 
                action_url, action_text, icon, is_important, metadata
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        $stmt = $this->conn->prepare($sql);
        $metadata = isset($options['metadata']) ? json_encode($options['metadata']) : null;
        
        $stmt->bind_param("issssisssssi", 
            $user_id,
            $type,
            $title,
            $message,
            $options['related_id'] ?? null,
            $options['related_type'] ?? null,
            $options['action_url'] ?? null,
            $options['action_text'] ?? null,
            $options['icon'] ?? 'fa-info-circle',
            $options['is_important'] ?? false,
            $metadata
        );
        
        return $stmt->execute();
    }
    
    /**
     * Create offer notification
     */
    public function createOfferNotification($developer_id, $offer_data) {
        return $this->create($developer_id, 'offer', 'New Offer Received', 
            "You received a new offer of \${$offer_data['amount']} for your app", [
                'related_id' => $offer_data['id'],
                'related_type' => 'offer',
                'action_url' => '../core/dashboard.php?page=offers',
                'icon' => 'fa-handshake',
                'is_important' => true,
                'metadata' => ['offer_amount' => $offer_data['amount']]
            ]
        );
    }
    
    /**
     * Create review notification
     */
    public function createReviewNotification($developer_id, $review_data) {
        $stars = str_repeat('⭐', $review_data['rating']);
        return $this->create($developer_id, 'review', 'New Review', 
            "Your app received a {$review_data['rating']}-star review {$stars}", [
                'related_id' => $review_data['id'],
                'related_type' => 'review',
                'action_url' => "app.php?id={$review_data['app_id']}#reviews",
                'icon' => 'fa-star',
                'metadata' => ['rating' => $review_data['rating']]
            ]
        );
    }
    
    /**
     * Create app update notification
     */
    public function createUpdateNotification($user_id, $update_data) {
        return $this->create($user_id, 'update', 'App Update Available', 
            "Update '{$update_data['app_title']}' to v{$update_data['version']}", [
                'related_id' => $update_data['version_id'],
                'related_type' => 'app_version',
                'action_url' => "app_updates.php?id={$update_data['app_id']}",
                'icon' => 'fa-download'
            ]
        );
    }
    
    /**
     * Create message notification
     */
    public function createMessageNotification($user_id, $message_data) {
        return $this->create($user_id, 'message', 'New Message', 
            "You received a new message from {$message_data['sender_name']}", [
                'related_id' => $message_data['id'],
                'related_type' => 'message',
                'action_url' => "messages.php?conversation={$message_data['conversation_id']}",
                'icon' => 'fa-envelope'
            ]
        );
    }
    
    /**
     * Create system notification
     */
    public function createSystemNotification($user_ids, $title, $message, $options = []) {
        if (!is_array($user_ids)) {
            $user_ids = [$user_ids];
        }
        
        $results = [];
        foreach ($user_ids as $user_id) {
            $results[] = $this->create($user_id, 'system', $title, $message, array_merge($options, [
                'icon' => 'fa-info-circle',
                'is_important' => true
            ]));
        }
        
        return $results;
    }
    
    /**
     * Get unread notification count
     */
    public function getUnreadCount($user_id) {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_row()[0];
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead($notification_id, $user_id) {
        $stmt = $this->conn->prepare("UPDATE notifications SET is_read = TRUE, read_at = NOW() WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $notification_id, $user_id);
        return $stmt->execute();
    }
    
    /**
     * Mark all notifications as read
     */
    public function markAllAsRead($user_id) {
        $stmt = $this->conn->prepare("UPDATE notifications SET is_read = TRUE, read_at = NOW() WHERE user_id = ? AND is_read = FALSE");
        $stmt->bind_param("i", $user_id);
        return $stmt->execute();
    }
    
    /**
     * Check user notification preferences
     */
    public function shouldNotify($user_id, $type) {
        $stmt = $this->conn->prepare("SELECT {$type}_notifications FROM notification_preferences WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_row();
        
        // Return true if preference is enabled or if no preference exists (default to enabled)
        return $result ? $result[0] : true;
    }
}

/**
 * Trigger notification functions for existing events
 */

// Trigger when new offer is created
function triggerOfferNotification($offer_id, $conn = null) {
    if (!$conn) {
        global $auth;
        $conn = $auth->getConnection();
    }
    
    // Get offer details
    $stmt = $conn->prepare("
        SELECT o.*, a.title as app_title, a.project_id, p.user_id as developer_id
        FROM offers o
        JOIN apps a ON o.app_id = a.id
        JOIN projects p ON a.project_id = p.id
        WHERE o.id = ?
    ");
    $stmt->bind_param("i", $offer_id);
    $stmt->execute();
    $offer = $stmt->get_result()->fetch_assoc();
    
    if ($offer) {
        $notificationHelper = new NotificationHelper($conn);
        $notificationHelper->createOfferNotification($offer['developer_id'], $offer);
    }
}

// Trigger when new review is created
function triggerReviewNotification($review_id, $conn = null) {
    if (!$conn) {
        global $auth;
        $conn = $auth->getConnection();
    }
    
    // Get review details
    $stmt = $conn->prepare("
        SELECT r.*, a.title as app_title, a.project_id, p.user_id as developer_id
        FROM app_reviews r
        JOIN apps a ON r.app_id = a.id
        JOIN projects p ON a.project_id = p.id
        WHERE r.id = ?
    ");
    $stmt->bind_param("i", $review_id);
    $stmt->execute();
    $review = $stmt->get_result()->fetch_assoc();
    
    if ($review) {
        $notificationHelper = new NotificationHelper($conn);
        $notificationHelper->createReviewNotification($review['developer_id'], $review);
    }
}

// Trigger when app version is created
function triggerUpdateNotifications($version_id, $conn = null) {
    if (!$conn) {
        global $auth;
        $conn = $auth->getConnection();
    }
    
    // Get version and installed users
    $stmt = $conn->prepare("
        SELECT av.*, a.title as app_title, ua.user_id
        FROM app_versions av
        JOIN apps a ON av.app_id = a.id
        JOIN user_apps ua ON a.id = ua.app_id
        WHERE av.id = ? AND av.is_current = TRUE
    ");
    $stmt->bind_param("i", $version_id);
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $notificationHelper = new NotificationHelper($conn);
    
    foreach ($results as $row) {
        // Only notify if user doesn't already have this version
        if ($row['installed_version'] !== $row['version']) {
            $notificationHelper->createUpdateNotification($row['user_id'], [
                'version_id' => $version_id,
                'app_id' => $row['app_id'],
                'app_title' => $row['app_title'],
                'version' => $row['version']
            ]);
        }
    }
}
?>
