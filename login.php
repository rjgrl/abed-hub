<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'redirect', 'redirect_url' => 'dashboard.php']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Validate input
    if (empty($username) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Username and password are required']);
        exit;
    }

    // Query user from database
    $stmt = $conn->prepare("SELECT id, username, email, full_name, password, role, is_active FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid username or password']);
        exit;
    }

    $user = $result->fetch_assoc();

    // Check if user is active
    if (!$user['is_active']) {
        echo json_encode(['status' => 'error', 'message' => 'Account is inactive']);
        exit;
    }

    // Verify password
    if (!password_verify($password, $user['password'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid username or password']);
        exit;
    }

    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['login_time'] = time();

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
        'redirect_url' => 'dashboard.php'
    ]);

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>