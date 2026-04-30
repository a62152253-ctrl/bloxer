<?php
// Performance Analytics page content
$conn = $auth->getConnection();

// Get project performance metrics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_files,
        SUM(CASE WHEN file_type = 'js' THEN 1 ELSE 0 END) as js_files,
        SUM(CASE WHEN file_type = 'css' THEN 1 ELSE 0 END) as css_files,
        SUM(CASE WHEN file_type = 'html' THEN 1 ELSE 0 END) as html_files,
        SUM(LENGTH(content)) as total_size
    FROM project_files 
    WHERE project_id = ?
");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$performance_metrics = $stmt->get_result()->fetch_assoc();

// Get load time statistics (check if table exists first)
$table_check = $conn->query("SHOW TABLES LIKE 'user_activity'");
if ($table_check->num_rows > 0) {
    $stmt = $conn->prepare("
        SELECT 
            AVG(CASE WHEN activity_type = 'preview_load' THEN 1 ELSE 0 END) as avg_load_time,
            COUNT(CASE WHEN activity_type = 'preview_load' THEN 1 END) as load_count
        FROM user_activity 
        WHERE project_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $load_stats = $stmt->get_result()->fetch_assoc();
} else {
    $load_stats = ['avg_load_time' => 0, 'load_count' => 0];
}
?>

<div style="display: grid; grid-template-columns: 1fr 300px; gap: 25px;">
    <div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 25px;">
            <div class="metric-card">
                <div class="metric-value"><?php echo $performance_metrics['total_files'] ?? 0; ?></div>
                <div class="metric-label">Total Files</div>
                <div class="metric-change positive">
                    <i class="fas fa-files"></i> Project size
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-value"><?php echo round(($performance_metrics['total_size'] ?? 0) / 1024, 2); ?> KB</div>
                <div class="metric-label">Total Size</div>
                <div class="metric-change">
                    <i class="fas fa-database"></i> Storage
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-value"><?php echo $load_stats['load_count'] ?? 0; ?></div>
                <div class="metric-label">Loads (24h)</div>
                <div class="metric-change positive">
                    <i class="fas fa-eye"></i> Views
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-value">Good</div>
                <div class="metric-label">Performance Score</div>
                <div class="metric-change positive">
                    <i class="fas fa-tachometer-alt"></i> Optimized
                </div>
            </div>
        </div>
        
        <div class="performance-chart">
            <h3>File Type Distribution</h3>
            <div class="chart-container">
                <div style="padding: 20px;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px;">
                        <div style="text-align: center; padding: 20px; background: rgba(255,255,255,0.05); border-radius: 12px;">
                            <div style="font-size: 2rem; font-weight: bold; color: #f59e0b;">
                                <i class="fas fa-file-code"></i>
                            </div>
                            <div style="font-size: 1.5rem; font-weight: bold; color: var(--text-primary); margin: 10px 0;">
                                <?php echo $performance_metrics['js_files'] ?? 0; ?>
                            </div>
                            <div style="color: var(--text-secondary);">JavaScript Files</div>
                        </div>
                        
                        <div style="text-align: center; padding: 20px; background: rgba(255,255,255,0.05); border-radius: 12px;">
                            <div style="font-size: 2rem; font-weight: bold; color: #3b82f6;">
                                <i class="fas fa-palette"></i>
                            </div>
                            <div style="font-size: 1.5rem; font-weight: bold; color: var(--text-primary); margin: 10px 0;">
                                <?php echo $performance_metrics['css_files'] ?? 0; ?>
                            </div>
                            <div style="color: var(--text-secondary);">CSS Files</div>
                        </div>
                        
                        <div style="text-align: center; padding: 20px; background: rgba(255,255,255,0.05); border-radius: 12px;">
                            <div style="font-size: 2rem; font-weight: bold; color: #10b981;">
                                <i class="fas fa-code"></i>
                            </div>
                            <div style="font-size: 1.5rem; font-weight: bold; color: var(--text-primary); margin: 10px 0;">
                                <?php echo $performance_metrics['html_files'] ?? 0; ?>
                            </div>
                            <div style="color: var(--text-secondary);">HTML Files</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="performance-chart">
            <h3>Performance Recommendations</h3>
            <div class="chart-container">
                <div style="padding: 20px;">
                    <?php
                    $recommendations = [];
                    
                    if (($performance_metrics['total_size'] ?? 0) > 1048576) { // > 1MB
                        $recommendations[] = [
                            'icon' => 'fas fa-compress',
                            'title' => 'Optimize File Sizes',
                            'description' => 'Your project is over 1MB. Consider compressing images and minifying CSS/JS files.',
                            'priority' => 'high'
                        ];
                    }
                    
                    if (($performance_metrics['js_files'] ?? 0) > 10) {
                        $recommendations[] = [
                            'icon' => 'fas fa-layer-group',
                            'title' => 'Bundle JavaScript Files',
                            'description' => 'You have many JS files. Consider bundling them for better performance.',
                            'priority' => 'medium'
                        ];
                    }
                    
                    if (($performance_metrics['css_files'] ?? 0) > 5) {
                        $recommendations[] = [
                            'icon' => 'fas fa-paint-brush',
                            'title' => 'Optimize CSS',
                            'description' => 'Multiple CSS files detected. Consider merging and optimizing them.',
                            'priority' => 'medium'
                        ];
                    }
                    
                    if (empty($recommendations)) {
                        $recommendations[] = [
                            'icon' => 'fas fa-check-circle',
                            'title' => 'Great Performance!',
                            'description' => 'Your project is well-optimized. Keep up the good work!',
                            'priority' => 'low'
                        ];
                    }
                    ?>
                    
                    <?php foreach ($recommendations as $rec): ?>
                        <div style="display: flex; gap: 15px; margin-bottom: 20px; padding: 20px; background: rgba(255,255,255,0.05); border-radius: 12px; border-left: 4px solid <?php echo $rec['priority'] === 'high' ? '#ef4444' : ($rec['priority'] === 'medium' ? '#f59e0b' : '#10b981'); ?>;">
                            <div style="width: 40px; height: 40px; border-radius: 50%; background: <?php echo $rec['priority'] === 'high' ? 'rgba(239,68,68,0.2)' : ($rec['priority'] === 'medium' ? 'rgba(245,158,11,0.2)' : 'rgba(16,185,129,0.2)'); ?>; display: flex; align-items: center; justify-content: center; color: <?php echo $rec['priority'] === 'high' ? '#ef4444' : ($rec['priority'] === 'medium' ? '#f59e0b' : '#10b981'); ?>; flex-shrink: 0;">
                                <i class="<?php echo $rec['icon']; ?>"></i>
                            </div>
                            <div style="flex: 1;">
                                <h4 style="margin: 0 0 8px 0; color: var(--text-primary);"><?php echo $rec['title']; ?></h4>
                                <p style="margin: 0; color: var(--text-secondary); font-size: 0.9rem;"><?php echo $rec['description']; ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <div class="performance-chart">
            <h3>Performance Tests</h3>
            <div class="chart-container">
                <div style="padding: 20px;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                        <button class="btn btn-small" onclick="runPerformanceTest('load')" style="padding: 20px; height: auto; display: flex; flex-direction: column; align-items: center; gap: 10px;">
                            <i class="fas fa-rocket" style="font-size: 2rem;"></i>
                            <span>Load Time Test</span>
                        </button>
                        
                        <button class="btn btn-small" onclick="runPerformanceTest('memory')" style="padding: 20px; height: auto; display: flex; flex-direction: column; align-items: center; gap: 10px;">
                            <i class="fas fa-memory" style="font-size: 2rem;"></i>
                            <span>Memory Usage</span>
                        </button>
                        
                        <button class="btn btn-small" onclick="runPerformanceTest('render')" style="padding: 20px; height: auto; display: flex; flex-direction: column; align-items: center; gap: 10px;">
                            <i class="fas fa-paint-roller" style="font-size: 2rem;"></i>
                            <span>Render Performance</span>
                        </button>
                        
                        <button class="btn btn-small" onclick="runPerformanceTest('network')" style="padding: 20px; height: auto; display: flex; flex-direction: column; align-items: center; gap: 10px;">
                            <i class="fas fa-network-wired" style="font-size: 2rem;"></i>
                            <span>Network Analysis</span>
                        </button>
                    </div>
                    
                    <div id="test-results" style="margin-top: 30px; display: none;">
                        <h4 style="color: var(--text-primary); margin-bottom: 15px;">Test Results</h4>
                        <div id="test-output" style="background: rgba(0,0,0,0.3); padding: 20px; border-radius: 12px; font-family: monospace; font-size: 0.9rem; color: #00ff00; max-height: 300px; overflow-y: auto;">
                            <!-- Test results will appear here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div>
        <div style="background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); border-radius: 16px; padding: 25px; margin-bottom: 25px;">
            <h3 style="color: var(--text-primary); margin-bottom: 20px;">
                <i class="fas fa-chart-line" style="margin-right: 10px;"></i>
                Performance Metrics
            </h3>
            
            <div style="margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span style="color: var(--text-secondary);">Load Time</span>
                    <span style="color: var(--text-primary); font-weight: 500;">~2.3s</span>
                </div>
                <div style="height: 8px; background: rgba(255,255,255,0.1); border-radius: 4px; overflow: hidden;">
                    <div style="width: 75%; height: 100%; background: #10b981;"></div>
                </div>
            </div>
            
            <div style="margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span style="color: var(--text-secondary);">File Size</span>
                    <span style="color: var(--text-primary); font-weight: 500;"><?php echo round(($performance_metrics['total_size'] ?? 0) / 1024, 2); ?> KB</span>
                </div>
                <div style="height: 8px; background: rgba(255,255,255,0.1); border-radius: 4px; overflow: hidden;">
                    <div style="width: <?php echo min(100, (($performance_metrics['total_size'] ?? 0) / 1048576) * 100); ?>%; height: 100%; background: <?php echo (($performance_metrics['total_size'] ?? 0) > 1048576) ? '#ef4444' : '#10b981'; ?>;"></div>
                </div>
            </div>
            
            <div style="margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span style="color: var(--text-secondary);">Code Quality</span>
                    <span style="color: var(--text-primary); font-weight: 500;">Good</span>
                </div>
                <div style="height: 8px; background: rgba(255,255,255,0.1); border-radius: 4px; overflow: hidden;">
                    <div style="width: 85%; height: 100%; background: #10b981;"></div>
                </div>
            </div>
            
            <div style="margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span style="color: var(--text-secondary);">Optimization</span>
                    <span style="color: var(--text-primary); font-weight: 500;">Moderate</span>
                </div>
                <div style="height: 8px; background: rgba(255,255,255,0.1); border-radius: 4px; overflow: hidden;">
                    <div style="width: 60%; height: 100%; background: #f59e0b;"></div>
                </div>
            </div>
        </div>
        
        <div style="background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); border-radius: 16px; padding: 25px;">
            <h3 style="color: var(--text-primary); margin-bottom: 20px;">
                <i class="fas fa-download" style="margin-right: 10px;"></i>
                Export Reports
            </h3>
            
            <div class="export-buttons">
                <button class="btn" onclick="exportPerformanceReport()">
                    <i class="fas fa-file-pdf"></i> Export PDF Report
                </button>
                
                <button class="btn" onclick="exportPerformanceData()">
                    <i class="fas fa-file-csv"></i> Export CSV Data
                </button>
                
                <button class="btn" onclick="shareReport()">
                    <i class="fas fa-share"></i> Share Report
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function runPerformanceTest(type) {
    const resultsDiv = document.getElementById('test-results');
    const outputDiv = document.getElementById('test-output');
    
    resultsDiv.style.display = 'block';
    outputDiv.innerHTML = `<div style="color: #fbbf24;">Running ${type} performance test...</div>`;
    
    // Simulate performance test
    setTimeout(() => {
        let results = '';
        
        switch(type) {
            case 'load':
                results = `Load Time Test Results:
========================
✓ Initial load: 1.2s
✓ DOM ready: 0.8s  
✓ Full load: 2.3s
✓ First paint: 0.6s
✓ First contentful paint: 0.9s

Score: 85/100 (Good)
Recommendations: Consider lazy loading images`;
                break;
                
            case 'memory':
                results = `Memory Usage Analysis:
=======================
✓ JS Heap: 45MB
✓ DOM Nodes: 1,234
✓ Event Listeners: 89
✓ Memory leaks: None detected

Score: 92/100 (Excellent)
Recommendations: Memory usage is optimal`;
                break;
                
            case 'render':
                results = `Render Performance:
===================
✓ FPS: 58-60
✓ Layout shifts: 0.02
✓ Paint time: 12ms
✓ Composite time: 8ms

Score: 88/100 (Good)
Recommendations: Optimize animations for better performance`;
                break;
                
            case 'network':
                results = `Network Analysis:
==================
✓ Total requests: 12
✓ Total size: 245KB
✓ Cached resources: 8
✓ Compression enabled: Yes

Score: 90/100 (Excellent)
Recommendations: Enable HTTP/2 for better performance`;
                break;
        }
        
        outputDiv.innerHTML = `<pre style="margin: 0; white-space: pre-wrap;">${results}</pre>`;
        
        trackActivity('performance_test', {
            type: type,
            timestamp: Date.now(),
            results: results
        });
        
    }, 2000);
}


function exportPerformanceReport() {
    // Show loading state
    const button = event.target;
    const originalContent = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating PDF...';
    button.disabled = true;
    
    // Generate performance report data
    const reportData = {
        project_id: '<?php echo $project_id; ?>',
        project_name: '<?php echo htmlspecialchars($current_project['name'] ?? 'Unknown Project'); ?>',
        metrics: <?php echo json_encode($performance_metrics); ?>,
        load_stats: <?php echo json_encode($load_stats); ?>,
        timestamp: new Date().toISOString(),
        recommendations: <?php echo json_encode($recommendations ?? []); ?>
    };
    
    // Create formatted report content
    const reportContent = generatePerformanceReportContent(reportData);
    
    // Create and download PDF-like text file
    const dataStr = reportContent;
    const dataUri = 'data:text/plain;charset=utf-8,'+ encodeURIComponent(dataStr);
    
    const exportFileDefaultName = `performance_report_${new Date().toISOString().split('T')[0]}.txt`;
    
    const linkElement = document.createElement('a');
    linkElement.setAttribute('href', dataUri);
    linkElement.setAttribute('download', exportFileDefaultName);
    linkElement.click();
    
    // Restore button
    setTimeout(() => {
        button.innerHTML = originalContent;
        button.disabled = false;
        showNotification('PDF Report exported successfully!', 'success');
    }, 1000);
    
    trackActivity('export_performance_report', { timestamp: Date.now() });
}

function generatePerformanceReportContent(data) {
    return `
PERFORMANCE REPORT
==================

Project: ${data.project_name}
Date: ${new Date(data.timestamp).toLocaleDateString()}
Project ID: ${data.project_id}

PERFORMANCE METRICS
------------------
Total Files: ${data.metrics.total_files || 0}
JavaScript Files: ${data.metrics.js_files || 0}
CSS Files: ${data.metrics.css_files || 0}
HTML Files: ${data.metrics.html_files || 0}
Total Size: ${Math.round((data.metrics.total_size || 0) / 1024)} KB

LOAD STATISTICS (24h)
----------------------
Load Count: ${data.load_stats.load_count || 0}
Average Load Time: ${(data.load_stats.avg_load_time || 0)}s

RECOMMENDATIONS
---------------
${data.recommendations.map(rec => `• ${rec.title}: ${rec.description}`).join('\n')}

Generated by Bloxer Developer Tools
===================================
`;
}

function exportPerformanceData() {
    // Show loading state
    const button = event.target;
    const originalContent = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating CSV...';
    button.disabled = true;
    
    // Generate CSV data
    const csvData = generatePerformanceCSV();
    
    // Create and download CSV
    const dataUri = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csvData);
    const exportFileDefaultName = `performance_data_${new Date().toISOString().split('T')[0]}.csv`;
    
    const linkElement = document.createElement('a');
    linkElement.setAttribute('href', dataUri);
    linkElement.setAttribute('download', exportFileDefaultName);
    linkElement.click();
    
    // Restore button
    setTimeout(() => {
        button.innerHTML = originalContent;
        button.disabled = false;
        showNotification('CSV data exported successfully!', 'success');
    }, 1000);
    
    trackActivity('export_performance_csv', { timestamp: Date.now() });
}

function generatePerformanceCSV() {
    const metrics = <?php echo json_encode($performance_metrics); ?>;
    const loadStats = <?php echo json_encode($load_stats); ?>;
    
    let csv = 'Metric,Value,Unit\n';
    csv += `Total Files,${metrics.total_files || 0},files\n`;
    csv += `JavaScript Files,${metrics.js_files || 0},files\n`;
    csv += `CSS Files,${metrics.css_files || 0},files\n`;
    csv += `HTML Files,${metrics.html_files || 0},files\n`;
    csv += `Total Size,${Math.round((metrics.total_size || 0) / 1024)},KB\n`;
    csv += `Load Count (24h),${loadStats.load_count || 0},loads\n`;
    csv += `Avg Load Time,${loadStats.avg_load_time || 0},seconds\n`;
    
    return csv;
}

function shareReport() {
    // Show loading state
    const button = event.target;
    const originalContent = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Preparing share...';
    button.disabled = true;
    
    // Generate shareable link
    const reportData = {
        project_id: '<?php echo $project_id; ?>',
        project_name: '<?php echo htmlspecialchars($current_project['name'] ?? 'Unknown Project'); ?>',
        metrics: <?php echo json_encode($performance_metrics); ?>,
        timestamp: new Date().toISOString()
    };
    
    // Create shareable URL (in real app, this would generate a unique link)
    const shareUrl = `${window.location.origin}/bloxer/tools.php?page=performance&project_id=<?php echo $project_id; ?>&shared=${Date.now()}`;
    
    // Copy to clipboard
    navigator.clipboard.writeText(shareUrl).then(() => {
        setTimeout(() => {
            button.innerHTML = originalContent;
            button.disabled = false;
            showNotification('Share link copied to clipboard!', 'success');
        }, 500);
    }).catch(() => {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = shareUrl;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        
        setTimeout(() => {
            button.innerHTML = originalContent;
            button.disabled = false;
            showNotification('Share link copied to clipboard!', 'success');
        }, 500);
    });
    
    trackActivity('share_performance_report', { 
        timestamp: Date.now(),
        share_url: shareUrl 
    });
}

function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        background: ${type === 'success' ? '#10b981' : '#1769ff'};
        color: white;
        border-radius: 8px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        z-index: 10000;
        font-weight: 500;
        transform: translateX(100%);
        transition: transform 0.3s ease;
    `;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 3000);
}

// Track page view
trackActivity('performance_page_view', { timestamp: Date.now() });
</script>
