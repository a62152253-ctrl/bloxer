<?php
require_once '../core/mainlogincore.php';

$auth = new AuthCore();

if (!$auth->isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit();
}

$user = $auth->getCurrentUser();
$other_user_id = intval($_GET['user_id'] ?? 0);

if ($other_user_id === 0) {
    header('Location: ../core/dashboard.php');
    exit();
}

// Get other user info
$conn = $auth->getConnection();
$stmt = $conn->prepare("SELECT id, username, avatar_url FROM users WHERE id = ?");
$stmt->bind_param("i", $other_user_id);
$stmt->execute();
$other_user = $stmt->get_result()->fetch_assoc();

if (!$other_user) {
    header('Location: ../core/dashboard.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Bloxer</title>
    <link rel="stylesheet" href="../../assets/css/marketplace.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .chat-container {
            min-height: 100vh;
            background: var(--deep-black);
            background: linear-gradient(180deg, var(--deep-black) 0%, var(--deep-black-gradient) 100%);
            display: flex;
            flex-direction: column;
        }
        
        .chat-header {
            background: linear-gradient(135deg, rgba(255,255,255,0.06), rgba(255,255,255,0.02));
            border-bottom: 1px solid var(--glass-border);
            padding: 25px;
            display: flex;
            align-items: center;
            gap: 20px;
            backdrop-filter: blur(20px);
        }
        
        .chat-header h2 {
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
            font-size: 1.3rem;
        }
        
        .other-user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .other-user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            border: 2px solid var(--accent);
            box-shadow: 0 4px 15px rgba(99,102,241,0.3);
        }
        
        .other-user-name {
            color: var(--text-primary);
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .chat-main {
            flex: 1;
            display: flex;
            overflow: hidden;
        }
        
        .conversations-sidebar {
            width: 320px;
            background: linear-gradient(180deg, rgba(255,255,255,0.04), rgba(255,255,255,0.01));
            border-right: 1px solid var(--glass-border);
            overflow-y: auto;
            backdrop-filter: blur(10px);
        }
        
        .conversation-item {
            padding: 18px;
            border-bottom: 1px solid var(--glass-border);
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .conversation-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(99,102,241,0.05), transparent);
            transition: left 0.5s ease;
        }
        
        .conversation-item:hover {
            background: linear-gradient(135deg, rgba(255,255,255,0.08), rgba(255,255,255,0.04));
            transform: translateX(5px);
        }
        
        .conversation-item:hover::before {
            left: 100%;
        }
        
        .conversation-item.active {
            background: var(--accent);
            color: white;
        }
        
        .conversation-item.unread {
            background: rgba(99, 102, 241, 0.1);
        }
        
        .conversation-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 5px;
        }
        
        .conversation-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
        }
        
        .conversation-info {
            flex: 1;
        }
        
        .conversation-name {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 2px;
        }
        
        .conversation-time {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
        
        .conversation-preview {
            font-size: 0.85rem;
            color: var(--text-secondary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .unread-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: var(--error);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .messages-container {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: rgba(255,255,255,0.02);
        }
        
        .message {
            margin-bottom: 20px;
            display: flex;
            gap: 12px;
        }
        
        .message.sent {
            flex-direction: row-reverse;
        }
        
        .message-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        
        .message-content {
            max-width: 70%;
        }
        
        .message-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 5px;
        }
        
        .message-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.9rem;
        }
        
        .message-time {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
        
        .message-bubble {
            background: var(--bg-secondary);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 15px;
        }
        
        .message.sent .message-bubble {
            background: var(--accent);
            color: white;
        }
        
        .message-text {
            color: var(--text-primary);
            line-height: 1.5;
            margin-bottom: 8px;
            white-space: pre-wrap;
        }
        
        .message.offer {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(16, 185, 129, 0.05) 100%);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .message.offer .message-text {
            color: var(--success);
        }
        
        .offer-amount {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--success);
            margin-bottom: 8px;
        }
        
        .message-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .btn-accept, .btn-reject {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-accept {
            background: var(--success);
            color: white;
        }
        
        .btn-reject {
            background: var(--error);
            color: white;
        }
        
        .btn-accept:hover, .btn-reject:hover {
            opacity: 0.8;
        }
        
        .message-input-area {
            background: rgba(255,255,255,0.04);
            border-top: 1px solid var(--glass-border);
            padding: 20px;
        }
        
        .message-form {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        
        .message-input {
            flex: 1;
            padding: 12px;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 8px;
            color: var(--text-primary);
            resize: vertical;
            min-height: 50px;
        }
        
        .message-input:focus {
            outline: none;
            border-color: var(--input-focus-border);
            box-shadow: 0 0 0 3px var(--input-focus-shadow);
        }
        
        .send-button {
            padding: 12px 24px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .send-button:hover {
            background: var(--accent-hover);
        }
        
        .send-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .offer-controls {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .offer-input {
            padding: 8px 12px;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 6px;
            color: var(--text-primary);
            width: 120px;
        }
        
        .offer-input:focus {
            outline: none;
            border-color: var(--input-focus-border);
        }
        
        .message-type-toggle {
            display: flex;
            gap: 5px;
        }
        
        .toggle-btn {
            padding: 6px 12px;
            background: var(--bg-tertiary);
            border: 1px solid var(--glass-border);
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .toggle-btn.active {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
        }
        
        .back-button {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 100;
        }
        
        .back-button a {
            background: rgba(255,255,255,0.1);
            border: 1px solid var(--glass-border);
            border-radius: 50px;
            padding: 10px 20px;
            color: var(--text-primary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }
        
        .back-button a:hover {
            background: rgba(255,255,255,0.15);
        }
        
        @media (max-width: 768px) {
            .chat-main {
                flex-direction: column;
            }
            
            .conversations-sidebar {
                width: 100%;
                height: 200px;
                border-right: none;
                border-bottom: 1px solid var(--glass-border);
            }
            
            .chat-area {
                height: calc(100vh - 200px);
            }
        }
    </style>
</head>
<body>
    <div class="chat-container">
        <!-- Back Button -->
        <div class="back-button">
            <a href="../core/dashboard.php">
                <i class="fas fa-arrow-left"></i>
                Back to Dashboard
            </a>
        </div>
        
        <!-- Chat Header -->
        <div class="chat-header">
            <h2>
                <i class="fas fa-comments"></i>
                Messages with
                <div class="other-user-info">
                    <img src="<?php echo $other_user['avatar_url'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($other_user['username']) . '&background=6366f1&color=fff'; ?>" 
                         alt="<?php echo htmlspecialchars($other_user['username']); ?>" class="other-user-avatar">
                    <span class="other-user-name"><?php echo htmlspecialchars($other_user['username']); ?></span>
                </div>
            </h2>
        </div>
        
        <!-- Chat Main -->
        <div class="chat-main">
            <!-- Conversations Sidebar -->
            <div class="conversations-sidebar" id="conversations-sidebar">
                <div style="padding: 20px; text-align: center; color: var(--text-secondary);">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading conversations...</p>
                </div>
            </div>
            
            <!-- Chat Area -->
            <div class="chat-area">
                <!-- Messages Container -->
                <div class="messages-container" id="messages-container">
                    <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
                        <i class="fas fa-comments" style="font-size: 3rem; margin-bottom: 15px;"></i>
                        <p>Select a conversation to start messaging</p>
                    </div>
                </div>
                
                <!-- Message Input Area -->
                <div class="message-input-area" id="message-input-area" style="display: none;">
                    <div class="message-form">
                        <div class="message-type-toggle">
                            <button class="toggle-btn active" id="message-toggle" onclick="setMessageType('message')">Message</button>
                            <button class="toggle-btn" id="offer-toggle" onclick="setMessageType('offer')">Make Offer</button>
                        </div>
                        
                        <div class="offer-controls" id="offer-controls" style="display: none;">
                            <input type="number" class="offer-input" id="offer-amount" placeholder="Amount" step="0.01" min="0">
                        </div>
                        
                        <textarea class="message-input" id="message-input" placeholder="Type your message..." rows="1"></textarea>
                        <button class="send-button" id="send-button" onclick="sendMessage()">
                            <i class="fas fa-paper-plane"></i> Send
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        let currentMessageType = 'message';
        let currentOtherUserId = <?php echo $other_user_id; ?>;
        let currentPage = 1;
        let isLoadingMessages = false;
        
        // Load conversations list
        async function loadConversations() {
            try {
                const response = await fetch('chat.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=get_conversations'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    displayConversations(result.conversations);
                } else {
                    console.error('Failed to load conversations:', result);
                }
            } catch (error) {
                console.error('Error loading conversations:', error);
            }
        }
        
        function displayConversations(conversations) {
            const sidebar = document.getElementById('conversations-sidebar');
            
            if (conversations.length === 0) {
                sidebar.innerHTML = `
                    <div style="padding: 20px; text-align: center; color: var(--text-secondary);">
                        <i class="fas fa-comments" style="font-size: 2rem; margin-bottom: 15px;"></i>
                        <p>No conversations yet</p>
                    </div>
                `;
                return;
            }
            
            let html = '';
            conversations.forEach(conv => {
                const unreadCount = conv.unread_count || 0;
                const lastMessageText = conv.last_message_text || 'No messages yet';
                const isOffer = conv.last_message_type === 'offer';
                
                html += `
                    <div class="conversation-item ${unreadCount > 0 ? 'unread' : ''}" onclick="loadConversation(${conv.other_user_id})">
                        ${unreadCount > 0 ? `<div class="unread-badge">${unreadCount}</div>` : ''}
                        <div class="conversation-header">
                            <img src="${conv.other_avatar || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(conv.other_username) + '&background=6366f1&color=fff'}" 
                                 alt="${conv.other_username}" class="conversation-avatar">
                            <div class="conversation-info">
                                <div class="conversation-name">${conv.other_username}</div>
                                <div class="conversation-time">${new Date(conv.last_message_time).toLocaleString()}</div>
                            </div>
                        </div>
                        <div class="conversation-preview">
                            ${isOffer ? '<i class="fas fa-hand-holding-usd" style="color: var(--success); margin-right: 5px;"></i>' : ''}
                            ${lastMessageText}
                        </div>
                    </div>
                `;
            });
            
            sidebar.innerHTML = html;
        }
        
        // Load conversation messages
        async function loadConversation(otherUserId) {
            if (isLoadingMessages || otherUserId === currentOtherUserId) {
                return;
            }
            
            currentOtherUserId = otherUserId;
            currentPage = 1;
            isLoadingMessages = true;
            
            try {
                const response = await fetch('chat.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=get_messages&other_user_id=${otherUserId}&page=${currentPage}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    displayMessages(result.messages);
                    updateConversations();
                    document.getElementById('message-input-area').style.display = 'flex';
                    isLoadingMessages = false;
                } else {
                    console.error('Failed to load messages:', result);
                    isLoadingMessages = false;
                }
            } catch (error) {
                console.error('Error loading messages:', error);
                isLoadingMessages = false;
            }
        }
        
        function displayMessages(messages) {
            const container = document.getElementById('messages-container');
            
            if (messages.length === 0) {
                container.innerHTML = `
                    <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
                        <i class="fas fa-comments" style="font-size: 2rem; margin-bottom: 15px;"></i>
                        <p>No messages yet. Start the conversation!</p>
                    </div>
                `;
                return;
            }
            
            let html = '';
            messages.forEach(message => {
                const isSent = message.is_sent;
                const isOffer = message.message_type === 'offer';
                const date = new Date(message.created_at);
                const dateStr = date.toLocaleString() + ' ' + date.toLocaleTimeString();
                
                html += `
                    <div class="message ${isSent ? 'sent' : 'received'}">
                        <img src="${message.sender_avatar || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(message.sender_name) + '&background=6366f1&color=fff'}" 
                             alt="${message.sender_name}" class="message-avatar">
                        <div class="message-content">
                            <div class="message-header">
                                <span class="message-name">${message.sender_name}</span>
                                <span class="message-time">${dateStr}</span>
                            </div>
                            <div class="message-bubble ${isOffer ? 'offer' : ''}">
                                ${isOffer ? `<div class="offer-amount">$${message.offer_amount}</div>` : ''}
                                <div class="message-text">${message.message_text}</div>
                            </div>
                            ${isOffer && !isSent && message.status === 'pending' ? `
                                <div class="message-actions">
                                    <button class="btn-accept" onclick="acceptDeal(${message.id})">
                                        <i class="fas fa-check"></i> Accept
                                    </button>
                                    <button class="btn-reject" onclick="rejectDeal(${message.id})">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
            container.scrollTop = container.scrollHeight;
        }
        
        function setMessageType(type) {
            currentMessageType = type;
            
            // Update toggle buttons
            document.getElementById('message-toggle').classList.toggle('active', type === 'message');
            document.getElementById('offer-toggle').classList.toggle('active', type === 'offer');
            
            // Show/hide offer controls
            const offerControls = document.getElementById('offer-controls');
            const messageInput = document.getElementById('message-input');
            
            if (type === 'offer') {
                offerControls.style.display = 'flex';
                messageInput.placeholder = 'Make an offer...';
            } else {
                offerControls.style.display = 'none';
                messageInput.placeholder = 'Type your message...';
            }
        }
        
        async function sendMessage() {
            const messageText = document.getElementById('message-input').value.trim();
            const offerAmount = currentMessageType === 'offer' ? parseFloat(document.getElementById('offer-amount').value) || 0 : 0;
            
            if (!messageText) {
                alert('Please enter a message');
                return;
            }
            
            if (currentMessageType === 'offer' && offerAmount <= 0) {
                alert('Please enter a valid offer amount');
                return;
            }
            
            const sendButton = document.getElementById('send-button');
            sendButton.disabled = true;
            
            try {
                const response = await fetch('chat.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=send_message&recipient_id=${currentOtherUserId}&message_type=${currentMessageType}&message=${encodeURIComponent(messageText)}&offer_amount=${offerAmount}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    document.getElementById('message-input').value = '';
                    document.getElementById('offer-amount').value = '';
                    loadConversation(currentOtherUserId);
                } else {
                    alert(result.error || 'Failed to send message');
                }
            } catch (error) {
                console.error('Error sending message:', error);
                alert('An error occurred while sending your message');
            } finally {
                sendButton.disabled = false;
            }
        }
        
        async function acceptDeal(messageId) {
            if (!confirm('Are you sure you want to accept this offer?')) {
                return;
            }
            
            try {
                const response = await fetch('chat.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=accept_deal&message_id=${messageId}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    loadConversation(currentOtherUserId);
                } else {
                    alert(result.error || 'Failed to accept deal');
                }
            } catch (error) {
                console.error('Error accepting deal:', error);
                alert('An error occurred while accepting the deal');
            }
        }
        
        async function rejectDeal(messageId) {
            if (!confirm('Are you sure you want to reject this offer?')) {
                return;
            }
            
            try {
                const response = await fetch('chat.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=reject_deal&message_id=${messageId}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    loadConversation(currentOtherUserId);
                } else {
                    alert(result.error || 'Failed to reject deal');
                }
            } catch (error) {
                console.error('Error rejecting deal:', error);
                alert('An error occurred while rejecting the deal');
            }
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadConversations();
        });
    </script>
</body>
</html>
