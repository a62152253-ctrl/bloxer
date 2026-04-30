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
