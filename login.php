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
    <title>ABED IDM Hub - Login</title>
    <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/css/style.css" />
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"
    />
  </head>
  <body class="auth-body">
    <div class="auth-container">
      <div class="card auth-card login">
        <div class="card-header auth-header text-center py-4">
          <h3 class="mb-0"><i class="fas fa-building me-2"></i>ABED IDM Hub</h3>
          <small>Malaybalay City</small>
        </div>
        <div class="card-body auth-card-body">
          <div id="alertContainer"></div>

          <form id="loginForm">
            <div class="mb-3">
              <label class="form-label">Username</label>
              <div class="input-group">
                <span class="input-group-text">
                  <i class="fas fa-user"></i>
                </span>
                <input
                  type="text"
                  class="form-control"
                  name="username"
                  placeholder="Enter your username"
                  required
                />
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">Password</label>
              <div class="input-group">
                <span class="input-group-text">
                  <i class="fas fa-lock"></i>
                </span>
                <input
                  type="password"
                  class="form-control"
                  name="password"
                  placeholder="Enter your password"
                  required
                />
              </div>
            </div>

            <div class="mb-3 form-check">
              <input type="checkbox" class="form-check-input" id="rememberMe" />
              <label class="form-check-label" for="rememberMe">
                Remember me
              </label>
            </div>

            <button type="submit" class="btn btn-auth-submit w-100">
              <i class="fas fa-sign-in-alt me-2"></i>Login
            </button>
          </form>

          <div class="divider-text mt-4">
            <span>New User?</span>
          </div>

          <div class="d-grid gap-2">
            <a href="signup.php" class="btn btn-outline-primary">
              <i class="fas fa-user-plus me-2"></i>Create Account
            </a>
          </div>

          <div class="text-center mt-3">
            <a
              href="forgot-password.php"
              class="text-decoration-none text-muted small link-primary-custom"
            >
              Forgot Password?
            </a>
          </div>

          <hr class="my-4" />

          <div class="text-center">
            <p class="text-muted small mb-0">
              For support, contact your system administrator
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
    <script src="assets/js/login.js"></script>
  </body>
</html>

