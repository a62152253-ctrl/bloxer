<body>
    <div class="chat-container">
        <header class="chat-header">
            <div class="chat-controls">
                <a href="<?php echo $is_developer ? '../controllers/core/dashboard.php' : '../controllers/marketplace/marketplace.php'; ?>" class="chat-control-btn" title="Back to Dashboard">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div class="online-status" title="User is online"></div>
            </div>
            <div class="chat-header-info">
                <h1><?php echo htmlspecialchars($offer['app_title']); ?></h1>
                <div class="subtitle">
                    <?php if ($is_developer): ?>
                        Chat with: <strong><?php echo htmlspecialchars($offer['buyer_name']); ?></strong>
                        <span class="role-badge">Buyer</span>
                    <?php else: ?>
                        Chat with: <strong><?php echo htmlspecialchars($offer['developer_name']); ?></strong>
                        <span class="role-badge">Developer</span>
                    <?php endif; ?>
                    <span class="status-indicator"></span>
                </div>
            </div>
            <div>
                <span class="studio-pill-neutral">
                    $<?php echo number_format($offer['amount'], 2); ?>
                </span>
            </div>
        </header>

        <div class="chat-messages" id="chat-messages">
            <?php if (empty($messages)): ?>
                <div class="empty-chat">
                    <i class="fas fa-comments" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.3;"></i>
                    <h3>No messages yet</h3>
                    <p>Start the conversation!</p>
                </div>
            <?php else: ?>
                <?php foreach ($messages as $message): ?>
                    <div class="chat-message <?php echo $message['is_sender'] ? 'sender' : 'receiver'; ?>">
                        <div class="message-bubble">
                            <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                        </div>
                        <div class="message-meta">
                            <?php echo htmlspecialchars($message['username']); ?> · 
                            <?php echo date('M j, Y H:i', strtotime($message['created_at'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="chat-input">
            <form class="chat-input-form" id="chat-form" action="" method="POST" onsubmit="return false;">
                <textarea 
                    id="message-input" 
                    placeholder="Type your message... (Press Enter to send)" 
                    rows="1"
                    maxlength="1000"
                ></textarea>
                <button type="submit" id="send-button">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </form>
        </div>
    </div>
