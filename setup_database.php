<?php
/**
 * Database Setup Script for Bloxer Platform
 * Creates and initializes the database with all required tables
 */

require_once 'config/database.php';

class DatabaseSetup {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
    }
    
    public function setup() {
        try {
            echo "Setting up database...\n";
            
            // Read the clean database schema
            $schemaFile = __DIR__ . '/clean_database_schema.sql';
            if (!file_exists($schemaFile)) {
                throw new Exception("Database schema file not found: $schemaFile");
            }
            
            $schema = file_get_contents($schemaFile);
            
            // Split into individual statements
            $statements = array_filter(array_map('trim', explode(';', $schema)));
            
            echo "Creating database tables...\n";
            
            foreach ($statements as $statement) {
                if (!empty($statement) && !preg_match('/^--/', $statement)) {
                    try {
                        $this->db->execute($statement);
                        echo "✓ Executed: " . substr($statement, 0, 50) . "...\n";
                    } catch (Exception $e) {
                        echo "✗ Error in statement: " . $e->getMessage() . "\n";
                        echo "Statement: " . substr($statement, 0, 100) . "...\n";
                    }
                }
            }
            
            echo "\nDatabase setup completed successfully!\n";
            
            // Verify tables were created
            $this->verifyTables();
            
        } catch (Exception $e) {
            echo "Database setup failed: " . $e->getMessage() . "\n";
            return false;
        }
        
        return true;
    }
    
    private function verifyTables() {
        echo "\nVerifying created tables...\n";
        
        $expectedTables = [
            'login_attempts',
            'users',
            'projects',
            'project_files',
            'apps',
            'app_reviews',
            'app_ratings',
            'user_apps',
            'saved_apps',
            'offers',
            'offer_messages',
            'notifications',
            'notification_preferences',
            'developer_wallet',
            'categories',
            'popular_apps',
            'user_preferences',
            'version_files',
            'project_versions',
            'comments',
            'collaboration_invites',
            'collaboration_members',
            'chat_rooms',
            'chat_messages',
            'chat_participants'
        ];
        
        foreach ($expectedTables as $table) {
            if ($this->db->tableExists($table)) {
                echo "✓ Table '$table' exists\n";
            } else {
                echo "✗ Table '$table' missing\n";
            }
        }
    }
}


// Run setup
if (php_sapi_name() === 'cli') {
    echo "=== Bloxer Database Setup ===\n\n";
    $setup = new DatabaseSetup();
    $setup->setup();
} else {
    echo "<h2>Bloxer Database Setup</h2>";
    echo "<pre>";
    echo "=== Bloxer Database Setup ===\n\n";
    $setup = new DatabaseSetup();
    $setup->setup();
    echo "</pre>";
}
?>
