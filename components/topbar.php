<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$userName = htmlspecialchars($_SESSION['full_name'] ?? 'Guest User');
$userRole = htmlspecialchars($_SESSION['user_role'] ?? 'Guest');
?>
<div class="topbar-fixed bg-white border-bottom p-3 d-flex justify-content-between align-items-center">
  <h5 class="m-0 fw-bold text-dark">System Management</h5>
  <div class="d-flex align-items-center">
    <div class="text-end me-3 d-none d-sm-block">
      <div class="fw-bold small"><?php echo $userName; ?></div>
      <div class="text-muted small"><?php echo $userRole; ?></div>
    </div>
    <div class="rounded-circle fw-bold bg-primary text-white d-flex align-items-center justify-content-center" style="width:40px; height:40px;">
      <?php echo strtoupper(substr($userName, 0, 2)); ?>
    </div>
  </div>
</div>
