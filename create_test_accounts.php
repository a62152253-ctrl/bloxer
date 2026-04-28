<?php
require_once 'mainlogincore.php';

$auth = new AuthCore();
$conn = $auth->getConnection();

// Test accounts data
$testAccounts = [
    // Developer accounts
    [
        'username' => 'dev_john',
        'email' => 'john@dev.com',
        'password' => 'Dev123456',
        'user_type' => 'developer',
        'bio' => 'Full-stack developer specializing in React and Node.js applications',
        'avatar_url' => 'https://picsum.photos/seed/devjohn/150/150.jpg',
        'social_links' => json_encode(['github' => 'github.com/johndev', 'twitter' => '@johndev'])
    ],
    [
        'username' => 'dev_sarah',
        'email' => 'sarah@dev.com',
        'password' => 'Dev123456',
        'user_type' => 'developer',
        'bio' => 'Frontend developer passionate about creating beautiful user interfaces',
        'avatar_url' => 'https://picsum.photos/seed/devsarah/150/150.jpg',
        'social_links' => json_encode(['github' => 'github.com/sarahdev', 'portfolio' => 'sarahdev.com'])
    ],
    [
        'username' => 'dev_mike',
        'email' => 'mike@dev.com',
        'password' => 'Dev123456',
        'user_type' => 'developer',
        'bio' => 'Game developer and creative coder',
        'avatar_url' => 'https://picsum.photos/seed/devmike/150/150.jpg',
        'social_links' => json_encode(['github' => 'github.com/mikedev', 'itchio' => 'mikedev.itch.io'])
    ],
    
    // Regular user accounts
    [
        'username' => 'user_anna',
        'email' => 'anna@user.com',
        'password' => 'User123456',
        'user_type' => 'user',
        'bio' => 'Tech enthusiast and app lover',
        'avatar_url' => 'https://picsum.photos/seed/useranna/150/150.jpg',
        'social_links' => null
    ],
    [
        'username' => 'user_peter',
        'email' => 'peter@user.com',
        'password' => 'User123456',
        'user_type' => 'user',
        'bio' => 'Productivity apps user',
        'avatar_url' => 'https://picsum.photos/seed/userpeter/150/150.jpg',
        'social_links' => null
    ],
    [
        'username' => 'user_emma',
        'email' => 'emma@user.com',
        'password' => 'User123456',
        'user_type' => 'user',
        'bio' => 'Gaming and entertainment apps fan',
        'avatar_url' => 'https://picsum.photos/seed/useremma/150/150.jpg',
        'social_links' => null
    ],
    
    // Admin-like developer account
    [
        'username' => 'admin_dev',
        'email' => 'admin@bloxer.com',
        'password' => 'Admin123456',
        'user_type' => 'developer',
        'bio' => 'Platform administrator and senior developer',
        'avatar_url' => 'https://picsum.photos/seed/admindev/150/150.jpg',
        'social_links' => json_encode(['github' => 'github.com/bloxer', 'linkedin' => 'linkedin.com/in/bloxer'])
    ]
];

echo "<h1>Tworzenie kont testowych dla Bloxer</h1>\n";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; }
    .error { color: red; }
    .account { border: 1px solid #ddd; padding: 10px; margin: 10px 0; border-radius: 5px; }
    .account h3 { margin: 0 0 5px 0; }
    .details { font-size: 0.9em; color: #666; }
</style>";

$createdCount = 0;
$skippedCount = 0;

foreach ($testAccounts as $account) {
    echo "<div class='account'>";
    echo "<h3>" . htmlspecialchars($account['username']) . "</h3>";
    
    // Check if account already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $account['username'], $account['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "<p class='error'>Konto już istnieje - pominięto</p>";
        $skippedCount++;
    } else {
        // Create account using the registration method
        $result = $auth->register(
            $account['username'], 
            $account['email'], 
            $account['password'], 
            $account['password'], 
            $account['user_type']
        );
        
        if ($result['success']) {
            // Get the user ID to update additional fields
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->bind_param("s", $account['username']);
            $stmt->execute();
            $userResult = $stmt->get_result();
            $user = $userResult->fetch_assoc();
            
            // Update additional profile fields
            $updateStmt = $conn->prepare("UPDATE users SET bio = ?, avatar_url = ?, social_links = ?, developer_rating = ?, total_apps = ?, total_earnings = ? WHERE id = ?");
            $rating = $account['user_type'] === 'developer' ? 4.5 : 0.00;
            $totalApps = $account['user_type'] === 'developer' ? rand(1, 10) : 0;
            $totalEarnings = $account['user_type'] === 'developer' ? rand(100, 5000) : 0.00;
            
            $socialLinks = $account['social_links'];
            $updateStmt->bind_param("ssssddi", 
                $account['bio'], 
                $account['avatar_url'], 
                $socialLinks, 
                $rating, 
                $totalApps, 
                $totalEarnings, 
                $user['id']
            );
            $updateStmt->execute();
            
            // Create developer wallet for developer accounts
            if ($account['user_type'] === 'developer') {
                $walletStmt = $conn->prepare("INSERT INTO developer_wallets (user_id, balance, total_earned, total_withdrawn) VALUES (?, ?, ?, ?)");
                $balance = $totalEarnings * 0.7; // Available balance after platform fees
                $totalWithdrawn = 0.00;
                $userId = $user['id'];
                $walletTotalEarned = $totalEarnings;
                $walletStmt->bind_param("iddi", $userId, $balance, $walletTotalEarned, $totalWithdrawn);
                $walletStmt->execute();
            }
            
            echo "<p class='success'>✓ Konto utworzone pomyślnie</p>";
            echo "<div class='details'>";
            echo "<strong>Email:</strong> " . htmlspecialchars($account['email']) . "<br>";
            echo "<strong>Hasło:</strong> " . htmlspecialchars($account['password']) . "<br>";
            echo "<strong>Typ:</strong> " . ($account['user_type'] === 'developer' ? 'Developer' : 'Użytkownik') . "<br>";
            if ($account['user_type'] === 'developer') {
                echo "<strong>Ocena:</strong> {$rating}/5.0<br>";
                echo "<strong>Aplikacje:</strong> {$totalApps}<br>";
                echo "<strong>Zarobki:</strong> \${$totalEarnings}<br>";
                echo "<strong>Portfel:</strong> \${$balance}<br>";
            }
            echo "</div>";
            $createdCount++;
        } else {
            echo "<p class='error'>✗ Błąd tworzenia konta: " . implode(', ', $result['errors']) . "</p>";
        }
    }
    
    echo "</div>";
}

echo "<h2>Podsumowanie</h2>";
echo "<p><strong>Utworzone konta:</strong> {$createdCount}</p>";
echo "<p><strong>Pominięte konta:</strong> {$skippedCount}</p>";

echo "<h2>Dane logowania</h2>";
echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr><th>Nazwa użytkownika</th><th>Email</th><th>Hasło</th><th>Typ</th></tr>";

foreach ($testAccounts as $account) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($account['username']) . "</td>";
    echo "<td>" . htmlspecialchars($account['email']) . "</td>";
    echo "<td>" . htmlspecialchars($account['password']) . "</td>";
    echo "<td>" . ($account['user_type'] === 'developer' ? 'Developer' : 'Użytkownik') . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<p><a href='login.php'>Przejdź do strony logowania</a></p>";
?>
