<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../functions/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];
$response = [];

try {
    switch ($action) {
        case 'list':
            $response = listFinancialRecords();
            break;

        case 'summary':
            $response = getFinancialSummary();
            break;

        case 'create':
            if ($method !== 'POST') {
                http_response_code(405);
                throw new Exception('Method not allowed');
            }
            $response = createFinancialRecord();
            break;

        case 'update':
            if ($method !== 'PUT') {
                http_response_code(405);
                throw new Exception('Method not allowed');
            }
            $response = updateFinancialRecord();
            break;

        case 'delete':
            if ($method !== 'DELETE') {
                http_response_code(405);
                throw new Exception('Method not allowed');
            }
            $response = deleteFinancialRecord();
            break;

        default:
            http_response_code(400);
            throw new Exception('Invalid action');
    }

    http_response_code(200);
    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

function listFinancialRecords() {
    global $conn;

    $project_id = $_GET['project_id'] ?? null;
    $project_type = $_GET['project_type'] ?? 'fspf';
    $status = $_GET['status'] ?? null;
    $limit = min((int)($_GET['limit'] ?? 100), 500);
    $offset = (int)($_GET['offset'] ?? 0);

    // Map project type to column name
    $type_map = [
        'fspf' => 'fspf_project_id',
        'idp' => 'idp_project_id',
        'afme' => 'afme_project_id'
    ];

    $project_col = $type_map[$project_type] ?? 'fspf_project_id';

    $query = "SELECT * FROM project_financial_tracker WHERE 1=1";
    $params = [];

    if ($project_id) {
        $query .= " AND $project_col = ?";
        $params[] = $project_id;
    }

    if ($status) {
        $query .= " AND record_status = ?";
        $params[] = $status;
    }

    $query .= " ORDER BY record_date DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        throw new Exception('prepare failed: ' . $conn->error);
    }

    if (!empty($params)) {
        $types = str_repeat('s', count($params) - 2) . 'ii';
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getFinancialSummary() {
    global $conn;

    $project_id = $_GET['project_id'] ?? null;
    $project_type = $_GET['project_type'] ?? 'fspf';

    $type_map = [
        'fspf' => 'fspf_project_id',
        'idp' => 'idp_project_id',
        'afme' => 'afme_project_id'
    ];

    $project_col = $type_map[$project_type] ?? 'fspf_project_id';

    if (!$project_id) {
        // Get aggregate across all projects
        $query = "SELECT 
                  SUM(CASE WHEN record_type = 'Obligation' THEN amount ELSE 0 END) as total_obligations,
                  SUM(CASE WHEN record_type = 'Disbursement' THEN amount ELSE 0 END) as total_disbursed,
                  SUM(CASE WHEN record_type = 'Liquidation' THEN amount ELSE 0 END) as total_liquidated,
                  COUNT(DISTINCT $project_col) as project_count
                  FROM project_financial_tracker";

        $result = $conn->query($query);
    } else {
        $stmt = $conn->prepare("SELECT 
                  SUM(CASE WHEN record_type = 'Obligation' THEN amount ELSE 0 END) as total_obligations,
                  SUM(CASE WHEN record_type = 'Disbursement' THEN amount ELSE 0 END) as total_disbursed,
                  SUM(CASE WHEN record_type = 'Liquidation' THEN amount ELSE 0 END) as total_liquidated
                  FROM project_financial_tracker 
                  WHERE $project_col = ?");

        $stmt->bind_param('i', $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
    }

    $summary = $result->fetch_assoc();

    // Add calculated fields
    $summary['total_obligations'] = (float)($summary['total_obligations'] ?? 0);
    $summary['total_disbursed'] = (float)($summary['total_disbursed'] ?? 0);
    $summary['total_liquidated'] = (float)($summary['total_liquidated'] ?? 0);
    $summary['disbursement_rate'] = $summary['total_obligations'] > 0 
        ? round(($summary['total_disbursed'] / $summary['total_obligations']) * 100, 2) 
        : 0;
    $summary['liquidation_rate'] = $summary['total_disbursed'] > 0 
        ? round(($summary['total_liquidated'] / $summary['total_disbursed']) * 100, 2) 
        : 0;

    return $summary;
}

function createFinancialRecord() {
    global $conn;

    $data = $_POST;
    $required = ['record_type', 'amount'];

    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Determine which project ID column to use
    $project_col = null;
    $project_id = null;

    if (!empty($data['fspf_project_id'])) {
        $project_col = 'fspf_project_id';
        $project_id = (int)$data['fspf_project_id'];
    } elseif (!empty($data['idp_project_id'])) {
        $project_col = 'idp_project_id';
        $project_id = (int)$data['idp_project_id'];
    } elseif (!empty($data['afme_project_id'])) {
        $project_col = 'afme_project_id';
        $project_id = (int)$data['afme_project_id'];
    } else {
        throw new Exception('Project ID required (fspf_project_id, idp_project_id, or afme_project_id)');
    }

    $record_type = $data['record_type'];
    $amount = (float)$data['amount'];
    $reference_number = $data['reference_number'] ?? null;
    $particulars = $data['particulars'] ?? null;
    $record_status = 'Active';
    $recorded_by = $_SESSION['user_id'];

    $columns = ["$project_col", "record_type", "amount", "record_status", "record_date", "recorded_by"];
    $placeholders = ["?", "?", "?", "?", "NOW()", "?"];

    if ($reference_number || $particulars) {
        if ($reference_number) {
            $columns[] = "reference_number";
            $placeholders[] = "?";
        }
        if ($particulars) {
            $columns[] = "particulars";
            $placeholders[] = "?";
        }
    }

    $query = "INSERT INTO project_financial_tracker (" . implode(", ", $columns) . ") 
              VALUES (" . implode(", ", $placeholders) . ")";

    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        throw new Exception('prepare failed: ' . $conn->error);
    }

    $bind_params = [$project_id, $record_type, $amount, $record_status, $recorded_by];
    if ($reference_number) $bind_params[] = $reference_number;
    if ($particulars) $bind_params[] = $particulars;

    $types = 'isdssi';
    if ($reference_number) $types = 'issdssi';
    if ($particulars && !$reference_number) $types = 'isdssi';

    $stmt->bind_param($types, ...$bind_params);
    $stmt->execute();

    $record_id = $conn->insert_id;

    logActivity($_SESSION['user_id'], "Created $record_type record for project $project_id", 'financial_tracker', 'CREATE');

    return ['success' => true, 'record_id' => $record_id, 'message' => 'Financial record created successfully'];
}

function updateFinancialRecord() {
    global $conn;

    parse_str(file_get_contents("php://input"), $data);

    $id = $_GET['id'] ?? $data['id'] ?? null;
    if (!$id) {
        throw new Exception('Record ID required');
    }

    $updates = [];
    $params = [];
    $types = '';

    $updateable_fields = ['amount', 'reference_number', 'particulars', 'record_status'];

    foreach ($updateable_fields as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
            $types .= is_numeric($data[$field]) ? 'd' : 's';
        }
    }

    if (empty($updates)) {
        throw new Exception('No fields to update');
    }

    $params[] = $id;
    $types .= 'i';

    $query = "UPDATE project_financial_tracker SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    logActivity($_SESSION['user_id'], "Updated financial record ID $id", 'financial_tracker', 'UPDATE');

    return ['success' => true, 'message' => 'Financial record updated successfully'];
}

function deleteFinancialRecord() {
    global $conn;

    $id = $_GET['id'] ?? null;
    if (!$id) {
        throw new Exception('Record ID required');
    }

    // Soft delete
    $stmt = $conn->prepare("UPDATE project_financial_tracker SET record_status = 'Archived' WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();

    logActivity($_SESSION['user_id'], "Archived financial record ID $id", 'financial_tracker', 'DELETE');

    return ['success' => true, 'message' => 'Financial record archived successfully'];
}
