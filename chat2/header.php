<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - <?php echo htmlspecialchars($offer['app_title']); ?> - Bloxer</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .chat-container {
            height: 100vh;
            display: flex;
            flex-direction: column;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            margin: 0;
            border-radius: 0;
        }
        
        .chat-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 24px 32px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .chat-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(255,255,255,0.1) 0%, transparent 70%);
            pointer-events: none;
        }
        
        .chat-header-info {
            flex: 1;
        }
        
        .chat-header h1 {
            margin: 0 0 8px 0;
            font-size: 1.4rem;
            font-weight: 600;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .chat-header .subtitle {
            opacity: 0.95;
            font-size: 0.95rem;
            font-weight: 400;
        }
        
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 24px;
            background: linear-gradient(to bottom, #ffffff, #f8fafc);
            scroll-behavior: smooth;
        }
        
        .chat-messages::-webkit-scrollbar {
            width: 6px;
        }
        
        .chat-messages::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        
        .chat-messages::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }
        
        .chat-messages::-webkit-scrollbar-thumb:hover {
            background: #a0aec0;
        }
        
        .chat-message {
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .chat-message.sender {
            align-items: flex-end;
        }
        
        .chat-message.receiver {
            align-items: flex-start;
        }
        
        .message-bubble {
            max-width: 75%;
            padding: 16px 20px;
            border-radius: 24px;
            word-wrap: break-word;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            position: relative;
        }
        
        .message-bubble:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }
        
        .chat-message.sender .message-bubble {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-bottom-right-radius: 8px;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .chat-message.receiver .message-bubble {
            background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
            color: #1f2937;
            border-bottom-left-radius: 8px;
        }
        
        .message-meta {
            font-size: 0.8rem;
            opacity: 0.6;
            margin-top: 8px;
            padding: 0 8px;
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        
        .chat-input {
            padding: 24px 32px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .chat-input-form {
            display: flex;
            gap: 16px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .chat-input textarea {
            flex: 1;
            resize: none;
            border: 2px solid #e5e7eb;
            border-radius: 24px;
            padding: 16px 20px;
            font-family: inherit;
            font-size: 15px;
            background: white;
            transition: all 0.3s ease;
            line-height: 1.4;
        }
        
        .chat-input textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.2);
            transform: translateY(-1px);
        }
        
        .chat-input textarea::placeholder {
            color: #9ca3af;
        }
        
        .chat-input button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 50%;
            width: 52px;
            height: 52px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .chat-input button:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.4);
        }
        
        .chat-input button:disabled {
            background: linear-gradient(135deg, #9ca3af 0%, #6b7280 100%);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .chat-input button:disabled:hover {
            transform: none;
            box-shadow: none;
        }
        
        .role-badge {
            background: rgba(255, 255, 255, 0.25);
            padding: 6px 12px;
            border-radius: 16px;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            backdrop-filter: blur(10px);
        }
        
        .status-indicator {
            width: 10px;
            height: 10px;
            background: #22c55e;
            border-radius: 50%;
            display: inline-block;
            margin-left: 12px;
            box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.4);
        }
        
        .empty-chat {
            text-align: center;
            color: #6b7280;
            padding: 60px 40px;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 20px;
            backdrop-filter: blur(10px);
        }
        
        .empty-chat i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.3;
            color: #6b7280;
        }
        
        .empty-chat h3 {
            margin: 0 0 12px 0;
            font-size: 1.5rem;
            font-weight: 600;
            color: #4b5563;
        }
        
        .empty-chat p {
            margin: 0;
            font-size: 1rem;
            color: #6b7280;
        }
        
        .chat-controls {
            display: flex;
            gap: 12px;
        }
        
        .chat-control-btn {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            text-decoration: none;
            color: white;
            backdrop-filter: blur(10px);
        }
        
        .chat-control-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .online-status {
            width: 10px;
            height: 10px;
            background: #22c55e;
            border-radius: 50%;
            animation: pulse 2s infinite;
            box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.4);
        }
        
        @keyframes pulse {
            0% { 
                box-shadow: 0 0 0 0 rgba(34,197,94,0.7); 
            }
            70% { 
                box-shadow: 0 0 0 12px rgba(34,197,94,0); 
            }
            100% { 
                box-shadow: 0 0 0 0 rgba(34,197,94,0); 
            }
        }

        .studio-pill-neutral {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 6px 12px;
            border-radius: 16px;
            font-size: 0.9rem;
            font-weight: 600;
            backdrop-filter: blur(10px);
        }

        /* Mobile responsive */
        @media (max-width: 768px) {
            .chat-header {
                padding: 16px 20px;
                gap: 12px;
            }
            
            .chat-header h1 {
                font-size: 1.2rem;
            }
            
            .chat-messages {
                padding: 16px;
            }
            
            .message-bubble {
                max-width: 85%;
                padding: 12px 16px;
            }
            
            .chat-input {
                padding: 16px 20px;
            }
            
            .chat-input-form {
                gap: 12px;
            }
            
            .chat-input textarea {
                padding: 12px 16px;
                font-size: 14px;
            }
            
            .chat-input button {
                width: 44px;
                height: 44px;
                font-size: 16px;
            }
        }

        @media (max-width: 480px) {
            .chat-header {
                padding: 12px 16px;
                gap: 8px;
            }
            
            .chat-header h1 {
                font-size: 1rem;
            }
            
            .chat-messages {
                padding: 12px;
            }
            
            .message-bubble {
                max-width: 90%;
                padding: 10px 14px;
            }
            
            .chat-input {
                padding: 12px 16px;
            }
            
            .chat-input-form {
                gap: 8px;
            }
            
            .chat-input textarea {
                padding: 10px 14px;
                font-size: 14px;
            }
            
            .chat-input button {
                width: 40px;
                height: 40px;
                font-size: 14px;
            }
        }
    </style>
</head>
