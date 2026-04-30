-- Create missing tables for Bloxer platform
-- Run this script to fix the database errors

-- Categories table (if not exists)
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT NULL,
    icon VARCHAR(50) NULL,
    color VARCHAR(7) NULL, -- hex color code
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_slug (slug),
    INDEX idx_is_active (is_active),
    INDEX idx_sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default categories
INSERT IGNORE INTO categories (name, slug, description, icon, color, sort_order) VALUES
('Web Apps', 'web-apps', 'Web applications and tools', 'fa-globe', '#3B82F6', 1),
('Games', 'games', 'Browser-based games', 'fa-gamepad', '#EF4444', 2),
('Productivity', 'productivity', 'Productivity tools and utilities', 'fa-check-circle', '#10B981', 3),
('Social', 'social', 'Social networking and communication', 'fa-users', '#8B5CF6', 4),
('Education', 'education', 'Educational tools and learning platforms', 'fa-graduation-cap', '#F59E0B', 5),
('Entertainment', 'entertainment', 'Entertainment and media apps', 'fa-play', '#EC4899', 6),
('Business', 'business', 'Business and professional tools', 'fa-briefcase', '#6366F1', 7),
('Development', 'development', 'Development tools and code editors', 'fa-code', '#14B8A6', 8),
('Design', 'design', 'Design and creative tools', 'fa-palette', '#F97316', 9),
('Utilities', 'utilities', 'General utilities and helpers', 'fa-tools', '#6B7280', 10);

-- Notifications table (if not exists)
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('offer', 'message', 'review', 'update', 'system', 'mention', 'like', 'comment', 'follow') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    related_id INT NULL, -- ID of related item (offer_id, app_id, review_id, etc.)
    related_type VARCHAR(50) NULL, -- Type of related item (offer, app, review, etc.)
    is_read BOOLEAN DEFAULT FALSE,
    is_important BOOLEAN DEFAULT FALSE,
    action_url VARCHAR(255) NULL, -- URL to redirect when clicked
    action_text VARCHAR(100) NULL, -- Text for action button
    icon VARCHAR(50) NULL, -- Font Awesome icon class
    metadata JSON NULL, -- Additional data
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_type (type),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at),
    INDEX idx_user_unread (user_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notification preferences for users (if not exists)
CREATE TABLE IF NOT EXISTS notification_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    offer_notifications BOOLEAN DEFAULT TRUE,
    message_notifications BOOLEAN DEFAULT TRUE,
    review_notifications BOOLEAN DEFAULT TRUE,
    update_notifications BOOLEAN DEFAULT TRUE,
    system_notifications BOOLEAN DEFAULT TRUE,
    email_notifications BOOLEAN DEFAULT FALSE,
    push_notifications BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_preferences (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add missing download_count column to app_versions table
ALTER TABLE app_versions ADD COLUMN IF NOT EXISTS download_count INT DEFAULT 0 AFTER file_size;

-- Create app_versions table if it doesn't exist (with download_count)
CREATE TABLE IF NOT EXISTS app_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    app_id INT NOT NULL,
    version VARCHAR(20) NOT NULL,
    changelog TEXT,
    is_current BOOLEAN DEFAULT FALSE,
    download_url VARCHAR(255),
    file_size INT DEFAULT 0,
    download_count INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (app_id) REFERENCES apps(id) ON DELETE CASCADE,
    INDEX idx_app_id (app_id),
    INDEX idx_version (app_id, version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User activity tracking for Developer Tools
CREATE TABLE IF NOT EXISTS user_activity (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    project_id INT NOT NULL,
    activity_type ENUM('file_create', 'file_edit', 'file_delete', 'project_open', 'preview_open', 'build', 'deploy') NOT NULL,
    activity_data JSON NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_project_id (project_id),
    INDEX idx_activity_type (activity_type),
    INDEX idx_created_at (created_at),
    INDEX idx_project_activity (project_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Visitor tracking for Developer Tools
CREATE TABLE IF NOT EXISTS visitor_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    project_id INT NOT NULL,
    visitor_ip VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    page_url VARCHAR(255) NOT NULL,
    visitor_data JSON NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_project_id (project_id),
    INDEX idx_visitor_ip (visitor_ip),
    INDEX idx_created_at (created_at),
    INDEX idx_project_visitors (project_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
