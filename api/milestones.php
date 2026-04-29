<?php
require_once __DIR__ . '/common.php';

apiRequireAuth();

$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {
        case 'create':
            if ($method !== 'POST') {
                apiError('Method not allowed', 405);
            }
            apiSuccess(createMilestone(), 'Milestone created', 201);
        case 'update':
            if (!in_array($method, ['POST', 'PUT'], true)) {
                apiError('Method not allowed', 405);
            }
            apiSuccess(updateMilestone(), 'Milestone updated');
        case 'list':
            apiSuccess(listMilestones(), 'Milestones retrieved');
        case 'get':
            apiSuccess(getMilestone(), 'Milestone retrieved');
        case 'delete':
            if (!in_array($method, ['DELETE', 'POST'], true)) {
                apiError('Method not allowed', 405);
            }
            apiSuccess(deleteMilestone(), 'Milestone deleted');
        default:
            apiError('Invalid action', 400);
    }
} catch (Exception $e) {
    apiError($e->getMessage(), 400);
}

function loadProjectMilestones(mysqli $conn, int $projectId, ?string $projectType = null): array {
    if ($projectId <= 0) {
        throw new Exception('project_id is required');
    }
    if ($projectType !== null && $projectType !== '') {
        $type = apiValidateType($projectType);
        $stmt = $conn->prepare('SELECT id, project_type, milestones FROM projects WHERE id = ? AND project_type = ?');
        $stmt->bind_param('is', $projectId, $type);
    } else {
        $stmt = $conn->prepare('SELECT id, project_type, milestones FROM projects WHERE id = ?');
        $stmt->bind_param('i', $projectId);
    }
    $stmt->execute();
    $project = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$project) {
        throw new Exception('Project not found');
    }
    $milestones = json_decode((string) ($project['milestones'] ?? '[]'), true);
    if (!is_array($milestones)) {
        $milestones = [];
    }
    return [$project, $milestones];
}

function persistProjectMilestones(mysqli $conn, int $projectId, array $milestones): void {
    $json = json_encode(array_values($milestones));
    $stmt = $conn->prepare('UPDATE projects SET milestones = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    $stmt->bind_param('si', $json, $projectId);
    if (!$stmt->execute()) {
        throw new Exception('Failed to save milestones');
    }
    $stmt->close();
}

function createMilestone(): array {
    global $conn;
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $projectType = $_POST['project_type'] ?? null;
    $name = trim((string) ($_POST['milestone_name'] ?? ''));
    $stage = trim((string) ($_POST['stage'] ?? ''));
    $targetDate = $_POST['target_date'] ?? null;
    $remarks = trim((string) ($_POST['remarks'] ?? $_POST['description'] ?? ''));

    if ($name === '' || $stage === '') {
        throw new Exception('milestone_name and stage are required');
    }

    [$project, $milestones] = loadProjectMilestones($conn, $projectId, $projectType);
    $nextId = 1;
    foreach ($milestones as $m) {
        if (is_array($m) && (int) ($m['id'] ?? 0) >= $nextId) {
            $nextId = (int) $m['id'] + 1;
        }
    }

    $milestone = [
        'id' => $nextId,
        'project_id' => (int) $project['id'],
        'project_type' => $project['project_type'],
        'milestone_name' => $name,
        'stage' => $stage,
        'target_date' => $targetDate,
        'actual_date' => null,
        'remarks' => $remarks,
        'status' => 'Pending',
        'progress_percentage' => 0,
        'created_by' => (int) ($_SESSION['user_id'] ?? 0),
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => null,
    ];
    $milestones[] = $milestone;
    persistProjectMilestones($conn, (int) $project['id'], $milestones);

    return ['milestone' => $milestone];
}

function updateMilestone(): array {
    global $conn;
    $payload = $methodPayload = apiInputJson();
    if (empty($payload)) {
        $payload = $_POST;
    }
    $projectId = (int) ($_GET['project_id'] ?? $payload['project_id'] ?? 0);
    $projectType = $_GET['project_type'] ?? $payload['project_type'] ?? null;
    $id = (int) ($_GET['id'] ?? $payload['id'] ?? 0);
    if ($id <= 0) {
        throw new Exception('Milestone ID required');
    }

    [$project, $milestones] = loadProjectMilestones($conn, $projectId, $projectType);
    $found = false;
    foreach ($milestones as &$m) {
        if (!is_array($m) || (int) ($m['id'] ?? 0) !== $id) {
            continue;
        }
        $found = true;
        foreach (['milestone_name', 'stage', 'target_date', 'actual_date', 'remarks', 'status', 'progress_percentage'] as $f) {
            if (array_key_exists($f, $payload)) {
                $m[$f] = $payload[$f];
            }
        }
        $m['updated_at'] = date('Y-m-d H:i:s');
        break;
    }
    unset($m);
    if (!$found) {
        throw new Exception('Milestone not found');
    }

    persistProjectMilestones($conn, (int) $project['id'], $milestones);
    return ['id' => $id];
}

function listMilestones(): array {
    global $conn;
    $projectId = (int) ($_GET['project_id'] ?? 0);
    $projectType = $_GET['project_type'] ?? null;
    [, $milestones] = loadProjectMilestones($conn, $projectId, $projectType);
    usort($milestones, static fn($a, $b) => strcmp((string) ($a['target_date'] ?? ''), (string) ($b['target_date'] ?? '')));
    return $milestones;
}

function getMilestone(): array {
    global $conn;
    $projectId = (int) ($_GET['project_id'] ?? 0);
    $projectType = $_GET['project_type'] ?? null;
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        throw new Exception('Milestone ID required');
    }
    [, $milestones] = loadProjectMilestones($conn, $projectId, $projectType);
    foreach ($milestones as $m) {
        if (is_array($m) && (int) ($m['id'] ?? 0) === $id) {
            return $m;
        }
    }
    throw new Exception('Milestone not found');
}

function deleteMilestone(): array {
    global $conn;
    $payload = apiInputJson();
    $projectId = (int) ($_GET['project_id'] ?? $payload['project_id'] ?? 0);
    $projectType = $_GET['project_type'] ?? $payload['project_type'] ?? null;
    $id = (int) ($_GET['id'] ?? $payload['id'] ?? 0);
    if ($id <= 0) {
        throw new Exception('Milestone ID required');
    }
    [$project, $milestones] = loadProjectMilestones($conn, $projectId, $projectType);
    $before = count($milestones);
    $milestones = array_values(array_filter($milestones, static fn($m) => !is_array($m) || (int) ($m['id'] ?? 0) !== $id));
    if (count($milestones) === $before) {
        throw new Exception('Milestone not found');
    }
    persistProjectMilestones($conn, (int) $project['id'], $milestones);
    return ['deleted_id' => $id];
}
