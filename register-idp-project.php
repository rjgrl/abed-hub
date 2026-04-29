<?php
session_start();
require_once 'config/database.php';
require_once 'functions/helpers.php';

requireLogin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get and sanitize inputs
$project_code = sanitize($_POST['project_code'] ?? '');
$project_title = sanitize($_POST['project_title'] ?? '');
$fund_source = sanitize($_POST['fund_source'] ?? '');
$funding_year = intval($_POST['funding_year'] ?? date('Y'));
$scope_of_work = sanitize($_POST['scope_of_work'] ?? '');
$beneficiary = sanitize($_POST['beneficiary'] ?? '');
$description = sanitize($_POST['description'] ?? '');
$implementation_schedule_days = intval($_POST['implementation_schedule_days'] ?? 0);
$quantity = floatval($_POST['quantity'] ?? 0);
$unit = sanitize($_POST['unit'] ?? '');
$province = sanitize($_POST['province'] ?? '');
$municipality = sanitize($_POST['municipality'] ?? '');
$barangay = sanitize($_POST['barangay'] ?? '');
$latitude = floatval($_POST['latitude'] ?? 0);
$longitude = floatval($_POST['longitude'] ?? 0);
$proposed_amount = floatval($_POST['proposed_amount'] ?? 0);
$allocated_amount = floatval($_POST['allocated_amount'] ?? 0);
$households_benefited = intval($_POST['households_benefited'] ?? 0);

// Validate required fields
if (empty($project_code) || empty($project_title) || empty($fund_source)) {
    echo json_encode(['status' => 'error', 'message' => 'Required fields missing']);
    exit;
}

// Check if project code exists
$stmt = $conn->prepare("SELECT id FROM idp_projects WHERE project_code = ?");
$stmt->bind_param("s", $project_code);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => 'Project code already exists']);
    exit;
}

// Insert project
$stmt = $conn->prepare("
    INSERT INTO idp_projects (
        project_code, project_title, fund_source, funding_year, scope_of_work,
        beneficiary, description, implementation_schedule_days, quantity, unit,
        province, municipality, barangay, latitude, longitude, proposed_amount,
        allocated_amount, households_benefited, created_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "sssisssidssssddddii",
    $project_code, $project_title, $fund_source, $funding_year, $scope_of_work,
    $beneficiary, $description, $implementation_schedule_days, $quantity, $unit,
    $province, $municipality, $barangay, $latitude, $longitude, $proposed_amount,
    $allocated_amount, $households_benefited, $user_id
);

if ($stmt->execute()) {
    $project_id = $stmt->insert_id;
    logAudit('CREATE_IDP_PROJECT', 'IDP', $project_id, null, $_POST);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'IDP Project registered successfully',
        'project_id' => $project_id
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Error registering project']);
}

$stmt->close();
$conn->close();
?>

