<?php
/**
 * ABED IDM Hub - Database Configuration
 * Malaybalay City Infrastructure Development Management System
 *
 * Production: (1) set DB_HOST, DB_USER, DB_PASS, DB_NAME in the server environment, or
 * (2) copy config/database.deploy.example.php to config/database.deploy.php and fill in
 * values (used when env vars are not set — typical on Ezyro / free hosts). MySQL host is
 * often a remote hostname, not "localhost". Host/user/database values are trimmed to
 * avoid copy-paste tab/space issues.
 */

if (PHP_VERSION_ID < 80000) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'This application requires PHP 8.0 or newer. Current version: ' . PHP_VERSION;
    exit;
}

if (!extension_loaded('mysqli')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'The PHP mysqli extension is required. Enable it in php.ini or your hosting control panel.';
    exit;
}

// Optional file-based credentials (when env vars are not set — copy database.deploy.example.php)
$_deploy_file = __DIR__ . '/database.deploy.php';
$_deploy = [];
if (is_readable($_deploy_file)) {
    $loaded = require $_deploy_file;
    if (is_array($loaded)) {
        $_deploy = $loaded;
    }
}

// --- Credentials: getenv first, then database.deploy.php, then XAMPP defaults ---
$dbHost = getenv('DB_HOST');
$dbHost = (is_string($dbHost) && $dbHost !== '') ? trim($dbHost) : '';
if ($dbHost === '' && isset($_deploy['host'])) {
    $dbHost = trim((string) $_deploy['host']);
}
if ($dbHost === '') {
    $dbHost = 'localhost';
}
define('DB_HOST', $dbHost);

$dbUser = getenv('DB_USER');
$dbUser = (is_string($dbUser) && $dbUser !== '') ? trim($dbUser) : '';
if ($dbUser === '' && isset($_deploy['user'])) {
    $dbUser = trim((string) $_deploy['user']);
}
if ($dbUser === '') {
    $dbUser = 'root';
}
define('DB_USER', $dbUser);

if (getenv('DB_PASS') !== false) {
    $dbPass = (string) getenv('DB_PASS');
} elseif (array_key_exists('pass', $_deploy)) {
    $dbPass = (string) $_deploy['pass'];
} else {
    $dbPass = '';
}
define('DB_PASS', $dbPass);

$dbName = getenv('DB_NAME');
$dbName = (is_string($dbName) && $dbName !== '') ? trim($dbName) : '';
if ($dbName === '' && isset($_deploy['name'])) {
    $dbName = trim((string) $_deploy['name']);
}
if ($dbName === '') {
    $dbName = 'abed_idm_hub';
}
define('DB_NAME', $dbName);

$dbPort = 3306;
$dbPortEnv = getenv('DB_PORT');
if (is_string($dbPortEnv) && $dbPortEnv !== '' && ctype_digit(trim($dbPortEnv))) {
    $dbPort = (int) trim($dbPortEnv);
} elseif (isset($_deploy['port']) && is_numeric($_deploy['port'])) {
    $dbPort = (int) $_deploy['port'];
}
define('DB_PORT', $dbPort);

unset($_deploy_file, $_deploy, $dbHost, $dbUser, $dbPass, $dbName, $dbPort, $dbPortEnv);

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Check connection
if ($conn->connect_error) {
    error_log('Database connection failed: ' . $conn->connect_error);
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Database unavailable</title></head><body>';
    echo '<h1>Database unavailable</h1>';
    echo '<p>The application cannot connect to MySQL. On your hosting panel, confirm <strong>database hostname</strong>, ';
    echo 'database name, username, and password, then set them using environment variables ';
    echo '(<code>DB_HOST</code>, <code>DB_USER</code>, <code>DB_PASS</code>, <code>DB_NAME</code>) ';
    echo 'or add <code>config/database.deploy.php</code> (see <code>config/database.deploy.example.php</code>).</p>';
    echo '</body></html>';
    exit;
}

// Set charset to utf8mb4
$conn->set_charset('utf8mb4');

// Set timezone
date_default_timezone_set('Asia/Manila');

// Errors: log always; show in-browser only when APP_DEBUG=1 (avoid leaking paths on production)
$appDebug = getenv('APP_DEBUG');
$showPhpErrors = is_string($appDebug) && $appDebug === '1';
ini_set('display_errors', $showPhpErrors ? '1' : '0');
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

// Check session timeout (full-page app only — JSON handlers must not emit HTML redirects)
$scriptPath = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$isJsonEntryScript = stripos($scriptPath, '/handlers/') !== false
    || stripos($scriptPath, '/api/') !== false;

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
    if ($isJsonEntryScript) {
        $_SESSION['last_activity'] = time();
    } else {
        session_destroy();
        header('Location: login.php?session_expired=1');
        exit;
    }
}
$_SESSION['last_activity'] = time();

// Load helper functions
require_once __DIR__ . '/../functions/helpers.php';

ensure_users_first_last_name_schema($conn);
ensure_users_role_admin_employee_schema($conn);
ensure_users_google_oauth_schema($conn);
ensure_users_profile_picture_schema($conn);
?>
