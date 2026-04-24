<?php
/**
 * Financial Records API
 *
 * Uses the normalized `project_financial_entries` table.
 * Accepts project_type (fspf|idp|afme) + project_id instead of
 * the old three-nullable-FK pattern.
 */

require_once __DIR__ . '/common.php';

apiRequireAuth();

$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {
        case 'list':
            apiSuccess(listFinancialRecords(), 'OK');

        case 'summary':
            apiSuccess(getFinancialSummary(), 'OK');

        case 'create':
            if ($method !== 'POST') {
                apiError('Method not allowed', 405);
            }
            apiSuccess(createFinancialRecord(), 'Financial record created', 201);

        case 'update':
            if ($method !== 'PUT') {
                apiError('Method not allowed', 405);
            }
            apiSuccess(updateFinancialRecord(), 'Financial record updated');

        case 'delete':
            if ($method !== 'DELETE') {
                apiError('Method not allowed', 405);
            }
            apiSuccess(archiveFinancialRecord(), 'Financial record archived');

        default:
            apiError('Invalid action', 400);
    }
} catch (Exception $e) {
    apiError($e->getMessage(), 400);
}

// ─── Handler functions ────────────────────────────────────────────────────────

function listFinancialRecords(): array {
    global $conn;

    $project_type = strtoupper($_GET['project_type'] ?? '');
    $project_id   = isset($_GET['project_id']) ? intval($_GET['project_id']) : null;
    $status       = $_GET['status'] ?? null;
    $limit        = min(intval($_GET['limit'] ?? 100), 500);
    $page         = max(1, intval($_GET['page'] ?? 1));
    $offset       = ($page - 1) * $limit;

    $where  = ['1=1'];
    $params = [];
    $types  = '';

    if ($project_type && in_array($project_type, ['FSPF', 'IDP', 'AFME'])) {
        $where[]  = 'project_type = ?';
        $params[] = $project_type;
        $types   .= 's';
    }
    if ($project_id) {
        $where[]  = 'project_id = ?';
        $params[] = $project_id;
        $types   .= 'i';
    }
    if ($status) {
        $where[]  = 'record_status = ?';
        $params[] = $status;
        $types   .= 's';
    }

    $whereClause = implode(' AND ', $where);

    $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM project_financial_entries WHERE $whereClause");
    if (!$countStmt) {
        throw new Exception('Query prepare failed: ' . $conn->error);
    }
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $total = (int) $countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();

    $stmt = $conn->prepare(
        "SELECT * FROM project_financial_entries WHERE $whereClause ORDER BY record_date DESC LIMIT ? OFFSET ?"
    );
    if (!$stmt) {
        throw new Exception('Query prepare failed: ' . $conn->error);
    }
    $allParams = array_merge($params, [$limit, $offset]);
    $allTypes  = $types . 'ii';
    $stmt->bind_param($allTypes, ...$allParams);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return [
        'records' => $rows,
        'meta'    => [
            'total'  => $total,
            'page'   => $page,
            'limit'  => $limit,
            'pages'  => $limit > 0 ? (int) ceil($total / $limit) : 1,
        ],
    ];
}

function getFinancialSummary(): array {
    global $conn;

    $project_type = strtoupper($_GET['project_type'] ?? '');
    $project_id   = isset($_GET['project_id']) ? intval($_GET['project_id']) : null;

    $where  = ['1=1'];
    $params = [];
    $types  = '';

    if ($project_type && in_array($project_type, ['FSPF', 'IDP', 'AFME'])) {
        $where[]  = 'project_type = ?';
        $params[] = $project_type;
        $types   .= 's';
    }
    if ($project_id) {
        $where[]  = 'project_id = ?';
        $params[] = $project_id;
        $types   .= 'i';
    }

    $whereClause = implode(' AND ', $where);

    $stmt = $conn->prepare(
        "SELECT
            SUM(CASE WHEN record_type = 'Obligation'   THEN amount ELSE 0 END) AS total_obligations,
            SUM(CASE WHEN record_type = 'Disbursement' THEN amount ELSE 0 END) AS total_disbursed,
            SUM(CASE WHEN record_type = 'Liquidation'  THEN amount ELSE 0 END) AS total_liquidated,
            COUNT(DISTINCT project_id) AS project_count
         FROM project_financial_entries
         WHERE $whereClause AND record_status = 'Active'"
    );
    if (!$stmt) {
        throw new Exception('Query prepare failed: ' . $conn->error);
    }
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $obligations = (float) ($row['total_obligations'] ?? 0);
    $disbursed   = (float) ($row['total_disbursed']   ?? 0);
    $liquidated  = (float) ($row['total_liquidated']  ?? 0);

    return [
        'total_obligations'   => $obligations,
        'total_disbursed'     => $disbursed,
        'total_liquidated'    => $liquidated,
        'project_count'       => (int) ($row['project_count'] ?? 0),
        'disbursement_rate'   => $obligations > 0 ? round(($disbursed  / $obligations) * 100, 2) : 0,
        'liquidation_rate'    => $disbursed   > 0 ? round(($liquidated / $disbursed)   * 100, 2) : 0,
    ];
}

function createFinancialRecord(): array {
    global $conn;

    $data         = $_POST;
    $project_type = strtoupper($data['project_type'] ?? '');
    $project_id   = intval($data['project_id'] ?? 0);
    $record_type  = $data['record_type'] ?? '';
    $amount       = (float) ($data['amount'] ?? 0);

    if (!in_array($project_type, ['FSPF', 'IDP', 'AFME'])) {
        throw new Exception('project_type must be FSPF, IDP, or AFME');
    }
    if (!$project_id) {
        throw new Exception('project_id is required');
    }
    if (!in_array($record_type, ['Obligation', 'Disbursement', 'Liquidation'])) {
        throw new Exception('record_type must be Obligation, Disbursement, or Liquidation');
    }
    if ($amount <= 0) {
        throw new Exception('amount must be a positive number');
    }

    $reference_number = $data['reference_number'] ?? null;
    $particulars      = $data['particulars'] ?? null;
    $recorded_by      = $_SESSION['user_id'];

    $stmt = $conn->prepare(
        'INSERT INTO project_financial_entries
            (project_type, project_id, record_type, amount, reference_number, particulars, record_status, recorded_by)
         VALUES (?, ?, ?, ?, ?, ?, "Active", ?)'
    );
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param('siidssi', $project_type, $project_id, $record_type, $amount, $reference_number, $particulars, $recorded_by);

    if (!$stmt->execute()) {
        throw new Exception('Insert failed: ' . $stmt->error);
    }
    $new_id = $stmt->insert_id;
    $stmt->close();

    logAudit('CREATE_FINANCIAL_RECORD', $project_type, $project_id);

    return ['record_id' => $new_id];
}

function updateFinancialRecord(): array {
    global $conn;

    $data = apiInputJson();
    $id   = intval($_GET['id'] ?? $data['id'] ?? 0);
    if (!$id) {
        throw new Exception('Record ID required');
    }

    $updateable = ['amount', 'reference_number', 'particulars', 'record_status'];
    $updates    = [];
    $params     = [];
    $types      = '';

    foreach ($updateable as $field) {
        if (array_key_exists($field, $data)) {
            $updates[] = "$field = ?";
            $params[]  = $data[$field];
            $types    .= is_numeric($data[$field]) ? 'd' : 's';
        }
    }

    if (empty($updates)) {
        throw new Exception('No updatable fields provided');
    }

    $params[] = $id;
    $types   .= 'i';

    $stmt = $conn->prepare('UPDATE project_financial_entries SET ' . implode(', ', $updates) . ' WHERE id = ?');
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param($types, ...$params);

    if (!$stmt->execute()) {
        throw new Exception('Update failed: ' . $stmt->error);
    }
    $stmt->close();

    logAudit('UPDATE_FINANCIAL_RECORD', null, $id);

    return ['record_id' => $id];
}

function archiveFinancialRecord(): array {
    global $conn;

    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        throw new Exception('Record ID required');
    }

    $stmt = $conn->prepare("UPDATE project_financial_entries SET record_status = 'Archived' WHERE id = ?");
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param('i', $id);

    if (!$stmt->execute()) {
        throw new Exception('Archive failed: ' . $stmt->error);
    }
    $stmt->close();

    logAudit('ARCHIVE_FINANCIAL_RECORD', null, $id);

    return ['record_id' => $id];
}
