// Enhanced Marketplace Functions - Additional JavaScript for Marketplace

// Enhanced Search Functionality
function performSearch() {
    const searchValue = document.querySelector('.search-box').value.trim();
    const currentUrl = new URL(window.location);
    
    if (searchValue) {
        currentUrl.searchParams.set('search', searchValue);
    } else {
        currentUrl.searchParams.delete('search');
    }
    
    currentUrl.searchParams.delete('page');
    window.location.href = currentUrl.toString();
}

// Enhanced App Opening
function openApp(appId) {
    // Add loading state
    const card = document.querySelector(`[onclick="openApp(${appId})"]`);
    if (card) {
        card.style.opacity = '0.7';
        card.style.pointerEvents = 'none';
        
        // Add loading spinner
        const spinner = document.createElement('div');
        spinner.className = 'loading-spinner';
        spinner.style.position = 'absolute';
        spinner.style.top = '50%';
        spinner.style.left = '50%';
        spinner.style.transform = 'translate(-50%, -50%)';
        card.appendChild(spinner);
    }
    
    // Redirect to app details
    window.location.href = `app-details.php?id=${appId}`;
}

// Enhanced Filter Functionality
function applyFilter(category) {
    const currentUrl = new URL(window.location);
    
    if (category && category !== 'all') {
        currentUrl.searchParams.set('category', category);
    } else {
        currentUrl.searchParams.delete('category');
    }
    
    currentUrl.searchParams.delete('page');
    window.location.href = currentUrl.toString();
}

// Enhanced Sort Functionality
function applySort(sortBy) {
    const currentUrl = new URL(window.location);
    
    if (sortBy && sortBy !== 'popular') {
        currentUrl.searchParams.set('sort', sortBy);
    } else {
        currentUrl.searchParams.delete('sort');
    }
    
    currentUrl.searchParams.delete('page');
    window.location.href = currentUrl.toString();
}

// Enhanced Category Navigation
function navigateToCategory(category) {
    applyFilter(category);
}

// Enhanced App Installation
function installApp(appId, event) {
    event.stopPropagation();
    
    const user = document.body.dataset.loggedIn === 'true';
    
    if (!user) {
        showNotification('Musisz być zalogowany, aby zainstalować aplikację', 'warning');
        setTimeout(() => {
            window.location.href = 'login.php';
        }, 2000);
        return;
    }
    
    // Show loading state
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Instalowanie...';
    button.disabled = true;
    
    // Simulate installation (replace with actual API call)
    fetch('app_actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=install_app&app_id=${appId}&csrf_token=<?php echo $auth->getCSRFToken(); ?>`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Aplikacja została pomyślnie zainstalowana!', 'success');
            button.innerHTML = '<i class="fas fa-check"></i> Zainstalowano';
            button.classList.add('installed');
            
            // Update download count
            updateDownloadCount(appId);
        } else {
            showNotification(data.error || 'Błąd instalacji', 'error');
            button.innerHTML = originalText;
            button.disabled = false;
        }
    })
    .catch(error => {
        console.error('Install error:', error);
        showNotification('Błąd połączenia', 'error');
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

// Enhanced App Rating
function rateApp(appId, rating) {
    const user = document.body.dataset.loggedIn === 'true';
    
    if (!user) {
        showNotification('Musisz być zalogowany, aby ocenić aplikację', 'warning');
        setTimeout(() => {
            window.location.href = 'login.php';
        }, 2000);
        return;
    }
    
    // Show rating modal or send rating directly
    fetch('ratings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=submit_rating&app_id=${appId}&rating=${rating}&csrf_token=<?php echo $auth->getCSRFToken(); ?>`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Dziękujemy za ocenę!', 'success');
            updateAppRating(appId, rating);
        } else {
            showNotification(data.error || 'Błąd oceny', 'error');
        }
    })
    .catch(error => {
        console.error('Rating error:', error);
        showNotification('Błąd połączenia', 'error');
    });
}

// Enhanced Notification System
function showNotification(message, type = 'info') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(notification => notification.remove());
    
    // Create new notification
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-icon">
            <i class="fas fa-${getNotificationIcon(type)}"></i>
        </div>
        <div class="notification-content">
            ${message}
        </div>
        <button class="notification-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    // Add to page
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

function getNotificationIcon(type) {
    const icons = {
        'success': 'check-circle',
        'error': 'exclamation-triangle',
        'warning': 'exclamation-circle',
        'info': 'info-circle'
    };
    return icons[type] || 'info-circle';
}

// Enhanced Download Count Update
function updateDownloadCount(appId) {
    const downloadElements = document.querySelectorAll(`[data-app-id="${appId}"] .app-downloads`);
    downloadElements.forEach(element => {
        const currentCount = parseInt(element.textContent.replace(/[^\d]/g, ''));
        const newCount = currentCount + 1;
        element.innerHTML = `<i class="fas fa-download"></i> ${newCount.toLocaleString()}`;
    });
}

// Enhanced Rating Update
function updateAppRating(appId, newRating) {
    const ratingElements = document.querySelectorAll(`[data-app-id="${appId}"] .app-rating`);
    ratingElements.forEach(element => {
        element.innerHTML = `<i class="fas fa-star"></i> ${newRating.toFixed(1)}`;
    });
}

// Enhanced Search Suggestions
function setupSearchSuggestions() {
    const searchBox = document.querySelector('.search-box');
    const suggestionsContainer = document.createElement('div');
    suggestionsContainer.className = 'search-suggestions';
    suggestionsContainer.style.display = 'none';
    
    searchBox.parentNode.appendChild(suggestionsContainer);
    
    searchBox.addEventListener('input', debounce(function() {
        const query = this.value.trim();
        
        if (query.length < 2) {
            suggestionsContainer.style.display = 'none';
            return;
        }
        
        // Fetch suggestions (replace with actual API call)
        fetch(`marketplace-api.php?action=search_suggestions&q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.suggestions.length > 0) {
                    displaySuggestions(data.suggestions);
                } else {
                    suggestionsContainer.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Suggestions error:', error);
                suggestionsContainer.style.display = 'none';
            });
    }, 300));
    
    function displaySuggestions(suggestions) {
        suggestionsContainer.innerHTML = suggestions.map(suggestion => `
            <div class="suggestion-item" onclick="selectSuggestion('${suggestion.query}')">
                <i class="fas fa-search"></i>
                <span>${suggestion.highlight}</span>
                <small>${suggestion.category}</small>
            </div>
        `).join('');
        
        suggestionsContainer.style.display = 'block';
    }
    
    window.selectSuggestion = function(query) {
        searchBox.value = query;
        suggestionsContainer.style.display = 'none';
        performSearch();
    };
    
    // Hide suggestions when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchBox.contains(e.target) && !suggestionsContainer.contains(e.target)) {
            suggestionsContainer.style.display = 'none';
        }
    });
}

// Enhanced Lazy Loading
function setupLazyLoading() {
    const images = document.querySelectorAll('img[data-src]');
    
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.classList.add('loaded');
                observer.unobserve(img);
            }
        });
    });
    
    images.forEach(img => imageObserver.observe(img));
}

// Enhanced Infinite Scroll
function setupInfiniteScroll() {
    const loadingIndicator = document.createElement('div');
    loadingIndicator.className = 'loading-indicator';
    loadingIndicator.innerHTML = '<div class="loading-spinner"></div><p>Ładowanie więcej aplikacji...</p>';
    loadingIndicator.style.display = 'none';
    
    const appsGrid = document.querySelector('.apps-grid');
    if (appsGrid) {
        appsGrid.parentNode.appendChild(loadingIndicator);
    }
    
    let loading = false;
    let page = parseInt(document.body.dataset.page) || 1;
    let hasMore = document.body.dataset.hasMore === 'true';
    
    const scrollObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting && !loading && hasMore) {
                loadMoreApps();
            }
        });
    });
    
    if (loadingIndicator) {
        scrollObserver.observe(loadingIndicator);
    }
    
    function loadMoreApps() {
        if (loading) return;
        
        loading = true;
        loadingIndicator.style.display = 'block';
        
        const nextPage = page + 1;
        const currentUrl = new URL(window.location);
        currentUrl.searchParams.set('page', nextPage);
        
        fetch(currentUrl.toString())
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newApps = doc.querySelectorAll('.app-card');
                
                if (newApps.length > 0) {
                    newApps.forEach(app => appsGrid.appendChild(app));
                    page = nextPage;
                    
                    // Check if there are more pages
                    const pagination = doc.querySelector('.pagination');
                    hasMore = pagination && pagination.querySelector(`a[href*="page=${nextPage + 1}"]`);
                    
                    setupLazyLoading();
                } else {
                    hasMore = false;
                }
            })
            .catch(error => {
                console.error('Load more error:', error);
                showNotification('Błąd ładowania więcej aplikacji', 'error');
            })
            .finally(() => {
                loading = false;
                loadingIndicator.style.display = 'none';
            });
    }
}

// Enhanced Keyboard Navigation
function setupKeyboardNavigation() {
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + K for search
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            const searchBox = document.querySelector('.search-box');
            if (searchBox) {
                searchBox.focus();
                searchBox.select();
            }
        }
        
        // Escape to clear search
        if (e.key === 'Escape') {
            const searchBox = document.querySelector('.search-box');
            if (searchBox && document.activeElement === searchBox) {
                searchBox.value = '';
                searchBox.blur();
            }
        }
        
        // Arrow keys for app navigation
        if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
            const appCards = document.querySelectorAll('.app-card');
            const focusedCard = document.activeElement.closest('.app-card');
            
            if (focusedCard) {
                const currentIndex = Array.from(appCards).indexOf(focusedCard);
                let newIndex;
                
                if (e.key === 'ArrowRight') {
                    newIndex = currentIndex + 1;
                } else {
                    newIndex = currentIndex - 1;
                }
                
                if (newIndex >= 0 && newIndex < appCards.length) {
                    appCards[newIndex].focus();
                }
            }
        }
    });
}

// Enhanced Theme Toggle
function setupThemeToggle() {
    const themeToggle = document.createElement('button');
    themeToggle.className = 'theme-toggle';
    themeToggle.innerHTML = '<i class="fas fa-moon"></i>';
    themeToggle.title = 'Przełącz motyw';
    
    const userMenu = document.querySelector('.user-menu');
    if (userMenu) {
        userMenu.appendChild(themeToggle);
    }
    
    themeToggle.addEventListener('click', function() {
        document.body.classList.toggle('dark-theme');
        const isDark = document.body.classList.contains('dark-theme');
        this.innerHTML = isDark ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
        
        // Save preference
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
    });
    
    // Load saved preference
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.body.classList.add('dark-theme');
        themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
    }
}

// Utility Functions
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function throttle(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

// Initialize everything when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    setupSearchSuggestions();
    setupLazyLoading();
    setupInfiniteScroll();
    setupKeyboardNavigation();
    setupThemeToggle();
    
    // Add smooth scrolling
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth' });
            }
        });
    });
});

// Enhanced Performance Monitoring
function trackPerformance() {
    // Track page load time
    window.addEventListener('load', function() {
        const loadTime = performance.timing.loadEventEnd - performance.timing.navigationStart;
        
        // Send to analytics if needed
        if (typeof gtag !== 'undefined') {
            gtag('event', 'page_load_time', {
                custom_parameter: loadTime
            });
        }
    });
    
    // Track user interactions
    document.addEventListener('click', function(e) {
        const target = e.target.closest('[data-track]');
        if (target) {
            const eventName = target.dataset.track;
            
            // Send to analytics if needed
            if (typeof gtag !== 'undefined') {
                gtag('event', 'user_interaction', {
                    event_category: 'marketplace',
                    event_label: eventName
                });
            }
        }
    });
}

// Initialize performance tracking
trackPerformance();
