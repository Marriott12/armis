/**
 * ARMIS Notification System - COMPLETELY DISABLED
 * All notification functionality has been removed
 */

// Notifications system completely disabled
console.log('ARMIS Notifications: COMPLETELY DISABLED');

// Stub class to prevent errors in existing code
class ARMISNotifications {
    constructor() {
        this.enabled = false;
    }
    
    // All methods return null or do nothing
    init() { return; }
    show() { return null; }
    success() { return null; }
    error() { return null; }
    warning() { return null; }
    info() { return null; }
    dismiss() { return; }
}

// Initialize stub notifications system to prevent errors
const armisNotifications = new ARMISNotifications();

// Export for global use (stub only)
window.armisNotifications = armisNotifications;
