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
$stmt = $conn->prepare("SELECT id, current_status FROM afme WHERE id = ?");
$stmt->bind_param("i", $machinery_id);
$stmt->execute();
$machinery = $stmt->get_result()->fetch_assoc();

if (!$machinery) {
    echo json_encode(['status' => 'error', 'message' => 'Machinery not found']);
    exit;
}

// Update delivery fields in consolidated afme table
$stmt = $conn->prepare("
    UPDATE afme
    SET notice_to_proceed_date = ?,
        delivery_date = ?,
        delivery_location = ?,
        delivery_status = 'Delivered',
        delivery_remarks = ?,
        current_status = 'Delivered'
    WHERE id = ?
");
$stmt->bind_param("ssssi", $notice_to_proceed_date, $delivery_date, $delivery_location, $delivery_remarks, $machinery_id);

if ($stmt->execute()) {
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

