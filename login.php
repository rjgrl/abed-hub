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
    <title>ABED IDM Hub - Login</title>
    <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/css/style.css" />
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"
    />
  </head>
  </head>
  <body class="auth-body" style="background-image: url('logos/City.jpg'); background-size: cover; background-position: center; background-repeat: no-repeat; min-height: 100vh; display: flex; justify-content: center; align-items: center;">
    <div class="auth-container" style="display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%;">
      <div class="card auth-card login">
        <div class="card-header auth-header text-center py-4">
          <img src="logos/abed_logo.png" alt="AgriTrack Logo" style="width: 200px; height: 80px; object-fit: contain; margin-bottom: 8px;">
          <div class="fw-bold" style="font-size: 1.40rem; line-height: 1.3; color: #fff;">ABED Integrated Data Management Hub</div>
          <small style="font-size: 0.78rem; color: rgba(255,255,255,0.85);">Agricultural and Biosystems Engineering Division - LGU Malaybalay City</small>
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

