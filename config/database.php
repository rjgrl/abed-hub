<?php
/**
 * ABED IDM Hub - Database Configuration
 * Malaybalay City Infrastructure Development Management System
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'abed_idm_hub');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode([
        'status' => 'error',
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]));
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");

// Enable error reporting in development
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Security constants
define('PROJECT_TYPES', ['FSPF', 'IDP', 'AFME']);
define('PROJECT_STAGES', ['Proposal', 'Pre-Implementation', 'Procurement', 'Implementation', 'Completed']);
define('PROPOSAL_STATUS', ['For Validation', 'Proposal Validated', 'Not Feasible', 'Archived', 'Cancelled']);
define('SCOPE_OF_WORK', ['Construction', 'Rehabilitation', 'Upgrading', 'Additional Work']);
define('USER_ROLES', ['admin', 'operator', 'viewer']);

// File upload settings
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('UPLOAD_DIR', 'uploads/');
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/jpg']);
define('ALLOWED_DOCUMENT_TYPES', ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);
define('ALLOWED_EXCEL_TYPES', ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);

// Session configuration
define('SESSION_TIMEOUT', 1800); // 30 minutes
define('SESSION_NAME', 'ABED_IDM_HUB');

// Initialize session
session_name(SESSION_NAME);
session_start();

// Check session timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
    session_destroy();
    header('Location: login.php?session_expired=1');
    exit;
}
$_SESSION['last_activity'] = time();

/**
 * Helper function: Check if user is authenticated
 */
function isAuthenticated() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Helper function: Redirect to login if not authenticated
 */
function requireLogin() {
    if (!isAuthenticated()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Helper function: Check user role
 */
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * Helper function: Require specific role
 */
function requireRole($role) {
    if (!hasRole($role) && $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        die(json_encode(['status' => 'error', 'message' => 'Insufficient permissions']));
    }
}

/**
 * Helper function: Sanitize input
 */
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Helper function: Validate email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Helper function: Log audit trail
 */
function logAudit($action, $projectType = null, $projectId = null, $oldValues = null, $newValues = null) {
    global $conn;
    
    $userId = $_SESSION['user_id'] ?? null;
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $oldValuesJson = $oldValues ? json_encode($oldValues) : null;
    $newValuesJson = $newValues ? json_encode($newValues) : null;
    
    $stmt = $conn->prepare("
        INSERT INTO audit_log (
            user_id, action, project_type, project_id, old_values, new_values, ip_address, user_agent
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param(
        "issiisss",
        $userId, $action, $projectType, $projectId, $oldValuesJson, $newValuesJson, $ipAddress, $userAgent
    );
    
    return $stmt->execute();
}
?>