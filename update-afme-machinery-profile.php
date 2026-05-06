<?php
session_name('ABED_IDM_HUB');
session_start();
require_once 'config/database.php';

requireLogin();
header('Content-Type: application/json');

if (!isSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Only Super Admin can edit AFME profile fields']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$machinery_id = (int) ($_POST['machinery_id'] ?? 0);
if ($machinery_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid machinery ID']);
    exit;
}

$existsStmt = $conn->prepare('SELECT id FROM afme WHERE id = ? LIMIT 1');
$existsStmt->bind_param('i', $machinery_id);
$existsStmt->execute();
if ($existsStmt->get_result()->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Machinery not found']);
    exit;
}
$existsStmt->close();

$mode = trim((string) ($_POST['mode_of_procurement'] ?? ''));
if ($mode !== '' && !in_array($mode, ['Public Bidding', 'Small Value Procurement'], true)) {
    $mode = '';
}
$opStatus = trim((string) ($_POST['operation_status'] ?? ''));
if ($opStatus !== '' && !in_array($opStatus, ['Operational', 'Non-operational', 'Intermittently Operational'], true)) {
    $opStatus = '';
}

$specText = trim((string) ($_POST['specifications'] ?? ''));

$inputMap = [
    'machine_name' => sanitize($_POST['machine_name'] ?? ''),
    'machine_id' => sanitize($_POST['machine_id'] ?? ''),
    'machinery_type' => sanitize($_POST['machinery_type'] ?? ''),
    'mode_of_procurement' => $mode,
    'brand' => sanitize($_POST['brand'] ?? ''),
    'engine_type' => sanitize($_POST['engine_type'] ?? ''),
    'serial_number' => sanitize($_POST['serial_number'] ?? ''),
    'chassis_serial_number' => sanitize($_POST['chassis_serial_number'] ?? ''),
    'service_area' => sanitize($_POST['service_area'] ?? ''),
    'date_receipt' => trim((string) ($_POST['date_receipt'] ?? '')),
    'last_maintenance_date' => trim((string) ($_POST['last_maintenance_date'] ?? '')),
    'serviceability_status' => sanitize($_POST['serviceability_status'] ?? ''),
    'audit_date' => trim((string) ($_POST['audit_date'] ?? '')),
    'operational_remarks' => sanitize($_POST['operational_remarks'] ?? ''),
    'maintenance_remarks' => sanitize($_POST['maintenance_remarks'] ?? ''),
    'operation_status' => $opStatus,
];

if ($inputMap['machine_name'] === '') {
    echo json_encode(['status' => 'error', 'message' => 'Machine Name is required']);
    exit;
}

// Schema-compatible spec column (current: specifications; legacy: description)
$columnsResult = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'afme'");
if (!$columnsResult) {
    echo json_encode(['status' => 'error', 'message' => 'Unable to inspect AFME schema']);
    exit;
}
$columns = [];
while ($row = $columnsResult->fetch_assoc()) {
    $columns[(string) ($row['COLUMN_NAME'] ?? '')] = true;
}

$specColumn = null;
if (isset($columns['specifications'])) {
    $specColumn = 'specifications';
} elseif (isset($columns['description'])) {
    $specColumn = 'description';
}

$updates = [];
$params = [];
$types = '';

foreach ($inputMap as $column => $value) {
    if (!isset($columns[$column])) {
        continue;
    }
    $updates[] = "{$column} = ?";
    if (in_array($column, ['date_receipt', 'last_maintenance_date', 'audit_date'], true)) {
        $value = $value !== '' ? $value : null;
    }
    $params[] = $value;
    $types .= 's';
}

if ($specColumn !== null) {
    $updates[] = "{$specColumn} = ?";
    $params[] = $specText;
    $types .= 's';
}

if (empty($updates)) {
    echo json_encode(['status' => 'error', 'message' => 'No compatible AFME columns found to update']);
    exit;
}

$sql = 'UPDATE afme SET ' . implode(', ', $updates) . ' WHERE id = ?';
$params[] = $machinery_id;
$types .= 'i';

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    logAudit('UPDATE_AFME_PROFILE_FIELDS', 'AFME', $machinery_id, null, $_POST);
    echo json_encode(['status' => 'success', 'message' => 'AFME profile fields updated successfully']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update AFME profile fields']);
}

$stmt->close();
$conn->close();
?>
