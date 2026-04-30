-- Chat/Deals System Schema

-- Table for chat messages and deals
CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    recipient_id INT NOT NULL,
    project_id INT NULL,
    app_id INT NULL,
    message_text TEXT NOT NULL,
    message_type ENUM('message', 'offer', 'deal_request') DEFAULT 'message',
    offer_amount DECIMAL(10,2) DEFAULT 0,
    status ENUM('pending', 'accepted', 'rejected', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (app_id) REFERENCES apps(id) ON DELETE CASCADE,
    INDEX idx_conversation (sender_id, recipient_id, created_at),
    INDEX idx_sender (sender_id, created_at),
    INDEX idx_recipient (recipient_id, created_at),
    CHECK ((project_id IS NOT NULL AND app_id IS NULL) OR (project_id IS NULL AND app_id IS NOT NULL))
);

-- Table for chat notifications
CREATE TABLE IF NOT EXISTS chat_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message_id INT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (message_id) REFERENCES chat_messages(id) ON DELETE CASCADE,
    INDEX idx_user_unread (user_id, is_read, created_at)
);

-- Triggers for notifications
DELIMITER //

CREATE TRIGGER IF NOT EXISTS create_chat_notification
AFTER INSERT ON chat_messages
FOR EACH ROW
BEGIN
    INSERT INTO chat_notifications (user_id, message_id, is_read)
    VALUES (NEW.recipient_id, NEW.id, FALSE);
END//

DELIMITER ;

-- Views for recent conversations and unread counts
CREATE VIEW IF NOT EXISTS conversation_list AS
SELECT 
    DISTINCT 
    CASE 
        WHEN cm.sender_id = cm.recipient_id THEN cm.recipient_id 
        ELSE cm.sender_id 
    END as user_id,
    CASE 
        WHEN cm.sender_id = cm.recipient_id THEN cm.sender_id 
        ELSE cm.recipient_id 
    END as other_user_id,
    u.username as other_username,
    u.avatar_url as other_avatar,
    MAX(cm.created_at) as last_message_time,
    COUNT(CASE WHEN cn.is_read = FALSE THEN 1 END) as unread_count,
    (SELECT message_text FROM chat_messages cm2 
     WHERE ((cm2.sender_id = cm.sender_id AND cm2.recipient_id = cm.recipient_id) 
        OR (cm2.sender_id = cm.recipient_id AND cm2.recipient_id = cm.sender_id))
     ORDER BY cm2.created_at DESC 
     LIMIT 1) as last_message_text,
    cm.message_type as last_message_type,
    cm.offer_amount as last_offer_amount,
    cm.status as last_status
FROM chat_messages cm
JOIN users u ON (
    CASE 
        WHEN cm.sender_id = cm.recipient_id THEN cm.sender_id 
        ELSE cm.recipient_id 
    END = u.id
)
LEFT JOIN chat_notifications cn ON cn.message_id = cm.id
GROUP BY user_id, other_user_id
ORDER BY last_message_time DESC;

CREATE VIEW IF NOT EXISTS unread_message_count AS
SELECT 
    user_id,
    COUNT(*) as unread_count
FROM chat_notifications cn
WHERE cn.is_read = FALSE
GROUP BY user_id;
