<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$stored_hash = '$2y$12$5C1RwA4VN5iwGdmWVQACr.lB4I5ufmr8hX5q1D59hOmhJEJIPpqtO';
$password = 'Dev123456';

echo "Testing password verification...\n";
echo "Stored hash: $stored_hash\n";
echo "Password to test: $password\n";

if (password_verify($password, $stored_hash)) {
    echo "Password verification: SUCCESS\n";
} else {
    echo "Password verification: FAILED\n";
    
    // Test with common variations
    $test_passwords = ['Dev123456', 'dev123456', 'DEV123456', 'Dev123456!', 'Password123'];
    foreach ($test_passwords as $test_pwd) {
        if (password_verify($test_pwd, $stored_hash)) {
            echo "Found matching password: $test_pwd\n";
            break;
        }
    }
}

// Create new hash for reference
echo "\nNew hash for 'Dev123456': " . password_hash('Dev123456', PASSWORD_DEFAULT) . "\n";
?>
