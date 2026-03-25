<?php
session_start();
require_once 'config/database.php';

requireLogin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$user_id = $_SESSION['user_id'];
$afme_project_id = intval($_POST['afme_project_id'] ?? 0);
$machine_name = sanitize($_POST['machine_name'] ?? '');
$farm_operation = sanitize($_POST['farm_operation'] ?? '');
$beneficiary_name = sanitize($_POST['beneficiary_name'] ?? '');
$beneficiary_contact = sanitize($_POST['beneficiary_contact'] ?? '');
$recipient_type = sanitize($_POST['recipient_type'] ?? '');
$farm_location = sanitize($_POST['farm_location'] ?? '');
$beneficiary_households = intval($_POST['beneficiary_households'] ?? 0);
$description = sanitize($_POST['description'] ?? '');
$proposed_amount = floatval($_POST['proposed_amount'] ?? 0);
$allocated_amount = floatval($_POST['allocated_amount'] ?? 0);
$funding_year = intval($_POST['funding_year'] ?? date('Y'));
$fund_source = sanitize($_POST['fund_source'] ?? '');
$date_receipt = $_POST['date_receipt'] ?? null;

// Validate
if (empty($machine_name) || empty($farm_operation) || empty($beneficiary_name)) {
    echo json_encode(['status' => 'error', 'message' => 'Required fields missing']);
    exit;
}

// Verify AFME project exists
$stmt = $conn->prepare("SELECT id FROM afme_projects WHERE id = ?");
$stmt->bind_param("i", $afme_project_id);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid AFME project']);
    exit;
}

// Insert
$stmt = $conn->prepare("
    INSERT INTO afme_machinery (
        afme_project_id, machine_name, farm_operation, beneficiary_name, 
        beneficiary_contact, recipient_type, farm_location, beneficiary_households,
        description, proposed_amount, allocated_amount, funding_year, fund_source, date_receipt
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "isssssiidddis",
    $afme_project_id, $machine_name, $farm_operation, $beneficiary_name,
    $beneficiary_contact, $recipient_type, $farm_location, $beneficiary_households,
    $description, $proposed_amount, $allocated_amount, $funding_year, $fund_source, $date_receipt
);

if ($stmt->execute()) {
    $machinery_id = $stmt->insert_id;
    logAudit('CREATE_AFME_MACHINERY', 'AFME', $machinery_id, null, $_POST);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Machinery added successfully',
        'machinery_id' => $machinery_id
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Error adding machinery']);
}

$stmt->close();
$conn->close();
?>