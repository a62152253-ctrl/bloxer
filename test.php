<?php
echo "<h1>Bloxer Server Test</h1>";
echo "<pre>";

echo "PHP Version: " . phpversion() . "\n";
echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "\n";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";

echo "\n=== Database Connection Test ===\n";

// Load configuration
$env_file = __DIR__ . '/.env';
$config = [];

if (file_exists($env_file)) {
    echo ".env file found\n";
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $config[trim($key)] = trim($value);
    }
    
    echo "Database Host: " . $config['DB_HOST'] . "\n";
    echo "Database Name: " . $config['DB_NAME'] . "\n";
    echo "Database Port: " . $config['DB_PORT'] . "\n";
    
    try {
        $dsn = "mysql:host={$config['DB_HOST']};port={$config['DB_PORT']};dbname={$config['DB_NAME']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['DB_USER'], $config['DB_PASS'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        
        echo "✓ Database connection successful!\n";
        
        // Test query
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo "Tables in database: " . count($tables) . "\n";
        
        if (empty($tables)) {
            echo "Database is empty - setup needed\n";
        } else {
            echo "Existing tables:\n";
            foreach ($tables as $table) {
                echo "- $table\n";
            }
        }
        
    } catch (PDOException $e) {
        echo "✗ Database connection failed: " . $e->getMessage() . "\n";
    }
} else {
    echo ".env file not found\n";
}

echo "\n=== File Permissions Test ===\n";

$test_files = [
    'complete_database_schema.sql',
    'setup_remote_database.php',
    'index.php'
];

foreach ($test_files as $file) {
    if (file_exists($file)) {
        echo "✓ $file exists\n";
        if (is_readable($file)) {
            echo "  - Readable\n";
        } else {
            echo "  - Not readable\n";
        }
    } else {
        echo "✗ $file missing\n";
    }
}

echo "\n=== Setup Links ===\n";
echo "<a href='setup_remote_database.php'>Run Database Setup</a><br>";
echo "<a href='index.php'>Go to Main Page</a><br>";

echo "</pre>";
?>
