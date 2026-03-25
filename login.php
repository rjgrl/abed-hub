<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Validate input
    if (empty($username) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Please fill out all fields']);
        exit;
    }

    // Query user
    $stmt = $conn->prepare("SELECT id, password, full_name, role, office_unit FROM users WHERE (username = ? OR email = ?) AND is_active = TRUE");
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $username;
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['office_unit'] = $user['office_unit'];
            $_SESSION['login_time'] = time();
            
            logAudit('LOGIN', null, null);
            
            echo json_encode(['status' => 'success', 'message' => 'Login successful']);
        } else {
            logAudit('FAILED_LOGIN_ATTEMPT', null, null);
            echo json_encode(['status' => 'error', 'message' => 'Invalid password']);
        }
    } else {
        logAudit('FAILED_LOGIN_ATTEMPT', null, null);
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>