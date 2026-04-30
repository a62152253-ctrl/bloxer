-- Personalized Recommendation System
CREATE TABLE IF NOT EXISTS user_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    preferred_categories JSON NOT NULL, -- Array of category slugs user prefers
    disliked_categories JSON NOT NULL, -- Array of category slugs user dislikes
    preferred_tags JSON NOT NULL, -- Array of tags user likes
    app_preferences JSON NOT NULL, -- {app_id: rating, install_date, usage_count}
    interaction_history JSON NOT NULL, -- {app_id: type, timestamp, data}
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_preferences (user_id),
    INDEX idx_user_id (user_id),
    INDEX idx_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User behavior tracking
CREATE TABLE IF NOT EXISTS user_behavior (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_id VARCHAR(255) NOT NULL,
    event_type ENUM('view_app', 'install_app', 'uninstall_app', 'rate_app', 'review_app', 'search_query', 'category_click', 'tag_click', 'app_open', 'session_start', 'session_end') NOT NULL,
    app_id INT NULL,
    category_id INT NULL,
    tag_id INT NULL,
    search_query VARCHAR(255) NULL,
    page_url VARCHAR(255) NULL,
    time_spent INT NULL, -- seconds
    metadata JSON NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_event_type (event_type),
    INDEX idx_app_id (app_id),
    INDEX idx_created_at (created_at),
    INDEX idx_session_id (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recommendation scores cache
CREATE TABLE IF NOT EXISTS recommendation_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    app_id INT NOT NULL,
    score DECIMAL(5,3) NOT NULL,
    score_factors JSON NOT NULL, -- {category_match: 0.3, popularity: 0.2, rating: 0.3, recent_views: 0.2}
    last_calculated DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (app_id) REFERENCES apps(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_app (user_id, app_id),
    INDEX idx_user_id (user_id),
    INDEX idx_score (score),
    INDEX idx_expires_at (expires_at),
    INDEX idx_last_calculated (last_calculated)
) ENGINE=CREATE TABLE DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Popular apps cache (updated daily)
CREATE TABLE IF NOT EXISTS popular_apps (
    app_id INT PRIMARY KEY,
    total_installs INT DEFAULT 0,
    recent_installs INT DEFAULT 0,
    avg_rating DECIMAL(3,2) DEFAULT 0,
    total_ratings INT DEFAULT 0,
    popularity_score DECIMAL(5,3) DEFAULT 0,
    last_updated DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (app_id) REFERENCES apps(id) ON DELETE CASCADE,
    INDEX idx_popularity_score (popularity_score),
    INDEX idx_last_updated (last_updated)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Category trends cache
CREATE TABLE IF NOT EXISTS category_trends (
    category_id INT PRIMARY KEY,
    total_apps INT DEFAULT 0,
    recent_installs INT DEFAULT 0,
    growth_rate DECIMAL(5,2) DEFAULT 0,
    trending_score DECIMAL(5,3) DEFAULT 0,
    last_updated DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    INDEX idx_trending_score (trending_score),
    INDEX idx_growth_rate (growth_rate),
    INDEX idx_last_updated (last_updated)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recommendation clicks tracking
CREATE TABLE IF NOT EXISTS recommendation_clicks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    app_id INT NOT NULL,
    recommendation_type ENUM('personalized', 'popular', 'trending', 'similar', 'new') DEFAULT 'personalized',
    position INT NOT NULL, -- Position in recommendation list (1-based)
    clicked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) on DELETE CASCADE,
    FOREIGN KEY (app_id) REFERENCES apps(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_app_id (app_id),
    INDEX idx_clicked_at (clicked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
