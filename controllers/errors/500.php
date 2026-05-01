<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Błąd serwera - Bloxer</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/reboot.css">
</head>
<body class="app-error">
    <div class="error-container">
        <div class="error-content">
            <div class="error-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h1>500 - Błąd serwera</h1>
            <p>Wystąpił wewnętrzny błąd serwera. Pracujemy nad rozwiązaniem problemu.</p>
            
            <div class="error-actions">
                <a href="../marketplace/marketplace.php" class="btn btn-primary">
                    <i class="fas fa-home"></i>
                    Strona główna
                </a>
                <button onclick="location.reload()" class="btn btn-secondary">
                    <i class="fas fa-redo"></i>
                    Odśwież stronę
                </button>
            </div>
            
            <div class="error-info">
                <h3>Co możesz zrobić:</h3>
                <ul>
                    <li>Spróbuj odświeżyć stronę</li>
                    <li>Sprawdzić połączenie z internetem</li>
                    <li>Poczekaj chwilę i spróbuj ponownie</li>
                    <li>Skontaktuj się z pomocą techniczną</li>
                </ul>
            </div>
            
            <?php if (AppConfig::getInstance()->isDebug()): ?>
            <div class="debug-info">
                <h3>Informacje diagnostyczne:</h3>
                <p><strong>Czas:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
                <p><strong>URL:</strong> <?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? ''); ?></p>
                <p><strong>Metoda:</strong> <?php echo htmlspecialchars($_SERVER['REQUEST_METHOD'] ?? ''); ?></p>
                <?php
                if (isset($error_message)) {
                    echo '<p><strong>Błąd:</strong> ' . htmlspecialchars($error_message) . '</p>';
                }
                ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Auto-redirect after 15 seconds
        setTimeout(function() {
            window.location.href = '../marketplace/marketplace.php';
        }, 15000);
    </script>
</body>
</html>
