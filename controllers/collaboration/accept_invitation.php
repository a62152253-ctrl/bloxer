<?php
require_once __DIR__ . '/../../bootstrap.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    die('Invalid invitation token');
}

$auth = new AuthCore();
$conn = $auth->getConnection();

// Get invitation details
$stmt = $conn->prepare("
    SELECT ti.*, t.name as team_name, t.description as team_description,
           u.username as inviter_name
    FROM team_invitations ti
    JOIN teams t ON ti.team_id = t.id
    JOIN users u ON ti.invited_by = u.id
    WHERE ti.token = ? AND ti.status = 'pending'
");
$stmt->bind_param("s", $token);
$stmt->execute();
$invitation = $stmt->get_result()->fetch_assoc();

if (!$invitation) {
    die('Invitation not found or already processed');
}

// Check if invitation has expired
if (strtotime($invitation['expires_at']) < time()) {
    // Mark as expired
    $expire_stmt = $conn->prepare("UPDATE team_invitations SET status = 'expired' WHERE id = ?");
    $expire_stmt->bind_param("i", $invitation['id']);
    $expire_stmt->execute();
    
    die('Invitation has expired');
}

// Check if user is logged in
$user = null;
if ($auth->isLoggedIn()) {
    $user = $auth->getCurrentUser();
    
    // Check if logged-in user email matches invitation email
    if ($user['email'] !== $invitation['invited_email']) {
        die('This invitation is for a different email address');
    }
}

// Handle invitation acceptance
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$user) {
        // User needs to login or register first
        $_SESSION['invitation_token'] = $token;
        $_SESSION['invitation_email'] = $invitation['invited_email'];
        SecurityUtils::safeRedirect('../auth/login.php', 302, 'Please login to accept invitation');
        exit;
    }
    
    $conn->begin_transaction();
    
    try {
        // Update invitation status
        $update_stmt = $conn->prepare("
            UPDATE team_invitations 
            SET status = 'accepted', responded_at = NOW() 
            WHERE id = ?
        ");
        $update_stmt->bind_param("i", $invitation['id']);
        $update_stmt->execute();
        
        // Add user to team members
        $member_stmt = $conn->prepare("
            INSERT INTO team_members (team_id, user_id, role, status, invited_by)
            VALUES (?, ?, ?, 'active', ?)
        ");
        $member_stmt->bind_param("iiis", 
            $invitation['team_id'], 
            $user['id'], 
            $invitation['role'], 
            $invitation['invited_by']
        );
        $member_stmt->execute();
        
        // Log activity
        $activity_stmt = $conn->prepare("
            INSERT INTO team_activity_log (team_id, user_id, action, entity_type, entity_id, details)
            VALUES (?, ?, 'invitation_accepted', 'invitation', ?, ?)
        ");
        $details = json_encode([
            'invitation_id' => $invitation['id'],
            'role' => $invitation['role']
        ]);
        $activity_stmt->bind_param("iiis", $invitation['team_id'], $user['id'], $invitation['id'], $details);
        $activity_stmt->execute();
        
        $conn->commit();
        
        // Send welcome email
        $team_stmt = $conn->prepare("SELECT * FROM teams WHERE id = ?");
        $team_stmt->bind_param("i", $invitation['team_id']);
        $team_stmt->execute();
        $team = $team_stmt->get_result()->fetch_assoc();
        
        $inviter_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $inviter_stmt->bind_param("i", $invitation['invited_by']);
        $inviter_stmt->execute();
        $inviter = $inviter_stmt->get_result()->fetch_assoc();
        
        EmailHelper::sendTeamWelcomeEmail($team, $user, $inviter);
        
        $_SESSION['success_message'] = "Welcome to {$team['name']}! You've successfully joined the team.";
        SecurityUtils::safeRedirect('../core/dashboard.php?page=teams', 302, 'Invitation accepted');
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Failed to accept invitation: " . $e->getMessage());
        die('Failed to accept invitation. Please try again.');
    }
}

// Display invitation acceptance page
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Invitation - Bloxer</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        
        .invitation-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .invitation-header {
            margin-bottom: 30px;
        }
        
        .invitation-header i {
            font-size: 4rem;
            color: #667eea;
            margin-bottom: 20px;
        }
        
        .invitation-header h1 {
            color: #333;
            margin: 0 0 10px 0;
            font-size: 2rem;
        }
        
        .invitation-header p {
            color: #666;
            margin: 0;
            font-size: 1.1rem;
        }
        
        .team-info {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin: 30px 0;
            text-align: left;
        }
        
        .team-info h3 {
            color: #333;
            margin: 0 0 15px 0;
            font-size: 1.3rem;
        }
        
        .team-info p {
            color: #666;
            margin: 5px 0;
            line-height: 1.5;
        }
        
        .team-info .role {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-top: 10px;
        }
        
        .invitation-message {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            font-style: italic;
            color: #555;
        }
        
        .btn {
            display: inline-block;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 10px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: #e0e0e0;
            color: #333;
        }
        
        .btn-secondary:hover {
            background: #d0d0d0;
        }
        
        .expires-info {
            color: #ff6b6b;
            font-size: 0.9rem;
            margin-top: 20px;
        }
        
        .login-required {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .login-required h4 {
            color: #856404;
            margin: 0 0 10px 0;
        }
        
        .login-required p {
            color: #856404;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="invitation-card">
        <div class="invitation-header">
            <i class="fas fa-envelope-open-text"></i>
            <h1>You're Invited!</h1>
            <p>Join a team on Bloxer</p>
        </div>
        
        <div class="team-info">
            <h3><?php echo htmlspecialchars($invitation['team_name']); ?></h3>
            <p><?php echo htmlspecialchars($invitation['team_description'] ?: 'No description available'); ?></p>
            <p><strong>Invited by:</strong> <?php echo htmlspecialchars($invitation['inviter_name']); ?></p>
            <p><strong>Your role:</strong> <span class="role"><?php echo ucfirst(htmlspecialchars($invitation['role'])); ?></span></p>
        </div>
        
        <?php if (!empty($invitation['message'])): ?>
            <div class="invitation-message">
                <strong>Personal message:</strong><br>
                "<?php echo htmlspecialchars($invitation['message']); ?>"
            </div>
        <?php endif; ?>
        
        <?php if ($user): ?>
            <form method="POST">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check"></i> Accept Invitation
                </button>
                <a href="decline_invitation.php?token=<?php echo htmlspecialchars($token); ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Decline
                </a>
            </form>
        <?php else: ?>
            <div class="login-required">
                <h4>Login Required</h4>
                <p>You need to login or create an account to accept this invitation.</p>
            </div>
            <form method="POST">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Login & Accept
                </button>
                <a href="decline_invitation.php?token=<?php echo htmlspecialchars($token); ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Decline
                </a>
            </form>
        <?php endif; ?>
        
        <div class="expires-info">
            <i class="fas fa-clock"></i>
            This invitation expires on <?php echo date('M j, Y', strtotime($invitation['expires_at'])); ?>
        </div>
    </div>
</body>
</html>
