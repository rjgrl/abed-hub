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
    <title>Sign Up - ABED IDM Hub</title>
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
                <img src="logos/abed_logo.png" alt="ABED Logo" class="auth-logo">
                <div class="fw-bold auth-title">ABED Integrated Data Management Hub</div>
                <small class="auth-subtitle">Agricultural and Biosystems Engineering Division - LGU Malaybalay City</small>
                <p class="mb-0 mt-1 auth-header-note">Create Account</p>
              </div>

              <div class="card-body auth-card-body">
                <div id="alertContainer"></div>

                <form id="signupForm">
                  <div class="row">
                    <div class="col-md-6 mb-3">
                      <label class="form-label">
                        <i class="fas fa-user"></i> First Name
                      </label>
                      <input
                        type="text"
                        class="form-control"
                        name="firstName"
                        placeholder="Juan"
                        required
                        autocomplete="given-name"
                      />
                    </div>
                    <div class="col-md-6 mb-3">
                      <label class="form-label">
                        <i class="fas fa-user-tag"></i> Last Name
                      </label>
                      <input
                        type="text"
                        class="form-control"
                        name="lastName"
                        placeholder="dela Cruz"
                        required
                        autocomplete="family-name"
                      />
                    </div>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">
                      <i class="fas fa-envelope"></i> Email Address
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
                  </div>

                  <div class="mb-3">
                    <label class="form-label">
                      <i class="fas fa-user"></i> Username
                    </label>
                    <div class="input-group">
                      <span class="input-group-text">
                        <i class="fas fa-at"></i>
                      </span>
                      <input
                        type="text"
                        class="form-control"
                        name="username"
                        placeholder="Enter your username"
                        required
                      />
                    </div>
                    <small class="text-muted">
                      <i class="fas fa-info-circle"></i> Username must be 4-20 characters
                    </small>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">
                      <i class="fas fa-building"></i> Office Unit
                    </label>
                    <select class="form-select" name="officeUnit" required>
                      <option value="">-- Select Office Unit --</option>
                      <option value="BKSP">Bureau of Soils and Water Management (BKSP)</option>
                      <option value="LGED">Land and Geospatial Engineering Division (LGED)</option>
                      <option value="APD">Agronomic and Plant Development Division (APD)</option>
                      <option value="AFMAD">Agricultural and Fisheries Machineries and Equipment Division (AFMAD)</option>
                      <option value="Other">Other</option>
                    </select>
                  </div>

                  <div class="text-center text-muted small mb-3">
                    <small>Password Requirements</small>
                  </div>

                  <div class="row">
                    <div class="col-md-6 mb-3">
                      <label class="form-label">
                        <i class="fas fa-lock"></i> Password
                      </label>
                      <input
                        type="password"
                        class="form-control"
                        id="password"
                        name="password"
                        placeholder="Enter password"
                        required
                      />
                      <div class="password-strength">
                        <div class="password-strength-meter" id="strengthMeter"></div>
                      </div>
                      <small class="strength-text" id="strengthText"></small>
                    </div>
                    <div class="col-md-6 mb-3">
                      <label class="form-label">
                        <i class="fas fa-check-circle"></i> Confirm Password
                      </label>
                      <input
                        type="password"
                        class="form-control"
                        id="confirmPassword"
                        name="confirmPassword"
                        placeholder="Confirm password"
                        required
                      />
                      <small class="text-muted" id="matchText"></small>
                    </div>
                  </div>

                  <div class="small mb-4">
                    <div class="mb-1">
                      <i class="fas fa-check-circle password-rule-check" id="check-length"></i>
                      At least 8 characters
                    </div>
                    <div class="mb-1">
                      <i class="fas fa-check-circle password-rule-check" id="check-upper"></i>
                      At least one uppercase letter
                    </div>
                    <div class="mb-1">
                      <i class="fas fa-check-circle password-rule-check" id="check-number"></i>
                      At least one number
                    </div>
                    <div>
                      <i class="fas fa-check-circle password-rule-check" id="check-special"></i>
                      At least one special character (!@#$%^&*)
                    </div>
                  </div>

                  <button type="submit" class="btn btn-auth-submit w-100">
                    <i class="fas fa-user-check me-2"></i>Create Account
                  </button>
                </form>

                <div class="divider-text mt-4">
                  <span>Already have an account?</span>
                </div>

                <a href="login.php" class="btn btn-outline-primary w-100">
                  <i class="fas fa-sign-in-alt me-2"></i>Login Here
                </a>

                <hr class="my-4" />

                <div class="text-center">
                  <p class="text-muted small mb-0">
                    <i class="fas fa-shield-alt"></i> Your data is secure and encrypted
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
    <script src="assets/js/signup.js"></script>
  </body>
</html>