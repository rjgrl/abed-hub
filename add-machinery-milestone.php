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
$machinery_id = intval($_POST['machinery_id'] ?? 0);
$milestone_name = sanitize($_POST['milestone_name'] ?? '');
$stage = sanitize($_POST['stage'] ?? '');
$target_date = $_POST['target_date'] ?? null;
$remarks = sanitize($_POST['remarks'] ?? '');

// Validate inputs
if ($machinery_id <= 0 || empty($milestone_name) || empty($stage)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid input parameters']);
    exit;
}

// Check if machinery exists
$stmt = $conn->prepare("SELECT id FROM afme_machinery WHERE id = ?");
$stmt->bind_param("i", $machinery_id);
$stmt->execute();
if ($stmt->get_result()->num_rows == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Machinery not found']);
    exit;
}

// Validate stage
$valid_stages = ['Proposal', 'Pre-Implementation', 'Procurement', 'Implementation', 'Delivered', 'Turned-Over'];
if (!in_array($stage, $valid_stages)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid stage']);
    exit;
}

// Insert milestone
$stmt = $conn->prepare("
    INSERT INTO afme_machinery_milestones (
        machinery_id, stage, milestone_name, target_date, remarks, created_at
    ) VALUES (?, ?, ?, ?, ?, NOW())
");

$stmt->bind_param(
    "issss",
    $machinery_id, $stage, $milestone_name, $target_date, $remarks
);

if ($stmt->execute()) {
    $milestone_id = $stmt->insert_id;
    logAudit('ADD_MACHINERY_MILESTONE', 'AFME', $machinery_id, null, [
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

