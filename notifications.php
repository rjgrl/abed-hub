<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/functions/helpers.php';
require_once __DIR__ . '/components/layout.php';

requireLogin();

$page_title = 'Notifications - ABED IDM Hub';
renderAppLayout($page_title);
?>
<div class="container-fluid px-3 px-lg-4 py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h4 mb-1"><i class="fas fa-bell me-2 text-primary"></i>Notifications</h1>
            <p class="text-muted small mb-0">Stored notices and dashboard items that need your attention.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnRefreshNotifications">
                <i class="fas fa-sync-alt me-1"></i>Refresh
            </button>
            <button type="button" class="btn btn-outline-primary btn-sm" id="btnMarkAllRead">
                <i class="fas fa-check-double me-1"></i>Mark all read
            </button>
            <a href="dashboard.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Dashboard</a>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div id="notificationsFullList" class="p-4">
                <div class="text-center text-muted py-5">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p class="mt-2 mb-0">Loading notifications…</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var listEl = document.getElementById('notificationsFullList');

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function notificationActionLink(n) {
        if (n.synthetic && n.synthetic_kind === 'pending_user') {
            return '<a href="admin-dashboard.php" class="btn btn-sm btn-outline-primary mt-2">Review accounts</a>';
        }
        if (n.synthetic && (n.synthetic_kind === 'pending_project' || n.synthetic_kind === 'my_pending_project')) {
            var pid = n.project_id || n.ref_id;
            var ptype = encodeURIComponent(String(n.project_type || 'fspf').toLowerCase());
            if (pid) {
                return '<a href="project-detail-enhanced.php?type=' + ptype + '&amp;id=' + encodeURIComponent(pid) + '" class="btn btn-sm btn-outline-primary mt-2">Open project</a>';
            }
        }
        if (!n.synthetic && n.project_id) {
            var pt = encodeURIComponent(String(n.project_type || 'fspf').toLowerCase());
            return '<a href="project-detail-enhanced.php?type=' + pt + '&amp;id=' + encodeURIComponent(n.project_id) + '" class="btn btn-sm btn-outline-secondary mt-2">Related project</a>';
        }
        return '';
    }

    async function loadAll() {
        try {
            var response = await fetch('api/notifications.php?action=get&limit=100');
            var data = await response.json();
            if (!data.success || !Array.isArray(data.data)) {
                throw new Error(data.error || 'Failed to load');
            }
            if (data.data.length === 0) {
                listEl.innerHTML = '<div class="text-center text-muted py-5"><i class="fas fa-bell-slash fa-3x mb-3 d-block opacity-50"></i><p class="mb-0">No notifications yet.</p></div>';
                return;
            }
            listEl.innerHTML = data.data.map(function (n) {
                var unread = !parseInt(String(n.is_read), 10);
                var extra = notificationActionLink(n);
                var markBtn = (!n.synthetic && unread)
                    ? '<button type="button" class="btn btn-sm btn-link text-decoration-none p-0 ms-2 align-baseline" data-mark-read="' + escapeHtml(String(n.id)) + '">Mark read</button>'
                    : '';
                return '<div class="d-flex align-items-start pb-4 mb-4 border-bottom notification-row" data-id="' + escapeHtml(String(n.id)) + '">' +
                    '<div class="flex-shrink-0 me-3"><div class="bg-light rounded-circle p-2"><i class="fas fa-bell text-muted"></i></div></div>' +
                    '<div class="flex-grow-1 min-w-0">' +
                    '<div class="d-flex flex-wrap justify-content-between gap-2 align-items-start">' +
                    '<h2 class="h6 mb-1">' + escapeHtml(n.title || 'Notification') + '</h2>' +
                    '<small class="text-muted text-nowrap">' + escapeHtml(new Date(n.created_at).toLocaleString()) + '</small></div>' +
                    '<p class="mb-2 text-muted small">' + escapeHtml(n.message || '') + '</p>' +
                    '<div class="d-flex flex-wrap align-items-center gap-2">' +
                    (unread ? '<span class="badge bg-primary">New</span>' : '') + markBtn + '</div>' +
                    extra +
                    '</div></div>';
            }).join('');
        } catch (e) {
            console.error(e);
            listEl.innerHTML = '<div class="alert alert-danger m-4 mb-0">Could not load notifications.</div>';
        }
    }

    listEl.addEventListener('click', async function (e) {
        var btn = e.target.closest('[data-mark-read]');
        if (!btn) return;
        var id = btn.getAttribute('data-mark-read');
        try {
            var fd = new FormData();
            fd.append('notification_id', id);
            var res = await fetch('api/notifications.php?action=mark_read', { method: 'POST', body: fd });
            var data = await res.json();
            if (data.success) {
                var row = btn.closest('.notification-row');
                if (row) {
                    var badge = row.querySelector('.badge');
                    if (badge) badge.remove();
                    btn.remove();
                }
                if (typeof notificationManager !== 'undefined' && notificationManager.pollNotifications) {
                    notificationManager.pollNotifications();
                }
            }
        } catch (err) { console.error(err); }
    });

    document.getElementById('btnRefreshNotifications').addEventListener('click', loadAll);
    document.getElementById('btnMarkAllRead').addEventListener('click', async function () {
        try {
            var res = await fetch('api/notifications.php?action=mark_all_read', { method: 'POST' });
            var data = await res.json();
            if (data.success) {
                await loadAll();
                if (typeof notificationManager !== 'undefined' && notificationManager.pollNotifications) {
                    notificationManager.pollNotifications();
                }
            }
        } catch (err) { console.error(err); }
    });

    document.addEventListener('DOMContentLoaded', loadAll);
})();
</script>
<?php renderAppLayoutFooter(); ?>
