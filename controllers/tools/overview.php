<?php
// Overview page content
// Ensure $current_project is always defined
if (!isset($current_project)) {
    $current_project = null;
}
// Ensure $analytics_data is always defined
if (!isset($analytics_data)) {
    $analytics_data = ['activity_summary' => [], 'visitor_summary' => []];
}
?>

<div class="overview-grid">
    <div class="overview-card">
        <h3>
            <div class="card-icon">
                <i class="fas fa-folder"></i>
            </div>
            Project Overview
        </h3>
        <div class="card-value"><?php echo htmlspecialchars($current_project['name'] ?? 'No Project Selected'); ?></div>
        <div class="card-label">
            <?php 
            if ($current_project) {
                echo $current_project['file_count'] . ' files • ' . ucfirst($current_project['framework']);
            } else {
                echo 'Select a project to view details';
            }
            ?>
        </div>
    </div>
    
    <div class="overview-card">
        <h3>
            <div class="card-icon">
                <i class="fas fa-clock"></i>
            </div>
            Last Activity
        </h3>
        <div class="card-value">
            <?php 
            if ($current_project) {
                echo date('M j', strtotime($current_project['updated_at']));
            } else {
                echo '--';
            }
            ?>
        </div>
        <div class="card-label">Project last updated</div>
    </div>
    
    <div class="overview-card">
        <h3>
            <div class="card-icon">
                <i class="fas fa-eye"></i>
            </div>
            Live Preview
        </h3>
        <div class="card-value">Ready</div>
        <div class="card-label">
            <?php 
            if ($current_project) {
                echo '<button class="btn btn-small" onclick="navigateToPage(\'preview\')">Open Preview</button>';
            } else {
                echo 'Select project first';
            }
            ?>
        </div>
    </div>
    
    <div class="overview-card">
        <h3>
            <div class="card-icon">
                <i class="fas fa-history"></i>
            </div>
            Activity Tracking
        </h3>
        <div class="card-value">
            <?php 
            if ($current_project) {
                $total_activities = array_sum(array_column($analytics_data['activity_summary'] ?? [], 'count'));
                echo $total_activities;
            } else {
                echo '0';
            }
            ?>
        </div>
        <div class="card-label">Activities in last 7 days</div>
    </div>
    
    <div class="overview-card">
        <h3>
            <div class="card-icon">
                <i class="fas fa-users"></i>
            </div>
            Visitor Monitor
        </h3>
        <div class="card-value">
            <?php 
            if ($current_project) {
                $total_visitors = array_sum(array_column($analytics_data['visitor_summary'] ?? [], 'visitors'));
                echo $total_visitors;
            } else {
                echo '0';
            }
            ?>
        </div>
        <div class="card-label">Visitors in last 7 days</div>
    </div>
    
    <div class="overview-card">
        <h3>
            <div class="card-icon">
                <i class="fas fa-tachometer-alt"></i>
            </div>
            Performance
        </h3>
        <div class="card-value">Good</div>
        <div class="card-label">
            <?php 
            if ($current_project) {
                echo '<button class="btn btn-small" onclick="navigateToPage(\'performance\')">View Details</button>';
            } else {
                echo 'Select project first';
            }
            ?>
        </div>
    </div>
</div>

<?php if ($current_project): ?>
    <div class="overview-grid">
        <div class="overview-card" style="grid-column: span 2;">
            <h3>
                <div class="card-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                Recent Activity Summary
            </h3>
            <div style="margin-top: 20px;">
                <?php if (!empty($analytics_data['activity_summary'])): ?>
                    <?php foreach ($analytics_data['activity_summary'] as $activity): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; padding: 10px; background: rgba(255,255,255,0.05); border-radius: 8px;">
                            <span style="color: var(--text-secondary);"><?php echo ucfirst($activity['activity_type']); ?></span>
                            <span style="font-weight: bold; color: var(--accent);"><?php echo $activity['count']; ?> actions</span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: var(--text-secondary); text-align: center; padding: 20px;">No activity recorded yet</p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="overview-card">
            <h3>
                <div class="card-icon">
                    <i class="fas fa-calendar"></i>
                </div>
                Quick Actions
            </h3>
            <div style="margin-top: 20px; display: flex; flex-direction: column; gap: 10px;">
                <button class="btn btn-small" onclick="navigateToPage('preview')" style="width: 100%;">
                    <i class="fas fa-eye"></i> Open Live Preview
                </button>
                <button class="btn btn-small" onclick="navigateToPage('activity')" style="width: 100%;">
                    <i class="fas fa-history"></i> View Activity Log
                </button>
                <button class="btn btn-small" onclick="navigateToPage('visitors')" style="width: 100%;">
                    <i class="fas fa-users"></i> Monitor Visitors
                </button>
                <button class="btn btn-small" onclick="window.location.href='../core/dashboard.php?page=workspace&project_id=<?php echo $project_id; ?>'" style="width: 100%;">
                    <i class="fas fa-code"></i> Open Editor
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>

<div style="margin-top: 30px; padding: 25px; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); border-radius: 16px;">
    <h3 style="color: var(--text-primary); margin-bottom: 15px;">
        <i class="fas fa-info-circle" style="margin-right: 10px;"></i>
        About Developer Tools
    </h3>
    <div style="color: var(--text-secondary); line-height: 1.6;">
        <p style="margin-bottom: 15px;">
            The Developer Tools panel provides comprehensive insights and controls for your projects:
        </p>
        <ul style="margin: 0; padding-left: 20px;">
            <li style="margin-bottom: 8px;"><strong>Live Preview:</strong> Real-time preview of your project with hot reload capabilities</li>
            <li style="margin-bottom: 8px;"><strong>Activity Tracking:</strong> Monitor all development activities and changes</li>
            <li style="margin-bottom: 8px;"><strong>Visitor Monitor:</strong> Track real-time visitors and their interactions</li>
            <li style="margin-bottom: 8px;"><strong>Performance Analytics:</strong> Analyze project performance and optimization opportunities</li>
        </ul>
    </div>
</div>
