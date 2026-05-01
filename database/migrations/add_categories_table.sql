-- Add missing categories table
-- Migration script to create categories table with default data

CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT NULL,
    icon VARCHAR(50) NULL,
    color VARCHAR(7) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_slug (slug),
    INDEX idx_is_active (is_active),
    INDEX idx_sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default categories if they don't exist
INSERT IGNORE INTO categories (name, slug, description, icon, color, sort_order) VALUES
('Games', 'games', 'Fun and interactive games', 'gamepad', '#FF6B6B', 1),
('Productivity', 'productivity', 'Tools to boost your productivity', 'tasks', '#4ECDC4', 2),
('Social', 'social', 'Social networking and communication', 'users', '#45B7D1', 3),
('Entertainment', 'entertainment', 'Entertainment and media apps', 'film', '#96CEB4', 4),
('Education', 'education', 'Learning and educational tools', 'graduation-cap', '#FFEAA7', 5),
('Business', 'business', 'Business and professional tools', 'briefcase', '#DDA0DD', 6),
('Utilities', 'utilities', 'Useful utilities and tools', 'wrench', '#74B9FF', 7),
('Lifestyle', 'lifestyle', 'Lifestyle and personal apps', 'heart', '#FD79A8', 8);
