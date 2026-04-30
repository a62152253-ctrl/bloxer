<?php
// Marketplace Components - Reusable PHP Components for Marketplace

/**
 * Component: App Card
 * Renders a single app card with enhanced styling
 */
function renderAppCard($app, $showActions = true) {
    $isInstalled = isset($_SESSION['installed_apps']) && in_array($app['id'], $_SESSION['installed_apps']);
    $isFavorite = isset($_SESSION['favorite_apps']) && in_array($app['id'], $_SESSION['favorite_apps']);
    
    return '
        <div class="app-card" data-app-id="' . $app['id'] . '" onclick="openApp(' . $app['id'] . ')">
            <div class="app-thumbnail">
                ' . ($app['thumbnail_url'] ? 
                    '<img src="' . htmlspecialchars($app['thumbnail_url']) . '" alt="' . htmlspecialchars($app['title']) . '" loading="lazy">' :
                    '<i class="fas fa-rocket"></i>'
                ) . '
                ' . ($app['is_featured'] ? '<div class="featured-badge"><i class="fas fa-star"></i> Polecane</div>' : '') . '
            </div>
            <div class="app-content">
                <h3 class="app-title">' . htmlspecialchars($app['title']) . '</h3>
                <div class="app-meta">
                    <div class="app-category">
                        <i class="fas fa-' . ($app['category_icon'] ?? 'folder') . '"></i>
                        ' . htmlspecialchars($app['category_name'] ?? 'General') . '
                    </div>
                    <div class="app-rating">
                        <div class="stars">' . renderStars($app['rating']) . '</div>
                        <span>' . number_format($app['rating'], 1) . '</span>
                    </div>
                </div>
                <p class="app-description">' . htmlspecialchars(substr($app['description'], 0, 120)) . '...</p>
                <div class="app-stats">
                    <div class="app-downloads">
                        <i class="fas fa-download"></i>
                        ' . number_format($app['download_count']) . '
                    </div>
                    <div class="app-price">
                        Darmowe
                    </div>
                </div>
                ' . ($showActions ? renderAppActions($app['id'], $isInstalled, $isFavorite) : '') . '
            </div>
        </div>
    ';
}

/**
 * Component: App Actions
 * Renders install/favorite actions for an app
 */
function renderAppActions($appId, $isInstalled, $isFavorite) {
    return '
        <div class="app-card-actions">
            ' . ($isInstalled ? 
                '<button class="btn-card btn-secondary" disabled>
                    <i class="fas fa-check"></i> Zainstalowano
                </button>' :
                '<button class="btn-card btn-primary" onclick="installApp(' . $appId . ', event)">
                    <i class="fas fa-download"></i> Zainstaluj
                </button>'
            ) . '
            <button class="btn-card btn-secondary" onclick="toggleFavorite(' . $appId . ', event)">
                <i class="fas fa-heart' . ($isFavorite ? '' : '-o') . '"></i>
                ' . ($isFavorite ? 'Ulubione' : 'Dodaj') . '
            </button>
            <button class="btn-card btn-secondary" onclick="shareApp(' . $appId . ', event)">
                <i class="fas fa-share"></i> Udostępnij
            </button>
        </div>
    ';
}

/**
 * Component: Star Rating
 * Renders star rating display
 */
function renderStars($rating) {
    $fullStars = floor($rating);
    $halfStar = ($rating - $fullStars) >= 0.5;
    $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);
    
    $stars = '';
    for ($i = 0; $i < $fullStars; $i++) {
        $stars .= '<i class="fas fa-star"></i>';
    }
    if ($halfStar) {
        $stars .= '<i class="fas fa-star-half-alt"></i>';
    }
    for ($i = 0; $i < $emptyStars; $i++) {
        $stars .= '<i class="far fa-star"></i>';
    }
    
    return $stars;
}

/**
 * Component: Developer Card
 * Renders developer information card
 */
function renderDeveloperCard($developer) {
    return '
        <div class="developer-card">
            <img src="' . ($developer['avatar_url'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($developer['username']) . '&background=6366f1&color=fff') . '" 
                 alt="' . htmlspecialchars($developer['username']) . '" class="developer-avatar">
            <div class="developer-info">
                <h4 class="developer-name">' . htmlspecialchars($developer['username']) . '</h4>
                <p class="developer-stats">
                    <span><i class="fas fa-rocket"></i> ' . $developer['app_count'] . ' aplikacji</span>
                    <span><i class="fas fa-download"></i> ' . number_format($developer['total_downloads']) . ' pobrań</span>
                </p>
            </div>
            <a href="../controllers/user/developer_profile.php?developer=' . $developer['id'] . '" class="btn-card btn-secondary">
                <i class="fas fa-user"></i> Profil
            </a>
        </div>
    ';
}

/**
 * Component: Category Card
 * Renders category card with stats
 */
function renderCategoryCard($category) {
    return '
        <div class="category-card" onclick="applyFilter(\'' . $category['slug'] . '\')">
            <div class="category-icon">
                <i class="fas fa-' . ($category['icon'] ?? 'folder') . '"></i>
            </div>
            <div class="category-info">
                <h3 class="category-name">' . htmlspecialchars($category['name']) . '</h3>
                <p class="category-count">' . $category['app_count'] . ' aplikacji</p>
            </div>
            <div class="category-arrow">
                <i class="fas fa-arrow-right"></i>
            </div>
        </div>
    ';
}

/**
 * Component: Search Result
 * Renders search result item
 */
function renderSearchResult($app, $query) {
    $title = highlightSearchTerm($app['title'], $query);
    $description = highlightSearchTerm(substr($app['description'], 0, 150), $query);
    
    return '
        <div class="search-result" onclick="openApp(' . $app['id'] . ')">
            <div class="search-result-thumbnail">
                ' . ($app['thumbnail_url'] ? 
                    '<img src="' . htmlspecialchars($app['thumbnail_url']) . '" alt="' . htmlspecialchars($app['title']) . '">' :
                    '<i class="fas fa-rocket"></i>'
                ) . '
            </div>
            <div class="search-result-content">
                <h4 class="search-result-title">' . $title . '</h4>
                <p class="search-result-description">' . $description . '...</p>
                <div class="search-result-meta">
                    <span class="search-result-category">
                        <i class="fas fa-' . ($app['category_icon'] ?? 'folder') . '"></i>
                        ' . htmlspecialchars($app['category_name'] ?? 'General') . '
                    </span>
                    <span class="search-result-rating">
                        <i class="fas fa-star"></i> ' . number_format($app['rating'], 1) . '
                    </span>
                    <span class="search-result-downloads">
                        <i class="fas fa-download"></i> ' . number_format($app['download_count']) . '
                    </span>
                </div>
            </div>
        </div>
    ';
}

/**
 * Component: Notification
 * Renders notification message
 */
function renderNotification($message, $type = 'info', $dismissible = true) {
    $icons = [
        'success' => 'check-circle',
        'error' => 'exclamation-triangle',
        'warning' => 'exclamation-circle',
        'info' => 'info-circle'
    ];
    
    return '
        <div class="notification notification-' . $type . '">
            <div class="notification-icon">
                <i class="fas fa-' . ($icons[$type] ?? 'info-circle') . '"></i>
            </div>
            <div class="notification-content">
                ' . htmlspecialchars($message) . '
            </div>
            ' . ($dismissible ? '<button class="notification-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>' : '') . '
        </div>
    ';
}

/**
 * Component: Loading Spinner
 * Renders loading spinner
 */
function renderLoadingSpinner($size = 'medium', $text = '') {
    $sizes = [
        'small' => '20px',
        'medium' => '30px',
        'large' => '40px'
    ];
    
    return '
        <div class="loading-spinner loading-' . $size . '">
            <div class="spinner" style="width: ' . ($sizes[$size] ?? $sizes['medium']) . '; height: ' . ($sizes[$size] ?? $sizes['medium']) . ';"></div>
            ' . ($text ? '<p class="loading-text">' . htmlspecialchars($text) . '</p>' : '') . '
        </div>
    ';
}

/**
 * Component: Empty State
 * Renders empty state message
 */
function renderEmptyState($title, $description, $icon = 'inbox', $action = null) {
    return '
        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="fas fa-' . $icon . '"></i>
            </div>
            <h3 class="empty-state-title">' . htmlspecialchars($title) . '</h3>
            <p class="empty-state-description">' . htmlspecialchars($description) . '</p>
            ' . ($action ? '<div class="empty-state-action">' . $action . '</div>' : '') . '
        </div>
    ';
}

/**
 * Component: Pagination
 * Renders pagination controls
 */
function renderPagination($currentPage, $totalPages, $baseUrl = '') {
    if ($totalPages <= 1) return '';
    
    $pagination = '<div class="pagination">';
    
    // Previous button
    if ($currentPage > 1) {
        $prevPage = $currentPage - 1;
        $pagination .= '<a href="' . $baseUrl . '?page=' . $prevPage . '" class="pagination-link prev">
            <i class="fas fa-chevron-left"></i> Poprzednia
        </a>';
    }
    
    // Page numbers
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $currentPage + 2);
    
    if ($startPage > 1) {
        $pagination .= '<a href="' . $baseUrl . '?page=1" class="pagination-link">1</a>';
        if ($startPage > 2) {
            $pagination .= '<span class="pagination-ellipsis">...</span>';
        }
    }
    
    for ($i = $startPage; $i <= $endPage; $i++) {
        $activeClass = $i == $currentPage ? ' active' : '';
        $pagination .= '<a href="' . $baseUrl . '?page=' . $i . '" class="pagination-link' . $activeClass . '">' . $i . '</a>';
    }
    
    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) {
            $pagination .= '<span class="pagination-ellipsis">...</span>';
        }
        $pagination .= '<a href="' . $baseUrl . '?page=' . $totalPages . '" class="pagination-link">' . $totalPages . '</a>';
    }
    
    // Next button
    if ($currentPage < $totalPages) {
        $nextPage = $currentPage + 1;
        $pagination .= '<a href="' . $baseUrl . '?page=' . $nextPage . '" class="pagination-link next">
            Następna <i class="fas fa-chevron-right"></i>
        </a>';
    }
    
    $pagination .= '</div>';
    
    return $pagination;
}

/**
 * Component: Breadcrumb
 * Renders breadcrumb navigation
 */
function renderBreadcrumb($items) {
    $breadcrumb = '<nav class="breadcrumb" aria-label="Breadcrumb">';
    $count = count($items);
    
    foreach ($items as $index => $item) {
        $isLast = $index === $count - 1;
        
        if ($isLast) {
            $breadcrumb .= '<span class="breadcrumb-item active">' . htmlspecialchars($item['label']) . '</span>';
        } else {
            $breadcrumb .= '<a href="' . htmlspecialchars($item['url']) . '" class="breadcrumb-item">
                ' . htmlspecialchars($item['label']) . '
            </a>';
            $breadcrumb .= '<span class="breadcrumb-separator"><i class="fas fa-chevron-right"></i></span>';
        }
    }
    
    $breadcrumb .= '</nav>';
    
    return $breadcrumb;
}

/**
 * Component: Stats Card
 * Renders statistics card
 */
function renderStatsCard($title, $value, $icon, $trend = null, $color = 'primary') {
    $trendHtml = '';
    if ($trend !== null) {
        $trendIcon = $trend >= 0 ? 'arrow-up' : 'arrow-down';
        $trendClass = $trend >= 0 ? 'trend-up' : 'trend-down';
        $trendValue = abs($trend);
        
        $trendHtml = '
            <div class="stats-trend ' . $trendClass . '">
                <i class="fas fa-' . $trendIcon . '"></i>
                <span>' . $trendValue . '%</span>
            </div>
        ';
    }
    
    return '
        <div class="stats-card stats-' . $color . '">
            <div class="stats-icon">
                <i class="fas fa-' . $icon . '"></i>
            </div>
            <div class="stats-content">
                <h3 class="stats-title">' . htmlspecialchars($title) . '</h3>
                <div class="stats-value">' . $value . '</div>
                ' . $trendHtml . '
            </div>
        </div>
    ';
}

/**
 * Component: Filter Pills
 * Renders filter pills for categories
 */
function renderFilterPills($categories, $activeCategory = 'all') {
    $pills = '<div class="filter-pills">';
    
    // Add "All" option
    $allActive = $activeCategory === 'all' ? ' active' : '';
    $pills .= '<button class="filter-pill' . $allActive . '" onclick="applyFilter(\'all\')">
        <i class="fas fa-th"></i> Wszystkie
    </button>';
    
    foreach ($categories as $category) {
        $active = $category['slug'] === $activeCategory ? ' active' : '';
        $pills .= '<button class="filter-pill' . $active . '" onclick="applyFilter(\'' . $category['slug'] . '\')">
            <i class="fas fa-' . ($category['icon'] ?? 'folder') . '"></i>
            ' . htmlspecialchars($category['name']) . '
            <span class="pill-count">' . $category['app_count'] . '</span>
        </button>';
    }
    
    $pills .= '</div>';
    
    return $pills;
}

/**
 * Component: Share Modal
 * Renders share modal for apps
 */
function renderShareModal($appId, $appTitle) {
    return '
        <div id="shareModal" class="modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Udostępnij aplikację</h3>
                    <button class="modal-close" onclick="closeShareModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="share-options">
                        <button class="share-option" onclick="shareOnFacebook(' . $appId . ')">
                            <i class="fab fa-facebook"></i>
                            <span>Facebook</span>
                        </button>
                        <button class="share-option" onclick="shareOnTwitter(' . $appId . ', \'' . htmlspecialchars($appTitle) . '\')">
                            <i class="fab fa-twitter"></i>
                            <span>Twitter</span>
                        </button>
                        <button class="share-option" onclick="shareOnLinkedIn(' . $appId . ')">
                            <i class="fab fa-linkedin"></i>
                            <span>LinkedIn</span>
                        </button>
                        <button class="share-option" onclick="copyShareLink(' . $appId . ')">
                            <i class="fas fa-link"></i>
                            <span>Kopiuj link</span>
                        </button>
                    </div>
                    <div class="share-link">
                        <input type="text" id="shareLink" value="' . (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/controllers/apps/app-details.php?id=' . $appId . '" readonly>
                        <button onclick="copyShareLink(' . $appId . ')">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    ';
}

/**
 * Helper: Highlight search term
 */
function highlightSearchTerm($text, $term) {
    if (empty($term)) return htmlspecialchars($text);
    
    $escapedTerm = preg_quote($term, '/');
    $pattern = "/($escapedTerm)/i";
    $replacement = '<mark>$1</mark>';
    
    return preg_replace($pattern, $replacement, htmlspecialchars($text));
}

/**
 * Helper: Format number
 */
function formatNumber($number) {
    if ($number >= 1000000) {
        return number_format($number / 1000000, 1) . 'M';
    } elseif ($number >= 1000) {
        return number_format($number / 1000, 1) . 'K';
    }
    return number_format($number);
}

/**
 * Helper: Get app status badge
 */
function getAppStatusBadge($status) {
    $badges = [
        'published' => '<span class="status-badge published"><i class="fas fa-check"></i> Opublikowana</span>',
        'featured' => '<span class="status-badge featured"><i class="fas fa-star"></i> Polecana</span>',
        'draft' => '<span class="status-badge draft"><i class="fas fa-edit"></i> Szkic</span>',
        'suspended' => '<span class="status-badge suspended"><i class="fas fa-ban"></i> Zawieszona</span>'
    ];
    
    return $badges[$status] ?? $badges['draft'];
}

/**
 * Helper: Get price display
 */
function getPriceDisplay($price, $isFree) {
    return '<span class="price free">Darmowe</span>';
}

/**
 * Helper: Get time ago
 */
function getTimeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'Przed chwilą';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minut' . ($minutes == 1 ? 'ę' : ($minutes < 5 ? 'y' : '')) . ' temu';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' godzin' . ($hours == 1 ? 'ę' : ($hours < 5 ? 'y' : '')) . ' temu';
    } elseif ($diff < 2592000) {
        $days = floor($diff / 86400);
        return $days . ' dni temu';
    } elseif ($diff < 31536000) {
        $months = floor($diff / 2592000);
        return $months . ' miesiąc' . ($months == 1 ? '' : ($months < 5 ? 'e' : 'y')) . ' temu';
    } else {
        $years = floor($diff / 31536000);
        return $years . ' lat' . ($years == 1 ? '' : ' temu');
    }
}

/**
 * Helper: Get user avatar
 */
function getUserAvatar($username, $avatarUrl = null, $size = 40) {
    if ($avatarUrl) {
        return '<img src="' . htmlspecialchars($avatarUrl) . '" alt="' . htmlspecialchars($username) . '" style="width: ' . $size . 'px; height: ' . $size . 'px; border-radius: 50%;">';
    }
    return '<img src="https://ui-avatars.com/api/?name=' . urlencode($username) . '&background=6366f1&color=fff&size=' . $size . '" alt="' . htmlspecialchars($username) . '" style="width: ' . $size . 'px; height: ' . $size . 'px; border-radius: 50%;">';
}

/**
 * Helper: Truncate text
 */
function truncateText($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return htmlspecialchars($text);
    }
    return htmlspecialchars(substr($text, 0, $length)) . $suffix;
}

/**
 * Helper: Sanitize filename
 */
function sanitizeFilename($filename) {
    // Remove any character that is not alphanumeric, space, or underscore
    $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $filename);
    
    // Remove any double dashes or underscores
    $filename = preg_replace('/[_\-]+/', '_', $filename);
    
    // Remove leading/trailing dashes or underscores
    $filename = trim($filename, '_-');
    
    return $filename;
}

/**
 * Helper: Generate slug
 */
function generateSlug($text) {
    // Convert to lowercase and replace spaces with hyphens
    $text = strtolower($text);
    
    // Replace special characters with hyphens
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    
    // Remove leading/trailing hyphens
    $text = trim($text, '-');
    
    // Remove multiple hyphens
    $text = preg_replace('/-+/', '-', $text);
    
    return $text;
}

/**
 * Helper: Validate email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Helper: Validate URL
 */
function validateUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Helper: Generate CSRF token
 */
function generateCSRFToken() {
    if (session_status() === PHP_SESSION_NONE) {
        // Don't start session here - it should be started in main files
        return null;
    }
    
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Helper: Verify CSRF token
 */
function verifyCSRFToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        // Don't start session here - it should be started in main files
        return false;
    }
    
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Helper: Log activity
 */
function logActivity($action, $details = []) {
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'action' => $action,
        'user_id' => $_SESSION['user']['id'] ?? null,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'details' => $details
    ];
    
    $logFile = __DIR__ . '/logs/activity.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * Helper: Send notification
 */
function sendNotification($userId, $type, $title, $message, $data = []) {
    // This would typically send a notification via email, push notification, or in-app notification
    // For now, we'll just log it
    logActivity('notification_sent', [
        'user_id' => $userId,
        'type' => $type,
        'title' => $title,
        'message' => $message,
        'data' => $data
    ]);
}

/**
 * Helper: Get app statistics
 */
function getAppStatistics($appId) {
    // This would typically fetch statistics from database
    // For now, return mock data
    return [
        'views' => rand(100, 10000),
        'downloads' => rand(50, 5000),
        'rating' => rand(30, 50) / 10,
        'reviews' => rand(5, 100),
        'favorites' => rand(10, 200)
    ];
}

/**
 * Helper: Get user statistics
 */
function getUserStatistics($userId) {
    // This would typically fetch user statistics from database
    // For now, return mock data
    return [
        'apps_created' => rand(1, 20),
        'total_downloads' => rand(100, 50000),
        'total_revenue' => rand(100, 10000),
        'followers' => rand(10, 1000),
        'rating' => rand(30, 50) / 10
    ];
}
?>
