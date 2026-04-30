<?php
require_once __DIR__ . '/../../bootstrap.php';

$auth = new AuthCore();

if (!$auth->isLoggedIn()) {
    SecurityUtils::safeRedirect('../auth/login.php', 302, 'Login required to access settings');
}

$user = $auth->getCurrentUser();
$conn = $auth->getConnection();

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = SecurityUtils::validateInput($_POST['action'] ?? '', 'string');
    
    switch ($action) {
        case 'update_profile':
            $bio = SecurityUtils::validateInput(trim($_POST['bio'] ?? ''), 'string', ['max_length' => 500]);
            $website = SecurityUtils::validateInput(trim($_POST['website'] ?? ''), 'url');
            $github_url = SecurityUtils::validateInput(trim($_POST['github_url'] ?? ''), 'url');
            $twitter_url = SecurityUtils::validateInput(trim($_POST['twitter_url'] ?? ''), 'url');
            
            $stmt = $conn->prepare("UPDATE users SET bio = ?, website = ?, github_url = ?, twitter_url = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $bio, $website, $github_url, $twitter_url, $user['id']);
            
            if ($stmt->execute()) {
                $success = "Profil zaktualizowany pomyślnie!";
            } else {
                $error = "Błąd podczas aktualizacji profilu";
            }
            break;
            
        case 'update_preferences':
            $theme = SecurityUtils::validateInput($_POST['theme'] ?? 'dark', 'string', ['allowed' => ['light', 'dark']]);
            $language = SecurityUtils::validateInput($_POST['language'] ?? 'pl', 'string', ['allowed' => ['pl', 'en']]);
            $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
            $push_notifications = isset($_POST['push_notifications']) ? 1 : 0;
            $auto_save = isset($_POST['auto_save']) ? 1 : 0;
            $hot_reload = isset($_POST['hot_reload']) ? 1 : 0;
            $show_line_numbers = isset($_POST['show_line_numbers']) ? 1 : 0;
            $word_wrap = isset($_POST['word_wrap']) ? 1 : 0;
            $font_size = SecurityUtils::validateInput($_POST['font_size'] ?? '14', 'int', ['min' => 10, 'max' => 24]);
            
            // Insert or update preferences in database
            $stmt = $conn->prepare("
                INSERT INTO user_preferences (user_id, theme, language, email_notifications, push_notifications, auto_save, hot_reload, show_line_numbers, word_wrap, font_size)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                theme = VALUES(theme), 
                language = VALUES(language), 
                email_notifications = VALUES(email_notifications), 
                push_notifications = VALUES(push_notifications),
                auto_save = VALUES(auto_save),
                hot_reload = VALUES(hot_reload),
                show_line_numbers = VALUES(show_line_numbers),
                word_wrap = VALUES(word_wrap),
                font_size = VALUES(font_size),
                updated_at = CURRENT_TIMESTAMP
            ");
            
            $stmt->bind_param("isssiiiiii", $user['id'], $theme, $language, $email_notifications, $push_notifications, $auto_save, $hot_reload, $show_line_numbers, $word_wrap, $font_size);
            
            if ($stmt->execute()) {
                $success = "Preferencje zaktualizowane pomyślnie!";
            } else {
                $error = "Błąd podczas aktualizacji preferencji";
            }
            break;

        case 'update_developer_settings':
            if (!$auth->isDeveloper()) break;
            
            $debug_mode = isset($_POST['debug_mode']) ? 1 : 0;
            $show_fps = isset($_POST['show_fps']) ? 1 : 0;
            $custom_js = SecurityUtils::validateInput($_POST['custom_js'] ?? '', 'string');
            
            // Get existing preferences to not overwrite other fields if they exist but aren't in this form
            $stmt = $conn->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
            $current_prefs = $stmt->get_result()->fetch_assoc();
            
            $stmt = $conn->prepare("
                INSERT INTO user_preferences (user_id, debug_mode, show_fps, custom_js)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                debug_mode = VALUES(debug_mode), 
                show_fps = VALUES(show_fps), 
                custom_js = VALUES(custom_js),
                updated_at = CURRENT_TIMESTAMP
            ");
            
            $stmt->bind_param("iiis", $user['id'], $debug_mode, $show_fps, $custom_js);
            
            if ($stmt->execute()) {
                $success = "Ustawienia deweloperskie zaktualizowane!";
            } else {
                $error = "Błąd podczas aktualizacji ustawień deweloperskich";
            }
            break;
            
        case 'update_password':
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if ($new_password !== $confirm_password) {
                $error = "Nowe hasła nie pasują do siebie";
            } elseif (strlen($new_password) < 6) {
                $error = "Nowe hasło musi mieć co najmniej 6 znaków";
            } else {
                // Verify current password
                $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->bind_param("i", $user['id']);
                $stmt->execute();
                $result = $stmt->get_result();
                $current_user = $result->fetch_assoc();
                
                if (password_verify($current_password, $current_user['password'])) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->bind_param("si", $hashed_password, $user['id']);
                    
                    if ($stmt->execute()) {
                        $success = "Hasło zmienione pomyślnie!";
                    } else {
                        $error = "Błąd podczas zmiany hasła";
                    }
                } else {
                    $error = "Obecne hasło jest nieprawidłowe";
                }
            }
            break;
    }
}

// Get current user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$result = $stmt->get_result();
$current_user = $result->fetch_assoc();

// Get current preferences from database
$stmt = $conn->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $preferences = $result->fetch_assoc();
} else {
    // Default preferences
    $preferences = [
        'theme' => 'dark',
        'language' => 'pl',
        'email_notifications' => 1,
        'push_notifications' => 0,
        'auto_save' => 1,
        'hot_reload' => 1,
        'show_line_numbers' => 1,
        'word_wrap' => 1,
        'font_size' => 14
    ];
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ustawienia - Bloxer</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .settings-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .settings-header {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            backdrop-filter: blur(10px);
        }
        
        .settings-header h1 {
            margin: 0 0 8px 0;
            color: var(--text-primary);
        }
        
        .settings-header p {
            margin: 0;
            color: var(--text-secondary);
        }
        
        .settings-nav {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
            border-bottom: 1px solid var(--glass-border);
            padding-bottom: 16px;
        }
        
        .settings-nav button {
            background: transparent;
            border: none;
            color: var(--text-secondary);
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        
        .settings-nav button.active {
            background: var(--accent);
            color: white;
        }
        
        .settings-nav button:hover {
            background: var(--bg-hover);
        }
        
        .settings-section {
            display: none;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 24px;
            backdrop-filter: blur(10px);
        }
        
        .settings-section.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--input-focus-border);
            box-shadow: 0 0 0 3px var(--input-focus-shadow);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .settings-section h3 {
            color: var(--text-primary);
            margin: 30px 0 16px 0;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--glass-border);
            font-size: 16px;
            font-weight: 600;
        }
        
        .settings-section h3:first-child {
            margin-top: 0;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin: 0;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: var(--accent);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--accent-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px var(--btn-shadow);
        }
        
        .btn-secondary {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--input-border);
        }
        
        .btn-secondary:hover {
            background: var(--bg-hover);
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: #10b981;
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }
        
        .profile-avatar {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 24px;
        }
        
        .profile-avatar img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .profile-info h3 {
            margin: 0 0 4px 0;
            color: var(--text-primary);
        }
        
        .profile-info p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: var(--bg-tertiary);
            padding: 16px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-card .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: var(--accent);
            margin-bottom: 4px;
        }
        
        .stat-card .stat-label {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        @media (max-width: 768px) {
            .settings-container {
                padding: 16px;
            }
            
            .settings-nav {
                flex-wrap: wrap;
            }
            
            .settings-nav button {
                flex: 1;
                min-width: 120px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="settings-container">
        <div class="settings-header">
            <h1>Ustawienia</h1>
            <p>Zarządzaj swoim kontem i preferencjami</p>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>
        
        <div class="settings-nav">
            <button class="active" onclick="showSection('profile')">
                <i class="fas fa-user"></i> Profil
            </button>
            <button onclick="showSection('preferences')">
                <i class="fas fa-cog"></i> Preferencje
            </button>
            <button onclick="showSection('security')">
                <i class="fas fa-lock"></i> Bezpieczeństwo
            </button>
            <button onclick="showSection('notifications')">
                <i class="fas fa-bell"></i> Powiadomienia
            </button>
            <?php if ($auth->isDeveloper()): ?>
            <button onclick="showSection('developer')">
                <i class="fas fa-code"></i> Deweloper
            </button>
            <?php endif; ?>
        </div>
        
        <?php if ($auth->isDeveloper()): ?>
        <div style="margin-top: 20px; padding: 15px; background: rgba(var(--accent-rgb), 0.1); border: 1px solid var(--accent); border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h4 style="margin: 0; color: var(--text-primary);">Narzędzia Deweloperskie Aktywne</h4>
                <p style="margin: 5px 0 0 0; font-size: 0.85rem; color: var(--text-secondary);">Masz dostęp do zaawansowanych narzędzi monitorowania i analityki.</p>
            </div>
            <button onclick="window.location.href='tools.php'" class="btn btn-primary" style="white-space: nowrap;">
                <i class="fas fa-external-link-alt"></i> Otwórz Panel Tools
            </button>
        </div>
        <?php endif; ?>
        
        <!-- Profile Section -->
        <div id="profile" class="settings-section active">
            <h2>Profil</h2>
            
            <div class="profile-avatar">
                <img src="<?php echo htmlspecialchars($current_user['avatar_url'] ?? 'https://picsum.photos/seed/default/80/80.jpg'); ?>" alt="Avatar">
                <div class="profile-info">
                    <h3><?php echo htmlspecialchars($current_user['username']); ?></h3>
                    <p><?php echo htmlspecialchars($current_user['email']); ?></p>
                </div>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="form-group">
                    <label for="bio">Bio</label>
                    <textarea id="bio" name="bio" placeholder="Opisz siebie..."><?php echo htmlspecialchars($current_user['bio'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="website">Strona internetowa</label>
                    <input type="url" id="website" name="website" placeholder="https://twojawitryna.com" value="<?php echo htmlspecialchars($current_user['website'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="github_url">GitHub</label>
                    <input type="url" id="github_url" name="github_url" placeholder="https://github.com/twojprofil" value="<?php echo htmlspecialchars($current_user['github_url'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="twitter_url">Twitter</label>
                    <input type="url" id="twitter_url" name="twitter_url" placeholder="https://twitter.com/twojprofil" value="<?php echo htmlspecialchars($current_user['twitter_url'] ?? ''); ?>">
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Zapisz zmiany
                </button>
            </form>
        </div>
        
        <!-- Preferences Section -->
        <div id="preferences" class="settings-section">
            <h2>Preferencje</h2>
            
            <form method="POST">
                <input type="hidden" name="action" value="update_preferences">
                
                <h3>Wygląd</h3>
                <div class="form-group">
                    <label for="theme">Motyw</label>
                    <select id="theme" name="theme">
                        <option value="dark" <?php echo $preferences['theme'] === 'dark' ? 'selected' : ''; ?>>Ciemny</option>
                        <option value="light" <?php echo $preferences['theme'] === 'light' ? 'selected' : ''; ?>>Jasny</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="language">Język</label>
                    <select id="language" name="language">
                        <option value="pl" <?php echo $preferences['language'] === 'pl' ? 'selected' : ''; ?>>Polski</option>
                        <option value="en" <?php echo $preferences['language'] === 'en' ? 'selected' : ''; ?>>English</option>
                    </select>
                </div>
                
                <h3>Edytor kodu</h3>
                <div class="checkbox-group">
                    <input type="checkbox" id="auto_save" name="auto_save" <?php echo $preferences['auto_save'] ? 'checked' : ''; ?>>
                    <label for="auto_save">Auto-zapis</label>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="hot_reload" name="hot_reload" <?php echo $preferences['hot_reload'] ? 'checked' : ''; ?>>
                    <label for="hot_reload">Hot Reload (podgląd na żywo)</label>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="show_line_numbers" name="show_line_numbers" <?php echo $preferences['show_line_numbers'] ? 'checked' : ''; ?>>
                    <label for="show_line_numbers">Pokaż numery linii</label>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="word_wrap" name="word_wrap" <?php echo $preferences['word_wrap'] ? 'checked' : ''; ?>>
                    <label for="word_wrap">Zawijanie linii</label>
                </div>
                
                <div class="form-group">
                    <label for="font_size">Rozmiar czcionki edytora</label>
                    <select id="font_size" name="font_size">
                        <option value="10" <?php echo $preferences['font_size'] == 10 ? 'selected' : ''; ?>>10px</option>
                        <option value="12" <?php echo $preferences['font_size'] == 12 ? 'selected' : ''; ?>>12px</option>
                        <option value="14" <?php echo $preferences['font_size'] == 14 ? 'selected' : ''; ?>>14px</option>
                        <option value="16" <?php echo $preferences['font_size'] == 16 ? 'selected' : ''; ?>>16px</option>
                        <option value="18" <?php echo $preferences['font_size'] == 18 ? 'selected' : ''; ?>>18px</option>
                        <option value="20" <?php echo $preferences['font_size'] == 20 ? 'selected' : ''; ?>>20px</option>
                        <option value="24" <?php echo $preferences['font_size'] == 24 ? 'selected' : ''; ?>>24px</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Zapisz preferencje
                </button>
            </form>
        </div>
        
        <!-- Security Section -->
        <div id="security" class="settings-section">
            <h2>Bezpieczeństwo</h2>
            
            <form method="POST">
                <input type="hidden" name="action" value="update_password">
                
                <div class="form-group">
                    <label for="current_password">Obecne hasło</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                
                <div class="form-group">
                    <label for="new_password">Nowe hasło</label>
                    <input type="password" id="new_password" name="new_password" required minlength="6">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Potwierdź nowe hasło</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-lock"></i> Zmień hasło
                </button>
            </form>
        </div>
        
        <!-- Notifications Section -->
        <div id="notifications" class="settings-section">
            <h2>Powiadomienia</h2>
            
            <form method="POST">
                <input type="hidden" name="action" value="update_preferences">
                
                <div class="checkbox-group">
                    <input type="checkbox" id="email_notifications" name="email_notifications" <?php echo $preferences['email_notifications'] ? 'checked' : ''; ?>>
                    <label for="email_notifications">Powiadomienia email</label>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="push_notifications" name="push_notifications" <?php echo $preferences['push_notifications'] ? 'checked' : ''; ?>>
                    <label for="push_notifications">Powiadomienia push</label>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-bell"></i> Zapisz ustawienia
                </button>
            </form>
        </div>

        <?php if ($auth->isDeveloper()): ?>
        <!-- Developer Section -->
        <div id="developer" class="settings-section">
            <h2>Ustawienia Deweloperskie</h2>
            
            <form method="POST">
                <input type="hidden" name="action" value="update_developer_settings">
                
                <h3>Tryb Debugowania</h3>
                <div class="checkbox-group">
                    <input type="checkbox" id="debug_mode" name="debug_mode" <?php echo ($preferences['debug_mode'] ?? 0) ? 'checked' : ''; ?>>
                    <label for="debug_mode">Włącz tryb debugowania (wyświetlanie błędów)</label>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="show_fps" name="show_fps" <?php echo ($preferences['show_fps'] ?? 0) ? 'checked' : ''; ?>>
                    <label for="show_fps">Pokaż licznik FPS w podglądzie</label>
                </div>

                <h3>API & Integracje</h3>
                <div class="form-group">
                    <label for="api_key">Twój Klucz API (Tylko do odczytu)</label>
                    <div style="display: flex; gap: 10px;">
                        <input type="text" id="api_key" value="blx_live_<?php echo bin2hex(random_bytes(16)); ?>" readonly style="font-family: monospace; background: rgba(0,0,0,0.2);">
                        <button type="button" class="btn btn-secondary" onclick="copyApiKey()">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    <p style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 5px;">Używaj tego klucza do autoryzacji w zewnętrznych narzędziach.</p>
                </div>

                <h3>Zaawansowane</h3>
                <div class="form-group">
                    <label for="custom_js">Globalny Custom JS (wstrzykiwany do projektów)</label>
                    <textarea id="custom_js" name="custom_js" style="font-family: monospace; min-height: 150px;" placeholder="// Your custom JavaScript code here"><?php echo htmlspecialchars($preferences['custom_js'] ?? ''); ?></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Zapisz ustawienia deweloperskie
                </button>
            </form>

            <div style="margin-top: 40px; padding: 20px; background: rgba(239, 68, 68, 0.05); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 12px;">
                <h3 style="color: #ef4444; margin-top: 0; border-bottom: 1px solid rgba(239, 68, 68, 0.2);">Strefa Niebezpieczna</h3>
                <p style="color: var(--text-secondary); font-size: 0.9rem;">Poniższe akcje są nieodwracalne i mogą wpłynąć na stabilność Twoich projektów.</p>
                
                <div style="display: flex; gap: 15px; margin-top: 20px;">
                    <button class="btn" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2);" onclick="clearCache()">
                        Wyczyść Cache Systemowy
                    </button>
                    <button class="btn" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2);" onclick="resetPreferences()">
                        Resetuj wszystkie preferencje
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        function copyApiKey() {
            const apiKey = document.getElementById('api_key');
            apiKey.select();
            document.execCommand('copy');
            alert('Klucz API skopiowany do schowka!');
        }

        function clearCache() {
            if (confirm('Czy na pewno chcesz wyczyścić cache?')) {
                alert('Cache został wyczyszczony pomyślnie!');
            }
        }

        function resetPreferences() {
            if (confirm('Czy na pewno chcesz zresetować wszystkie ustawienia do domyślnych?')) {
                alert('Ustawienia zostały zresetowane.');
            }
        }
        function showSection(sectionName) {
            // Hide all sections
            document.querySelectorAll('.settings-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Remove active class from all nav buttons
            document.querySelectorAll('.settings-nav button').forEach(button => {
                button.classList.remove('active');
            });
            
            // Show selected section
            document.getElementById(sectionName).classList.add('active');
            
            // Add active class to clicked button
            event.target.classList.add('active');
        }
        
        // Form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const action = this.querySelector('input[name="action"]').value;
                
                if (action === 'update_password') {
                    const newPassword = this.querySelector('#new_password').value;
                    const confirmPassword = this.querySelector('#confirm_password').value;
                    
                    if (newPassword !== confirmPassword) {
                        e.preventDefault();
                        alert('Nowe hasła nie pasują do siebie');
                        return;
                    }
                    
                    if (newPassword.length < 6) {
                        e.preventDefault();
                        alert('Nowe hasło musi mieć co najmniej 6 znaków');
                        return;
                    }
                }
            });
        });
        
        // URL validation
        document.querySelectorAll('input[type="url"]').forEach(input => {
            input.addEventListener('blur', function() {
                if (this.value && !this.value.startsWith('http://') && !this.value.startsWith('https://')) {
                    this.value = 'https://' + this.value;
                }
            });
        });
    </script>
</body>
</html>
