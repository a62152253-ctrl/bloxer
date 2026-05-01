<?php
require_once __DIR__ . '/../../bootstrap.php';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_username'])) {
    $username = SecurityUtils::validateInput(trim($_POST['check_username']), 'string', ['max_length' => 50]);

    $stmt = $auth->getConnection()->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    SecurityUtils::sendJSONResponse(['available' => $result->num_rows === 0]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_email'])) {
    $email = SecurityUtils::validateInput(trim($_POST['check_email']), 'email');

    $stmt = $auth->getConnection()->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    SecurityUtils::sendJSONResponse(['available' => $result->num_rows === 0]);
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
        $_POST = [];
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
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/reboot.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <meta name="description" content="Załóż konto na Bloxer i publikuj aplikacje web">
    <meta name="robots" content="noindex, nofollow">
</head>
<body class="app-auth">
    <a href="#main" class="skip-to-content">Przejdź do treści</a>

    <div class="auth-shell">
        <section class="auth-showcase">
            <div>
                <div class="auth-brand">
                    <span class="brand-mark"><i class="fas fa-rocket"></i></span>
                    <span>Bloxer</span>
                </div>
                <div class="auth-kicker">
                    <i class="fas fa-sparkles"></i>
                    Zacznij budować i publikować
                </div>
                <h2 class="auth-headline">Załóż konto i wejdź do nowego marketplace’u dla web appów.</h2>
                <p class="auth-copy">
                    Twórz aplikacje, zarządzaj projektami i publikuj gotowe produkty w jednym, spójnym środowisku.
                    Nowy interfejs jest lżejszy, czytelniejszy i dużo bardziej produktowy.
                </p>
                <div class="auth-metric-grid">
                    <div class="auth-metric">
                        <strong>Publikuj</strong>
                        <span>własne appki w marketplace</span>
                    </div>
                    <div class="auth-metric">
                        <strong>Zarządzaj</strong>
                        <span>projektami i ofertami w studio</span>
                    </div>
                    <div class="auth-metric">
                        <strong>Rosnij</strong>
                        <span>od prototypu do gotowego produktu</span>
                    </div>
                </div>
            </div>

            <div class="auth-quote">
                <p>„Jedno konto. Jeden workflow. Zero wizualnego bałaganu.”</p>
                <small>Dołącz do społeczności twórców aplikacji i odpal własny panel.</small>
            </div>
        </section>

        <section class="auth-form-wrap">
            <div class="auth-form-card" id="main">
                <div class="auth-header">
                    <h1>Utwórz konto</h1>
                    <p>Dołącz do Bloxera i zacznij publikować swoje aplikacje web.</p>
                </div>

                <div class="auth-surface-row">
                    <button type="button" class="btn-social">
                        <i class="fab fa-github"></i>
                        <span>GitHub</span>
                    </button>
                    <button type="button" class="btn-social">
                        <i class="fab fa-google"></i>
                        <span>Google</span>
                    </button>
                </div>

                <div class="auth-divider">albo zarejestruj się ręcznie</div>

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

                <form method="POST" action="" id="registerForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $auth->getCSRFToken(); ?>">

                    <div class="form-group">
                        <label for="username">Nazwa użytkownika</label>
                        <div class="input-wrapper">
                            <div class="input-icon"><i class="fas fa-user"></i></div>
                            <input
                                type="text"
                                id="username"
                                name="username"
                                required
                                value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                placeholder="Twoja unikalna nazwa"
                                onkeyup="checkUsernameAvailability(this.value)"
                            >
                        </div>
                        <div id="username-feedback" class="field-feedback"></div>
                    </div>

                    <div class="form-group">
                        <label for="email">Adres email</label>
                        <div class="input-wrapper">
                            <div class="input-icon"><i class="fas fa-envelope"></i></div>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                required
                                value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                placeholder="email@example.com"
                                onkeyup="checkEmailAvailability(this.value)"
                            >
                        </div>
                        <div id="email-feedback" class="field-feedback"></div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">Hasło</label>
                            <div class="input-wrapper">
                                <div class="input-icon"><i class="fas fa-lock"></i></div>
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    required
                                    placeholder="Minimum kilka sensownych znaków"
                                    onkeyup="checkPasswordStrength(this.value)"
                                >
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Powtórz hasło</label>
                            <div class="input-wrapper">
                                <div class="input-icon"><i class="fas fa-shield-alt"></i></div>
                                <input
                                    type="password"
                                    id="confirm_password"
                                    name="confirm_password"
                                    required
                                    placeholder="Powtórz hasło"
                                >
                            </div>
                        </div>
                    </div>

                    <div id="password-strength" class="password-strength"></div>

                    <div class="form-group">
                        <label>Chcę korzystać jako</label>
                        <div class="radio-group">
                            <label class="radio-label active" onclick="selectRadio(this)">
                                <input type="radio" name="user_type" value="user" checked style="display: none;">
                                <span class="radio-text">Użytkownik</span>
                            </label>
                            <label class="radio-label" onclick="selectRadio(this)">
                                <input type="radio" name="user_type" value="developer" style="display: none;">
                                <span class="radio-text">Developer</span>
                            </label>
                        </div>
                    </div>

                    <div class="form-options">
                        <label class="custom-checkbox">
                            <input type="checkbox" name="terms" id="terms" required style="display: none;">
                            <div class="checkbox-box"><i class="fas fa-check"></i></div>
                            <span>Akceptuję <a href="#" class="terms-link">regulamin</a> i <a href="#" class="terms-link">politykę prywatności</a></span>
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary" id="registerBtn">
                        <span class="btn-text">Utwórz konto</span>
                        <span class="btn-loader" style="display: none;"><i class="fas fa-circle-notch fa-spin"></i></span>
                    </button>
                </form>

                <div class="links">
                    <p>Masz już konto? <a href="login.php">Zaloguj się tutaj</a></p>
                </div>
            </div>
        </section>
    </div>

    <script>
        function selectRadio(el) {
            const group = el.parentElement;
            group.querySelectorAll('.radio-label').forEach(label => label.classList.remove('active'));
            el.classList.add('active');
            el.querySelector('input').checked = true;
        }

        let usernameTimeout, emailTimeout;

        function checkUsernameAvailability(username) {
            const feedback = document.getElementById('username-feedback');
            if (username.length < 3) {
                feedback.innerHTML = '';
                return;
            }
            feedback.innerHTML = '<span class="checking">Sprawdzanie...</span>';
            clearTimeout(usernameTimeout);
            usernameTimeout = setTimeout(() => {
                fetch('register.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `check_username=${encodeURIComponent(username)}`
                })
                .then(r => r.json())
                .then(d => {
                    feedback.innerHTML = d.available
                        ? '<span class="success"><i class="fas fa-check"></i> Nazwa dostępna</span>'
                        : '<span class="error"><i class="fas fa-times"></i> Nazwa zajęta</span>';
                });
            }, 500);
        }

        function checkEmailAvailability(email) {
            const feedback = document.getElementById('email-feedback');
            if (!email.includes('@')) {
                feedback.innerHTML = '';
                return;
            }
            feedback.innerHTML = '<span class="checking">Sprawdzanie...</span>';
            clearTimeout(emailTimeout);
            emailTimeout = setTimeout(() => {
                fetch('register.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `check_email=${encodeURIComponent(email)}`
                })
                .then(r => r.json())
                .then(d => {
                    feedback.innerHTML = d.available
                        ? '<span class="success"><i class="fas fa-check"></i> Email dostępny</span>'
                        : '<span class="error"><i class="fas fa-times"></i> Email zajęty</span>';
                });
            }, 500);
        }

        function checkPasswordStrength(password) {
            const strengthEl = document.getElementById('password-strength');
            let strength = 0;
            if (password.length > 5) strength++;
            if (password.length > 10) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;

            const labels = ['Zbyt krótkie', 'Słabe', 'Dobre', 'Silne'];
            const colors = ['#e11d48', '#d97706', '#1769ff', '#16a34a'];

            strengthEl.innerHTML = password
                ? `<span style="color: ${colors[strength]}">Siła hasła: ${labels[strength]}</span>`
                : '';
        }

        document.getElementById('registerForm').addEventListener('submit', function() {
            const btn = document.getElementById('registerBtn');
            btn.querySelector('.btn-text').style.display = 'none';
            btn.querySelector('.btn-loader').style.display = 'inline-block';
            btn.disabled = true;
        });
    </script>
</body>
</html>