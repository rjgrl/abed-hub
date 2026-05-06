<?php
/**
 * ABED IDM Hub - Helper Functions
 * Centralized utility functions for the entire system
 */

/**
 * Check if user is authenticated
 */
function isAuthenticated() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Redirect to login if not authenticated
 */
function requireLogin() {
    if (!isAuthenticated()) {
        header('Location: /abed-hub/login.php');
        exit;
    }
}

/**
 * Check user role
 */
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * Require specific role
 */
function requireRole($role) {
    if (!hasRole($role) && ($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        die(json_encode(['status' => 'error', 'message' => 'Insufficient permissions']));
    }
}

/**
 * Require any role from a whitelist.
 */
function requireRoles(array $roles) {
    $userRole = $_SESSION['role'] ?? '';
    if (!in_array($userRole, $roles, true)) {
        http_response_code(403);
        die(json_encode(['status' => 'error', 'message' => 'Insufficient permissions']));
    }
}

/**
 * Check if current user has super admin privileges.
 */
function isSuperAdmin() {
    return ($_SESSION['role'] ?? '') === 'admin';
}

/**
 * SQL fragment: projects that appear in the live catalog (maps, search, reports).
 * Non-admin registrations are stored with approval_status = 'Pending' until a Super Admin approves.
 */
function sqlProjectsCatalogApproved(string $tableAlias = ''): string {
    $prefix = $tableAlias !== '' ? $tableAlias . '.' : '';
    return "{$prefix}approval_status = 'Approved'";
}

/**
 * Route all users through one dashboard entry point.
 */
function getDashboardRoute() {
    return 'dashboard.php';
}

/**
 * Sanitize input for security
 */
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email format
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * First column of the first row (COUNT/SUM/etc.). On SQL failure logs and returns $default.
 */
function db_query_scalar(mysqli $conn, string $sql, $default = 0)
{
    $r = $conn->query($sql);
    if (!$r) {
        error_log('db_query_scalar: ' . $conn->error . ' | ' . $sql);
        return $default;
    }
    $row = $r->fetch_assoc();
    if (!$row) {
        return $default;
    }
    return reset($row);
}

/**
 * All rows as associative arrays. On SQL failure logs and returns [].
 */
function db_query_all_assoc(mysqli $conn, string $sql): array
{
    $r = $conn->query($sql);
    if (!$r) {
        error_log('db_query_all_assoc: ' . $conn->error . ' | ' . $sql);
        return [];
    }
    return $r->fetch_all(MYSQLI_ASSOC);
}

/**
 * Display name from separate first and last name fields.
 */
function user_display_name($first_name, $last_name) {
    $first = trim((string) $first_name);
    $last = trim((string) $last_name);
    if ($first === '' && $last === '') {
        return '';
    }
    return trim($first . ' ' . $last);
}

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

/**
 * Add google_sub for Google Sign-In (OpenID Connect).
 */
function ensure_users_google_oauth_schema(mysqli $conn): void
{
    if (!defined('DB_NAME')) {
        return;
    }
    $db = $conn->real_escape_string(DB_NAME);
    $r = $conn->query("SELECT COUNT(*) AS c FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = '{$db}' AND TABLE_NAME = 'users' AND COLUMN_NAME = 'google_sub'");
    if ($r && ($row = $r->fetch_assoc()) && (int) $row['c'] > 0) {
        return;
    }

    $conn->query("ALTER TABLE users ADD COLUMN google_sub VARCHAR(255) NULL DEFAULT NULL AFTER email");
    if ($conn->errno) {
        error_log('ensure_users_google_oauth_schema add google_sub failed: ' . $conn->error);
        return;
    }
    $conn->query('ALTER TABLE users ADD UNIQUE INDEX idx_users_google_sub (google_sub)');
}

/**
 * Ensure profile_picture column exists (used by Google avatar and My Account uploads).
 */
function ensure_users_profile_picture_schema(mysqli $conn): void
{
    if (!defined('DB_NAME')) {
        return;
    }
    $db = $conn->real_escape_string(DB_NAME);
    $r = $conn->query("SELECT COUNT(*) AS c FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = '{$db}' AND TABLE_NAME = 'users' AND COLUMN_NAME = 'profile_picture'");
    if ($r && ($row = $r->fetch_assoc()) && (int) $row['c'] > 0) {
        return;
    }
    $conn->query('ALTER TABLE users ADD COLUMN profile_picture VARCHAR(500) NULL AFTER office_unit');
}

/**
 * Two-letter avatar initials from first and last name.
 */
function user_initials($first_name, $last_name) {
    $f = trim((string) $first_name);
    $l = trim((string) $last_name);
    if ($f !== '' && $l !== '') {
        return strtoupper(substr($f, 0, 1) . substr($l, 0, 1));
    }
    if ($f !== '') {
        return strtoupper(substr($f, 0, 2));
    }
    if ($l !== '') {
        return strtoupper(substr($l, 0, 2));
    }
    return 'U';
}

/**
 * Migrate legacy users.full_name to first_name / last_name once; no-op when already migrated.
 */
function ensure_users_first_last_name_schema(mysqli $conn): void {
    if (!defined('DB_NAME')) {
        return;
    }
    $db = $conn->real_escape_string(DB_NAME);
    $q = "SELECT COUNT(*) AS c FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = '{$db}' AND TABLE_NAME = 'users' AND COLUMN_NAME = 'first_name'";
    $r = $conn->query($q);
    if ($r && ($row = $r->fetch_assoc()) && (int) $row['c'] > 0) {
        return;
    }

    $q2 = "SELECT COUNT(*) AS c FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = '{$db}' AND TABLE_NAME = 'users' AND COLUMN_NAME = 'full_name'";
    $r2 = $conn->query($q2);
    $has_full = $r2 && ($row2 = $r2->fetch_assoc()) && (int) $row2['c'] > 0;

    if ($has_full) {
        $conn->query("ALTER TABLE users ADD COLUMN first_name VARCHAR(255) NOT NULL DEFAULT '' AFTER email");
        $conn->query("ALTER TABLE users ADD COLUMN last_name VARCHAR(255) NOT NULL DEFAULT '' AFTER first_name");
        $conn->query("UPDATE users SET
            first_name = SUBSTRING_INDEX(TRIM(full_name), ' ', 1),
            last_name = TRIM(CASE WHEN LOCATE(' ', TRIM(full_name)) > 0
                THEN SUBSTRING(TRIM(full_name), LOCATE(' ', TRIM(full_name)) + 1)
                ELSE '' END)");
        $conn->query("ALTER TABLE users DROP COLUMN full_name");
        return;
    }

    $conn->query("ALTER TABLE users ADD COLUMN first_name VARCHAR(255) NOT NULL DEFAULT '' AFTER email");
    $conn->query("ALTER TABLE users ADD COLUMN last_name VARCHAR(255) NOT NULL DEFAULT '' AFTER first_name");
}

/**
 * Ensure users.role is ENUM('admin','employee') so signups can use role = employee.
 * Migrates legacy ENUM('admin','coordinator','operator','viewer') on first connect.
 */
function ensure_users_role_admin_employee_schema(mysqli $conn): void
{
    if (!defined('DB_NAME')) {
        return;
    }
    $db = $conn->real_escape_string(DB_NAME);
    $r = $conn->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = '{$db}' AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role' LIMIT 1");
    if (!$r || !($row = $r->fetch_assoc())) {
        return;
    }
    $colType = (string) ($row['COLUMN_TYPE'] ?? '');
    if (stripos($colType, 'enum(') === false) {
        return;
    }

    $hasEmployee = stripos($colType, "'employee'") !== false;
    $hasLegacy = stripos($colType, "'operator'") !== false
        || stripos($colType, "'viewer'") !== false
        || stripos($colType, "'coordinator'") !== false;

    if ($hasEmployee && !$hasLegacy) {
        return;
    }

    if (!$hasEmployee && $hasLegacy) {
        $conn->query("ALTER TABLE users MODIFY COLUMN role ENUM(
            'admin','coordinator','operator','viewer','employee'
        ) NOT NULL DEFAULT 'operator'");
        if ($conn->errno) {
            error_log('ensure_users_role_admin_employee_schema expand failed: ' . $conn->error);
            return;
        }
        $conn->query("UPDATE users SET role = 'employee' WHERE role IN ('coordinator','operator','viewer')");
        $conn->query("ALTER TABLE users MODIFY COLUMN role ENUM('admin','employee') NOT NULL DEFAULT 'employee'");
        if ($conn->errno) {
            error_log('ensure_users_role_admin_employee_schema shrink failed: ' . $conn->error);
        }
        return;
    }

    if ($hasEmployee && $hasLegacy) {
        $conn->query("UPDATE users SET role = 'employee' WHERE role IN ('coordinator','operator','viewer')");
        $conn->query("ALTER TABLE users MODIFY COLUMN role ENUM('admin','employee') NOT NULL DEFAULT 'employee'");
        if ($conn->errno) {
            error_log('ensure_users_role_admin_employee_schema finalize failed: ' . $conn->error);
        }
    }
}

/**
 * Log audit trail for tracking changes
 */
function logAudit($action, $projectType = null, $projectId = null, $oldValues = null, $newValues = null) {
    global $conn;
    
    if (!$conn) return false;
    
    $userId = $_SESSION['user_id'] ?? null;
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $oldValuesJson = $oldValues ? json_encode($oldValues) : null;
    $newValuesJson = $newValues ? json_encode($newValues) : null;
    
    // Guard against invalid FK values (e.g., user IDs passed as project_id).
    if (!is_null($projectId)) {
        $projectId = (int) $projectId;
        if ($projectId <= 0) {
            $projectId = null;
        } else {
            $projectCheck = $conn->prepare("SELECT id FROM projects WHERE id = ? LIMIT 1");
            if ($projectCheck) {
                $projectCheck->bind_param('i', $projectId);
                $projectCheck->execute();
                $exists = $projectCheck->get_result()->fetch_assoc();
                $projectCheck->close();
                if (!$exists) {
                    $projectId = null;
                }
            }
        }
    }

    $stmt = $conn->prepare("
        INSERT INTO audit_log (
            user_id, action, project_type, project_id, old_values, new_values, ip_address, user_agent
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    if (!$stmt) return false;
    
    $stmt->bind_param(
        "ississss",
        $userId, $action, $projectType, $projectId, $oldValuesJson, $newValuesJson, $ipAddress, $userAgent
    );
    
    return $stmt->execute();
}

/**
 * Get session user full name
 */
function getUserFullName() {
    return $_SESSION['full_name'] ?? 'User';
}

/**
 * Get session user ID
 */
function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get session user role
 */
function getUserRole() {
    return $_SESSION['role'] ?? 'employee';
}

/**
 * Format currency in Philippine Peso
 */
function formatPHP($amount) {
    return '₱' . number_format($amount, 2);
}

/**
 * Format date in readable format
 */
function formatDate($date) {
    if (!$date) return 'N/A';
    try {
        return date('M d, Y', strtotime($date));
    } catch (Exception $e) {
        return 'N/A';
    }
}

/**
 * Get status badge color
 */
function getStatusBadgeColor($status) {
    return match($status) {
        'Proposal', 'For Validation' => 'warning',
        'Pre-Implementation' => 'info',
        'Procurement' => 'secondary',
        'Implementation' => 'primary',
        'Completed', 'Delivered', 'Turned-Over' => 'success',
        'Not Feasible', 'Cancelled', 'Archived' => 'danger',
        'Operational' => 'success',
        'Non-operational', 'Intermittently Operational' => 'warning',
        default => 'secondary'
    };
}

/**
 * Generate CSRF token for forms
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Redirect to previous page or default
 */
function redirectBack($default = 'dashboard.php') {
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if ($referer && strpos($referer, $_SERVER['HTTP_HOST']) !== false) {
        header("Location: $referer");
    } else {
        header("Location: $default");
    }
    exit;
}

/**
 * Array of project types
 */
define('PROJECT_TYPES', ['FSPF', 'IDP', 'AFME']);
define('PROJECT_STAGES', ['Proposal', 'Pre-Implementation', 'Procurement', 'Implementation', 'Completed']);
define('PROPOSAL_STATUS', ['For Validation', 'Proposal Validated', 'Not Feasible', 'Archived', 'Cancelled']);
define('SCOPE_OF_WORK', ['Construction', 'Rehabilitation', 'Upgrading', 'Additional Work']);
define('USER_ROLES', ['admin', 'employee']);

/**
 * CSS classes for project lifecycle stage badges (see .stage-badge in assets/css/style.css).
 */
function stage_badge_class(string $stage): string
{
    $kind = match ($stage) {
        'Proposal' => 'stage-badge--proposal',
        'Pre-Implementation' => 'stage-badge--pre-implementation',
        'Procurement' => 'stage-badge--procurement',
        'Implementation' => 'stage-badge--implementation',
        'Completed', 'Turned-Over' => 'stage-badge--done',
        default => 'stage-badge--default',
    };

    return 'stage-badge ' . $kind;
}

/**
 * Variance pill: negative (physical behind financial) vs warning.
 */
function variance_badge_class(float $variance): string
{
    return 'variance-badge ' . ($variance < 0 ? 'variance-badge--negative' : 'variance-badge--warn');
}

/**
 * Financial record type color coding on project detail.
 */
function financial_record_badge_class(string $recordType): string
{
    $kind = match ($recordType) {
        'Obligation' => 'fin-record--obligation',
        'Disbursement' => 'fin-record--disbursement',
        'Liquidation' => 'fin-record--liquidation',
        default => 'fin-record--other',
    };

    return 'fin-record-badge ' . $kind;
}

?>
