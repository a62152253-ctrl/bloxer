<?php
// Live Preview page content
// Get project files for preview
$conn = $auth->getConnection();
$stmt = $conn->prepare("SELECT * FROM project_files WHERE project_id = ? ORDER BY file_path");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$project_files = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Find main HTML file
$main_file = null;
foreach ($project_files as $file) {
    if ($file['file_name'] === 'index.html' || pathinfo($file['file_name'], PATHINFO_EXTENSION) === 'html') {
        $main_file = $file;
        break;
    }
}

if (!$main_file && !empty($project_files)) {
    $main_file = $project_files[0];
}
?>

<div class="preview-controls">
    <button class="btn btn-small" onclick="refreshPreview()">
        <i class="fas fa-redo"></i> Refresh
    </button>
    <button class="btn btn-small" onclick="toggleDeviceMode()">
        <i class="fas fa-mobile-alt"></i> Device Mode
    </button>
    <button class="btn btn-small" onclick="toggleConsole()">
        <i class="fas fa-terminal"></i> Console
    </button>
    <button class="btn btn-small" onclick="openInNewTab()">
        <i class="fas fa-external-link-alt"></i> Open in New Tab
    </button>
    <div style="margin-left: auto; display: flex; align-items: center; gap: 10px;">
        <label style="color: var(--text-secondary); font-size: 0.9rem;">Auto-refresh:</label>
        <input type="checkbox" id="auto-refresh" checked onchange="toggleAutoRefresh()" style="cursor: pointer;">
        <span id="refresh-status" style="color: var(--text-muted); font-size: 0.8rem;">ON</span>
    </div>
</div>

<div class="preview-container" id="preview-container">
    <div class="preview-panel">
        <div style="background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); border-radius: 12px; padding: 15px; margin-bottom: 15px;">
            <h4 style="color: var(--text-primary); margin-bottom: 10px;">
                <i class="fas fa-file-code" style="margin-right: 8px;"></i>
                Project: <?php echo htmlspecialchars($current_project['name']); ?>
            </h4>
            <div style="display: flex; gap: 15px; font-size: 0.9rem; color: var(--text-secondary);">
                <span><i class="fas fa-files"></i> <?php echo count($project_files); ?> files</span>
                <span><i class="fas fa-code"></i> <?php echo ucfirst($current_project['framework']); ?></span>
                <span><i class="fas fa-clock"></i> Updated <?php echo date('M j, H:i', strtotime($current_project['updated_at'])); ?></span>
            </div>
        </div>
        
        <div style="background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); border-radius: 12px; padding: 15px;">
            <h4 style="color: var(--text-primary); margin-bottom: 15px;">
                <i class="fas fa-list" style="margin-right: 8px;"></i>
                Project Files
            </h4>
            <div style="max-height: 200px; overflow-y: auto;">
                <?php foreach ($project_files as $file): ?>
                    <div class="file-item" style="display: flex; align-items: center; gap: 10px; padding: 8px 12px; margin-bottom: 5px; background: rgba(255,255,255,0.05); border-radius: 8px; cursor: pointer; transition: all 0.2s ease;" onclick="previewFile('<?php echo htmlspecialchars($file['file_path']); ?>')">
                        <i class="fas fa-file-code" style="color: var(--accent); font-size: 0.8rem;"></i>
                        <span style="color: var(--text-secondary); font-size: 0.9rem;"><?php echo htmlspecialchars($file['file_path']); ?></span>
                        <?php if ($file['file_name'] === $main_file['file_name']): ?>
                            <span style="margin-left: auto; padding: 2px 8px; background: var(--accent); color: white; border-radius: 12px; font-size: 0.7rem;">MAIN</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <div class="preview-panel">
        <div style="background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); border-radius: 12px 12px 0 0; padding: 10px 15px; display: flex; justify-content: space-between; align-items: center;">
            <span style="color: var(--text-primary); font-weight: 500;">
                <i class="fas fa-eye" style="margin-right: 8px;"></i>
                Live Preview
            </span>
            <div style="display: flex; gap: 10px;">
                <button onclick="zoomIn()" style="background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 5px;">
                    <i class="fas fa-search-plus"></i>
                </button>
                <button onclick="zoomOut()" style="background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 5px;">
                    <i class="fas fa-search-minus"></i>
                </button>
                <button onclick="resetZoom()" style="background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 5px;">
                    <i class="fas fa-compress"></i>
                </button>
            </div>
        </div>
        <iframe 
            class="preview-iframe" 
            id="preview-frame"
            src="run_app.php?project_id=<?php echo $project_id; ?>&preview=true"
            sandbox="allow-scripts allow-same-origin allow-forms"
            onload="onPreviewLoad()"
        ></iframe>
    </div>
</div>

<div class="preview-console" id="preview-console" style="display: none;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h4 style="color: var(--text-primary); margin: 0;">
            <i class="fas fa-terminal" style="margin-right: 8px;"></i>
            Console Output
        </h4>
        <button onclick="clearConsole()" style="background: none; border: none; color: var(--text-muted); cursor: pointer;">
            <i class="fas fa-trash"></i> Clear
        </button>
    </div>
    <div id="console-output">
        <div style="color: #888;">Console ready. Waiting for output...</div>
    </div>
</div>

<script>
let autoRefreshInterval;
let currentZoom = 1;

function refreshPreview() {
    const iframe = document.getElementById('preview-frame');
    const currentSrc = iframe.src;
    iframe.src = currentSrc;
    logConsole('Preview refreshed', 'info');
}

function toggleDeviceMode() {
    const container = document.getElementById('preview-container');
    const iframe = document.getElementById('preview-frame');
    
    if (container.style.gridTemplateColumns === '1fr') {
        container.style.gridTemplateColumns = '1fr 1fr';
        iframe.style.maxWidth = '100%';
        logConsole('Switched to desktop mode', 'info');
    } else {
        container.style.gridTemplateColumns = '1fr';
        iframe.style.maxWidth = '375px';
        iframe.style.margin = '0 auto';
        logConsole('Switched to mobile mode', 'info');
    }
}

function toggleConsole() {
    const console = document.getElementById('preview-console');
    console.style.display = console.style.display === 'none' ? 'block' : 'none';
}

function clearConsole() {
    document.getElementById('console-output').innerHTML = '<div style="color: #888;">Console cleared.</div>';
}

function logConsole(message, type = 'log') {
    const output = document.getElementById('console-output');
    const timestamp = new Date().toLocaleTimeString();
    const color = type === 'error' ? '#ff6b6b' : type === 'warn' ? '#feca57' : '#00ff00';
    
    const logEntry = document.createElement('div');
    logEntry.style.color = color;
    logEntry.innerHTML = `<span style="color: #888;">[${timestamp}]</span> ${message}`;
    
    output.appendChild(logEntry);
    output.scrollTop = output.scrollHeight;
}

function onPreviewLoad() {
    logConsole('Preview loaded successfully', 'info');
    trackActivity('preview_load', { timestamp: Date.now() });
}

function previewFile(filePath) {
    logConsole(`Previewing file: ${filePath}`, 'info');
    trackActivity('file_preview', { file: filePath });
}

function openInNewTab() {
    const projectId = document.getElementById('project-selector').value;
    window.open(`run_app.php?project_id=${projectId}&preview=true`, '_blank');
    logConsole('Opened preview in new tab', 'info');
}

function toggleAutoRefresh() {
    const checkbox = document.getElementById('auto-refresh');
    const status = document.getElementById('refresh-status');
    
    if (checkbox.checked) {
        startAutoRefresh();
        status.textContent = 'ON';
        status.style.color = '#22c55e';
        logConsole('Auto-refresh enabled', 'info');
    } else {
        stopAutoRefresh();
        status.textContent = 'OFF';
        status.style.color = '#ef4444';
        logConsole('Auto-refresh disabled', 'info');
    }
}

function startAutoRefresh() {
    autoRefreshInterval = setInterval(() => {
        refreshPreview();
    }, 5000);
}

function stopAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
    }
}

function zoomIn() {
    currentZoom = Math.min(currentZoom + 0.1, 2);
    applyZoom();
}

function zoomOut() {
    currentZoom = Math.max(currentZoom - 0.1, 0.5);
    applyZoom();
}

function resetZoom() {
    currentZoom = 1;
    applyZoom();
}

function applyZoom() {
    const iframe = document.getElementById('preview-frame');
    iframe.style.transform = `scale(${currentZoom})`;
    iframe.style.transformOrigin = 'top left';
    logConsole(`Zoom: ${Math.round(currentZoom * 100)}%`, 'info');
}

// Initialize auto-refresh
if (document.getElementById('auto-refresh').checked) {
    startAutoRefresh();
}

// Track visitor activity
trackVisitor('preview_page', {
    page: 'live_preview',
    project_id: '<?php echo $project_id; ?>',
    timestamp: Date.now()
});

// Capture console errors from iframe
window.addEventListener('message', (event) => {
    if (event.data.type === 'console') {
        logConsole(event.data.message, event.data.level);
    }
});

// Setup iframe communication
document.getElementById('preview-frame').onload = function() {
    try {
        this.contentWindow.console.log = function(...args) {
            logConsole(args.join(' '), 'log');
            window.parent.postMessage({
                type: 'console',
                message: args.join(' '),
                level: 'log'
            }, '*');
        };
        
        this.contentWindow.console.error = function(...args) {
            logConsole(args.join(' '), 'error');
            window.parent.postMessage({
                type: 'console',
                message: args.join(' '),
                level: 'error'
            }, '*');
        };
        
        this.contentWindow.console.warn = function(...args) {
            logConsole(args.join(' '), 'warn');
            window.parent.postMessage({
                type: 'console',
                message: args.join(' '),
                level: 'warn'
            }, '*');
        };
    } catch (e) {
        // Cross-origin restrictions, console capture may not work
        logConsole('Console capture limited due to cross-origin', 'warn');
    }
};
</script>
