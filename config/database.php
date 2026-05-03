<?php
/**
 * ABED IDM Hub - Database Configuration
 * Malaybalay City Infrastructure Development Management System
 */

// Database credentials
define('DB_HOST', 'localhost');      // Database host
define('DB_USER', 'root');           // Database user
define('DB_PASS', '');               // Database password
define('DB_NAME', 'abed_idm_hub');   // Database name
define('DB_PORT', 3306);             // Database port

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Check connection
if ($conn->connect_error) {
    // Log error for debugging
    error_log("Database connection failed: " . $conn->connect_error);
    
    // Show user-friendly error
    die(json_encode([
        'status' => 'error',
        'message' => 'Database connection error. Please contact administrator.'
    ]));
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");

// Set timezone
date_default_timezone_set('Asia/Manila');

// Enable error reporting in development (set to 1 while debugging locally)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// File upload settings
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('UPLOAD_DIR', 'uploads/');
define('ALLOWED_IMAGE_TYPES', [
    'image/jpeg',
    'image/png',
    'image/jpg',
    'image/webp',
    'image/gif',
    'image/heic',
    'image/heif',
]);
define('ALLOWED_DOCUMENT_TYPES', ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);
define('ALLOWED_EXCEL_TYPES', ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);

// Session configuration
define('SESSION_TIMEOUT', 1800); // 30 minutes
define('SESSION_NAME', 'ABED_IDM_HUB');

// Initialize session (only if not already started)
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// Check session timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
    session_destroy();
    header('Location: login.php?session_expired=1');
    exit;
}
$_SESSION['last_activity'] = time();

// Load helper functions
require_once __DIR__ . '/../functions/helpers.php';

ensure_users_first_last_name_schema($conn);
?>
