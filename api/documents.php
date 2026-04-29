<?php
require_once __DIR__ . '/common.php';

apiRequireAuth();

$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {
        case 'list':
            apiSuccess(listDocuments(), 'Documents retrieved');
        case 'upload':
            if ($method !== 'POST') {
                apiError('Method not allowed', 405);
            }
            apiSuccess(uploadDocument(), 'Document uploaded', 201);
        case 'download':
            downloadDocument();
        case 'preview':
            previewDocument();
        case 'delete':
            if (!in_array($method, ['DELETE', 'POST'], true)) {
                apiError('Method not allowed', 405);
            }
            apiSuccess(deleteDocument(), 'Document deleted');
        default:
            apiError('Invalid action', 400);
    }
} catch (Exception $e) {
    apiError($e->getMessage(), 400);
}

function fetchProjectForDocuments(mysqli $conn, int $projectId, ?string $projectType = null): array {
    if ($projectId <= 0) {
        throw new Exception('project_id is required');
    }

    if ($projectType !== null && $projectType !== '') {
        $type = apiValidateType($projectType);
        $stmt = $conn->prepare('SELECT id, project_type, documents FROM projects WHERE id = ? AND project_type = ?');
        $stmt->bind_param('is', $projectId, $type);
    } else {
        $stmt = $conn->prepare('SELECT id, project_type, documents FROM projects WHERE id = ?');
        $stmt->bind_param('i', $projectId);
    }
    $stmt->execute();
    $project = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$project) {
        throw new Exception('Project not found');
    }
    return $project;
}

function decodeDocuments(?string $json): array {
    $docs = json_decode((string) ($json ?? '[]'), true);
    return is_array($docs) ? $docs : [];
}

function listDocuments(): array {
    global $conn;
    $projectId = (int) ($_GET['project_id'] ?? 0);
    $projectType = $_GET['project_type'] ?? null;
    $documentType = trim((string) ($_GET['document_type'] ?? ''));
    $project = fetchProjectForDocuments($conn, $projectId, $projectType);
    $docs = decodeDocuments($project['documents'] ?? '[]');

    $out = [];
    foreach ($docs as $doc) {
        if (!is_array($doc)) {
            continue;
        }
        if ($documentType !== '' && ($doc['document_type'] ?? '') !== $documentType) {
            continue;
        }
        $out[] = $doc;
    }

    usort($out, static fn($a, $b) => strcmp((string) ($b['upload_date'] ?? ''), (string) ($a['upload_date'] ?? '')));
    return $out;
}

function uploadDocument(): array {
    global $conn;
    if (empty($_FILES['document']) && empty($_FILES['document_file'])) {
        throw new Exception('No file uploaded');
    }
    $file = $_FILES['document'] ?? $_FILES['document_file'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new Exception('Upload failed');
    }

    $projectId = (int) ($_POST['project_id'] ?? 0);
    $projectType = $_POST['project_type'] ?? null;
    $project = fetchProjectForDocuments($conn, $projectId, $projectType);

    $maxSize = defined('MAX_FILE_SIZE') ? (int) MAX_FILE_SIZE : 10 * 1024 * 1024;
    if ((int) $file['size'] > $maxSize) {
        throw new Exception('File is too large');
    }

    $allowed = [];
    if (defined('ALLOWED_IMAGE_TYPES') && is_array(ALLOWED_IMAGE_TYPES)) {
        $allowed = array_merge($allowed, ALLOWED_IMAGE_TYPES);
    }
    if (defined('ALLOWED_DOCUMENT_TYPES') && is_array(ALLOWED_DOCUMENT_TYPES)) {
        $allowed = array_merge($allowed, ALLOWED_DOCUMENT_TYPES);
    }
    if (empty($allowed)) {
        $allowed = ['application/pdf', 'image/jpeg', 'image/png'];
    }

    $mime = mime_content_type($file['tmp_name']) ?: ($file['type'] ?? '');
    if (!in_array($mime, $allowed, true)) {
        throw new Exception('Invalid file type');
    }

    $uploadRoot = defined('UPLOAD_DIR') ? UPLOAD_DIR : (__DIR__ . '/../uploads/');
    $folder = rtrim($uploadRoot, '/\\') . DIRECTORY_SEPARATOR . strtolower($project['project_type']) . DIRECTORY_SEPARATOR . 'documents' . DIRECTORY_SEPARATOR . $projectId . DIRECTORY_SEPARATOR;
    if (!is_dir($folder) && !mkdir($folder, 0755, true) && !is_dir($folder)) {
        throw new Exception('Unable to create upload directory');
    }

    $ext = pathinfo((string) $file['name'], PATHINFO_EXTENSION);
    $storedName = uniqid('doc_', true) . ($ext ? '.' . $ext : '');
    $path = $folder . $storedName;
    if (!move_uploaded_file($file['tmp_name'], $path)) {
        throw new Exception('Failed to save file');
    }

    $docs = decodeDocuments($project['documents'] ?? '[]');
    $nextId = 1;
    foreach ($docs as $d) {
        if (is_array($d) && isset($d['id']) && (int) $d['id'] >= $nextId) {
            $nextId = (int) $d['id'] + 1;
        }
    }

    $doc = [
        'id' => $nextId,
        'project_id' => $projectId,
        'project_type' => $project['project_type'],
        'document_type' => trim((string) ($_POST['document_type'] ?? $_POST['doc_type'] ?? 'General')),
        'stage' => trim((string) ($_POST['stage'] ?? '')),
        'description' => trim((string) ($_POST['description'] ?? '')),
        'original_filename' => (string) $file['name'],
        'file_path' => $path,
        'mime_type' => $mime,
        'upload_date' => date('Y-m-d H:i:s'),
        'uploaded_by' => (int) ($_SESSION['user_id'] ?? 0),
    ];
    $docs[] = $doc;

    $json = json_encode($docs);
    $stmt = $conn->prepare('UPDATE projects SET documents = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    $stmt->bind_param('si', $json, $projectId);
    if (!$stmt->execute()) {
        @unlink($path);
        throw new Exception('Failed to update project documents');
    }
    $stmt->close();

    return ['document' => $doc];
}

function findDocumentOrFail(array $docs, int $id): array {
    foreach ($docs as $idx => $doc) {
        if (is_array($doc) && (int) ($doc['id'] ?? 0) === $id) {
            return [$idx, $doc];
        }
    }
    throw new Exception('Document not found');
}

function downloadDocument(): never {
    global $conn;
    $projectId = (int) ($_GET['project_id'] ?? 0);
    $projectType = $_GET['project_type'] ?? null;
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        die('Document ID required');
    }
    $project = fetchProjectForDocuments($conn, $projectId, $projectType);
    $docs = decodeDocuments($project['documents'] ?? '[]');
    [, $doc] = findDocumentOrFail($docs, $id);
    $path = (string) ($doc['file_path'] ?? '');
    if ($path === '' || !file_exists($path)) {
        http_response_code(404);
        die('Document file not found');
    }

    header('Content-Type: ' . ($doc['mime_type'] ?? mime_content_type($path)));
    header('Content-Disposition: attachment; filename="' . basename((string) ($doc['original_filename'] ?? basename($path))) . '"');
    header('Content-Length: ' . (string) filesize($path));
    readfile($path);
    exit;
}

function previewDocument(): never {
    global $conn;
    $projectId = (int) ($_GET['project_id'] ?? 0);
    $projectType = $_GET['project_type'] ?? null;
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        die('Document ID required');
    }
    $project = fetchProjectForDocuments($conn, $projectId, $projectType);
    $docs = decodeDocuments($project['documents'] ?? '[]');
    [, $doc] = findDocumentOrFail($docs, $id);
    $path = (string) ($doc['file_path'] ?? '');
    if ($path === '' || !file_exists($path)) {
        http_response_code(404);
        die('Document file not found');
    }

    header('Content-Type: ' . ($doc['mime_type'] ?? mime_content_type($path)));
    readfile($path);
    exit;
}

function deleteDocument(): array {
    global $conn;
    $payload = apiInputJson();
    $projectId = (int) ($_GET['project_id'] ?? $payload['project_id'] ?? 0);
    $projectType = $_GET['project_type'] ?? $payload['project_type'] ?? null;
    $id = (int) ($_GET['id'] ?? $payload['id'] ?? 0);
    if ($id <= 0) {
        throw new Exception('Document ID required');
    }
    $project = fetchProjectForDocuments($conn, $projectId, $projectType);
    $docs = decodeDocuments($project['documents'] ?? '[]');
    [$idx, $doc] = findDocumentOrFail($docs, $id);

    $path = (string) ($doc['file_path'] ?? '');
    if ($path !== '' && file_exists($path)) {
        @unlink($path);
    }

    array_splice($docs, $idx, 1);
    $json = json_encode($docs);
    $stmt = $conn->prepare('UPDATE projects SET documents = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    $stmt->bind_param('si', $json, $projectId);
    if (!$stmt->execute()) {
        throw new Exception('Failed to update documents');
    }
    $stmt->close();

    return ['deleted_id' => $id];
}
