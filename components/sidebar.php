<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_page = basename($_SERVER['SCRIPT_NAME']);
?>
<div class="sidebar-fixed bg-dark text-white p-3 v-100 d-flex flex-column">
  <h4 class="fw-bold mb-4">
    <i class="fas fa-building me-2"></i>ABED IDM Hub
  </h4>

  <ul class="nav nav-pills flex-column mb-auto">
    <li class="nav-item mb-2">
      <a href="dashboard-enhanced.php" class="nav-link text-white <?php echo (in_array($current_page, ['dashboard-enhanced.php', 'dashboard.php'])) ? 'active' : ''; ?>">
        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
        <span class="badge bg-danger ms-auto d-none" id="notificationBadge" data-notification-badge>0</span>
      </a>
    </li>

    <li class="nav-item mb-2">
      <a href="projects-advanced.php" class="nav-link text-white <?php echo (in_array($current_page, ['projects-advanced.php', 'projects.php', 'project-detail-enhanced.php'])) ? 'active' : ''; ?>">
        <i class="fas fa-folder-open me-2"></i>Projects
      </a>
    </li>

    <li class="nav-item mb-2">
      <a href="analytics-reports.php" class="nav-link text-white <?php echo (in_array($current_page, ['analytics-reports.php', 'analytics.php', 'reports.php'])) ? 'active' : ''; ?>">
        <i class="fas fa-chart-bar me-2"></i>Analytics & Reports
      </a>
    </li>

    <!-- Module Dashboards -->
    <hr class="my-3">
    <li class="nav-item mb-2">
      <span class="nav-link text-muted small fw-bold px-0">
        <i class="fas fa-th-large me-2"></i>MODULE DASHBOARDS
      </span>
    </li>

    <li class="nav-item mb-2">
      <a href="fspf-dashboard.php" class="nav-link text-white <?php echo ($current_page === 'fspf-dashboard.php') ? 'active' : ''; ?>">
        <i class="fas fa-project-diagram me-2"></i>FSPF Dashboard
      </a>
    </li>

    <li class="nav-item mb-2">
      <a href="idp-dashboard.php" class="nav-link text-white <?php echo ($current_page === 'idp-dashboard.php') ? 'active' : ''; ?>">
        <i class="fas fa-home me-2"></i>IDP Dashboard
      </a>
    </li>

    <li class="nav-item mb-2">
      <a href="afme-dashboard.php" class="nav-link text-white <?php echo ($current_page === 'afme-dashboard.php') ? 'active' : ''; ?>">
        <i class="fas fa-cogs me-2"></i>AFME Dashboard
      </a>
    </li>

    <li class="nav-item mb-2">
      <a href="geomap.php" class="nav-link text-white <?php echo ($current_page === 'geomap.php') ? 'active' : ''; ?>">
        <i class="fas fa-map me-2"></i>GeoMap
      </a>
    </li>

    <li class="nav-item mb-2">
      <a href="user-guide.php" class="nav-link text-white <?php echo ($current_page === 'user-guide.php') ? 'active' : ''; ?>">
        <i class="fas fa-question-circle me-2"></i>Help & Guide
      </a>
    </li>

    <!-- Admin Only Section -->
    <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'coordinator'])): ?>
    <hr class="my-3">
    <li class="nav-item mb-2">
      <span class="nav-link text-muted small fw-bold px-0">
        <i class="fas fa-cog me-2"></i>ADMIN TOOLS
      </span>
    </li>

    <li class="nav-item mb-2">
      <a href="admin-users.php" class="nav-link text-white <?php echo ($current_page === 'admin-users.php') ? 'active' : ''; ?>">
        <i class="fas fa-users me-2"></i>User Management
      </a>
    </li>

    <li class="nav-item mb-2">
      <a href="admin-settings.php" class="nav-link text-white <?php echo ($current_page === 'admin-settings.php') ? 'active' : ''; ?>">
        <i class="fas fa-sliders-h me-2"></i>System Settings
      </a>
    </li>

    <li class="nav-item mb-2">
      <a href="admin-audit.php" class="nav-link text-white <?php echo ($current_page === 'admin-audit.php') ? 'active' : ''; ?>">
        <i class="fas fa-history me-2"></i>Audit Log
      </a>
    </li>
    <?php endif; ?>
  </ul>

  <hr />

  <ul class="nav nav-pills flex-column">
    <li class="nav-item mb-2">
      <a href="logout.php" class="nav-link text-danger">
        <i class="fas fa-sign-out-alt me-2"></i>Logout
      </a>
    </li>
  </ul>
</div>

<!-- Notification Manager Script -->
<script src="assets/js/notifications.js"></script>
<script>
  // Initialize notifications when DOM is loaded
  document.addEventListener('DOMContentLoaded', function() {
    notificationManager.init();
  });
</script>
