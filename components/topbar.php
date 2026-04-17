<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isAuthenticated = isset($_SESSION['user_id']);
$userName = htmlspecialchars($_SESSION['full_name'] ?? 'Guest User');
$userRole = htmlspecialchars($_SESSION['role'] ?? 'Guest');
?>
<div class="topbar-fixed border-bottom px-3 d-flex justify-content-between align-items-center bg-white">
    <a class="navbar-brand d-flex align-items-center text-decoration-none" href="dashboard-enhanced.php" style="gap: 10px;">
        <img src="logos/abed_logo.png" alt="ABED Logo" style="width: 42px; height: 42px; object-fit: contain; flex-shrink: 0;">
        <div style="line-height: 1.3;">
            <div class="fw-bold" style="font-size: 1.1rem; color: #1a1a1a;">ABED Integrated Data Management Hub</div>
            <small class="text-muted d-block" style="font-size: 0.75rem; line-height: 1.2;">Agricultural and Biosystems Engineering Division - LGU Malaybalay City</small>
        </div>
    </a>
    
    <div class="d-flex align-items-center">
        <?php if ($isAuthenticated): ?>
            <div class="dropdown d-flex align-items-center">
                <div class="text-end me-3 d-none d-sm-block">
                    <div class="fw-bold small text-dark" style="line-height: 1.2;"><?php echo $userName; ?></div>
                    <div class="text-muted small" style="font-size: 0.75rem;"><?php echo $userRole; ?></div>
                </div>

                <a href="#" class="d-flex align-items-center text-decoration-none" id="userTopbarDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="rounded-circle fw-bold text-white d-flex align-items-center justify-content-center shadow-sm" 
                         style="width:40px; height:40px; background: var(--primary-color);">
                        <?php echo strtoupper(substr($userName, 0, 2)); ?>
                    </div>
                </a>

                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-2" aria-labelledby="userTopbarDropdown">
                    <li class="px-3 py-2 d-sm-none">
                        <div class="fw-bold small text-dark"><?php echo $userName; ?></div>
                        <div class="text-muted small"><?php echo $userRole; ?></div>
                        <hr class="dropdown-divider">
                    </li>
                    <li><a class="dropdown-item" href="my-account.php"><i class="fas fa-user me-2 text-primary"></i>My Account</a></li>
                    <li><a class="dropdown-item" href="my-account.php?edit=1"><i class="fas fa-edit me-2 text-primary"></i>Edit Profile</a></li>
                </ul>
            </div>
        <?php else: ?>
            <a class="btn btn-outline-primary btn-sm" href="login.php">Login</a>
        <?php endif; ?>
    </div>
</div>