<?php
/**
 * Shared API bootstrap — load once at the top of every api/*.php file.
 *
 * Provides: auth guards, a guaranteed response envelope, pagination helper,
 * and the project-type → table resolver used across all API handlers.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../functions/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// ─── Auth guards ─────────────────────────────────────────────────────────────

function apiRequireAuth(): void {
    if (!isset($_SESSION['user_id'])) {
        apiError('Unauthorized', 401);
    }
}

function apiRequireRoles(array $roles): void {
    apiRequireAuth();
    if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
        apiError('Insufficient permissions', 403);
    }
}

// ─── Response envelope ───────────────────────────────────────────────────────

/**
 * Send a successful JSON response.
 *
 * Always produces:
 *   { "status": "success", "message": "...", "data": <mixed>, "meta": <array|null> }
 */
function apiSuccess(mixed $data = null, string $message = 'OK', int $statusCode = 200, ?array $meta = null): never {
    http_response_code($statusCode);
    echo json_encode([
        'status'  => 'success',
        'message' => $message,
        'data'    => $data,
        'meta'    => $meta,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send an error JSON response.
 *
 * Always produces:
 *   { "status": "error", "message": "...", "data": null, "meta": <array|null> }
 */
function apiError(string $message, int $statusCode = 400, ?array $meta = null): never {
    http_response_code($statusCode);
    echo json_encode([
        'status'  => 'error',
        'message' => $message,
        'data'    => null,
        'meta'    => $meta,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send a paginated list response.
 *
 * Always produces:
 *   { "status": "success", "message": "...", "data": [...],
 *     "meta": { "total": int, "page": int, "limit": int, "pages": int } }
 */
function apiPaginated(array $items, int $total, int $page, int $limit, string $message = 'OK'): never {
    apiSuccess($items, $message, 200, [
        'total' => $total,
        'page'  => $page,
        'limit' => $limit,
        'pages' => $limit > 0 ? (int) ceil($total / $limit) : 1,
    ]);
}

// ─── Request helpers ─────────────────────────────────────────────────────────

/** Decode JSON request body; returns empty array if body is absent or invalid. */
function apiInputJson(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

// ─── Project-type helpers ─────────────────────────────────────────────────────

/** Canonical project types accepted by all API endpoints. */
const PROJECT_TYPE_TABLE_MAP = [
    'fspf' => 'projects',
    'idp'  => 'projects',
    'afme' => 'projects',
];

/**
 * Resolve a project-type string to its database table name.
 * Calls apiError(400) immediately if the type is invalid.
 */
function apiTableFor(string $type): string {
    $type = strtolower(trim($type));
    if (!array_key_exists($type, PROJECT_TYPE_TABLE_MAP)) {
        apiError('Invalid project type. Must be one of: fspf, idp, afme', 400);
    }
    return PROJECT_TYPE_TABLE_MAP[$type];
}

/**
 * Return the sanitised project type or call apiError(400).
 * Use when you need both the validated type string AND its table name.
 */
function apiValidateType(?string $raw): string {
    if ($raw === null || $raw === '') {
        apiError('Project type is required', 400);
    }
    $type = strtolower(trim($raw));
    if (!array_key_exists($type, PROJECT_TYPE_TABLE_MAP)) {
        apiError('Invalid project type. Must be one of: fspf, idp, afme', 400);
    }
    return $type;
}
