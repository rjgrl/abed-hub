<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim($_POST['token'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $new_password = trim($_POST['newPassword'] ?? '');
    $confirm_password = trim($_POST['confirmPassword'] ?? '');

    // Validate input
    if (empty($token) || empty($code) || empty($new_password)) {
        echo json_encode(['status' => 'error', 'message' => 'Please fill out all fields']);
        exit;
    }

    // Validate password match
    if ($new_password !== $confirm_password) {
        echo json_encode(['status' => 'error', 'message' => 'Passwords do not match']);
        exit;
    }

    // Validate password strength
    if (strlen($new_password) < 8) {
        echo json_encode(['status' => 'error', 'message' => 'Password must be at least 8 characters']);
        exit;
    }

    // Verify reset token
    $stmt = $conn->prepare("
        SELECT user_id FROM password_reset_tokens 
        WHERE token = ? AND code = ? AND is_used = FALSE AND expires_at > NOW()
    ");
    $stmt->bind_param("ss", $token, $code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid or expired reset code']);
        exit;
    }

    $reset_data = $result->fetch_assoc();
    $user_id = $reset_data['user_id'];

    // Update password
    $password_hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $password_hash, $user_id);

    if ($stmt->execute()) {
        // Mark token as used
        $stmt = $conn->prepare("UPDATE password_reset_tokens SET is_used = TRUE WHERE token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();

        logAudit('PASSWORD_RESET', null, null);
        echo json_encode(['status' => 'success', 'message' => 'Password reset successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error resetting password']);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>