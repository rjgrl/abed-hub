<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['fullName'] ?? '');
    $employee_id = trim($_POST['employeeId'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirmPassword'] ?? '');
    $office_unit = trim($_POST['officeUnit'] ?? '');

    // Validate input
    $required = ['fullName', 'employeeId', 'email', 'username', 'password', 'officeUnit'];
    foreach ($required as $field) {
        if (empty($_POST[$field] ?? '')) {
            echo json_encode(['status' => 'error', 'message' => "Field '$field' is required"]);
            exit;
        }
    }

    // Validate password match
    if ($password !== $confirm_password) {
        echo json_encode(['status' => 'error', 'message' => 'Passwords do not match']);
        exit;
    }

    // Validate password strength
    if (strlen($password) < 8) {
        echo json_encode(['status' => 'error', 'message' => 'Password must be at least 8 characters']);
        exit;
    }

    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        echo json_encode(['status' => 'error', 'message' => 'Password must contain uppercase letters and numbers']);
        exit;
    }

    // Validate email
    if (!isValidEmail($email)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email format']);
        exit;
    }

    // Check if user already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ? OR employee_id = ?");
    $stmt->bind_param("sss", $email, $username, $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Email, username, or employee ID already registered']);
        exit;
    }

    // Hash password
    $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    // Insert user
    $stmt = $conn->prepare("
        INSERT INTO users (username, email, full_name, employee_id, password, office_unit, role)
        VALUES (?, ?, ?, ?, ?, ?, 'operator')
    ");

    $stmt->bind_param("ssssss", $username, $email, $full_name, $employee_id, $password_hash, $office_unit);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Account created successfully. Please login.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error creating account']);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>