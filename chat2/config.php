<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../controllers/core/mainlogincore.php';
require_once '../config/security.php';

$auth = new AuthCore();

if (!$auth->isLoggedIn()) {
    header('Location: ../controllers/auth/login.php');
    exit();
}

$user = $auth->getCurrentUser();
$conn = $auth->getConnection();

// Get offer ID from URL
$offer_id = isset($_GET['offer_id']) ? (int)$_GET['offer_id'] : 0;
if (!$offer_id) {
    header('Location: ../controllers/marketplace/marketplace.php');
    exit();
}

// Verify user has access to this offer (either as developer or buyer)
$stmt = $conn->prepare("
    SELECT o.*, a.title as app_title, a.project_id,
           p.user_id as developer_id, dev.username as developer_name,
           buyer.username as buyer_name, buyer.email as buyer_email
    FROM offers o
    JOIN apps a ON o.app_id = a.id
    JOIN projects p ON a.project_id = p.id
    JOIN users dev ON p.user_id = dev.id
    JOIN users buyer ON o.buyer_id = buyer.id
    WHERE o.id = ? AND (p.user_id = ? OR o.buyer_id = ?)
");
$stmt->bind_param("iii", $offer_id, $user['id'], $user['id']);
$stmt->execute();
$offer = $stmt->get_result()->fetch_assoc();

if (!$offer) {
    header('Location: ../marketplace.php');
    exit();
}

// Determine user role
$is_developer = $offer['developer_id'] === $user['id'];
$is_buyer = $offer['buyer_id'] === $user['id'];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'get_messages') {
    $stmt = $conn->prepare("
        SELECT m.*, u.username,
               CASE WHEN m.sender_id = ? THEN 1 ELSE 0 END as is_sender
        FROM offer_messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.offer_id = ?
        ORDER BY m.created_at ASC
    ");
    
    if ($stmt) {
        $stmt->bind_param("ii", $user['id'], $offer_id);
        if ($stmt->execute()) {
            $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'messages' => $messages]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Failed to execute query: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Failed to prepare statement: ' . $conn->error]);
    }
    exit;
}

// Test endpoint
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'test') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'message' => 'Test endpoint working',
        'user_id' => $user['id'] ?? 'not logged in',
        'offer_id' => $offer_id ?? 'not set'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_message') {
    $message = trim($_POST['message'] ?? '');
    $post_offer_id = (int)($_POST['offer_id'] ?? 0);
    
    if (!$post_offer_id) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Missing offer_id']);
        exit;
    }
    
    if (!empty($message)) {
        $stmt = $conn->prepare("
            INSERT INTO offer_messages (offer_id, sender_id, message)
            VALUES (?, ?, ?)
        ");
        
        if ($stmt) {
            $stmt->bind_param("iis", $post_offer_id, $user['id'], $message);
            if ($stmt->execute()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Failed to save message: ' . $stmt->error]);
            }
            $stmt->close();
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Failed to prepare statement: ' . $conn->error]);
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
    }
    exit;
}

// Get messages for display
$stmt = $conn->prepare("
    SELECT m.*, u.username,
           CASE WHEN m.sender_id = ? THEN 1 ELSE 0 END as is_sender
    FROM offer_messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.offer_id = ?
    ORDER BY m.created_at ASC
");
$stmt->bind_param("ii", $user['id'], $offer_id);
$stmt->execute();
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
