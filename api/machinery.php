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
            $response = listMachinery();
            break;

        case 'detail':
            $response = getMachineryDetail();
            break;

        case 'create':
            if ($method !== 'POST') {
                http_response_code(405);
                throw new Exception('Method not allowed');
            }
            $response = createMachinery();
            break;

        case 'update':
            if ($method !== 'PUT') {
                http_response_code(405);
                throw new Exception('Method not allowed');
            }
            $response = updateMachinery();
            break;

        case 'validate':
            if ($method !== 'PUT') {
                http_response_code(405);
                throw new Exception('Method not allowed');
            }
            $response = validateMachinery();
            break;

        case 'delete':
            if ($method !== 'DELETE') {
                http_response_code(405);
                throw new Exception('Method not allowed');
            }
            $response = deleteMachinery();
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

function listMachinery() {
    global $conn;

    $afme_id = $_GET['afme_id'] ?? null;
    $status = $_GET['status'] ?? null;
    $type = $_GET['type'] ?? null;
    $limit = min((int)($_GET['limit'] ?? 100), 500);
    $offset = (int)($_GET['offset'] ?? 0);

    $query = "SELECT am.*, ap.project_title, ap.project_code FROM afme_machinery am 
              LEFT JOIN afme_projects ap ON am.afme_project_id = ap.id 
              WHERE 1=1";

    $params = [];

    if ($afme_id) {
        $query .= " AND am.afme_project_id = ?";
        $params[] = $afme_id;
    }

    if ($status) {
        $query .= " AND am.machinery_status = ?";
        $params[] = $status;
    }

    if ($type) {
        $query .= " AND am.machinery_type = ?";
        $params[] = $type;
    }

    $query .= " ORDER BY am.created_at DESC LIMIT ? OFFSET ?";
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

function getMachineryDetail() {
    global $conn;

    $id = $_GET['id'] ?? null;
    if (!$id) {
        throw new Exception('Machinery ID required');
    }

    $stmt = $conn->prepare("
        SELECT am.*, 
               ams.specifications,
               amv.validation_status,
               amv.validator_remarks,
               ap.project_title, ap.project_code
        FROM afme_machinery am
        LEFT JOIN afme_machinery_specs ams ON am.id = ams.machinery_id
        LEFT JOIN afme_machinery_validation amv ON am.id = amv.machinery_id
        LEFT JOIN afme_projects ap ON am.afme_project_id = ap.id
        WHERE am.id = ?
    ");

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if (!$result) {
        throw new Exception('Machinery not found');
    }

    return $result;
}

function createMachinery() {
    global $conn;

    $data = $_POST;
    $required = ['afme_project_id', 'machinery_type', 'unit_quantity', 'unit_cost'];

    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    $afme_project_id = (int)$data['afme_project_id'];
    $machinery_type = $data['machinery_type'];
    $unit_quantity = (int)$data['unit_quantity'];
    $unit_cost = (float)$data['unit_cost'];
    $specifications = $data['specifications'] ?? null;
    $status = 'Pending Delivery';
    $created_by = $_SESSION['user_id'];

    $stmt = $conn->prepare("
        INSERT INTO afme_machinery 
        (afme_project_id, machinery_type, unit_quantity, unit_cost, machinery_status, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->bind_param('isidsi', $afme_project_id, $machinery_type, $unit_quantity, $unit_cost, $status, $created_by);
    $stmt->execute();

    $machinery_id = $conn->insert_id;

    // Add specifications if provided
    if ($specifications) {
        $specs_stmt = $conn->prepare("
            INSERT INTO afme_machinery_specs (machinery_id, specifications, created_at)
            VALUES (?, ?, NOW())
        ");
        $specs_stmt->bind_param('is', $machinery_id, $specifications);
        $specs_stmt->execute();
    }

    logActivity($_SESSION['user_id'], "Created machinery ID $machinery_id for AFME Project $afme_project_id", 'afme_machinery', 'CREATE');

    return ['success' => true, 'machinery_id' => $machinery_id, 'message' => 'Machinery created successfully'];
}

function updateMachinery() {
    global $conn;

    parse_str(file_get_contents("php://input"), $data);

    $id = $_GET['id'] ?? $data['id'] ?? null;
    if (!$id) {
        throw new Exception('Machinery ID required');
    }

    $updates = [];
    $params = [];
    $types = '';

    $updateable_fields = ['machinery_type', 'unit_quantity', 'unit_cost', 'machinery_status', 'specifications'];

    foreach ($updateable_fields as $field) {
        if (isset($data[$field])) {
            if ($field === 'specifications') {
                // Handle separately
                continue;
            }
            $updates[] = "$field = ?";
            $params[] = $data[$field];
            $types .= is_int($data[$field]) ? 'i' : 's';
        }
    }

    if (empty($updates)) {
        throw new Exception('No fields to update');
    }

    $params[] = $id;
    $types .= 'i';

    $query = "UPDATE afme_machinery SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    // Update specifications if provided
    if (isset($data['specifications'])) {
        $spec_stmt = $conn->prepare("
            UPDATE afme_machinery_specs SET specifications = ? WHERE machinery_id = ?
        ");
        $spec_stmt->bind_param('si', $data['specifications'], $id);
        $spec_stmt->execute();
    }

    logActivity($_SESSION['user_id'], "Updated machinery ID $id", 'afme_machinery', 'UPDATE');

    return ['success' => true, 'message' => 'Machinery updated successfully'];
}

function validateMachinery() {
    global $conn;

    parse_str(file_get_contents("php://input"), $data);

    $id = $_GET['id'] ?? $data['id'] ?? null;
    if (!$id) {
        throw new Exception('Machinery ID required');
    }

    $validation_status = $data['validation_status'] ?? 'Approved';
    $validator_remarks = $data['validator_remarks'] ?? '';
    $validator_id = $_SESSION['user_id'];

    // Check if validation record exists
    $check = $conn->prepare("SELECT id FROM afme_machinery_validation WHERE machinery_id = ?");
    $check->bind_param('i', $id);
    $check->execute();
    $exists = $check->get_result()->num_rows > 0;

    if ($exists) {
        $stmt = $conn->prepare("
            UPDATE afme_machinery_validation 
            SET validation_status = ?, validator_remarks = ?, validator_id = ?, validated_date = NOW()
            WHERE machinery_id = ?
        ");
        $stmt->bind_param('ssii', $validation_status, $validator_remarks, $validator_id, $id);
    } else {
        $stmt = $conn->prepare("
            INSERT INTO afme_machinery_validation 
            (machinery_id, validation_status, validator_remarks, validator_id, validated_date)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param('issi', $id, $validation_status, $validator_remarks, $validator_id);
    }

    $stmt->execute();

    // Update machinery status to match validation
    $update_stmt = $conn->prepare("UPDATE afme_machinery SET machinery_status = ? WHERE id = ?");
    $update_stmt->bind_param('si', $validation_status, $id);
    $update_stmt->execute();

    logActivity($_SESSION['user_id'], "Validated machinery ID $id as $validation_status", 'afme_machinery', 'VALIDATE');

    return ['success' => true, 'message' => 'Machinery validated successfully'];
}

function deleteMachinery() {
    global $conn;

    $id = $_GET['id'] ?? null;
    if (!$id) {
        throw new Exception('Machinery ID required');
    }

    // Soft delete
    $stmt = $conn->prepare("UPDATE afme_machinery SET machinery_status = 'Archived' WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();

    logActivity($_SESSION['user_id'], "Archived machinery ID $id", 'afme_machinery', 'DELETE');

    return ['success' => true, 'message' => 'Machinery archived successfully'];
}
