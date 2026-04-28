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

// Handle AJAX availability checks
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_username'])) {
    header('Content-Type: application/json');
    $username = trim($_POST['check_username']);
    
    $stmt = $auth->getConnection()->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo json_encode(['available' => $result->num_rows === 0]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_email'])) {
    header('Content-Type: application/json');
    $email = trim($_POST['check_email']);
    
    $stmt = $auth->getConnection()->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo json_encode(['available' => $result->num_rows === 0]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $user_type = $_POST['user_type'] ?? 'user';
    
    $result = $auth->register($username, $email, $password, $confirm_password, $user_type);
    
    if ($result['success']) {
        $success = $result['message'];
        $_POST = []; // Clear form data
        
        // Auto redirect after successful registration
        header('refresh:3;url=login.php');
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
    <title>Rejestracja - Bloxer</title>
    <link rel="stylesheet" href="style.css">
    <meta name="description" content="Zarejestruj się na platformie Bloxer - twórz i publikuj aplikacje web">
    <meta name="robots" content="noindex, nofollow">
</head>
<body>
    <a href="#main" class="skip-to-content">Przejdź do treści</a>
    <div class="auth-container" id="main">
        <div class="logo">
            <h1>Bloxer</h1>
            <p>Stwórz nowe konto</p>
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

        <form method="POST" action="" id="registerForm">
            <input type="hidden" name="csrf_token" value="<?php echo $auth->getCSRFToken(); ?>">
            <div class="form-group">
                <label for="username">Nazwa użytkownika</label>
                <div class="input-wrapper">
                    <input type="text" id="username" name="username" required 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                           pattern="[a-zA-Z0-9_]{3,20}"
                           title="Nazwa użytkownika może zawierać tylko litery, cyfry i podkreślenia (3-20 znaków)"
                           autocomplete="username"
                           placeholder="Wpisz nazwę użytkownika"
                           onkeyup="checkUsernameAvailability(this.value)">
                    <span class="input-icon">👤</span>
                </div>
                <div id="username-feedback" class="field-feedback"></div>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <div class="input-wrapper">
                    <input type="email" id="email" name="email" required 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                           autocomplete="email"
                           placeholder="Wpisz adres email"
                           onkeyup="checkEmailAvailability(this.value)">
                    <span class="input-icon">📧</span>
                </div>
                <div id="email-feedback" class="field-feedback"></div>
            </div>

            <div class="form-group">
                <label for="password">Hasło</label>
                <div class="input-wrapper">
                    <input type="password" id="password" name="password" required 
                           minlength="6"
                           autocomplete="new-password"
                           placeholder="Wpisz hasło"
                           onkeyup="checkPasswordStrength(this.value)">
                    <button type="button" class="password-toggle" onclick="togglePassword('password')">
                        <span id="password-toggle-icon">👁️</span>
                    </button>
                </div>
                <div id="password-strength" class="password-strength"></div>
                <div class="password-requirements">
                    <small>Hasło musi zawierać co najmniej 6 znaków</small>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Potwierdź hasło</label>
                <div class="input-wrapper">
                    <input type="password" id="confirm_password" name="confirm_password" required 
                           autocomplete="new-password"
                           placeholder="Potwierdź hasło">
                    <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                        <span id="confirm_password-toggle-icon">👁️</span>
                    </button>
                </div>
                <div id="confirm-feedback" class="field-feedback"></div>
            </div>

            <div class="form-group">
                <label>Typ konta</label>
                <div class="radio-group">
                    <label class="radio-label">
                        <input type="radio" name="user_type" value="user" checked>
                        <span class="radio-text">Użytkownik</span>
                        <small class="radio-desc">Przeglądaj i używaj aplikacji</small>
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="user_type" value="developer">
                        <span class="radio-text">Developer</span>
                        <small class="radio-desc">Twórz i publikuj aplikacje</small>
                    </label>
                </div>
            </div>

            <div class="form-group terms-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="terms" id="terms" required>
                    <span class="checkmark"></span>
                    Akceptuję <a href="#" class="terms-link">regulamin</a> i <a href="#" class="terms-link">politykę prywatności</a>
                </label>
            </div>

            <button type="submit" class="btn" id="registerBtn">
                <span class="btn-text">Zarejestruj się</span>
                <span class="btn-loader" style="display: none;">⏳</span>
            </button>
        </form>

        <div class="links">
            <a href="login.php">Masz już konto? Zaloguj się</a>
        </div>
    </div>

    <script>
        let usernameTimeout, emailTimeout;
        
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

        function checkPasswordStrength(password) {
            const strengthDiv = document.getElementById('password-strength');
            let strength = 0;
            
            if (password.length >= 6) strength++;
            if (password.length >= 10) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            strengthDiv.className = 'password-strength';
            
            if (strength <= 2) {
                strengthDiv.textContent = 'Słabe hasło';
                strengthDiv.classList.add('weak');
            } else if (strength <= 3) {
                strengthDiv.textContent = 'Średnie hasło';
                strengthDiv.classList.add('medium');
            } else {
                strengthDiv.textContent = 'Silne hasło';
                strengthDiv.classList.add('strong');
            }
        }

        function checkUsernameAvailability(username) {
            const feedback = document.getElementById('username-feedback');
            clearTimeout(usernameTimeout);
            
            if (username.length < 3) {
                feedback.innerHTML = '<span class="error">Nazwa użytkownika musi mieć co najmniej 3 znaki</span>';
                return;
            }
            
            if (!/^[a-zA-Z0-9_]+$/.test(username)) {
                feedback.innerHTML = '<span class="error">Dozwolone tylko litery, cyfry i podkreślenia</span>';
                return;
            }
            
            // Check for reserved names
            const reservedNames = ['admin', 'root', 'system', 'api', 'www', 'mail', 'ftp', 'support', 'help', 'info', 'contact'];
            if (reservedNames.includes(username.toLowerCase())) {
                feedback.innerHTML = '<span class="error">Ta nazwa użytkownika jest zarezerwowana</span>';
                return;
            }
            
            feedback.innerHTML = '<span class="checking">Sprawdzanie...</span>';
            
            usernameTimeout = setTimeout(() => {
                // Make actual API call to check availability
                fetch('register.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `check_username=${encodeURIComponent(username)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.available) {
                        feedback.innerHTML = '<span class="success">Nazwa użytkownika dostępna</span>';
                    } else {
                        feedback.innerHTML = '<span class="error">Ta nazwa użytkownika jest już zajęta</span>';
                    }
                })
                .catch(error => {
                    // Fallback to client-side validation
                    feedback.innerHTML = '<span class="success">Nazwa użytkownika dostępna</span>';
                });
            }, 800);
        }

        function checkEmailAvailability(email) {
            const feedback = document.getElementById('email-feedback');
            clearTimeout(emailTimeout);
            
            if (!email.includes('@') || email.length < 5) {
                feedback.innerHTML = '';
                return;
            }
            
            // Basic email format validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                feedback.innerHTML = '<span class="error">Nieprawidłowy format email</span>';
                return;
            }
            
            // Check for disposable email domains
            const disposableDomains = ['tempmail.org', '10minutemail.com', 'guerrillamail.com', 'mailinator.com'];
            const domain = email.split('@')[1].toLowerCase();
            if (disposableDomains.some(d => domain.includes(d))) {
                feedback.innerHTML = '<span class="error">Użyj prawdziwego adresu email</span>';
                return;
            }
            
            feedback.innerHTML = '<span class="checking">Sprawdzanie...</span>';
            
            emailTimeout = setTimeout(() => {
                // Make actual API call to check availability
                fetch('register.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `check_email=${encodeURIComponent(email)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.available) {
                        feedback.innerHTML = '<span class="success">Adres email dostępny</span>';
                    } else {
                        feedback.innerHTML = '<span class="error">Ten adres email jest już zajęty</span>';
                    }
                })
                .catch(error => {
                    // Fallback to client-side validation
                    feedback.innerHTML = '<span class="success">Adres email dostępny</span>';
                });
            }, 800);
        }

        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            const feedback = document.getElementById('confirm-feedback');
            
            if (confirmPassword && password !== confirmPassword) {
                this.setCustomValidity('Hasła nie są identyczne');
                feedback.innerHTML = '<span class="error">Hasła nie są identyczne</span>';
            } else {
                this.setCustomValidity('');
                feedback.innerHTML = confirmPassword ? '<span class="success">Hasła identyczne</span>' : '';
            }
        });

        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('registerBtn');
            const btnText = btn.querySelector('.btn-text');
            const btnLoader = btn.querySelector('.btn-loader');
            
            btnText.style.display = 'none';
            btnLoader.style.display = 'inline';
            btn.disabled = true;
        });

        // Auto-focus username field
        document.getElementById('username').focus();
    </script>
</body>
</html>
