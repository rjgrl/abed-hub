<?php
session_name('ABED_IDM_HUB');
session_start();
require_once 'config/database.php';

requireLogin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get and sanitize inputs
$machine_name = sanitize($_POST['machine_name'] ?? '');
$afme_project_id = intval($_POST['afme_project_id'] ?? 0);
$farm_operation = sanitize($_POST['farm_operation'] ?? '');
$beneficiary_name = sanitize($_POST['beneficiary_name'] ?? '');
$beneficiary_contact = sanitize($_POST['beneficiary_contact'] ?? '');
$recipient_type = sanitize($_POST['recipient_type'] ?? 'Farmers Cooperative');
$farm_location = sanitize($_POST['farm_location'] ?? '');
$beneficiary_households = intval($_POST['beneficiary_households'] ?? 0);
$description = sanitize($_POST['description'] ?? '');
$proposed_amount = floatval($_POST['proposed_amount'] ?? 0);
$allocated_amount = floatval($_POST['allocated_amount'] ?? 0);
$funding_year = intval($_POST['funding_year'] ?? date('Y'));
$indicative_funding_year = intval($_POST['indicative_funding_year'] ?? $funding_year);
$fund_source = sanitize($_POST['fund_source'] ?? '');
$current_status = 'For Validation';

// Validate required fields
if (empty($machine_name) || empty($farm_operation) || empty($beneficiary_name)) {
    echo json_encode(['status' => 'error', 'message' => 'Required fields missing']);
    exit;
}

// Insert machinery into consolidated afme table
$stmt = $conn->prepare("
    INSERT INTO afme (
        project_id, machine_name, farm_operation, beneficiary_name,
        beneficiary_contact, recipient_type, farm_location, beneficiary_households,
        description, amount_proposed, amount_allocated, funding_year,
        indicative_funding_year, fund_source, current_status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "issssssisddiiss",
    $afme_project_id, $machine_name, $farm_operation, $beneficiary_name,
    $beneficiary_contact, $recipient_type, $farm_location, $beneficiary_households,
    $description, $proposed_amount, $allocated_amount, $funding_year,
    $indicative_funding_year, $fund_source, $current_status
);

if ($stmt->execute()) {
    $machinery_id = $stmt->insert_id;
    logAudit('CREATE_AFME_MACHINERY', 'AFME', $machinery_id, null, $_POST);

    echo json_encode([
        'status' => 'success',
        'message' => 'AFME Machinery added successfully',
        'machinery_id' => $machinery_id
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Error adding machinery']);
}

$stmt->close();
$conn->close();
?>

