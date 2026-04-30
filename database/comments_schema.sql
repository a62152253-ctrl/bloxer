-- Comments System Schema

-- Table for comments on projects and apps
CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    project_id INT NULL,
    app_id INT NULL,
    parent_id INT NULL DEFAULT 0,
    comment_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (app_id) REFERENCES apps(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE,
    INDEX idx_project_comments (project_id, parent_id, created_at),
    INDEX idx_app_comments (app_id, parent_id, created_at),
    INDEX idx_user_comments (user_id),
    CHECK ((project_id IS NOT NULL AND app_id IS NULL) OR (project_id IS NULL AND app_id IS NOT NULL))
);

-- Add comment count columns to projects and apps tables
ALTER TABLE projects 
ADD COLUMN IF NOT EXISTS comment_count INT DEFAULT 0 AFTER rating_count;

ALTER TABLE apps 
ADD COLUMN IF NOT EXISTS comment_count INT DEFAULT 0 AFTER rating_count;

-- Triggers to update comment counts
DELIMITER //

CREATE TRIGGER IF NOT EXISTS update_project_comment_count_after_insert
AFTER INSERT ON comments
FOR EACH ROW
BEGIN
    IF NEW.project_id IS NOT NULL THEN
        UPDATE projects 
        SET comment_count = (
            SELECT COUNT(*) FROM comments 
            WHERE project_id = NEW.project_id AND parent_id = 0
        )
        WHERE id = NEW.project_id;
    END IF;
END//

CREATE TRIGGER IF NOT EXISTS update_project_comment_count_after_delete
AFTER DELETE ON comments
FOR EACH ROW
BEGIN
    IF OLD.project_id IS NOT NULL THEN
        UPDATE projects 
        SET comment_count = (
            SELECT COUNT(*) FROM comments 
            WHERE project_id = OLD.project_id AND parent_id = 0
        )
        WHERE id = OLD.project_id;
    END IF;
END//

CREATE TRIGGER IF NOT EXISTS update_app_comment_count_after_insert
AFTER INSERT ON comments
FOR EACH ROW
BEGIN
    IF NEW.app_id IS NOT NULL THEN
        UPDATE apps 
        SET comment_count = (
            SELECT COUNT(*) FROM comments 
            WHERE app_id = NEW.app_id AND parent_id = 0
        )
        WHERE id = NEW.app_id;
    END IF;
END//

CREATE TRIGGER IF NOT EXISTS update_app_comment_count_after_delete
AFTER DELETE ON comments
FOR EACH ROW
BEGIN
    IF OLD.app_id IS NOT NULL THEN
        UPDATE apps 
        SET comment_count = (
            SELECT COUNT(*) FROM comments 
            WHERE app_id = OLD.app_id AND parent_id = 0
        )
        WHERE id = OLD.app_id;
    END IF;
END//

DELIMITER ;

-- View for recent comments with user info
CREATE VIEW IF NOT EXISTS recent_comments AS
SELECT 
    c.*,
    u.username,
    u.avatar_url,
    p.name as project_name,
    a.title as app_title,
    CASE 
        WHEN c.project_id IS NOT NULL THEN 'project'
        WHEN c.app_id IS NOT NULL THEN 'app'
    END as comment_type
FROM comments c
JOIN users u ON c.user_id = u.id
LEFT JOIN projects p ON c.project_id = p.id
LEFT JOIN apps a ON c.app_id = a.id
ORDER BY c.created_at DESC;
