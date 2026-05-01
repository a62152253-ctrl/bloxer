<?php
require_once '../core/mainlogincore.php';

$auth = new AuthCore();

if ($auth->isLoggedIn()) {
    if ($auth->isDeveloper()) {
        SecurityUtils::safeRedirect('../core/dashboard.php', 302, 'Already logged in as developer');
    } else {
        SecurityUtils::safeRedirect('../marketplace/marketplace.php', 302, 'Already logged in');
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
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <a href="#main" class="skip-to-content">Przejdź do treści</a>
    
    <div class="auth-container" id="main">
        <div class="auth-header">
            <h1>Bloxer</h1>
            <p><?php echo $step === 1 ? 'Odzyskiwanie dostępu' : 'Ustaw nowe hasło'; ?></p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($errors[0]); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <div class="step-indicator-simple">
                <div class="step active">1</div>
                <div class="line"></div>
                <div class="step">2</div>
            </div>

            <form method="POST" action="" id="emailForm">
                <input type="hidden" name="csrf_token" value="<?php echo $auth->getCSRFToken(); ?>">
                <div class="form-group">
                    <label for="email">Twój adres email</label>
                    <div class="input-wrapper">
                        <div class="input-icon"><i class="fas fa-envelope"></i></div>
                        <input type="email" id="email" name="email" required 
                               placeholder="email@example.com">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" id="emailBtn">
                    <span class="btn-text">Wyślij link do resetu</span>
                    <span class="btn-loader" style="display: none;"><i class="fas fa-circle-notch fa-spin"></i></span>
                </button>
            </form>

            <div class="links">
                <a href="login.php" class="back-to-login">
                    <i class="fas fa-arrow-left"></i> Wróć do logowania
                </a>
            </div>

        <?php else: ?>
            <div class="step-indicator-simple">
                <div class="step completed"><i class="fas fa-check"></i></div>
                <div class="line active"></div>
                <div class="step active">2</div>
            </div>

            <form method="POST" action="" id="passwordForm">
                <input type="hidden" name="csrf_token" value="<?php echo $auth->getCSRFToken(); ?>">
                <div class="form-group">
                    <label for="new_password">Nowe, silne hasło</label>
                    <div class="input-wrapper">
                        <div class="input-icon"><i class="fas fa-lock"></i></div>
                        <input type="password" id="new_password" name="new_password" required 
                               placeholder="••••••••"
                               onkeyup="checkPasswordStrength(this.value)">
                    </div>
                    <div id="password-strength" class="password-strength"></div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Powtórz nowe hasło</label>
                    <div class="input-wrapper">
                        <div class="input-icon"><i class="fas fa-lock"></i></div>
                        <input type="password" id="confirm_password" name="confirm_password" required 
                               placeholder="••••••••">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" id="passwordBtn">
                    <span class="btn-text">Zapisz nowe hasło</span>
                    <span class="btn-loader" style="display: none;"><i class="fas fa-circle-notch fa-spin"></i></span>
                </button>
            </form>

            <div class="links">
                <a href="login.php" class="back-to-login">Anuluj i wróć</a>
            </div>
        <?php endif; ?>
    </div>

    <style>
        .step-indicator-simple {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 35px;
        }
        
        .step-indicator-simple .step {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--glass-border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 600;
        }
        
        .step-indicator-simple .step.active {
            background: var(--accent);
            border-color: var(--accent);
            color: white;
            box-shadow: 0 0 15px rgba(99, 102, 241, 0.3);
        }
        
        .step-indicator-simple .step.completed {
            background: rgba(16, 185, 129, 0.15);
            border-color: #10b981;
            color: #10b981;
        }
        
        .step-indicator-simple .line {
            width: 40px;
            height: 2px;
            background: var(--glass-border);
        }
        
        .step-indicator-simple .line.active {
            background: #10b981;
        }
        
        .back-to-login {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 0.9rem;
            color: var(--text-secondary);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .back-to-login:hover {
            color: white;
        }

        .alert {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 0.9rem;
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #f87171;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: #34d399;
        }

        .password-strength {
            font-size: 0.75rem;
            margin-top: 8px;
            font-weight: 500;
        }
    </style>

    <script>
        function checkPasswordStrength(p) {
            const s = document.getElementById('password-strength');
            if(!s) return;
            let strength = 0;
            if (p.length > 5) strength++;
            if (p.length > 10) strength++;
            if (/[A-Z]/.test(p)) strength++;
            if (/[0-9]/.test(p)) strength++;
            
            const labels = ['Zbyt krótkie', 'Słabe', 'Dobre', 'Silne'];
            const colors = ['#f87171', '#fbbf24', '#34d399', '#10b981'];
            
            s.innerHTML = p ? `<span style="color: ${colors[strength]}">Siła: ${labels[strength]}</span>` : '';
        }

        const forms = ['emailForm', 'passwordForm'];
        forms.forEach(id => {
            const f = document.getElementById(id);
            if(f) {
                f.addEventListener('submit', function() {
                    const btn = f.querySelector('.btn-primary');
                    btn.querySelector('.btn-text').style.display = 'none';
                    btn.querySelector('.btn-loader').style.display = 'inline-block';
                    btn.disabled = true;
                });
            }
        });
    </script>
</body>
</html>
</html>
