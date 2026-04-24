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

if ($userId <= 0 || !in_array($decision, ['approve', 'reject'], true)) {
    header('Location: ../admin-dashboard.php?error=invalid_action');
    exit;
}

if ($decision === 'approve') {
    $stmt = $conn->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    header('Location: ../admin-dashboard.php?ok=user_approved');
    exit;
}

$stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND is_active = 0");
$stmt->bind_param('i', $userId);
$stmt->execute();

header('Location: ../admin-dashboard.php?ok=user_rejected');
exit;
