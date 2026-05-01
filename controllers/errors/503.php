<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usługa niedostępna - Bloxer</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/reboot.css">
</head>
<body class="app-error">
    <div class="error-container">
        <div class="error-content">
            <div class="error-icon">
                <i class="fas fa-tools"></i>
            </div>
            <h1>503 - Usługa niedostępna</h1>
            <p>Platforma Bloxer jest obecnie w trybie konserwacji. Wróć wkrótce.</p>
            
            <div class="error-actions">
                <button onclick="location.reload()" class="btn btn-primary">
                    <i class="fas fa-redo"></i>
                    Spróbuj ponownie
                </button>
                <a href="mailto:support@bloxer.com" class="btn btn-secondary">
                    <i class="fas fa-envelope"></i>
                    Kontakt z supportem
                </a>
            </div>
            
            <div class="maintenance-info">
                <h3>Prace konserwacyjne:</h3>
                <ul>
                    <li>Aktualizacja systemu</li>
                    <li>Poprawa wydajności</li>
                    <li>Dodawanie nowych funkcji</li>
                    <li>Zabezpieczanie platformy</li>
                </ul>
                
                <p class="estimated-time">
                    <strong>Szacowany czas trwania:</strong> kilka minut
                </p>
            </div>
            
            <div class="status-updates">
                <h3>Śledź postępy:</h3>
                <div class="update-item">
                    <i class="fas fa-check-circle text-success"></i>
                    <span>Rozpoczęcie prac konserwacyjnych</span>
                </div>
                <div class="update-item">
                    <i class="fas fa-spinner fa-spin text-warning"></i>
                    <span>Trwa aktualizacja systemu...</span>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-refresh every 30 seconds
        setInterval(function() {
            fetch(window.location.href)
                .then(response => {
                    if (response.status === 200) {
                        location.reload();
                    }
                })
                .catch(() => {
                    // Still in maintenance
                });
        }, 30000);
        
        // Update time
        function updateTime() {
            const now = new Date();
            document.getElementById('current-time').textContent = now.toLocaleTimeString('pl-PL');
        }
        
        if (document.getElementById('current-time')) {
            updateTime();
            setInterval(updateTime, 1000);
        }
    </script>
</body>
</html>
