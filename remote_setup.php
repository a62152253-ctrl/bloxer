<?php
/**
 * Remote Setup Script for Bloxer Platform
 * This script sets up the database and creates test users on the remote server
 */

echo "<h2>Bloxer Remote Setup</h2>";
echo "<pre>";

// Step 1: Setup Database
echo "=== Step 1: Database Setup ===\n";
echo "Setting up database tables...\n";

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
} else {
    die("Error: .env file not found\n");
}

// Database connection
try {
    $dsn = "mysql:host={$config['DB_HOST']};port={$config['DB_PORT']};dbname={$config['DB_NAME']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['DB_USER'], $config['DB_PASS'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    echo "✓ Connected to database successfully!\n";
    
    // Create tables using AuthCore
    require_once __DIR__ . '/bootstrap.php';
    $auth = new AuthCore();
    
    if ($auth->createTables()) {
        echo "✓ Database tables created successfully!\n";
    } else {
        echo "✗ Failed to create some tables\n";
    }
    
} catch (Exception $e) {
    echo "✗ Database setup failed: " . $e->getMessage() . "\n";
    echo "\nPlease check:\n";
    echo "1. Database credentials in .env file\n";
    echo "2. Database server is accessible\n";
    echo "3. Database user has proper permissions\n";
    echo "</pre>";
    exit;
}

echo "\n=== Step 2: Creating Test Users ===\n";

// Step 2: Create Test Users
try {
    // Create test user
    $result = $auth->register('testuser', 'test@example.com', 'password123', 'password123', 'user');
    if ($result['success']) {
        echo "✓ Test user created successfully\n";
        echo "  Username: testuser\n";
        echo "  Password: password123\n";
        echo "  Email: test@example.com\n";
    } else {
        echo "✗ Failed to create test user: " . implode(", ", $result['errors']) . "\n";
    }
    
    // Create test developer
    $result = $auth->register('devuser', 'dev@example.com', 'password123', 'password123', 'developer');
    if ($result['success']) {
        echo "✓ Test developer created successfully\n";
        echo "  Username: devuser\n";
        echo "  Password: password123\n";
        echo "  Email: dev@example.com\n";
    } else {
        echo "✗ Failed to create test developer: " . implode(", ", $result['errors']) . "\n";
    }
    
} catch (Exception $e) {
    echo "✗ User creation failed: " . $e->getMessage() . "\n";
}

echo "\n=== Step 3: Verification ===\n";

// Step 3: Verify Setup
try {
    // Check users table
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✓ Total users in database: " . $result['count'] . "\n";
    
    // Check login attempts table
    $stmt = $pdo->query("SHOW TABLES LIKE 'login_attempts'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Login attempts table exists\n";
    } else {
        echo "✗ Login attempts table missing\n";
    }
    
    // Check remember tokens table
    $stmt = $pdo->query("SHOW TABLES LIKE 'remember_tokens'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Remember tokens table exists\n";
    } else {
        echo "✗ Remember tokens table missing\n";
    }
    
} catch (Exception $e) {
    echo "✗ Verification failed: " . $e->getMessage() . "\n";
}

echo "\n=== Setup Complete! ===\n";
echo "Your Bloxer platform is now ready!\n\n";
echo "Test Accounts:\n";
echo "1. User Account:\n";
echo "   Username: testuser\n";
echo "   Password: password123\n";
echo "   Email: test@example.com\n\n";
echo "2. Developer Account:\n";
echo "   Username: devuser\n";
echo "   Password: password123\n";
echo "   Email: dev@example.com\n\n";
echo "Access your platform at: https://bloxer.eskp.pl/\n";
echo "Login page: https://bloxer.eskp.pl/controllers/auth/login.php\n";

echo "</pre>";
?>
