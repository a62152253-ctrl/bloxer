<?php
require_once __DIR__ . '/../../bootstrap.php';

$auth = new AuthCore();

if (!$auth->isLoggedIn()) {
    SecurityUtils::safeRedirect('../auth/login.php');
}

if (!$auth->isDeveloper()) {
    SecurityUtils::safeRedirect('../marketplace/marketplace.php');
}

$user = $auth->getCurrentUser();
$page = $_GET['page'] ?? 'overview';
$project_id = $_GET['project_id'] ?? null;

// Get user's projects for selector
$conn = $auth->getConnection();
$stmt = $conn->prepare("
    SELECT p.*, COUNT(pf.id) as file_count 
    FROM projects p 
    LEFT JOIN project_files pf ON p.id = pf.project_id 
    WHERE p.user_id = ? 
    GROUP BY p.id 
    ORDER BY p.updated_at DESC
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get current project
$current_project = null;
if ($project_id) {
    $stmt = $conn->prepare("
        SELECT p.*, COUNT(pf.id) as file_count 
        FROM projects p 
        LEFT JOIN project_files pf ON p.id = pf.project_id 
        WHERE p.id = ? AND p.user_id = ?
        GROUP BY p.id
    ");
    $stmt->bind_param("ii", $project_id, $user['id']);
    $stmt->execute();
    $current_project = $stmt->get_result()->fetch_assoc();
}

// Handle tool actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($_POST['action']) {
        case 'track_activity':
            $activity_type = $_POST['activity_type'] ?? '';
            $activity_data = $_POST['activity_data'] ?? '{}';
            
            if ($activity_type && $project_id) {
                $stmt = $conn->prepare("
                    INSERT INTO user_activity (user_id, project_id, activity_type, activity_data, created_at) 
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $activity_data_json = json_encode($activity_data);
                $stmt->bind_param("iiss", $user['id'], $project_id, $activity_type, $activity_data_json);
                $stmt->execute();
                
                echo json_encode(['success' => true]);
                exit();
            }
            break;
            
        case 'get_activity':
            if ($project_id) {
                $stmt = $conn->prepare("
                    SELECT * FROM user_activity 
                    WHERE project_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT 50
                ");
                $stmt->bind_param("i", $project_id);
                $stmt->execute();
                $activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                
                echo json_encode(['success' => true, 'activities' => $activities]);
                exit();
            }
            break;
            
        case 'track_visitor':
            $visitor_data = $_POST['visitor_data'] ?? '{}';
            $page_url = $_POST['page_url'] ?? '';
            
            if ($page_url) {
                $visitor_ip = $_SERVER['REMOTE_ADDR'] ?? '';
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                
                $stmt = $conn->prepare("
                    INSERT INTO visitor_tracking (user_id, project_id, visitor_ip, user_agent, page_url, visitor_data, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $visitor_data_json = json_encode($visitor_data);
                $stmt->bind_param("isssss", $user['id'], $project_id, $visitor_ip, $user_agent, $page_url, $visitor_data_json);
                $stmt->execute();
                
                echo json_encode(['success' => true]);
                exit();
            }
            break;
            
        case 'get_visitors':
            if ($project_id) {
                $stmt = $conn->prepare("
                    SELECT * FROM visitor_tracking 
                    WHERE project_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT 100
                ");
                $stmt->bind_param("i", $project_id);
                $stmt->execute();
                $visitors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                
                echo json_encode(['success' => true, 'visitors' => $visitors]);
                exit();
            }
            break;
            
        case 'clear_visitors':
            if ($project_id) {
                // Check if visitor_tracking table exists
                $table_check = $conn->query("SHOW TABLES LIKE 'visitor_tracking'");
                
                if ($table_check->num_rows > 0) {
                    $stmt = $conn->prepare("DELETE FROM visitor_tracking WHERE project_id = ?");
                    $stmt->bind_param("i", $project_id);
                    $stmt->execute();
                }
                
                echo json_encode(['success' => true]);
                exit();
            }
            break;
    }
}

// Get analytics data
$analytics_data = [];
if ($current_project) {
    // Check if user_activity table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'user_activity'");
    
    if ($table_check->num_rows > 0) {
        // Get activity summary
        $stmt = $conn->prepare("
            SELECT activity_type, COUNT(*) as count 
            FROM user_activity 
            WHERE project_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY activity_type
        ");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $activity_summary = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        $activity_summary = [];
    }
    
    // Check if visitor_tracking table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'visitor_tracking'");
    
    if ($table_check->num_rows > 0) {
        // Get visitor summary
        $stmt = $conn->prepare("
            SELECT DATE(created_at) as date, COUNT(*) as visitors
            FROM visitor_tracking 
            WHERE project_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date
        ");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $visitor_summary = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        $visitor_summary = [];
    }
    
    $analytics_data = [
        'activity_summary' => $activity_summary,
        'visitor_summary' => $visitor_summary
    ];
}

?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Developer Tools - Bloxer</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/tools.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="logo">
                <h1>Bloxer</h1>
                <p>Developer Tools</p>
            </div>
            
            <nav>
                <div class="nav-item" onclick="window.location.href='dashboard.php'">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Dashboard</span>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Project</div>
                    <div style="padding: 10px 15px; margin-bottom: 15px;">
                        <select id="project-selector" onchange="changeProject()" style="width: 100%; padding: 8px 12px; border-radius: 8px; background: rgba(255,255,255,0.1); border: 1px solid var(--glass-border); color: var(--text-primary);">
                            <option value="">Select Project</option>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?php echo $project['id']; ?>" <?php echo $project_id == $project['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($project['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Tools</div>
                    <div class="nav-item <?php echo $page === 'overview' ? 'active' : ''; ?>" onclick="navigateToPage('overview')">
                        <i class="fas fa-chart-line"></i>
                        <span>Overview</span>
                    </div>
                    <div class="nav-item <?php echo $page === 'preview' ? 'active' : ''; ?>" onclick="navigateToPage('preview')">
                        <i class="fas fa-eye"></i>
                        <span>Live Preview</span>
                    </div>
                    <div class="nav-item <?php echo $page === 'activity' ? 'active' : ''; ?>" onclick="navigateToPage('activity')">
                        <i class="fas fa-history"></i>
                        <span>Activity Tracking</span>
                    </div>
                    <div class="nav-item <?php echo $page === 'visitors' ? 'active' : ''; ?>" onclick="navigateToPage('visitors')">
                        <i class="fas fa-users"></i>
                        <span>Visitor Monitor</span>
                    </div>
                    <div class="nav-item <?php echo $page === 'performance' ? 'active' : ''; ?>" onclick="navigateToPage('performance')">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Performance</span>
                    </div>
                                    </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Settings</div>
                    <div class="nav-item" onclick="window.location.href='marketplace-settings.php'">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </div>
                </div>
            </nav>
            
            <div style="margin-top: auto; padding-top: 20px; border-top: 1px solid var(--glass-border);">
                <div class="nav-item" onclick="window.location.href='logout.php'">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <button class="btn btn-small" onclick="toggleSidebar()" style="background: transparent; border: 1px solid var(--glass-border); padding: 8px 12px;">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h3 style="margin: 0; color: var(--text-primary);">
                        <?php 
                        $pageTitles = [
                            'overview' => 'Tools Overview',
                            'preview' => 'Live Preview',
                            'activity' => 'Activity Tracking',
                            'visitors' => 'Visitor Monitor',
                            'performance' => 'Performance Analytics'
                        ];
                        echo $pageTitles[$page] ?? 'Developer Tools';
                        ?>
                    </h3>
                </div>
                
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="width: 1px; height: 24px; background: var(--glass-border);"></div>
                    <div style="display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,0.05); padding: 5px 15px; border-radius: 20px; border: 1px solid var(--glass-border);">
                        <div style="width: 28px; height: 28px; border-radius: 50%; background: var(--accent); display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.8rem;">
                            <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                        </div>
                        <span style="font-weight: 500; font-size: 0.9rem;"><?php echo htmlspecialchars($user['username']); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Page Content -->
            <div class="tools-content">
                <?php if ($page === 'overview'): ?>
                    <?php include 'tools/overview.php'; ?>
                <?php elseif ($page === 'preview' && $current_project): ?>
                    <?php include 'tools/preview.php'; ?>
                <?php elseif ($page === 'activity' && $current_project): ?>
                    <?php include 'tools/activity.php'; ?>
                <?php elseif ($page === 'visitors' && $current_project): ?>
                    <?php include 'tools/visitors.php'; ?>
                <?php elseif ($page === 'performance' && $current_project): ?>
                    <?php include 'tools/performance.php'; ?>
                                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-folder-open" style="font-size: 4rem; margin-bottom: 20px; opacity: 0.5;"></i>
                        <h3>Select a Project</h3>
                        <p>Choose a project from the selector above to access developer tools.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function navigateToPage(page) {
            const projectId = document.getElementById('project-selector').value;
            const url = `tools.php?page=${page}${projectId ? '&project_id=' + projectId : ''}`;
            window.location.href = url;
        }
        
        function changeProject() {
            const projectId = document.getElementById('project-selector').value;
            const currentPage = '<?php echo $page; ?>';
            const url = `tools.php?page=${currentPage}${projectId ? '&project_id=' + projectId : ''}`;
            window.location.href = url;
        }
        
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('collapsed');
        }
        
        // Auto-refresh for real-time data
        setInterval(() => {
            if (document.getElementById('project-selector').value) {
                refreshData();
            }
        }, 30000);
        
        function refreshData() {
            const page = '<?php echo $page; ?>';
            const projectId = document.getElementById('project-selector').value;
            
            if (page === 'activity') {
                loadActivityData();
            } else if (page === 'visitors') {
                loadVisitorData();
            }
        }
        
        function loadActivityData() {
            const projectId = document.getElementById('project-selector').value;
            if (!projectId) return;
            
            fetch('tools.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_activity&project_id=${projectId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateActivityDisplay(data.activities);
                }
            });
        }
        
        function loadVisitorData() {
            const projectId = document.getElementById('project-selector').value;
            if (!projectId) return;
            
            fetch('tools.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_visitors&project_id=${projectId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateVisitorDisplay(data.visitors);
                }
            });
        }
        
        function trackActivity(type, data) {
            const projectId = document.getElementById('project-selector').value;
            if (!projectId) return;
            
            fetch('tools.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=track_activity&project_id=${projectId}&activity_type=${type}&activity_data=${JSON.stringify(data)}`
            });
        }
        
        function trackVisitor(pageUrl, data) {
            const projectId = document.getElementById('project-selector').value;
            if (!projectId) return;
            
            fetch('tools.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=track_visitor&project_id=${projectId}&page_url=${pageUrl}&visitor_data=${JSON.stringify(data)}`
            });
        }
    </script>
</body>
</html>
