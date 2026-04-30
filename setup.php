<?php
/**
 * Setup Script for Bloxer Platform
 * Automated installation and configuration
 */

require_once 'bootstrap.php';

?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bloxer Platform Setup</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .setup-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 800px;
            width: 100%;
            padding: 40px;
            animation: fadeIn 0.6s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .setup-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .setup-header h1 {
            color: #333;
            font-size: 2.5em;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        
        .setup-header p {
            color: #666;
            font-size: 1.1em;
        }
        
        .setup-section {
            margin-bottom: 30px;
            padding: 25px;
            border: 2px solid #f0f0f0;
            border-radius: 15px;
            transition: all 0.3s ease;
        }
        
        .setup-section:hover {
            border-color: #667eea;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.1);
        }
        
        .setup-section h3 {
            color: #333;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .setup-section h3 i {
            color: #667eea;
        }
        
        .status-check {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 10px 0;
            padding: 10px;
            border-radius: 8px;
            background: #f8f9fa;
        }
        
        .status-check.success {
            background: #d4edda;
            color: #155724;
        }
        
        .status-check.error {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-check.warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin: 10px 5px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        
        .progress-bar {
            width: 100%;
            height: 6px;
            background: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
            margin: 20px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            width: 0%;
            transition: width 0.3s ease;
        }
        
        .log-output {
            background: #1a1a1a;
            color: #00ff00;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            max-height: 200px;
            overflow-y: auto;
            margin: 15px 0;
        }
        
        .hidden {
            display: none;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .setup-container {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-header">
            <h1><i class="fas fa-rocket"></i> Bloxer Setup</h1>
            <p>Complete platform installation and configuration</p>
        </div>
        
        <div class="progress-bar">
            <div class="progress-fill" id="progressBar"></div>
        </div>
        
        <!-- Environment Check -->
        <div class="setup-section">
            <h3><i class="fas fa-check-circle"></i> Environment Check</h3>
            <div id="envCheck"></div>
        </div>
        
        <!-- Database Configuration -->
        <div class="setup-section">
            <h3><i class="fas fa-database"></i> Database Configuration</h3>
            <form id="dbForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="dbHost">Database Host</label>
                        <input type="text" id="dbHost" name="dbHost" value="localhost" required>
                    </div>
                    <div class="form-group">
                        <label for="dbName">Database Name</label>
                        <input type="text" id="dbName" name="dbName" value="bloxer_db" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="dbUser">Username</label>
                        <input type="text" id="dbUser" name="dbUser" value="root" required>
                    </div>
                    <div class="form-group">
                        <label for="dbPass">Password</label>
                        <input type="password" id="dbPass" name="dbPass">
                    </div>
                </div>
                <button type="submit" class="btn">
                    <i class="fas fa-plug"></i> Test Connection
                </button>
            </form>
            <div id="dbResult"></div>
        </div>
        
        <!-- Database Setup -->
        <div class="setup-section">
            <h3><i class="fas fa-cogs"></i> Database Setup</h3>
            <button id="setupDbBtn" class="btn" disabled>
                <i class="fas fa-database"></i> Create Database Tables
            </button>
            <div id="setupResult"></div>
            <div id="setupLog" class="log-output hidden"></div>
        </div>
        
        <!-- Admin Account -->
        <div class="setup-section">
            <h3><i class="fas fa-user-shield"></i> Admin Account</h3>
            <form id="adminForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="adminUsername">Username</label>
                        <input type="text" id="adminUsername" name="adminUsername" required>
                    </div>
                    <div class="form-group">
                        <label for="adminEmail">Email</label>
                        <input type="email" id="adminEmail" name="adminEmail" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="adminPassword">Password</label>
                        <input type="password" id="adminPassword" name="adminPassword" required>
                    </div>
                    <div class="form-group">
                        <label for="adminConfirmPassword">Confirm Password</label>
                        <input type="password" id="adminConfirmPassword" name="adminConfirmPassword" required>
                    </div>
                </div>
                <button type="submit" class="btn" disabled>
                    <i class="fas fa-user-plus"></i> Create Admin Account
                </button>
            </form>
            <div id="adminResult"></div>
        </div>
        
        <!-- Final Steps -->
        <div class="setup-section">
            <h3><i class="fas fa-flag-checkered"></i> Complete Setup</h3>
            <button id="completeBtn" class="btn btn-success" disabled>
                <i class="fas fa-check"></i> Complete Setup
            </button>
            <div id="completeResult"></div>
        </div>
    </div>

    <script>
        let setupProgress = 0;
        
        function updateProgress(percent) {
            setupProgress = percent;
            document.getElementById('progressBar').style.width = percent + '%';
        }
        
        function showStatus(containerId, message, type = 'success') {
            const container = document.getElementById(containerId);
            const div = document.createElement('div');
            div.className = `status-check ${type}`;
            div.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'times' : 'exclamation'}"></i>
                ${message}
            `;
            container.appendChild(div);
        }
        
        function logMessage(message) {
            const log = document.getElementById('setupLog');
            log.classList.remove('hidden');
            log.innerHTML += message + '\n';
            log.scrollTop = log.scrollHeight;
        }
        
        // Environment Check
        function checkEnvironment() {
            fetch('setup.php?action=check_env')
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('envCheck');
                    container.innerHTML = '';
                    
                    data.checks.forEach(check => {
                        showStatus('envCheck', check.message, check.status);
                    });
                    
                    if (data.all_good) {
                        updateProgress(20);
                    }
                });
        }
        
        // Database Connection Test
        document.getElementById('dbForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const params = new URLSearchParams(formData);
            
            fetch('setup.php?action=test_db&' + params.toString())
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('dbResult');
                    container.innerHTML = '';
                    
                    if (data.success) {
                        showStatus('dbResult', 'Database connection successful!', 'success');
                        document.getElementById('setupDbBtn').disabled = false;
                        updateProgress(40);
                    } else {
                        showStatus('dbResult', data.error, 'error');
                    }
                });
        });
        
        // Database Setup
        document.getElementById('setupDbBtn').addEventListener('click', function() {
            this.disabled = true;
            const log = document.getElementById('setupLog');
            log.classList.remove('hidden');
            log.innerHTML = '';
            
            fetch('setup.php?action=setup_db')
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('setupResult');
                    container.innerHTML = '';
                    
                    if (data.success) {
                        showStatus('setupResult', 'Database setup completed!', 'success');
                        document.getElementById('adminForm').querySelector('button').disabled = false;
                        updateProgress(60);
                    } else {
                        showStatus('setupResult', data.error, 'error');
                        this.disabled = false;
                    }
                    
                    if (data.log) {
                        log.innerHTML = data.log;
                    }
                });
        });
        
        // Admin Account Creation
        document.getElementById('adminForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const params = new URLSearchParams(formData);
            
            fetch('setup.php?action=create_admin&' + params.toString())
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('adminResult');
                    container.innerHTML = '';
                    
                    if (data.success) {
                        showStatus('adminResult', 'Admin account created successfully!', 'success');
                        document.getElementById('completeBtn').disabled = false;
                        updateProgress(80);
                    } else {
                        showStatus('adminResult', data.error, 'error');
                    }
                });
        });
        
        // Complete Setup
        document.getElementById('completeBtn').addEventListener('click', function() {
            fetch('setup.php?action=complete')
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('completeResult');
                    container.innerHTML = '';
                    
                    if (data.success) {
                        showStatus('completeResult', 'Setup completed successfully!', 'success');
                        updateProgress(100);
                        
                        setTimeout(() => {
                            window.location.href = 'index.php';
                        }, 2000);
                    } else {
                        showStatus('completeResult', data.error, 'error');
                    }
                });
        });
        
        // Initialize
        checkEnvironment();
    </script>
</body>
</html>

<?php
// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    $action = $_GET['action'];
    
    switch ($action) {
        case 'check_env':
            $checks = [];
            
            // PHP Version
            $phpVersion = PHP_VERSION;
            $checks[] = [
                'status' => version_compare($phpVersion, '7.4.0', '>=') ? 'success' : 'error',
                'message' => "PHP Version: $phpVersion " . (version_compare($phpVersion, '7.4.0', '>=') ? '(OK)' : '(Requires 7.4+)')
            ];
            
            // Required Extensions
            $extensions = ['pdo', 'pdo_mysql', 'mysqli', 'json', 'mbstring', 'curl'];
            foreach ($extensions as $ext) {
                $checks[] = [
                    'status' => extension_loaded($ext) ? 'success' : 'error',
                    'message' => "Extension: $ext " . (extension_loaded($ext) ? '(Loaded)' : '(Missing)')
                ];
            }
            
            // Directory Permissions
            $directories = ['uploads', 'logs', 'cache'];
            foreach ($directories as $dir) {
                $path = BLOXER_ROOT . '/' . $dir;
                $writable = is_dir($path) || is_writable(BLOXER_ROOT);
                $checks[] = [
                    'status' => $writable ? 'success' : 'warning',
                    'message' => "Directory: $dir " . ($writable ? '(Writable)' : '(Will be created)')
                ];
            }
            
            $allGood = array_reduce($checks, function($carry, $check) {
                return $carry && $check['status'] !== 'error';
            }, true);
            
            echo json_encode(['checks' => $checks, 'all_good' => $allGood]);
            exit;
            
        case 'test_db':
            $host = $_GET['dbHost'] ?? '';
            $name = $_GET['dbName'] ?? '';
            $user = $_GET['dbUser'] ?? '';
            $pass = $_GET['dbPass'] ?? '';
            
            try {
                $dsn = "mysql:host=$host;dbname=$name;charset=utf8mb4";
                $pdo = new PDO($dsn, $user, $pass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Update .env file
                $envContent = file_get_contents(BLOXER_ROOT . '/.env');
                $envContent = preg_replace('/DB_HOST=.*/', "DB_HOST=$host", $envContent);
                $envContent = preg_replace('/DB_NAME=.*/', "DB_NAME=$name", $envContent);
                $envContent = preg_replace('/DB_USER=.*/', "DB_USER=$user", $envContent);
                $envContent = preg_replace('/DB_PASS=.*/', "DB_PASS=$pass", $envContent);
                file_put_contents(BLOXER_ROOT . '/.env', $envContent);
                
                echo json_encode(['success' => true]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            
        case 'setup_db':
            try {
                $db = DatabaseConfig::getInstance();
                $conn = $db->getConnection();
                
                // Read and execute schema
                $schemaFile = BLOXER_ROOT . '/database/complete_database_schema.sql';
                if (file_exists($schemaFile)) {
                    $sql = file_get_contents($schemaFile);
                    $statements = array_filter(array_map('trim', explode(';', $sql)));
                    
                    $log = "Starting database setup...\n";
                    
                    foreach ($statements as $statement) {
                        if (!empty($statement)) {
                            try {
                                $conn->exec($statement);
                                $log .= "✓ Executed: " . substr($statement, 0, 50) . "...\n";
                            } catch (PDOException $e) {
                                $log .= "✗ Error: " . $e->getMessage() . "\n";
                            }
                        }
                    }
                    
                    $log .= "\nDatabase setup completed!\n";
                    echo json_encode(['success' => true, 'log' => $log]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Schema file not found']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            
        case 'create_admin':
            $username = $_GET['adminUsername'] ?? '';
            $email = $_GET['adminEmail'] ?? '';
            $password = $_GET['adminPassword'] ?? '';
            $confirm = $_GET['adminConfirmPassword'] ?? '';
            
            if ($password !== $confirm) {
                echo json_encode(['success' => false, 'error' => 'Passwords do not match']);
                exit;
            }
            
            if (strlen($password) < 8) {
                echo json_encode(['success' => false, 'error' => 'Password must be at least 8 characters']);
                exit;
            }
            
            try {
                $db = DatabaseConfig::getInstance();
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                $db->insert('users', [
                    'username' => $username,
                    'email' => $email,
                    'password' => $hashedPassword,
                    'user_type' => 'developer',
                    'email_verified' => true,
                    'status' => 'active'
                ]);
                
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            
        case 'complete':
            // Create a completion flag
            file_put_contents(BLOXER_ROOT . '/.installed', date('Y-m-d H:i:s'));
            echo json_encode(['success' => true]);
            exit;
    }
}
?>
