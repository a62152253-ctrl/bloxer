<?php
/**
 * Run remember_tokens table migration
 */

require_once __DIR__ . '/../bootstrap.php';

try {
    $db = DatabaseConfig::getInstance();
    $conn = $db->getConnection();
    
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    // Read and execute the migration
    $migration_sql = file_get_contents(__DIR__ . '/create_remember_tokens_table.sql');
    
    if ($migration_sql === false) {
        throw new Exception("Failed to read migration file");
    }
    
    // Execute the migration
    try {
        $conn->exec($migration_sql);
        echo "✅ Migration executed successfully!\n";
        echo "Table 'remember_tokens' has been created.\n";
    } catch (PDOException $e) {
        throw new Exception("Migration failed: " . $e->getMessage());
    }
    
} catch (Exception $e) {
    echo "❌ Migration error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "🎉 Migration completed successfully!\n";
?>
