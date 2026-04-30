<?php
// WebSocket Server for Real-time Chat
require_once '../core/mainlogincore.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

class Chat implements MessageComponentInterface {
    protected $clients;
    protected $rooms; // offer_id => [connections]
    protected $users; // connection_id => user_data

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->rooms = [];
        $this->users = [];
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        
        if (!$data || !isset($data['type'])) {
            return;
        }

        switch ($data['type']) {
            case 'join_room':
                $this->joinRoom($from, $data);
                break;
            case 'chat_message':
                $this->handleChatMessage($from, $data);
                break;
            case 'typing':
                $this->handleTyping($from, $data);
                break;
            case 'stop_typing':
                $this->handleStopTyping($from, $data);
                break;
            case 'mark_read':
                $this->handleMarkRead($from, $data);
                break;
            case 'user_status':
                $this->handleUserStatus($from, $data);
                break;
        }
    }

    private function joinRoom($conn, $data) {
        $offerId = $data['offer_id'] ?? null;
        $userId = $data['user_id'] ?? null;
        $username = $data['username'] ?? null;
        $isDeveloper = $data['is_developer'] ?? false;

        if (!$offerId || !$userId || !$username) {
            return;
        }

        // Store user data
        $this->users[$conn->resourceId] = [
            'user_id' => $userId,
            'username' => $username,
            'offer_id' => $offerId,
            'is_developer' => $isDeveloper,
            'is_typing' => false,
            'last_seen' => time()
        ];

        // Add to room
        if (!isset($this->rooms[$offerId])) {
            $this->rooms[$offerId] = [];
        }
        $this->rooms[$offerId][$conn->resourceId] = $conn;

        // Notify others in room
        $this->broadcastToRoom($offerId, [
            'type' => 'user_joined',
            'user_id' => $userId,
            'username' => $username,
            'is_developer' => $isDeveloper,
            'online_users' => $this->getOnlineUsers($offerId)
        ], $conn);

        // Send current online users to new user
        $conn->send(json_encode([
            'type' => 'room_joined',
            'online_users' => $this->getOnlineUsers($offerId),
            'recent_messages' => $this->getRecentMessages($offerId)
        ]));
    }

    private function handleChatMessage($from, $data) {
        $user = $this->users[$from->resourceId] ?? null;
        if (!$user) return;

        $message = $data['message'] ?? '';
        $messageId = $this->saveMessage($user['offer_id'], $user['user_id'], $message);

        $messageData = [
            'type' => 'new_message',
            'message_id' => $messageId,
            'user_id' => $user['user_id'],
            'username' => $user['username'],
            'is_developer' => $user['is_developer'],
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s'),
            'is_sender' => false
        ];

        // Send to all in room except sender
        $this->broadcastToRoom($user['offer_id'], $messageData, $from);

        // Send confirmation to sender
        $from->send(json_encode([
            'type' => 'message_sent',
            'message_id' => $messageId,
            'timestamp' => date('Y-m-d H:i:s')
        ]));
    }

    private function handleTyping($from, $data) {
        $user = $this->users[$from->resourceId] ?? null;
        if (!$user) return;

        $user['is_typing'] = true;

        $this->broadcastToRoom($user['offer_id'], [
            'type' => 'user_typing',
            'user_id' => $user['user_id'],
            'username' => $user['username']
        ], $from);
    }

    private function handleStopTyping($from, $data) {
        $user = $this->users[$from->resourceId] ?? null;
        if (!$user) return;

        $user['is_typing'] = false;

        $this->broadcastToRoom($user['offer_id'], [
            'type' => 'user_stop_typing',
            'user_id' => $user['user_id']
        ], $from);
    }

    private function handleMarkRead($from, $data) {
        $user = $this->users[$from->resourceId] ?? null;
        if (!$user) return;

        $messageId = $data['message_id'] ?? null;
        if ($messageId) {
            $this->markMessageAsRead($messageId, $user['user_id']);
        }

        $this->broadcastToRoom($user['offer_id'], [
            'type' => 'message_read',
            'message_id' => $messageId,
            'user_id' => $user['user_id']
        ], $from);
    }

    private function handleUserStatus($from, $data) {
        $user = $this->users[$from->resourceId] ?? null;
        if (!$user) return;

        $status = $data['status'] ?? 'online';
        $user['status'] = $status;

        $this->broadcastToRoom($user['offer_id'], [
            'type' => 'user_status_update',
            'user_id' => $user['user_id'],
            'status' => $status
        ], $from);
    }

    private function broadcastToRoom($offerId, $data, $excludeConn = null) {
        if (!isset($this->rooms[$offerId])) {
            return;
        }

        foreach ($this->rooms[$offerId] as $conn) {
            if ($conn !== $excludeConn) {
                $conn->send(json_encode($data));
            }
        }
    }

    private function getOnlineUsers($offerId) {
        if (!isset($this->rooms[$offerId])) {
            return [];
        }

        $onlineUsers = [];
        foreach ($this->rooms[$offerId] as $conn) {
            $user = $this->users[$conn->resourceId] ?? null;
            if ($user) {
                $onlineUsers[] = [
                    'user_id' => $user['user_id'],
                    'username' => $user['username'],
                    'is_developer' => $user['is_developer'],
                    'is_typing' => $user['is_typing'],
                    'status' => $user['status'] ?? 'online'
                ];
            }
        }

        return $onlineUsers;
    }

    private function getRecentMessages($offerId, $limit = 20) {
        $auth = new AuthCore();
        $conn = $auth->getConnection();

        $stmt = $conn->prepare("
            SELECT m.*, u.username,
                   CASE WHEN m.sender_id = ? THEN 1 ELSE 0 END as is_sender
            FROM offer_messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.offer_id = ?
            ORDER BY m.created_at DESC
            LIMIT ?
        ");
        
        // Use first online user's ID for is_sender logic
        $firstUserId = null;
        if (isset($this->rooms[$offerId])) {
            foreach ($this->rooms[$offerId] as $conn) {
                $user = $this->users[$conn->resourceId] ?? null;
                if ($user) {
                    $firstUserId = $user['user_id'];
                    break;
                }
            }
        }

        $stmt->bind_param("iii", $firstUserId, $offerId, $limit);
        $stmt->execute();
        $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        return array_reverse($messages);
    }

    private function saveMessage($offerId, $userId, $message) {
        $auth = new AuthCore();
        $conn = $auth->getConnection();

        $stmt = $conn->prepare("
            INSERT INTO offer_messages (offer_id, sender_id, message)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("iis", $offerId, $userId, $message);
        $stmt->execute();

        return $stmt->insert_id;
    }

    private function markMessageAsRead($messageId, $userId) {
        $auth = new AuthCore();
        $conn = $auth->getConnection();

        $stmt = $conn->prepare("
            UPDATE offer_messages 
            SET read_at = NOW() 
            WHERE id = ? AND sender_id != ?
        ");
        $stmt->bind_param("ii", $messageId, $userId);
        $stmt->execute();
    }

    public function onClose(ConnectionInterface $conn) {
        $user = $this->users[$conn->resourceId] ?? null;
        
        if ($user) {
            // Remove from room
            if (isset($this->rooms[$user['offer_id']][$conn->resourceId])) {
                unset($this->rooms[$user['offer_id']][$conn->resourceId]);
            }

            // Notify others
            $this->broadcastToRoom($user['offer_id'], [
                'type' => 'user_left',
                'user_id' => $user['user_id'],
                'online_users' => $this->getOnlineUsers($user['offer_id'])
            ]);

            // Remove user data
            unset($this->users[$conn->resourceId]);
        }

        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }
}

// Run WebSocket server
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new Chat()
        )
    ),
    8080
);

echo "WebSocket server started on port 8080\n";
$server->run();
