<?php
/**
 * Google OAuth redirect URI: exchanges code, loads profile, creates or logs in user.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/google-oauth.php';

function google_oauth_redirect_login(string $code): void
{
    header('Location: ../login.php?oauth_error=' . rawurlencode($code));
    exit;
}

function google_oauth_redirect_signup(string $code): void
{
    header('Location: ../signup.php?oauth_error=' . rawurlencode($code));
    exit;
}

if (!google_oauth_is_configured()) {
    google_oauth_redirect_login('config');
}

if (($_GET['error'] ?? '') === 'access_denied') {
    google_oauth_redirect_login('denied');
}

$code = trim((string) ($_GET['code'] ?? ''));
$state = trim((string) ($_GET['state'] ?? ''));

if ($code === '' || $state === '') {
    google_oauth_redirect_login('invalid');
}

$expected = $_SESSION['google_oauth_state'] ?? '';
if ($expected === '' || !hash_equals($expected, $state)) {
    google_oauth_redirect_login('invalid');
}

unset($_SESSION['google_oauth_state']);

$intent = $_SESSION['google_oauth_intent'] ?? 'login';
$login_role = $_SESSION['google_oauth_login_role'] ?? 'employee';
$office_unit = trim((string) ($_SESSION['google_oauth_office_unit'] ?? ''));

unset($_SESSION['google_oauth_intent'], $_SESSION['google_oauth_login_role'], $_SESSION['google_oauth_office_unit']);

if (!in_array($intent, ['login', 'signup'], true)) {
    $intent = 'login';
}
if (!in_array($login_role, ['employee', 'admin'], true)) {
    $login_role = 'employee';
}

$tokenResponse = google_oauth_exchange_code($code);
if ($tokenResponse === null || empty($tokenResponse['access_token'])) {
    google_oauth_redirect_login('token');
}

$info = google_oauth_fetch_userinfo((string) $tokenResponse['access_token']);
if ($info === null) {
    google_oauth_redirect_login('token');
}

$sub = trim((string) ($info['sub'] ?? ''));
$email = trim((string) ($info['email'] ?? ''));
$email_verified = !empty($info['email_verified']);

if ($sub === '' || $email === '' || !isValidEmail($email) || !$email_verified) {
    google_oauth_redirect_login('email_unverified');
}

$given = trim((string) ($info['given_name'] ?? ''));
$family = trim((string) ($info['family_name'] ?? ''));
if ($given === '' && $family === '') {
    $name = trim((string) ($info['name'] ?? ''));
    if ($name !== '') {
        $parts = preg_split('/\s+/u', $name, 2, PREG_SPLIT_NO_EMPTY);
        $given = $parts[0] ?? '';
        $family = $parts[1] ?? '';
    }
}
if ($given === '' && $family === '') {
    $given = strstr($email, '@', true) ?: 'User';
}

$pictureRaw = trim((string) ($info['picture'] ?? ''));
$picture = $pictureRaw !== '' ? substr($pictureRaw, 0, 500) : '';

$user = null;

$stmt = $conn->prepare(
    'SELECT id, username, email, first_name, last_name, password, role, is_active, google_sub, profile_picture
     FROM users WHERE google_sub = ? LIMIT 1'
);
$stmt->bind_param('s', $sub);
$stmt->execute();
$res = $stmt->get_result();
if ($res && $res->num_rows === 1) {
    $user = $res->fetch_assoc();
}
$stmt->close();

if ($user === null) {
    $stmt = $conn->prepare(
        'SELECT id, username, email, first_name, last_name, password, role, is_active, google_sub, profile_picture
         FROM users WHERE email = ? LIMIT 1'
    );
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows === 1) {
        $user = $res->fetch_assoc();
    }
    $stmt->close();
}

if ($user !== null) {
    $existingSub = trim((string) ($user['google_sub'] ?? ''));
    if ($existingSub !== '' && $existingSub !== $sub) {
        google_oauth_redirect_login('account_conflict');
    }

    if ($existingSub === '') {
        $uidLocal = (int) $user['id'];
        if ($picture !== '') {
            $upd = $conn->prepare(
                'UPDATE users SET google_sub = ?, profile_picture = ? WHERE id = ? AND google_sub IS NULL'
            );
            $upd->bind_param('ssi', $sub, $picture, $uidLocal);
        } else {
            $upd = $conn->prepare(
                'UPDATE users SET google_sub = ? WHERE id = ? AND google_sub IS NULL'
            );
            $upd->bind_param('si', $sub, $uidLocal);
        }
        $upd->execute();
        $upd->close();

        $stmt = $conn->prepare(
            'SELECT id, username, email, first_name, last_name, password, role, is_active, google_sub, profile_picture
             FROM users WHERE id = ? LIMIT 1'
        );
        $uid = (int) $user['id'];
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    // Always refresh avatar from Google on Google sign-in when available.
    if ($picture !== '' && isset($user['id'])) {
        $uidPicture = (int) $user['id'];
        $updPic = $conn->prepare('UPDATE users SET profile_picture = ? WHERE id = ?');
        if ($updPic) {
            $updPic->bind_param('si', $picture, $uidPicture);
            $updPic->execute();
            $updPic->close();
            $user['profile_picture'] = $picture;
        }
    }
}

if ($user === null) {
    if ($intent === 'login') {
        google_oauth_redirect_login('no_account');
    }

    if (!in_array($office_unit, google_oauth_allowed_office_units(), true)) {
        google_oauth_redirect_signup('office_required');
    }

    $username = google_oauth_derive_username($conn, $email, $sub);
    $password_hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT, ['cost' => 12]);

    $ins = $conn->prepare(
        'INSERT INTO users (username, email, first_name, last_name, employee_id, password, office_unit, role, is_active, google_sub, profile_picture)
         VALUES (?, ?, ?, ?, ?, ?, ?, \'employee\', 0, ?, NULLIF(?, \'\'))'
    );

    $inserted = false;
    for ($attempt = 0; $attempt < 8; $attempt++) {
        $employee_id = generate_next_employee_id($conn);
        $picBind = $picture;
        $ins->bind_param(
            'sssssssss',
            $username,
            $email,
            $given,
            $family,
            $employee_id,
            $password_hash,
            $office_unit,
            $sub,
            $picBind
        );

        if ($ins->execute()) {
            $inserted = true;
            break;
        }

        if ($conn->errno === 1062 && stripos($conn->error, 'employee_id') !== false) {
            continue;
        }
        if ($conn->errno === 1062 && (stripos($conn->error, 'username') !== false || stripos($conn->error, 'email') !== false)) {
            $ins->close();
            google_oauth_redirect_signup('duplicate');
        }
        break;
    }
    $ins->close();

    if (!$inserted) {
        google_oauth_redirect_signup('server');
    }

    header('Location: ../login.php?oauth_notice=signup_pending');
    exit;
}

if (!(int) $user['is_active']) {
    google_oauth_redirect_login('inactive');
}

if ($login_role === 'admin' && $user['role'] !== 'admin') {
    google_oauth_redirect_login('admin_gate');
}
if ($login_role === 'employee' && $user['role'] === 'admin') {
    google_oauth_redirect_login('use_admin_login');
}

$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['email'] = $user['email'];
$_SESSION['full_name'] = user_display_name($user['first_name'], $user['last_name']);
$_SESSION['role'] = $user['role'];
$_SESSION['login_time'] = time();
$pic = $user['profile_picture'] ?? null;
$_SESSION['profile_picture'] = is_string($pic) ? trim($pic) : '';

$ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$log_stmt = $conn->prepare('
    INSERT INTO audit_log (user_id, action, ip_address, user_agent)
    VALUES (?, \'LOGIN\', ?, ?)
');
$uid = (int) $user['id'];
$log_stmt->bind_param('iss', $uid, $ip_address, $user_agent);
$log_stmt->execute();
$log_stmt->close();

header('Location: ../' . getDashboardRoute());
exit;
