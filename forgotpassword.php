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
$step = 1;

if (isset($_GET['token'])) {
    $step = 2;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_GET['token'];
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        $result = $auth->verifyResetToken($token, $new_password, $confirm_password);
        
        if ($result['success']) {
            $success = $result['message'];
            // Log successful password reset
            error_log("Password reset completed for token: " . substr($token, 0, 8) . "...");
            header('refresh:3;url=login.php');
        } else {
            $errors = $result['errors'];
        }
    }
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = trim($_POST['email'] ?? '');
        
        // Rate limiting check
        $rate_limit_result = $auth->checkRateLimit('forgot_password');
        if (!$rate_limit_result['allowed']) {
            $errors = [$rate_limit_result['message']];
        } else {
            $result = $auth->generateResetToken($email);
            
            if ($result['success']) {
                $success = 'Link do resetowania hasła został wysłany na podany adres email.';
                // Log the reset request for security
                error_log("Password reset requested for email: " . substr($email, 0, 3) . "***@" . substr(strrchr($email, "@"), 1));
            } else {
                $errors = $result['errors'];
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resetowanie hasła - Bloxer</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <a href="#main" class="skip-to-content">Przejdź do treści</a>
    <div class="auth-container" id="main">
        <div class="logo">
            <h1>Bloxer</h1>
            <p><?php echo $step === 1 ? 'Resetowanie hasła' : 'Ustaw nowe hasło'; ?></p>
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

        <?php if ($step === 1): ?>
            <div class="step-indicator">
                <div class="step active">1</div>
                <div class="step-line"></div>
                <div class="step">2</div>
                <div class="step-text">
                    <span class="step-active">Krok 1: Weryfikacja email</span>
                    <span class="step-total">z 2</span>
                </div>
            </div>

            <form method="POST" action="" id="emailForm">
                <input type="hidden" name="csrf_token" value="<?php echo $auth->getCSRFToken(); ?>">
                <div class="form-group">
                    <label for="email">Adres email</label>
                    <div class="input-wrapper">
                        <input type="email" id="email" name="email" required 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                               autocomplete="email"
                               placeholder="wpisz@twoj.email">
                        <span class="input-icon">📧</span>
                    </div>
                    <div class="help-text">
                        Wpisz adres email powiązany z Twoim kontem. Wyślemy na niego link do resetowania hasła.
                    </div>
                    <div class="security-notice">
                        <span class="notice-icon">🔒</span>
                        <span>Ze względów bezpieczeństwa nie informujemy czy email istnieje w systemie</span>
                    </div>
                </div>

                <button type="submit" class="btn" id="emailBtn">
                    <span class="btn-text">Wyślij link resetujący</span>
                    <span class="btn-loader" style="display: none;">⏳</span>
                </button>
            </form>

            <div class="links">
                <a href="login.php">Wróć do logowania</a>
            </div>

        <?php else: ?>
            <div class="step-indicator">
                <div class="step completed">✓</div>
                <div class="step-line"></div>
                <div class="step active">2</div>
                <div class="step-text">
                    <span class="step-active">Krok 2: Nowe hasło</span>
                    <span class="step-total">z 2</span>
                </div>
            </div>

            <?php if (isset($_GET['token'])): ?>
                <div class="token-info">
                    <div class="token-header">
                        <span class="token-icon">🔑</span>
                        <span>Token resetowania</span>
                    </div>
                    <div class="token-value">
                        <?php echo htmlspecialchars(substr($_GET['token'], 0, 12)); ?>...
                    </div>
                    <div class="token-expiry">
                        Token ważny przez 1 godzinę
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="passwordForm">
                <input type="hidden" name="csrf_token" value="<?php echo $auth->getCSRFToken(); ?>">
                <div class="form-group">
                    <label for="new_password">Nowe hasło</label>
                    <div class="input-wrapper">
                        <input type="password" id="new_password" name="new_password" required 
                               minlength="6"
                               autocomplete="new-password"
                               placeholder="Wpisz nowe hasło"
                               onkeyup="checkPasswordStrength(this.value)">
                        <button type="button" class="password-toggle" onclick="togglePassword('new_password')">
                            <span id="new_password-toggle-icon">👁️</span>
                        </button>
                    </div>
                    <div id="password-strength" class="password-strength"></div>
                    <div class="password-requirements">
                        <small>Hasło musi zawierać co najmniej 6 znaków. Zalecamy użycie mieszanych liter, cyfr i znaków specjalnych.</small>
                    </div>
                    <div class="security-notice">
                        <span class="notice-icon">🔒</span>
                        <span>Upewnij się, że nowe hasło jest bezpieczne i nie używane nigdzie indziej</span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Potwierdź nowe hasło</label>
                    <div class="input-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password" required 
                               autocomplete="new-password"
                               placeholder="Potwierdź nowe hasło">
                        <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                            <span id="confirm_password-toggle-icon">👁️</span>
                        </button>
                    </div>
                    <div id="confirm-feedback" class="field-feedback"></div>
                </div>

                <button type="submit" class="btn" id="passwordBtn">
                    <span class="btn-text">Zresetuj hasło</span>
                    <span class="btn-loader" style="display: none;">⏳</span>
                </button>
            </form>

            <div class="links">
                <a href="login.php">Wróć do logowania</a> | 
                <a href="forgotpassword.php">Wygeneruj nowy token</a>
            </div>
        <?php endif; ?>
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

        function checkPasswordStrength(password) {
            const strengthDiv = document.getElementById('password-strength');
            if (!strengthDiv) return;
            
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

        // Email form submission
        const emailForm = document.getElementById('emailForm');
        if (emailForm) {
            emailForm.addEventListener('submit', function(e) {
                const btn = document.getElementById('emailBtn');
                const btnText = btn.querySelector('.btn-text');
                const btnLoader = btn.querySelector('.btn-loader');
                
                btnText.style.display = 'none';
                btnLoader.style.display = 'inline';
                btn.disabled = true;
            });
        }

        // Password form submission
        const passwordForm = document.getElementById('passwordForm');
        if (passwordForm) {
            passwordForm.addEventListener('submit', function(e) {
                const btn = document.getElementById('passwordBtn');
                const btnText = btn.querySelector('.btn-text');
                const btnLoader = btn.querySelector('.btn-loader');
                
                btnText.style.display = 'none';
                btnLoader.style.display = 'inline';
                btn.disabled = true;
            });

            const confirmPassword = document.getElementById('confirm_password');
            if (confirmPassword) {
                confirmPassword.addEventListener('input', function() {
                    const password = document.getElementById('new_password').value;
                    const confirmPass = this.value;
                    const feedback = document.getElementById('confirm-feedback');
                    
                    if (confirmPass && password !== confirmPass) {
                        this.setCustomValidity('Hasła nie są identyczne');
                        feedback.innerHTML = '<span class="error">Hasła nie są identyczne</span>';
                    } else {
                        this.setCustomValidity('');
                        feedback.innerHTML = confirmPass ? '<span class="success">Hasła identyczne</span>' : '';
                    }
                });
            }
        }

        // Auto-focus email field
        const emailField = document.getElementById('email');
        if (emailField) {
            emailField.focus();
        }
    </script>
</body>
</html>
