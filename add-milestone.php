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

// Insert milestone
$stmt = $conn->prepare("
    INSERT INTO project_milestones (
        project_type, project_id, stage, milestone_name, target_date, remarks, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, NOW())
");

$stmt->bind_param(
    "sissss",
    $project_type, $project_id, $stage, $milestone_name, $target_date, $remarks
);

if ($stmt->execute()) {
    $milestone_id = $stmt->insert_id;
    logAudit('ADD_MILESTONE', $project_type, $project_id, null, [
        'milestone_name' => $milestone_name,
        'stage' => $stage,
        'target_date' => $target_date
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Milestone added successfully',
        'milestone_id' => $milestone_id
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to add milestone']);
}

$stmt->close();
$conn->close();
?>

