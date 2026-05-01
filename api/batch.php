<?php
/**
 * Batch Operations API
 *
 * All actions require the admin or coordinator role.
 *
 * Supported actions (via ?action=):
 *   update-stage      POST   Update current_stage for a list of projects.
 *   update-progress   POST   Update physical/financial progress for a list.
 *   update-field      POST   Update a single whitelisted field for a list.
 *   bulk-update       POST   Update multiple fields at once (used by the project grid).
 *   delete-projects   POST   Soft-archive a list of projects.
 *   export            POST   Return project data as JSON or CSV.
 */

require_once __DIR__ . '/common.php';

apiRequireRoles(['admin', 'coordinator']);

$action = $_GET['action'] ?? null;
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method !== 'POST') {
        apiError('Method not allowed', 405);
    }

    switch ($action) {
        case 'update-stage':
            apiSuccess(batchUpdateStage(), 'Batch stage update complete');

        case 'update-progress':
            apiSuccess(batchUpdateProgress(), 'Batch progress update complete');

        case 'update-field':
            apiSuccess(batchUpdateField(), 'Batch field update complete');

        case 'bulk-update':
            apiSuccess(batchBulkUpdate(), 'Bulk update complete');

        case 'delete-projects':
            apiSuccess(batchDeleteProjects(), 'Batch archive complete');

        case 'export':
            apiSuccess(batchExport(), 'Export complete');

        default:
            apiError('Invalid action', 400);
    }
} catch (Exception $e) {
    apiError($e->getMessage(), 400);
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

/** Resolve type → table, throw on invalid. */
function batchTable(string $type): string {
    return apiTableFor($type);
}

// ─── Handlers ────────────────────────────────────────────────────────────────

function batchUpdateStage(): array {
    global $conn;

    $data = apiInputJson();
    if (!isset($data['projects']) || !is_array($data['projects'])) {
        throw new Exception('projects array required');
    }
    if (empty($data['stage'])) {
        throw new Exception('stage parameter required');
    }

    $table    = batchTable($data['type'] ?? '');
    $stage    = $data['stage'];
    $projects = $data['projects'];

    $valid = ['Proposal','Pre-Implementation','Procurement','Implementation','Completed','Turned-Over'];
    if (!in_array($stage, $valid)) {
        throw new Exception('Invalid stage value');
    }

    $updated = 0;
    $errors  = [];

    foreach ($projects as $id) {
        $id   = (int) $id;
        $stmt = $conn->prepare("UPDATE $table SET current_stage = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('si', $stage, $id);
        $stmt->execute() ? $updated++ : $errors[] = "Failed to update project $id";
    }

    logAudit("BATCH_UPDATE_STAGE:$stage", $data['type'] ?? null);

    return ['updated' => $updated, 'total' => count($projects), 'errors' => $errors];
}

function batchUpdateProgress(): array {
    global $conn;

    $data = apiInputJson();
    if (!isset($data['projects']) || !is_array($data['projects'])) {
        throw new Exception('projects array required');
    }

    $table    = batchTable($data['type'] ?? '');
    $physical  = isset($data['physical_progress'])  ? (float) $data['physical_progress']  : null;
    $financial = isset($data['financial_progress']) ? (float) $data['financial_progress'] : null;

    if ($physical === null && $financial === null) {
        throw new Exception('At least one progress value required');
    }
    if ($physical !== null && ($physical < 0 || $physical > 100)) {
        throw new Exception('physical_progress must be 0–100');
    }
    if ($financial !== null && ($financial < 0 || $financial > 100)) {
        throw new Exception('financial_progress must be 0–100');
    }

    $updated = 0;
    $errors  = [];

    foreach ($data['projects'] as $id) {
        $id      = (int) $id;
        $sets    = [];
        $params  = [];
        $types   = '';

        if ($physical !== null) {
            $sets[]  = 'physical_progress = ?';
            $params[] = $physical;
            $types   .= 'd';
        }
        if ($financial !== null) {
            $sets[]  = 'financial_progress = ?';
            $params[] = $financial;
            $types   .= 'd';
        }
        $sets[]  = 'updated_at = NOW()';
        $params[] = $id;
        $types   .= 'i';

        $stmt = $conn->prepare('UPDATE ' . $table . ' SET ' . implode(', ', $sets) . ' WHERE id = ?');
        $stmt->bind_param($types, ...$params);
        $stmt->execute() ? $updated++ : $errors[] = "Failed to update project $id";
    }

    logAudit('BATCH_UPDATE_PROGRESS', $data['type'] ?? null);

    return ['updated' => $updated, 'total' => count($data['projects']), 'errors' => $errors];
}

function batchUpdateField(): array {
    global $conn;

    $data = apiInputJson();
    if (!isset($data['projects']) || !is_array($data['projects'])) {
        throw new Exception('projects array required');
    }
    if (!isset($data['field']) || !isset($data['value'])) {
        throw new Exception('field and value required');
    }

    $table   = batchTable($data['type'] ?? '');
    $field   = $data['field'];
    $value   = $data['value'];
    $allowed = ['allocated_amount', 'proposed_amount', 'commodity', 'description'];

    if (!in_array($field, $allowed)) {
        throw new Exception("Cannot update field '$field'");
    }

    $updated = 0;
    $errors  = [];

    foreach ($data['projects'] as $id) {
        $id   = (int) $id;
        $stmt = $conn->prepare("UPDATE $table SET $field = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('si', $value, $id);
        $stmt->execute() ? $updated++ : $errors[] = "Failed to update project $id";
    }

    logAudit("BATCH_UPDATE_FIELD:$field", $data['type'] ?? null);

    return ['updated' => $updated, 'total' => count($data['projects']), 'errors' => $errors];
}

/**
 * Bulk-update: accepts `{ project_type, ids: [], updates: {field: value, ...} }`.
 * Mirrors the old api/batch-operations.php update action.
 */
function batchBulkUpdate(): array {
    global $conn;

    $data = apiInputJson();
    $ids     = $data['ids'] ?? [];
    $updates = $data['updates'] ?? [];

    if (empty($ids) || !is_array($ids)) {
        throw new Exception('ids array required');
    }
    if (empty($updates)) {
        throw new Exception('updates object required');
    }

    $table = batchTable($data['project_type'] ?? '');

    $allowed = [
        'projects' => ['project_code','title','allocated_amount','physical_progress','financial_progress','current_stage','status'],
    ];

    $filtered = [];
    foreach ($updates as $f => $v) {
        if (in_array($f, $allowed[$table] ?? [])) {
            $filtered[$f] = $v;
        }
    }
    if (empty($filtered)) {
        throw new Exception('No valid fields to update');
    }

    $sets   = [];
    $params = [];
    $types  = '';
    foreach ($filtered as $f => $v) {
        $sets[]  = "$f = ?";
        $params[] = $v;
        $types   .= is_numeric($v) ? 'd' : 's';
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare(
        'UPDATE ' . $table . ' SET ' . implode(', ', $sets) . ', updated_at = CURRENT_TIMESTAMP WHERE id IN (' . $placeholders . ')'
    );
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    $allParams = array_merge($params, array_map('intval', $ids));
    $stmt->bind_param($types . str_repeat('i', count($ids)), ...$allParams);

    if (!$stmt->execute()) {
        throw new Exception('Update failed: ' . $stmt->error);
    }
    $updated = $stmt->affected_rows;
    $stmt->close();

    logAudit('BATCH_BULK_UPDATE', $data['project_type'] ?? null);

    return ['success' => true, 'updated_count' => $updated];
}

/**
 * Soft-archive projects. Accepts `{ project_type, ids: [] }` (legacy) or `{ type, projects: [] }`.
 */
function batchDeleteProjects(): array {
    global $conn;

    if (($_SESSION['role'] ?? '') !== 'admin') {
        throw new Exception('Only admins can delete projects');
    }

    $data     = apiInputJson();
    $ids      = $data['ids'] ?? $data['projects'] ?? [];
    $typeRaw  = $data['project_type'] ?? $data['type'] ?? '';
    $password = (string) ($data['password'] ?? '');

    if (empty($ids) || !is_array($ids)) {
        throw new Exception('ids (or projects) array required');
    }
    if ($password === '') {
        throw new Exception('Password is required to delete projects');
    }

    $table = batchTable($typeRaw);
    batchAssertDeletePassword($password);

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare(
        "UPDATE $table SET status = 'Archived', updated_at = CURRENT_TIMESTAMP WHERE id IN ($placeholders)"
    );
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    $intIds = array_map('intval', $ids);
    $stmt->bind_param(str_repeat('i', count($intIds)), ...$intIds);

    if (!$stmt->execute()) {
        throw new Exception('Archive failed: ' . $stmt->error);
    }
    $deleted = $stmt->affected_rows;
    $stmt->close();

    logAudit('BATCH_ARCHIVE', $typeRaw);

    return ['success' => true, 'deleted_count' => $deleted];
}

/**
 * Verify the current logged-in user's password before delete operations.
 */
function batchAssertDeletePassword(string $password): void {
    global $conn;

    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        throw new Exception('Unauthorized');
    }

    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
    if (!$stmt) {
        throw new Exception('Failed to validate credentials');
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || !isset($row['password']) || !password_verify($password, $row['password'])) {
        throw new Exception('Invalid password');
    }
}

function batchExport(): array {
    global $conn;

    $data     = apiInputJson();
    $ids      = $data['ids'] ?? $data['projects'] ?? [];
    $typeRaw  = $data['project_type'] ?? $data['type'] ?? '';
    $format   = $data['format'] ?? 'json';

    if (empty($ids) || !is_array($ids)) {
        throw new Exception('ids (or projects) array required');
    }

    $table = batchTable($typeRaw);

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare("SELECT * FROM $table WHERE id IN ($placeholders)");
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    $intIds = array_map('intval', $ids);
    $stmt->bind_param(str_repeat('i', count($intIds)), ...$intIds);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if ($format === 'csv') {
        $csv = [];
        if (!empty($rows)) {
            $csv[] = array_keys($rows[0]);
            foreach ($rows as $row) {
                $csv[] = array_values($row);
            }
        }
        return ['format' => 'csv', 'data' => $csv, 'count' => count($rows)];
    }

    return ['format' => 'json', 'data' => $rows, 'count' => count($rows)];
}
