-- User Activity Tracking Schema
-- This table tracks all user activities within projects for analytics and debugging

CREATE TABLE IF NOT EXISTS user_activity (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    project_id INT NOT NULL,
    activity_type VARCHAR(50) NOT NULL COMMENT 'Type of activity (file_edit, preview_load, etc.)',
    activity_data JSON COMMENT 'Additional activity data as JSON',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_user_project (user_id, project_id),
    INDEX idx_activity_type (activity_type),
    INDEX idx_created_at (created_at),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Visitor Tracking Schema
-- This table tracks visitor activity on published projects

CREATE TABLE IF NOT EXISTS visitor_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL COMMENT 'Project owner ID',
    project_id INT NOT NULL COMMENT 'Project being visited',
    visitor_ip VARCHAR(45) NOT NULL COMMENT 'Visitor IP address',
    user_agent TEXT COMMENT 'Visitor browser user agent',
    page_url VARCHAR(500) COMMENT 'Page being visited',
    referrer VARCHAR(500) COMMENT 'Referring page',
    visitor_data JSON COMMENT 'Additional visitor data as JSON',
    session_id VARCHAR(100) COMMENT 'Visitor session identifier',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_project_visitor (project_id, visitor_ip),
    INDEX idx_visitor_ip (visitor_ip),
    INDEX idx_created_at (created_at),
    INDEX idx_session_id (session_id),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Performance Metrics Schema
-- This table stores performance metrics for projects

CREATE TABLE IF NOT EXISTS performance_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    metric_type VARCHAR(50) NOT NULL COMMENT 'Type of metric (load_time, memory_usage, etc.)',
    metric_value DECIMAL(10,2) NOT NULL COMMENT 'Metric value',
    metric_unit VARCHAR(20) COMMENT 'Unit of measurement (ms, MB, etc.)',
    test_data JSON COMMENT 'Additional test data as JSON',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_project_metric (project_id, metric_type),
    INDEX idx_created_at (created_at),
    
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert some sample activity data for testing
INSERT IGNORE INTO user_activity (user_id, project_id, activity_type, activity_data) VALUES
(1, 1, 'file_edit', '{"file": "index.html", "changes": 5}'),
(1, 1, 'preview_load', '{"timestamp": ' + UNIX_TIMESTAMP() + '}'),
(1, 1, 'project_save', '{"auto_save": true}');

-- Insert some sample visitor data for testing
INSERT IGNORE INTO visitor_tracking (user_id, project_id, visitor_ip, user_agent, page_url, visitor_data) VALUES
(1, 1, '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', '/index.html', '{"browser": "Chrome", "os": "Windows"}'),
(1, 1, '192.168.1.101', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36', '/index.html', '{"browser": "Safari", "os": "macOS"}');
