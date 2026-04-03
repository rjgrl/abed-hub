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

$project_code = sanitize($_POST['project_code'] ?? '');
$project_title = sanitize($_POST['project_title'] ?? '');
$fund_source = sanitize($_POST['fund_source'] ?? '');
$funding_year = intval($_POST['funding_year'] ?? date('Y'));
$beneficiary = sanitize($_POST['beneficiary'] ?? '');
$description = sanitize($_POST['description'] ?? '');
$proposed_amount = floatval($_POST['proposed_amount'] ?? 0);
$allocated_amount = floatval($_POST['allocated_amount'] ?? 0);

if (empty($project_code) || empty($project_title) || empty($fund_source)) {
    echo json_encode(['status' => 'error', 'message' => 'Required fields missing']);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM afme_projects WHERE project_code = ?");
$stmt->bind_param("s", $project_code);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => 'Project code already exists']);
    exit;
}

$stmt = $conn->prepare("
    INSERT INTO afme_projects (
        project_code, project_title, fund_source, funding_year,
        beneficiary, description, proposed_amount, allocated_amount, created_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "sssisddii",
    $project_code, $project_title, $fund_source, $funding_year,
    $beneficiary, $description, $proposed_amount, $allocated_amount, $user_id
);

if ($stmt->execute()) {
    $project_id = $stmt->insert_id;
    logAudit('CREATE_AFME_PROJECT', 'AFME', $project_id, null, $_POST);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'AFME Project registered successfully',
        'project_id' => $project_id
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Error registering project']);
}

$stmt->close();
$conn->close();
?>

