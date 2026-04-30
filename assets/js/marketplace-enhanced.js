// Enhanced Marketplace JavaScript
class MarketplaceEnhanced {
    constructor() {
        this.currentCategory = 'all';
        this.currentSort = 'popular';
        this.searchQuery = '';
        this.currentPage = 1;
        this.apps = [];
        this.featuredApps = [];
        this.categories = [];
        this.favorites = this.loadFavorites();
        
        this.init();
    }
    
    async init() {
        this.setupEventListeners();
        await this.loadData();
        this.renderApps();
        this.initializeInfiniteScroll();
        this.setupKeyboardShortcuts();
    }
    
    setupEventListeners() {
        // Search functionality
        const searchBox = document.querySelector('.search-box');
        if (searchBox) {
            searchBox.addEventListener('input', this.debounce((e) => {
                this.handleSearch(e.target.value);
            }, 500));
        }
        
        // Category filters
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleCategoryFilter(tab.dataset.category || 'all');
            });
        });
        
        // Sort dropdown
        const sortDropdown = document.querySelector('.sort-dropdown');
        if (sortDropdown) {
            sortDropdown.addEventListener('change', (e) => {
                this.handleSort(e.target.value);
            });
        }
        
        // App card interactions
        document.addEventListener('click', (e) => {
            if (e.target.closest('.btn-install, .btn-favorite, .btn-card')) {
                return;
            }
            const card = e.target.closest('.app-card');
            if (card) {
                this.handleAppClick(card);
            }
        });
        
        // Install buttons
        document.addEventListener('click', (e) => {
            if (e.target.closest('.btn-install')) {
                e.preventDefault();
                e.stopPropagation();
                this.handleInstall(e.target.closest('.btn-install'));
            }
        });
        
        // Favorite buttons
        document.addEventListener('click', (e) => {
            if (e.target.closest('.btn-favorite')) {
                e.preventDefault();
                this.handleFavorite(e.target.closest('.btn-favorite'));
            }
        });
    }
    
    async loadData() {
        try {
            // Load featured apps
            const featuredResponse = await fetch('marketplace-api.php?action=get_featured');
            const featuredData = await featuredResponse.json();
            if (featuredData.success) {
                this.featuredApps = featuredData.apps;
            }
            
            // Load categories
            const categoriesResponse = await fetch('marketplace-api.php?action=get_categories');
            const categoriesData = await categoriesResponse.json();
            if (categoriesData.success) {
                this.categories = categoriesData.categories;
            }
            
            // Load apps
            await this.loadApps();
        } catch (error) {
            console.error('Error loading marketplace data:', error);
        }
    }
    
    async loadApps() {
        try {
            const params = new URLSearchParams({
                category: this.currentCategory,
                sort: this.currentSort,
                page: this.currentPage
            });
            
            if (this.searchQuery) {
                params.set('search', this.searchQuery);
            }
            
            const response = await fetch(`marketplace-api.php?action=get_apps&${params}`);
            const data = await response.json();
            
            if (data.success) {
                if (this.currentPage === 1) {
                    this.apps = data.apps;
                } else {
                    this.apps = [...this.apps, ...data.apps];
                }
            }
        } catch (error) {
            console.error('Error loading apps:', error);
        }
    }
    
    handleSearch(query) {
        this.searchQuery = query;
        this.currentPage = 1;
        this.loadApps().then(() => this.renderApps());
        this.updateURL();
    }
    
    handleCategoryFilter(category) {
        this.currentCategory = category;
        this.currentPage = 1;
        
        // Update active state
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        document.querySelector(`[data-category="${category}"]`)?.classList.add('active');
        
        this.loadApps().then(() => this.renderApps());
        this.updateURL();
    }
    
    handleSort(sort) {
        this.currentSort = sort;
        this.currentPage = 1;
        this.loadApps().then(() => this.renderApps());
        this.updateURL();
    }
    
    handleAppClick(appCard) {
        const appId = appCard.dataset.appId;
        if (appId) {
            this.openAppDetails(appId);
        }
    }
    
    async handleInstall(button) {
        const appId = button.dataset.appId;
        
        if (!appId) return;
        
        if (!this.isLoggedIn()) {
            this.showLoginPrompt();
            return;
        }
        
        try {
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Installing...';
            
            const response = await fetch('marketplace-api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=install_app&app_id=${appId}`
            });
            
            const result = await response.json();
            
            if (result.success) {
                button.innerHTML = '<i class="fas fa-check"></i> Installed';
                button.classList.add('installed');
                this.showNotification('App installed successfully!', 'success');
                
                // Update download count
                this.updateAppDownloadCount(appId);
            } else {
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-download"></i> Install';
                this.showNotification(result.error || 'Failed to install app', 'error');
            }
        } catch (error) {
            console.error('Error installing app:', error);
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-download"></i> Install';
            this.showNotification('An error occurred while installing the app', 'error');
        }
    }
    
    async handleFavorite(button) {
        const appId = button.dataset.appId;
        
        if (!appId) return;
        
        if (!this.isLoggedIn()) {
            this.showLoginPrompt();
            return;
        }
        
        this.toggleFavoriteState(appId);
        const isFavorited = this.favorites.includes(String(appId));
        button.classList.toggle('favorited', isFavorited);
        const icon = button.querySelector('i');
        icon.className = isFavorited ? 'fas fa-heart' : 'far fa-heart';
        
        this.showNotification(isFavorited ? 'Added to favorites' : 'Removed from favorites', 'success');
    }
    
    renderApps() {
        const appsGrid = document.querySelector('.apps-grid');
        if (!appsGrid) return;
        
        if (this.apps.length === 0) {
            appsGrid.innerHTML = `
                <div class="no-results">
                    <i class="fas fa-search"></i>
                    <h3>No applications found</h3>
                    <p>Try adjusting your search terms or browse different categories.</p>
                </div>
            `;
            return;
        }
        
        appsGrid.innerHTML = this.apps.map(app => this.createAppCard(app)).join('');
        
        // Add data attributes to cards
        this.apps.forEach((app, index) => {
            const card = appsGrid.children[index];
            if (card) {
                card.dataset.appId = app.id;
                
                const installBtn = card.querySelector('.btn-install');
                if (installBtn) {
                    installBtn.dataset.appId = app.id;
                }
                
                const favoriteBtn = card.querySelector('.btn-favorite');
                if (favoriteBtn) {
                    favoriteBtn.dataset.appId = app.id;
                }
            }
        });
    }
    
    createAppCard(app) {
        const isInstalled = app.is_installed || false;
        const isFavorited = this.favorites.includes(String(app.id));
        
        return `
            <div class="app-card" data-app-id="${app.id}">
                <div class="app-thumbnail">
                    ${app.thumbnail_url ? 
                        `<img src="${app.thumbnail_url}" alt="${app.title}">` :
                        `<i class="fas fa-rocket"></i>`
                    }
                </div>
                <div class="app-content">
                    <div class="app-developer">
                        <img src="${app.developer_avatar || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(app.developer_name) + '&background=6366f1&color=fff'}" 
                             alt="Developer" class="developer-avatar">
                        <span class="developer-name">${app.developer_name}</span>
                    </div>
                    <h3 class="app-title">${app.title}</h3>
                    <p class="app-description">${app.short_description || app.description.substring(0, 100) + '...'}</p>
                    <div class="app-meta">
                        <div class="app-category">
                            <i class="fas fa-${app.category_icon || 'folder'}"></i>
                            ${app.category_name || 'General'}
                        </div>
                        <div class="app-rating">
                            <i class="fas fa-star"></i>
                            ${Number(app.rating).toFixed(1)}
                        </div>
                    </div>
                    <div class="app-stats">
                        <div class="app-downloads">
                            <i class="fas fa-download"></i>
                            ${Number(app.download_count).toLocaleString()}
                        </div>
                        <div class="app-price">
                            ${app.is_free ? 'Free' : '$' + Number(app.price).toFixed(2)}
                        </div>
                    </div>
                    <div class="app-actions">
                        <button class="btn-install ${isInstalled ? 'installed' : ''}" 
                                ${isInstalled ? 'disabled' : ''}>
                            <i class="fas fa-${isInstalled ? 'check' : 'download'}"></i>
                            ${isInstalled ? 'Installed' : 'Install'}
                        </button>
                        <button class="btn-favorite ${isFavorited ? 'favorited' : ''}" data-app-id="${app.id}">
                            <i class="${isFavorited ? 'fas' : 'far'} fa-heart"></i>
                        </button>
                    </div>
                    <div class="app-card-actions">
                        <a href="app.php?id=${app.id}" class="btn-card btn-secondary" onclick="event.stopPropagation();">
                            <i class="fas fa-eye"></i>
                            View offer
                        </a>
                        ${app.demo_url ? `<a href="${app.demo_url}" target="_blank" rel="noreferrer noopener" class="btn-card btn-primary" onclick="event.stopPropagation();"><i class="fas fa-play-circle"></i> Live preview</a>` : ''}
                    </div>
                </div>
            </div>
        `;
    }
    
    openAppDetails(appId) {
        window.location.href = `app-details.php?id=${appId}`;
    }
    
    updateAppDownloadCount(appId) {
        const app = this.apps.find(a => a.id === appId);
        if (app) {
            app.download_count++;
            app.is_installed = true;
            this.renderApps();
        }
    }

    loadFavorites() {
        try {
            return JSON.parse(localStorage.getItem('bloxer_favorites')) || [];
        } catch {
            return [];
        }
    }

    saveFavorites() {
        try {
            localStorage.setItem('bloxer_favorites', JSON.stringify(this.favorites));
        } catch (error) {
            console.warn('Favorites storage error', error);
        }
    }

    toggleFavoriteState(appId) {
        const key = String(appId);
        if (this.favorites.includes(key)) {
            this.favorites = this.favorites.filter(id => id !== key);
        } else {
            this.favorites.push(key);
        }
        this.saveFavorites();
    }
    
    initializeInfiniteScroll() {
        let isLoading = false;
        
        const loadMoreApps = async () => {
            if (isLoading) return;
            
            const scrollPosition = window.innerHeight + window.pageYOffset;
            const documentHeight = document.documentElement.offsetHeight;
            
            if (scrollPosition >= documentHeight - 1000) {
                isLoading = true;
                this.currentPage++;
                
                await this.loadApps();
                this.renderApps();
                
                isLoading = false;
            }
        };
        
        window.addEventListener('scroll', this.debounce(loadMoreApps, 100));
    }
    
    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + K for search
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                const searchBox = document.querySelector('.search-box');
                if (searchBox) {
                    searchBox.focus();
                }
            }
            
            // Escape to clear search
            if (e.key === 'Escape') {
                const searchBox = document.querySelector('.search-box');
                if (searchBox && document.activeElement === searchBox) {
                    searchBox.value = '';
                    this.handleSearch('');
                }
            }
        });
    }
    
    updateURL() {
        const params = new URLSearchParams();
        
        if (this.currentCategory !== 'all') {
            params.set('category', this.currentCategory);
        }
        
        if (this.searchQuery) {
            params.set('search', this.searchQuery);
        }
        
        if (this.currentSort !== 'popular') {
            params.set('sort', this.currentSort);
        }
        
        const url = params.toString() ? `marketplace.php?${params}` : 'marketplace.php';
        window.history.replaceState({}, '', url);
    }
    
    isLoggedIn() {
        // Check if user is logged in (implementation needed)
        return document.querySelector('.user-menu') !== null;
    }
    
    showLoginPrompt() {
        this.showNotification('Please login to install apps', 'info');
        setTimeout(() => {
            window.location.href = 'login.php';
        }, 2000);
    }
    
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 10000;
            transform: translateX(100%);
            transition: transform 0.3s ease;
            max-width: 300px;
        `;
        
        switch(type) {
            case 'success':
                notification.style.backgroundColor = '#10b981';
                break;
            case 'error':
                notification.style.backgroundColor = '#ef4444';
                break;
            default:
                notification.style.backgroundColor = '#6366f1';
        }
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 100);
        
        setTimeout(() => {
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                document.body.removeChild(notification);
            }, 300);
        }, 3000);
    }
    
    debounce(func, wait) {
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
}

// Advanced search functionality
class AdvancedSearch {
    constructor() {
        this.searchIndex = {};
        this.init();
    }
    
    init() {
        this.buildSearchIndex();
    }
    
    buildSearchIndex() {
        // Build search index for faster searching
        // Implementation needed
    }
    
    search(query) {
        // Advanced search with filters
        // Implementation needed
    }
}

// Marketplace analytics
class MarketplaceAnalytics {
    constructor() {
        this.trackViews();
        this.trackInteractions();
    }
    
    trackViews() {
        // Track app views
        document.addEventListener('click', (e) => {
            if (e.target.closest('.app-card')) {
                const appId = e.target.closest('.app-card').dataset.appId;
                this.trackEvent('app_view', { app_id: appId });
            }
        });
    }
    
    trackInteractions() {
        // Track installs, favorites, etc.
        // Implementation needed
    }
    
    trackEvent(event, data) {
        // Send analytics data
        // Implementation needed
        // Analytics event tracked
    }
}

// Initialize enhanced marketplace
document.addEventListener('DOMContentLoaded', () => {
    new MarketplaceEnhanced();
    new MarketplaceAnalytics();
});

// Export for use in other files
window.MarketplaceEnhanced = MarketplaceEnhanced;
