<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_page = basename($_SERVER['SCRIPT_NAME']);
?>
<nav class="navbar navbar-expand-lg border-bottom px-3 navbar-dark navbar-fixed bg-white">
    <div class="container-fluid">
        <h6 class="m-0 fw-bold text-dark">System Management</h6>

        <div class=" align-items-center text-black ms-3 border-start ps-3">
            <small class="text-muted fw-bold">
                <?php echo match($current_page) {
                    'dashboard-enhanced.php', 'dashboard.php' => 'Dashboard',
                    'reports.php' => 'Reports',
                    'analytics-reports.php', 'analytics.php' => 'Analytics',
                    'projects-advanced.php', 'project-details.php' => 'Projects',
                    'geomap.php' => 'GeoMap',
                    'my-account.php' => 'My Account',
                    'afme-machinery-details.php' => 'AFME Machinery',
                    default => 'Main Menu'
                }; ?>
            </small>
        </div>
    </div>
</nav>