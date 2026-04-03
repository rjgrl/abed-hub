<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isAuthenticated = isset($_SESSION['user_id']);
$fullName = htmlspecialchars($_SESSION['full_name'] ?? 'User');
$current_page = basename($_SERVER['SCRIPT_NAME']);
$activePage = $current_page;
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary navbar-fixed">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="dashboard.php">
            <i class="fas fa-building me-2"></i>ABED IDM Hub
        </a>
        <div class="d-flex align-items-center text-white ms-3">
            <small class="fw-bold"><?php echo match($current_page) {
                'dashboard.php' => 'Dashboard',
                'reports.php' => 'Reports',
                'analytics.php' => 'Analytics',
                'projects.php', 'project-details.php' => 'Projects',
                'geomap.php' => 'GeoMap',
                'my-account.php' => 'My Account',
                'afme-machinery-details.php' => 'AFME Machinery',
                default => 'ABED IDM Hub'
            }; ?></small>
        </div>

        <div class="ms-auto">
            <?php if ($isAuthenticated): ?>
            <ul class="navbar-nav flex-row align-items-center">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-white" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user-circle me-1"></i><?php echo $fullName; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="my-account.php"><i class="fas fa-user me-2"></i>My Account</a></li>
                        <li><a class="dropdown-item" href="my-account.php?edit=1"><i class="fas fa-edit me-2"></i>Edit Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </li>
            </ul>
            <?php else: ?>
            <a class="btn btn-outline-light btn-sm" href="login.php"><i class="fas fa-sign-in-alt me-1"></i>Login</a>
            <a class="btn btn-outline-light btn-sm ms-2" href="signup.php"><i class="fas fa-user-plus me-1"></i>Signup</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

