/**
 * Beta Banner JavaScript for Bloxer Platform
 */

class BetaBanner {
    constructor() {
        this.banner = null;
        this.storageKey = 'bloxer_beta_banner_dismissed';
        this.init();
    }
    
    init() {
        // Check if banner was dismissed in last 24 hours
        if (this.isDismissedRecently()) {
            return;
        }
        
        this.createBanner();
        this.attachEventListeners();
        this.adjustBodyPadding();
    }
    
    createBanner() {
        const bannerHTML = `
            <div class="beta-banner" id="betaBanner">
                <span class="beta-icon">
                    <i class="fas fa-flask"></i>
                    BETA
                </span>
                <span class="beta-text">
                    Aplikacja jest w fazie rozwoju (beta). 
                    Jeśli zauważysz błąd, zgłoś go proszę na Discord: hmm067
                </span>
                <a href="#" class="discord-link" onclick="betaBanner.openDiscord(); return false;">
                    <i class="fab fa-discord"></i>
                    Discord Support
                </a>
                <button class="close-banner" onclick="betaBanner.closeBanner()" title="Ukryj na 24 godziny">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        document.body.insertAdjacentHTML('afterbegin', bannerHTML);
        this.banner = document.getElementById('betaBanner');
    }
    
    attachEventListeners() {
        // Auto-hide after 10 seconds on first visit
        if (!this.hasSeenBanner()) {
            setTimeout(() => {
                if (this.banner && !this.banner.classList.contains('hiding')) {
                    this.closeBanner();
                }
            }, 10000);
        }
        
        // Mark as seen
        this.markAsSeen();
    }
    
    adjustBodyPadding() {
        document.body.classList.add('has-beta-banner');
        
        // Adjust padding based on banner height
        const updatePadding = () => {
            if (this.banner && !this.banner.classList.contains('hiding')) {
                const bannerHeight = this.banner.offsetHeight;
                document.body.style.paddingTop = bannerHeight + 'px';
            }
        };
        
        // Initial padding
        setTimeout(updatePadding, 100);
        
        // Update on resize
        window.addEventListener('resize', updatePadding);
    }
    
    openDiscord() {
        // Try to open Discord app first, then fallback to web
        const discordUsername = 'hmm067';
        const discordUrl = `https://discord.com/users/${discordUsername}`;
        
        // Try to open Discord app protocol
        window.location.href = `discord://users/${discordUsername}`;
        
        // Fallback to web after a short delay
        setTimeout(() => {
            window.open(discordUrl, '_blank');
        }, 500);
        
        // Log the click for analytics
        this.logDiscordClick();
    }
    
    closeBanner() {
        if (!this.banner) return;
        
        this.banner.classList.add('hiding');
        
        // Remove banner after animation
        setTimeout(() => {
            if (this.banner) {
                this.banner.remove();
                this.banner = null;
            }
            document.body.classList.remove('has-beta-banner');
            document.body.style.paddingTop = '';
        }, 300);
        
        // Store dismissal time
        this.setDismissedTime();
    }
    
    isDismissedRecently() {
        const dismissedTime = localStorage.getItem(this.storageKey);
        if (!dismissedTime) return false;
        
        const now = Date.now();
        const dismissed = parseInt(dismissedTime);
        const hoursSinceDismissed = (now - dismissed) / (1000 * 60 * 60);
        
        return hoursSinceDismissed < 24; // Dismissed less than 24 hours ago
    }
    
    setDismissedTime() {
        localStorage.setItem(this.storageKey, Date.now().toString());
    }
    
    hasSeenBanner() {
        return localStorage.getItem('bloxer_beta_banner_seen') === 'true';
    }
    
    markAsSeen() {
        localStorage.setItem('bloxer_beta_banner_seen', 'true');
    }
    
    logDiscordClick() {
        // Simple analytics logging
        try {
            const clickData = {
                timestamp: new Date().toISOString(),
                action: 'discord_support_click',
                page: window.location.pathname,
                userAgent: navigator.userAgent.substring(0, 100)
            };
            
            // Send to analytics endpoint if available
            fetch('/api/analytics/event', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(clickData)
            }).catch(() => {
                // Silently fail - analytics is not critical
            });
        } catch (e) {
            // Silently fail
        }
    }
    
    // Public method to manually show banner (for testing)
    showBanner() {
        localStorage.removeItem(this.storageKey);
        if (this.banner) {
            this.banner.remove();
        }
        this.init();
    }
}

// Initialize banner when DOM is ready
let betaBanner;
document.addEventListener('DOMContentLoaded', function() {
    betaBanner = new BetaBanner();
});

// Global function for manual banner control
window.showBetaBanner = function() {
    if (betaBanner) {
        betaBanner.showBanner();
    }
};
