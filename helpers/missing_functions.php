<?php
/**
 * Missing helper functions for Bloxer Platform
 * These functions are referenced throughout the codebase but were missing
 */

// Create file version function
if (!function_exists('createFileVersion')) {
    function createFileVersion($conn, $project_id, $file_path, $content, $comment = '') {
        $stmt = $conn->prepare("INSERT INTO version_files (project_id, file_path, content, comment, created_at) VALUES (?, ?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param("isss", $project_id, $file_path, $content, $comment);
            return $stmt->execute();
        }
        return false;
    }
}

// Get current user ID function
if (!function_exists('getCurrentUserId')) {
    function getCurrentUserId() {
        if (isset($_SESSION['user_id'])) {
            return $_SESSION['user_id'];
        }
        return null;
    }
}

// isLoggedIn function for global use
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}

// Safe file get contents function
if (!function_exists('safeFileGetContents')) {
    function safeFileGetContents($filename) {
        if (file_exists($filename) && is_readable($filename)) {
            return file_get_contents($filename);
        }
        return false;
    }
}

// Safe exec function
if (!function_exists('safeExec')) {
    function safeExec($command) {
        if (function_exists('escapeshellcmd')) {
            $command = escapeshellcmd($command);
        }
        return shell_exec($command);
    }
}

// Get user preferences function
if (!function_exists('getUserPreferences')) {
    function getUserPreferences($user_id) {
        try {
            $db = DatabaseConfig::getInstance();
            $conn = $db->getConnection();
            
            $stmt = $conn->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting user preferences: " . $e->getMessage());
            return [];
        }
    }
}

// Update user preferences function
if (!function_exists('updateUserPreferences')) {
    function updateUserPreferences($user_id, $preferences) {
        try {
            $db = DatabaseConfig::getInstance();
            $conn = $db->getConnection();
            
            $stmt = $conn->prepare("REPLACE INTO user_preferences (user_id, preferences) VALUES (?, ?)");
            return $stmt->execute([$user_id, json_encode($preferences)]);
        } catch (Exception $e) {
            error_log("Error updating user preferences: " . $e->getMessage());
            return false;
        }
    }
}

// Generate notification function
if (!function_exists('generateNotification')) {
    function generateNotification($user_id, $type, $title, $message, $data = []) {
        try {
            $db = DatabaseConfig::getInstance();
            $conn = $db->getConnection();
            
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, data, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            return $stmt->execute([$user_id, $type, $title, $message, json_encode($data)]);
        } catch (Exception $e) {
            error_log("Error generating notification: " . $e->getMessage());
            return false;
        }
    }
}

// Get user notifications function
if (!function_exists('getUserNotifications')) {
    function getUserNotifications($user_id, $limit = 10) {
        try {
            $db = DatabaseConfig::getInstance();
            $conn = $db->getConnection();
            
            $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
            $stmt->execute([$user_id, $limit]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting user notifications: " . $e->getMessage());
            return [];
        }
    }
}

// Mark notification as read function
if (!function_exists('markNotificationAsRead')) {
    function markNotificationAsRead($notification_id, $user_id) {
        try {
            $db = DatabaseConfig::getInstance();
            $conn = $db->getConnection();
            
            $stmt = $conn->prepare("UPDATE notifications SET read_at = NOW() WHERE id = ? AND user_id = ?");
            return $stmt->execute([$notification_id, $user_id]);
        } catch (Exception $e) {
            error_log("Error marking notification as read: " . $e->getMessage());
            return false;
        }
    }
}

// Get app rating function
if (!function_exists('getAppRating')) {
    function getAppRating($app_id) {
        try {
            $db = DatabaseConfig::getInstance();
            $conn = $db->getConnection();
            
            $stmt = $conn->prepare("SELECT AVG(rating) as average_rating, COUNT(*) as total_ratings FROM app_ratings WHERE app_id = ?");
            $stmt->execute([$app_id]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting app rating: " . $e->getMessage());
            return ['average_rating' => 0, 'total_ratings' => 0];
        }
    }
}

// Get user apps function
if (!function_exists('getUserApps')) {
    function getUserApps($user_id, $status = 'installed') {
        try {
            $db = DatabaseConfig::getInstance();
            $conn = $db->getConnection();
            
            $stmt = $conn->prepare("SELECT ua.*, a.name, a.description, a.category, a.icon_url FROM user_apps ua JOIN apps a ON ua.app_id = a.id WHERE ua.user_id = ? AND ua.status = ?");
            $stmt->execute([$user_id, $status]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting user apps: " . $e->getMessage());
            return [];
        }
    }
}

// Install app function
if (!function_exists('installApp')) {
    function installApp($user_id, $app_id) {
        try {
            $db = DatabaseConfig::getInstance();
            $conn = $db->getConnection();
            
            $stmt = $conn->prepare("INSERT INTO user_apps (user_id, app_id, status, installed_at) VALUES (?, ?, 'installed', NOW())");
            return $stmt->execute([$user_id, $app_id]);
        } catch (Exception $e) {
            error_log("Error installing app: " . $e->getMessage());
            return false;
        }
    }
}

// Uninstall app function
if (!function_exists('uninstallApp')) {
    function uninstallApp($user_id, $app_id) {
        try {
            $db = DatabaseConfig::getInstance();
            $conn = $db->getConnection();
            
            $stmt = $conn->prepare("UPDATE user_apps SET status = 'uninstalled', uninstalled_at = NOW() WHERE user_id = ? AND app_id = ?");
            return $stmt->execute([$user_id, $app_id]);
        } catch (Exception $e) {
            error_log("Error uninstalling app: " . $e->getMessage());
            return false;
        }
    }
}

// Get project files function
if (!function_exists('getProjectFiles')) {
    function getProjectFiles($project_id) {
        try {
            $db = DatabaseConfig::getInstance();
            $conn = $db->getConnection();
            
            $stmt = $conn->prepare("SELECT * FROM project_files WHERE project_id = ? ORDER BY file_path");
            $stmt->execute([$project_id]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting project files: " . $e->getMessage());
            return [];
        }
    }
}

// Save project file function
if (!function_exists('saveProjectFile')) {
    function saveProjectFile($project_id, $file_path, $content, $user_id) {
        try {
            $db = DatabaseConfig::getInstance();
            $conn = $db->getConnection();
            
            $stmt = $conn->prepare("REPLACE INTO project_files (project_id, file_path, content, updated_by, updated_at) VALUES (?, ?, ?, ?, NOW())");
            return $stmt->execute([$project_id, $file_path, $content, $user_id]);
        } catch (Exception $e) {
            error_log("Error saving project file: " . $e->getMessage());
            return false;
        }
    }
}

// Get project details function
if (!function_exists('getProjectDetails')) {
    function getProjectDetails($project_id, $user_id = null) {
        try {
            $db = DatabaseConfig::getInstance();
            $conn = $db->getConnection();
            
            if ($user_id) {
                $stmt = $conn->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
                $stmt->execute([$project_id, $user_id]);
            } else {
                $stmt = $conn->prepare("SELECT * FROM projects WHERE id = ?");
                $stmt->execute([$project_id]);
            }
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting project details: " . $e->getMessage());
            return null;
        }
    }
}

// Create project function
if (!function_exists('createProject')) {
    function createProject($user_id, $name, $description, $type = 'web') {
        try {
            $db = DatabaseConfig::getInstance();
            $conn = $db->getConnection();
            
            $stmt = $conn->prepare("INSERT INTO projects (user_id, name, description, type, created_at) VALUES (?, ?, ?, ?, NOW())");
            return $stmt->execute([$user_id, $name, $description, $type]);
        } catch (Exception $e) {
            error_log("Error creating project: " . $e->getMessage());
            return false;
        }
    }
}

// Update project function
if (!function_exists('updateProject')) {
    function updateProject($project_id, $name, $description, $user_id) {
        try {
            $db = DatabaseConfig::getInstance();
            $conn = $db->getConnection();
            
            $stmt = $conn->prepare("UPDATE projects SET name = ?, description = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
            return $stmt->execute([$name, $description, $project_id, $user_id]);
        } catch (Exception $e) {
            error_log("Error updating project: " . $e->getMessage());
            return false;
        }
    }
}

// Delete project function
if (!function_exists('deleteProject')) {
    function deleteProject($project_id, $user_id) {
        try {
            $db = DatabaseConfig::getInstance();
            $conn = $db->getConnection();
            
            // Delete project files first
            $stmt = $conn->prepare("DELETE FROM project_files WHERE project_id = ?");
            $stmt->execute([$project_id]);
            
            // Delete the project
            $stmt = $conn->prepare("DELETE FROM projects WHERE id = ? AND user_id = ?");
            return $stmt->execute([$project_id, $user_id]);
        } catch (Exception $e) {
            error_log("Error deleting project: " . $e->getMessage());
            return false;
        }
    }
}
?>
