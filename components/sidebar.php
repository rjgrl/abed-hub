<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_page = basename($_SERVER['SCRIPT_NAME']);
?>
<div class="sidebar-fixed bg-dark text-white p-3 v-100 d-flex flex-column">
  <h4 class="fw-bold mb-4">Admin Panel</h4>

  <ul class="nav nav-pills flex-column mb-auto">
    <li class="nav-item mb-2">
      <a href="dashboard.php" class="nav-link text-white <?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>">
        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
      </a>
    </li>
    <li class="nav-item mb-2">
      <a href="reports.php" class="nav-link text-white <?php echo ($current_page === 'reports.php') ? 'active' : ''; ?>">
        <i class="fas fa-chart-bar me-2"></i>Reports
      </a>
    </li>
    <li class="nav-item mb-2">
      <a href="analytics.php" class="nav-link text-white <?php echo ($current_page === 'analytics.php') ? 'active' : ''; ?>">
        <i class="fas fa-chart-pie me-2"></i>Analytics
      </a>
    </li>
    <li class="nav-item mb-2">
      <a href="projects.php" class="nav-link text-white <?php echo ($current_page === 'projects.php') ? 'active' : ''; ?>">
        <i class="fas fa-folder-open me-2"></i>Projects
      </a>
    </li>
    <li class="nav-item mb-2">
      <a href="geomap.php" class="nav-link text-white <?php echo ($current_page === 'geomap.php') ? 'active' : ''; ?>">
        <i class="fas fa-map me-2"></i>GeoMap
      </a>
    </li>
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
