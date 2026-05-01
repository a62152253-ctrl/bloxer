<?php
require_once __DIR__ . '/../../bootstrap.php';

// Get validation limits from config
$appConfig = AppConfig::getInstance();
define('MIN_PASSWORD_LENGTH', 8);
define('MAX_USERNAME_LENGTH', $appConfig->get('validation.max_username_length', 50));
define('MAX_BIO_LENGTH', $appConfig->get('validation.max_bio_length', 1000));
define('MAX_URL_LENGTH', $appConfig->get('validation.max_url_length', 255));

$auth = new AuthCore();

if (!$auth->isLoggedIn()) {
    SecurityUtils::safeRedirect('../auth/login.php', 302, 'User not logged in, redirecting to login');
}

$user = $auth->getCurrentUser();
$conn = $auth->getConnection();
$errors = [];
$success = '';

// Add URL validation helper
function isValidURL($url) {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    $parsed = parse_url($url);
    return in_array(strtolower($parsed['scheme'] ?? ''), ['http', 'https']);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation first
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!SecurityUtils::validateCSRFToken($csrf_token)) {
        SecurityUtils::logSecurityEvent('CSRF token validation failed', 'Settings form submission attempt');
        $errors[] = 'Invalid request. Please try again.';
    } else {
        // Rate limiting for sensitive operations
        require_once __DIR__ . '/../../config/rate_limiter.php';
        $rateLimiter = RateLimiter::getInstance();
        
        $action = SecurityUtils::validateInput(trim($_POST['action'] ?? ''), 'alphanumeric', 20);
        if ($action === false) {
            $errors[] = 'Invalid action format';
            $action = 'unknown'; // Fallback for rate limiting
        }
        $rateLimitKey = 'settings_' . $user['id'] . '_' . $action;
        
        // Validate rate limit key length to prevent filesystem issues
        if (strlen($rateLimitKey) > 255) {
            $rateLimitKey = 'settings_' . $user['id'] . '_' . hash('md5', $action);
        }
        
        if (!$rateLimiter->checkLimit($rateLimitKey, 'auth')['allowed']) {
            $errors[] = 'Too many requests. Please try again later';
        } else {
    switch ($action) {
        case 'update_profile':
            $username = SecurityUtils::validateInput(trim($_POST['username'] ?? ''), 'username', MAX_USERNAME_LENGTH);
            $email = SecurityUtils::validateInput(trim($_POST['email'] ?? ''), 'email', 100);
            $bio = SecurityUtils::validateInput(trim($_POST['bio'] ?? ''), 'string', MAX_BIO_LENGTH);
            $website = SecurityUtils::validateInput(trim($_POST['website'] ?? ''), 'url', MAX_URL_LENGTH);
            $github_url = SecurityUtils::validateInput(trim($_POST['github_url'] ?? ''), 'url', MAX_URL_LENGTH);
            $twitter_url = SecurityUtils::validateInput(trim($_POST['twitter_url'] ?? ''), 'url', MAX_URL_LENGTH);
            
            // Validate inputs - SecurityUtils handles format validation
            if ($username === false) {
                $errors[] = 'Invalid username format';
            }
            
            if ($email === false) {
                $errors[] = 'Invalid email format';
            }
            
            if ($bio === false) {
                $errors[] = 'Invalid bio format';
            }
            
            if ($website === false) {
                $errors[] = 'Invalid website URL format';
            } elseif (!empty($website) && !isValidURL($website)) {
                $errors[] = 'Please enter a valid website URL';
            }
            
            if ($github_url === false) {
                $errors[] = 'Invalid GitHub URL format';
            } elseif (!empty($github_url) && !isValidURL($github_url)) {
                $errors[] = 'Please enter a valid GitHub URL';
            }
            
            if ($twitter_url === false) {
                $errors[] = 'Invalid Twitter URL format';
            } elseif (!empty($twitter_url) && !isValidURL($twitter_url)) {
                $errors[] = 'Please enter a valid Twitter URL';
            }
            
            if (empty($errors)) {
                // Check if username is already taken by another user
                $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                if (!$stmt) {
                    error_log("Failed to prepare username check statement");
                    $errors[] = 'Database error occurred';
                } else {
                    $stmt->bind_param("si", $username, $user['id']);
                    if (!$stmt->execute()) {
                        error_log("Failed to execute username check");
                        $errors[] = 'Database error occurred';
                    } else {
                        if ($stmt->get_result()->num_rows > 0) {
                            $errors[] = 'Username is already taken';
                        } else {
                            // Check if email is already taken by another user
                            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                            if (!$stmt) {
                                error_log("Failed to prepare email check statement");
                                $errors[] = 'Database error occurred';
                            } else {
                                $stmt->bind_param("si", $email, $user['id']);
                                if (!$stmt->execute()) {
                                    error_log("Failed to execute email check");
                                    $errors[] = 'Database error occurred';
                                } else {
                                    if ($stmt->get_result()->num_rows > 0) {
                                        $errors[] = 'Email is already taken';
                                    } else {
                                        // Update user profile
                                        $stmt = $conn->prepare("
                                            UPDATE users 
                                            SET username = ?, email = ?, bio = ?, website = ?, github_url = ?, twitter_url = ?
                                            WHERE id = ?
                                        ");
                                        if (!$stmt) {
                                            error_log("Failed to prepare profile update statement");
                                            $errors[] = 'Database error occurred';
                                        } else {
                                            $stmt->bind_param("ssssssi", $username, $email, $bio, $website, $github_url, $twitter_url, $user['id']);
                                            
                                            if ($stmt->execute()) {
                            $success = 'Profile updated successfully';
                            // Update session user data atomically using session lock
                            if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user'])) {
                                $_SESSION['user']['username'] = $username;
                                $_SESSION['user']['email'] = $email;
                                session_commit(); // This returns void, not boolean
                                
                                // Only restart if headers not sent AND session needed later
                                if (headers_sent() === false) {
                                    session_regenerate_id(true); // Prevent session fixation
                                    session_start();
                                } else {
                                    // Log warning but continue - session data already updated
                                    error_log("Session could not be restarted - headers already sent for user ID: {$user['id']}");
                                }
                            }
                                            } else {
                                                $errors[] = 'Failed to update profile';
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            break;
            
        case 'change_password':
            $current_password = SecurityUtils::validateInput($_POST['current_password'] ?? '', 'string', 255);
            $new_password = SecurityUtils::validateInput($_POST['new_password'] ?? '', 'string', 255);
            $confirm_password = SecurityUtils::validateInput($_POST['confirm_password'] ?? '', 'string', 255);
            
            if ($current_password === false) {
                $errors[] = 'Invalid current password format';
            }
            
            if ($new_password === false) {
                $errors[] = 'Invalid new password format';
            } elseif (strlen($new_password) < MIN_PASSWORD_LENGTH) {
                $errors[] = 'New password must be at least ' . MIN_PASSWORD_LENGTH . ' characters';
            }
            
            if ($confirm_password === false) {
                $errors[] = 'Invalid confirm password format';
            } elseif ($new_password !== $confirm_password) {
                $errors[] = 'Passwords do not match';
            }
            
            if (empty($errors)) {
                // Verify current password
                if (password_verify($current_password, $user['password'])) {
                    // Update password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    if (!$stmt) {
                        error_log("Failed to prepare password update statement");
                        $errors[] = 'Database error occurred';
                    } else {
                        $stmt->bind_param("si", $hashed_password, $user['id']);
                        
                        if ($stmt->execute()) {
                            $success = 'Password changed successfully';
                        } else {
                            $errors[] = 'Failed to change password';
                        }
                    }
                } else {
                    $errors[] = 'Current password is incorrect';
                }
            }
            break;
            
        case 'update_notifications':
            $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
            $push_notifications = isset($_POST['push_notifications']) ? 1 : 0;
            
            // Update or insert notification preferences
            $stmt = $conn->prepare("
                INSERT INTO notification_preferences (user_id, email_notifications, push_notifications)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                email_notifications = VALUES(email_notifications),
                push_notifications = VALUES(push_notifications),
                updated_at = CURRENT_TIMESTAMP
            ");
            if (!$stmt) {
                error_log("Failed to prepare notification preferences statement");
                $errors[] = 'Database error occurred';
            } else {
                $stmt->bind_param("iii", $user['id'], $email_notifications, $push_notifications);
                
                if ($stmt->execute()) {
                    $success = 'Notification preferences updated successfully';
                } else {
                    $errors[] = 'Failed to update notification preferences';
                }
            }
            break;
            
        default:
            $errors[] = 'Invalid action specified';
            break;
    }
        }
    }
}

// Get current notification preferences
$notification_prefs = [
    'email_notifications' => 0,
    'push_notifications' => 1
];

$stmt = $conn->prepare("SELECT email_notifications, push_notifications FROM notification_preferences WHERE user_id = ?");
if (!$stmt) {
    error_log("Failed to prepare statement for notification preferences");
} else {
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result) {
        $notification_prefs = $result;
    }
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Bloxer</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="app-studio">
    <div class="studio-shell">
        <aside class="studio-sidebar sidebar" id="sidebar">
            <div class="studio-brand">
                <span class="brand-mark"><i class="fas fa-laptop-code"></i></span>
                <div class="studio-brand-copy">
                    <strong>Bloxer Studio</strong>
                    <span>Panel developera</span>
                </div>
            </div>

            <nav class="studio-nav">
                <div class="studio-nav-item nav-item" onclick="window.location.href='../core/dashboard.php'">
                    <div class="studio-nav-icon">
                        <i class="fas fa-cog"></i>
                    </div>
                    <div class="studio-nav-content">
                        <span class="studio-nav-title">Dashboard</span>
                        <span class="studio-nav-desc">Back to dashboard</span>
                    </div>
                </div>
            </nav>

            <div class="studio-nav-foot">
                <div class="studio-nav-item nav-item" onclick="window.location.href='../auth/logout.php'">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </div>
            </div>
        </aside>

        <div class="studio-main-wrap">
            <header class="studio-header top-bar">
                <div class="studio-header-main">
                    <button class="btn btn-small studio-menu-toggle" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div>
                        <h1>Settings</h1>
                    </div>
                </div>

                <div class="studio-header-meta">
                    <div class="studio-user-chip">
                        <span class="studio-user-avatar"><?php echo strtoupper(substr($user['username'], 0, 1)); ?></span>
                        <span><?php echo htmlspecialchars($user['username']); ?></span>
                    </div>
                </div>
            </header>

            <main class="studio-main workspace-area">
                <?php if (!empty($errors)): ?>
                    <div class="studio-flash studio-flash-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo htmlspecialchars(implode(', ', $errors)); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="studio-flash studio-flash-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <section class="studio-section">
                    <div class="studio-section-head">
                        <div>
                            <h2>Profile Settings</h2>
                            <p>Update your account information and profile details.</p>
                        </div>
                    </div>

                    <form method="POST" class="studio-form">
                        <input type="hidden" name="action" value="update_profile">
                        <input type="hidden" name="csrf_token" value="<?php echo SecurityUtils::getCSRFToken(); ?>">
                        
                        <div class="studio-form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                        </div>

                        <div class="studio-form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>

                        <div class="studio-form-group">
                            <label for="bio">Bio</label>
                            <textarea id="bio" name="bio" rows="3"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                        </div>

                        <div class="studio-form-group">
                            <label for="website">Website</label>
                            <input type="url" id="website" name="website" value="<?php echo htmlspecialchars($user['website'] ?? ''); ?>">
                        </div>

                        <div class="studio-form-group">
                            <label for="github_url">GitHub URL</label>
                            <input type="url" id="github_url" name="github_url" value="<?php echo htmlspecialchars($user['github_url'] ?? ''); ?>">
                        </div>

                        <div class="studio-form-group">
                            <label for="twitter_url">Twitter URL</label>
                            <input type="url" id="twitter_url" name="twitter_url" value="<?php echo htmlspecialchars($user['twitter_url'] ?? ''); ?>">
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Update Profile
                        </button>
                    </form>
                </section>

                <section class="studio-section">
                    <div class="studio-section-head">
                        <div>
                            <h2>Change Password</h2>
                            <p>Update your password to keep your account secure.</p>
                        </div>
                    </div>

                    <form method="POST" class="studio-form">
                        <input type="hidden" name="action" value="change_password">
                        <input type="hidden" name="csrf_token" value="<?php echo SecurityUtils::getCSRFToken(); ?>">
                        
                        <div class="studio-form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>

                        <div class="studio-form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" required minlength="8">
                        </div>

                        <div class="studio-form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-lock"></i>
                            Change Password
                        </button>
                    </form>
                </section>

                <section class="studio-section">
                    <div class="studio-section-head">
                        <div>
                            <h2>Notification Preferences</h2>
                            <p>Control how you receive notifications.</p>
                        </div>
                    </div>

                    <form method="POST" class="studio-form">
                        <input type="hidden" name="action" value="update_notifications">
                        <input type="hidden" name="csrf_token" value="<?php echo SecurityUtils::getCSRFToken(); ?>">
                        
                        <div class="studio-form-group">
                            <label class="studio-checkbox">
                                <input type="checkbox" name="email_notifications" <?php echo $notification_prefs['email_notifications'] ? 'checked' : ''; ?>>
                                <span class="checkmark"></span>
                                Email Notifications
                            </label>
                            <small>Receive notifications via email</small>
                        </div>

                        <div class="studio-form-group">
                            <label class="studio-checkbox">
                                <input type="checkbox" name="push_notifications" <?php echo $notification_prefs['push_notifications'] ? 'checked' : ''; ?>>
                                <span class="checkmark"></span>
                                Push Notifications
                            </label>
                            <small>Receive push notifications in your browser</small>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-bell"></i>
                            Update Preferences
                        </button>
                    </form>
                </section>
            </main>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('collapsed');
        }
    </script>
</body>
</html>
