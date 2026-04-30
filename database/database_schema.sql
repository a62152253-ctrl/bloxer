-- =============================================
-- BLOXER PLATFORM DATABASE SCHEMA
-- =============================================

-- Extend existing users table for platform features
ALTER TABLE users ADD COLUMN bio TEXT NULL;
ALTER TABLE users ADD COLUMN avatar_url VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN social_links JSON NULL;
ALTER TABLE users ADD COLUMN developer_rating DECIMAL(3,2) DEFAULT 0.00;
ALTER TABLE users ADD COLUMN total_apps INT DEFAULT 0;
ALTER TABLE users ADD COLUMN total_earnings DECIMAL(10,2) DEFAULT 0.00;

-- Projects table - Developer workspace projects
CREATE TABLE projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    slug VARCHAR(100) UNIQUE NOT NULL,
    framework VARCHAR(50) DEFAULT 'vanilla',
    status ENUM('draft', 'development', 'testing', 'published', 'archived') DEFAULT 'draft',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_slug (slug)
);

-- Project files - Code files for each project
CREATE TABLE project_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_type ENUM('html', 'css', 'js', 'json', 'md', 'other') NOT NULL,
    content LONGTEXT NOT NULL,
    file_size INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_project_id (project_id),
    INDEX idx_file_path (project_id, file_path)
);

-- Apps table - Published applications in marketplace
CREATE TABLE apps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT NOT NULL,
    short_description VARCHAR(255),
    category VARCHAR(50) NOT NULL,
    tags JSON,
    thumbnail_url VARCHAR(255),
    screenshots JSON,
    demo_url VARCHAR(255),
    price DECIMAL(8,2) DEFAULT 0.00,
    is_free BOOLEAN DEFAULT TRUE,
    is_public BOOLEAN DEFAULT TRUE,
    download_count INT DEFAULT 0,
    view_count INT DEFAULT 0,
    rating DECIMAL(3,2) DEFAULT 0.00,
    rating_count INT DEFAULT 0,
    status ENUM('draft', 'published', 'featured', 'suspended') DEFAULT 'draft',
    published_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_project_id (project_id),
    INDEX idx_slug (slug),
    INDEX idx_category (category),
    INDEX idx_status (status),
    INDEX idx_rating (rating),
    INDEX idx_published (published_at),
    FULLTEXT idx_search (title, description, short_description)
);

-- App versions - Version control for published apps
CREATE TABLE app_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    app_id INT NOT NULL,
    version VARCHAR(20) NOT NULL,
    changelog TEXT,
    is_current BOOLEAN DEFAULT FALSE,
    download_url VARCHAR(255),
    file_size INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (app_id) REFERENCES apps(id) ON DELETE CASCADE,
    INDEX idx_app_id (app_id),
    INDEX idx_version (app_id, version)
);

-- User app library - Apps installed/saved by users
CREATE TABLE user_apps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    app_id INT NOT NULL,
    status ENUM('installed', 'saved', 'favorite') DEFAULT 'installed',
    installed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_used DATETIME NULL,
    usage_count INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (app_id) REFERENCES apps(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_app (user_id, app_id),
    INDEX idx_user_id (user_id),
    INDEX idx_app_id (app_id),
    INDEX idx_status (status)
);

-- App ratings and reviews
CREATE TABLE app_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    app_id INT NOT NULL,
    user_id INT NOT NULL,
    rating TINYINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    review TEXT,
    helpful_count INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (app_id) REFERENCES apps(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_app_review (app_id, user_id),
    INDEX idx_app_id (app_id),
    INDEX idx_user_id (user_id),
    INDEX idx_rating (rating),
    INDEX idx_created (created_at)
);

-- Marketplace offer and deal conversations
CREATE TABLE offers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    app_id INT NOT NULL,
    buyer_id INT NOT NULL,
    developer_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    phone_number VARCHAR(50) NULL,
    subject VARCHAR(255) NULL,
    status ENUM('pending', 'accepted', 'declined', 'closed') DEFAULT 'pending',
    transfer_notes TEXT NULL,
    transferred_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (app_id) REFERENCES apps(id) ON DELETE CASCADE,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (developer_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_app_id (app_id),
    INDEX idx_buyer_id (buyer_id),
    INDEX idx_developer_id (developer_id),
    INDEX idx_status (status)
);

CREATE TABLE offer_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    offer_id INT NOT NULL,
    sender_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (offer_id) REFERENCES offers(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_offer_id (offer_id),
    INDEX idx_sender_id (sender_id)
);

-- App analytics - Usage statistics
CREATE TABLE app_analytics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    app_id INT NOT NULL,
    date DATE NOT NULL,
    views INT DEFAULT 0,
    downloads INT DEFAULT 0,
    unique_users INT DEFAULT 0,
    avg_session_time INT DEFAULT 0,
    revenue DECIMAL(8,2) DEFAULT 0.00,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (app_id) REFERENCES apps(id) ON DELETE CASCADE,
    UNIQUE KEY unique_app_date (app_id, date),
    INDEX idx_app_id (app_id),
    INDEX idx_date (date)
);

-- Developer wallet and transactions
CREATE TABLE developer_wallets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    balance DECIMAL(10,2) DEFAULT 0.00,
    total_earned DECIMAL(10,2) DEFAULT 0.00,
    total_withdrawn DECIMAL(10,2) DEFAULT 0.00,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user (user_id)
);

CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    app_id INT,
    type ENUM('sale', 'withdrawal', 'refund', 'bonus') NOT NULL,
    amount DECIMAL(8,2) NOT NULL,
    fee DECIMAL(8,2) DEFAULT 0.00,
    description VARCHAR(255),
    status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (app_id) REFERENCES apps(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_app_id (app_id),
    INDEX idx_type (type),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
);

-- Templates and snippets
CREATE TABLE templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    category VARCHAR(50) NOT NULL,
    framework VARCHAR(50) DEFAULT 'vanilla',
    thumbnail_url VARCHAR(255),
    is_official BOOLEAN DEFAULT FALSE,
    is_public BOOLEAN DEFAULT TRUE,
    usage_count INT DEFAULT 0,
    rating DECIMAL(3,2) DEFAULT 0.00,
    created_by INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_category (category),
    INDEX idx_framework (framework),
    INDEX idx_public (is_public),
    FULLTEXT idx_search (name, description)
);

CREATE TABLE template_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_type ENUM('html', 'css', 'js', 'json', 'md', 'other') NOT NULL,
    content LONGTEXT NOT NULL,
    FOREIGN KEY (template_id) REFERENCES templates(id) ON DELETE CASCADE,
    INDEX idx_template_id (template_id)
);

-- Categories for apps and templates
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    slug VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    icon VARCHAR(50),
    parent_id INT NULL,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_parent (parent_id),
    INDEX idx_active (is_active),
    INDEX idx_sort (sort_order)
);

-- Insert default categories
INSERT INTO categories (name, slug, description, icon, sort_order) VALUES
('Games', 'games', 'Interactive games and entertainment apps', 'gamepad', 1),
('Productivity', 'productivity', 'Tools for productivity and work', 'briefcase', 2),
('Social', 'social', 'Social networking and communication apps', 'users', 3),
('Education', 'education', 'Educational and learning applications', 'graduation-cap', 4),
('Entertainment', 'entertainment', 'Entertainment and media apps', 'play-circle', 5),
('Utilities', 'utilities', 'Utility tools and system apps', 'wrench', 6),
('Business', 'business', 'Business and professional applications', 'building', 7),
('Creative', 'creative', 'Creative and design tools', 'palette', 8),
('Health & Fitness', 'health-fitness', 'Health, fitness and wellness apps', 'heart', 9),
('Finance', 'finance', 'Financial and money management apps', 'dollar-sign', 10);

-- System settings
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    key_name VARCHAR(100) UNIQUE NOT NULL,
    value TEXT,
    description TEXT,
    type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    is_public BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (key_name),
    INDEX idx_public (is_public)
);

-- Insert default settings
INSERT INTO settings (key_name, value, description, type, is_public) VALUES
('platform_name', 'Bloxer', 'Platform name', 'string', true),
('platform_description', 'Create, share, and discover amazing web applications', 'Platform description', 'string', true),
('max_app_size', '10485760', 'Maximum app size in bytes (10MB)', 'number', false),
('developer_fee_rate', '0.15', 'Platform fee rate (15%)', 'number', false),
('min_withdrawal', '50.00', 'Minimum withdrawal amount', 'number', false),
('enable_marketplace', 'true', 'Enable marketplace feature', 'boolean', true),
('enable_analytics', 'true', 'Enable analytics tracking', 'boolean', false);
