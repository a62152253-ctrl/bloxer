<?php
// Activity Tracking page content
$conn = $auth->getConnection();

// Check if user_activity table exists
$table_check = $conn->query("SHOW TABLES LIKE 'user_activity'");

if ($table_check->num_rows > 0) {
    $stmt = $conn->prepare("
        SELECT * FROM user_activity 
        WHERE project_id = ? 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $activities = [];
}

// Get activity summary
if ($table_check->num_rows > 0) {
    $stmt = $conn->prepare("
        SELECT activity_type, COUNT(*) as count, MAX(created_at) as last_activity
        FROM user_activity 
        WHERE project_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY activity_type
        ORDER BY count DESC
    ");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $activity_summary = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $activity_summary = [];
}
?>

<div style="display: grid; grid-template-columns: 1fr 300px; gap: 25px;">
    <div>
        <div style="background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); border-radius: 16px; padding: 25px; margin-bottom: 25px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="color: var(--text-primary); margin: 0;">
                    <i class="fas fa-history" style="margin-right: 10px;"></i>
                    Activity Timeline
                </h3>
                <div style="display: flex; gap: 10px;">
                    <button class="btn btn-small" onclick="refreshActivity()">
                        <i class="fas fa-redo"></i> Refresh
                    </button>
                    <button class="btn btn-small" onclick="clearActivity()">
                        <i class="fas fa-trash"></i> Clear
                    </button>
                </div>
            </div>
            
            <div class="activity-timeline" id="activity-timeline">
                <?php if (!empty($activities)): ?>
                    <?php foreach ($activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <?php
                                $icon = 'fas fa-circle';
                                switch ($activity['activity_type']) {
                                    case 'file_edit':
                                        $icon = 'fas fa-edit';
                                        break;
                                    case 'file_create':
                                        $icon = 'fas fa-plus';
                                        break;
                                    case 'file_delete':
                                        $icon = 'fas fa-trash';
                                        break;
                                    case 'preview_load':
                                        $icon = 'fas fa-eye';
                                        break;
                                    case 'project_save':
                                        $icon = 'fas fa-save';
                                        break;
                                    case 'debug':
                                        $icon = 'fas fa-bug';
                                        break;
                                    case 'test':
                                        $icon = 'fas fa-flask';
                                        break;
                                }
                                ?>
                                <i class="<?php echo $icon; ?>"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-type"><?php echo ucfirst(str_replace('_', ' ', $activity['activity_type'])); ?></div>
                                <div class="activity-time"><?php echo date('M j, Y H:i:s', strtotime($activity['created_at'])); ?></div>
                                <?php if (!empty($activity['activity_data'])): ?>
                                    <div class="activity-details">
                                        <?php 
                                        $data = json_decode($activity['activity_data'], true);
                                        if (is_array($data)) {
                                            foreach ($data as $key => $value) {
                                                echo "<strong>" . ucfirst($key) . ":</strong> " . htmlspecialchars(is_array($value) ? json_encode($value) : $value) . "<br>";
                                            }
                                        } else {
                                            echo htmlspecialchars($data);
                                        }
                                        ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
                        <i class="fas fa-history" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
                        <p>No activity recorded yet</p>
                        <p style="font-size: 0.9rem;">Start working on your project to see activity here</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div style="background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); border-radius: 16px; padding: 25px;">
            <h3 style="color: var(--text-primary); margin-bottom: 20px;">
                <i class="fas fa-chart-bar" style="margin-right: 10px;"></i>
                Activity Statistics
            </h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <div style="text-align: center; padding: 20px; background: rgba(255,255,255,0.05); border-radius: 12px;">
                    <div style="font-size: 2rem; font-weight: bold; color: var(--accent);"><?php echo count($activities); ?></div>
                    <div style="color: var(--text-secondary); font-size: 0.9rem;">Total Activities</div>
                </div>
                <div style="text-align: center; padding: 20px; background: rgba(255,255,255,0.05); border-radius: 12px;">
                    <div style="font-size: 2rem; font-weight: bold; color: var(--accent);"><?php echo count($activity_summary); ?></div>
                    <div style="color: var(--text-secondary); font-size: 0.9rem;">Activity Types</div>
                </div>
            </div>
            
            <?php if (!empty($activity_summary)): ?>
                <div style="margin-top: 25px;">
                    <h4 style="color: var(--text-primary); margin-bottom: 15px;">Last 7 Days Summary</h4>
                    <?php foreach ($activity_summary as $summary): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding: 12px; background: rgba(255,255,255,0.05); border-radius: 8px;">
                            <div>
                                <div style="font-weight: 500; color: var(--text-primary);"><?php echo ucfirst(str_replace('_', ' ', $summary['activity_type'])); ?></div>
                                <div style="font-size: 0.8rem; color: var(--text-secondary);">Last: <?php echo date('M j H:i', strtotime($summary['last_activity'])); ?></div>
                            </div>
                            <div style="font-size: 1.2rem; font-weight: bold; color: var(--accent);"><?php echo $summary['count']; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div>
        <div style="background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); border-radius: 16px; padding: 25px; margin-bottom: 25px;">
            <h3 style="color: var(--text-primary); margin-bottom: 20px;">
                <i class="fas fa-filter" style="margin-right: 10px;"></i>
                Filters
            </h3>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; color: var(--text-secondary); margin-bottom: 8px;">Activity Type</label>
                <select id="activity-filter" onchange="filterActivities()" style="width: 100%; padding: 10px; border-radius: 8px; background: rgba(255,255,255,0.1); border: 1px solid var(--glass-border); color: var(--text-primary);">
                    <option value="">All Activities</option>
                    <option value="file_edit">File Edits</option>
                    <option value="file_create">File Creation</option>
                    <option value="file_delete">File Deletion</option>
                    <option value="preview_load">Preview Loads</option>
                    <option value="project_save">Project Saves</option>
                    <option value="debug">Debug Sessions</option>
                    <option value="test">Test Runs</option>
                </select>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; color: var(--text-secondary); margin-bottom: 8px;">Time Range</label>
                <select id="time-filter" onchange="filterActivities()" style="width: 100%; padding: 10px; border-radius: 8px; background: rgba(255,255,255,0.1); border: 1px solid var(--glass-border); color: var(--text-primary);">
                    <option value="">All Time</option>
                    <option value="1">Last Hour</option>
                    <option value="24">Last 24 Hours</option>
                    <option value="168">Last 7 Days</option>
                    <option value="720">Last 30 Days</option>
                </select>
            </div>
            
            <button class="btn btn-small" onclick="exportActivity()" style="width: 100%;">
                <i class="fas fa-download"></i> Export Activity
            </button>
        </div>
        
        <div style="background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); border-radius: 16px; padding: 25px;">
            <h3 style="color: var(--text-primary); margin-bottom: 20px;">
                <i class="fas fa-cog" style="margin-right: 10px;"></i>
                Tracking Settings
            </h3>
            
            <div style="margin-bottom: 15px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" id="track-file-edits" checked onchange="updateTrackingSettings()">
                    <span style="color: var(--text-secondary);">Track file edits</span>
                </label>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" id="track-preview-loads" checked onchange="updateTrackingSettings()">
                    <span style="color: var(--text-secondary);">Track preview loads</span>
                </label>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" id="track-debug-sessions" checked onchange="updateTrackingSettings()">
                    <span style="color: var(--text-secondary);">Track debug sessions</span>
                </label>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" id="auto-cleanup" onchange="updateTrackingSettings()">
                    <span style="color: var(--text-secondary);">Auto-cleanup old records (30 days)</span>
                </label>
            </div>
            
            <button class="btn btn-small" onclick="testTracking()" style="width: 100%; margin-top: 15px;">
                <i class="fas fa-vial"></i> Test Tracking
            </button>
        </div>
    </div>
</div>

<script>
let activityData = <?php echo json_encode($activities); ?>;

function refreshActivity() {
    loadActivityData();
}

function clearActivity() {
    if (confirm('Are you sure you want to clear all activity records? This cannot be undone.')) {
        fetch('tools.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=clear_activity&project_id=<?php echo $project_id; ?>`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('activity-timeline').innerHTML = `
                    <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
                        <i class="fas fa-trash" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
                        <p>Activity cleared successfully</p>
                    </div>
                `;
            }
        });
    }
}

function filterActivities() {
    const typeFilter = document.getElementById('activity-filter').value;
    const timeFilter = document.getElementById('time-filter').value;
    
    let filtered = activityData.filter(activity => {
        let matchType = !typeFilter || activity.activity_type === typeFilter;
        let matchTime = true;
        
        if (timeFilter) {
            const activityTime = new Date(activity.created_at).getTime();
            const cutoffTime = Date.now() - (parseInt(timeFilter) * 60 * 60 * 1000);
            matchTime = activityTime >= cutoffTime;
        }
        
        return matchType && matchTime;
    });
    
    updateActivityDisplay(filtered);
}

function updateActivityDisplay(activities) {
    const timeline = document.getElementById('activity-timeline');
    
    if (activities.length === 0) {
        timeline.innerHTML = `
            <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
                <i class="fas fa-search" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
                <p>No activities match your filters</p>
            </div>
        `;
        return;
    }
    
    timeline.innerHTML = activities.map(activity => {
        const icon = getActivityIcon(activity.activity_type);
        const data = activity.activity_data ? JSON.parse(activity.activity_data) : null;
        
        return `
            <div class="activity-item">
                <div class="activity-icon">
                    <i class="${icon}"></i>
                </div>
                <div class="activity-content">
                    <div class="activity-type">${ucfirst(activity.activity_type.replace('_', ' '))}</div>
                    <div class="activity-time">${new Date(activity.created_at).toLocaleString()}</div>
                    ${data ? `<div class="activity-details">${formatActivityData(data)}</div>` : ''}
                </div>
            </div>
        `;
    }).join('');
}

function getActivityIcon(type) {
    const icons = {
        'file_edit': 'fas fa-edit',
        'file_create': 'fas fa-plus',
        'file_delete': 'fas fa-trash',
        'preview_load': 'fas fa-eye',
        'project_save': 'fas fa-save',
        'debug': 'fas fa-bug',
        'test': 'fas fa-flask'
    };
    return icons[type] || 'fas fa-circle';
}

function formatActivityData(data) {
    if (typeof data === 'object') {
        return Object.entries(data)
            .map(([key, value]) => `<strong>${ucfirst(key)}:</strong> ${Array.isArray(value) ? JSON.stringify(value) : value}`)
            .join('<br>');
    }
    return data;
}

function exportActivity() {
    const dataStr = JSON.stringify(activityData, null, 2);
    const dataUri = 'data:application/json;charset=utf-8,'+ encodeURIComponent(dataStr);
    
    const exportFileDefaultName = `activity_${new Date().toISOString().split('T')[0]}.json`;
    
    const linkElement = document.createElement('a');
    linkElement.setAttribute('href', dataUri);
    linkElement.setAttribute('download', exportFileDefaultName);
    linkElement.click();
    
    trackActivity('activity_export', { timestamp: Date.now() });
}

function updateTrackingSettings() {
    const settings = {
        track_file_edits: document.getElementById('track-file-edits').checked,
        track_preview_loads: document.getElementById('track-preview-loads').checked,
        track_debug_sessions: document.getElementById('track-debug-sessions').checked,
        auto_cleanup: document.getElementById('auto-cleanup').checked
    };
    
    localStorage.setItem('tracking_settings', JSON.stringify(settings));
    trackActivity('settings_update', settings);
}

function testTracking() {
    trackActivity('test_tracking', { 
        message: 'Manual test triggered',
        timestamp: Date.now(),
        random: Math.random()
    });
    
    setTimeout(() => {
        loadActivityData();
        alert('Test activity tracked! Refresh to see it in the timeline.');
    }, 500);
}

function ucfirst(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

// Load tracking settings on page load
const savedSettings = localStorage.getItem('tracking_settings');
if (savedSettings) {
    const settings = JSON.parse(savedSettings);
    document.getElementById('track-file-edits').checked = settings.track_file_edits;
    document.getElementById('track-preview-loads').checked = settings.track_preview_loads;
    document.getElementById('track-debug-sessions').checked = settings.track_debug_sessions;
    document.getElementById('auto-cleanup').checked = settings.auto_cleanup;
}

// Auto-refresh activity every 30 seconds
setInterval(() => {
    if (document.visibilityState === 'visible') {
        loadActivityData();
    }
}, 30000);

// Track page view
trackActivity('activity_page_view', { timestamp: Date.now() });
</script>
