/**
 * Sandbox Bridge - Secure Communication Between Parent and Iframe
 * 
 * This script provides a secure bridge for communication between the parent window
 * and the sandboxed iframe containing the app.
 */

class SandboxBridge {
    constructor() {
        this.parentOrigin = window.location.origin;
        this.isIframe = window !== window.parent;
        this.messageHandlers = new Map();
        this.init();
    }

    init() {
        if (this.isIframe) {
            this.setupIframeListeners();
            this.notifyParent('app-ready', { timestamp: Date.now() });
        } else {
            this.setupParentListeners();
        }
    }

    /**
     * Setup listeners for iframe context
     */
    setupIframeListeners() {
        window.addEventListener('message', (event) => {
            // Verify origin for security
            if (event.origin !== this.parentOrigin) {
                console.warn('Received message from untrusted origin:', event.origin);
                return;
            }

            const data = event.data;
            if (data.type === 'sandbox-init') {
                this.handleSandboxInit(data);
            }
        });

        // Setup error handling
        window.addEventListener('error', (event) => {
            this.notifyParent('app-error', {
                message: event.message,
                filename: event.filename,
                lineno: event.lineno,
                colno: event.colno,
                timestamp: Date.now()
            });
        });

        // Setup unhandled promise rejection handling
        window.addEventListener('unhandledrejection', (event) => {
            this.notifyParent('app-error', {
                message: 'Unhandled Promise Rejection: ' + event.reason,
                timestamp: Date.now()
            });
        });
    }

    /**
     * Setup listeners for parent context
     */
    setupParentListeners() {
        window.addEventListener('message', (event) => {
            // Verify origin for security
            if (event.origin !== this.parentOrigin) {
                return;
            }

            const data = event.data;
            this.handleMessage(data);
        });
    }

    /**
     * Handle sandbox initialization
     */
    handleSandboxInit(data) {
        console.log('Sandbox initialized:', data);
        
        // Setup app-specific functionality
        this.setupAppFeatures();
        
        // Notify parent that app is ready
        this.notifyParent('app-ready', {
            appId: data.appId,
            features: this.getAvailableFeatures(),
            timestamp: Date.now()
        });
    }

    /**
     * Setup app features within sandbox
     */
    setupAppFeatures() {
        // Override console methods to send to parent
        const originalConsole = {
            log: console.log,
            warn: console.warn,
            error: console.error,
            info: console.info
        };

        ['log', 'warn', 'error', 'info'].forEach(method => {
            console[method] = (...args) => {
                // Send to parent for logging
                this.notifyParent('app-console', {
                    level: method,
                    args: args,
                    timestamp: Date.now()
                });
                
                // Also log in iframe for debugging
                originalConsole[method].apply(console, args);
            };
        });

        // Setup fullscreen API bridge
        this.setupFullscreenBridge();
        
        // Setup storage bridge (if needed)
        this.setupStorageBridge();
    }

    /**
     * Setup fullscreen API bridge
     */
    setupFullscreenBridge() {
        // Override fullscreen requests to go through parent
        const originalRequestFullscreen = Element.prototype.requestFullscreen;
        
        Element.prototype.requestFullscreen = function() {
            SandboxBridge.getInstance().notifyParent('app-action', {
                action: 'request-fullscreen',
                timestamp: Date.now()
            });
        };
    }

    /**
     * Setup storage bridge for sandboxed storage
     */
    setupStorageBridge() {
        // Create sandbox-specific storage namespace
        const sandboxStorage = {
            get: (key) => {
                try {
                    const item = localStorage.getItem(`sandbox_${key}`);
                    return item ? JSON.parse(item) : null;
                } catch (e) {
                    return null;
                }
            },
            set: (key, value) => {
                try {
                    localStorage.setItem(`sandbox_${key}`, JSON.stringify(value));
                    return true;
                } catch (e) {
                    return false;
                }
            },
            remove: (key) => {
                try {
                    localStorage.removeItem(`sandbox_${key}`);
                    return true;
                } catch (e) {
                    return false;
                }
            }
        };

        // Expose sandbox storage to app
        window.sandboxStorage = sandboxStorage;
    }

    /**
     * Get available features in sandbox
     */
    getAvailableFeatures() {
        return {
            localStorage: typeof Storage !== 'undefined',
            sessionStorage: typeof Storage !== 'undefined',
            canvas: !!document.createElement('canvas').getContext,
            webgl: !!document.createElement('canvas').getContext('webgl'),
            geolocation: !!navigator.geolocation,
            camera: !!navigator.mediaDevices && !!navigator.mediaDevices.getUserMedia,
            microphone: !!navigator.mediaDevices && !!navigator.mediaDevices.getUserMedia,
            fullscreen: !!document.documentElement.requestFullscreen
        };
    }

    /**
     * Send message to parent window
     */
    notifyParent(type, data = {}) {
        if (this.isIframe && window.parent) {
            const message = {
                type: type,
                ...data,
                source: 'sandbox-bridge'
            };
            
            window.parent.postMessage(message, this.parentOrigin);
        }
    }

    /**
     * Send message to iframe
     */
    notifyIframe(type, data = {}) {
        const iframe = document.getElementById('app-iframe');
        if (iframe && iframe.contentWindow) {
            const message = {
                type: type,
                ...data,
                source: 'sandbox-bridge'
            };
            
            iframe.contentWindow.postMessage(message, this.parentOrigin);
        }
    }

    /**
     * Handle incoming messages
     */
    handleMessage(data) {
        if (!data.type || !data.source) return;

        const handler = this.messageHandlers.get(data.type);
        if (handler) {
            handler(data);
        }
    }

    /**
     * Register message handler
     */
    onMessage(type, handler) {
        this.messageHandlers.set(type, handler);
    }

    /**
     * Unregister message handler
     */
    offMessage(type) {
        this.messageHandlers.delete(type);
    }

    /**
     * Get singleton instance
     */
    static getInstance() {
        if (!window.sandboxBridgeInstance) {
            window.sandboxBridgeInstance = new SandboxBridge();
        }
        return window.sandboxBridgeInstance;
    }
}

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        SandboxBridge.getInstance();
    });
} else {
    SandboxBridge.getInstance();
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SandboxBridge;
}
