<?php
/**
 * Anonymous preview/download for documents that are admin-approved on approved catalog projects.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$action = $_GET['action'] ?? 'preview';
$projectId = (int) ($_GET['project_id'] ?? 0);
$projectType = strtolower(trim((string) ($_GET['project_type'] ?? '')));
$docId = (int) ($_GET['id'] ?? 0);

if ($projectId <= 0 || $docId <= 0 || $projectType === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    die('Invalid request');
}

$allowedTypes = ['fspf', 'idp', 'afme'];
if (!in_array($projectType, $allowedTypes, true)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    die('Invalid project type');
}

$stmt = $conn->prepare('SELECT id, documents, approval_status FROM projects WHERE id = ? AND project_type = ? LIMIT 1');
$stmt->bind_param('is', $projectId, $projectType);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$project || ($project['approval_status'] ?? '') !== 'Approved') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    die('Not found');
}

$docs = json_decode((string) ($project['documents'] ?? '[]'), true);
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

$mime = (string) ($found['mime_type'] ?? '');
if ($mime === '') {
    $mime = mime_content_type($path) ?: 'application/octet-stream';
}

$basename = basename((string) ($found['original_filename'] ?? basename($path)));

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
