<?php
require_once __DIR__ . '/common.php';

apiRequireAuth();

$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {
        case 'list':
            apiSuccess(listFinancialRecords(), 'Financial records retrieved');
        case 'summary':
            apiSuccess(getFinancialSummary(), 'Financial summary retrieved');
        case 'create':
            if ($method !== 'POST') {
                apiError('Method not allowed', 405);
            }
            apiSuccess(createFinancialRecord(), 'Financial record created', 201);
        case 'update':
            if (!in_array($method, ['PUT', 'POST'], true)) {
                apiError('Method not allowed', 405);
            }
            apiSuccess(updateFinancialRecord(), 'Financial record updated');
        case 'delete':
            if (!in_array($method, ['DELETE', 'POST'], true)) {
                apiError('Method not allowed', 405);
            }
            apiSuccess(archiveFinancialRecord(), 'Financial record archived');
        default:
            apiError('Invalid action', 400);
    }
} catch (Exception $e) {
    apiError($e->getMessage(), 400);
}

function loadProjectFinancial(mysqli $conn, int $projectId, ?string $projectType = null): array {
    if ($projectId <= 0) {
        throw new Exception('project_id is required');
    }
    if ($projectType !== null && $projectType !== '') {
        $type = apiValidateType($projectType);
        $stmt = $conn->prepare('SELECT id, project_type, financial_entries, proposed_amount, allocated_amount FROM projects WHERE id = ? AND project_type = ?');
        $stmt->bind_param('is', $projectId, $type);
    } else {
        $stmt = $conn->prepare('SELECT id, project_type, financial_entries, proposed_amount, allocated_amount FROM projects WHERE id = ?');
        $stmt->bind_param('i', $projectId);
    }
    $stmt->execute();
    $project = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$project) {
        throw new Exception('Project not found');
    }

    $entries = json_decode((string) ($project['financial_entries'] ?? '[]'), true);
    if (!is_array($entries)) {
        $entries = [];
    }
    return [$project, $entries];
}

function persistProjectFinancial(mysqli $conn, int $projectId, array $entries): void {
    $json = json_encode(array_values($entries));
    $stmt = $conn->prepare('UPDATE projects SET financial_entries = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    $stmt->bind_param('si', $json, $projectId);
    if (!$stmt->execute()) {
        throw new Exception('Failed to save financial entries');
    }
    $stmt->close();
}

function listFinancialRecords(): array {
    global $conn;
    $projectId = (int) ($_GET['project_id'] ?? 0);
    $projectType = $_GET['project_type'] ?? null;
    $status = trim((string) ($_GET['status'] ?? ''));
    $recordType = trim((string) ($_GET['record_type'] ?? ''));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = min(500, max(1, (int) ($_GET['limit'] ?? 100)));
    $offset = ($page - 1) * $limit;

    [$project, $entries] = loadProjectFinancial($conn, $projectId, $projectType);
    $filtered = [];
    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        if ($status !== '' && ($entry['record_status'] ?? '') !== $status) {
            continue;
        }
        if ($recordType !== '' && ($entry['record_type'] ?? '') !== $recordType) {
            continue;
        }
        $filtered[] = $entry;
    }
    usort($filtered, static fn($a, $b) => strcmp((string) ($b['record_date'] ?? ''), (string) ($a['record_date'] ?? '')));

    return [
        'project' => [
            'id' => (int) $project['id'],
            'project_type' => $project['project_type'],
        ],
        'records' => array_slice($filtered, $offset, $limit),
        'meta' => [
            'total' => count($filtered),
            'page' => $page,
            'limit' => $limit,
            'pages' => (int) ceil(max(1, count($filtered)) / $limit),
        ],
    ];
}

function getFinancialSummary(): array {
    global $conn;
    $projectId = (int) ($_GET['project_id'] ?? 0);
    $projectType = $_GET['project_type'] ?? null;
    [$project, $entries] = loadProjectFinancial($conn, $projectId, $projectType);

    $totals = [
        'Obligation' => 0.0,
        'Disbursement' => 0.0,
        'Liquidation' => 0.0,
    ];
    foreach ($entries as $entry) {
        if (!is_array($entry) || ($entry['record_status'] ?? 'Active') !== 'Active') {
            continue;
        }
        $rt = (string) ($entry['record_type'] ?? '');
        if (!isset($totals[$rt])) {
            continue;
        }
        $totals[$rt] += (float) ($entry['amount'] ?? 0);
    }

    $obligation = $totals['Obligation'];
    $disbursement = $totals['Disbursement'];
    $liquidation = $totals['Liquidation'];

    return [
        'project_id' => (int) $project['id'],
        'project_type' => $project['project_type'],
        'proposed_amount' => (float) ($project['proposed_amount'] ?? 0),
        'allocated_amount' => (float) ($project['allocated_amount'] ?? 0),
        'total_obligations' => $obligation,
        'total_disbursed' => $disbursement,
        'total_liquidated' => $liquidation,
        'disbursement_rate' => $obligation > 0 ? round(($disbursement / $obligation) * 100, 2) : 0.0,
        'liquidation_rate' => $disbursement > 0 ? round(($liquidation / $disbursement) * 100, 2) : 0.0,
    ];
}

function createFinancialRecord(): array {
    global $conn;
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $projectType = $_POST['project_type'] ?? null;
    $recordType = trim((string) ($_POST['record_type'] ?? ''));
    $amount = (float) ($_POST['amount'] ?? 0);
    $allowed = ['Obligation', 'Disbursement', 'Liquidation'];
    if (!in_array($recordType, $allowed, true)) {
        throw new Exception('record_type must be Obligation, Disbursement, or Liquidation');
    }
    if ($amount <= 0) {
        throw new Exception('amount must be positive');
    }

    [$project, $entries] = loadProjectFinancial($conn, $projectId, $projectType);
    $nextId = 1;
    foreach ($entries as $entry) {
        if (is_array($entry) && (int) ($entry['id'] ?? 0) >= $nextId) {
            $nextId = (int) $entry['id'] + 1;
        }
    }

    $new = [
        'id' => $nextId,
        'project_id' => (int) $project['id'],
        'project_type' => strtoupper($project['project_type']),
        'record_type' => $recordType,
        'amount' => $amount,
        'reference_number' => trim((string) ($_POST['reference_number'] ?? '')),
        'particulars' => trim((string) ($_POST['particulars'] ?? '')),
        'record_status' => 'Active',
        'record_date' => date('Y-m-d H:i:s'),
        'recorded_by' => (int) ($_SESSION['user_id'] ?? 0),
    ];
    $entries[] = $new;
    persistProjectFinancial($conn, (int) $project['id'], $entries);
    return ['record' => $new];
}

function updateFinancialRecord(): array {
    global $conn;
    $payload = apiInputJson();
    if (empty($payload)) {
        $payload = $_POST;
    }
    $projectId = (int) ($_GET['project_id'] ?? $payload['project_id'] ?? 0);
    $projectType = $_GET['project_type'] ?? $payload['project_type'] ?? null;
    $id = (int) ($_GET['id'] ?? $payload['id'] ?? 0);
    if ($id <= 0) {
        throw new Exception('Record ID required');
    }

    [$project, $entries] = loadProjectFinancial($conn, $projectId, $projectType);
    $found = false;
    foreach ($entries as &$entry) {
        if (!is_array($entry) || (int) ($entry['id'] ?? 0) !== $id) {
            continue;
        }
        $found = true;
        foreach (['amount', 'reference_number', 'particulars', 'record_status', 'record_type'] as $field) {
            if (array_key_exists($field, $payload)) {
                $entry[$field] = $payload[$field];
            }
        }
        break;
    }
    unset($entry);
    if (!$found) {
        throw new Exception('Record not found');
    }

    persistProjectFinancial($conn, (int) $project['id'], $entries);
    return ['record_id' => $id];
}

function archiveFinancialRecord(): array {
    global $conn;
    $payload = apiInputJson();
    $projectId = (int) ($_GET['project_id'] ?? $payload['project_id'] ?? 0);
    $projectType = $_GET['project_type'] ?? $payload['project_type'] ?? null;
    $id = (int) ($_GET['id'] ?? $payload['id'] ?? 0);
    if ($id <= 0) {
        throw new Exception('Record ID required');
    }

    [$project, $entries] = loadProjectFinancial($conn, $projectId, $projectType);
    $found = false;
    foreach ($entries as &$entry) {
        if (is_array($entry) && (int) ($entry['id'] ?? 0) === $id) {
            $entry['record_status'] = 'Archived';
            $found = true;
            break;
        }
    }
    unset($entry);
    if (!$found) {
        throw new Exception('Record not found');
    }

    persistProjectFinancial($conn, (int) $project['id'], $entries);
    return ['record_id' => $id];
}
