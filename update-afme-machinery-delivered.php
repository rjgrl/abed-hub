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
$notice_to_proceed_date = $_POST['notice_to_proceed_date'] ?? null;
$delivery_date = $_POST['delivery_date'] ?? null;
$delivery_location = sanitize($_POST['delivery_location'] ?? '');
$delivery_remarks = sanitize($_POST['delivery_remarks'] ?? '');

// Verify machinery
$stmt = $conn->prepare("SELECT id, current_status FROM afme_machinery WHERE id = ?");
$stmt->bind_param("i", $machinery_id);
$stmt->execute();
$machinery = $stmt->get_result()->fetch_assoc();

if (!$machinery) {
    echo json_encode(['status' => 'error', 'message' => 'Machinery not found']);
    exit;
}

// Update delivery
$stmt = $conn->prepare("
    INSERT INTO afme_machinery_delivery (
        machinery_id, notice_to_proceed_date, delivery_date, delivery_location, 
        delivery_status, delivery_remarks
    ) VALUES (?, ?, ?, ?, 'Delivered', ?)
    ON DUPLICATE KEY UPDATE
    notice_to_proceed_date = VALUES(notice_to_proceed_date),
    delivery_date = VALUES(delivery_date),
    delivery_location = VALUES(delivery_location),
    delivery_status = 'Delivered',
    delivery_remarks = VALUES(delivery_remarks)
");

$stmt->bind_param(
    "issss",
    $machinery_id, $notice_to_proceed_date, $delivery_date, $delivery_location, $delivery_remarks
);

if ($stmt->execute()) {
    // Update status
    $new_status = 'Delivered';
    $update_stmt = $conn->prepare("UPDATE afme_machinery SET current_status = ? WHERE id = ?");
    $update_stmt->bind_param("si", $new_status, $machinery_id);
    $update_stmt->execute();

    logAudit('UPDATE_AFME_DELIVERY', 'AFME', $machinery_id, null, $_POST);

    echo json_encode([
        'status' => 'success',
        'message' => 'Machinery marked as Delivered',
        'delivery_date' => $delivery_date
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Error updating delivery']);
}

$stmt->close();
$conn->close();
?>