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

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? null;
$response = [];

try {
    switch ($action) {
        case 'update-stage':
            if ($method !== 'POST') {
                http_response_code(405);
                throw new Exception('Method not allowed');
            }
            $response = batchUpdateStage();
            break;

        case 'update-progress':
            if ($method !== 'POST') {
                http_response_code(405);
                throw new Exception('Method not allowed');
            }
            $response = batchUpdateProgress();
            break;

        case 'update-field':
            if ($method !== 'POST') {
                http_response_code(405);
                throw new Exception('Method not allowed');
            }
            $response = batchUpdateField();
            break;

        case 'delete-projects':
            if ($method !== 'DELETE') {
                http_response_code(405);
                throw new Exception('Method not allowed');
            }
            $response = batchDeleteProjects();
            break;

        case 'export':
            if ($method !== 'POST') {
                http_response_code(405);
                throw new Exception('Method not allowed');
            }
            $response = batchExport();
            break;

        defaults:
            http_response_code(400);
            throw new Exception('Invalid action');
    }

    http_response_code(200);
    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

function batchUpdateStage() {
    global $conn;

    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['projects']) || !is_array($data['projects'])) {
        throw new Exception('projects array required');
    }

    if (!isset($data['stage'])) {
        throw new Exception('stage parameter required');
    }

    $projects = $data['projects'];
    $project_type = $data['type'] ?? 'fspf';
    $new_stage = $data['stage'];
    $updated_count = 0;
    $errors = [];

    $type_map = [
        'fspf' => 'fspf_projects',
        'idp' => 'idp_projects',
        'afme' => 'afme_projects'
    ];

    $table = $type_map[$project_type] ?? null;
    if (!$table) {
        throw new Exception('Invalid project type');
    }

    // Valid stages
    $valid_stages = ['Proposal', 'Pre-Implementation', 'Procurement', 'Implementation', 'Completed', 'Turned-Over'];
    if (!in_array($new_stage, $valid_stages)) {
        throw new Exception('Invalid stage');
    }

    foreach ($projects as $project_id) {
        $project_id = (int)$project_id;

        $stmt = $conn->prepare("UPDATE $table SET current_stage = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('si', $new_stage, $project_id);

        if ($stmt->execute()) {
            $updated_count++;
            logActivity($_SESSION['user_id'], "Batch updated project $project_id to stage $new_stage", 'batch_operations', 'UPDATE');
        } else {
            $errors[] = "Failed to update project $project_id";
        }
    }

    return [
        'success' => true,
        'updated' => $updated_count,
        'total' => count($projects),
        'errors' => $errors,
        'message' => "$updated_count project(s) updated successfully"
    ];
}

function batchUpdateProgress() {
    global $conn;

    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['projects']) || !is_array($data['projects'])) {
        throw new Exception('projects array required');
    }

    $projects = $data['projects'];
    $project_type = $data['type'] ?? 'fspf';
    $physical_progress = isset($data['physical_progress']) ? (float)$data['physical_progress'] : null;
    $financial_progress = isset($data['financial_progress']) ? (float)$data['financial_progress'] : null;

    if ($physical_progress === null && $financial_progress === null) {
        throw new Exception('At least one progress value required');
    }

    // Validate percentages
    if ($physical_progress !== null && ($physical_progress < 0 || $physical_progress > 100)) {
        throw new Exception('Physical progress must be between 0 and 100');
    }

    if ($financial_progress !== null && ($financial_progress < 0 || $financial_progress > 100)) {
        throw new Exception('Financial progress must be between 0 and 100');
    }

    $updated_count = 0;
    $errors = [];

    $type_map = [
        'fspf' => 'fspf_projects',
        'idp' => 'idp_projects',
        'afme' => 'afme_projects'
    ];

    $table = $type_map[$project_type] ?? null;
    if (!$table) {
        throw new Exception('Invalid project type');
    }

    foreach ($projects as $project_id) {
        $project_id = (int)$project_id;

        $updates = [];
        $params = [];
        $types = '';

        if ($physical_progress !== null) {
            $updates[] = 'physical_progress = ?';
            $params[] = $physical_progress;
            $types .= 'd';
        }

        if ($financial_progress !== null) {
            $updates[] = 'financial_progress = ?';
            $params[] = $financial_progress;
            $types .= 'd';
        }

        $updates[] = 'updated_at = NOW()';
        $params[] = $project_id;
        $types .= 'i';

        $query = "UPDATE $table SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            $updated_count++;
            logActivity($_SESSION['user_id'], "Batch updated progress for project $project_id", 'batch_operations', 'UPDATE');
        } else {
            $errors[] = "Failed to update project $project_id";
        }
    }

    return [
        'success' => true,
        'updated' => $updated_count,
        'total' => count($projects),
        'errors' => $errors,
        'message' => "$updated_count project(s) updated successfully"
    ];
}

function batchUpdateField() {
    global $conn;

    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['projects']) || !is_array($data['projects'])) {
        throw new Exception('projects array required');
    }

    if (!isset($data['field']) || !isset($data['value'])) {
        throw new Exception('field and value required');
    }

    $projects = $data['projects'];
    $project_type = $data['type'] ?? 'fspf';
    $field = $data['field'];
    $value = $data['value'];

    // Whitelisted fields that can be updated
    $allowed_fields = ['allocated_amount', 'proposed_amount', 'commodity', 'description'];
    if (!in_array($field, $allowed_fields)) {
        throw new Exception("Cannot update field '$field'");
    }

    $updated_count = 0;
    $errors = [];

    $type_map = [
        'fspf' => 'fspf_projects',
        'idp' => 'idp_projects',
        'afme' => 'afme_projects'
    ];

    $table = $type_map[$project_type] ?? null;
    if (!$table) {
        throw new Exception('Invalid project type');
    }

    foreach ($projects as $project_id) {
        $project_id = (int)$project_id;

        $stmt = $conn->prepare("UPDATE $table SET $field = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('si', $value, $project_id);

        if ($stmt->execute()) {
            $updated_count++;
        } else {
            $errors[] = "Failed to update project $project_id";
        }
    }

    logActivity($_SESSION['user_id'], "Batch updated $field for " . $updated_count . " projects", 'batch_operations', 'UPDATE');

    return [
        'success' => true,
        'updated' => $updated_count,
        'total' => count($projects),
        'errors' => $errors
    ];
}

function batchDeleteProjects() {
    global $conn;

    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['projects']) || !is_array($data['projects'])) {
        throw new Exception('projects array required');
    }

    $projects = $data['projects'];
    $project_type = $data['type'] ?? 'fspf';
    $deleted_count = 0;
    $errors = [];

    $type_map = [
        'fspf' => 'fspf_projects',
        'idp' => 'idp_projects',
        'afme' => 'afme_projects'
    ];

    $table = $type_map[$project_type] ?? null;
    if (!$table) {
        throw new Exception('Invalid project type');
    }

    foreach ($projects as $project_id) {
        $project_id = (int)$project_id;

        // Soft delete by setting status
        $stmt = $conn->prepare("UPDATE $table SET current_stage = 'Archived' WHERE id = ?");
        $stmt->bind_param('i', $project_id);

        if ($stmt->execute()) {
            $deleted_count++;
            logActivity($_SESSION['user_id'], "Archived project $project_id via batch operation", 'batch_operations', 'DELETE');
        } else {
            $errors[] = "Failed to delete project $project_id";
        }
    }

    return [
        'success' => true,
        'deleted' => $deleted_count,
        'total' => count($projects),
        'errors' => $errors,
        'message' => "$deleted_count project(s) archived successfully"
    ];
}

function batchExport() {
    global $conn;

    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['projects']) || !is_array($data['projects'])) {
        throw new Exception('projects array required');
    }

    $projects = $data['projects'];
    $project_type = $data['type'] ?? 'fspf';
    $format = $data['format'] ?? 'json';

    $type_map = [
        'fspf' => 'fspf_projects',
        'idp' => 'idp_projects',
        'afme' => 'afme_projects'
    ];

    $table = $type_map[$project_type] ?? null;
    if (!$table) {
        throw new Exception('Invalid project type');
    }

    // Get project data
    $project_ids = implode(',', array_map('intval', $projects));
    $query = "SELECT * FROM $table WHERE id IN ($project_ids)";
    $result = $conn->query($query)->fetch_all(MYSQLI_ASSOC);

    if ($format === 'csv') {
        // Format as CSV
        $csv_data = [];
        if (!empty($result)) {
            $csv_data[] = array_keys($result[0]);
            foreach ($result as $row) {
                $csv_data[] = array_values($row);
            }
        }
        return [
            'success' => true,
            'format' => 'csv',
            'data' => $csv_data,
            'count' => count($result)
        ];
    } else {
        // JSON format
        return [
            'success' => true,
            'format' => 'json',
            'data' => $result,
            'count' => count($result)
        ];
    }
}
