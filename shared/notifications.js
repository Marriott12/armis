/**
 * ARMIS Notification System
 * Real-time notifications for dashboard events
 */

class ARMISNotifications {
    constructor() {
        this.notifications = [];
        this.container = null;
        this.init();
    }

    init() {
        this.createContainer();
        this.setupEventListeners();
        this.startPolling();
    }

    createContainer() {
        if (document.getElementById('armis-notifications')) return;
        
        this.container = document.createElement('div');
        this.container.id = 'armis-notifications';
        this.container.className = 'armis-notifications-container';
        document.body.appendChild(this.container);
    }

    setupEventListeners() {
        // Listen for custom notification events
        document.addEventListener('armis:notify', (event) => {
            this.show(event.detail);
        });

        // Listen for system events
        window.addEventListener('online', () => {
            this.show({
                type: 'success',
                title: 'Connection Restored',
                message: 'Your connection to ARMIS has been restored.',
                duration: 3000
            });
        });

        window.addEventListener('offline', () => {
            this.show({
                type: 'warning',
                title: 'Connection Lost',
                message: 'Working in offline mode. Some features may be limited.',
                duration: 0 // Persistent until online
            });
        });
    }

    startPolling() {
        // Poll for system notifications every 30 seconds
        setInterval(() => {
            this.checkForNotifications();
        }, 30000);
    }

    async checkForNotifications() {
        try {
            const response = await fetch('/Armis2/admin_branch/api.php?action=get_notifications');
            const notifications = await response.json();
            
            notifications.forEach(notification => {
                this.show(notification);
            });
        } catch (error) {
            console.warn('Failed to check for notifications:', error);
        }
    }

    show(options) {
        const notification = this.createNotification(options);
        this.container.appendChild(notification);
        
        // Trigger entrance animation
        setTimeout(() => {
            notification.classList.add('show');
        }, 100);

        // Auto-dismiss if duration is set
        if (options.duration && options.duration > 0) {
            setTimeout(() => {
                this.dismiss(notification);
            }, options.duration);
        }

        return notification;
    }

    createNotification(options) {
        const {
            type = 'info',
            title = 'Notification',
            message = '',
            duration = 5000,
            actions = [],
            persistent = false
        } = options;

        const notification = document.createElement('div');
        notification.className = `armis-notification armis-notification-${type}`;
        
        const iconMap = {
            success: 'fas fa-check-circle',
            error: 'fas fa-exclamation-triangle',
            warning: 'fas fa-exclamation-circle',
            info: 'fas fa-info-circle'
        };

        notification.innerHTML = `
            <div class="notification-icon">
                <i class="${iconMap[type]}"></i>
            </div>
            <div class="notification-content">
                <div class="notification-title">${title}</div>
                ${message ? `<div class="notification-message">${message}</div>` : ''}
                ${actions.length > 0 ? this.createActionButtons(actions) : ''}
            </div>
            ${!persistent ? `<button class="notification-close" onclick="armisNotifications.dismiss(this.parentNode)">
                <i class="fas fa-times"></i>
            </button>` : ''}
        `;

        return notification;
    }

    createActionButtons(actions) {
        const buttonsHtml = actions.map(action => 
            `<button class="btn btn-sm btn-${action.type || 'primary'}" onclick="${action.onclick || ''}">
                ${action.label}
            </button>`
        ).join(' ');
        
        return `<div class="notification-actions">${buttonsHtml}</div>`;
    }

    dismiss(notification) {
        if (!notification) return;
        
        notification.classList.remove('show');
        notification.classList.add('hide');
        
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }

    // Predefined notification types
    success(title, message, duration = 4000) {
        return this.show({ type: 'success', title, message, duration });
    }

    error(title, message, duration = 6000) {
        return this.show({ type: 'error', title, message, duration });
    }

    warning(title, message, duration = 5000) {
        return this.show({ type: 'warning', title, message, duration });
    }

    info(title, message, duration = 4000) {
        return this.show({ type: 'info', title, message, duration });
    }

    // System notifications
    systemUpdate(message) {
        return this.show({
            type: 'info',
            title: 'System Update',
            message: message,
            duration: 8000,
            actions: [
                {
                    label: 'Refresh',
                    type: 'primary',
                    onclick: 'location.reload()'
                }
            ]
        });
    }

    securityAlert(message) {
        return this.show({
            type: 'error',
            title: 'Security Alert',
            message: message,
            persistent: true,
            actions: [
                {
                    label: 'Review',
                    type: 'danger',
                    onclick: 'window.open("/security", "_blank")'
                }
            ]
        });
    }
}

// Initialize notifications system
const armisNotifications = new ARMISNotifications();

// Add CSS styles
const notificationStyles = `
<style>
.armis-notifications-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    max-width: 400px;
    pointer-events: none;
}

.armis-notification {
    background: white;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
    margin-bottom: 12px;
    padding: 16px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    transform: translateX(100%);
    opacity: 0;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    pointer-events: auto;
    border-left: 4px solid;
    max-width: 400px;
    word-wrap: break-word;
}

.armis-notification.show {
    transform: translateX(0);
    opacity: 1;
}

.armis-notification.hide {
    transform: translateX(100%);
    opacity: 0;
}

.armis-notification-success {
    border-left-color: #28a745;
    background: linear-gradient(135deg, #d4edda 0%, #f8f9fa 100%);
}

.armis-notification-error {
    border-left-color: #dc3545;
    background: linear-gradient(135deg, #f8d7da 0%, #f8f9fa 100%);
}

.armis-notification-warning {
    border-left-color: #ffc107;
    background: linear-gradient(135deg, #fff3cd 0%, #f8f9fa 100%);
}

.armis-notification-info {
    border-left-color: #17a2b8;
    background: linear-gradient(135deg, #d1ecf1 0%, #f8f9fa 100%);
}

.notification-icon {
    font-size: 1.25rem;
    margin-top: 2px;
}

.armis-notification-success .notification-icon { color: #28a745; }
.armis-notification-error .notification-icon { color: #dc3545; }
.armis-notification-warning .notification-icon { color: #ffc107; }
.armis-notification-info .notification-icon { color: #17a2b8; }

.notification-content {
    flex: 1;
    min-width: 0;
}

.notification-title {
    font-weight: 600;
    color: #2d5a27;
    margin-bottom: 4px;
    font-size: 0.95rem;
}

.notification-message {
    color: #666;
    font-size: 0.85rem;
    line-height: 1.4;
    margin-bottom: 8px;
}

.notification-actions {
    margin-top: 8px;
    display: flex;
    gap: 6px;
}

.notification-close {
    background: none;
    border: none;
    color: #999;
    cursor: pointer;
    padding: 4px;
    border-radius: 4px;
    transition: all 0.2s ease;
    margin-top: -2px;
}

.notification-close:hover {
    background: rgba(0, 0, 0, 0.1);
    color: #666;
}

@media (max-width: 768px) {
    .armis-notifications-container {
        top: 10px;
        right: 10px;
        left: 10px;
        max-width: none;
    }
    
    .armis-notification {
        margin-bottom: 8px;
        padding: 12px;
    }
}
</style>
`;

document.head.insertAdjacentHTML('beforeend', notificationStyles);

// Export for global use
window.armisNotifications = armisNotifications;
