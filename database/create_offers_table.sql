-- Create offers table if it doesn't exist
CREATE TABLE IF NOT EXISTS offers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    app_id INT NOT NULL,
    buyer_id INT NOT NULL,
    developer_id INT NOT NULL,
    offer_amount DECIMAL(10,2) NOT NULL,
    offer_type ENUM('fixed', 'percentage') DEFAULT 'fixed',
    message TEXT,
    status ENUM('pending', 'accepted', 'declined', 'countered') DEFAULT 'pending',
    transfer_notes TEXT,
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

-- Create offer_messages table if it doesn't exist
CREATE TABLE IF NOT EXISTS offer_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    offer_id INT NOT NULL,
    sender_id INT NOT NULL,
    message TEXT NOT NULL,
    message_type ENUM('text', 'offer', 'accept', 'decline', 'counter') DEFAULT 'text',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (offer_id) REFERENCES offers(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_offer_id (offer_id),
    INDEX idx_sender_id (sender_id)
);
