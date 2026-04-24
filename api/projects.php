<?php
/**
 * Projects API — CRUD for FSPF, IDP, and AFME projects.
 *
 * Every response follows the envelope defined in api/common.php:
 *   { "status", "message", "data", "meta" }
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

require_once __DIR__ . '/common.php';

apiRequireAuth();

$method       = $_SERVER['REQUEST_METHOD'];
$action       = $_GET['action'] ?? null;
$project_type = isset($_GET['type']) ? strtolower($_GET['type']) : null;
$project_id   = isset($_GET['id']) ? intval($_GET['id']) : null;

try {
    switch ($method) {
        case 'GET':
            if ($action === 'list') {
                listProjects($conn, $project_type);
            } elseif ($action === 'detail' && $project_id) {
                getProjectDetail($conn, $project_type, $project_id);
            } elseif ($action === 'duplicates') {
                detectDuplicates($conn, $project_type);
            } else {
                apiError('Invalid action', 400);
            }
            break;

        case 'POST':
            if ($action === 'create') {
                createProject($conn, $project_type, $_POST, $_FILES ?? []);
            } elseif ($action === 'update-progress') {
                updateProgress($conn, $project_type, $project_id, $_POST);
            } else {
                apiError('Invalid action', 400);
            }
            break;

        case 'PUT':
            $data = apiInputJson();
            if ($action === 'update' && $project_id) {
                updateProject($conn, $project_type, $project_id, $data);
            } elseif ($action === 'stage' && $project_id) {
                updateProjectStage($conn, $project_type, $project_id, $data);
            } else {
                apiError('Invalid action', 400);
            }
            break;

        case 'DELETE':
            if ($project_id) {
                archiveProject($conn, $project_type, $project_id);
            } else {
                apiError('Project ID required', 400);
            }
            break;

        case 'OPTIONS':
            apiSuccess(null, 'Preflight OK');

        default:
            apiError('Method not allowed', 405);
    }
} catch (Exception $e) {
    apiError('Server error: ' . $e->getMessage(), 500);
}

// ─── Handler functions ───────────────────────────────────────────────────────

function listProjects(mysqli $conn, ?string $project_type): never {
    $table   = apiTableFor($project_type ?? '');
    $filters = [];
    $params  = [];
    $types   = '';

    if (isset($_GET['year'])) {
        $filters[] = 'funding_year = ?';
        $params[]  = intval($_GET['year']);
        $types    .= 'i';
    }
    if (isset($_GET['status'])) {
        $filters[] = 'proposal_status = ?';
        $params[]  = $_GET['status'];
        $types    .= 's';
    }
    if (isset($_GET['stage'])) {
        $filters[] = 'current_stage = ?';
        $params[]  = $_GET['stage'];
        $types    .= 's';
    }
    if (isset($_GET['location'])) {
        $filters[] = 'municipality LIKE ?';
        $params[]  = '%' . $_GET['location'] . '%';
        $types    .= 's';
    }

    $page  = max(1, intval($_GET['page'] ?? 1));
    $limit = min(200, max(1, intval($_GET['limit'] ?? 100)));
    $offset = ($page - 1) * $limit;

    $where = !empty($filters) ? 'WHERE ' . implode(' AND ', $filters) : '';

    $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM $table $where");
    if ($countStmt) {
        if (!empty($params)) {
            $countStmt->bind_param($types, ...$params);
        }
        $countStmt->execute();
        $total = (int) $countStmt->get_result()->fetch_assoc()['total'];
        $countStmt->close();
    } else {
        apiError('Database error: ' . $conn->error, 500);
    }

    $stmt = $conn->prepare("SELECT * FROM $table $where ORDER BY created_at DESC LIMIT ? OFFSET ?");
    if (!$stmt) {
        apiError('Database error: ' . $conn->error, 500);
    }

    $allParams  = array_merge($params, [$limit, $offset]);
    $allTypes   = $types . 'ii';
    $stmt->bind_param($allTypes, ...$allParams);
    $stmt->execute();
    $projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    apiPaginated($projects, $total, $page, $limit);
}

function getProjectDetail(mysqli $conn, ?string $project_type, int $project_id): never {
    $table = apiTableFor($project_type ?? '');
    $stmt  = $conn->prepare("SELECT * FROM $table WHERE id = ?");
    if (!$stmt) {
        apiError('Database error: ' . $conn->error, 500);
    }
    $stmt->bind_param('i', $project_id);
    $stmt->execute();
    $project = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$project) {
        apiError('Project not found', 404);
    }
    apiSuccess($project, 'Project retrieved');
}

function createProject(mysqli $conn, ?string $project_type, array $data, array $files): never {
    $table = apiTableFor($project_type ?? '');

    $required = ['project_code', 'project_title', 'funding_year'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            apiError("Missing required field: $field", 400);
        }
    }

    $fields       = array_keys($data);
    $fields[]     = 'created_by';
    $placeholders = array_fill(0, count($fields), '?');
    $values       = array_values($data);
    $values[]     = $_SESSION['user_id'];

    $query = 'INSERT INTO ' . $table . ' (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt  = $conn->prepare($query);
    if (!$stmt) {
        apiError('Database error: ' . $conn->error, 500);
    }

    $bindTypes = str_repeat('s', count($values) - 1) . 'i';
    $stmt->bind_param($bindTypes, ...$values);

    if (!$stmt->execute()) {
        apiError('Failed to create project: ' . $stmt->error, 500);
    }
    $new_id = $stmt->insert_id;
    $stmt->close();

    apiSuccess(['project_id' => $new_id], 'Project created successfully', 201);
}

function updateProject(mysqli $conn, ?string $project_type, int $project_id, array $data): never {
    $table   = apiTableFor($project_type ?? '');
    if (empty($data)) {
        apiError('No fields provided for update', 400);
    }

    $updates = [];
    $params  = [];
    foreach ($data as $key => $value) {
        $updates[] = "$key = ?";
        $params[]  = $value;
    }
    $params[] = $project_id;

    $stmt = $conn->prepare('UPDATE ' . $table . ' SET ' . implode(', ', $updates) . ' WHERE id = ?');
    if (!$stmt) {
        apiError('Database error: ' . $conn->error, 500);
    }

    $bindTypes = str_repeat('s', count($params) - 1) . 'i';
    $stmt->bind_param($bindTypes, ...$params);

    if (!$stmt->execute()) {
        apiError('Failed to update project: ' . $stmt->error, 500);
    }
    $stmt->close();

    apiSuccess(['project_id' => $project_id], 'Project updated successfully');
}

function updateProjectStage(mysqli $conn, ?string $project_type, int $project_id, array $data): never {
    $table = apiTableFor($project_type ?? '');

    if (empty($data['stage'])) {
        apiError('Stage is required', 400);
    }

    $stmt = $conn->prepare("UPDATE $table SET current_stage = ? WHERE id = ?");
    if (!$stmt) {
        apiError('Database error: ' . $conn->error, 500);
    }
    $stmt->bind_param('si', $data['stage'], $project_id);

    if (!$stmt->execute()) {
        apiError('Failed to update stage: ' . $stmt->error, 500);
    }
    $stmt->close();

    apiSuccess(['project_id' => $project_id, 'stage' => $data['stage']], 'Stage updated successfully');
}

function updateProgress(mysqli $conn, ?string $project_type, ?int $project_id, array $data): never {
    if (!in_array($project_type, ['fspf', 'idp'], true)) {
        apiError('Progress tracking is only available for FSPF and IDP projects', 400);
    }
    if (!$project_id) {
        apiError('Project ID required', 400);
    }

    $table    = apiTableFor($project_type);
    $physical  = isset($data['physical_progress']) ? intval($data['physical_progress']) : 0;
    $financial = isset($data['financial_progress']) ? intval($data['financial_progress']) : 0;

    $stmt = $conn->prepare("UPDATE $table SET physical_progress = ?, financial_progress = ? WHERE id = ?");
    if (!$stmt) {
        apiError('Database error: ' . $conn->error, 500);
    }
    $stmt->bind_param('iii', $physical, $financial, $project_id);

    if (!$stmt->execute()) {
        apiError('Failed to update progress: ' . $stmt->error, 500);
    }
    $stmt->close();

    apiSuccess(['project_id' => $project_id, 'physical_progress' => $physical, 'financial_progress' => $financial], 'Progress updated');
}

function archiveProject(mysqli $conn, ?string $project_type, int $project_id): never {
    $table = apiTableFor($project_type ?? '');

    $stmt = $conn->prepare("UPDATE $table SET proposal_status = 'Archived' WHERE id = ?");
    if (!$stmt) {
        apiError('Database error: ' . $conn->error, 500);
    }
    $stmt->bind_param('i', $project_id);

    if (!$stmt->execute()) {
        apiError('Failed to archive project: ' . $stmt->error, 500);
    }
    $stmt->close();

    logAudit('ARCHIVE_PROJECT', $project_type, $project_id);
    apiSuccess(['project_id' => $project_id], 'Project archived');
}

function detectDuplicates(mysqli $conn, ?string $project_type): never {
    $table       = apiTableFor($project_type ?? '');
    $search_term = $_GET['search'] ?? '';

    if (strlen($search_term) < 3) {
        apiError('Search term must be at least 3 characters', 400);
    }

    $stmt = $conn->prepare(
        "SELECT id, project_code, project_title, municipality, created_at
         FROM $table
         WHERE (project_title LIKE ? OR project_code LIKE ?)
         ORDER BY created_at DESC
         LIMIT 10"
    );
    if (!$stmt) {
        apiError('Database error: ' . $conn->error, 500);
    }

    $pattern = '%' . $search_term . '%';
    $stmt->bind_param('ss', $pattern, $pattern);
    $stmt->execute();
    $duplicates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    apiSuccess($duplicates, 'Duplicate search complete', 200, ['count' => count($duplicates)]);
}
