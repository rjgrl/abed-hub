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