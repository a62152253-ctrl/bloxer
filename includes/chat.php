<?php
require_once '../controllers/core/mainlogincore.php';

$auth = new AuthCore();

$action = $_POST['action'] ?? null;
$user = $auth->getCurrentUser();

// Handle message sending
if ($action === 'send_message' && $user) {
    $recipient_id = intval($_POST['recipient_id'] ?? 0);
    $project_id = intval($_POST['project_id'] ?? 0);
    $app_id = intval($_POST['app_id'] ?? 0);
    $message_text = trim($_POST['message'] ?? '');
    $message_type = $_POST['message_type'] ?? 'message'; // 'message', 'offer', 'deal_request'
    $offer_amount = floatval($_POST['offer_amount'] ?? 0);
    
    if (empty($message_text) || $recipient_id === 0) {
        echo json_encode(['success' => false, 'error' => 'Message and recipient are required']);
        exit();
    }
    
    if (strlen($message_text) > 2000) {
        echo json_encode(['success' => false, 'error' => 'Message too long (max 2000 characters)']);
        exit();
    }
    
    if ($message_type === 'offer' && $offer_amount <= 0) {
        echo json_encode(['success' => false, 'error' => 'Offer amount must be greater than 0']);
        exit();
    }
    
    $conn = $auth->getConnection();
    
    // Verify recipient exists
    $stmt = $conn->prepare("SELECT id, username FROM users WHERE id = ?");
    $stmt->bind_param("i", $recipient_id);
    $stmt->execute();
    $recipient = $stmt->get_result()->fetch_assoc();
    
    if (!$recipient) {
        echo json_encode(['success' => false, 'error' => 'Recipient not found']);
        exit();
    }
    
    // Verify access to project/app if specified
    if ($project_id > 0) {
        $stmt = $conn->prepare("SELECT id, name, user_id FROM projects WHERE id = ? AND status = 'published'");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $project = $stmt->get_result()->fetch_assoc();
        
        if (!$project) {
            echo json_encode(['success' => false, 'error' => 'Project not found']);
            exit();
        }
        
        // Check if user has permission to discuss this project
        if ($project['user_id'] != $user['id']) {
            echo json_encode(['success' => false, 'error' => 'Permission denied']);
            exit();
        }
    }
    
    if ($app_id > 0) {
        $stmt = $conn->prepare("SELECT id, title, project_id FROM apps WHERE id = ? AND status IN ('published', 'featured')");
        $stmt->bind_param("i", $app_id);
        $stmt->execute();
        $app = $stmt->get_result()->fetch_assoc();
        
        if (!$app) {
            echo json_encode(['success' => false, 'error' => 'App not found']);
            exit();
        }
        
        // Verify project ownership
        $stmt = $conn->prepare("SELECT user_id FROM projects WHERE id = ?");
        $stmt->bind_param("i", $app['project_id']);
        $stmt->execute();
        $app_project = $stmt->get_result()->fetch_assoc();
        
        if ($app_project['user_id'] != $user['id']) {
            echo json_encode(['success' => false, 'error' => 'Permission denied']);
            exit();
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
        echo json_encode(['success' => false, 'error' => 'Failed to send message']);
    }
    exit();
}

// Handle deal acceptance
if ($action === 'accept_deal' && $user) {
    $message_id = intval($_POST['message_id'] ?? 0);
    
    if ($message_id === 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid message ID']);
        exit();
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
        echo json_encode(['success' => false, 'error' => 'Message not found or permission denied']);
        exit();
    }
    
    if ($message['message_type'] !== 'offer') {
        echo json_encode(['success' => false, 'error' => 'This is not a deal offer']);
        exit();
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
            header("Location: ../chat2/chat.php?offer_id={$offer_id}");
            exit();
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to create offer']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to accept deal']);
    }
    exit();
}

// Handle deal rejection
if ($action === 'reject_deal' && $user) {
    $message_id = intval($_POST['message_id'] ?? 0);
    
    if ($message_id === 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid message ID']);
        exit();
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
        echo json_encode(['success' => false, 'error' => 'Message not found or permission denied']);
        exit();
    }
    
    if ($message['message_type'] !== 'offer') {
        echo json_encode(['success' => false, 'error' => 'This is not a deal offer']);
        exit();
    }
    
    // Update message status
    $stmt = $conn->prepare("UPDATE chat_messages SET status = 'rejected', updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $message_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Deal rejected successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to reject deal']);
    }
    exit();
}

// Get chat messages
if ($action === 'get_messages') {
    $other_user_id = intval($_POST['other_user_id'] ?? 0);
    $page = max(1, intval($_POST['page'] ?? 1));
    $limit = 50;
    $offset = ($page - 1) * $limit;
    
    if ($other_user_id === 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
        exit();
    }
    
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit();
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
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit();
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
    exit();
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
?>
