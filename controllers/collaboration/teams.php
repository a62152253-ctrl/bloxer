<?php
require_once __DIR__ . '/../../bootstrap.php';

$auth = new AuthCore();

if (!$auth->isLoggedIn()) {
    SecurityUtils::safeRedirect('../auth/login.php', 302, 'Login required');
}

$user = $auth->getCurrentUser();
$conn = $auth->getConnection();
$page = $_GET['page'] ?? 'teams';

// Handle team creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'create_team') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $company_name = trim($_POST['company_name'] ?? '');
    $website = trim($_POST['website'] ?? '');
    
    if (!empty($name)) {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $name));
        $slug = rtrim($slug, '-');
        
        // Check if slug already exists
        $check_stmt = $conn->prepare("SELECT id FROM teams WHERE slug = ?");
        $check_stmt->bind_param("s", $slug);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            $slug .= '-' . time();
        }
        
        $stmt = $conn->prepare("
            INSERT INTO teams (name, slug, description, owner_id, company_name, website)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("sssisss", $name, $slug, $description, $user['id'], $company_name, $website);
        
        if ($stmt->execute()) {
            $team_id = $stmt->insert_id;
            
            // Add owner as team member
            $member_stmt = $conn->prepare("
                INSERT INTO team_members (team_id, user_id, role, status, invited_by)
                VALUES (?, ?, 'owner', 'active', ?)
            ");
            $member_stmt->bind_param("iii", $team_id, $user['id'], $user['id']);
            $member_stmt->execute();
            
            // Log activity
            $activity_stmt = $conn->prepare("
                INSERT INTO team_activity_log (team_id, user_id, action, entity_type, entity_id, details)
                VALUES (?, ?, 'team_created', 'team', ?, ?)
            ");
            $details = json_encode(['team_name' => $name]);
            $activity_stmt->bind_param("iiis", $team_id, $user['id'], $team_id, $details);
            $activity_stmt->execute();
            
            $_SESSION['success_message'] = 'Team created successfully!';
        } else {
            $_SESSION['form_errors'] = ['Failed to create team'];
        }
    }
}

// Handle team invitation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'invite_member') {
    $team_id = $_POST['team_id'] ?? null;
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'developer';
    $message = trim($_POST['message'] ?? '');
    
    if ($team_id && $email && EmailHelper::validateEmail($email)) {
        // Check if user is team admin/owner
        $check_stmt = $conn->prepare("
            SELECT role FROM team_members 
            WHERE team_id = ? AND user_id = ? AND status = 'active'
        ");
        $check_stmt->bind_param("ii", $team_id, $user['id']);
        $check_stmt->execute();
        $member_role = $check_stmt->get_result()->fetch_assoc();
        
        if ($member_role && in_array($member_role['role'], ['owner', 'admin'])) {
            // Check if already invited or member
            $existing_stmt = $conn->prepare("
                SELECT id FROM team_members WHERE team_id = ? AND user_id = ?
                UNION
                SELECT id FROM team_invitations WHERE team_id = ? AND invited_email = ? AND status = 'pending'
            ");
            
            // Get user ID from email if exists
            $user_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $user_stmt->bind_param("s", $email);
            $user_stmt->execute();
            $existing_user = $user_stmt->get_result()->fetch_assoc();
            $user_id_for_check = $existing_user['id'] ?? null;
            
            $existing_stmt->bind_param("iisi", $team_id, $user_id_for_check, $team_id, $email);
            $existing_stmt->execute();
            
            if ($existing_stmt->get_result()->num_rows === 0) {
                $token = EmailHelper::generateInvitationToken();
                
                $invite_stmt = $conn->prepare("
                    INSERT INTO team_invitations (team_id, invited_email, invited_by, role, token, message)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $invite_stmt->bind_param("isssss", $team_id, $email, $user['id'], $role, $token, $message);
                
                if ($invite_stmt->execute()) {
                    // Get team details for email
                    $team_stmt = $conn->prepare("SELECT * FROM teams WHERE id = ?");
                    $team_stmt->bind_param("i", $team_id);
                    $team_stmt->execute();
                    $team = $team_stmt->get_result()->fetch_assoc();
                    
                    // Get invitation details
                    $invitation_id = $conn->insert_id;
                    $inv_stmt = $conn->prepare("SELECT * FROM team_invitations WHERE id = ?");
                    $inv_stmt->bind_param("i", $invitation_id);
                    $inv_stmt->execute();
                    $invitation = $inv_stmt->get_result()->fetch_assoc();
                    
                    // Send email
                    if (EmailHelper::sendTeamInvitation($team, $invitation, $user)) {
                        $_SESSION['success_message'] = 'Invitation sent successfully!';
                        
                        // Log activity
                        $activity_stmt = $conn->prepare("
                            INSERT INTO team_activity_log (team_id, user_id, action, entity_type, entity_id, details)
                            VALUES (?, ?, 'invitation_sent', 'invitation', ?, ?)
                        ");
                        $details = json_encode(['email' => $email, 'role' => $role]);
                        $activity_stmt->bind_param("iiis", $team_id, $user['id'], $invitation_id, $details);
                        $activity_stmt->execute();
                    } else {
                        $_SESSION['form_errors'] = ['Failed to send invitation email'];
                    }
                } else {
                    $_SESSION['form_errors'] = ['Failed to create invitation'];
                }
            } else {
                $_SESSION['form_errors'] = ['User already invited or is a member'];
            }
        } else {
            $_SESSION['form_errors'] = ['You do not have permission to invite members'];
        }
    } else {
        $_SESSION['form_errors'] = ['Invalid email address'];
    }
}

// Get user's teams
$teams_stmt = $conn->prepare("
    SELECT t.*, tm.role as user_role,
           (SELECT COUNT(*) FROM team_members WHERE team_id = t.id AND status = 'active') as member_count
    FROM teams t
    JOIN team_members tm ON t.id = tm.team_id
    WHERE tm.user_id = ? AND tm.status = 'active'
    ORDER BY t.created_at DESC
");
$teams_stmt->bind_param("i", $user['id']);
$teams_stmt->execute();
$teams = $teams_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get pending invitations
$invitations_stmt = $conn->prepare("
    SELECT ti.*, t.name as team_name, t.description as team_description,
           u.username as inviter_name, u.email as inviter_email
    FROM team_invitations ti
    JOIN teams t ON ti.team_id = t.id
    JOIN users u ON ti.invited_by = u.id
    WHERE ti.invited_email = ? AND ti.status = 'pending'
    ORDER BY ti.created_at DESC
");
$invitations_stmt->bind_param("s", $user['email']);
$invitations_stmt->execute();
$pending_invitations = $invitations_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get team activity for teams where user is member
$team_ids = array_column($teams, 'id');
$activities = [];
if (!empty($team_ids)) {
    $placeholders = str_repeat('?,', count($team_ids) - 1) . '?';
    $types = str_repeat('i', count($team_ids));
    
    $activity_stmt = $conn->prepare("
        SELECT tal.*, t.name as team_name, u.username as user_name
        FROM team_activity_log tal
        JOIN teams t ON tal.team_id = t.id
        JOIN users u ON tal.user_id = u.id
        WHERE tal.team_id IN ($placeholders)
        ORDER BY tal.created_at DESC
        LIMIT 20
    ");
    $activity_stmt->bind_param($types, ...$team_ids);
    $activity_stmt->execute();
    $activities = $activity_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Management - Bloxer</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        .teams-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .teams-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .teams-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .team-card {
            background: linear-gradient(135deg, var(--bg-secondary), var(--bg-tertiary));
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 25px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .team-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(23, 105, 255, 0.15);
        }
        
        .team-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 20px;
        }
        
        .team-info h3 {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0 0 5px 0;
        }
        
        .team-role {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            background: var(--accent);
            color: white;
        }
        
        .team-description {
            color: var(--text-secondary);
            margin-bottom: 20px;
            line-height: 1.5;
        }
        
        .team-stats {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .team-stat {
            text-align: center;
        }
        
        .team-stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--accent);
        }
        
        .team-stat-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
            text-transform: uppercase;
        }
        
        .team-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-small {
            padding: 8px 16px;
            font-size: 0.85rem;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: var(--bg-secondary);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 600;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--glass-border);
            border-radius: 8px;
            background: var(--bg-primary);
            color: var(--text-primary);
        }
        
        .invitations-section {
            background: var(--bg-secondary);
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .invitation-item {
            background: var(--bg-primary);
            border: 1px solid var(--glass-border);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .invitation-info h4 {
            margin: 0 0 5px 0;
            color: var(--text-primary);
        }
        
        .invitation-info p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .activity-feed {
            background: var(--bg-secondary);
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            padding: 25px;
        }
        
        .activity-item {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid var(--glass-border);
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            background: var(--accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-content h4 {
            margin: 0 0 5px 0;
            color: var(--text-primary);
            font-size: 0.95rem;
        }
        
        .activity-content p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 0.85rem;
        }
    </style>
</head>
<body class="app-studio">
    <div class="studio-shell">
        <?php include __DIR__ . '/../core/dashboard_sidebar.php'; ?>
        
        <div class="studio-main-wrap">
            <header class="studio-header top-bar">
                <div class="studio-header-main">
                    <button class="btn btn-small studio-menu-toggle" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div>
                        <h1>Team Management</h1>
                    </div>
                </div>
                
                <div class="studio-header-meta">
                    <div class="studio-user-chip">
                        <span class="studio-user-avatar"><?php echo strtoupper(substr($user['username'], 0, 1)); ?></span>
                        <span><?php echo htmlspecialchars($user['username']); ?></span>
                    </div>
                </div>
            </header>
            
            <main class="studio-main workspace-area">
                <div class="teams-container">
                    <div class="teams-header">
                        <div>
                            <h2>Your Teams</h2>
                            <p>Manage your teams and collaborate with others</p>
                        </div>
                        <button class="btn btn-primary" onclick="showCreateTeamModal()">
                            <i class="fas fa-plus"></i> Create Team
                        </button>
                    </div>
                    
                    <?php if (!empty($teams)): ?>
                        <div class="teams-grid">
                            <?php foreach ($teams as $team): ?>
                                <div class="team-card">
                                    <div class="team-header">
                                        <div class="team-info">
                                            <h3><?php echo htmlspecialchars($team['name']); ?></h3>
                                            <span class="team-role"><?php echo htmlspecialchars($team['user_role']); ?></span>
                                        </div>
                                        <div class="team-actions">
                                            <button class="btn btn-secondary btn-small" onclick="showInviteModal(<?php echo $team['id']; ?>, '<?php echo htmlspecialchars($team['name']); ?>')">
                                                <i class="fas fa-user-plus"></i> Invite
                                            </button>
                                            <button class="btn btn-primary btn-small" onclick="viewTeam(<?php echo $team['id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="team-description">
                                        <?php echo htmlspecialchars($team['description'] ?: 'No description available'); ?>
                                    </div>
                                    
                                    <div class="team-stats">
                                        <div class="team-stat">
                                            <div class="team-stat-number"><?php echo $team['member_count']; ?></div>
                                            <div class="team-stat-label">Members</div>
                                        </div>
                                        <div class="team-stat">
                                            <div class="team-stat-number">0</div>
                                            <div class="team-stat-label">Projects</div>
                                        </div>
                                        <div class="team-stat">
                                            <div class="team-stat-number">0</div>
                                            <div class="team-stat-label">Reviews</div>
                                        </div>
                                    </div>
                                    
                                    <?php if ($team['company_name']): ?>
                                        <p style="color: var(--text-secondary); font-size: 0.85rem; margin-top: 10px;">
                                            <i class="fas fa-building"></i> <?php echo htmlspecialchars($team['company_name']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="studio-empty-state">
                            <i class="fas fa-users"></i>
                            <h3>No Teams Yet</h3>
                            <p>Create your first team to start collaborating with other developers</p>
                            <button class="btn btn-primary" onclick="showCreateTeamModal()">
                                <i class="fas fa-plus"></i> Create Your First Team
                            </button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($pending_invitations)): ?>
                        <div class="invitations-section">
                            <h3>Pending Invitations</h3>
                            <?php foreach ($pending_invitations as $invitation): ?>
                                <div class="invitation-item">
                                    <div class="invitation-info">
                                        <h4><?php echo htmlspecialchars($invitation['team_name']); ?></h4>
                                        <p>Invited by <?php echo htmlspecialchars($invitation['inviter_name']); ?> as <?php echo htmlspecialchars($invitation['role']); ?></p>
                                    </div>
                                    <div class="invitation-actions">
                                        <a href="accept_invitation.php?token=<?php echo htmlspecialchars($invitation['token']); ?>" class="btn btn-primary btn-small">
                                            <i class="fas fa-check"></i> Accept
                                        </a>
                                        <a href="decline_invitation.php?token=<?php echo htmlspecialchars($invitation['token']); ?>" class="btn btn-secondary btn-small">
                                            <i class="fas fa-times"></i> Decline
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($activities)): ?>
                        <div class="activity-feed">
                            <h3>Recent Activity</h3>
                            <?php foreach ($activities as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-<?php echo getActivityIcon($activity['action']); ?>"></i>
                                    </div>
                                    <div class="activity-content">
                                        <h4><?php echo getActivityMessage($activity); ?></h4>
                                        <p><?php echo timeAgo($activity['created_at']); ?> in <?php echo htmlspecialchars($activity['team_name']); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Create Team Modal -->
    <div id="createTeamModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Create New Team</h3>
                <button class="modal-close" onclick="closeModal('createTeamModal')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create_team">
                
                <div class="form-group">
                    <label for="teamName">Team Name *</label>
                    <input type="text" id="teamName" name="name" required placeholder="My Awesome Team">
                </div>
                
                <div class="form-group">
                    <label for="teamDescription">Description</label>
                    <textarea id="teamDescription" name="description" placeholder="What's your team about?"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="companyName">Company Name</label>
                    <input type="text" id="companyName" name="company_name" placeholder="Optional">
                </div>
                
                <div class="form-group">
                    <label for="website">Website</label>
                    <input type="url" id="website" name="website" placeholder="https://example.com">
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('createTeamModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Team</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Invite Member Modal -->
    <div id="inviteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Invite Team Member</h3>
                <button class="modal-close" onclick="closeModal('inviteModal')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="invite_member">
                <input type="hidden" id="inviteTeamId" name="team_id">
                
                <div class="form-group">
                    <label for="inviteEmail">Email Address *</label>
                    <input type="email" id="inviteEmail" name="email" required placeholder="colleague@example.com">
                </div>
                
                <div class="form-group">
                    <label for="inviteRole">Role</label>
                    <select id="inviteRole" name="role">
                        <option value="developer">Developer</option>
                        <option value="designer">Designer</option>
                        <option value="tester">Tester</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="inviteMessage">Personal Message (Optional)</label>
                    <textarea id="inviteMessage" name="message" placeholder="Hey! I'd love for you to join our team..."></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('inviteModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Invitation</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function showCreateTeamModal() {
            document.getElementById('createTeamModal').classList.add('active');
        }
        
        function showInviteModal(teamId, teamName) {
            document.getElementById('inviteTeamId').value = teamId;
            document.getElementById('inviteModal').classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        function viewTeam(teamId) {
            window.location.href = 'team_details.php?id=' + teamId;
        }
        
        // Close modals when clicking outside
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.classList.remove('active');
                }
            });
        });
        
        <?php
        function getActivityIcon($action) {
            $icons = [
                'team_created' => 'users',
                'invitation_sent' => 'envelope',
                'invitation_accepted' => 'check',
                'member_joined' => 'user-plus',
                'project_created' => 'folder',
                'review_created' => 'code-branch'
            ];
            return $icons[$action] ?? 'circle';
        }
        
        function getActivityMessage($activity) {
            $messages = [
                'team_created' => 'created the team',
                'invitation_sent' => 'sent an invitation',
                'invitation_accepted' => 'accepted an invitation',
                'member_joined' => 'joined the team',
                'project_created' => 'created a project',
                'review_created' => 'created a code review'
            ];
            return $activity['user_name'] . ' ' . ($messages[$activity['action']] ?? 'performed an action');
        }
        
        function timeAgo($datetime) {
            $time = strtotime($datetime);
            $now = time();
            $diff = $now - $time;
            
            if ($diff < 60) return 'just now';
            if ($diff < 3600) return floor($diff/60) . ' minutes ago';
            if ($diff < 86400) return floor($diff/3600) . ' hours ago';
            if ($diff < 604800) return floor($diff/86400) . ' days ago';
            return date('M j, Y', $time);
        }
        ?>
    </script>
</body>
</html>
