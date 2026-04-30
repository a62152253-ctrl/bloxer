-- Version Control System Schema

-- Table for file versions
CREATE TABLE IF NOT EXISTS file_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    version_number INT NOT NULL,
    content LONGTEXT,
    change_description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_project_file (project_id, file_path),
    INDEX idx_created_at (created_at)
);

-- Add triggers to automatically create versions when files are updated
DELIMITER //

CREATE TRIGGER IF NOT EXISTS create_file_version_after_update
AFTER UPDATE ON project_files
FOR EACH ROW
BEGIN
    -- Only create version if content actually changed
    IF OLD.content IS NULL OR NEW.content IS NULL OR OLD.content != NEW.content THEN
        INSERT INTO file_versions (project_id, file_path, version_number, content, change_description)
        SELECT NEW.project_id, NEW.file_path, 
               COALESCE((SELECT MAX(version_number) + 1 FROM file_versions 
                        WHERE project_id = NEW.project_id AND file_path = NEW.file_path), 1),
               NEW.content, 'Auto-saved version';
    END IF;
END//

DELIMITER ;

-- View for recent file versions
CREATE VIEW IF NOT EXISTS recent_file_versions AS
SELECT 
    fv.*,
    p.name as project_name,
    u.username as developer_name
FROM file_versions fv
JOIN projects p ON fv.project_id = p.id
JOIN users u ON p.user_id = u.id
ORDER BY fv.created_at DESC;
