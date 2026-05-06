<?php
if (session_status() === PHP_SESSION_NONE) {
    if (defined('SESSION_NAME')) {
        session_name(SESSION_NAME);
    }
    session_start();
}
$isAuthenticated = isset($_SESSION['user_id']);
$userName = htmlspecialchars($_SESSION['full_name'] ?? 'Guest User');
$userRole = htmlspecialchars($_SESSION['role'] ?? 'Guest');
$profilePicture = isset($_SESSION['profile_picture']) ? trim((string) $_SESSION['profile_picture']) : '';
$hasProfilePicture = $profilePicture !== '';
?>
<div class="topbar-fixed app-topbar px-3 px-lg-4 d-flex justify-content-between align-items-center">
    <a class="navbar-brand app-topbar-brand d-flex align-items-center text-decoration-none" href="dashboard.php">
        <img src="logos/abed_logo.png" alt="ABED Logo" class="app-topbar-logo">
        <div class="app-topbar-titles">
            <div class="app-topbar-title fw-bold">ABED Integrated Data Management Hub</div>
            <small class="app-topbar-subtitle d-block">Agricultural and Biosystems Engineering Division — LGU Malaybalay City</small>
        </div>
    </a>
    
    <div class="d-flex align-items-center">
        <?php if ($isAuthenticated): ?>
            <div class="dropdown d-flex align-items-center">
                <div class="dropdown me-3">
                    <button
                        class="btn btn-sm btn-outline-secondary position-relative app-topbar-notif-btn"
                        type="button"
                        id="topbarNotificationDropdown"
                        data-bs-toggle="dropdown"
                        data-bs-auto-close="outside"
                        aria-expanded="false"
                        aria-label="Notifications"
                    >
                        <i class="fas fa-bell"></i>
                        <span
                            class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger app-topbar-notif-badge"
                            data-notification-badge
                            style="display:none;"
                        >0</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-0 shadow app-topbar-notif-menu" aria-labelledby="topbarNotificationDropdown">
                        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                            <strong class="small mb-0">Notifications</strong>
                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" id="topbarMarkAllReadBtn">Mark all read</button>
                        </div>
                        <div class="app-topbar-notif-list" id="topbarNotificationsList">
                            <div class="px-3 py-3 text-muted small">Loading...</div>
                        </div>
                    </div>
                </div>
                <div class="text-end me-3 d-none d-sm-block">
                    <div class="fw-bold small text-dark topbar-user-name"><?php echo $userName; ?></div>
                    <div class="text-muted small topbar-user-role"><?php echo $userRole; ?></div>
                </div>

                <a href="#" class="d-flex align-items-center text-decoration-none" id="userTopbarDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="rounded-circle fw-bold text-white d-flex align-items-center justify-content-center app-user-avatar overflow-hidden<?php echo $hasProfilePicture ? '' : ' app-user-avatar--placeholder'; ?>">
                        <?php if ($hasProfilePicture): ?>
                            <img src="<?php echo htmlspecialchars($profilePicture); ?>" alt="" class="w-100 h-100">
                        <?php else: ?>
                            <?php echo strtoupper(substr($userName, 0, 2)); ?>
                        <?php endif; ?>
                    </div>
                </a>

                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-2" aria-labelledby="userTopbarDropdown">
                    <li class="px-3 py-2 d-sm-none">
                        <div class="fw-bold small text-dark"><?php echo $userName; ?></div>
                        <div class="text-muted small"><?php echo $userRole; ?></div>
                        <hr class="dropdown-divider">
                    </li>
                    <li><a class="dropdown-item" href="my-account.php"><i class="fas fa-user me-2 text-primary"></i>My Account</a></li>
                </ul>
            </div>
        <?php else: ?>
            <a class="btn btn-outline-primary btn-sm" href="login.php">Login</a>
        <?php endif; ?>
    </div>
</div>
<?php if ($isAuthenticated): ?>
<script>
(() => {
    const baseUrl = 'api/notifications.php';
    const listEl = document.getElementById('topbarNotificationsList');
    const dropdownEl = document.getElementById('topbarNotificationDropdown');
    const markAllBtn = document.getElementById('topbarMarkAllReadBtn');

    async function fetchJson(url, options = {}) {
        const response = await fetch(url, options);
        if (!response.ok) {
            throw new Error('Request failed');
        }
        return response.json();
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    async function loadUnreadCount() {
        try {
            const data = await fetchJson(`${baseUrl}?action=count`);
            const count = Number(data.unread_count || 0);
            document.querySelectorAll('[data-notification-badge]').forEach((badge) => {
                if (count > 0) {
                    badge.textContent = count > 99 ? '99+' : String(count);
                    badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                }
            });
        } catch (_) {
            // noop
        }
    }

    function notificationBadgeClass(type) {
        if (type === 'project_approved') return 'bg-success';
        if (type === 'project_rejected') return 'bg-danger';
        if (String(type).includes('pending')) return 'bg-warning text-dark';
        return 'bg-primary';
    }

    function notificationTargetUrl(notification) {
        const projectId = Number(notification.project_id || 0);
        const projectType = String(notification.project_type || 'fspf').toLowerCase();
        const alertType = String(notification.alert_type || '');
        if (projectId > 0) {
            if (alertType === 'pending_project') {
                return `admin-dashboard.php`;
            }
            return `project-detail-enhanced.php?type=${encodeURIComponent(projectType)}&id=${projectId}`;
        }
        if (alertType.includes('pending_user')) {
            return 'admin-dashboard.php';
        }
        return 'notifications.php';
    }

    async function loadNotifications() {
        if (!listEl) return;
        try {
            const data = await fetchJson(`${baseUrl}?action=get&limit=12`);
            const rows = Array.isArray(data.data) ? data.data : [];
            if (rows.length === 0) {
                listEl.innerHTML = '<div class="px-3 py-3 text-muted small">No notifications yet.</div>';
                return;
            }
            listEl.innerHTML = rows.map((n) => {
                const isUnread = Number(n.is_read || 0) === 0;
                const created = n.created_at ? new Date(n.created_at).toLocaleString() : '';
                const targetUrl = notificationTargetUrl(n);
                return `
                    <button type="button" class="dropdown-item app-topbar-notif-item ${isUnread ? 'app-topbar-notif-item--unread' : ''}" data-id="${Number(n.id || 0)}" data-target-url="${escapeHtml(targetUrl)}">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <span class="badge ${notificationBadgeClass(n.alert_type)}">${escapeHtml(n.title || 'Notification')}</span>
                            <small class="text-muted ms-2">${escapeHtml(created)}</small>
                        </div>
                        <div class="small text-wrap">${escapeHtml(n.message || '')}</div>
                    </button>
                `;
            }).join('');
        } catch (_) {
            listEl.innerHTML = '<div class="px-3 py-3 text-danger small">Failed to load notifications.</div>';
        }
    }

    async function markAllRead() {
        try {
            const body = new URLSearchParams({ action: 'mark_all_read' });
            await fetchJson(baseUrl, { method: 'POST', body });
            await Promise.all([loadUnreadCount(), loadNotifications()]);
        } catch (_) {
            // noop
        }
    }

    async function markSingleRead(notificationId) {
        if (notificationId <= 0) return;
        try {
            const body = new URLSearchParams({ action: 'mark_read', notification_id: String(notificationId) });
            await fetchJson(baseUrl, { method: 'POST', body });
            await loadUnreadCount();
        } catch (_) {
            // noop
        }
    }

    if (markAllBtn) {
        markAllBtn.addEventListener('click', (event) => {
            event.preventDefault();
            markAllRead();
        });
    }

    if (listEl) {
        listEl.addEventListener('click', async (event) => {
            const item = event.target.closest('[data-id]');
            if (!item) return;
            const notificationId = Number(item.getAttribute('data-id') || 0);
            const targetUrl = item.getAttribute('data-target-url') || 'notifications.php';
            await markSingleRead(notificationId);
            item.classList.remove('app-topbar-notif-item--unread');
            window.location.href = targetUrl;
        });
    }

    if (dropdownEl) {
        dropdownEl.addEventListener('show.bs.dropdown', () => {
            loadNotifications();
            loadUnreadCount();
        });
    }

    loadUnreadCount();
    setInterval(loadUnreadCount, 30000);
})();
</script>
<?php endif; ?>