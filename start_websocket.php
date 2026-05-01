<?php
// Simple WebSocket server starter
// This script helps you start the WebSocket server

require_once 'bootstrap.php';

echo "Starting WebSocket server for Bloxer Chat...\n";
echo "==========================================\n\n";

// Check if Ratchet is available
if (!class_exists('Ratchet\MessageComponentInterface')) {
    echo "❌ Ratchet WebSocket library not found!\n\n";
    echo "Please install Ratchet using Composer:\n";
    echo "composer require ratchet/pawl\n\n";
    echo "Or install manually:\n";
    echo "1. Download Ratchet from https://github.com/ratchetphp/Ratchet\n";
    echo "2. Include the autoloader in your project\n\n";
    echo "For now, the chat will work with HTTP polling fallback.\n";
    exit;
}

echo "✅ Ratchet library found!\n";
echo "🚀 Starting WebSocket server on port 8080...\n\n";

// Start the server
$command = "php controllers/api/websocket_server.php";
echo "Running: $command\n";
echo "Press Ctrl+C to stop the server\n\n";

// Execute the server securely
$safe_command = escapeshellcmd($command);
SecurityUtils::safeExec($safe_command);

?>
