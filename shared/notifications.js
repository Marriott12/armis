/**
 * ARMIS toast/alert popups.
 *
 * Previously this file was a deliberately-disabled stub — every method
 * (success/error/warning/info) was a no-op, so every call site across
 * the app (e.g. admin_branch/js/dashboard.js) silently did nothing.
 * This is a real, lightweight implementation: no external dependency,
 * just a fixed-position stack of auto-dismissing toasts.
 */

class ARMISNotifications {
    constructor() {
        this.enabled = true;
        this.container = null;
    }

    _ensureContainer() {
        if (this.container && document.body.contains(this.container)) {
            return this.container;
        }
        const el = document.createElement('div');
        el.id = 'armis-toast-container';
        el.style.cssText = 'position:fixed;top:1rem;right:1rem;z-index:2000;display:flex;flex-direction:column;gap:0.5rem;max-width:360px;';
        document.body.appendChild(el);
        this.container = el;
        return el;
    }

    show(title, message, type = 'info', durationMs = 5000) {
        const colors = {
            success: { bg: '#d1e7dd', border: '#0f5132', icon: 'fa-check-circle' },
            error: { bg: '#f8d7da', border: '#842029', icon: 'fa-exclamation-circle' },
            warning: { bg: '#fff3cd', border: '#664d03', icon: 'fa-exclamation-triangle' },
            info: { bg: '#cfe2ff', border: '#084298', icon: 'fa-info-circle' },
        };
        const style = colors[type] || colors.info;

        const container = this._ensureContainer();
        const toast = document.createElement('div');
        toast.setAttribute('role', 'status');
        toast.style.cssText = `background:${style.bg};color:${style.border};border:1px solid ${style.border}33;border-left:4px solid ${style.border};border-radius:6px;padding:0.75rem 1rem;box-shadow:0 2px 8px rgba(0,0,0,0.15);font-size:0.9rem;position:relative;animation:armisToastIn 0.2s ease-out;`;
        toast.innerHTML = `
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:0.5rem;">
                <div>
                    <strong><i class="fa ${style.icon}" aria-hidden="true"></i> ${this._escape(title)}</strong>
                    ${message ? `<div style="margin-top:0.25rem;">${this._escape(message)}</div>` : ''}
                </div>
                <button type="button" aria-label="Dismiss" style="background:none;border:none;font-size:1rem;line-height:1;cursor:pointer;color:${style.border};opacity:0.6;">&times;</button>
            </div>
        `;
        const dismiss = () => {
            toast.style.animation = 'armisToastOut 0.2s ease-in forwards';
            setTimeout(() => toast.remove(), 200);
        };
        toast.querySelector('button').addEventListener('click', dismiss);
        container.appendChild(toast);

        if (durationMs > 0) {
            setTimeout(dismiss, durationMs);
        }
        return toast;
    }

    success(title, message) { return this.show(title, message, 'success'); }
    error(title, message) { return this.show(title, message, 'error', 8000); }
    warning(title, message) { return this.show(title, message, 'warning', 7000); }
    info(title, message) { return this.show(title, message, 'info'); }

    dismiss(toastEl) {
        if (toastEl && toastEl.remove) toastEl.remove();
    }

    _escape(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }

    init() { /* no setup needed; container is created lazily on first show() */ }
}

if (!document.getElementById('armis-toast-keyframes')) {
    const style = document.createElement('style');
    style.id = 'armis-toast-keyframes';
    style.textContent = `
        @keyframes armisToastIn { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: translateX(0); } }
        @keyframes armisToastOut { from { opacity: 1; } to { opacity: 0; } }
    `;
    document.head.appendChild(style);
}

const armisNotifications = new ARMISNotifications();
window.armisNotifications = armisNotifications;
