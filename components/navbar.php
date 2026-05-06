<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_page = basename($_SERVER['SCRIPT_NAME']);
?>
<nav class="navbar navbar-expand-lg app-subnav px-3 px-lg-4 navbar-light navbar-fixed">
    <div class="container-fluid">
        <h6 class="m-0 fw-bold app-subnav-heading">System Management</h6>

        <div class="d-flex align-items-center ms-3 app-subnav-breadcrumb">
            <small class="text-muted fw-semibold">
                <?php echo match($current_page) {
                    'dashboard-enhanced.php', 'dashboard.php' => 'Dashboard',
                    'notifications.php', 'alerts.php' => 'Notifications & Alerts',
                    'reports.php' => 'Reports',
                    'analytics-reports.php', 'analytics.php' => 'Analytics',
                    'projects-advanced.php', 'project-details.php' => 'Projects',
                    'geomap.php' => 'Geo Map',
                    'user-guide.php' => 'Help & Guide',
                    'admin-dashboard.php' => 'Manage Users',
                    'admin-settings.php' => 'Admin Settings',
                    'admin-audit.php' => 'Logs',
                    default => 'Main Menu'
                }; ?>
            </small>
        </div>
    </div>
</nav>