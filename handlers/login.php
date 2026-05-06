<?php
session_name('ABED_IDM_HUB');
session_start();
require_once '../config/database.php';
require_once '../config/recaptcha.php';

header('Content-Type: application/json');

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'redirect', 'redirect_url' => getDashboardRoute()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recaptchaToken = $_POST['g-recaptcha-response'] ?? '';
    $recaptcha = verify_recaptcha_v2(
        is_string($recaptchaToken) ? $recaptchaToken : null,
        $_SERVER['REMOTE_ADDR'] ?? null
    );
    if (!$recaptcha['success']) {
        $codes = $recaptcha['error_codes'] ?? [];
        $msg = in_array('recaptcha-not-reachable', $codes, true)
            ? 'Could not reach reCAPTCHA verification. Please try again in a moment.'
            : 'Please verify that you are not a robot.';
        echo json_encode(['status' => 'error', 'message' => $msg]);
        exit;
    }

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $loginRole = trim($_POST['login_role'] ?? 'employee');
    if (!in_array($loginRole, ['employee', 'admin'], true)) {
        $loginRole = 'employee';
    }

    // Validate input
    if (empty($username) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Username and password are required']);
        exit;
    }

    // Query user from database
    $hasUserRejectionReasonColumn = false;
    $colCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'rejection_reason'");
    if ($colCheck && $colCheck->num_rows > 0) {
        $hasUserRejectionReasonColumn = true;
    }

    $selectFields = "id, username, email, first_name, last_name, password, role, is_active, profile_picture";
    if ($hasUserRejectionReasonColumn) {
        $selectFields .= ", rejection_reason";
    }

    $stmt = $conn->prepare("SELECT {$selectFields} FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid username or password']);
        exit;
    }

    $user = $result->fetch_assoc();

    // Pending approval (is_active = 0) or deactivated account
    if (!(int) $user['is_active']) {
        $reason = $hasUserRejectionReasonColumn ? trim((string) ($user['rejection_reason'] ?? '')) : '';
        if ($reason !== '') {
            echo json_encode([
                'status' => 'error',
                'message' => 'Your account request was rejected by Super Admin. Reason: ' . $reason,
            ]);
            exit;
        }
        echo json_encode([
            'status' => 'error',
            'message' => 'Your account is pending Super Admin approval or has been deactivated. You cannot log in yet.',
        ]);
        exit;
    }

    // Verify password
    if (!password_verify($password, $user['password'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid username or password']);
        exit;
    }

    // Enforce role-aware login entry point.
    if ($loginRole === 'admin' && $user['role'] !== 'admin') {
        echo json_encode(['status' => 'error', 'message' => 'Only Super Admin accounts can use Admin Login']);
        exit;
    }
    if ($loginRole === 'employee' && $user['role'] === 'admin') {
        echo json_encode(['status' => 'error', 'message' => 'Please use Admin Login for Super Admin accounts']);
        exit;
    }

    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['full_name'] = user_display_name($user['first_name'], $user['last_name']);
    $_SESSION['role'] = $user['role'];
    $_SESSION['login_time'] = time();
    $pic = $user['profile_picture'] ?? null;
    $_SESSION['profile_picture'] = is_string($pic) ? trim($pic) : '';

    // Log login activity
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $log_stmt = $conn->prepare("
        INSERT INTO audit_log (user_id, action, ip_address, user_agent)
        VALUES (?, 'LOGIN', ?, ?)
    ");
    $log_stmt->bind_param("iss", $user['id'], $ip_address, $user_agent);
    $log_stmt->execute();

    echo json_encode([
        'status' => 'success',
        'message' => 'Login successful',
        'redirect_url' => getDashboardRoute()
    ]);

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>
