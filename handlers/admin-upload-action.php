<?php
require_once __DIR__ . '/../config/database.php';

requireLogin();
requireRoles(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin-dashboard.php');
    exit;
}

$documentId = (int) ($_POST['document_id'] ?? 0);
if ($documentId <= 0) {
    header('Location: ../admin-dashboard.php?error=invalid_document');
    exit;
}

$stmt = $conn->prepare("DELETE FROM project_documents WHERE id = ?");
$stmt->bind_param('i', $documentId);
$stmt->execute();

header('Location: ../admin-dashboard.php?ok=upload_archived');
exit;
