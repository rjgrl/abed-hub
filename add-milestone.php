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
$project_type = sanitize($_POST['project_type'] ?? '');
$project_id = intval($_POST['project_id'] ?? 0);
$milestone_name = sanitize($_POST['milestone_name'] ?? '');
$stage = sanitize($_POST['stage'] ?? '');
$target_date = $_POST['target_date'] ?? null;
$remarks = sanitize($_POST['remarks'] ?? '');

// Validate inputs
if (empty($project_type) || !in_array($project_type, ['FSPF', 'IDP', 'AFME']) || $project_id <= 0 || empty($milestone_name) || empty($stage)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid input parameters']);
    exit;
}

// Validate stage
$valid_stages = ['Proposal', 'Pre-Implementation', 'Procurement', 'Implementation', 'Completed'];
if (!in_array($stage, $valid_stages)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid stage']);
    exit;
}

echo json_encode([
    'status' => 'error',
    'message' => 'Milestones are not available in the refactored schema.'
]);
$conn->close();
?>

