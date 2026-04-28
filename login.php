<?php
require_once 'mainlogincore.php';

$auth = new AuthCore();

if ($auth->isLoggedIn()) {
    if ($auth->isDeveloper()) {
        header('Location: dashboard.php');
    } else {
        header('Location: marketplace.php');
    }
    exit();
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $user_type = $_POST['user_type'] ?? 'user';
    
    $result = $auth->login($username, $password);
    
    if ($result['success']) {
        // Redirect based on user type
        $user = $auth->getCurrentUser();
        if ($user && $user['user_type'] === 'developer') {
            header('Location: dashboard.php');
        } else {
            header('Location: marketplace.php');
        }
        exit();
    } else {
        $errors = $result['errors'];
    }
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logowanie - Bloxer</title>
    <link rel="stylesheet" href="style.css">
    <meta name="description" content="Zaloguj się do platformy Bloxer - twórz i publikuj aplikacje web">
    <meta name="robots" content="noindex, nofollow">
</head>
<body>
    <a href="#main" class="skip-to-content">Przejdź do treści</a>
    <div class="auth-container" id="main">
        <div class="logo">
            <h1>Bloxer</h1>
            <p>Zaloguj się do swojego konta</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php if (count($errors) === 1): ?>
                    <?php echo htmlspecialchars($errors[0]); ?>
                <?php else: ?>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="loginForm">
            <input type="hidden" name="csrf_token" value="<?php echo $auth->getCSRFToken(); ?>">
            <div class="form-group">
                <label for="username">Nazwa użytkownika lub email</label>
                <div class="input-wrapper">
                    <input type="text" id="username" name="username" required 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                           autocomplete="username"
                           placeholder="Wpisz nazwę użytkownika lub email"
                           oninput="validateUsername(this.value)">
                    <span class="input-icon">👤</span>
                </div>
                <div id="username-feedback" class="field-feedback"></div>
            </div>

            <div class="form-group">
                <label for="password">Hasło</label>
                <div class="input-wrapper">
                    <input type="password" id="password" name="password" required 
                           autocomplete="current-password"
                           placeholder="Wpisz swoje hasło"
                           oninput="validatePassword(this.value)">
                    <button type="button" class="password-toggle" onclick="togglePassword('password')">
                        <span id="password-toggle-icon">👁️</span>
                    </button>
                </div>
                <div id="password-feedback" class="field-feedback"></div>
            </div>

            <div class="form-options">
                <label class="checkbox-label">
                    <input type="checkbox" name="remember" id="remember">
                    <span class="checkmark"></span>
                    Zapamiętaj mnie
                    <small class="option-hint">Zaufane urządzenie</small>
                </label>
                <a href="forgotpassword.php" class="forgot-link">Zapomniałeś hasła?</a>
            </div>

            <div class="form-group">
                <label>Typ konta</label>
                <div class="radio-group">
                    <label class="radio-label">
                        <input type="radio" name="user_type" value="user" checked>
                        <span class="radio-text">Użytkownik</span>
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="user_type" value="developer">
                        <span class="radio-text">Developer</span>
                    </label>
                </div>
            </div>

            <button type="submit" class="btn" id="loginBtn">
                <span class="btn-text">Zaloguj się</span>
                <span class="btn-loader" style="display: none;">⏳</span>
            </button>
        </form>

        <div class="links">
            <a href="register.php">Nie masz konta? Zarejestruj się</a>
        </div>
    </div>

    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(inputId + '-toggle-icon');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.textContent = '🙈';
            } else {
                input.type = 'password';
                icon.textContent = '👁️';
            }
        }

        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('loginBtn');
            const btnText = btn.querySelector('.btn-text');
            const btnLoader = btn.querySelector('.btn-loader');
            
            btnText.style.display = 'none';
            btnLoader.style.display = 'inline';
            btn.disabled = true;
        });

        // Real-time validation functions
        function validateUsername(username) {
            const feedback = document.getElementById('username-feedback');
            
            if (username.length === 0) {
                feedback.innerHTML = '';
                return;
            }
            
            if (username.length < 3) {
                feedback.innerHTML = '<span class="error">Minimum 3 znaki</span>';
                return;
            }
            
            // Basic email validation check
            if (username.includes('@')) {
                if (!username.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                    feedback.innerHTML = '<span class="error">Nieprawidłowy format email</span>';
                    return;
                }
            }
            
            feedback.innerHTML = '<span class="success">✓</span>';
        }
        
        function validatePassword(password) {
            const feedback = document.getElementById('password-feedback');
            
            if (password.length === 0) {
                feedback.innerHTML = '';
                return;
            }
            
            if (password.length < 6) {
                feedback.innerHTML = '<span class="error">Minimum 6 znaków</span>';
                return;
            }
            
            feedback.innerHTML = '<span class="success">✓</span>';
        }
        
        // Enhanced form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            
            if (username.length < 3) {
                e.preventDefault();
                document.getElementById('username-feedback').innerHTML = '<span class="error">Wprowadź nazwę użytkownika lub email</span>';
                return;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                document.getElementById('password-feedback').innerHTML = '<span class="error">Wprowadź hasło</span>';
                return;
            }
            
            const btn = document.getElementById('loginBtn');
            const btnText = btn.querySelector('.btn-text');
            const btnLoader = btn.querySelector('.btn-loader');
            
            btnText.style.display = 'none';
            btnLoader.style.display = 'inline';
            btn.disabled = true;
        });
        
        // Auto-focus username field
        document.getElementById('username').focus();
        
        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                const activeElement = document.activeElement;
                if (activeElement.id === 'username') {
                    e.preventDefault();
                    document.getElementById('password').focus();
                }
            }
        });
    </script>
</body>
</html>
