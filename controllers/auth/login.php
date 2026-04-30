<?php
require_once __DIR__ . '/../../bootstrap.php';

$auth = new AuthCore();

if ($auth->isLoggedIn()) {
    if ($auth->isDeveloper()) {
        header('Location: ../core/dashboard.php');
    } else {
        header('Location: ../marketplace/marketplace.php');
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
        $user = $auth->getCurrentUser();
        if ($user && $user['user_type'] === 'developer') {
            header('Location: ../core/dashboard.php');
        } else {
            header('Location: ../marketplace/marketplace.php');
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
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/reboot.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <meta name="description" content="Zaloguj się do platformy Bloxer i zarządzaj aplikacjami web">
    <meta name="robots" content="noindex, nofollow">
</head>
<body class="app-auth">
    <a href="#main" class="skip-to-content">Przejdź do treści</a>

    <div class="auth-shell">
        <section class="auth-showcase">
            <div>
                <div class="auth-brand">
                    <span class="brand-mark"><i class="fas fa-cube"></i></span>
                    <span>Bloxer</span>
                </div>
                <div class="auth-kicker">
                    <i class="fas fa-rocket"></i>
                    Platforma dla nowoczesnych twórców aplikacji webowych
                </div>
                <h2 class="auth-headline">Twórz, publikuj i zarabiaj na swoich aplikacjach webowych</h2>
                <p class="auth-copy">
                    Dołącz do ekosystemu, gdzie twórcy mogą rozwijać swoje projekty w profesjonalnym środowisku, 
                    a użytkownicy odkrywają innowacyjne aplikacje. Intuicyjny interfejs i potężne narzędzia w jednym miejscu.
                </p>
                <div class="auth-metric-grid">
                    <div class="auth-metric">
                        <strong>Marketplace</strong>
                        <span>Odkrywaj i instaluj aplikacje stworzone przez społeczność</span>
                    </div>
                    <div class="auth-metric">
                        <strong>Studio Dewelopera</strong>
                        <span>Zaawansowane narzędzia do tworzenia i zarządzania projektami</span>
                    </div>
                    <div class="auth-metric">
                        <strong>Monetyzacja</strong>
                        <span>Zarabiaj na swoich aplikacjach i buduj swoją markę</span>
                    </div>
                </div>
            </div>

            <div class="auth-quote">
                <p>„Profesjonalne narzędzia, które pozwalają skupić się na tworzeniu świetnych aplikacji.”</p>
                <small>Dołącz do tysięcy twórców, którzy już budują przyszłość webu.</small>
            </div>
        </section>

        <section class="auth-form-wrap">
            <div class="auth-form-card" id="main">
                <div class="auth-header">
                    <h1>Witaj z powrotem</h1>
                    <p>Zaloguj się, aby kontynuować pracę na swoich projektach i odkrywać nowe możliwości.</p>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($errors[0]); ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="loginForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $auth->getCSRFToken(); ?>">

                    <div class="form-group">
                        <label for="username">Adres email lub nazwa użytkownika</label>
                        <div class="input-wrapper">
                            <div class="input-icon"><i class="fas fa-user-circle"></i></div>
                            <input
                                type="text"
                                id="username"
                                name="username"
                                required
                                value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                placeholder="np. jan@kowalski.pl"
                            >
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password">Hasło</label>
                        <div class="input-wrapper">
                            <div class="input-icon"><i class="fas fa-lock"></i></div>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                required
                                placeholder="Wprowadź swoje bezpieczne hasło"
                            >
                            <button type="button" class="password-toggle" onclick="togglePassword('password')">
                                <i class="fas fa-eye" id="password-toggle-icon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-options">
                        <label class="custom-checkbox">
                            <input type="checkbox" name="remember" id="remember" style="display: none;">
                            <div class="checkbox-box"><i class="fas fa-check"></i></div>
                            <span>Zapamiętaj mnie</span>
                        </label>
                        <a href="forgotpassword.php" class="forgot-link">Nie pamiętasz hasła?</a>
                    </div>

                    <div class="form-group">
                        <label>Typ konta</label>
                        <div class="radio-group">
                            <label class="radio-label <?php echo ($_POST['user_type'] ?? 'user') === 'user' ? 'active' : ''; ?>" onclick="selectRadio(this)">
                                <input type="radio" name="user_type" value="user" <?php echo ($_POST['user_type'] ?? 'user') === 'user' ? 'checked' : ''; ?> style="display: none;">
                                <span class="radio-text">Użytkownik</span>
                            </label>
                            <label class="radio-label <?php echo ($_POST['user_type'] ?? '') === 'developer' ? 'active' : ''; ?>" onclick="selectRadio(this)">
                                <input type="radio" name="user_type" value="developer" <?php echo ($_POST['user_type'] ?? '') === 'developer' ? 'checked' : ''; ?> style="display: none;">
                                <span class="radio-text">Developer</span>
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" id="loginBtn">
                        <span class="btn-text">Zaloguj się</span>
                        <span class="btn-loader" style="display: none;"><i class="fas fa-circle-notch fa-spin"></i></span>
                    </button>
                </form>

                <div class="links">
                    <p>Nie masz jeszcze konta? <a href="register.php">Załóż darmowe konto</a></p>
                    <p class="terms-links">
                        Logując się, akceptujesz nasz <a href="terms.php" target="_blank">Regulamin</a> i <a href="privacy.php" target="_blank">Politykę Prywatności</a>
                    </p>
                </div>
            </div>
        </section>
    </div>

    <script src="../../assets/js/beta-banner.js"></script>
    <script>
        function selectRadio(el) {
            const group = el.parentElement;
            group.querySelectorAll('.radio-label').forEach(label => label.classList.remove('active'));
            el.classList.add('active');
            el.querySelector('input').checked = true;
        }

        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(inputId + '-toggle-icon');

            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }

        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            btn.querySelector('.btn-text').style.display = 'none';
            btn.querySelector('.btn-loader').style.display = 'inline-block';
            btn.disabled = true;
        });

        document.getElementById('username').focus();
    </script>
</body>
</html>