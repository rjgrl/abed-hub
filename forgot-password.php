<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    // Validate email
    if (empty($email) || !isValidEmail($email)) {
        echo json_encode(['status' => 'error', 'message' => 'Please enter a valid email']);
        exit;
    }

    // Check if user exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // Security: Don't reveal if email exists
        echo json_encode(['status' => 'success', 'message' => 'If the email exists, a reset code will be sent']);
        exit;
    }

    $user = $result->fetch_assoc();
    $user_id = $user['id'];

    // Generate reset token and code
    $token = bin2hex(random_bytes(32));
    $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Clear old tokens
    $stmt = $conn->prepare("DELETE FROM password_reset_tokens WHERE user_id = ? AND is_used = FALSE AND expires_at < NOW()");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    // Insert reset token
    $stmt = $conn->prepare("
        INSERT INTO password_reset_tokens (user_id, token, code, expires_at)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("isss", $user_id, $token, $code, $expires_at);

    if ($stmt->execute()) {
        // TODO: Send email with reset link and code
        // Example: mail($email, 'Password Reset Code', "Your reset code: " . $code);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Reset code sent to your email',
            'token' => $token // In production, send via email only
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error processing request']);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>