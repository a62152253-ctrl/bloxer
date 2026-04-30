<?php
require_once '../controllers/core/mainlogincore.php';

$auth = new AuthCore();
$user = $auth->getCurrentUser();

$app_id = $_GET['app_id'] ?? null;
$action = $_POST['action'] ?? null;

// Handle rating submission
if ($action === 'submit_rating' && $user && $app_id) {
    $rating = intval($_POST['rating'] ?? 0);
    $review = trim($_POST['review'] ?? '');
    
    if ($rating < 1 || $rating > 5) {
        echo json_encode(['success' => false, 'error' => 'Invalid rating']);
        exit();
    }
    
    if (strlen($review) > 1000) {
        echo json_encode(['success' => false, 'error' => 'Review too long (max 1000 characters)']);
        exit();
    }
    
    $conn = $auth->getConnection();
    
    // Check if user already rated this app
    $stmt = $conn->prepare("SELECT id FROM app_ratings WHERE app_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $app_id, $user['id']);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    
    if ($existing) {
        // Update existing rating
        $stmt = $conn->prepare("UPDATE app_ratings SET rating = ?, review = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("isi", $rating, $review, $existing['id']);
    } else {
        // Insert new rating
        $stmt = $conn->prepare("INSERT INTO app_ratings (app_id, user_id, rating, review) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiis", $app_id, $user['id'], $rating, $review);
    }
    
    if ($stmt->execute()) {
        // Update app's average rating
        updateAppRating($conn, $app_id);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save rating']);
    }
    exit();
}

// Handle rating deletion
if ($action === 'delete_rating' && $user && $app_id) {
    $conn = $auth->getConnection();
    
    $stmt = $conn->prepare("DELETE FROM app_ratings WHERE app_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $app_id, $user['id']);
    
    if ($stmt->execute()) {
        updateAppRating($conn, $app_id);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to delete rating']);
    }
    exit();
}

// Get ratings for an app
if ($app_id) {
    $conn = $auth->getConnection();
    
    // Get ratings with user info
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    $stmt = $conn->prepare("
        SELECT ar.*, u.username, u.avatar_url,
               CASE WHEN ar.user_id = ? THEN 1 ELSE 0 END as is_own_rating
        FROM app_ratings ar
        JOIN users u ON ar.user_id = u.id
        WHERE ar.app_id = ?
        ORDER BY ar.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $user_id = $user ? $user['id'] : 0;
    $stmt->bind_param("iiii", $user_id, $app_id, $limit, $offset);
    $stmt->execute();
    $ratings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get total count
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM app_ratings WHERE app_id = ?");
    $stmt->bind_param("i", $app_id);
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];
    
    // Get rating breakdown
    $stmt = $conn->prepare("
        SELECT rating, COUNT(*) as count
        FROM app_ratings
        WHERE app_id = ?
        GROUP BY rating
        ORDER BY rating DESC
    ");
    $stmt->bind_param("i", $app_id);
    $stmt->execute();
    $breakdown = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode([
        'success' => true,
        'ratings' => $ratings,
        'total' => $total,
        'pages' => ceil($total / $limit),
        'breakdown' => $breakdown
    ]);
}

function updateAppRating($conn, $app_id) {
    $stmt = $conn->prepare("
        UPDATE apps 
        SET rating = (
            SELECT COALESCE(AVG(rating), 0) 
            FROM app_ratings 
            WHERE app_id = ?
        )
        WHERE id = ?
    ");
    $stmt->bind_param("ii", $app_id, $app_id);
    $stmt->execute();
}
?>
