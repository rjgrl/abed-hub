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
$token = isset($_POST['token']) ? trim($_POST['token']) : '';
$newPassword = isset($_POST['newPassword']) ? $_POST['newPassword'] : '';

// Validate inputs
if (empty($token)) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Invalid session']));
}

if (empty($newPassword) || strlen($newPassword) < 8) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Password must be at least 8 characters']));
}

// Validate password requirements
if (!preg_match('/[A-Z]/', $newPassword)) {
    die(json_encode(['status' => 'error', 'message' => 'Password must contain at least one uppercase letter']));
}

if (!preg_match('/\d/', $newPassword)) {
    die(json_encode(['status' => 'error', 'message' => 'Password must contain at least one number']));
}

if (!preg_match('/[!@#$%^&*]/', $newPassword)) {
    die(json_encode(['status' => 'error', 'message' => 'Password must contain at least one special character']));
}

// Find the token
$token_stmt = $conn->prepare(
    "SELECT user_id FROM password_reset_tokens WHERE token = ? AND expires_at > NOW() AND is_used = 0"
);

if (!$token_stmt) {
    die(json_encode(['status' => 'error', 'message' => 'Database error']));
}

$token_stmt->bind_param("s", $token);
$token_stmt->execute();
$token_result = $token_stmt->get_result();

if ($token_result->num_rows === 0) {
    http_response_code(401);
    die(json_encode(['status' => 'error', 'message' => 'Invalid or expired token. Please request a new password reset.']));
}

$token_data = $token_result->fetch_assoc();
$user_id = $token_data['user_id'];

// Hash the new password
$hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

// Update user password
$update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");

if (!$update_stmt) {
    die(json_encode(['status' => 'error', 'message' => 'Database error']));
}

$update_stmt->bind_param("si", $hashedPassword, $user_id);

if (!$update_stmt->execute()) {
    die(json_encode(['status' => 'error', 'message' => 'Failed to update password']));
}

// Mark token as used
$mark_used_stmt = $conn->prepare(
    "UPDATE password_reset_tokens SET is_used = 1 WHERE token = ?"
);
$mark_used_stmt->bind_param("s", $token);
$mark_used_stmt->execute();

// Get user info for logging
$user_stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();

// Log the action
$action = "Password reset via recovery";
$ip_address = $_SERVER['REMOTE_ADDR'];
$user_agent = $_SERVER['HTTP_USER_AGENT'];

$audit_stmt = $conn->prepare(
    "INSERT INTO audit_log (user_id, action, ip_address, user_agent) VALUES (?, ?, ?, ?)"
);
$audit_stmt->bind_param("isss", $user_id, $action, $ip_address, $user_agent);
$audit_stmt->execute();

// Delete all tokens for this user (invalidate all recovery codes)
$conn->query("DELETE FROM password_reset_tokens WHERE user_id = $user_id");

// Send confirmation email
$to = $user['email'];
$subject = "ABED IDM Hub - Password Changed Successfully";
$message = "
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; }
        .container { max-width: 600px; margin: 0 auto; background: #f5f7fa; padding: 20px; border-radius: 10px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: white; padding: 30px; border-radius: 0 0 10px 10px; }
        .success-message { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 8px; margin: 20px 0; }
        .footer { text-align: center; color: #999; font-size: 12px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h2>Password Changed</h2>
        </div>
        <div class='content'>
            <p>Your password has been successfully reset.</p>
            
            <div class='success-message'>
                ✓ You can now log in with your new password.
            </div>
            
            <p>If you didn't make this change or believe this is a security issue, please contact your system administrator immediately.</p>
            
            <p>For security, all recovery tokens have been invalidated.</p>
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

mail($to, $subject, $message, $headers);

// Return success
die(json_encode([
    'status' => 'success',
    'message' => 'Password reset successfully'
]));

$token_stmt->close();
$update_stmt->close();
$mark_used_stmt->close();
$user_stmt->close();
$audit_stmt->close();
$conn->close();
?>
