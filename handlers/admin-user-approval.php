<?php
require_once __DIR__ . '/../config/database.php';

requireLogin();
requireRoles(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin-dashboard.php');
    exit;
}

$userId = (int) ($_POST['user_id'] ?? 0);
$decision = trim($_POST['decision'] ?? '');
$rejectionReason = trim((string) ($_POST['rejection_reason'] ?? ''));
$hasUserRejectionReasonColumn = false;
$colCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'rejection_reason'");
if ($colCheck && $colCheck->num_rows > 0) {
    $hasUserRejectionReasonColumn = true;
}

if ($userId <= 0 || !in_array($decision, ['approve', 'reject'], true)) {
    header('Location: ../admin-dashboard.php?error=invalid_action');
    exit;
}

if ($decision === 'approve') {
    if ($hasUserRejectionReasonColumn) {
        $stmt = $conn->prepare("UPDATE users SET is_active = 1, role = 'employee', rejection_reason = NULL, rejected_at = NULL WHERE id = ?");
    } else {
        $stmt = $conn->prepare("UPDATE users SET is_active = 1, role = 'employee' WHERE id = ?");
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    header('Location: ../admin-dashboard.php?ok=user_approved');
    exit;
}

if ($rejectionReason === '') {
    header('Location: ../admin-dashboard.php?error=rejection_reason_required');
    exit;
}

if ($hasUserRejectionReasonColumn) {
    $stmt = $conn->prepare("UPDATE users SET is_active = 0, rejection_reason = ?, rejected_at = NOW() WHERE id = ? AND is_active = 0");
    $stmt->bind_param('si', $rejectionReason, $userId);
} else {
    $stmt = $conn->prepare("UPDATE users SET is_active = 0 WHERE id = ? AND is_active = 0");
    $stmt->bind_param('i', $userId);
}
$stmt->execute();

header('Location: ../admin-dashboard.php?ok=user_rejected');
exit;
