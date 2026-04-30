<?php
// This file is included by admin.php
$conn = $auth->getConnection();

// Get reports with pagination and filtering
$page = max(1, intval($_GET['report_page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;
$status_filter = $_GET['status'] ?? '';
$type_filter = $_GET['type'] ?? '';

$where_clauses = ["1=1"];
$params = [];

if ($status_filter) {
    $where_clauses[] = "r.status = ?";
    $params[] = $status_filter;
}

if ($type_filter) {
    $where_clauses[] = "r.reported_type = ?";
    $params[] = $type_filter;
}

$where_sql = "WHERE " . implode(' AND ', $where_clauses);

// Get reports
$stmt = $conn->prepare("
    SELECT r.*, u1.username as reporter_name,
           CASE r.reported_type
               WHEN 'app' THEN (SELECT title FROM apps WHERE id = r.reported_id)
               WHEN 'review' THEN (SELECT CONCAT('Review by ', u2.username, ' for ', a.title) FROM app_reviews ar JOIN users u2 ON ar.user_id = u2.id JOIN apps a ON ar.app_id = a.id WHERE ar.id = r.reported_id)
               WHEN 'user' THEN (SELECT username FROM users WHERE id = r.reported_id)
               ELSE 'Unknown'
           END as reported_item,
           u2.username as moderator_name
    FROM reports r
    JOIN users u1 ON r.reporter_id = u1.id
    LEFT JOIN users u2 ON r.moderator_id = u2.id
    $where_sql
    ORDER BY r.created_at DESC
    LIMIT ? OFFSET ?
");
$all_params = array_merge($params, [$per_page, $offset]);
$stmt->bind_param(str_repeat('i', count($all_params)), ...$all_params);
$stmt->execute();
$reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get total count for pagination
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM reports $where_sql");
$stmt->bind_param(str_repeat('s', count($params)), ...$params);
$stmt->execute();
$total_reports = $stmt->get_result()->fetch_row()[0];
$total_pages = ceil($total_reports / $per_page);

// Get report details for modal
$report_id = $_GET['id'] ?? null;
$report_details = null;

if ($report_id) {
    $stmt = $conn->prepare("
        SELECT r.*, u1.username as reporter_name,
               CASE r.reported_type
                   WHEN 'app' THEN (SELECT title FROM apps WHERE id = r.reported_id)
                   WHEN 'review' THEN (SELECT CONCAT('Review by ', u2.username, ' for ', a.title) FROM app_reviews ar JOIN users u2 ON ar.user_id = u2.id JOIN apps a ON ar.app_id = a.id WHERE ar.id = r.reported_id)
                   WHEN 'user' THEN (SELECT username FROM users WHERE id = r.reported_id)
                   ELSE 'Unknown'
               END as reported_item,
               u2.username as moderator_name
        FROM reports r
        JOIN users u1 ON r.reporter_id = u1.id
        LEFT JOIN users u2 ON r.moderator_id = u2.id
        WHERE r.id = ?
    ");
    $stmt->bind_param("i", $report_id);
    $stmt->execute();
    $report_details = $stmt->get_result()->fetch_assoc();
}
?>

<div class="card">
    <div class="card-header">
        <h2>Reports Management</h2>
        <div class="filters">
            <form method="GET" class="filter-form">
                <select name="status" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="under_review" <?php echo $status_filter === 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                    <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                    <option value="dismissed" <?php echo $status_filter === 'dismissed' ? 'selected' : ''; ?>>Dismissed</option>
                </select>
                
                <select name="type" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    <option value="app" <?php echo $type_filter === 'app' ? 'selected' : ''; ?>>Apps</option>
                    <option value="review" <?php echo $type_filter === 'review' ? 'selected' : ''; ?>>Reviews</option>
                    <option value="user" <?php echo $type_filter === 'user' ? 'selected' : ''; ?>>Users</option>
                </select>
            </form>
        </div>
    </div>
    
    <div class="reports-table">
        <?php if (!empty($reports)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Reported Item</th>
                        <th>Reason</th>
                        <th>Reporter</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $report): ?>
                        <tr>
                            <td>
                                <span class="type-badge <?php echo $report['reported_type']; ?>">
                                    <?php echo ucfirst($report['reported_type']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="reported-item">
                                    <strong><?php echo htmlspecialchars($report['reported_item']); ?></strong>
                                </div>
                            </td>
                            <td>
                                <span class="reason-badge <?php echo $report['reason']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $report['reason'])); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($report['reporter_name']); ?></td>
                            <td>
                                <span class="status-badge <?php echo $report['status']; ?>">
                                    <?php echo ucfirst($report['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y H:i', strtotime($report['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button onclick="viewReport(<?php echo $report['id']; ?>)" class="btn btn-small">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <?php if ($report['status'] === 'pending'): ?>
                                        <button onclick="reviewReport(<?php echo $report['id']; ?>)" class="btn btn-small btn-primary">
                                            <i class="fas fa-gavel"></i> Review
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=reports&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>&report_page=<?php echo $page - 1; ?>" class="pagination-link">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <span class="pagination-info">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?> (<?php echo $total_reports; ?> reports)
                    </span>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=reports&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>&report_page=<?php echo $page + 1; ?>" class="pagination-link">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-flag"></i>
                <h3>No reports found</h3>
                <p>There are no reports matching your criteria.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Report Review Modal -->
<div id="report-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Review Report</h2>
            <button onclick="closeModal()" class="btn-close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="review-form" method="POST" action="admin.php">
            <input type="hidden" name="action" value="resolve_report">
            <input type="hidden" name="report_id" id="modal-report-id">
            
            <div class="form-group">
                <label for="action_taken">Action Taken</label>
                <select id="action_taken" name="action_taken" required>
                    <option value="">Select action...</option>
                    <option value="none">No Action</option>
                    <option value="content_removed">Remove Content</option>
                    <option value="user_warned">Warn User</option>
                    <option value="user_suspended">Suspend User (7 days)</option>
                    <option value="user_banned">Ban User</option>
                    <option value="app_removed">Remove App</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="moderator_notes">Moderator Notes</label>
                <textarea id="moderator_notes" name="moderator_notes" rows="4" placeholder="Add notes about this moderation action..."></textarea>
            </div>
            
            <div class="modal-actions">
                <button type="submit" class="btn btn-primary">Resolve Report</button>
                <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Report Details Modal -->
<div id="details-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Report Details</h2>
            <button onclick="closeDetailsModal()" class="btn-close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="report-details">
            <div class="detail-section">
                <h3>Report Information</h3>
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>Type:</label>
                        <span id="detail-type"></span>
                    </div>
                    <div class="detail-item">
                        <label>Status:</label>
                        <span id="detail-status"></span>
                    </div>
                    <div class="detail-item">
                        <label>Reason:</label>
                        <span id="detail-reason"></span>
                    </div>
                    <div class="detail-item">
                        <label>Reported By:</label>
                        <span id="detail-reporter"></span>
                    </div>
                    <div class="detail-item">
                        <label>Date:</label>
                        <span id="detail-date"></span>
                    </div>
                </div>
            </div>
            
            <div class="detail-section">
                <h3>Description</h3>
                <div class="description-box">
                    <p id="detail-description"></p>
                </div>
            </div>
            
            <div class="detail-section">
                <h3>Moderation</h3>
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>Moderator:</label>
                        <span id="detail-moderator"></span>
                    </div>
                    <div class="detail-item">
                        <label>Action Taken:</label>
                        <span id="detail-action"></span>
                    </div>
                    <div class="detail-item">
                        <label>Resolved:</label>
                        <span id="detail-resolved"></span>
                    </div>
                </div>
                
                <div class="detail-section">
                    <h4>Moderator Notes</h4>
                    <div class="notes-box">
                        <p id="detail-notes"></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="modal-actions">
            <button onclick="closeDetailsModal()" class="btn btn-secondary">Close</button>
        </div>
    </div>
</div>

<style>
    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    
    .filters {
        display: flex;
        gap: 10px;
    }
    
    .filter-form {
        display: flex;
        gap: 10px;
    }
    
    .filter-form select {
        padding: 8px 12px;
        border: 1px solid var(--glass-border);
        border-radius: 6px;
        background: var(--input-bg);
        color: var(--text-primary);
    }
    
    .reports-table {
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-radius: 12px;
        overflow: hidden;
    }
    
    .reports-table table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .reports-table th {
        background: var(--input-bg);
        padding: 12px;
        text-align: left;
        font-weight: 600;
        color: var(--text-primary);
        border-bottom: 1px solid var(--glass-border);
    }
    
    .reports-table td {
        padding: 12px;
        border-bottom: 1px solid var(--glass-border);
        vertical-align: middle;
    }
    
    .type-badge, .reason-badge, .status-badge {
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 0.8em;
        font-weight: 500;
    }
    
    .type-badge.app { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }
    .type-badge.review { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
    .type-badge.user { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
    
    .reason-badge.inappropriate_content { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
    .reason-badge.spam { background: rgba(107, 114, 128, 0.1); color: #6b7280; }
    .reason-badge.harassment { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
    .reason-badge.copyright { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
    .reason-badge.fake_app { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
    .reason-badge.malware { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
    .reason-badge.other { background: rgba(107, 114, 128, 0.1); color: #6b7280; }
    
    .status-badge.pending { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
    .status-badge.under_review { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
    .status-badge.resolved { background: rgba(16, 185, 129, 0.1); color: #10b981; }
    .status-badge.dismissed { background: rgba(107, 114, 128, 0.1); color: #6b7280; }
    
    .reported-item {
        max-width: 200px;
    }
    
    .action-buttons {
        display: flex;
        gap: 5px;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--text-secondary);
    }
    
    .empty-state i {
        font-size: 3em;
        margin-bottom: 20px;
        opacity: 0.5;
    }
    
    .report-details {
        max-height: 500px;
        overflow-y: auto;
    }
    
    .detail-section {
        margin-bottom: 25px;
    }
    
    .detail-section h3 {
        margin-bottom: 15px;
        color: var(--text-primary);
        border-bottom: 1px solid var(--glass-border);
        padding-bottom: 8px;
    }
    
    .detail-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }
    
    .detail-item {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    
    .detail-item label {
        font-weight: 500;
        color: var(--text-secondary);
        font-size: 0.9em;
    }
    
    .detail-item span {
        color: var(--text-primary);
    }
    
    .description-box, .notes-box {
        background: var(--input-bg);
        padding: 15px;
        border-radius: 8px;
        line-height: 1.5;
        color: var(--text-secondary);
    }
    
    .modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
    }
    
    .modal-content {
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-radius: 16px;
        padding: 30px;
        max-width: 600px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
    }
    
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }
    
    .modal-header h2 {
        margin: 0;
        color: var(--text-primary);
    }
    
    .btn-close {
        background: none;
        border: none;
        color: var(--text-secondary);
        font-size: 1.2em;
        cursor: pointer;
        padding: 8px;
        border-radius: 6px;
    }
    
    .btn-close:hover {
        background: var(--input-bg);
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 8px;
        color: var(--text-primary);
        font-weight: 500;
    }
    
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 12px;
        border: 1px solid var(--glass-border);
        border-radius: 8px;
        background: var(--input-bg);
        color: var(--text-primary);
    }
    
    .modal-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 25px;
        padding-top: 20px;
        border-top: 1px solid var(--glass-border);
    }
    
    .pagination {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 20px;
        padding: 20px 0;
    }
    
    .pagination-link {
        color: var(--accent-color);
        text-decoration: none;
        padding: 8px 16px;
        border: 1px solid var(--accent-color);
        border-radius: 6px;
        transition: all 0.3s ease;
    }
    
    .pagination-link:hover {
        background: var(--accent-color);
        color: white;
    }
    
    .pagination-info {
        color: var(--text-secondary);
    }
</style>

<script>
function viewReport(reportId) {
    // Fetch report details via AJAX
    fetch(`admin.php?page=reports&id=${reportId}`)
        .then(response => response.text())
        .then(html => {
            // Parse the report details from the page
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            
            // Extract details
            const type = tempDiv.querySelector('#detail-type')?.textContent || '';
            const status = tempDiv.querySelector('#detail-status')?.textContent || '';
            const reason = tempDiv.querySelector('#detail-reason')?.textContent || '';
            const reporter = tempDiv.querySelector('#detail-reporter')?.textContent || '';
            const date = tempDiv.querySelector('#detail-date')?.textContent || '';
            const description = tempDiv.querySelector('#detail-description')?.textContent || '';
            const moderator = tempDiv.querySelector('#detail-moderator')?.textContent || 'Not yet';
            const action = tempDiv.querySelector('#detail-action')?.textContent || 'None';
            const resolved = tempDiv.querySelector('#detail-resolved')?.textContent || 'No';
            const notes = tempDiv.querySelector('#detail-notes')?.textContent || '';
            
            // Populate modal
            document.getElementById('detail-type').textContent = type;
            document.getElementById('detail-status').textContent = status;
            document.getElementById('detail-reason').textContent = reason;
            document.getElementById('detail-reporter').textContent = reporter;
            document.getElementById('detail-date').textContent = date;
            document.getElementById('detail-description').textContent = description;
            document.getElementById('detail-moderator').textContent = moderator;
            document.getElementById('detail-action').textContent = action;
            document.getElementById('detail-resolved').textContent = resolved;
            document.getElementById('detail-notes').textContent = notes;
            
            // Show modal
            document.getElementById('details-modal').style.display = 'flex';
        });
}

function reviewReport(reportId) {
    document.getElementById('modal-report-id').value = reportId;
    document.getElementById('report-modal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('report-modal').style.display = 'none';
}

function closeDetailsModal() {
    document.getElementById('details-modal').style.display = 'none';
}

// Close modals when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        closeModal();
        closeDetailsModal();
    }
}
</script>
