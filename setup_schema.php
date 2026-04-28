<?php
require_once 'mainlogincore.php';

$auth = new AuthCore();
$conn = $auth->getConnection();

echo "<h1>Aktualizacja schematu bazy danych</h1>";

// First, create the basic users table if it doesn't exist
$auth->createTables();

// Read and execute the schema updates
$sql = file_get_contents('database_schema.sql');

// Split the SQL into individual statements
$statements = explode(';', $sql);

$successCount = 0;
$errorCount = 0;

foreach ($statements as $statement) {
    $statement = trim($statement);
    if (!empty($statement)) {
        try {
            if ($conn->query($statement)) {
                $successCount++;
                echo "<p style='color: green;'>✓ Wykonano: " . htmlspecialchars(substr($statement, 0, 50)) . "...</p>";
            }
        } catch (Exception $e) {
            // Ignore errors for columns that already exist
            if (strpos($e->getMessage(), 'Duplicate column name') !== false || 
                strpos($e->getMessage(), 'already exists') !== false) {
                echo "<p style='color: orange;'>⚠ Pominięto (już istnieje): " . htmlspecialchars(substr($statement, 0, 50)) . "...</p>";
            } else {
                echo "<p style='color: red;'>✗ Błąd: " . htmlspecialchars($e->getMessage()) . "</p>";
                $errorCount++;
            }
        }
    }
}

echo "<h2>Podsumowanie</h2>";
echo "<p><strong>Pomyślnie wykonane:</strong> {$successCount}</p>";
echo "<p><strong>Błędy:</strong> {$errorCount}</p>";

echo "<p><a href='create_test_accounts.php'>Przejdź do tworzenia kont testowych</a></p>";
?>
