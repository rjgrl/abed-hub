<?php
/**
 * Begins Google OAuth: stores intent in session and redirects to Google.
 *
 * Login:  GET  google-oauth-start.php?intent=login&login_role=employee|admin
 * Signup: POST intent=signup&officeUnit=...
 */

session_name('ABED_IDM_HUB');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/google-oauth.php';

if (!google_oauth_is_configured()) {
    header('Location: ../login.php?oauth_error=config');
    exit;
}

$intent = $_POST['intent'] ?? $_GET['intent'] ?? 'login';
if (!in_array($intent, ['login', 'signup'], true)) {
    $intent = 'login';
}

$login_role = trim((string) ($_POST['login_role'] ?? $_GET['login_role'] ?? 'employee'));
if (!in_array($login_role, ['employee', 'admin'], true)) {
    $login_role = 'employee';
}

$office_unit = trim((string) ($_POST['officeUnit'] ?? ''));

if ($intent === 'signup') {
    if (!in_array($office_unit, google_oauth_allowed_office_units(), true)) {
        header('Location: ../signup.php?oauth_error=office_required');
        exit;
    }
}

$state = bin2hex(random_bytes(24));
$_SESSION['google_oauth_state'] = $state;
$_SESSION['google_oauth_intent'] = $intent;
$_SESSION['google_oauth_login_role'] = $login_role;
$_SESSION['google_oauth_office_unit'] = $office_unit;

header('Location: ' . google_oauth_authorization_url($state));
exit;
