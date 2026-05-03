<?php
session_name('ABED_IDM_HUB');
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard-enhanced.php');
    exit;
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Forgot Password - ABED IDM Hub</title>
    <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css" />
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
            <i class="fas fa-key"></i>
          </div>
          <h3>Password Recovery</h3>
          <p>Enter your email address to receive a recovery code</p>
        </div>

        <div class="card-body auth-card-body">
          <div id="alertContainer"></div>

          <form id="forgotPasswordForm">
            <div class="mb-4">
              <label class="form-label">
                <i class="fas fa-envelope"></i>Email Address
              </label>
              <div class="input-group">
                <span class="input-group-text">
                  <i class="fas fa-at"></i>
                </span>
                <input
                  type="email"
                  class="form-control"
                  name="email"
                  placeholder="your.email@abed.gov.ph"
                  required
                />
              </div>
              <div class="help-text">
                <i class="fas fa-info-circle"></i> We'll send a verification
                code to this email address
              </div>
            </div>

            <button type="submit" class="btn btn-auth-submit w-100 mb-2">
              <i class="fas fa-paper-plane me-2"></i>Send Recovery Code
            </button>

            <a href="login.php" class="btn btn-auth-back w-100">
              <i class="fas fa-arrow-left me-2"></i>Back to Login
            </a>
          </form>

          <div class="divider-text"></div>

          <div class="text-center">
            <p class="text-muted small mb-2">
              Don't have an account?
              <a href="signup.php" class="link-primary-custom">Sign up here</a>
            </p>
            <p class="text-muted small mb-0">
              <i class="fas fa-shield-alt"></i> Your data is secure and
              encrypted
            </p>
          </div>
        </div>
      </div>

      <div class="auth-footer">
        <small>&copy; 2026 ABED IDM Hub. All rights reserved.</small>
      </div>
    </div>

    <script src="assets/bootstrap/js/bootstrap.bundle.js"></script>
    <script src="assets/js/main.js"></script>
    <script src="assets/js/forgot-password.js"></script>
  </body>
</html>

