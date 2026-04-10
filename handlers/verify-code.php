<?php
session_name('ABED_IDM_HUB');
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

// Get form data
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$code = isset($_POST['code']) ? trim($_POST['code']) : '';

// Validate inputs
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Invalid email address']));
}

if (empty($code) || strlen($code) !== 6 || !ctype_digit($code)) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Please enter a valid 6-digit code']));
}

// Get user by email
$user_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
if (!$user_stmt) {
    die(json_encode(['status' => 'error', 'message' => 'Database error']));
}

$user_stmt->bind_param("s", $email);
$user_stmt->execute();
$user_result = $user_stmt->get_result();

if ($user_result->num_rows === 0) {
    http_response_code(404);
    die(json_encode(['status' => 'error', 'message' => 'User not found']));
}

$user = $user_result->fetch_assoc();
$user_id = $user['id'];

// Check if code is valid and not expired
$token_stmt = $conn->prepare(
    "SELECT token FROM password_reset_tokens WHERE user_id = ? AND code = ? AND expires_at > NOW() AND is_used = 0"
);

if (!$token_stmt) {
    die(json_encode(['status' => 'error', 'message' => 'Database error']));
}

$token_stmt->bind_param("is", $user_id, $code);
$token_stmt->execute();
$token_result = $token_stmt->get_result();

if ($token_result->num_rows === 0) {
    http_response_code(401);
    die(json_encode(['status' => 'error', 'message' => 'Invalid or expired code. Please request a new one.']));
}

$token_data = $token_result->fetch_assoc();
$token = $token_data['token'];

// Return success with token
die(json_encode([
    'status' => 'success',
    'message' => 'Code verified successfully',
    'token' => $token
]));

$user_stmt->close();
$token_stmt->close();
$conn->close();
?>
