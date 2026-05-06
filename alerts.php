<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/functions/helpers.php';
require_once __DIR__ . '/components/layout.php';

requireLogin();

$page_title = 'Alerts - ABED IDM Hub';
renderAppLayout($page_title);
?>
<div class="container-fluid px-3 px-lg-4 py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h4 mb-1"><i class="fas fa-exclamation-triangle me-2 text-warning"></i>Active alerts</h1>
            <p class="text-muted small mb-0">System alerts tied to projects (variance, delays, milestones, etc.).</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnRefreshAlerts">
                <i class="fas fa-sync-alt me-1"></i>Refresh
            </button>
            <a href="dashboard.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Dashboard</a>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div id="alertsFullList" class="p-4">
                <div class="text-center text-muted py-5">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p class="mt-2 mb-0">Loading alerts…</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var listEl = document.getElementById('alertsFullList');

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function severityClass(sev) {
        var colors = { low: 'info', medium: 'warning', high: 'danger', critical: 'danger' };
        return colors[String(sev || '').toLowerCase()] || 'secondary';
    }

    async function loadAll() {
        try {
            var response = await fetch('api/notifications.php?action=get_alerts');
            var data = await response.json();
            if (!data.success || !Array.isArray(data.data)) {
                throw new Error(data.error || 'Failed to load');
            }
            if (data.data.length === 0) {
                listEl.innerHTML = '<div class="text-center text-muted py-5"><i class="fas fa-check-circle fa-3x mb-3 d-block text-success opacity-75"></i><p class="mb-0">No active alerts.</p></div>';
                return;
            }
            listEl.innerHTML = data.data.map(function (a) {
                var sev = severityClass(a.severity);
                var title = escapeHtml(String(a.alert_type || 'Alert').replace(/_/g, ' '));
                title = title.charAt(0).toUpperCase() + title.slice(1);
                var msg = escapeHtml(a.message || '');
                var when = escapeHtml(new Date(a.created_at).toLocaleString());
                var proj = '';
                if (a.project_id) {
                    var pt = encodeURIComponent(String(a.project_type || 'fspf').toLowerCase());
                    var code = escapeHtml(a.project_code || ('#' + a.project_id));
                    proj = '<a href="project-detail-enhanced.php?type=' + pt + '&amp;id=' + encodeURIComponent(a.project_id) + '" class="btn btn-sm btn-outline-primary mt-2">Open ' + code + '</a>';
                }
                return '<div class="d-flex align-items-start pb-4 mb-4 border-bottom">' +
                    '<div class="flex-shrink-0 me-3"><div class="bg-' + sev + ' rounded-circle p-2"><i class="fas fa-exclamation-triangle text-white"></i></div></div>' +
                    '<div class="flex-grow-1 min-w-0">' +
                    '<div class="d-flex flex-wrap justify-content-between gap-2 align-items-start">' +
                    '<h2 class="h6 mb-1">' + title + '</h2>' +
                    '<span class="badge bg-' + sev + '">' + escapeHtml(String(a.severity || '')) + '</span></div>' +
                    '<p class="mb-1 text-muted small">' + msg + '</p>' +
                    '<small class="text-muted d-block">' + when + '</small>' +
                    proj +
                    '</div></div>';
            }).join('');
        } catch (e) {
            console.error(e);
            listEl.innerHTML = '<div class="alert alert-danger m-4 mb-0">Could not load alerts.</div>';
        }
    }

    document.getElementById('btnRefreshAlerts').addEventListener('click', loadAll);
    document.addEventListener('DOMContentLoaded', loadAll);
})();
</script>
<?php renderAppLayoutFooter(); ?>
