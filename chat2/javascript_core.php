<script>
        const offerId = <?php echo $offer_id; ?>;
        const currentUserId = <?php echo $user['id']; ?>;
        const currentUsername = '<?php echo htmlspecialchars($user['username']); ?>';

        // Auto-scroll to bottom
        function scrollToBottom() {
            const messagesContainer = document.getElementById('chat-messages');
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        // Load messages
        async function loadMessages() {
            try {
                const response = await fetch(`./config.php?offer_id=${offerId}&action=get_messages`);
                const data = await response.json();
                
                if (data.success) {
                    displayMessages(data.messages);
                    scrollToBottom();
                }
            } catch (error) {
                console.error('Error loading messages:', error);
            }
        }

        // Display messages
        function displayMessages(messages) {
            const container = document.getElementById('chat-messages');
            
            if (messages.length === 0) {
                container.innerHTML = `
                    <div class="empty-chat">
                        <i class="fas fa-comments" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.3;"></i>
                        <h3>No messages yet</h3>
                        <p>Start the conversation!</p>
                    </div>
                `;
                return;
            }

            let html = '';
            messages.forEach(message => {
                const isSender = Number(message.is_sender) === 1;
                html += `
                    <div class="chat-message ${isSender ? 'sender' : 'receiver'}">
                        <div class="message-bubble">
                            ${message.message.replace(/\n/g, '<br>')}
                        </div>
                        <div class="message-meta">
                            ${message.username} · ${new Date(message.created_at).toLocaleString()}
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }

        // Send message
        async function sendMessage(message) {
            try {
                const formData = new FormData();
                formData.append('action', 'send_message');
                formData.append('message', message);
                formData.append('offer_id', offerId);
                
                const response = await fetch('./config.php', {
                    method: 'POST',
                    body: formData
                });
                
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                
                const responseText = await response.text();
                console.log('Raw response:', responseText);
                
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (e) {
                    console.error('Failed to parse JSON:', e);
                    console.error('Response was:', responseText);
                    alert('Invalid response from server: ' + responseText);
                    return;
                }
                
                console.log('Parsed data:', data);
                
                if (data.success) {
                    await loadMessages();
                } else {
                    alert('Error sending message: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error sending message:', error);
                alert('Error sending message. Please try again.');
            }
        }

        // Handle form submission
        document.getElementById('chat-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const input = document.getElementById('message-input');
            const message = input.value.trim();
            
            if (message) {
                const sendButton = document.getElementById('send-button');
                sendButton.disabled = true;
                
                await sendMessage(message);
                input.value = '';
                
                sendButton.disabled = false;
                input.focus();
            }
        });

        // Auto-resize textarea
        const messageInput = document.getElementById('message-input');
        messageInput.addEventListener('input', () => {
            messageInput.style.height = 'auto';
            messageInput.style.height = Math.min(messageInput.scrollHeight, 120) + 'px';
        });

        // Handle Enter key
        messageInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                document.getElementById('chat-form').dispatchEvent(new Event('submit'));
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                e.preventDefault();
                window.location.href = '<?php echo $is_developer ? '../controllers/core/dashboard.php' : '../controllers/marketplace/marketplace.php'; ?>';
            }
        });

        // Test function
        async function testConnection() {
            try {
                // Test 1: Direct API file
                console.log('=== Testing direct API ===');
                const apiResponse = await fetch('./test_api.php');
                const apiText = await apiResponse.text();
                console.log('API Raw response:', apiText);
                const apiData = JSON.parse(apiText);
                console.log('API Parsed data:', apiData);
                
                // Test 2: Config.php
                console.log('=== Testing config.php ===');
                const formData = new FormData();
                formData.append('action', 'test');
                
                const response = await fetch('./config.php', {
                    method: 'POST',
                    body: formData
                });
                
                const responseText = await response.text();
                console.log('CONFIG Raw response:', responseText);
                
                if (responseText.startsWith('{')) {
                    const data = JSON.parse(responseText);
                    console.log('CONFIG Parsed data:', data);
                } else {
                    console.error('CONFIG returned HTML instead of JSON!');
                }
            } catch (error) {
                console.error('TEST Error:', error);
            }
        }

        // Initial load
        loadMessages();
        
        // Run test on load
        testConnection();
    </script>
