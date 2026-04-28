<?php
require_once 'mainlogincore.php';

$auth = new AuthCore();

// Require login for all actions
if (!$auth->isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Login required']);
    exit();
}

$user = $auth->getCurrentUser();
$action = $_POST['action'] ?? '';
$app_id = $_POST['app_id'] ?? null;
$offer_id = $_POST['offer_id'] ?? null;

$allowed_actions = ['install', 'toggle_save', 'review', 'send_offer', 'send_offer_message'];
if (!in_array($action, $allowed_actions)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit();
}

$conn = $auth->getConnection();
header('Content-Type: application/json');

if (in_array($action, ['install', 'toggle_save', 'review', 'send_offer'])) {
    if (!$app_id) {
        echo json_encode(['success' => false, 'error' => 'Invalid request']);
        exit();
    }

    // Verify app exists and is published
    $stmt = $conn->prepare("SELECT a.id, p.user_id AS developer_id FROM apps a JOIN projects p ON a.project_id = p.id WHERE a.id = ? AND a.status = 'published'");
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Database error']);
        exit();
    }
    $stmt->bind_param("i", $app_id);
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'error' => 'Database error']);
        exit();
    }
    $appResult = $stmt->get_result();
    if ($appResult->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'App not found']);
        exit();
    }
    $appData = $appResult->fetch_assoc();
}

switch ($action) {
    case 'install':
        // Check if already installed
        $stmt = $conn->prepare("SELECT id FROM user_apps WHERE user_id = ? AND app_id = ?");
        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
            break;
        }
        $stmt->bind_param("ii", $user['id'], $app_id);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
            break;
        }
        
        if ($stmt->get_result()->num_rows === 0) {
            // Install app
            $stmt = $conn->prepare("INSERT INTO user_apps (user_id, app_id, installed_at) VALUES (?, ?, NOW())");
            if ($stmt) {
                $stmt->bind_param("ii", $user['id'], $app_id);
                $stmt->execute();
            }
            
            // Increment download count
            $stmt = $conn->prepare("UPDATE apps SET download_count = download_count + 1 WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $app_id);
                $stmt->execute();
            }
            
            // Record analytics
            $stmt = $conn->prepare("
                INSERT INTO developer_analytics (user_id, app_id, date, downloads, unique_users) 
                VALUES (?, ?, CURDATE(), 1, 1)
                ON DUPLICATE KEY UPDATE downloads = downloads + 1, unique_users = unique_users + 1
            ");
            $stmt->bind_param("ii", $user['id'], $app_id);
            $stmt->execute();
        }
        
        echo json_encode(['success' => true]);
        break;
        
    case 'toggle_save':
        $stmt = $conn->prepare("SELECT is_favorite FROM user_apps WHERE user_id = ? AND app_id = ?");
        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
            break;
        }
        $stmt->bind_param("ii", $user['id'], $app_id);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
            break;
        }
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            // Create entry with favorite
            $stmt = $conn->prepare("INSERT INTO user_apps (user_id, app_id, is_favorite) VALUES (?, ?, 1)");
            if ($stmt) {
                $stmt->bind_param("ii", $user['id'], $app_id);
                $stmt->execute();
            }
        } else {
            // Toggle favorite status
            $current = $result->fetch_assoc()['is_favorite'];
            $stmt = $conn->prepare("UPDATE user_apps SET is_favorite = ? WHERE user_id = ? AND app_id = ?");
            if ($stmt) {
                $new_favorite = $current ? 0 : 1;
                $stmt->bind_param("iii", $new_favorite, $user['id'], $app_id);
                $stmt->execute();
            }
        }
        
        echo json_encode(['success' => true]);
        break;
        
    case 'review':
        $rating = intval($_POST['rating'] ?? 0);
        $review_text = trim($_POST['review'] ?? '');
        
        if ($rating < 1 || $rating > 5) {
            echo json_encode(['success' => false, 'error' => 'Invalid rating']);
            break;
        }
        
        // Check if user has installed the app
        $stmt = $conn->prepare("SELECT id FROM user_apps WHERE user_id = ? AND app_id = ?");
        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
            break;
        }
        $stmt->bind_param("ii", $user['id'], $app_id);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
            break;
        }
        if ($stmt->get_result()->num_rows === 0) {
            echo json_encode(['success' => false, 'error' => 'You must install the app first']);
            break;
        }
        
        // Check if review already exists
        $stmt = $conn->prepare("SELECT id FROM app_reviews WHERE app_id = ? AND user_id = ?");
        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
            break;
        }
        $stmt->bind_param("ii", $app_id, $user['id']);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
            break;
        }
        
        if ($stmt->get_result()->num_rows > 0) {
            // Update existing review
            $stmt = $conn->prepare("
                UPDATE app_reviews 
                SET rating = ?, review = ?, updated_at = NOW() 
                WHERE app_id = ? AND user_id = ?
            ");
            $stmt->bind_param("isii", $rating, $review_text, $app_id, $user['id']);
            $stmt->execute();
        } else {
            // Create new review
            $stmt = $conn->prepare("
                INSERT INTO app_reviews (app_id, user_id, rating, review, is_verified_purchase) 
                VALUES (?, ?, ?, ?, 1)
            ");
            $stmt->bind_param("iisi", $app_id, $user['id'], $rating, $review_text);
            $stmt->execute();
        }
        
        // Update app rating
        $stmt = $conn->prepare("
            UPDATE apps 
            SET rating = (
                SELECT AVG(rating) FROM app_reviews WHERE app_id = ? AND status = 'published'
            ),
            rating_count = (
                SELECT COUNT(*) FROM app_reviews WHERE app_id = ? AND status = 'published'
            )
            WHERE id = ?
        ");
        $stmt->bind_param("iii", $app_id, $app_id, $app_id);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
        break;

    case 'send_offer':
        $amount = floatval($_POST['amount'] ?? 0);
        $phone_number = trim($_POST['phone_number'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if ($amount <= 0) {
            echo json_encode(['success' => false, 'error' => 'Please enter a valid offer amount']);
            break;
        }

        if ($appData['developer_id'] == $user['id']) {
            echo json_encode(['success' => false, 'error' => 'You cannot send an offer to your own app']);
            break;
        }

        $stmt = $conn->prepare("INSERT INTO offers (app_id, buyer_id, developer_id, amount, phone_number, subject, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
            break;
        }
        $stmt->bind_param("iiidss", $app_id, $user['id'], $appData['developer_id'], $amount, $phone_number, $message);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'error' => 'Failed to create offer']);
            break;
        }
        $new_offer_id = $stmt->insert_id;

        if (!empty($message)) {
            $stmt = $conn->prepare("INSERT INTO offer_messages (offer_id, sender_id, message) VALUES (?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("iis", $new_offer_id, $user['id'], $message);
                $stmt->execute();
            }
        }

        echo json_encode(['success' => true, 'message' => 'Offer sent to the developer']);
        break;

    case 'send_offer_message':
        if (!$offer_id) {
            echo json_encode(['success' => false, 'error' => 'Invalid offer']);
            break;
        }

        $message = trim($_POST['message'] ?? '');
        if (empty($message)) {
            echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
            break;
        }

        $stmt = $conn->prepare("SELECT id, buyer_id, developer_id, status FROM offers WHERE id = ?");
        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
            break;
        }
        $stmt->bind_param("i", $offer_id);
        $stmt->execute();
        $offerData = $stmt->get_result()->fetch_assoc();

        if (!$offerData) {
            echo json_encode(['success' => false, 'error' => 'Offer not found']);
            break;
        }

        if ($user['id'] !== $offerData['buyer_id'] && $user['id'] !== $offerData['developer_id']) {
            echo json_encode(['success' => false, 'error' => 'You are not authorized to message on this offer']);
            break;
        }

        if (!in_array($offerData['status'], ['pending', 'accepted'])) {
            echo json_encode(['success' => false, 'error' => 'Cannot message on this offer']);
            break;
        }

        $stmt = $conn->prepare("INSERT INTO offer_messages (offer_id, sender_id, message) VALUES (?, ?, ?)");
        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
            break;
        }
        $stmt->bind_param("iis", $offer_id, $user['id'], $message);
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => 'Message sent']);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
?>
