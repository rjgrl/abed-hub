<?php
session_name('ABED_IDM_HUB');
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

/**
 * Next EMP-{YEAR}-{NNN} based on existing rows for that year (auto-increment sequence).
 */
function generate_next_employee_id(mysqli $conn): string
{
    $year = date('Y');
    $prefix = 'EMP-' . $year . '-';
    $like = $prefix . '%';

    $stmt = $conn->prepare('SELECT employee_id FROM users WHERE employee_id LIKE ?');
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $result = $stmt->get_result();

    $max = 0;
    $pattern = '/^' . preg_quote($prefix, '/') . '(\d+)$/';
    while ($row = $result->fetch_assoc()) {
        if (preg_match($pattern, (string) $row['employee_id'], $m)) {
            $max = max($max, (int) $m[1]);
        }
    }
    $stmt->close();

    $next = $max + 1;
    return $prefix . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['firstName'] ?? '');
    $last_name = trim($_POST['lastName'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirmPassword'] ?? '');
    $office_unit = trim($_POST['officeUnit'] ?? '');

    // Validate input
    $required = ['firstName', 'lastName', 'email', 'username', 'password', 'officeUnit'];
    foreach ($required as $field) {
        if (empty($_POST[$field] ?? '')) {
            echo json_encode(['status' => 'error', 'message' => "Field '$field' is required"]);
            exit;
        }
    }

    if ($first_name === '' || $last_name === '') {
        echo json_encode(['status' => 'error', 'message' => 'First and last name are required']);
        exit;
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

    // Check if user already exists (employee ID is assigned server-side)
    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? OR username = ?');
    $stmt->bind_param('ss', $email, $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Email or username already registered']);
        exit;
    }
    $stmt->close();

    // Hash password
    $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    $stmt = $conn->prepare('
        INSERT INTO users (username, email, first_name, last_name, employee_id, password, office_unit, role, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, \'operator\', 0)
    ');

    $max_attempts = 8;
    $inserted = false;

    for ($attempt = 0; $attempt < $max_attempts; $attempt++) {
        $employee_id = generate_next_employee_id($conn);
        $stmt->bind_param('sssssss', $username, $email, $first_name, $last_name, $employee_id, $password_hash, $office_unit);

        if ($stmt->execute()) {
            $inserted = true;
            break;
        }

        // Duplicate employee_id (concurrent signups): retry with a new generated ID
        if ($conn->errno === 1062 && stripos($conn->error, 'employee_id') !== false) {
            continue;
        }

        break;
    }

    if ($inserted) {
        echo json_encode(['status' => 'success', 'message' => 'Account created. Please wait for Super Admin approval before logging in.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error creating account']);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
