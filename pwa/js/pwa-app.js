/**
 * ARMIS PWA Application Core
 * Handles PWA functionality, offline capabilities, and modern web features
 */

class ARMISPWAApp {
    constructor() {
        this.isOnline = navigator.onLine;
        this.installPrompt = null;
        this.notificationPermission = 'default';
        
        this.init();
    }
    
    async init() {
        console.log('[PWA] Initializing ARMIS PWA Application');
        
        // Register service worker
        await this.registerServiceWorker();
        
        // Setup offline/online handlers
        this.setupNetworkHandlers();
        
        // Setup install prompt
        this.setupInstallPrompt();
        
        // Setup push notifications
        await this.setupPushNotifications();
        
        // Initialize offline capabilities
        this.initOfflineCapabilities();
        
        // Setup background sync
        this.setupBackgroundSync();
        
        // Update UI for PWA features
        this.updatePWAUI();
        
        console.log('[PWA] ARMIS PWA Application initialized');
    }
    
    async registerServiceWorker() {
        if ('serviceWorker' in navigator) {
            try {
                const registration = await navigator.serviceWorker.register('/sw.js');
                console.log('[PWA] Service Worker registered:', registration);
                
                // Handle updates
                registration.addEventListener('updatefound', () => {
                    console.log('[PWA] New Service Worker found');
                    this.handleServiceWorkerUpdate(registration);
                });
                
                return registration;
            } catch (error) {
                console.error('[PWA] Service Worker registration failed:', error);
            }
        }
    }
    
    handleServiceWorkerUpdate(registration) {
        const newWorker = registration.installing;
        
        newWorker.addEventListener('statechange', () => {
            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                // New content is available
                this.showUpdateNotification();
            }
        });
    }
    
    showUpdateNotification() {
        const notification = document.createElement('div');
        notification.className = 'pwa-update-notification';
        notification.innerHTML = `
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <strong>App Update Available!</strong> 
                A new version of ARMIS is ready to install.
                <button type="button" class="btn btn-sm btn-outline-primary ms-2" onclick="ARMISApp.updateApp()">
                    Update Now
                </button>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        document.body.appendChild(notification);
    }
    
    async updateApp() {
        if ('serviceWorker' in navigator) {
            const registration = await navigator.serviceWorker.getRegistration();
            if (registration && registration.waiting) {
                registration.waiting.postMessage({ type: 'SKIP_WAITING' });
                window.location.reload();
            }
        }
    }
    
    setupNetworkHandlers() {
        window.addEventListener('online', () => {
            console.log('[PWA] Network: Online');
            this.isOnline = true;
            this.onNetworkStatusChange(true);
        });
        
        window.addEventListener('offline', () => {
            console.log('[PWA] Network: Offline');
            this.isOnline = false;
            this.onNetworkStatusChange(false);
        });
        
        // Initial status
        this.onNetworkStatusChange(this.isOnline);
    }
    
    onNetworkStatusChange(isOnline) {
        // Update UI based on network status
        const statusIndicator = document.getElementById('network-status');
        if (statusIndicator) {
            statusIndicator.className = isOnline ? 'online' : 'offline';
            statusIndicator.textContent = isOnline ? 'Online' : 'Offline';
        }
        
        // Show/hide offline banner
        const offlineBanner = document.getElementById('offline-banner');
        if (offlineBanner) {
            offlineBanner.style.display = isOnline ? 'none' : 'block';
        }
        
        // Enable/disable certain features
        const onlineOnlyElements = document.querySelectorAll('.online-only');
        onlineOnlyElements.forEach(element => {
            element.disabled = !isOnline;
        });
        
        if (isOnline) {
            // Sync pending data when back online
            this.syncPendingData();
        }
    }
    
    setupInstallPrompt() {
        window.addEventListener('beforeinstallprompt', (e) => {
            console.log('[PWA] Install prompt available');
            e.preventDefault();
            this.installPrompt = e;
            this.showInstallButton();
        });
        
        window.addEventListener('appinstalled', () => {
            console.log('[PWA] App installed');
            this.hideInstallButton();
            this.trackEvent('pwa_installed');
        });
    }
    
    showInstallButton() {
        const installBtn = document.getElementById('pwa-install-btn');
        if (installBtn) {
            installBtn.style.display = 'block';
            installBtn.addEventListener('click', () => this.promptInstall());
        }
    }
    
    hideInstallButton() {
        const installBtn = document.getElementById('pwa-install-btn');
        if (installBtn) {
            installBtn.style.display = 'none';
        }
    }
    
    async promptInstall() {
        if (this.installPrompt) {
            this.installPrompt.prompt();
            const result = await this.installPrompt.userChoice;
            console.log('[PWA] Install prompt result:', result);
            
            if (result.outcome === 'accepted') {
                this.trackEvent('pwa_install_accepted');
            } else {
                this.trackEvent('pwa_install_dismissed');
            }
            
            this.installPrompt = null;
        }
    }
    
    async setupPushNotifications() {
        if ('Notification' in window) {
            this.notificationPermission = Notification.permission;
            
            if (this.notificationPermission === 'default') {
                this.showNotificationPrompt();
            } else if (this.notificationPermission === 'granted') {
                await this.subscribeToPush();
            }
        }
    }
    
    showNotificationPrompt() {
        const prompt = document.createElement('div');
        prompt.className = 'pwa-notification-prompt';
        prompt.innerHTML = `
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <strong>Stay Updated!</strong> 
                Enable notifications to receive important ARMIS alerts and updates.
                <button type="button" class="btn btn-sm btn-outline-primary ms-2" onclick="ARMISApp.requestNotificationPermission()">
                    Enable Notifications
                </button>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        document.body.appendChild(prompt);
    }
    
    async requestNotificationPermission() {
        if ('Notification' in window) {
            const permission = await Notification.requestPermission();
            this.notificationPermission = permission;
            
            if (permission === 'granted') {
                console.log('[PWA] Notification permission granted');
                await this.subscribeToPush();
                this.trackEvent('notifications_enabled');
            } else {
                console.log('[PWA] Notification permission denied');
                this.trackEvent('notifications_denied');
            }
        }
    }
    
    async subscribeToPush() {
        try {
            const registration = await navigator.serviceWorker.getRegistration();
            if (registration) {
                const subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: this.urlBase64ToUint8Array('your-vapid-public-key-here')
                });
                
                console.log('[PWA] Push subscription:', subscription);
                
                // Send subscription to server
                await this.sendSubscriptionToServer(subscription);
            }
        } catch (error) {
            console.error('[PWA] Push subscription failed:', error);
        }
    }
    
    async sendSubscriptionToServer(subscription) {
        try {
            await fetch('/api/v1/push/subscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(subscription)
            });
        } catch (error) {
            console.error('[PWA] Failed to send subscription to server:', error);
        }
    }
    
    initOfflineCapabilities() {
        // Setup IndexedDB for offline data storage
        this.setupIndexedDB();
        
        // Cache critical data
        this.cacheEssentialData();
        
        // Setup offline forms
        this.setupOfflineForms();
    }
    
    async setupIndexedDB() {
        try {
            this.db = await this.openIndexedDB();
            console.log('[PWA] IndexedDB initialized');
        } catch (error) {
            console.error('[PWA] IndexedDB setup failed:', error);
        }
    }
    
    openIndexedDB() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open('ARMIS_PWA', 1);
            
            request.onerror = () => reject(request.error);
            request.onsuccess = () => resolve(request.result);
            
            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                
                // Create object stores
                if (!db.objectStoreNames.contains('pendingData')) {
                    const store = db.createObjectStore('pendingData', { keyPath: 'id', autoIncrement: true });
                    store.createIndex('timestamp', 'timestamp', { unique: false });
                }
                
                if (!db.objectStoreNames.contains('cachedData')) {
                    db.createObjectStore('cachedData', { keyPath: 'key' });
                }
            };
        });
    }
    
    async cacheEssentialData() {
        if (!this.isOnline) return;
        
        try {
            // Cache user profile
            const userProfile = await fetch('/api/v1/user/profile');
            if (userProfile.ok) {
                const data = await userProfile.json();
                await this.storeInCache('userProfile', data);
            }
            
            // Cache navigation data
            const navigation = await fetch('/api/v1/navigation');
            if (navigation.ok) {
                const data = await navigation.json();
                await this.storeInCache('navigation', data);
            }
            
        } catch (error) {
            console.warn('[PWA] Failed to cache essential data:', error);
        }
    }
    
    setupOfflineForms() {
        const forms = document.querySelectorAll('form[data-offline="true"]');
        
        forms.forEach(form => {
            form.addEventListener('submit', (e) => {
                if (!this.isOnline) {
                    e.preventDefault();
                    this.handleOfflineFormSubmission(form);
                }
            });
        });
    }
    
    async handleOfflineFormSubmission(form) {
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        
        // Store for later sync
        await this.storePendingData({
            type: 'form_submission',
            url: form.action,
            method: form.method || 'POST',
            data: data,
            timestamp: Date.now()
        });
        
        // Show confirmation
        this.showOfflineSubmissionConfirmation();
    }
    
    showOfflineSubmissionConfirmation() {
        const toast = document.createElement('div');
        toast.className = 'toast-container position-fixed top-0 end-0 p-3';
        toast.innerHTML = `
            <div class="toast show" role="alert">
                <div class="toast-header">
                    <strong class="me-auto">ARMIS</strong>
                    <small>Just now</small>
                    <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body">
                    Form saved offline. Will sync when connection is restored.
                </div>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 5000);
    }
    
    setupBackgroundSync() {
        if ('serviceWorker' in navigator && 'sync' in window.ServiceWorkerRegistration.prototype) {
            navigator.serviceWorker.ready.then(registration => {
                // Register for background sync
                registration.sync.register('sync-data');
                registration.sync.register('sync-metrics');
            });
        }
    }
    
    async syncPendingData() {
        if (!this.db) return;
        
        try {
            const pendingData = await this.getAllPendingData();
            
            for (const item of pendingData) {
                try {
                    const response = await fetch(item.url, {
                        method: item.method,
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(item.data)
                    });
                    
                    if (response.ok) {
                        await this.removePendingData(item.id);
                        console.log('[PWA] Synced pending data item:', item.id);
                    }
                } catch (e) {
                    console.warn('[PWA] Failed to sync item:', item.id, e);
                }
            }
        } catch (error) {
            console.error('[PWA] Sync failed:', error);
        }
    }
    
    updatePWAUI() {
        // Add PWA-specific UI elements
        this.addNetworkStatusIndicator();
        this.addInstallButton();
        this.addOfflineBanner();
    }
    
    addNetworkStatusIndicator() {
        const indicator = document.createElement('div');
        indicator.id = 'network-status';
        indicator.className = this.isOnline ? 'online' : 'offline';
        indicator.textContent = this.isOnline ? 'Online' : 'Offline';
        indicator.style.cssText = `
            position: fixed;
            top: 10px;
            right: 10px;
            z-index: 9999;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            color: white;
            background: ${this.isOnline ? '#28a745' : '#dc3545'};
            transition: all 0.3s ease;
        `;
        
        document.body.appendChild(indicator);
    }
    
    addInstallButton() {
        const button = document.createElement('button');
        button.id = 'pwa-install-btn';
        button.className = 'btn btn-primary btn-sm';
        button.innerHTML = '<i class="fas fa-download"></i> Install App';
        button.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9998;
            display: none;
        `;
        
        document.body.appendChild(button);
    }
    
    addOfflineBanner() {
        const banner = document.createElement('div');
        banner.id = 'offline-banner';
        banner.className = 'alert alert-warning alert-dismissible fade show';
        banner.innerHTML = `
            <strong>Offline Mode</strong> 
            Limited functionality available. Some features will sync when connection is restored.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        banner.style.cssText = `
            position: fixed;
            top: 60px;
            left: 0;
            right: 0;
            z-index: 9997;
            margin: 0 20px;
            display: ${this.isOnline ? 'none' : 'block'};
        `;
        
        document.body.appendChild(banner);
    }
    
    // Utility methods for IndexedDB operations
    async storeInCache(key, data) {
        if (!this.db) return;
        
        const transaction = this.db.transaction(['cachedData'], 'readwrite');
        const store = transaction.objectStore('cachedData');
        
        await store.put({
            key: key,
            data: data,
            timestamp: Date.now()
        });
    }
    
    async getFromCache(key) {
        if (!this.db) return null;
        
        const transaction = this.db.transaction(['cachedData'], 'readonly');
        const store = transaction.objectStore('cachedData');
        
        return await store.get(key);
    }
    
    async storePendingData(data) {
        if (!this.db) return;
        
        const transaction = this.db.transaction(['pendingData'], 'readwrite');
        const store = transaction.objectStore('pendingData');
        
        await store.add(data);
    }
    
    async getAllPendingData() {
        if (!this.db) return [];
        
        const transaction = this.db.transaction(['pendingData'], 'readonly');
        const store = transaction.objectStore('pendingData');
        
        return await store.getAll();
    }
    
    async removePendingData(id) {
        if (!this.db) return;
        
        const transaction = this.db.transaction(['pendingData'], 'readwrite');
        const store = transaction.objectStore('pendingData');
        
        await store.delete(id);
    }
    
    // Utility functions
    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/-/g, '+')
            .replace(/_/g, '/');
        
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        
        return outputArray;
    }
    
    trackEvent(eventName, eventData = {}) {
        // Track PWA events for analytics
        console.log('[PWA] Event:', eventName, eventData);
        
        // Send to analytics service if online
        if (this.isOnline) {
            fetch('/api/v1/analytics/track', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    event: eventName,
                    data: eventData,
                    timestamp: Date.now()
                })
            }).catch(console.warn);
        }
    }
}

// Initialize ARMIS PWA when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.ARMISApp = new ARMISPWAApp();
});

// Export for global access
window.ARMISPWAApp = ARMISPWAApp;