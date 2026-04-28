<?php
require_once 'mainlogincore.php';

$auth = new AuthCore();

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

if (!$auth->isDeveloper()) {
    header('Location: index.php');
    exit();
}

$user = $auth->getCurrentUser();
$project_id = $_GET['project_id'] ?? null;

// Handle file save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'save_file') {
    $project_id = $_POST['project_id'] ?? null;
    $file_path = $_POST['file_path'] ?? null;
    $content = $_POST['content'] ?? '';
    
    if ($project_id && $file_path) {
        $conn = $auth->getConnection();
        
        // Verify project ownership first
        $stmt = $conn->prepare("SELECT id FROM projects WHERE id = ? AND user_id = ?");
        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
            exit();
        }
        $stmt->bind_param("ii", $project_id, $user['id']);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
            exit();
        }
        
        if ($stmt->get_result()->num_rows === 0) {
            echo json_encode(['success' => false, 'error' => 'Project not found']);
            exit();
        }
        
        // Check if file exists
        $stmt = $conn->prepare("SELECT id FROM project_files WHERE project_id = ? AND file_path = ?");
        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
            exit();
        }
        $stmt->bind_param("is", $project_id, $file_path);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
            exit();
        }
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing file
            $stmt = $conn->prepare("UPDATE project_files SET content = ?, file_size = ?, updated_at = NOW() WHERE project_id = ? AND file_path = ?");
            if (!$stmt) {
                echo json_encode(['success' => false, 'error' => 'Database error']);
                exit();
            }
            $file_size = strlen($content);
            $stmt->bind_param("siss", $content, $file_size, $project_id, $file_path);
        } else {
            // Create new file
            $file_type = pathinfo($file_path, PATHINFO_EXTENSION);
            $file_type_map = [
                'html' => 'html',
                'css' => 'css', 
                'js' => 'js',
                'json' => 'json',
                'md' => 'md'
            ];
            $file_type = $file_type_map[$file_type] ?? 'other';
            $file_name = basename($file_path);
            $file_size = strlen($content);
            
            $stmt = $conn->prepare("INSERT INTO project_files (project_id, file_path, file_name, file_type, content, file_size) VALUES (?, ?, ?, ?, ?, ?)");
            if (!$stmt) {
                echo json_encode(['success' => false, 'error' => 'Database error']);
                exit();
            }
            $stmt->bind_param("issssi", $project_id, $file_path, $file_name, $file_type, $content, $file_size);
        }
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to save file']);
        }
        exit();
    }
}

// Get all user projects for selection
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
$all_projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get current project
$current_project = null;
if ($project_id) {
    $stmt = $conn->prepare("
        SELECT p.*, u.username as developer_name 
        FROM projects p 
        JOIN users u ON p.user_id = u.id 
        WHERE p.id = ? AND p.user_id = ?
    ");
    $stmt->bind_param("ii", $project_id, $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_project = $result->fetch_assoc();
    
    // If project doesn't exist or doesn't belong to user, redirect to projects
    if (!$current_project) {
        header('Location: projects.php');
        exit();
    }
}

// Get project files
$project_files = [];
if ($current_project) {
    $stmt = $conn->prepare("SELECT * FROM project_files WHERE project_id = ? ORDER BY file_path");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $project_files = $result->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editor - Bloxer</title>
    <link rel="stylesheet" href="editor.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Monaco Editor -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs/loader.js"></script>
    
    </head>
<body>
    <div class="editor-container">
        <!-- Breadcrumbs -->
        <div class="back-button">
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="projects.php" class="btn btn-secondary" style="margin-left: 10px;">
                <i class="fas fa-folder"></i> Projects
            </a>
            <?php if ($current_project): ?>
                <span class="btn btn-secondary" style="margin-left: 10px; background: var(--accent); color: white; cursor: default;">
                    <i class="fas fa-code"></i> <?php echo htmlspecialchars($current_project['name']); ?>
                </span>
            <?php endif; ?>
        </div>
        
        <!-- User Menu -->
        <div class="user-menu">
            <a href="dashboard.php" class="user-button">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="projects.php" class="user-button">
                <i class="fas fa-folder"></i>
                <span>Projects</span>
            </a>
            <a href="publish.php" class="user-button">
                <i class="fas fa-rocket"></i>
                <span>Publish</span>
            </a>
            <a href="logout.php" class="user-button" style="margin-left: 10px; background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.3);">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
        
        <?php if ($current_project): ?>
            <!-- Sidebar -->
            <div class="sidebar">
                <div class="sidebar-header">
                    <div class="project-info">
                        <h3><?php echo htmlspecialchars($current_project['name']); ?></h3>
                        <p><?php echo htmlspecialchars($current_project['framework']); ?> • <?php echo htmlspecialchars($current_project['status']); ?></p>
                    </div>
                </div>
                
                <div class="file-explorer">
                    <h4 style="margin-bottom: 15px;">Files</h4>
                    <div class="drop-zone" id="drop-zone">
                        <i class="fas fa-cloud-upload-alt" style="font-size: 24px; margin-bottom: 10px;"></i>
                        <p>Drag & drop files here</p>
                        <small style="opacity: 0.7;">Supports .html, .css, .js, .json, .md</small>
                    </div>
                    <div class="file-tree" id="file-tree">
                        <?php 
                        // Group files by directory
                        $fileTree = [];
                        foreach ($project_files as $file) {
                            $pathParts = explode('/', $file['file_path']);
                            $current = &$fileTree;
                            
                            foreach ($pathParts as $i => $part) {
                                if ($i === count($pathParts) - 1) {
                                    // This is the file
                                    $current['_files'][] = $file;
                                } else {
                                    // This is a directory
                                    if (!isset($current[$part])) {
                                        $current[$part] = ['_files' => []];
                                    }
                                    $current = &$current[$part];
                                }
                            }
                        }
                        
                        function renderFileTree($tree, $level = 0) {
                            foreach ($tree as $key => $value) {
                                if ($key === '_files') {
                                    foreach ($value as $file) {
                                        echo '<div class="tree-item file-item" data-file="' . htmlspecialchars($file['file_path']) . '" data-type="' . htmlspecialchars($file['file_type']) . '" style="padding-left: ' . ($level * 20) . 'px;">';
                                        echo '<i class="fas fa-' . get_file_icon($file['file_type']) . '" style="margin-right: 8px;"></i>';
                                        echo '<span>' . htmlspecialchars($file['file_name']) . '</span>';
                                        echo '</div>';
                                    }
                                } else {
                                    echo '<div class="tree-item" onclick="toggleTree(this)" style="padding-left: ' . ($level * 20) . 'px; font-weight: 500;">';
                                    echo '<span class="tree-toggle">▶</span>';
                                    echo '<i class="fas fa-folder" style="margin-right: 8px;"></i>';
                                    echo '<span>' . htmlspecialchars($key) . '</span>';
                                    echo '</div>';
                                    echo '<div class="tree-children">';
                                    renderFileTree($value, $level + 1);
                                    echo '</div>';
                                }
                            }
                        }
                        
                        function get_file_icon($type) {
                            switch($type) {
                                case 'html': return 'code';
                                case 'css': return 'palette';
                                case 'js': return 'file-code';
                                case 'json': return 'brackets-curly';
                                case 'md': return 'file-alt';
                                default: return 'file';
                            }
                        }
                        
                        renderFileTree($fileTree);
                        ?>
                    </div>
                </div>
            </div>
            
            <!-- Main Area -->
            <div class="main-area">
                <div class="editor-tabs">
                    <div class="tab active">
                        <i class="fas fa-code"></i>
                        <span style="margin-left: 8px;">Code Editor</span>
                    </div>
                    
                    <div class="editor-actions">
                        <button class="btn btn-secondary" onclick="runCode()">
                            <i class="fas fa-play"></i> Run
                        </button>
                        <button class="btn btn-secondary" id="hot-reload-toggle" onclick="toggleHotReload()">
                            <i class="fas fa-fire"></i> Hot Reload
                        </button>
                        <button class="btn btn-primary" onclick="saveFile()">
                            <i class="fas fa-save"></i> Save
                        </button>
                    </div>
                </div>
                
                <div class="editor-workspace">
                    <div class="code-editor" id="monaco-container"></div>
                    
                    <div class="preview-pane">
                        <div class="preview-header">
                            <i class="fas fa-eye"></i> Live Preview
                        </div>
                        <div class="preview-content">
                            <iframe id="preview-frame"></iframe>
                        </div>
                    </div>
                </div>
                
                <div class="status-bar">
                    <div>
                        <span id="current-file">No file selected</span> • 
                        <span id="cursor-position">Line 1, Column 1</span>
                    </div>
                    <div>
                        <span id="save-status">Ready</span>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div style="display: flex; align-items: center; justify-content: center; height: 100vh; padding: 20px;">
                <div style="max-width: 800px; width: 100%; text-align: center;">
                    <i class="fas fa-folder-open" style="font-size: 64px; color: var(--text-secondary); margin-bottom: 20px;"></i>
                    <h2 style="color: var(--text-primary); margin-bottom: 10px;">Select a Project to Edit</h2>
                    <p style="color: var(--text-secondary); margin-bottom: 30px;">Choose one of your projects or create a new one</p>
                    
                    <?php if (!empty($all_projects)): ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px;">
                            <?php foreach ($all_projects as $project): ?>
                                <div style="background: var(--bg-secondary); border: 1px solid var(--glass-border); border-radius: 12px; padding: 20px; cursor: pointer; transition: all 0.3s ease;" 
                                     onmouseover="this.style.transform='translateY(-2px)'" 
                                     onmouseout="this.style.transform='translateY(0)'"
                                     onclick="window.location.href='editor.php?project_id=<?php echo $project['id']; ?>'">
                                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                                        <h3 style="color: var(--text-primary); margin: 0;"><?php echo htmlspecialchars($project['name']); ?></h3>
                                        <span style="padding: 4px 8px; background: var(--accent); color: white; border-radius: 4px; font-size: 0.8em;">
                                            <?php echo htmlspecialchars($project['framework']); ?>
                                        </span>
                                    </div>
                                    
                                    <?php if ($project['description']): ?>
                                        <p style="color: var(--text-secondary); margin-bottom: 15px; font-size: 0.9em; line-height: 1.4;">
                                            <?php echo htmlspecialchars(substr($project['description'], 0, 100)) . '...'; ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.85em; color: var(--text-secondary);">
                                        <span><i class="fas fa-file"></i> <?php echo $project['file_count']; ?> files</span>
                                        <span><i class="fas fa-clock"></i> <?php echo date('M j', strtotime($project['updated_at'])); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div style="display: flex; gap: 15px; justify-content: center;">
                        <a href="projects.php" class="btn btn-secondary">
                            <i class="fas fa-folder"></i> Manage Projects
                        </a>
                        <a href="projects.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Create New Project
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        let editor;
        let currentFile = null;
        let monacoEditor = null;
        
        // Load Monaco Editor
        require.config({ paths: { vs: 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs' } });
        require(['vs/editor/editor.main'], function () {
            if (typeof monaco !== 'undefined') {
                initializeMonacoEditor();
            } else {
                console.error('Monaco Editor failed to load');
                document.getElementById('save-status').textContent = 'Error loading editor';
            }
        });
        
        function initializeMonacoEditor() {
            const container = document.getElementById('monaco-container');
            
            if (!container) {
                console.error('Monaco container not found');
                return;
            }
            
            try {
                monacoEditor = monaco.editor.create(container, {
                value: '',
                language: 'html',
                theme: 'vs-dark',
                automaticLayout: true,
                fontSize: 14,
                lineNumbers: 'on',
                minimap: { enabled: true },
                scrollBeyondLastLine: false,
                wordWrap: 'on',
                bracketPairColorization: { enabled: true },
                suggest: {
                    showKeywords: true,
                    showSnippets: true
                },
                quickSuggestions: {
                    other: true,
                    comments: true,
                    strings: true
                }
            });
                
                // Update cursor position
                monacoEditor.onDidChangeCursorPosition(function(e) {
                    const position = e.position;
                    const cursorElement = document.getElementById('cursor-position');
                    if (cursorElement) {
                        cursorElement.textContent = 
                            `Line ${position.lineNumber}, Column ${position.column}`;
                    }
                });
                
                // Handle file selection
                document.querySelectorAll('.file-item').forEach(item => {
                    item.addEventListener('click', function() {
                        selectFile(this);
                    });
                });
                
                // Auto-save
                let saveTimeout;
                monacoEditor.onDidChangeModelContent(function() {
                    clearTimeout(saveTimeout);
                    const saveStatusElement = document.getElementById('save-status');
                    if (saveStatusElement) {
                        saveStatusElement.textContent = 'Modified';
                    }
                    saveTimeout = setTimeout(function() {
                        saveFile();
                    }, 2000);
                });
                
                // Initialize drag and drop
                initializeDragAndDrop();
                
                console.log('Monaco Editor initialized successfully');
                
                // Check if there are any files to load
                const fileItems = document.querySelectorAll('.file-item');
                if (fileItems.length > 0) {
                    // Auto-select first file
                    selectFile(fileItems[0]);
                } else {
                    console.log('No files found in project');
                    const saveStatusElement = document.getElementById('save-status');
                    if (saveStatusElement) {
                        saveStatusElement.textContent = 'No files in project';
                    }
                }
            } catch (error) {
                console.error('Error creating Monaco Editor:', error);
                const saveStatusElement = document.getElementById('save-status');
                if (saveStatusElement) {
                    saveStatusElement.textContent = 'Error creating editor';
                }
            }
        }
        
        function initializeDragAndDrop() {
            const dropZone = document.getElementById('drop-zone');
            const container = document.getElementById('monaco-container');
            
            // Prevent default drag behaviors
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, preventDefaults, false);
                container.addEventListener(eventName, preventDefaults, false);
                document.body.addEventListener(eventName, preventDefaults, false);
            });
            
            // Highlight drop zone when item is dragged over it
            ['dragenter', 'dragover'].forEach(eventName => {
                dropZone.addEventListener(eventName, highlightDropZone, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, unhighlightDropZone, false);
            });
            
            // Handle dropped files
            dropZone.addEventListener('drop', handleDrop, false);
            container.addEventListener('drop', handleDrop, false);
        }
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        function highlightDropZone(e) {
            document.getElementById('drop-zone').classList.add('active');
        }
        
        function unhighlightDropZone(e) {
            document.getElementById('drop-zone').classList.remove('active');
        }
        
        function highlight(e) {
            document.getElementById('monaco-container').classList.add('active');
        }
        
        function unhighlight(e) {
            document.getElementById('monaco-container').classList.remove('active');
        }
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            handleFiles(files);
        }
        
        function handleFiles(files) {
            ([...files]).forEach(uploadFile);
        }
        
        function uploadFile(file) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const content = e.target.result;
                const fileName = file.name;
                const fileType = fileName.split('.').pop().toLowerCase();
                
                // Create file in project
                createFileInProject(fileName, content, fileType);
            };
            
            reader.readAsText(file);
        }
        
        function createFileInProject(fileName, content, fileType) {
            const filePath = fileName; // Simple implementation - could be enhanced with folder structure
            
            fetch('editor.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'save_file',
                    project_id: <?php echo $project_id; ?>,
                    file_path: filePath,
                    content: content
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Refresh file explorer
                    location.reload();
                }
            });
        }
        
        // File tree functionality
        function toggleTree(element) {
            const children = element.nextElementSibling;
            const toggle = element.querySelector('.tree-toggle');
            
            if (children && children.classList.contains('tree-children')) {
                children.classList.toggle('expanded');
                toggle.textContent = children.classList.contains('expanded') ? '▼' : '▶';
            }
        }
        
        function selectFile(element) {
            if (!monacoEditor) {
                console.error('Monaco Editor not initialized');
                return;
            }
            
            // Update active state
            document.querySelectorAll('.file-item').forEach(item => {
                item.classList.remove('active');
            });
            element.classList.add('active');
            
            // Load file content
            const filePath = element.dataset.file;
            const fileType = element.dataset.type;
            
            currentFile = {
                path: filePath,
                type: fileType
            };
            
            const currentFileElement = document.getElementById('current-file');
            if (currentFileElement) {
                currentFileElement.textContent = filePath;
            }
            
            // Set editor language based on file type
            const languageMap = {
                'html': 'html',
                'css': 'css',
                'js': 'javascript',
                'json': 'json',
                'md': 'markdown'
            };
            
            const language = languageMap[fileType] || 'plaintext';
            try {
                monaco.editor.setModelLanguage(monacoEditor.getModel(), language);
            } catch (error) {
                console.error('Error setting language:', error);
            }
            
            // Load file content via AJAX
            fetch(`get_file.php?project_id=<?php echo $project_id; ?>&file=${encodeURIComponent(filePath)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        monacoEditor.setValue(data.content);
                        const saveStatusElement = document.getElementById('save-status');
                        if (saveStatusElement) {
                            saveStatusElement.textContent = 'Loaded';
                        }
                    } else {
                        console.error('Failed to load file:', data);
                        const saveStatusElement = document.getElementById('save-status');
                        if (saveStatusElement) {
                            saveStatusElement.textContent = 'Error loading file';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error loading file:', error);
                    const saveStatusElement = document.getElementById('save-status');
                    if (saveStatusElement) {
                        saveStatusElement.textContent = 'Error loading file';
                    }
                });
        }
        
        function saveFile() {
            if (!currentFile || !monacoEditor) {
                console.error('No file selected or editor not initialized');
                return;
            }
            
            const content = monacoEditor.getValue();
            const saveStatusElement = document.getElementById('save-status');
            if (saveStatusElement) {
                saveStatusElement.textContent = 'Saving...';
            }
            
            fetch('editor.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'save_file',
                    project_id: <?php echo $project_id; ?>,
                    file_path: currentFile.path,
                    content: content
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (saveStatusElement) {
                        saveStatusElement.textContent = 'Saved';
                    }
                    updatePreview();
                } else {
                    console.error('Failed to save file:', data);
                    if (saveStatusElement) {
                        saveStatusElement.textContent = 'Error saving';
                    }
                }
            })
            .catch(error => {
                console.error('Error saving file:', error);
                if (saveStatusElement) {
                    saveStatusElement.textContent = 'Error saving';
                }
            });
        }
        
        function runCode() {
            updatePreview();
        }
        
        function updatePreview() {
            if (!monacoEditor) {
                console.error('Monaco Editor not initialized');
                return;
            }
            
            const content = monacoEditor.getValue();
            const iframe = document.getElementById('preview-frame');
            
            if (currentFile && currentFile.type === 'html') {
                // Enhanced hot reload with script injection
                const enhancedContent = content.replace(
                    '</head>',
                    `
                    <script>
                        // Hot reload functionality
                        let lastModified = Date.now();
                        
                        // Listen for changes from parent
                        window.addEventListener('message', function(event) {
                            if (event.data.type === 'reload') {
                                location.reload();
                            }
                        });
                        
                        // Notify parent when loaded
                        window.addEventListener('load', function() {
                            window.parent.postMessage({type: 'iframe-loaded'}, '*');
                        });
                    </script>
                    </head>`
                );
                
                iframe.srcdoc = enhancedContent;
                
                // Enable hot reload for HTML files
                enableHotReload();
            } else {
                // For non-HTML files, show a message
                iframe.srcdoc = `
                    <html>
                        <body style="font-family: Arial, sans-serif; padding: 20px; text-align: center;">
                            <h2>Preview not available</h2>
                            <p>Live preview is only available for HTML files.</p>
                        </body>
                    </html>
                `;
            }
        }
        
        let hotReloadEnabled = false;
        let hotReloadInterval = null;
        
        function enableHotReload() {
            if (hotReloadEnabled) return;
            
            hotReloadEnabled = true;
            
            // Check for changes every 2 seconds
            hotReloadInterval = setInterval(function() {
                const iframe = document.getElementById('preview-frame');
                if (iframe.contentWindow) {
                    iframe.contentWindow.postMessage({type: 'reload'}, '*');
                }
            }, 2000);
        }
        
        function disableHotReload() {
            if (hotReloadInterval) {
                clearInterval(hotReloadInterval);
                hotReloadInterval = null;
            }
            hotReloadEnabled = false;
            document.getElementById('hot-reload-toggle').style.background = 'var(--bg-tertiary)';
        }
        
        function toggleHotReload() {
            if (hotReloadEnabled) {
                disableHotReload();
            } else {
                enableHotReload();
                document.getElementById('hot-reload-toggle').style.background = 'var(--accent)';
            }
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case 's':
                        e.preventDefault();
                        saveFile();
                        break;
                    case 'Enter':
                        e.preventDefault();
                        runCode();
                        break;
                }
            }
        });
    </script>
</body>
</html>
