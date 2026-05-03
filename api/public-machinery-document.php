<?php
/**
 * Anonymous preview/download for AFME machinery documents with admin-approved review status.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$action = $_GET['action'] ?? 'preview';
$machineryId = (int) ($_GET['machinery_id'] ?? 0);
$docId = (int) ($_GET['id'] ?? 0);

if ($machineryId <= 0 || $docId <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    die('Invalid request');
}

$stmt = $conn->prepare('
    SELECT a.id, a.documents, a.project_id, p.approval_status AS project_approval
    FROM afme a
    LEFT JOIN projects p ON p.id = a.project_id
    WHERE a.id = ?
    LIMIT 1
');
$stmt->bind_param('i', $machineryId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    die('Not found');
}

$pid = (int) ($row['project_id'] ?? 0);
$pap = (string) ($row['project_approval'] ?? '');
if ($pid > 0 && $pap !== 'Approved') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    die('Not found');
}

$docs = json_decode((string) ($row['documents'] ?? '[]'), true);
if (!is_array($docs)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    die('Not found');
}

$found = null;
foreach ($docs as $doc) {
    if (!is_array($doc) || (int) ($doc['id'] ?? 0) !== $docId) {
        continue;
    }
    if ((string) ($doc['review_status'] ?? '') !== 'Approved') {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        die('This document is not published for public access.');
    }
    $found = $doc;
    break;
}

if ($found === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    die('Not found');
}

$path = (string) ($found['file_path'] ?? '');
if ($path === '' || !is_file($path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    die('File not found');
}

$mime = mime_content_type($path) ?: 'application/octet-stream';
$basename = basename((string) ($found['file_name'] ?? basename($path)));

if ($action === 'download') {
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $basename) . '"');
    header('Content-Length: ' . (string) filesize($path));
    readfile($path);
    exit;
}

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . str_replace('"', '', $basename) . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
