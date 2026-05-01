<?php
require_once __DIR__ . '/../../bootstrap.php';

$auth = new AuthCore();

if (!$auth->isLoggedIn()) {
    SecurityUtils::safeRedirect('../auth/login.php', 302, 'Unauthorized access attempt');
}

if (!$auth->isDeveloper()) {
    SecurityUtils::safeRedirect('../marketplace/marketplace.php', 302, 'Developer access required');
}

$user = $auth->getCurrentUser();

// Handle version actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_version') {
    $app_id = $_POST['app_id'] ?? null;
    $version = trim($_POST['version'] ?? '');
    $changelog = trim($_POST['changelog'] ?? '');
    $release_notes = trim($_POST['release_notes'] ?? '');
    
    if ($app_id && $version && $changelog) {
        $conn = $auth->getConnection();
        
        // Verify app ownership
        $stmt = $conn->prepare("SELECT a.id, a.title FROM apps a JOIN projects p ON a.project_id = p.id WHERE a.id = ? AND p.user_id = ?");
        $stmt->bind_param("ii", $app_id, $user['id']);
        $stmt->execute();
        $app = $stmt->get_result()->fetch_assoc();
        
        if ($app) {
            // Check if version already exists
            $stmt = $conn->prepare("SELECT id FROM app_versions WHERE app_id = ? AND version = ?");
            $stmt->bind_param("is", $app_id, $version);
            $stmt->execute();
            
            if ($stmt->get_result()->num_rows === 0) {
                // Get current project files
                $stmt = $conn->prepare("SELECT file_path, file_name, file_type, content FROM project_files WHERE project_id = (SELECT project_id FROM apps WHERE id = ?)");
                $stmt->bind_param("i", $app_id);
                $stmt->execute();
                $files = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                
                // Calculate file size (number of files)
                $file_size = count($files);
                
                // Set previous versions as non-current
                $stmt = $conn->prepare("UPDATE app_versions SET is_current = FALSE WHERE app_id = ?");
                $stmt->bind_param("i", $app_id);
                $stmt->execute();
                
                // Insert new version
                $stmt = $conn->prepare("
                    INSERT INTO app_versions (app_id, version, changelog, file_size, is_current) 
                    VALUES (?, ?, ?, ?, TRUE)
                ");
                $stmt->bind_param("issi", $app_id, $version, $changelog, $file_size);
                
                if ($stmt->execute()) {
                    $version_id = $stmt->insert_id;
                    
                    // Handle additional file uploads
                    if (!empty($_FILES['additional_files']['name'][0])) {
                        $upload_dir = '../uploads/version_files/' . $version_id . '/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        $uploaded_count = 0;
                        foreach ($_FILES['additional_files']['name'] as $key => $name) {
                            if ($_FILES['additional_files']['error'][$key] === UPLOAD_ERR_OK) {
                                $tmp_name = $_FILES['additional_files']['tmp_name'][$key];
                                $file_size = $_FILES['additional_files']['size'][$key];
                                $file_type = $_FILES['additional_files']['type'][$key];
                                
                                // Validate file
                                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 
                                                'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                                'text/plain', 'application/zip', 'application/x-rar-compressed'];
                                $max_size = 10 * 1024 * 1024; // 10MB
                                
                                if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
                                    $file_extension = pathinfo($name, PATHINFO_EXTENSION);
                                    $unique_name = uniqid() . '_' . time() . '.' . $file_extension;
                                    $file_path = $upload_dir . $unique_name;
                                    
                                    if (move_uploaded_file($tmp_name, $file_path)) {
                                        // Store file info in database
                                        $stmt = $conn->prepare("
                                            INSERT INTO version_files (version_id, file_name, original_name, file_path, file_size, file_type) 
                                            VALUES (?, ?, ?, ?, ?, ?)
                                        ");
                                        $relative_path = str_replace('../', '', $file_path);
                                        $stmt->bind_param("isssis", $version_id, $unique_name, $name, $relative_path, $file_size, $file_type);
                                        $stmt->execute();
                                        $uploaded_count++;
                                    }
                                }
                            }
                        }
                        
                        if ($uploaded_count > 0) {
                            $file_size += $uploaded_count;
                            // Update version file count
                            $stmt = $conn->prepare("UPDATE app_versions SET file_size = ? WHERE id = ?");
                            $stmt->bind_param("ii", $file_size, $version_id);
                            $stmt->execute();
                        }
                    }
                    
                    $_SESSION['success_message'] = "Version $version created successfully for '{$app['title']}'";
                    SecurityUtils::safeRedirect('version-control.php', 302, 'Version created successfully');
                } else {
                    $_SESSION['form_errors'] = ['Failed to create version: ' . $stmt->error];
                }
            } else {
                $_SESSION['form_errors'] = ['Version already exists'];
            }
        } else {
            $_SESSION['form_errors'] = ['App not found or access denied'];
        }
    } else {
        $_SESSION['form_errors'] = ['All fields are required'];
    }
    
    if (isset($_SESSION['form_errors'])) {
        $_SESSION['form_data'] = $_POST;
        header('Location: version-control.php');
        exit();
    }
    } elseif ($action === 'set_current') {
        $version_id = $_POST['version_id'] ?? null;
        
        if ($version_id) {
            $conn = $auth->getConnection();
            
            // Get version and app details
            $stmt = $conn->prepare("
                SELECT av.app_id, av.version 
                FROM app_versions av 
                JOIN apps a ON av.app_id = a.id 
                JOIN projects p ON a.project_id = p.id 
                WHERE av.id = ? AND p.user_id = ?
            ");
            $stmt->bind_param("ii", $version_id, $user['id']);
            $stmt->execute();
            $version = $stmt->get_result()->fetch_assoc();
            
            if ($version) {
                // Set all versions as non-current
                $stmt = $conn->prepare("UPDATE app_versions SET is_current = FALSE WHERE app_id = ?");
                $stmt->bind_param("i", $version['app_id']);
                $stmt->execute();
                
                // Set selected version as current
                $stmt = $conn->prepare("UPDATE app_versions SET is_current = TRUE WHERE id = ?");
                $stmt->bind_param("i", $version_id);
                $stmt->execute();
                
                echo json_encode(['success' => true, 'message' => "Version {$version['version']} set as current"]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Version not found or access denied']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Version ID required']);
        }
        exit();
    }
}

// Get user's published apps
$conn = $auth->getConnection();
$stmt = $conn->prepare("
    SELECT a.*, p.name as project_name 
    FROM apps a 
    JOIN projects p ON a.project_id = p.id 
    WHERE p.user_id = ? 
    ORDER BY a.published_at DESC
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get versions for each app
foreach ($apps as &$app) {
    $stmt = $conn->prepare("SELECT * FROM app_versions WHERE app_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $app['id']);
    $stmt->execute();
    $versions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get files for each version
    foreach ($versions as &$version) {
        $stmt = $conn->prepare("SELECT * FROM version_files WHERE version_id = ? ORDER BY uploaded_at ASC");
        $stmt->bind_param("i", $version['id']);
        $stmt->execute();
        $version['files'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    $app['versions'] = $versions;
}
unset($app);

// Helper functions
function getFileIconClass($fileType) {
    if (strpos($fileType, 'image/') === 0) return 'fa-image';
    if (strpos($fileType, 'pdf') !== false) return 'fa-file-pdf';
    if (strpos($fileType, 'word') !== false || strpos($fileType, 'document') !== false) return 'fa-file-word';
    if (strpos($fileType, 'zip') !== false || strpos($fileType, 'rar') !== false) return 'fa-file-archive';
    if (strpos($fileType, 'text') !== false) return 'fa-file-alt';
    return 'fa-file';
}

function formatFileSizeDisplay($bytes) {
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return number_format($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Version Control - Bloxer</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="logo">
                <h1>Bloxer</h1>
                <p>Version Control</p>
            </div>
            
            <nav>
                <div class="nav-item" onclick="window.location.href='dashboard.php?page=workspace'">
                    <i class="fas fa-code"></i>
                    <span>Workspace</span>
                </div>
                <div class="nav-item" onclick="window.location.href='dashboard.php?page=projects'">
                    <i class="fas fa-folder"></i>
                    <span>Projects</span>
                </div>
                <div class="nav-item active" onclick="window.location.href='version-control.php'">
                    <i class="fas fa-code-branch"></i>
                    <span>Version Control</span>
                </div>
                <div class="nav-item" onclick="window.location.href='dashboard.php?page=publish'">
                    <i class="fas fa-rocket"></i>
                    <span>Publish Center</span>
                </div>
            </nav>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <header class="main-header">
                <h1>Version Control</h1>
                <p>Manage app versions and updates</p>
            </header>
            
            <div class="content-area">
                <?php if (isset($_SESSION['form_errors'])): ?>
                    <div class="error-message">
                        <?php foreach ($_SESSION['form_errors'] as $error): ?>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        <?php endforeach; ?>
                        <?php unset($_SESSION['form_errors']); unset($_SESSION['form_data']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="success-message">
                        <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                        <?php unset($_SESSION['success_message']); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Create New Version -->
                <div class="version-create-section">
                    <div class="version-create-header">
                        <div class="version-create-title">
                            <h2>Create New Version</h2>
                            <p>Package your app updates and release them to users</p>
                        </div>
                        <div class="version-steps">
                            <div class="step active">
                                <div class="step-number">1</div>
                                <span class="step-label">Basic Info</span>
                            </div>
                            <div class="step">
                                <div class="step-number">2</div>
                                <span class="step-label">Release Notes</span>
                            </div>
                            <div class="step">
                                <div class="step-number">3</div>
                                <span class="step-label">Assets</span>
                            </div>
                        </div>
                    </div>
                    
                    <form method="POST" action="" enctype="multipart/form-data" class="version-create-form">
                        <input type="hidden" name="action" value="create_version">
                        
                        <!-- Step 1: Basic Info -->
                        <div class="version-step-section">
                            <div class="step-section-header">
                                <div class="step-section-icon">
                                    <i class="fas fa-info-circle"></i>
                                </div>
                                <div class="step-section-content">
                                    <h3>Basic Information</h3>
                                    <p>Select the app and version details</p>
                                </div>
                            </div>
                            
                            <div class="version-step-card">
                                <div class="form-group">
                                    <label for="app_id">Select Application</label>
                                    <select id="app_id" name="app_id" required>
                                        <option value="">Choose an application...</option>
                                        <?php foreach ($apps as $app): ?>
                                            <option value="<?php echo $app['id']; ?>">
                                                <?php echo htmlspecialchars($app['title']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="version">Version Number</label>
                                    <input type="text" id="version" name="version" placeholder="1.0.0, 1.1.0, 2.0.0" required>
                                    <small>Follow semantic versioning (major.minor.patch)</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Step 2: Release Notes -->
                        <div class="version-step-section">
                            <div class="step-section-header">
                                <div class="step-section-icon">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <div class="step-section-content">
                                    <h3>Release Notes</h3>
                                    <p>Document what's new in this version</p>
                                </div>
                            </div>
                            
                            <div class="version-step-card">
                                <div class="form-group">
                                    <label for="changelog">Changelog</label>
                                    <textarea id="changelog" name="changelog" rows="8" placeholder="• Added new feature X&#10;• Fixed bug Y&#10;• Improved performance Z" required></textarea>
                                    <small>Use bullet points for better readability</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Step 3: Assets -->
                        <div class="version-step-section">
                            <div class="step-section-header">
                                <div class="step-section-icon">
                                    <i class="fas fa-box"></i>
                                </div>
                                <div class="step-section-content">
                                    <h3>Additional Assets</h3>
                                    <p>Upload supporting files and documentation</p>
                                </div>
                            </div>
                            
                            <div class="version-step-card">
                                <div class="form-group">
                                    <label>Additional Files (Optional)</label>
                                    <div class="file-upload-area" id="fileUploadArea">
                                        <div class="upload-content">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                            <p>Drag and drop files here or click to browse</p>
                                            <p class="upload-hint">Support for images, documents, and other assets (max 10MB per file)</p>
                                        </div>
                                        <input type="file" id="additionalFiles" name="additional_files[]" multiple accept="image/*,.pdf,.doc,.docx,.txt,.zip,.rar">
                                    </div>
                                    <div class="file-list" id="fileList"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="version-create-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-rocket"></i> Publish Version
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="resetForm()">
                                <i class="fas fa-times"></i> Clear Form
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Version History -->
                <div class="version-history-section">
                    <div class="version-history-header">
                        <div class="version-history-title">
                            <h2>Version History</h2>
                            <p>Track all releases and manage version deployment</p>
                        </div>
                        <div class="version-history-stats">
                            <div class="version-stat">
                                <span class="version-stat-number"><?php echo array_sum(array_map(fn($app) => count($app['versions'] ?? []), $apps)); ?></span>
                                <span class="version-stat-label">Total Versions</span>
                            </div>
                            <div class="version-stat">
                                <span class="version-stat-number"><?php echo count(array_filter($apps, fn($app) => !empty($app['versions']))); ?></span>
                                <span class="version-stat-label">Apps with Versions</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="version-history-grid">
                        <?php foreach ($apps as $app): ?>
                            <div class="version-app-card">
                                <div class="version-app-header">
                                    <div class="version-app-info">
                                        <h3><?php echo htmlspecialchars($app['title']); ?></h3>
                                        <span class="version-app-count"><?php echo count($app['versions'] ?? []); ?> versions</span>
                                    </div>
                                    <div class="version-app-status">
                                        <?php if (!empty($app['versions'])): ?>
                                            <span class="version-status-badge version-active">Active</span>
                                        <?php else: ?>
                                            <span class="version-status-badge version-inactive">No Versions</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if (!empty($app['versions'])): ?>
                                    <div class="versions-list">
                                        <?php foreach ($app['versions'] as $index => $version): ?>
                                            <?php 
                                            $status_class = 'version-current';
                                            $status_text = 'Current';
                                            $status_color = '#007AFF';
                                            if (!$version['is_current']) {
                                                $status_class = 'version-draft';
                                                $status_text = 'Previous';
                                                $status_color = '#8E8E93';
                                            }
                                            ?>
                                            <div class="version-card <?php echo $status_class; ?>">
                                                <div class="version-card-header">
                                                    <div class="version-card-icon">
                                                        <i class="fas fa-code-branch"></i>
                                                    </div>
                                                    <div class="version-card-info">
                                                        <div class="version-card-title">
                                                            <span class="version-number">v<?php echo htmlspecialchars($version['version']); ?></span>
                                                            <?php if ($version['is_current']): ?>
                                                                <span class="version-current-badge">Current</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="version-card-meta">
                                                            <span class="version-date">
                                                                <i class="fas fa-calendar"></i>
                                                                <?php echo date('M j, Y', strtotime($version['created_at'])); ?>
                                                            </span>
                                                            <span class="version-status" style="color: <?php echo $status_color; ?>;">
                                                                <i class="fas fa-circle"></i>
                                                                <?php echo $status_text; ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="version-card-actions">
                                                        <button class="version-action-btn" onclick="downloadVersion(<?php echo $version['id']; ?>)" title="Download">
                                                            <i class="fas fa-download"></i>
                                                        </button>
                                                        <?php if (!$version['is_current']): ?>
                                                            <button class="version-action-btn" onclick="setCurrentVersion(<?php echo $version['id']; ?>)" title="Set as Current">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                
                                                <div class="version-card-content">
                                                    <div class="version-changelog">
                                                        <div class="changelog-header">
                                                            <i class="fas fa-file-alt"></i>
                                                            <span>Release Notes</span>
                                                        </div>
                                                        <div class="changelog-content">
                                                            <?php 
                                                            $changelog = nl2br(htmlspecialchars($version['changelog']));
                                                            $lines = explode('<br />', $changelog);
                                                            $display_lines = array_slice($lines, 0, 3);
                                                            echo implode('<br />', $display_lines);
                                                            if (count($lines) > 3) {
                                                                echo '<span class="changelog-more">... (' . (count($lines) - 3) . ' more lines)</span>';
                                                            }
                                                            ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <?php if (!empty($version['files'])): ?>
                                                        <div class="version-files">
                                                            <div class="files-header">
                                                                <i class="fas fa-box"></i>
                                                                <span>Assets (<?php echo count($version['files']); ?>)</span>
                                                            </div>
                                                            <div class="files-list">
                                                                <?php foreach (array_slice($version['files'], 0, 3) as $file): ?>
                                                                    <div class="file-item">
                                                                        <div class="file-icon">
                                                                            <i class="fas <?php echo getFileIconClass($file['file_type']); ?>"></i>
                                                                        </div>
                                                                        <div class="file-info">
                                                                            <span class="file-name"><?php echo htmlspecialchars($file['original_name']); ?></span>
                                                                            <span class="file-size"><?php echo formatFileSizeDisplay($file['file_size']); ?></span>
                                                                        </div>
                                                                        <button class="file-download" onclick="downloadVersionFile(<?php echo $file['id']; ?>)">
                                                                            <i class="fas fa-download"></i>
                                                                        </button>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                                <?php if (count($version['files']) > 3): ?>
                                                                    <div class="files-more">
                                                                        +<?php echo count($version['files']) - 3; ?> more files
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="version-empty-state">
                                        <div class="version-empty-icon">
                                            <i class="fas fa-code-branch"></i>
                                        </div>
                                        <h4>No Versions Yet</h4>
                                        <p>Create your first version to start tracking releases</p>
                                        <button class="btn btn-primary" onclick="scrollToCreateForm()">
                                            <i class="fas fa-plus"></i> Create First Version
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <style>
        /* CSS Variables - Premium Color Palette */
        :root {
            --bg-main: #F7F7F8;
            --bg-card: #FFFFFF;
            --text-primary: #1C1C1E;
            --text-secondary: #6E6E73;
            --accent: #007AFF;
            --accent-hover: #0056CC;
            --shadow-subtle: rgba(0, 0, 0, 0.04);
            --shadow-medium: rgba(0, 0, 0, 0.08);
            --border-light: #E5E5EA;
            --border-lighter: #F2F2F7;
            --status-active: #E8F0FE;
            --status-active-text: #1E40AF;
            --radius-medium: 14px;
            --radius-large: 18px;
            --radius-small: 8px;
        }

        /* Base Layout */
        .dashboard-container {
            background: var(--bg-main) !important;
            min-height: 100vh;
            display: flex;
        }

        .sidebar {
            background: var(--bg-card) !important;
            border-right: 1px solid var(--border-light) !important;
            width: 280px;
            padding: 24px;
            box-shadow: 4px 0 20px var(--shadow-subtle);
        }

        /* Version Control Premium Design */
        .version-create-section {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-large);
            padding: 0;
            margin-bottom: 32px;
            box-shadow: 0 2px 12px var(--shadow-subtle);
            overflow: hidden;
        }

        .version-create-header {
            background: linear-gradient(135deg, var(--accent), var(--accent-hover));
            color: white;
            padding: 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 24px;
        }

        .version-create-title h2 {
            font-size: 28px;
            font-weight: 700;
            margin: 0 0 8px 0;
        }

        .version-create-title p {
            font-size: 16px;
            opacity: 0.9;
            margin: 0;
        }

        .version-steps {
            display: flex;
            gap: 8px;
        }

        .step {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--radius-large);
            font-size: 14px;
            font-weight: 500;
        }

        .step.active {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.4);
        }

        .step-number {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
        }

        .version-create-form {
            padding: 32px;
        }

        .version-step-section {
            margin-bottom: 32px;
        }

        .step-section-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 16px;
        }

        .step-section-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-medium);
            background: var(--accent);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .step-section-content h3 {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0 0 4px 0;
        }

        .step-section-content p {
            font-size: 14px;
            color: var(--text-secondary);
            margin: 0;
        }

        .version-step-card {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-large);
            padding: 24px;
            margin-bottom: 16px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border-light);
            border-radius: var(--radius-medium);
            background: var(--bg-card);
            color: var(--text-primary);
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .form-group small {
            display: block;
            margin-top: 4px;
            font-size: 12px;
            color: var(--text-secondary);
        }

        .file-upload-area {
            border: 2px dashed var(--border-light);
            border-radius: var(--radius-medium);
            padding: 40px 20px;
            text-align: center;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .file-upload-area:hover {
            border-color: var(--accent);
            background: var(--border-lighter);
        }

        .upload-content i {
            font-size: 48px;
            color: var(--text-secondary);
            margin-bottom: 16px;
        }

        .upload-content p {
            font-size: 16px;
            color: var(--text-primary);
            margin: 0 0 8px 0;
        }

        .upload-hint {
            font-size: 14px;
            color: var(--text-secondary);
            margin: 0;
        }

        .version-create-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            padding-top: 24px;
            border-top: 1px solid var(--border-light);
            margin-top: 32px;
        }

        /* Version History */
        .version-history-section {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-large);
            padding: 32px;
            margin-bottom: 32px;
            box-shadow: 0 2px 12px var(--shadow-subtle);
        }

        .version-history-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 24px;
            margin-bottom: 32px;
        }

        .version-history-title h2 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0 0 8px 0;
        }

        .version-history-title p {
            font-size: 16px;
            color: var(--text-secondary);
            margin: 0;
        }

        .version-history-stats {
            display: flex;
            gap: 16px;
        }

        .version-stat {
            text-align: center;
            padding: 16px 24px;
            background: var(--border-lighter);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-medium);
        }

        .version-stat-number {
            display: block;
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1;
        }

        .version-stat-label {
            font-size: 12px;
            color: var(--text-secondary);
            font-weight: 500;
            margin-top: 4px;
        }

        .version-history-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 24px;
        }

        .version-app-card {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-large);
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 12px var(--shadow-subtle);
            transition: all 0.2s ease;
        }

        .version-app-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px var(--shadow-medium);
        }

        .version-app-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 16px;
        }

        .version-app-info {
            flex: 1;
        }

        .version-app-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0 0 4px 0;
        }

        .version-app-count {
            font-size: 14px;
            color: var(--text-secondary);
            margin: 0;
        }

        .version-app-status {
            padding: 6px 12px;
            border-radius: var(--radius-small);
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }

        .version-status-badge.version-active {
            background: var(--status-active);
            color: var(--status-active-text);
            border: 1px solid rgba(30, 64, 175, 0.3);
        }

        .version-status-badge.version-inactive {
            background: var(--border-lighter);
            color: var(--text-secondary);
            border: 1px solid var(--border-light);
        }

        .versions-list {
            display: grid;
            gap: 16px;
        }

        .version-card {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-large);
            padding: 24px;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }

        .version-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px var(--shadow-medium);
        }

        .version-card.current {
            border-left: 4px solid var(--accent);
        }

        .version-card.draft {
            border-left: 4px solid var(--text-secondary);
        }

        .version-card.archived {
            border-left: 4px solid #FF3B30;
        }

        .version-card-header {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 16px;
        }

        .version-card-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-medium);
            background: var(--accent);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .version-card-info {
            flex: 1;
        }

        .version-card-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0 0 4px 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .version-number {
            font-family: 'SF Mono', 'Monaco', 'Inconsolata', monospace;
            font-weight: 600;
            color: var(--accent);
        }

        .version-current-badge {
            padding: 4px 8px;
            background: var(--accent);
            color: white;
            border-radius: var(--radius-small);
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .version-card-meta {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-top: 8px;
        }

        .version-date {
            font-size: 13px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .version-status {
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
            padding: 4px 8px;
            border-radius: var(--radius-small);
        }

        .version-status.current {
            background: var(--status-active);
            color: var(--status-active-text);
            border: 1px solid rgba(30, 64, 175, 0.3);
        }

        .version-status.previous {
            background: var(--border-lighter);
            color: var(--text-secondary);
            border: 1px solid var(--border-light);
        }

        .version-card-controls {
            display: flex;
            gap: 8px;
            align-items: flex-start;
        }

        .version-card-btn {
            width: 32px;
            height: 32px;
            border: 1px solid var(--border-light);
            background: var(--bg-card);
            border-radius: var(--radius-small);
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .version-card-btn:hover {
            background: var(--border-lighter);
            color: var(--text-primary);
        }

        .version-card-content {
            margin-top: 16px;
        }

        .version-changelog {
            margin-bottom: 16px;
        }

        .changelog-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .changelog-content {
            font-size: 14px;
            color: var(--text-primary);
            line-height: 1.5;
        }

        .changelog-more {
            color: var(--accent);
            font-size: 12px;
            font-weight: 500;
        }

        .version-files {
            margin-top: 16px;
        }

        .files-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .files-list {
            display: grid;
            gap: 12px;
        }

        .file-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: var(--border-lighter);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-medium);
        }

        .file-icon {
            width: 32px;
            height: 32px;
            border-radius: var(--radius-small);
            background: var(--accent);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .file-info {
            flex: 1;
        }

        .file-name {
            font-size: 13px;
            color: var(--text-primary);
            font-weight: 500;
            display: block;
        }

        .file-size {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .file-download {
            width: 28px;
            height: 28px;
            border: 1px solid var(--border-light);
            background: var(--bg-card);
            border-radius: var(--radius-small);
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }

        .file-download:hover {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
        }

        .files-more {
            text-align: center;
            padding: 8px;
            font-size: 12px;
            color: var(--text-secondary);
            font-style: italic;
        }

        .version-card-actions {
            display: flex;
            gap: 12px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--border-light);
        }

        .version-empty-state {
            text-align: center;
            padding: 80px 20px;
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-large);
            box-shadow: 0 2px 12px var(--shadow-subtle);
        }

        .version-empty-icon {
            font-size: 48px;
            color: var(--text-secondary);
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .version-empty h3 {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0 0 8px 0;
        }

        .version-empty p {
            font-size: 14px;
            color: var(--text-secondary);
            margin: 0 0 20px 0;
            line-height: 1.5;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .version-create-header {
                flex-direction: column;
                gap: 16px;
                text-align: center;
            }

            .version-steps {
                justify-content: center;
            }

            .version-create-form {
                padding: 20px;
            }

            .version-history-header {
                flex-direction: column;
                gap: 16px;
                align-items: stretch;
            }

            .version-history-stats {
                justify-content: center;
            }

            .version-app-header {
                flex-direction: column;
                gap: 12px;
            }

            .version-card-header {
                flex-direction: column;
                gap: 12px;
            }

            .version-card-controls {
                align-self: flex-end;
            }
        }

        .sidebar .logo h1 {
            color: var(--text-primary) !important;
            font-size: 18px;
            font-weight: 600;
            margin: 0 0 4px 0;
        }

        .sidebar .logo p {
            color: var(--text-secondary) !important;
            font-size: 12px;
            margin: 0;
        }

        .sidebar .nav-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 14px 20px;
            border-radius: var(--radius-medium);
            cursor: pointer;
            transition: all 0.2s ease;
            margin-bottom: 4px;
            color: #8E8E93 !important;
            font-weight: 500;
            font-size: 14px;
        }

        .sidebar .nav-item:hover {
            background: var(--border-lighter) !important;
            color: var(--text-primary) !important;
        }

        .sidebar .nav-item.active {
            background: rgba(0, 122, 255, 0.08) !important;
            color: var(--accent) !important;
            font-weight: 600;
        }

        .main-content {
            flex: 1;
            background: var(--bg-main) !important;
            overflow-y: auto;
        }

        .main-header {
            background: var(--bg-card) !important;
            border-bottom: 1px solid var(--border-light) !important;
            padding: 20px 32px;
            box-shadow: 0 2px 12px var(--shadow-subtle);
        }

        .main-header h1 {
            color: var(--text-primary) !important;
            font-size: 24px;
            font-weight: 600;
            margin: 0 0 4px 0;
        }

        .main-header p {
            color: var(--text-secondary) !important;
            font-size: 14px;
            margin: 0;
        }

        .content-area {
            padding: 32px;
        }

        /* Cards */
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-large);
            padding: 32px;
            margin-bottom: 32px;
            box-shadow: 0 2px 12px var(--shadow-subtle);
        }

        .card h2 {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-primary) !important;
            margin: 0 0 24px 0;
        }

        /* App Versions */
        .app-versions {
            margin-bottom: 32px;
            padding: 24px;
            border: 1px solid var(--border-light);
            border-radius: var(--radius-large);
            background: var(--bg-card);
            box-shadow: 0 2px 12px var(--shadow-subtle);
        }

        .app-versions h3 {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary) !important;
            margin: 0 0 20px 0;
        }

        .versions-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .version-item {
            padding: 24px;
            border-radius: var(--radius-large);
            background: var(--border-lighter);
            border: 1px solid var(--border-light);
            transition: all 0.2s ease;
        }

        .version-item.current {
            border-left: 4px solid var(--accent);
            background: rgba(0, 122, 255, 0.02);
        }

        .version-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px var(--shadow-medium);
        }

        .version-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .version-number {
            font-weight: 700;
            color: var(--accent);
            font-size: 16px;
        }

        .current-badge {
            background: var(--accent);
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .version-date {
            color: var(--text-secondary);
            font-size: 13px;
            margin-left: auto;
        }

        .changelog {
            color: var(--text-secondary);
            margin-bottom: 16px;
            line-height: 1.5;
            font-size: 14px;
        }

        .version-actions {
            display: flex;
            gap: 12px;
        }

        .no-versions {
            color: var(--text-secondary);
            font-style: italic;
            text-align: center;
            padding: 40px;
            font-size: 14px;
        }

        /* Forms */
        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 500;
            font-size: 14px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border-light);
            border-radius: var(--radius-small);
            background: var(--bg-card);
            color: var(--text-primary);
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.1);
        }

        /* Messages */
        .success-message {
            background: rgba(52, 199, 89, 0.1);
            border: 1px solid rgba(52, 199, 89, 0.3);
            border-radius: var(--radius-medium);
            padding: 16px;
            margin-bottom: 24px;
            color: #34C759;
            font-size: 14px;
            font-weight: 500;
        }

        .error-message {
            background: rgba(255, 59, 48, 0.1);
            border: 1px solid rgba(255, 59, 48, 0.3);
            border-radius: var(--radius-medium);
            padding: 16px;
            margin-bottom: 24px;
            color: #FF3B30;
            font-size: 14px;
            font-weight: 500;
        }

        /* File Upload */
        .file-upload-area {
            border: 2px dashed var(--border-light);
            border-radius: var(--radius-large);
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            background: var(--border-lighter);
        }

        .file-upload-area:hover {
            border-color: var(--accent);
            background: rgba(0, 122, 255, 0.02);
        }

        .file-upload-area.dragover {
            border-color: var(--accent);
            background: rgba(0, 122, 255, 0.05);
        }

        .upload-content i {
            font-size: 48px;
            color: var(--text-secondary);
            margin-bottom: 16px;
        }

        .upload-content p {
            margin: 4px 0;
            color: var(--text-primary);
            font-size: 16px;
            font-weight: 500;
        }

        .upload-hint {
            font-size: 13px !important;
            color: var(--text-secondary) !important;
            font-weight: 400 !important;
        }

        .file-list {
            margin-top: 16px;
            display: none;
        }

        .file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            background: var(--border-lighter);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-medium);
            margin-bottom: 8px;
            transition: all 0.2s ease;
        }

        .file-item:hover {
            background: var(--bg-card);
            box-shadow: 0 2px 8px var(--shadow-subtle);
        }

        .file-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .file-icon {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--accent);
            color: white;
            border-radius: var(--radius-small);
            font-size: 14px;
        }

        .file-details {
            display: flex;
            flex-direction: column;
        }

        .file-name {
            font-weight: 500;
            color: var(--text-primary);
            font-size: 14px;
        }

        .file-size {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .remove-file {
            background: rgba(255, 59, 48, 0.1);
            border: 1px solid rgba(255, 59, 48, 0.3);
            color: #FF3B30;
            border: none;
            padding: 6px 12px;
            border-radius: var(--radius-small);
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .remove-file:hover {
            background: rgba(255, 59, 48, 0.2);
            transform: translateY(-1px);
        }

        /* Version Files */
        .version-files {
            margin: 20px 0;
            padding: 20px;
            background: var(--border-lighter);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-large);
        }

        .version-files h4 {
            margin-bottom: 16px;
            color: var(--text-primary);
            font-size: 14px;
            font-weight: 600;
        }

        .files-grid {
            display: grid;
            gap: 8px;
        }

        .file-item-small {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-medium);
            transition: all 0.2s ease;
        }

        .file-item-small:hover {
            background: var(--border-lighter);
            transform: translateY(-1px);
        }

        .file-icon-small {
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--accent);
            color: white;
            border-radius: var(--radius-small);
            font-size: 12px;
        }

        .file-info-small {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .file-name-small {
            font-size: 13px;
            color: var(--text-primary);
            font-weight: 500;
        }

        .file-size-small {
            font-size: 11px;
            color: var(--text-secondary);
        }

        .btn-download-file {
            background: rgba(52, 199, 89, 0.1);
            border: 1px solid rgba(52, 199, 89, 0.3);
            color: #34C759;
            padding: 6px 10px;
            border-radius: var(--radius-small);
            cursor: pointer;
            font-size: 11px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-download-file:hover {
            background: rgba(52, 199, 89, 0.2);
            transform: translateY(-1px);
        }

        /* Buttons */
        .btn {
            padding: 12px 24px;
            border-radius: var(--radius-medium);
            font-size: 14px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--accent);
            color: white;
        }

        .btn-primary:hover {
            background: var(--accent-hover);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--border-lighter);
            color: var(--text-primary);
            border: 1px solid var(--border-light);
        }

        .btn-secondary:hover {
            background: var(--border-light);
        }

        .btn-small {
            padding: 8px 16px;
            font-size: 13px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                height: auto;
                flex-direction: row;
                padding: 16px;
                border-right: none;
                border-bottom: 1px solid var(--border-light);
            }

            .sidebar nav {
                display: flex;
                gap: 8px;
                overflow-x: auto;
            }

            .sidebar .nav-item {
                margin: 0;
                white-space: nowrap;
            }

            .content-area {
                padding: 20px;
            }

            .card {
                padding: 24px;
            }

            .app-versions {
                padding: 20px;
            }

            .version-item {
                padding: 20px;
            }
        }
    </style>
    
    <script>
        function downloadVersion(versionId) {
            window.location.href = `download_version.php?id=${versionId}`;
        }
        
        function setCurrentVersion(versionId) {
            if (confirm('Set this version as the current version?')) {
                fetch('version-control.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=set_current&version_id=${versionId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.error);
                    }
                });
            }
        }
        
        // File upload functionality
        const fileUploadArea = document.getElementById('fileUploadArea');
        const fileInput = document.getElementById('additionalFiles');
        const fileList = document.getElementById('fileList');
        let uploadedFiles = [];
        
        // Click to upload
        fileUploadArea.addEventListener('click', () => {
            fileInput.click();
        });
        
        // Drag and drop
        fileUploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            fileUploadArea.classList.add('dragover');
        });
        
        fileUploadArea.addEventListener('dragleave', () => {
            fileUploadArea.classList.remove('dragover');
        });
        
        fileUploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            fileUploadArea.classList.remove('dragover');
            handleFiles(e.dataTransfer.files);
        });
        
        fileInput.addEventListener('change', (e) => {
            handleFiles(e.target.files);
        });
        
        function handleFiles(files) {
            for (let file of files) {
                if (!uploadedFiles.find(f => f.name === file.name)) {
                    uploadedFiles.push(file);
                    addFileToList(file);
                }
            }
        }
        
        function addFileToList(file) {
            fileList.style.display = 'block';
            
            const fileItem = document.createElement('div');
            fileItem.className = 'file-item';
            fileItem.dataset.fileName = file.name;
            
            const fileIcon = getFileIcon(file.type);
            const fileSize = formatFileSize(file.size);
            
            fileItem.innerHTML = `
                <div class="file-info">
                    <div class="file-icon">
                        <i class="fas ${fileIcon}"></i>
                    </div>
                    <div class="file-details">
                        <span class="file-name">${file.name}</span>
                        <span class="file-size">${fileSize}</span>
                    </div>
                </div>
                <button type="button" class="remove-file" onclick="removeFile('${file.name}')">
                    <i class="fas fa-times"></i> Remove
                </button>
            `;
            
            fileList.appendChild(fileItem);
        }
        
        function removeFile(fileName) {
            uploadedFiles = uploadedFiles.filter(f => f.name !== fileName);
            const fileItem = document.querySelector(`[data-file-name="${fileName}"]`);
            if (fileItem) {
                fileItem.remove();
            }
            
            if (uploadedFiles.length === 0) {
                fileList.style.display = 'none';
            }
        }
        
        function getFileIcon(fileType) {
            if (fileType.startsWith('image/')) return 'fa-image';
            if (fileType.includes('pdf')) return 'fa-file-pdf';
            if (fileType.includes('word') || fileType.includes('document')) return 'fa-file-word';
            if (fileType.includes('zip') || fileType.includes('rar')) return 'fa-file-archive';
            if (fileType.includes('text')) return 'fa-file-alt';
            return 'fa-file';
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        function downloadVersionFile(fileId) {
            window.location.href = `download_version_file.php?id=${fileId}`;
        }
    </script>
</body>
</html>
