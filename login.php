<?php
session_name('ABED_IDM_HUB');
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
                <img src="logos/abed_logo.png" alt="ABED Logo" class="auth-logo">
                <div class="fw-bold auth-title">ABED Integrated Data Management Hub</div>
                <small class="auth-subtitle">Agricultural and Biosystems Engineering Division - LGU Malaybalay City</small>
                <p class="mb-0 mt-2 auth-header-note">Login</p>
              </div>
              <div class="card-body auth-card-body">
                <div id="alertContainer"></div>

                <form id="loginForm">
                  <div class="mb-3">
                    <label class="form-label">Login As</label>
                    <select class="form-select" name="login_role" required>
                      <option value="employee" selected>Employee Login</option>
                      <option value="admin">Admin Login (Super Admin)</option>
                    </select>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input
                      type="text"
                      class="form-control"
                      name="username"
                      placeholder="Enter your username"
                      autocomplete="username"
                      required
                    />
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Password</label>
                    <div class="input-password-wrap">
                      <input
                        type="password"
                        class="form-control"
                        id="loginPassword"
                        name="password"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required
                      />
                      <button
                        type="button"
                        class="btn-password-toggle"
                        id="toggleLoginPassword"
                        aria-label="Show password"
                        aria-pressed="false"
                      >
                        <i class="fas fa-eye" aria-hidden="true"></i>
                      </button>
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
                  <a href="index.php" class="btn btn-outline-primary btn-sm mt-3 px-3">
                    <i class="fas fa-arrow-left me-2"></i>Go back to Homepage
                  </a>
                </div>
              </div>
      </div>

      <div class="auth-footer">
        <small>&copy; 2026 ABED IDM Hub. All rights reserved.</small>
      </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script src="assets/js/login.js?v=20260503"></script>
  </body>
</html>

