<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Strona nie znaleziona - Bloxer</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/reboot.css">
</head>
<body class="app-error">
    <div class="error-container">
        <div class="error-content">
            <div class="error-icon">
                <i class="fas fa-search"></i>
            </div>
            <h1>404 - Strona nie znaleziona</h1>
            <p>Przykro nam, ale strona której szukasz nie istnieje.</p>
            
            <div class="error-actions">
                <a href="../marketplace/marketplace.php" class="btn btn-primary">
                    <i class="fas fa-home"></i>
                    Strona główna
                </a>
                <a href="../auth/login.php" class="btn btn-secondary">
                    <i class="fas fa-sign-in-alt"></i>
                    Zaloguj się
                </a>
            </div>
            
            <div class="error-suggestions">
                <h3>Możesz spróbować:</h3>
                <ul>
                    <li>Sprawdzić poprawność adresu URL</li>
                    <li>Wrócić do poprzedniej strony</li>
                    <li>Skorzystać z wyszukiwarki</li>
                    <li>Kontaktować się z pomocą techniczną</li>
                </ul>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-redirect after 30 seconds
        setTimeout(function() {
            window.location.href = '../marketplace/marketplace.php';
        }, 30000);
    </script>
</body>
</html>
