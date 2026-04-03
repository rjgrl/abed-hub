<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Reset Password - ABED IDM Hub</title>
    <link rel="stylesheet" href="bootstrap/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/css/style.css" />
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"
    />
  </head>
  <body class="auth-body">
    <div class="auth-container">
      <div class="card auth-card">
        <div class="card-header auth-header text-center py-4">
          <div class="auth-icon">
            <i class="fas fa-shield-alt"></i>
          </div>
          <h3>Create New Password</h3>
          <p>Enter your new password below</p>
        </div>

        <div class="card-body auth-card-body">
          <div id="alertContainer"></div>

          <form id="resetPasswordForm">
            <div class="mb-4">
              <label class="form-label">
                <i class="fas fa-lock"></i>New Password
              </label>
              <div class="input-group">
                <input
                  type="password"
                  class="form-control"
                  id="newPassword"
                  name="newPassword"
                  placeholder="Enter new password"
                  required
                />
                <span class="input-group-text" onclick="togglePassword('newPassword')">
                  <i class="fas fa-eye"></i>
                </span>
              </div>
              <div class="password-strength">
                <div class="password-strength-meter" id="strengthMeter"></div>
              </div>
              <small class="strength-text" id="strengthText"></small>
            </div>

            <div class="mb-3">
              <label class="form-label">
                <i class="fas fa-check-circle"></i>Confirm Password
              </label>
              <div class="input-group">
                <input
                  type="password"
                  class="form-control"
                  id="confirmPassword"
                  name="confirmPassword"
                  placeholder="Confirm password"
                  required
                />
                <span class="input-group-text" onclick="togglePassword('confirmPassword')">
                  <i class="fas fa-eye"></i>
                </span>
              </div>
              <small class="text-muted" id="matchText"></small>
            </div>

            <div class="small mb-4">
              <div class="mb-1">
                <i
                  class="fas fa-check-circle"
                  id="check-length"
                  style="color: #ccc"
                ></i>
                At least 8 characters
              </div>
              <div class="mb-1">
                <i
                  class="fas fa-check-circle"
                  id="check-upper"
                  style="color: #ccc"
                ></i>
                At least one uppercase letter
              </div>
              <div class="mb-1">
                <i
                  class="fas fa-check-circle"
                  id="check-number"
                  style="color: #ccc"
                ></i>
                At least one number
              </div>
              <div>
                <i
                  class="fas fa-check-circle"
                  id="check-special"
                  style="color: #ccc"
                ></i>
                At least one special character (!@#$%^&*)
              </div>
            </div>

            <button type="submit" class="btn btn-auth-submit w-100 mb-2">
              <i class="fas fa-key me-2"></i>Reset Password
            </button>

            <a href="login.php" class="btn btn-auth-back w-100">
              <i class="fas fa-arrow-left me-2"></i>Back to Login
            </a>
          </form>

          <div class="divider-text"></div>

          <div class="text-center">
            <p class="text-muted small mb-0">
              <i class="fas fa-shield-alt"></i> Your password will be encrypted
              and secured
            </p>
          </div>
        </div>
      </div>

      <div class="auth-footer">
        <small>&copy; 2026 ABED IDM Hub. All rights reserved.</small>
      </div>
    </div>

    <script src="assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script src="assets/js/reset-password.js"></script>
  </body>
</html>

