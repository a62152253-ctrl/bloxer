-- Create user_preferences table for storing user settings
CREATE TABLE user_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    theme VARCHAR(20) DEFAULT 'dark',
    language VARCHAR(10) DEFAULT 'pl',
    email_notifications TINYINT(1) DEFAULT 1,
    push_notifications TINYINT(1) DEFAULT 0,
    auto_save TINYINT(1) DEFAULT 1,
    hot_reload TINYINT(1) DEFAULT 1,
    show_line_numbers TINYINT(1) DEFAULT 1,
    word_wrap TINYINT(1) DEFAULT 1,
    font_size INT DEFAULT 14,
    debug_mode TINYINT(1) DEFAULT 0,
    show_fps TINYINT(1) DEFAULT 0,
    custom_js TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_preferences (user_id)
);
