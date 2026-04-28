<?php
require_once 'mainlogincore.php';

class DatabaseSetup extends AuthCore {
    protected $schema_file;
    
    public function __construct() {
        parent::__construct();
        $this->schema_file = __DIR__ . '/database_schema.sql';
    }
    
    public function setupDatabase() {
        echo "<h2>🚀 Setting up Bloxer Platform Database</h2>\n";
        
        try {
            // Read and execute schema
            if (!file_exists($this->schema_file)) {
                throw new Exception("Schema file not found: " . $this->schema_file);
            }
            
            $schema = file_get_contents($this->schema_file);
            $statements = array_filter(array_map('trim', explode(';', $schema)));
            
            echo "<h3>📋 Executing SQL Statements...</h3>\n";
            
            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    if (stripos($statement, 'ALTER TABLE') === 0) {
                        // Handle ALTER TABLE statements separately to avoid errors if columns already exist
                        try {
                            $this->conn->query($statement);
                            echo "✅ " . htmlspecialchars(substr($statement, 0, 50)) . "...\n";
                        } catch (Exception $e) {
                            if (strpos($e->getMessage(), 'Duplicate column name') === false) {
                                echo "⚠️ " . htmlspecialchars(substr($statement, 0, 50)) . "... - " . $e->getMessage() . "\n";
                            } else {
                                echo "✅ " . htmlspecialchars(substr($statement, 0, 50)) . "... (already exists)\n";
                            }
                        }
                    } else {
                        $this->conn->query($statement);
                        echo "✅ " . htmlspecialchars(substr($statement, 0, 50)) . "...\n";
                    }
                }
            }
            
            echo "<h3>🎉 Database Setup Complete!</h3>\n";
            echo "<p>All tables and initial data have been created successfully.</p>\n";
            
            // Test the setup
            $this->testDatabase();
            
        } catch (Exception $e) {
            echo "<h3>❌ Setup Failed</h3>\n";
            echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
            return false;
        }
        
        return true;
    }
    
    private function testDatabase() {
        echo "<h3>🔍 Testing Database Setup...</h3>\n";
        
        // Test tables exist
        $tables = ['users', 'projects', 'project_files', 'apps', 'app_versions', 
                  'user_apps', 'app_reviews', 'app_analytics', 'developer_wallets', 
                  'transactions', 'templates', 'template_files', 'categories', 'settings'];
        
        foreach ($tables as $table) {
            $result = $this->conn->query("SHOW TABLES LIKE '$table'");
            if ($result->num_rows > 0) {
                echo "✅ Table '$table' exists\n";
            } else {
                echo "❌ Table '$table' missing\n";
            }
        }
        
        // Test categories
        $result = $this->conn->query("SELECT COUNT(*) as count FROM categories");
        $count = $result->fetch_assoc()['count'];
        echo "✅ $count categories created\n";
        
        // Test settings
        $result = $this->conn->query("SELECT COUNT(*) as count FROM settings");
        $count = $result->fetch_assoc()['count'];
        echo "✅ $count settings created\n";
    }
    
    public function createSampleData() {
        echo "<h3>🎨 Creating Sample Data...</h3>\n";
        
        try {
            // Sample template
            $this->conn->query("
                INSERT INTO templates (name, description, category, framework, is_official) VALUES
                ('Hello World', 'A simple Hello World application template', 'Utilities', 'vanilla', true),
                ('Todo App', 'Basic todo application template', 'Productivity', 'vanilla', true),
                ('Calculator', 'Simple calculator template', 'Utilities', 'vanilla', true)
            ");
            
            // Sample template files
            $templates = [
                1 => ['index.html' => '<h1>Hello World!</h1>\n<p>Welcome to Bloxer!</p>'],
                2 => ['index.html' => '<h1>Todo App</h1>\n<ul id="todos"></ul>\n<input type="text" id="newTodo" placeholder="Add new todo...">'],
                3 => ['index.html' => '<h1>Calculator</h1>\n<input type="text" id="display" readonly>\n<div class="buttons"></div>']
            ];
            
            foreach ($templates as $template_id => $files) {
                foreach ($files as $file_name => $content) {
                    $this->conn->query("
                        INSERT INTO template_files (template_id, file_path, file_name, file_type, content)
                        VALUES ($template_id, '/$file_name', '$file_name', 'html', '" . addslashes($content) . "')
                    ");
                }
            }
            
            echo "✅ Sample templates created\n";
            
        } catch (Exception $e) {
            echo "⚠️ Sample data creation failed: " . $e->getMessage() . "\n";
        }
    }
}

// Handle setup request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup'])) {
    $setup = new DatabaseSetup();
    
    if ($setup->setupDatabase()) {
        if (isset($_POST['create_samples'])) {
            $setup->createSampleData();
        }
        
        echo "<p><a href='login.php'>Go to Login</a> | <a href='index.php'>Go to Homepage</a></p>";
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bloxer Platform Setup</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .setup-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 40px;
        }
        .form-group {
            margin: 20px 0;
        }
        .btn {
            margin: 10px 5px;
        }
        pre {
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 8px;
            padding: 15px;
            overflow-x: auto;
            font-size: 0.9rem;
        }
        .success { color: var(--success); }
        .error { color: var(--error); }
        .warning { color: var(--warning); }
    </style>
</head>
<body>
    <div class="auth-container setup-container">
        <div class="logo">
            <h1>Bloxer</h1>
            <p>Platform Setup</p>
        </div>

        <div class="form-group">
            <h3>🚀 Database Setup</h3>
            <p>This will create all necessary tables and initial data for the Bloxer platform.</p>
            <p><strong>⚠️ Warning:</strong> This will modify your database structure. Make sure you have a backup if needed.</p>
        </div>

        <form method="POST" action="">
            <div class="form-group">
                <label>
                    <input type="checkbox" name="create_samples" checked>
                    Create sample templates and data
                </label>
            </div>
            
            <button type="submit" name="setup" class="btn">Setup Database</button>
            <a href="login.php" class="btn" style="background: var(--input-bg); text-decoration: none; display: inline-block;">Cancel</a>
        </form>

        <div class="links">
            <a href="login.php">← Back to Login</a>
        </div>
    </div>
</body>
</html>
