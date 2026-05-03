<?php
session_name('ABED_IDM_HUB');
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email_styles.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

// Get email from form
$email = isset($_POST['email']) ? trim($_POST['email']) : '';

// Validate email
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Please provide a valid email address']));
}

// Check if email exists in database
$stmt = $conn->prepare("SELECT id, username, email, full_name FROM users WHERE email = ?");
if (!$stmt) {
    die(json_encode(['status' => 'error', 'message' => 'Database error']));
}

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Security: Don't reveal if email doesn't exist
    die(json_encode(['status' => 'success', 'message' => 'If email exists, recovery code has been sent']));
}

$user = $result->fetch_assoc();
$user_id = $user['id'];

// Generate a 6-digit code
$code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

// Generate a random token
$token = bin2hex(random_bytes(32));

// Set expiration time to 30 minutes from now
$expires_at = date('Y-m-d H:i:s', time() + (30 * 60));

// Delete any existing tokens for this user
$conn->query("DELETE FROM password_reset_tokens WHERE user_id = $user_id");

// Insert new token
$insert_stmt = $conn->prepare(
    "INSERT INTO password_reset_tokens (user_id, token, code, expires_at) VALUES (?, ?, ?, ?)"
);

if (!$insert_stmt) {
    die(json_encode(['status' => 'error', 'message' => 'Database error']));
}

$insert_stmt->bind_param("isss", $user_id, $token, $code, $expires_at);

if (!$insert_stmt->execute()) {
    die(json_encode(['status' => 'error', 'message' => 'Failed to generate recovery code']));
}

// Send email with recovery code
// Note: Configure your email settings accordingly
$to = $email;
$subject = "ABED IDM Hub - Password Recovery Code";
$recoveryCss = email_transactional_css_recovery();
$message = "
<html>
<head>
    <style>{$recoveryCss}</style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h2>Password Recovery</h2>
        </div>
        <div class='content'>
            <p>Hello " . htmlspecialchars($user['full_name']) . ",</p>
            <p>We received a request to reset your password. Use the code below to proceed:</p>
            
            <div class='code-box'>
                <div class='code'>$code</div>
            </div>
            
            <p>This code will expire in 30 minutes.</p>
            <p>If you didn't request a password reset, please ignore this email.</p>
            
            <div class='warning'>
                ⚠️ Never share this code with anyone. Support staff will never ask for this code.
            </div>
        </div>
        <div class='footer'>
            <p>&copy; 2026 ABED IDM Hub. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
";

$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
$headers .= "From: noreply@abed.gov.ph" . "\r\n";

// Send email
if (!mail($to, $subject, $message, $headers)) {
    die(json_encode(['status' => 'error', 'message' => 'Failed to send email']));
}

// Log action
$action = "Password recovery requested";
$ip_address = $_SERVER['REMOTE_ADDR'];
$user_agent = $_SERVER['HTTP_USER_AGENT'];

$audit_stmt = $conn->prepare(
    "INSERT INTO audit_log (user_id, action, ip_address, user_agent) VALUES (?, ?, ?, ?)"
);
$audit_stmt->bind_param("isss", $user_id, $action, $ip_address, $user_agent);
$audit_stmt->execute();

$stmt->close();
$insert_stmt->close();
$audit_stmt->close();
$conn->close();

die(json_encode([
    'status' => 'success',
    'message' => 'Recovery code sent to your email',
    'token' => $token
]));
