-- Follow System for Developers
CREATE TABLE IF NOT EXISTS developer_follows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    follower_id INT NOT NULL,
    developer_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (developer_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_follow (follower_id, developer_id),
    INDEX idx_follower_id (follower_id),
    INDEX idx_developer_id (developer_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Developer Profiles Enhancement
CREATE TABLE IF NOT EXISTS developer_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    bio TEXT NOT NULL,
    website VARCHAR(255) NULL,
    github_url VARCHAR(255) NULL,
    twitter_url VARCHAR(255) NULL,
    linkedin_url VARCHAR(255) NULL,
    discord_url VARCHAR(255) NULL,
    youtube_url VARCHAR(255) NULL,
    company_name VARCHAR(255) NULL,
    location VARCHAR(255) NULL,
    avatar_url VARCHAR(255) NULL,
    banner_url VARCHAR(255) NULL,
    skills JSON NULL, -- Array of developer skills
    specialties JSON NULL, -- Array of developer specialties
    experience_years INT DEFAULT 0,
    total_apps INT DEFAULT 0,
    total_downloads INT DEFAULT 0,
    total_revenue DECIMAL(10,2) DEFAULT 0,
    is_verified BOOLEAN DEFAULT FALSE,
    is_featured BOOLEAN DEFAULT FALSE,
    featured_until DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_profile (user_id),
    INDEX idx_user_id (user_id),
    INDEX idx_is_verified (is_verified),
    INDEX idx_is_featured (is_featured),
    INDEX idx_total_downloads (total_downloads)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Developer Roadmap
CREATE TABLE IF NOT EXISTS developer_roadmaps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    developer_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    status ENUM('planned', 'in_progress', 'completed', 'cancelled') DEFAULT 'planned',
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    target_date DATE NULL,
    completed_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (developer_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_developer_id (developer_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_target_date (target_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Developer Changelog
CREATE TABLE IF NOT EXISTS developer_changelog (
    id INT AUTO_INCREMENT PRIMARY KEY,
    developer_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    type ENUM('announcement', 'update', 'milestone', 'feature', 'fix', 'other') DEFAULT 'announcement',
    version VARCHAR(50) NULL,
    related_app_id INT NULL,
    is_public BOOLEAN DEFAULT TRUE,
    is_featured BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (developer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (related_app_id) REFERENCES apps(id) ON DELETE SET NULL,
    INDEX idx_developer_id (developer_id),
    INDEX idx_type (type),
    INDEX idx_is_public (is_public),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Developer Subscriptions (Premium followers)
CREATE TABLE IF NOT EXISTS developer_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    follower_id INT NOT NULL,
    developer_id INT NOT NULL,
    tier ENUM('free', 'basic', 'premium', 'vip') DEFAULT 'free',
    monthly_price DECIMAL(10,2) DEFAULT 0,
    benefits JSON NULL, -- Array of subscription benefits
    starts_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    ends_at DATETIME NULL,
    is_active BOOLEAN DEFAULT TRUE,
    auto_renew BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (developer_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_subscription (follower_id, developer_id),
    INDEX idx_follower_id (follower_id),
    INDEX idx_developer_id (developer_id),
    INDEX idx_is_active (is_active),
    INDEX idx_ends_at (ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Developer Activity Feed
CREATE TABLE IF NOT EXISTS developer_activity_feed (
    id INT AUTO_INCREMENT PRIMARY KEY,
    developer_id INT NOT NULL,
    activity_type ENUM('app_published', 'app_updated', 'roadmap_added', 'changelog_posted', 'milestone_reached', 'subscriber_milestone') NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    related_id INT NULL, -- app_id, roadmap_id, changelog_id, etc.
    related_type VARCHAR(50) NULL,
    metadata JSON NULL,
    is_public BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (developer_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_developer_id (developer_id),
    INDEX idx_activity_type (activity_type),
    INDEX idx_is_public (is_public),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User Following Feed (For followers to see developer updates)
CREATE TABLE IF NOT EXISTS user_follow_feed (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    developer_id INT NOT NULL,
    activity_id INT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (developer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (activity_id) REFERENCES developer_activity_feed(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_activity (user_id, activity_id),
    INDEX idx_user_id (user_id),
    INDEX idx_developer_id (developer_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Developer Analytics
CREATE TABLE IF NOT EXISTS developer_analytics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    developer_id INT NOT NULL,
    date DATE NOT NULL,
    followers_count INT DEFAULT 0,
    new_followers INT DEFAULT 0,
    app_views INT DEFAULT 0,
    app_downloads INT DEFAULT 0,
    revenue DECIMAL(10,2) DEFAULT 0,
    engagement_rate DECIMAL(5,2) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (developer_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_developer_date (developer_id, date),
    INDEX idx_developer_id (developer_id),
    INDEX idx_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
