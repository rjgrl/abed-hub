<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$conn->close();
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>My Account - ABED IDM Hub</title>
    <link rel="stylesheet" href="bootstrap/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
  </head>
  <body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
      <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="dashboard.php">
          <i class="fas fa-building me-2"></i>ABED IDM Hub
        </a>
        <div class="navbar-text text-white ms-auto">
          <a href="logout.php" class="btn btn-outline-light btn-sm">
            <i class="fas fa-sign-out-alt me-1"></i>Logout
          </a>
        </div>
      </div>
    </nav>

    <div class="container py-5">
      <div class="row">
        <div class="col-md-8 mx-auto">
          <div class="card">
            <div class="card-header bg-primary text-white">
              <h5 class="mb-0">My Account Details</h5>
            </div>
            <div class="card-body">
              <div class="row">
                <div class="col-md-6">
                  <div class="mb-3">
                    <label class="form-label fw-bold">Full Name</label>
                    <p class="form-control-plaintext"><?php echo htmlspecialchars($user['full_name']); ?></p>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="mb-3">
                    <label class="form-label fw-bold">Username</label>
                    <p class="form-control-plaintext"><?php echo htmlspecialchars($user['username']); ?></p>
                  </div>
                </div>
              </div>

              <div class="row">
                <div class="col-md-6">
                  <div class="mb-3">
                    <label class="form-label fw-bold">Email</label>
                    <p class="form-control-plaintext"><?php echo htmlspecialchars($user['email']); ?></p>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="mb-3">
                    <label class="form-label fw-bold">Role</label>
                    <p class="form-control-plaintext">
                      <span class="badge bg-info"><?php echo ucfirst($user['role']); ?></span>
                    </p>
                  </div>
                </div>
              </div>

              <div class="row">
                <div class="col-md-6">
                  <div class="mb-3">
                    <label class="form-label fw-bold">Office/Unit</label>
                    <p class="form-control-plaintext"><?php echo htmlspecialchars($user['office_unit'] ?? 'N/A'); ?></p>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="mb-3">
                    <label class="form-label fw-bold">Account Status</label>
                    <p class="form-control-plaintext">
                      <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'danger'; ?>">
                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                      </span>
                    </p>
                  </div>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label fw-bold">Member Since</label>
                <p class="form-control-plaintext">
                  <?php echo date('F d, Y', strtotime($user['created_at'])); ?>
                </p>
              </div>

              <hr />

              <div class="d-flex gap-2">
                <a href="dashboard.php" class="btn btn-secondary">
                  <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                </a>
                <a href="change-password.php" class="btn btn-primary">
                  <i class="fas fa-key me-1"></i>Change Password
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <script src="bootstrap/js/bootstrap.bundle.min.js"></script>
  </body>
</html>