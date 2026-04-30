-- Ratings and Reviews System Schema

-- Table for app ratings and reviews
CREATE TABLE IF NOT EXISTS app_ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    app_id INT NOT NULL,
    user_id INT NOT NULL,
    rating TINYINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    review TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (app_id) REFERENCES apps(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_app_rating (app_id, user_id),
    INDEX idx_app_rating (app_id),
    INDEX idx_user_rating (user_id),
    INDEX idx_created_at (created_at)
);

-- Add helpful rating breakdown view
CREATE VIEW IF NOT EXISTS app_rating_stats AS
SELECT 
    app_id,
    COUNT(*) as total_ratings,
    AVG(rating) as average_rating,
    SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
    SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
    SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
    SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
    SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
FROM app_ratings
GROUP BY app_id;

-- Table for helpful votes on reviews
CREATE TABLE IF NOT EXISTS review_helpful_votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rating_id INT NOT NULL,
    user_id INT NOT NULL,
    is_helpful BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rating_id) REFERENCES app_ratings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_rating_vote (rating_id, user_id)
);

-- Add rating_count column to apps table if it doesn't exist
ALTER TABLE apps 
ADD COLUMN IF NOT EXISTS rating_count INT DEFAULT 0 AFTER rating;

-- Trigger to update rating count
DELIMITER //
CREATE TRIGGER IF NOT EXISTS update_rating_count_after_insert
AFTER INSERT ON app_ratings
FOR EACH ROW
BEGIN
    UPDATE apps 
    SET rating_count = (
        SELECT COUNT(*) FROM app_ratings WHERE app_id = NEW.app_id
    )
    WHERE id = NEW.app_id;
END//

CREATE TRIGGER IF NOT EXISTS update_rating_count_after_delete
AFTER DELETE ON app_ratings
FOR EACH ROW
BEGIN
    UPDATE apps 
    SET rating_count = (
        SELECT COUNT(*) FROM app_ratings WHERE app_id = OLD.app_id
    )
    WHERE id = OLD.app_id;
END//
DELIMITER ;
