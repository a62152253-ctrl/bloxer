<?php
/**
 * Database Configuration for Bloxer Platform
 * Centralized database connection and configuration management
 */

class DatabaseConfig {
    private static $instance = null;
    private $connection;
    private $config;
    
    private function __construct() {
        $this->loadConfig();
        $this->connect();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function loadConfig() {
        // Load environment variables
        $env_file = __DIR__ . '/../.env';
        if (file_exists($env_file)) {
            $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '#') === 0) continue;
                if (strpos($line, '=') === false) continue;
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
        
        $this->config = [
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'username' => $_ENV['DB_USER'] ?? 'root',
            'password' => $_ENV['DB_PASS'] ?? '',
            'database' => $_ENV['DB_NAME'] ?? 'bloxer_db',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci'
        ];
    }
    
    private function connect() {
        try {
            $dsn = "mysql:host={$this->config['host']};dbname={$this->config['database']};charset={$this->config['charset']}";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->config['charset']}"
            ];
            
            $this->connection = new PDO($dsn, $this->config['username'], $this->config['password'], $options);
            
        } catch (PDOException $e) {
            if (isDevelopmentEnvironment()) {
                die("Database connection failed: " . $e->getMessage());
            } else {
                error_log("Database connection failed: " . $e->getMessage());
                die("Database connection failed. Please try again later.");
            }
        }
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function getConfig() {
        return $this->config;
    }
    
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    public function commit() {
        return $this->connection->commit();
    }
    
    public function rollback() {
        return $this->connection->rollback();
    }
    
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
    
    public function prepare($sql) {
        return $this->connection->prepare($sql);
    }
    
    public function query($sql) {
        return $this->connection->query($sql);
    }
    
    public function execute($sql, $params = []) {
        $stmt = $this->prepare($sql);
        return $stmt->execute($params);
    }
    
    public function fetch($sql, $params = []) {
        $stmt = $this->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }
    
    public function fetchAll($sql, $params = []) {
        $stmt = $this->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function fetchColumn($sql, $params = [], $column = 0) {
        $stmt = $this->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn($column);
    }
    
    public function insert($table, $data) {
        // Whitelist table names to prevent SQL injection
        $allowed_tables = [
            'users', 'projects', 'project_files', 'apps', 'app_reviews', 'app_ratings',
            'user_apps', 'saved_apps', 'offers', 'offer_messages', 'notifications',
            'notification_preferences', 'developer_wallet', 'categories', 'popular_apps',
            'user_preferences', 'version_files', 'project_versions'
        ];
        
        if (!in_array($table, $allowed_tables)) {
            throw new Exception("Table not allowed: $table");
        }
        
        // Validate column names to prevent SQL injection
        $columns = [];
        foreach (array_keys($data) as $column) {
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
                throw new Exception("Invalid column name: $column");
            }
            $columns[] = $column;
        }
        
        $column_list = implode(', ', $columns);
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO $table ($column_list) VALUES ($placeholders)";
        
        $this->execute($sql, array_values($data));
        return $this->lastInsertId();
    }
    
    public function update($table, $data, $where, $whereParams = []) {
        // Whitelist table names to prevent SQL injection
        $allowed_tables = [
            'users', 'projects', 'project_files', 'apps', 'app_reviews', 'app_ratings',
            'user_apps', 'saved_apps', 'offers', 'offer_messages', 'notifications',
            'notification_preferences', 'developer_wallet', 'categories', 'popular_apps',
            'user_preferences', 'version_files', 'project_versions'
        ];
        
        if (!in_array($table, $allowed_tables)) {
            throw new Exception("Table not allowed: $table");
        }
        
        $setParts = [];
        foreach (array_keys($data) as $column) {
            // Validate column names to prevent SQL injection
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
                throw new Exception("Invalid column name: $column");
            }
            $setParts[] = "$column = ?";
        }
        $setClause = implode(', ', $setParts);
        
        $sql = "UPDATE $table SET $setClause WHERE $where";
        $params = array_merge(array_values($data), $whereParams);
        
        $stmt = $this->prepare($sql);
        return $stmt->execute($params);
    }
    
    public function delete($table, $where, $params = []) {
        // Whitelist table names to prevent SQL injection
        $allowed_tables = [
            'users', 'projects', 'project_files', 'apps', 'app_reviews', 'app_ratings',
            'user_apps', 'saved_apps', 'offers', 'offer_messages', 'notifications',
            'notification_preferences', 'developer_wallet', 'categories', 'popular_apps',
            'user_preferences', 'version_files', 'project_versions'
        ];
        
        if (!in_array($table, $allowed_tables)) {
            throw new Exception("Table not allowed: $table");
        }
        
        $sql = "DELETE FROM $table WHERE $where";
        return $this->execute($sql, $params);
    }
    
    public function tableExists($table) {
        $sql = "SHOW TABLES LIKE ?";
        $result = $this->fetch($sql, [$table]);
        return !empty($result);
    }
    
    public function getTableInfo($table) {
        $sql = "DESCRIBE $table";
        return $this->fetchAll($sql);
    }
    
    public function close() {
        $this->connection = null;
    }
    
    public function __destruct() {
        $this->close();
    }
}

// Helper function for backward compatibility
function getDatabaseConnection() {
    return DatabaseConfig::getInstance()->getConnection();
}
?>
