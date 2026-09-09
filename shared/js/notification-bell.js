/**
 * Notification bell — polls shared/notifications_api.php for unread
 * count on an interval, and loads the full list when opened. Only
 * runs on pages where the bell markup exists (i.e. pages that include
 * shared/header.php while logged in), so it's a no-op elsewhere.
 */
(function () {
    const POLL_INTERVAL_MS = 30000; // 30s — same order of magnitude as admin_branch's dashboard refresh
    const API = '/Armis2/shared/notifications_api.php';

    const badge = document.getElementById('notificationBadge');
    const list = document.getElementById('notificationList');
    const toggle = document.getElementById('notificationBellToggle');
    const markAllBtn = document.getElementById('markAllReadBtn');
    if (!badge || !list || !toggle) {
        return; // Bell markup not present on this page/logged-out state
    }

    function timeAgo(isoString) {
        const seconds = Math.floor((Date.now() - new Date(isoString.replace(' ', 'T') + 'Z')) / 1000);
        if (seconds < 60) return 'just now';
        const minutes = Math.floor(seconds / 60);
        if (minutes < 60) return `${minutes}m ago`;
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return `${hours}h ago`;
        const days = Math.floor(hours / 24);
        return `${days}d ago`;
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }

    function updateBadge(count) {
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.style.display = 'inline-block';
        } else {
            badge.style.display = 'none';
        }
    }

    async function pollUnreadCount() {
        try {
            const res = await fetch(`${API}?action=unread_count`);
            if (!res.ok) return;
            const data = await res.json();
            updateBadge(data.unread_count || 0);
        } catch (e) {
            // Silent — a failed poll shouldn't interrupt the user, just try again next interval.
        }
    }

    function renderList(notifications) {
        if (!notifications || notifications.length === 0) {
            list.innerHTML = '<div class="text-center text-muted py-3">No notifications.</div>';
            return;
        }
        list.innerHTML = notifications.map(n => {
            const unreadClass = n.status === 'unread' ? 'bg-light' : '';
            const content = `
                <div class="fw-semibold small">${escapeHtml(n.title)}</div>
                ${n.message ? `<div class="small text-muted">${escapeHtml(n.message)}</div>` : ''}
                <div class="small text-muted">${timeAgo(n.created_at)}</div>
            `;
            const inner = n.link
                ? `<a href="${escapeHtml(n.link)}" class="text-decoration-none text-dark">${content}</a>`
                : content;
            return `<div class="px-3 py-2 border-bottom notification-item ${unreadClass}" data-id="${n.id}" data-status="${n.status}">${inner}</div>`;
        }).join('');

        list.querySelectorAll('.notification-item[data-status="unread"]').forEach(el => {
            el.addEventListener('click', () => markRead(el.dataset.id), { once: true });
        });
    }

    async function loadList() {
        list.innerHTML = '<div class="text-center text-muted py-3">Loading...</div>';
        try {
            const res = await fetch(`${API}?action=list`);
            if (!res.ok) throw new Error('Request failed');
            const data = await res.json();
            renderList(data.notifications);
            updateBadge(data.unread_count || 0);
        } catch (e) {
            list.innerHTML = '<div class="text-center text-danger py-3">Failed to load notifications.</div>';
        }
    }

    async function markRead(id) {
        try {
            await fetch(API, { method: 'POST', body: new URLSearchParams({ action: 'mark_read', id }) });
            pollUnreadCount();
        } catch (e) { /* ignore */ }
    }

    if (markAllBtn) {
        markAllBtn.addEventListener('click', async (e) => {
            e.preventDefault();
            try {
                await fetch(API, { method: 'POST', body: new URLSearchParams({ action: 'mark_all_read' }) });
                loadList();
            } catch (err) { /* ignore */ }
        });
    }

    toggle.addEventListener('click', loadList);

    pollUnreadCount();
    setInterval(pollUnreadCount, POLL_INTERVAL_MS);
})();
