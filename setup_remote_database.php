<?php
/**
 * Remote Database Setup Script for Bloxer Platform
 * This script should be uploaded to the server and run there
 */

echo "<h2>Bloxer Remote Database Setup</h2>";
echo "<pre>";

// Load configuration
$env_file = __DIR__ . '/.env';
$config = [];

if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $config[trim($key)] = trim($value);
    }
}

// Database connection
try {
    $dsn = "mysql:host={$config['DB_HOST']};port={$config['DB_PORT']};dbname={$config['DB_NAME']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['DB_USER'], $config['DB_PASS'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    echo "Connected to database successfully!\n\n";
    
    // Read and execute schema
    $schemaFile = __DIR__ . '/complete_database_schema.sql';
    if (!file_exists($schemaFile)) {
        throw new Exception("Database schema file not found: $schemaFile");
    }
    
    $schema = file_get_contents($schemaFile);
    $statements = array_filter(array_map('trim', explode(';', $schema)));
    
    echo "Creating database tables...\n";
    
    foreach ($statements as $statement) {
        if (!empty($statement) && !preg_match('/^--/', $statement)) {
            try {
                $pdo->exec($statement);
                echo "✓ Executed: " . substr($statement, 0, 50) . "...\n";
            } catch (Exception $e) {
                echo "✗ Error: " . $e->getMessage() . "\n";
                echo "Statement: " . substr($statement, 0, 100) . "...\n";
            }
        }
    }
    
    echo "\nDatabase setup completed successfully!\n";
    
    // Verify tables
    echo "\nVerifying created tables...\n";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    $expectedTables = [
        'users', 'projects', 'project_files', 'apps', 'app_reviews', 'app_ratings',
        'user_apps', 'saved_apps', 'offers', 'offer_messages', 'notifications',
        'notification_preferences', 'developer_wallet', 'categories', 'popular_apps',
        'user_preferences', 'version_files', 'project_versions', 'comments',
        'collaboration_invites', 'collaboration_members', 'chat_rooms',
        'chat_messages', 'chat_participants'
    ];
    
    foreach ($expectedTables as $table) {
        if (in_array($table, $tables)) {
            echo "✓ Table '$table' exists\n";
        } else {
            echo "✗ Table '$table' missing\n";
        }
    }
    
    echo "\n=== Setup Complete! ===\n";
    echo "Your Bloxer platform is now ready!\n";
    echo "You can access it at: https://bloxer.eskp.pl/\n";
    
} catch (Exception $e) {
    echo "Database setup failed: " . $e->getMessage() . "\n";
    echo "\nPlease check:\n";
    echo "1. Database credentials in .env file\n";
    echo "2. Database server is accessible\n";
    echo "3. Database user has proper permissions\n";
}

echo "</pre>";
?>
