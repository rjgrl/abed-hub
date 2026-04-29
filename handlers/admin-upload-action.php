<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../functions/helpers.php';

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

header('Location: ../admin-dashboard.php?info=upload_archive_not_supported');
exit;
