/**
 * Visitor Tracking Script
 * This script tracks visitor activity on published projects
 * Include this script in your project HTML to enable visitor tracking
 */

(function() {
    'use strict';
    
    // Configuration
    const config = {
        endpoint: window.location.origin + '/bloxer/controllers/tools/tools.php',
        projectId: new URLSearchParams(window.location.search).get('project_id'),
        trackPageViews: true,
        trackClicks: true,
        trackScrolls: true,
        trackTime: true,
        sessionTimeout: 30 * 60 * 1000, // 30 minutes
        batchSize: 10,
        flushInterval: 5000 // 5 seconds
    };
    
    // State
    let sessionId = null;
    let startTime = Date.now();
    let lastActivity = Date.now();
    let eventQueue = [];
    let flushTimer = null;
    let pageViewTracked = false;
    
    // Utility functions
    function generateSessionId() {
        return 'session_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
    }
    
    function getVisitorData() {
        return {
            url: window.location.href,
            path: window.location.pathname,
            referrer: document.referrer || 'direct',
            userAgent: navigator.userAgent,
            language: navigator.language,
            platform: navigator.platform,
            screen: {
                width: screen.width,
                height: screen.height
            },
            viewport: {
                width: window.innerWidth,
                height: window.innerHeight
            },
            timestamp: Date.now(),
            sessionId: sessionId
        };
    }
    
    function sendToServer(action, data) {
        const payload = {
            action: action,
            project_id: config.projectId,
            visitor_data: JSON.stringify(data),
            page_url: window.location.pathname
        };
        
        // Use sendBeacon for better performance when available
        if (navigator.sendBeacon) {
            const formData = new FormData();
            for (const key in payload) {
                formData.append(key, payload[key]);
            }
            navigator.sendBeacon(config.endpoint, formData);
        } else {
            // Fallback to fetch
            fetch(config.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(payload)
            }).catch(err => {
                console.warn('Failed to send tracking data:', err);
            });
        }
    }
    
    function trackEvent(eventType, eventData) {
        const data = {
            ...getVisitorData(),
            eventType: eventType,
            eventData: eventData
        };
        
        if (eventType === 'page_view') {
            sendToServer('track_visitor', data);
        } else {
            eventQueue.push(data);
            
            if (eventQueue.length >= config.batchSize) {
                flushEvents();
            }
        }
    }
    
    function flushEvents() {
        if (eventQueue.length === 0) return;
        
        const events = eventQueue.splice(0, config.batchSize);
        events.forEach(event => {
            sendToServer('track_visitor', event);
        });
    }
    
    function updateLastActivity() {
        lastActivity = Date.now();
    }
    
    function checkSessionTimeout() {
        if (Date.now() - lastActivity > config.sessionTimeout) {
            sessionId = generateSessionId();
            trackEvent('session_start', { newSession: true });
        }
    }
    
    // Tracking functions
    function trackPageView() {
        if (pageViewTracked) return;
        
        trackEvent('page_view', {
            title: document.title,
            loadTime: performance.timing.loadEventEnd - performance.timing.navigationStart
        });
        
        pageViewTracked = true;
        updateLastActivity();
    }
    
    function trackClick(event) {
        if (!config.trackClicks) return;
        
        const target = event.target;
        const tagName = target.tagName.toLowerCase();
        const className = target.className;
        const id = target.id;
        const text = target.textContent ? target.textContent.substring(0, 50) : '';
        
        trackEvent('click', {
            tagName: tagName,
            className: className,
            id: id,
            text: text,
            x: event.clientX,
            y: event.clientY
        });
        
        updateLastActivity();
    }
    
    function trackScroll() {
        if (!config.trackScrolls) return;
        
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        const scrollHeight = document.documentElement.scrollHeight;
        const clientHeight = document.documentElement.clientHeight;
        const scrollPercent = Math.round((scrollTop / (scrollHeight - clientHeight)) * 100);
        
        trackEvent('scroll', {
            scrollTop: scrollTop,
            scrollPercent: scrollPercent
        });
        
        updateLastActivity();
    }
    
    function trackTimeOnPage() {
        if (!config.trackTime) return;
        
        const timeSpent = Date.now() - startTime;
        trackEvent('time_on_page', {
            timeSpent: timeSpent,
            timeSpentFormatted: Math.round(timeSpent / 1000) + 's'
        });
    }
    
    // Initialize tracking
    function init() {
        // Check if project ID is available
        if (!config.projectId) {
            console.warn('Visitor Tracker: No project_id found in URL');
            return;
        }
        
        // Generate session ID
        sessionId = generateSessionId();
        
        // Track initial page view
        trackPageView();
        
        // Set up event listeners
        if (config.trackClicks) {
            document.addEventListener('click', trackClick, true);
        }
        
        if (config.trackScrolls) {
            let scrollTimeout;
            window.addEventListener('scroll', function() {
                clearTimeout(scrollTimeout);
                scrollTimeout = setTimeout(trackScroll, 100);
            });
        }
        
        // Set up periodic flush
        flushTimer = setInterval(flushEvents, config.flushInterval);
        
        // Set up session timeout check
        setInterval(checkSessionTimeout, 60000); // Check every minute
        
        // Track time on page when user leaves
        window.addEventListener('beforeunload', function() {
            trackTimeOnPage();
            flushEvents(); // Flush any remaining events
        });
        
        // Track visibility changes
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'visible') {
                trackEvent('page_focus', {});
                updateLastActivity();
            } else {
                trackEvent('page_blur', {});
            }
        });
        
        // Track errors
        window.addEventListener('error', function(event) {
            trackEvent('error', {
                message: event.message,
                filename: event.filename,
                lineno: event.lineno,
                colno: event.colno
            });
        });
        
        console.log('Visitor Tracker initialized for project:', config.projectId);
    }
    
    // Public API
    window.BloxerTracker = {
        trackEvent: trackEvent,
        trackPageView: trackPageView,
        flushEvents: flushEvents,
        config: config
    };
    
    // Start tracking when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
})();
