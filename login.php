<?php
session_name('ABED_IDM_HUB');
session_start();
require_once __DIR__ . '/config/recaptcha.php';
require_once __DIR__ . '/config/google-oauth.php';

$oauthErrorMessages = [
    'config' => 'Google Sign-In is not configured yet. Ask your administrator to add OAuth credentials.',
    'denied' => 'Google sign-in was cancelled.',
    'invalid' => 'Sign-in session expired or was invalid. Please try again.',
    'token' => 'Could not complete Google sign-in. Please try again.',
    'email_unverified' => 'Your Google account email must be verified to sign in.',
    'inactive' => 'Your account is pending Super Admin approval or has been deactivated.',
    'admin_gate' => 'Only Super Admin accounts can use Admin Login.',
    'use_admin_login' => 'Please use Admin Login for Super Admin accounts.',
    'no_account' => 'No account found for this Google sign-in. Use Create Account first.',
    'account_conflict' => 'This Google account cannot be linked — that email is already linked to a different Google account.',
];

$oauthNoticeMessages = [
    'signup_pending' => 'Google account registered. Please wait for Super Admin approval before signing in.',
];

$oauth_err_key = preg_replace('/[^a-z0-9_]/', '', (string) ($_GET['oauth_error'] ?? ''));
$oauth_notice_key = preg_replace('/[^a-z0-9_]/', '', (string) ($_GET['oauth_notice'] ?? ''));
$oauth_alert_message = $oauth_err_key !== ''
    ? ($oauthErrorMessages[$oauth_err_key] ?? 'Sign-in failed. Please try again.')
    : null;
$oauth_notice_message = $oauth_notice_key !== ''
    ? ($oauthNoticeMessages[$oauth_notice_key] ?? '')
    : null;

$google_oauth_ready = google_oauth_is_configured();

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
    <script
      src="https://www.google.com/recaptcha/api.js"
      async
      defer
    ></script>
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
                <?php if ($oauth_alert_message): ?>
                <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($oauth_alert_message, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                <?php if ($oauth_notice_message): ?>
                <div class="alert alert-success" role="status"><?php echo htmlspecialchars($oauth_notice_message, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

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

                  <?php /* reCAPTCHA site key: set RECAPTCHA_SITE_KEY in config/recaptcha.php */ ?>
                  <div class="mb-3 text-center">
                    <div
                      class="g-recaptcha d-inline-block"
                      data-sitekey="<?php echo htmlspecialchars(RECAPTCHA_SITE_KEY, ENT_QUOTES, 'UTF-8'); ?>"
                    ></div>
                  </div>

                  <button type="submit" class="btn btn-auth-submit w-100">
                    <i class="fas fa-sign-in-alt me-2"></i>Login
                  </button>
                </form>

                <div class="divider-text mt-3">
                  <span>or</span>
                </div>
                <?php if ($google_oauth_ready): ?>
                <a href="#" class="btn btn-google w-100" id="googleLoginBtn" data-start-url="handlers/google-oauth-start.php">
                  <i class="fab fa-google me-2" aria-hidden="true"></i>Continue with Google
                </a>
                <p class="text-muted small text-center mt-2 mb-0">Uses the <strong>Login As</strong> option above (Employee vs Admin).</p>
                <?php else: ?>
                <p class="text-muted small text-center mb-0">Google Sign-In is available after the administrator adds OAuth client credentials in <code>config/google-oauth.php</code>.</p>
                <?php endif; ?>

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
    <script src="assets/js/login.js?v=20260505"></script>
  </body>
</html>

