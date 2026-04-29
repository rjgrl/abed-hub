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
$stmt = $conn->prepare("SELECT id, milestones FROM afme WHERE id = ?");
$stmt->bind_param("i", $machinery_id);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
if (!$existing) {
    echo json_encode(['status' => 'error', 'message' => 'Machinery not found']);
    exit;
}

// Validate stage
$valid_stages = ['Proposal', 'Pre-Implementation', 'Procurement', 'Implementation', 'Delivered', 'Turned-Over'];
if (!in_array($stage, $valid_stages)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid stage']);
    exit;
}

// Append milestone into afme.milestones JSON
$milestones = json_decode((string)($existing['milestones'] ?? '[]'), true);
if (!is_array($milestones)) {
    $milestones = [];
}
$milestone_id = count($milestones) + 1;
$milestones[] = [
    'id' => $milestone_id,
    'stage' => $stage,
    'milestone_name' => $milestone_name,
    'target_date' => $target_date,
    'actual_date' => null,
    'remarks' => $remarks,
    'created_at' => date('Y-m-d H:i:s')
];
$milestonesJson = json_encode($milestones);

$stmt = $conn->prepare("UPDATE afme SET milestones = ? WHERE id = ?");
$stmt->bind_param("si", $milestonesJson, $machinery_id);

if ($stmt->execute()) {
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

