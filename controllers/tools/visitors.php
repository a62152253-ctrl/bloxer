<?php
// Visitor Monitor page content
$conn = $auth->getConnection();

// Check if visitor_tracking table exists
$table_check = $conn->query("SHOW TABLES LIKE 'visitor_tracking'");

if ($table_check->num_rows > 0) {
    $stmt = $conn->prepare("
        SELECT * FROM visitor_tracking 
        WHERE project_id = ? 
        ORDER BY created_at DESC 
        LIMIT 100
    ");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $visitors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $visitors = [];
}

// Get visitor statistics
if ($table_check->num_rows > 0) {
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_visitors,
            COUNT(DISTINCT visitor_ip) as unique_visitors,
            MAX(created_at) as last_visit
        FROM visitor_tracking 
        WHERE project_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $visitor_stats = $stmt->get_result()->fetch_assoc();
} else {
    $visitor_stats = ['total_visitors' => 0, 'unique_visitors' => 0, 'last_visit' => null];
}

// Get popular pages
if ($table_check->num_rows > 0) {
    $stmt = $conn->prepare("
        SELECT page_url, COUNT(*) as visits
        FROM visitor_tracking 
        WHERE project_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY page_url
        ORDER BY visits DESC
        LIMIT 10
    ");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $popular_pages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $popular_pages = [];
}

// Get hourly traffic
if ($table_check->num_rows > 0) {
    $stmt = $conn->prepare("
        SELECT HOUR(created_at) as hour, COUNT(*) as visitors
        FROM visitor_tracking 
        WHERE project_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY HOUR(created_at)
        ORDER BY hour
    ");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $hourly_traffic = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $hourly_traffic = [];
}
?>

<div style="display: grid; grid-template-columns: 1fr 300px; gap: 25px;">
    <div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 25px;">
            <div class="metric-card">
                <div class="metric-value"><?php echo $visitor_stats['total_visitors'] ?? 0; ?></div>
                <div class="metric-label">Total Visits (24h)</div>
                <div class="metric-change positive">
                    <i class="fas fa-arrow-up"></i> Active now
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-value"><?php echo $visitor_stats['unique_visitors'] ?? 0; ?></div>
                <div class="metric-label">Unique Visitors (24h)</div>
                <div class="metric-change positive">
                    <i class="fas fa-users"></i> Real-time
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-value"><?php echo count($visitors); ?></div>
                <div class="metric-label">Total Records</div>
                <div class="metric-change">
                    <i class="fas fa-database"></i> All time
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-value">
                    <?php 
                    $last_visit = $visitor_stats['last_visit'] ?? null;
                    echo $last_visit ? date('H:i', strtotime($last_visit)) : '--:--';
                    ?>
                </div>
                <div class="metric-label">Last Visit</div>
                <div class="metric-change">
                    <i class="fas fa-clock"></i> Recent
                </div>
            </div>
        </div>
        
        <div style="background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); border-radius: 16px; padding: 25px; margin-bottom: 25px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="color: var(--text-primary); margin: 0;">
                    <i class="fas fa-users" style="margin-right: 10px;"></i>
                    Recent Visitors
                </h3>
                <div style="display: flex; gap: 10px;">
                    <button class="btn btn-small" onclick="refreshVisitors()">
                        <i class="fas fa-redo"></i> Refresh
                    </button>
                    <button class="btn btn-small" onclick="clearVisitors()">
                        <i class="fas fa-trash"></i> Clear
                    </button>
                </div>
            </div>
            
            <div class="visitor-grid" id="visitor-grid">
                <?php if (!empty($visitors)): ?>
                    <?php foreach ($visitors as $visitor): ?>
                        <div class="visitor-card">
                            <div class="visitor-header">
                                <div class="visitor-info">
                                    <div class="visitor-avatar">
                                        <?php echo substr($visitor['visitor_ip'], 0, 2); ?>
                                    </div>
                                    <div class="visitor-details">
                                        <div class="visitor-ip"><?php echo htmlspecialchars($visitor['visitor_ip']); ?></div>
                                        <div class="visitor-time"><?php echo date('M j, H:i:s', strtotime($visitor['created_at'])); ?></div>
                                    </div>
                                </div>
                                <div class="visitor-status <?php echo (time() - strtotime($visitor['created_at'])) < 300 ? 'online' : 'offline'; ?>">
                                    <?php echo (time() - strtotime($visitor['created_at'])) < 300 ? 'Online' : 'Offline'; ?>
                                </div>
                            </div>
                            
                            <div class="visitor-activity">
                                <h4>Activity Details</h4>
                                <div class="visitor-activity-item">
                                    <span class="page"><?php echo htmlspecialchars($visitor['page_url'] ?: 'Unknown'); ?></span>
                                    <span class="time"><?php echo date('H:i:s', strtotime($visitor['created_at'])); ?></span>
                                </div>
                                
                                <?php if (!empty($visitor['visitor_data'])): ?>
                                    <?php 
                                    $data = json_decode($visitor['visitor_data'], true);
                                    if (is_array($data) && !empty($data)):
                                    ?>
                                        <div style="margin-top: 10px; font-size: 0.8rem; color: var(--text-secondary);">
                                            <?php foreach ($data as $key => $value): ?>
                                                <div style="margin-bottom: 3px;">
                                                    <strong><?php echo ucfirst($key); ?>:</strong> 
                                                    <?php echo htmlspecialchars(is_array($value) ? json_encode($value) : $value); ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: var(--text-secondary);">
                        <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
                        <p>No visitor data yet</p>
                        <p style="font-size: 0.9rem;">Share your project link to start tracking visitors</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
            <div class="performance-chart">
                <h3>Popular Pages (24h)</h3>
                <div class="chart-container">
                    <?php if (!empty($popular_pages)): ?>
                        <div style="padding: 20px;">
                            <?php foreach ($popular_pages as $page): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding: 12px; background: rgba(255,255,255,0.05); border-radius: 8px;">
                                    <div style="flex: 1; margin-right: 15px;">
                                        <div style="font-weight: 500; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            <?php echo htmlspecialchars($page['page_url'] ?: 'Unknown'); ?>
                                        </div>
                                        <div style="font-size: 0.8rem; color: var(--text-secondary);"><?php echo $page['visits']; ?> visits</div>
                                    </div>
                                    <div style="width: 60px; height: 8px; background: rgba(255,255,255,0.1); border-radius: 4px; overflow: hidden;">
                                        <div style="width: <?php echo min(100, ($page['visits'] / max(1, $popular_pages[0]['visits'])) * 100); ?>%; height: 100%; background: var(--accent);"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="chart-placeholder">
                            <i class="fas fa-chart-bar" style="font-size: 2rem; margin-bottom: 10px;"></i>
                            <p>No page data yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="performance-chart">
                <h3>Hourly Traffic (24h)</h3>
                <div class="chart-container">
                    <?php if (!empty($hourly_traffic)): ?>
                        <div style="padding: 20px;">
                            <?php 
                            // Fill missing hours with 0
                            $traffic_by_hour = array_fill(0, 24, 0);
                            foreach ($hourly_traffic as $hour) {
                                $traffic_by_hour[$hour['hour']] = $hour['visitors'];
                            }
                            
                            $max_traffic = max($traffic_by_hour);
                            ?>
                            
                            <div style="display: flex; align-items: flex-end; height: 120px; gap: 2px;">
                                <?php foreach ($traffic_by_hour as $hour => $visitors): ?>
                                    <div style="flex: 1; background: var(--accent); border-radius: 2px; height: <?php echo $max_traffic > 0 ? ($visitors / $max_traffic) * 100 : 0; ?>%; position: relative;" title="<?php echo $hour; ?>:00 - <?php echo $visitors; ?> visitors">
                                        <div style="position: absolute; top: -20px; left: 50%; transform: translateX(-50%); font-size: 0.7rem; color: var(--text-secondary);">
                                            <?php echo $visitors > 0 ? $visitors : ''; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div style="display: flex; justify-content: space-between; margin-top: 10px; font-size: 0.7rem; color: var(--text-secondary);">
                                <?php for ($i = 0; $i < 24; $i += 4): ?>
                                    <span><?php echo $i; ?>:00</span>
                                <?php endfor; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="chart-placeholder">
                            <i class="fas fa-clock" style="font-size: 2rem; margin-bottom: 10px;"></i>
                            <p>No hourly data yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div>
        <div style="background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); border-radius: 16px; padding: 25px; margin-bottom: 25px;">
            <h3 style="color: var(--text-primary); margin-bottom: 20px;">
                <i class="fas fa-share-alt" style="margin-right: 10px;"></i>
                Share Project
            </h3>
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; color: var(--text-secondary); margin-bottom: 8px;">Project URL</label>
                <div style="display: flex; gap: 10px;">
                    <input type="text" id="project-url" readonly value="<?php echo "http://localhost/bloxer/run_app.php?project_id={$project_id}"; ?>" style="flex: 1; padding: 10px; border-radius: 8px; background: rgba(255,255,255,0.1); border: 1px solid var(--glass-border); color: var(--text-primary);">
                    <button class="btn btn-small" onclick="copyProjectUrl()">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; color: var(--text-secondary); margin-bottom: 8px;">Tracking Code</label>
                <textarea readonly rows="4" style="width: 100%; padding: 10px; border-radius: 8px; background: rgba(255,255,255,0.1); border: 1px solid var(--glass-border); color: var(--text-primary); font-family: monospace; font-size: 0.8rem;"><script>
(function() {
    var script = document.createElement('script');
    script.src = '<?php echo "http://localhost/bloxer/assets/js/visitor-tracker.js?project_id={$project_id}"; ?>';
    document.head.appendChild(script);
})();
</script></textarea>
            </div>
            
            <button class="btn btn-small" onclick="copyTrackingCode()" style="width: 100%;">
                <i class="fas fa-code"></i> Copy Tracking Code
            </button>
        </div>
        
        <div style="background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); border-radius: 16px; padding: 25px; margin-bottom: 25px;">
            <h3 style="color: var(--text-primary); margin-bottom: 20px;">
                <i class="fas fa-filter" style="margin-right: 10px;"></i>
                Filters
            </h3>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; color: var(--text-secondary); margin-bottom: 8px;">Time Range</label>
                <select id="visitor-time-filter" onchange="filterVisitors()" style="width: 100%; padding: 10px; border-radius: 8px; background: rgba(255,255,255,0.1); border: 1px solid var(--glass-border); color: var(--text-primary);">
                    <option value="">All Time</option>
                    <option value="1">Last Hour</option>
                    <option value="24">Last 24 Hours</option>
                    <option value="168">Last 7 Days</option>
                    <option value="720">Last 30 Days</option>
                </select>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; color: var(--text-secondary); margin-bottom: 8px;">Visitor Status</label>
                <select id="visitor-status-filter" onchange="filterVisitors()" style="width: 100%; padding: 10px; border-radius: 8px; background: rgba(255,255,255,0.1); border: 1px solid var(--glass-border); color: var(--text-primary);">
                    <option value="">All Visitors</option>
                    <option value="online">Online Now</option>
                    <option value="offline">Offline</option>
                </select>
            </div>
            
            <button class="btn btn-small" onclick="exportVisitors()" style="width: 100%;">
                <i class="fas fa-download"></i> Export Data
            </button>
        </div>
        
        <div style="background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); border-radius: 16px; padding: 25px;">
            <h3 style="color: var(--text-primary); margin-bottom: 20px;">
                <i class="fas fa-cog" style="margin-right: 10px;"></i>
                Tracking Settings
            </h3>
            
            <div style="margin-bottom: 15px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" id="track-page-views" checked onchange="updateVisitorSettings()">
                    <span style="color: var(--text-secondary);">Track page views</span>
                </label>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" id="track-user-agent" checked onchange="updateVisitorSettings()">
                    <span style="color: var(--text-secondary);">Track user agent</span>
                </label>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" id="track-referrer" onchange="updateVisitorSettings()">
                    <span style="color: var(--text-secondary);">Track referrer</span>
                </label>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" id="real-time-updates" checked onchange="updateVisitorSettings()">
                    <span style="color: var(--text-secondary);">Real-time updates</span>
                </label>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" id="auto-cleanup-visitors" onchange="updateVisitorSettings()">
                    <span style="color: var(--text-secondary);">Auto-cleanup old records (90 days)</span>
                </label>
            </div>
            
            <button class="btn btn-small" onclick="testVisitorTracking()" style="width: 100%; margin-top: 15px;">
                <i class="fas fa-vial"></i> Test Tracking
            </button>
        </div>
    </div>
</div>

<script>
let visitorData = <?php echo json_encode($visitors); ?>;

function refreshVisitors() {
    loadVisitorData();
}

function clearVisitors() {
    if (confirm('Are you sure you want to clear all visitor records? This cannot be undone.')) {
        fetch('tools.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=clear_visitors&project_id=<?php echo $project_id; ?>`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('visitor-grid').innerHTML = `
                    <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: var(--text-secondary);">
                        <i class="fas fa-trash" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
                        <p>Visitor data cleared successfully</p>
                    </div>
                `;
            }
        });
    }
}

function filterVisitors() {
    const timeFilter = document.getElementById('visitor-time-filter').value;
    const statusFilter = document.getElementById('visitor-status-filter').value;
    
    let filtered = visitorData.filter(visitor => {
        let matchTime = true;
        let matchStatus = true;
        
        if (timeFilter) {
            const visitorTime = new Date(visitor.created_at).getTime();
            const cutoffTime = Date.now() - (parseInt(timeFilter) * 60 * 60 * 1000);
            matchTime = visitorTime >= cutoffTime;
        }
        
        if (statusFilter) {
            const isOnline = (Date.now() - new Date(visitor.created_at).getTime()) < 300000; // 5 minutes
            matchStatus = (statusFilter === 'online' && isOnline) || (statusFilter === 'offline' && !isOnline);
        }
        
        return matchTime && matchStatus;
    });
    
    updateVisitorDisplay(filtered);
}

function updateVisitorDisplay(visitors) {
    const grid = document.getElementById('visitor-grid');
    
    if (visitors.length === 0) {
        grid.innerHTML = `
            <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: var(--text-secondary);">
                <i class="fas fa-search" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
                <p>No visitors match your filters</p>
            </div>
        `;
        return;
    }
    
    grid.innerHTML = visitors.map(visitor => {
        const isOnline = (Date.now() - new Date(visitor.created_at).getTime()) < 300000;
        const data = visitor.visitor_data ? JSON.parse(visitor.visitor_data) : null;
        
        return `
            <div class="visitor-card">
                <div class="visitor-header">
                    <div class="visitor-info">
                        <div class="visitor-avatar">${visitor.visitor_ip.substring(0, 2)}</div>
                        <div class="visitor-details">
                            <div class="visitor-ip">${visitor.visitor_ip}</div>
                            <div class="visitor-time">${new Date(visitor.created_at).toLocaleString()}</div>
                        </div>
                    </div>
                    <div class="visitor-status ${isOnline ? 'online' : 'offline'}">
                        ${isOnline ? 'Online' : 'Offline'}
                    </div>
                </div>
                
                <div class="visitor-activity">
                    <h4>Activity Details</h4>
                    <div class="visitor-activity-item">
                        <span class="page">${visitor.page_url || 'Unknown'}</span>
                        <span class="time">${new Date(visitor.created_at).toLocaleTimeString()}</span>
                    </div>
                    
                    ${data ? `
                        <div style="margin-top: 10px; font-size: 0.8rem; color: var(--text-secondary);">
                            ${Object.entries(data).map(([key, value]) => 
                                `<div style="margin-bottom: 3px;">
                                    <strong>${ucfirst(key)}:</strong> ${Array.isArray(value) ? JSON.stringify(value) : value}
                                </div>`
                            ).join('')}
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
    }).join('');
}

function copyProjectUrl() {
    const urlInput = document.getElementById('project-url');
    urlInput.select();
    document.execCommand('copy');
    
    trackActivity('copy_project_url', { timestamp: Date.now() });
    alert('Project URL copied to clipboard!');
}

function copyTrackingCode() {
    const textarea = document.querySelector('textarea[readonly]');
    textarea.select();
    document.execCommand('copy');
    
    trackActivity('copy_tracking_code', { timestamp: Date.now() });
    alert('Tracking code copied to clipboard!');
}

function exportVisitors() {
    const dataStr = JSON.stringify(visitorData, null, 2);
    const dataUri = 'data:application/json;charset=utf-8,'+ encodeURIComponent(dataStr);
    
    const exportFileDefaultName = `visitors_${new Date().toISOString().split('T')[0]}.json`;
    
    const linkElement = document.createElement('a');
    linkElement.setAttribute('href', dataUri);
    linkElement.setAttribute('download', exportFileDefaultName);
    linkElement.click();
    
    trackActivity('visitors_export', { timestamp: Date.now() });
}

function updateVisitorSettings() {
    const settings = {
        track_page_views: document.getElementById('track-page-views').checked,
        track_user_agent: document.getElementById('track-user-agent').checked,
        track_referrer: document.getElementById('track-referrer').checked,
        real_time_updates: document.getElementById('real-time-updates').checked,
        auto_cleanup_visitors: document.getElementById('auto-cleanup-visitors').checked
    };
    
    localStorage.setItem('visitor_settings', JSON.stringify(settings));
    trackActivity('visitor_settings_update', settings);
}

function testVisitorTracking() {
    trackVisitor('test_page', {
        action: 'test_tracking',
        timestamp: Date.now(),
        user_agent: navigator.userAgent,
        test: true
    });
    
    setTimeout(() => {
        loadVisitorData();
        alert('Test visitor tracked! Refresh to see it in the list.');
    }, 500);
}

function ucfirst(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

// Load visitor settings on page load
const savedVisitorSettings = localStorage.getItem('visitor_settings');
if (savedVisitorSettings) {
    const settings = JSON.parse(savedVisitorSettings);
    document.getElementById('track-page-views').checked = settings.track_page_views;
    document.getElementById('track-user-agent').checked = settings.track_user_agent;
    document.getElementById('track-referrer').checked = settings.track_referrer;
    document.getElementById('real-time-updates').checked = settings.real_time_updates;
    document.getElementById('auto-cleanup-visitors').checked = settings.auto_cleanup_visitors;
}

// Real-time updates
setInterval(() => {
    if (document.getElementById('real-time-updates')?.checked && document.visibilityState === 'visible') {
        loadVisitorData();
    }
}, 30000);

// Track page view
trackVisitor('visitor_monitor_page', {
    page: 'visitor_monitor',
    project_id: '<?php echo $project_id; ?>',
    timestamp: Date.now()
});
</script>
