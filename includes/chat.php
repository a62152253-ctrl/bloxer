<?php
require_once '../controllers/core/mainlogincore.php';

$auth = new AuthCore();

$action = SecurityUtils::validateInput($_POST['action'] ?? null, 'action');
$user = $auth->getCurrentUser();

// Handle message sending
if ($action === 'send_message' && $user) {
    $recipient_id = SecurityUtils::validateInput($_POST['recipient_id'] ?? 0, 'int');
    $project_id = SecurityUtils::validateInput($_POST['project_id'] ?? 0, 'int');
    $app_id = SecurityUtils::validateInput($_POST['app_id'] ?? 0, 'int');
    $message_text = SecurityUtils::validateInput(trim($_POST['message'] ?? ''), 'string', 2000);
    $message_type = SecurityUtils::validateInput($_POST['message_type'] ?? 'message', 'string', 20);
    $offer_amount = SecurityUtils::validateInput($_POST['offer_amount'] ?? 0, 'float');
    
    if (empty($message_text) || $recipient_id === 0) {
        SecurityUtils::safeExit(['success' => false, 'error' => 'Message and recipient are required'], 400, 'warning');
    }
    
    if (strlen($message_text) > 2000) {
        SecurityUtils::safeExit(['success' => false, 'error' => 'Message too long (max 2000 characters)'], 400, 'warning');
    }
    
    if ($message_type === 'offer' && $offer_amount <= 0) {
        SecurityUtils::safeExit(['success' => false, 'error' => 'Offer amount must be greater than 0'], 400, 'warning');
    }
    
    $conn = $auth->getConnection();
    
    // Verify recipient exists
    $stmt = $conn->prepare("SELECT id, username FROM users WHERE id = ?");
    $stmt->bind_param("i", $recipient_id);
    $stmt->execute();
    $recipient = $stmt->get_result()->fetch_assoc();
    
    if (!$recipient) {
        SecurityUtils::safeExit(['success' => false, 'error' => 'Recipient not found'], 404, 'warning');
    }
    
    // Verify access to project/app if specified
    if ($project_id > 0) {
        $stmt = $conn->prepare("SELECT id, name, user_id FROM projects WHERE id = ? AND status = 'published'");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $project = $stmt->get_result()->fetch_assoc();
        
        if (!$project) {
            SecurityUtils::safeExit(['success' => false, 'error' => 'Project not found'], 404, 'warning');
        }
        
        // Check if user has permission to discuss this project
        if ($project['user_id'] != $user['id']) {
            SecurityUtils::safeExit(['success' => false, 'error' => 'Permission denied'], 403, 'warning');
        }
    }
    
    if ($app_id > 0) {
        $stmt = $conn->prepare("SELECT id, title, project_id FROM apps WHERE id = ? AND status IN ('published', 'featured')");
        $stmt->bind_param("i", $app_id);
        $stmt->execute();
        $app = $stmt->get_result()->fetch_assoc();
        
        if (!$app) {
            SecurityUtils::safeExit(['success' => false, 'error' => 'App not found'], 404, 'warning');
        }
        
        // Verify project ownership
        $stmt = $conn->prepare("SELECT user_id FROM projects WHERE id = ?");
        $stmt->bind_param("i", $app['project_id']);
        $stmt->execute();
        $app_project = $stmt->get_result()->fetch_assoc();
        
        if ($app_project['user_id'] != $user['id']) {
            SecurityUtils::safeExit(['success' => false, 'error' => 'Permission denied'], 403, 'warning');
        }
    }
    
    // Send message
    $stmt = $conn->prepare("
        INSERT INTO chat_messages (sender_id, recipient_id, project_id, app_id, message_text, message_type, offer_amount, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("iiissssd", $user['id'], $recipient_id, $project_id, $app_id, $message_text, $message_type, $offer_amount);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Message sent successfully']);
    } else {
        SecurityUtils::safeExit(['success' => false, 'error' => 'Failed to send message'], 500, 'error');
    }
}

// Handle deal acceptance
if ($action === 'accept_deal' && $user) {
    $message_id = SecurityUtils::validateInput($_POST['message_id'] ?? 0, 'int');
    
    if ($message_id === 0) {
        SecurityUtils::safeExit(['success' => false, 'error' => 'Invalid message ID'], 400, 'warning');
    }
    
    $conn = $auth->getConnection();
    
    // Get message and verify recipient
    $stmt = $conn->prepare("
        SELECT cm.*, 
               CASE WHEN cm.recipient_id = ? THEN 1 ELSE 0 END as is_recipient
        FROM chat_messages cm
        WHERE cm.id = ?
    ");
    $stmt->bind_param("ii", $user['id'], $message_id);
    $stmt->execute();
    $message = $stmt->get_result()->fetch_assoc();
    
    if (!$message || !$message['is_recipient']) {
        SecurityUtils::safeExit(['success' => false, 'error' => 'Message not found or permission denied'], 403, 'warning');
    }
    
    if ($message['message_type'] !== 'offer') {
        SecurityUtils::safeExit(['success' => false, 'error' => 'This is not a deal offer'], 400, 'warning');
    }
    
    // Update message status
    $stmt = $conn->prepare("UPDATE chat_messages SET status = 'accepted', updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $message_id);
    
    if ($stmt->execute()) {
        // Create an offer from the accepted deal for chat2
        $stmt = $conn->prepare("
            INSERT INTO offers (app_id, buyer_id, developer_id, offer_amount, message, status)
            VALUES (?, ?, ?, ?, ?, 'accepted')
        ");
        
        $app_id = $message['app_id'] ?? 0;
        $buyer_id = $message['recipient_id']; // The user who accepted
        $developer_id = $message['sender_id']; // The user who made the offer
        $offer_amount = $message['offer_amount'];
        $deal_message = "Deal accepted: " . $message['message_text'];
        
        $stmt->bind_param("iidids", $app_id, $buyer_id, $developer_id, $offer_amount, $deal_message);
        
        if ($stmt->execute()) {
            $offer_id = $conn->insert_id;
            
            // Add initial message to offer_messages
            $stmt = $conn->prepare("
                INSERT INTO offer_messages (offer_id, sender_id, message, message_type)
                VALUES (?, ?, ?, 'accept')
            ");
            $stmt->bind_param("iis", $offer_id, $buyer_id, $deal_message);
            $stmt->execute();
            
            // Redirect to chat2 with the offer
            SecurityUtils::safeRedirect("../chat2/chat.php?offer_id={$offer_id}", 302, 'Offer created, redirecting to chat');
        } else {
            SecurityUtils::safeExit(['success' => false, 'error' => 'Failed to create offer'], 500, 'error');
        }
    } else {
        SecurityUtils::safeExit(['success' => false, 'error' => 'Failed to accept deal'], 500, 'error');
    }
}

// Handle deal rejection
if ($action === 'reject_deal' && $user) {
    $message_id = SecurityUtils::validateInput($_POST['message_id'] ?? 0, 'int');
    
    if ($message_id === 0) {
        SecurityUtils::safeExit(['success' => false, 'error' => 'Invalid message ID'], 400, 'warning');
    }
    
    $conn = $auth->getConnection();
    
    // Get message and verify recipient
    $stmt = $conn->prepare("
        SELECT cm.*, 
               CASE WHEN cm.recipient_id = ? THEN 1 ELSE 0 END as is_recipient
        FROM chat_messages cm
        WHERE cm.id = ?
    ");
    $stmt->bind_param("ii", $user['id'], $message_id);
    $stmt->execute();
    $message = $stmt->get_result()->fetch_assoc();
    
    if (!$message || !$message['is_recipient']) {
        SecurityUtils::safeExit(['success' => false, 'error' => 'Message not found or permission denied'], 403, 'warning');
    }
    
    if ($message['message_type'] !== 'offer') {
        SecurityUtils::safeExit(['success' => false, 'error' => 'This is not a deal offer'], 400, 'warning');
    }
    
    // Update message status
    $stmt = $conn->prepare("UPDATE chat_messages SET status = 'rejected', updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $message_id);
    
    if ($stmt->execute()) {
        SecurityUtils::safeExit(['success' => true, 'message' => 'Deal rejected successfully'], 200, 'success');
    } else {
        SecurityUtils::safeExit(['success' => false, 'error' => 'Failed to reject deal'], 500, 'error');
    }
}

// Get chat messages
if ($action === 'get_messages') {
    $other_user_id = SecurityUtils::validateInput($_POST['other_user_id'] ?? 0, 'int');
    $page = max(1, SecurityUtils::validateInput($_POST['page'] ?? 1, 'int'));
    $limit = 50;
    $offset = ($page - 1) * $limit;
    
    if ($other_user_id === 0) {
        SecurityUtils::safeExit(['success' => false, 'error' => 'Invalid user ID'], 400, 'warning');
    }
    
    if (!$user) {
        SecurityUtils::safeExit(['success' => false, 'error' => 'Unauthorized'], 401, 'warning');
    }
    
    $conn = $auth->getConnection();
    
    // Get conversation between current user and other user
    $stmt = $conn->prepare("
        SELECT cm.*, 
               u1.username as sender_name, u1.avatar_url as sender_avatar,
               u2.username as recipient_name, u2.avatar_url as recipient_avatar,
               p.name as project_name,
               a.title as app_title,
               CASE WHEN cm.sender_id = ? THEN 1 ELSE 0 END as is_sent
        FROM chat_messages cm
        JOIN users u1 ON cm.sender_id = u1.id
        JOIN users u2 ON cm.recipient_id = u2.id
        LEFT JOIN projects p ON cm.project_id = p.id
        LEFT JOIN apps a ON cm.app_id = a.id
        WHERE (cm.sender_id = ? AND cm.recipient_id = ?) 
           OR (cm.sender_id = ? AND cm.recipient_id = ?)
        ORDER BY cm.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("iiiiii", $user['id'], $other_user_id, $other_user_id, $user['id'], $limit, $offset);
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get total count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM chat_messages cm
        WHERE (cm.sender_id = ? AND cm.recipient_id = ?) 
           OR (cm.sender_id = ? AND cm.recipient_id = ?)
    ");
    $stmt->bind_param("iiii", $user['id'], $other_user_id, $other_user_id, $user['id']);
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];
    
    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'total' => $total,
        'pages' => ceil($total / $limit)
    ]);
    exit();
}

// Get conversations list
if ($action === 'get_conversations') {
    if (!$user) {
        SecurityUtils::safeExit(['success' => false, 'error' => 'Unauthorized'], 401, 'warning');
    }
    
    $conn = $auth->getConnection();
    
    // Get recent conversations for current user
    $stmt = $conn->prepare("
        SELECT DISTINCT 
            CASE 
                WHEN cm.sender_id = ? THEN cm.recipient_id 
                ELSE cm.sender_id 
            END as other_user_id,
            u.username as other_username,
            u.avatar_url as other_avatar,
            MAX(cm.created_at) as last_message_time,
            (SELECT COUNT(*) FROM chat_messages cm2 
             WHERE ((cm2.sender_id = ? AND cm2.recipient_id = other_user_id) 
                OR (cm2.sender_id = other_user_id AND cm2.recipient_id = ?))
             AND cm2.created_at <= cm.created_at) as message_count,
            (SELECT message_text FROM chat_messages cm2 
             WHERE ((cm2.sender_id = ? AND cm2.recipient_id = other_user_id) 
                OR (cm2.sender_id = other_user_id AND cm2.recipient_id = ?))
             ORDER BY cm2.created_at DESC 
             LIMIT 1) as last_message_text
        FROM chat_messages cm
        JOIN users u ON (
            CASE 
                WHEN cm.sender_id = ? THEN cm.recipient_id 
                ELSE cm.sender_id 
            END = u.id
        )
        WHERE (cm.sender_id = ? OR cm.recipient_id = ?)
        GROUP BY other_user_id
        ORDER BY last_message_time DESC
        LIMIT 20
    ");
    $stmt->bind_param("iiiiii", $user['id'], $user['id'], $user['id'], $user['id']);
    $stmt->execute();
    $conversations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode(['success' => true, 'conversations' => $conversations]);
    SecurityUtils::safeExit(['success' => true, 'conversations' => $conversations], 200, 'success');
}

SecurityUtils::safeExit(['success' => false, 'error' => 'Invalid action'], 400, 'warning');
?>
