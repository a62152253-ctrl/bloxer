<?php
// Core Authentication System for Bloxer
// This file should include bootstrap.php to load necessary dependencies

require_once __DIR__ . '/../../bootstrap.php';

// Environment-based error reporting
function isDevelopmentEnvironment() {
    $env_file = dirname(__DIR__, 2) . '/.env';
    if (file_exists($env_file)) {
        $env_content = SecurityUtils::safeFileGetContents($env_file);
        return strpos($env_content, 'APP_ENV=development') !== false || 
               strpos($env_content, 'APP_ENV=local') !== false;
    }
    
    // Fallback to server detection
    $server_name = $_SERVER['SERVER_NAME'] ?? 'localhost';
    $server_addr = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
    return $server_name === 'localhost' || $server_addr === '127.0.0.1';
}

if (isDevelopmentEnvironment()) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/logs/php_errors.log');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/logs/php_errors.log');
}

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include security utilities
require_once __DIR__ . '/../../config/security.php';

class AuthCore {
    protected $db;
    protected $conn;
    protected $max_login_attempts = 5;
    protected $login_timeout = 900; // 15 minutes
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->connectMysqli();
        $this->initCSRF();
    }
    
    private function connectMysqli() {
        try {
            $config = $this->db->getConfig();
            $this->conn = new mysqli($config['host'], $config['username'], $config['password'], $config['database']);
            if ($this->conn->connect_error) {
                throw new Exception("Database connection failed: " . $this->conn->connect_error);
            }
            $this->conn->set_charset("utf8mb4");
        } catch (Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw new Exception("Database connection error. Please try again later.");
        }
    }
    
    public function getConnection(): mysqli {
        if (!$this->conn) {
            $this->connectMysqli();
        }
        return $this->conn;
    }
    
    public function register($username, $email, $password, $confirm_password, $user_type = 'user') {
        // CSRF validation
        if (!$this->validateCSRF()) {
            return ['success' => false, 'errors' => ['Nieprawidłowe żądanie. Spróbuj ponownie.']];
        }
        
        // Rate limiting check
        $rate_limit_result = $this->checkRateLimit('register');
        if (!$rate_limit_result['allowed']) {
            return ['success' => false, 'errors' => [$rate_limit_result['message']]];
        }
        
        $errors = [];
        
        if (empty($username) || empty($email) || empty($password)) {
            $errors[] = "Wszystkie pola są wymagane";
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Nieprawidłowy format adresu email";
        }
        
        if (strlen($username) < 3 || strlen($username) > 20) {
            $errors[] = "Nazwa użytkownika musi mieć od 3 do 20 znaków";
        }
        
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors[] = "Nazwa użytkownika może zawierać tylko litery, cyfry i podkreślenia";
        }
        
        if (strlen($password) < 6) {
            $errors[] = "Hasło musi mieć co najmniej 6 znaków";
        }
        
        if ($password !== $confirm_password) {
            $errors[] = "Hasła nie są identyczne";
        }
        
        if (!in_array($user_type, ['user', 'developer'])) {
            $errors[] = "Nieprawidłowy typ konta";
        }
        
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        
        $conn = $this->getConnection();
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            return ['success' => false, 'errors' => ['Błąd bazy danych. Spróbuj ponownie.']];
        }
        $stmt->bind_param("ss", $username, $email);
        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
            return ['success' => false, 'errors' => ['Błąd bazy danych. Spróbuj ponownie.']];
        }
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return ['success' => false, 'errors' => ['Nazwa użytkownika lub email już istnieje']];
        }
        
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $conn = $this->getConnection();
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, user_type, created_at) VALUES (?, ?, ?, ?, NOW())");
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            return ['success' => false, 'errors' => ['Błąd bazy danych. Spróbuj ponownie.']];
        }
        $stmt->bind_param("ssss", $username, $email, $hashed_password, $user_type);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Rejestracja zakończona pomyślnie'];
        } else {
            error_log("Execute failed: " . $stmt->error);
            return ['success' => false, 'errors' => ['Rejestracja nie powiodła się']];
        }
    }
    
    public function login($username, $password) {
        // CSRF validation
        if (!$this->validateCSRF()) {
            return ['success' => false, 'errors' => ['Nieprawidłowe żądanie. Spróbuj ponownie.']];
        }
        
        // Rate limiting check
        $rate_limit_result = $this->checkRateLimit('login');
        if (!$rate_limit_result['allowed']) {
            return ['success' => false, 'errors' => [$rate_limit_result['message']]];
        }
        
        // Check brute force attempts
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if ($this->isBlocked($ip_address)) {
            return ['success' => false, 'errors' => ['Zbyt wiele prób logowania. Spróbuj ponownie za 15 minut.']];
        }
        
        $errors = [];
        
        if (empty($username) || empty($password)) {
            $errors[] = "Nazwa użytkownika i hasło są wymagane";
        }
        
        if (!empty($errors)) {
            $this->recordFailedAttempt($ip_address);
            return ['success' => false, 'errors' => $errors];
        }
        
        $conn = $this->getConnection();
        $stmt = $conn->prepare("SELECT id, username, email, password, user_type FROM users WHERE username = ? OR email = ?");
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            return ['success' => false, 'errors' => ['Błąd bazy danych. Spróbuj ponownie.']];
        }
        $stmt->bind_param("ss", $username, $username);
        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
            return ['success' => false, 'errors' => ['Błąd bazy danych. Spróbuj ponownie.']];
        }
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $this->recordFailedAttempt($ip_address);
            return ['success' => false, 'errors' => ['Nie znaleziono użytkownika']];
        }
        
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            // Clear failed attempts on successful login
            $this->clearFailedAttempts($ip_address);
            
            // Regenerate session ID for security
            session_regenerate_id(true);
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['last_activity'] = time();
            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
            
            // Handle remember me
            if (isset($_POST['remember']) && $_POST['remember'] === 'on') {
                $this->setRememberCookie($user['id']);
            }
            
            return ['success' => true, 'message' => 'Logowanie pomyślne'];
        } else {
            $this->recordFailedAttempt($ip_address);
            return ['success' => false, 'errors' => ['Nieprawidłowe hasło']];
        }
    }
    
    public function isLoggedIn() {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        // Check session timeout (30 minutes)
        $timeout = 1800; // 30 minutes
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
            $this->logout();
            return false;
        }
        
        // Check IP address and user agent for session hijacking protection
        if (isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
            $this->logout();
            return false;
        }
        
        if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
            $this->logout();
            return false;
        }
        
        // Update last activity
        $_SESSION['last_activity'] = time();
        return true;
    }
    
public function logout() {
        session_destroy();
        
        // Clear session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        SecurityUtils::safeRedirect('../auth/login.php', 403, 'Session expired');
    }
    
    public function getCurrentUser() {
        if ($this->isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'email' => $_SESSION['email'],
                'user_type' => $_SESSION['user_type'] ?? 'user'
            ];
        }
        return null;
    }
    
    public function isDeveloper() {
        if ($this->isLoggedIn()) {
            return ($_SESSION['user_type'] ?? 'user') === 'developer';
        }
        return false;
    }
    
    public function requireDeveloper() {
        if (!$this->isLoggedIn() || !$this->isDeveloper()) {
            SecurityUtils::safeRedirect('../auth/login.php', 403, 'Developer access required');
        }
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            SecurityUtils::safeRedirect('../auth/login.php', 403, 'Login required');
        }
    }
    
    public function generateResetToken($email) {
        // CSRF validation
        if (!$this->validateCSRF()) {
            return ['success' => false, 'errors' => ['Nieprawidłowe żądanie. Spróbuj ponownie.']];
        }
        
        $conn = $this->getConnection();
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            return ['success' => false, 'errors' => ['Database error occurred']];
        }
        $stmt->bind_param("s", $email);
        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
            return ['success' => false, 'errors' => ['Database error occurred']];
        }
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            // Always return success for security (don't reveal if email exists)
            return ['success' => true, 'token' => ''];
        }
        
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $conn = $this->getConnection();
        $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expiry = ? WHERE email = ?");
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            return ['success' => false, 'errors' => ['Database error occurred']];
        }
        $stmt->bind_param("sss", $token, $expiry, $email);
        
        if ($stmt->execute()) {
            return ['success' => true, 'token' => $token];
        } else {
            error_log("Execute failed: " . $stmt->error);
            return ['success' => false, 'errors' => ['Failed to generate reset token']];
        }
    }
    
    public function verifyResetToken($token, $new_password, $confirm_password) {
        // CSRF validation
        if (!$this->validateCSRF()) {
            return ['success' => false, 'errors' => ['Nieprawidłowe żądanie. Spróbuj ponownie.']];
        }
        
        $errors = [];
        
        if (empty($new_password) || empty($confirm_password)) {
            $errors[] = "Wszystkie pola są wymagane";
        }
        
        if (strlen($new_password) < 6) {
            $errors[] = "Hasło musi mieć co najmniej 6 znaków";
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = "Hasła nie są identyczne";
        }
        
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        
        $conn = $this->getConnection();
        $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expiry > NOW()");
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            return ['success' => false, 'errors' => ['Database error occurred']];
        }
        $stmt->bind_param("s", $token);
        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
            return ['success' => false, 'errors' => ['Database error occurred']];
        }
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return ['success' => false, 'errors' => ['Invalid or expired token']];
        }
        
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $conn = $this->getConnection();
        $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expiry = NULL WHERE reset_token = ?");
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            return ['success' => false, 'errors' => ['Database error occurred']];
        }
        $stmt->bind_param("ss", $hashed_password, $token);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Password reset successful'];
        } else {
            error_log("Execute failed: " . $stmt->error);
            return ['success' => false, 'errors' => ['Failed to reset password']];
        }
    }
    
    public function createTables() {
        $sql = "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            user_type ENUM('user', 'developer') DEFAULT 'user',
            reset_token VARCHAR(64) NULL,
            reset_expiry DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
        
        // Create login attempts table for brute force protection
        $login_attempts_sql = "CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ip (ip_address),
            INDEX idx_time (attempt_time)
        )";
        
        // Create remember tokens table
        $remember_tokens_sql = "CREATE TABLE IF NOT EXISTS remember_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(64) UNIQUE NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_token (token),
            INDEX idx_expires (expires_at)
        )";
        
        $conn = $this->getConnection();
        if ($conn->query($sql) && $conn->query($login_attempts_sql) && $conn->query($remember_tokens_sql)) {
            return true;
        } else {
            return false;
        }
    }
    
    // CSRF Protection
    private function initCSRF() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }
    
    public function getCSRFToken() {
        return $_SESSION['csrf_token'] ?? '';
    }
    
    public function validateCSRF() {
        // Skip CSRF validation for CLI testing
        if (php_sapi_name() === 'cli') {
            return true;
        }
        
        if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
    }
    
    // Rate Limiting
    public function checkRateLimit($action) {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $key = $action . '_' . $ip_address;
        $limit_file = sys_get_temp_dir() . '/rate_limit_' . md5($key);
        
        if (file_exists($limit_file)) {
            $data = json_decode(file_get_contents($limit_file), true);
            $count = $data['count'] ?? 0;
            $last_time = $data['time'] ?? 0;
            
            // Reset if hour has passed
            if (time() - $last_time > 3600) {
                $count = 0;
            }
            
            if ($count >= 10) { // 10 attempts per hour
                return ['allowed' => false, 'message' => 'Zbyt wiele prób. Spróbuj ponownie za godzinę.'];
            }
            
            $count++;
        } else {
            $count = 1;
        }
        
        file_put_contents($limit_file, json_encode(['count' => $count, 'time' => time()]));
        return ['allowed' => true];
    }
    
    // Brute Force Protection
    private function isBlocked($ip_address) {
        try {
            $conn = $this->getConnection();
            if (!$conn) return false;
            
            $stmt = $conn->prepare("SELECT COUNT(*) as attempts FROM login_attempts WHERE ip_address = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)");
            if (!$stmt) return false;
        
        $stmt->bind_param("si", $ip_address, $this->login_timeout);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
            
            return $data['attempts'] >= $this->max_login_attempts;
        } catch (Exception $e) {
            error_log("Error in isBlocked: " . $e->getMessage());
            return false;
        }
    }
    
    private function recordFailedAttempt($ip_address) {
        $conn = $this->getConnection();
        $stmt = $conn->prepare("INSERT INTO login_attempts (ip_address) VALUES (?)");
        if ($stmt) {
            $stmt->bind_param("s", $ip_address);
            $stmt->execute();
        }
    }
    
    private function clearFailedAttempts($ip_address) {
        $conn = $this->getConnection();
        $stmt = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
        if ($stmt) {
            $stmt->bind_param("s", $ip_address);
            $stmt->execute();
        }
    }
    
    // Remember Me Functionality
    private function setRememberCookie($user_id) {
        $token = bin2hex(random_bytes(32));
        $selector = bin2hex(random_bytes(16));
        $combined = $selector . $token;
        $hashed_token = hash('sha256', $token);
        
        // Store in database for 30 days
        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
        $conn = $this->getConnection();
        $stmt = $conn->prepare("INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("iss", $user_id, $hashed_token, $expires);
            $stmt->execute();
        }
        
        // Set cookie
        setcookie('remember_token', $combined, strtotime('+30 days'), '/', '', false, true);
    }
    
    public function checkRememberCookie() {
        if (isset($_COOKIE['remember_token']) && !$this->isLoggedIn()) {
            $combined = $_COOKIE['remember_token'];
            if (strlen($combined) !== 96) return false; // 32 + 16 = 48 chars, hex = 96
            
            $selector = substr($combined, 0, 32);
            $token = substr($combined, 32);
            $hashed_token = hash('sha256', $token);
            
            $conn = $this->getConnection();
            $stmt = $conn->prepare("SELECT rt.user_id, u.username, u.email, u.user_type FROM remember_tokens rt JOIN users u ON rt.user_id = u.id WHERE rt.token = ? AND rt.expires_at > NOW()");
            if ($stmt) {
                $stmt->bind_param("s", $hashed_token);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['user_type'] = $user['user_type'];
                    
                    // Generate new token for security
                    $this->setRememberCookie($user['user_id']);
                    return true;
                }
            }
        }
        return false;
    }
    
    public function clearRememberCookie() {
        if (isset($_COOKIE['remember_token'])) {
            $combined = $_COOKIE['remember_token'];
            $token = substr($combined, 32);
            $hashed_token = hash('sha256', $token);
            
            $conn = $this->getConnection();
            $stmt = $conn->prepare("DELETE FROM remember_tokens WHERE token = ?");
            if ($stmt) {
                $stmt->bind_param("s", $hashed_token);
                $stmt->execute();
            }
            
            setcookie('remember_token', '', time() - 3600, '/');
        }
    }
}
?>
